<?php

namespace App\Modules\Tests\Services;

use App\Core\Support\DomainException;
use App\Models\Question;
use App\Models\Test;
use App\Models\TestQuestion;
use App\Models\TestSection;
use App\Modules\QuestionBank\Repositories\QuestionRepository;
use Illuminate\Support\Facades\DB;

/** Builds tests: sections with their own marks / negative / time, questions picked by hand or by label rules. */
class TestBuilderService
{
    public function __construct(private QuestionRepository $questions) {}

    public function create(array $d): Test
    {
        return DB::transaction(function () use ($d) {
            $sections = $d['sections'] ?? [['name' => 'Section 1']];
            unset($d['sections']);
            $d['created_by'] = auth()->id();
            $test = Test::create($d);
            foreach ($sections as $i => $s) {
                $this->saveSection($test, $s + ['sort' => $i]);
            }

            return $test->load('sections');
        });
    }

    public function update(Test $test, array $d): Test
    {
        if ($test->attempts()->exists() && array_intersect(array_keys($d), ['duration_min', 'sectional_timing'])) {
            // allowed, but warn in logs – students mid-attempt keep their original timer
            logger()->info("Test {$test->id} timing changed after attempts started");
        }
        $test->update($d);

        return $test->fresh('sections');
    }

    public function saveSection(Test $test, array $d, ?TestSection $s = null): TestSection
    {
        $s ??= new TestSection(['test_id' => $test->id]);
        $s->fill(array_intersect_key($d, array_flip(['name', 'short_name', 'sort', 'duration_min', 'marks_per_question', 'negative_per_question', 'en_only'])));
        $s->sort ??= (int) $test->sections()->max('sort') + 1;
        $s->save();
        $test->refreshTotals();

        return $s;
    }

    public function deleteSection(TestSection $s): void
    {
        if ($s->test->attempts()->exists()) {
            throw new DomainException('Students have attempted this test. Sections can no longer be removed.');
        }
        $s->delete();
        $s->test->refreshTotals();
    }

    /** Hand-picked question ids → section. Skips ones already in the test. */
    public function addQuestions(Test $test, ?int $sectionId, array $questionIds): int
    {
        $this->assertEditable($test);
        $this->assertSection($test, $sectionId);
        $existing = $test->testQuestions()->pluck('question_id')->all();
        $sort = (int) $test->testQuestions()->max('sort');
        $added = 0;
        foreach (array_diff(array_unique($questionIds), $existing) as $qid) {
            TestQuestion::create(['test_id' => $test->id, 'section_id' => $sectionId, 'question_id' => $qid, 'sort' => ++$sort]);
            $added++;
        }
        $test->refreshTotals();

        return $added;
    }

    /**
     * Random pick by rules, e.g. 25 questions from label "LDC 2027" in folder "GK": 10 easy / 10 moderate / 5 hard.
     * rule: {label_ids[], folder_id, subject, topic, count, mix:{easy,moderate,hard}, exclude_used_in_tests: bool}
     */
    public function autoPick(Test $test, ?int $sectionId, array $rule): array
    {
        $this->assertEditable($test);
        $this->assertSection($test, $sectionId);
        $base = $this->questions->filtered([
            'label_ids' => $rule['label_ids'] ?? [], 'folder_id' => $rule['folder_id'] ?? null, 'include_sub' => true,
            'subject' => $rule['subject'] ?? null, 'topic' => $rule['topic'] ?? null, 'not_in_test' => $test->id,
            'lang' => $rule['lang'] ?? null,
        ])->when(! empty($rule['exclude_used_in_tests']), fn ($q) => $q->doesntHave('testQuestions'));

        $mix = array_filter($rule['mix'] ?? []);
        $picked = [];
        $short = [];
        if ($mix) {
            foreach ($mix as $level => $n) {
                $ids = (clone $base)->where('difficulty', $level)->inRandomOrder()->limit((int) $n)->pluck('id')->all();
                if (count($ids) < $n) {
                    $short[$level] = $n - count($ids);
                }
                $picked = array_merge($picked, $ids);
            }
        } else {
            $count = (int) ($rule['count'] ?? 10);
            $picked = (clone $base)->inRandomOrder()->limit($count)->pluck('id')->all();
            if (count($picked) < $count) {
                $short['any'] = $count - count($picked);
            }
        }
        $added = $this->addQuestions($test, $sectionId, $picked);

        return ['added' => $added, 'short' => $short];
    }

    public function removeQuestions(Test $test, array $testQuestionIds): void
    {
        $this->assertEditable($test);
        $test->testQuestions()->whereIn('id', $testQuestionIds)->delete();
        $test->refreshTotals();
    }

    /** items: [{id, section_id, sort, marks?, negative?}] */
    public function arrange(Test $test, array $items): void
    {
        DB::transaction(function () use ($test, $items) {
            foreach ($items as $i) {
                $test->testQuestions()->whereKey($i['id'])->update(array_filter([
                    'sort' => $i['sort'] ?? null,
                    'section_id' => $i['section_id'] ?? null,
                ], fn ($v) => $v !== null) + array_intersect_key($i, array_flip(['marks', 'negative'])));
            }
        });
        $test->refreshTotals();
    }

    public function publish(Test $test): Test
    {
        $test->loadCount('testQuestions');
        if ($test->test_questions_count === 0) {
            throw new DomainException('Add questions before publishing.');
        }
        if ($test->sections()->has('testQuestions', '=', 0)->exists()) {
            throw new DomainException('One section has no questions. Add questions or delete the section.');
        }
        if ($test->sectional_timing && $test->sections()->whereNull('duration_min')->exists()) {
            throw new DomainException('Set a time for every section (sectional timing is on).');
        }
        $bad = Question::whereIn('id', $test->testQuestions()->pluck('question_id'))
            ->whereIn('type', ['mcq_single', 'mcq_multi', 'true_false'])
            ->whereDoesntHave('options', fn ($o) => $o->where('is_correct', true))->pluck('id');
        if ($bad->isNotEmpty()) {
            throw new DomainException('These questions have no correct answer: #'.$bad->implode(', #'));
        }
        $test->update(['status' => 'published']);
        $test->refreshTotals();

        return $test->fresh('sections');
    }

    public function duplicate(Test $test, string $title): Test
    {
        return DB::transaction(function () use ($test, $title) {
            $test->load('sections.testQuestions');
            $copy = $test->replicate(['status', 'result_published_at']);
            $copy->fill(['title' => $title, 'status' => 'draft', 'created_by' => auth()->id()])->save();
            foreach ($test->sections as $s) {
                $ns = $s->replicate();
                $ns->test_id = $copy->id;
                $ns->save();
                foreach ($s->testQuestions as $tq) {
                    $n = $tq->replicate();
                    $n->test_id = $copy->id;
                    $n->section_id = $ns->id;
                    $n->save();
                }
            }
            $copy->refreshTotals();

            return $copy->load('sections');
        });
    }

    private function assertEditable(Test $test): void
    {
        if ($test->attempts()->whereNotNull('submitted_at')->exists()) {
            throw new DomainException('Students have already submitted this test. Duplicate it to make changes.');
        }
    }

    private function assertSection(Test $test, ?int $sectionId): void
    {
        if ($sectionId && ! $test->sections()->whereKey($sectionId)->exists()) {
            throw new DomainException('That section belongs to another test.');
        }
    }
}
