<?php

namespace App\Modules\QuestionBank\Services;

use App\Core\Support\DomainException;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Modules\QuestionBank\DTOs\QuestionData;
use Illuminate\Support\Facades\DB;

class QuestionService
{
    public const WITH = ['translations', 'options.translations', 'labels:id,name,color', 'folder:id,name'];

    public function create(QuestionData $d, ?int $importId = null): Question
    {
        $this->validateShape($d->type ?? 'mcq_single', $d->translations ?? [], $d->options ?? [], $d->numeric_answer);

        return DB::transaction(function () use ($d, $importId) {
            $cols = $d->columns() + ['type' => 'mcq_single'];
            $cols['created_by'] = auth()->id();
            $cols['import_id'] = $importId;
            $cols['hash'] = Question::hashFor($this->primaryText($d->translations));
            $q = Question::create($cols);
            $this->saveTranslations($q, $d->translations);
            $this->saveOptions($q, $d->options ?? []);
            $q->labels()->sync($d->label_ids ?? []);

            return $q->load(self::WITH);
        });
    }

    public function update(Question $q, QuestionData $d): Question
    {
        return DB::transaction(function () use ($q, $d) {
            $q->fill($d->columns());
            if ($d->translations) {
                $this->saveTranslations($q, $d->translations);
                $q->hash = Question::hashFor($this->primaryText($d->translations + $q->translations()->pluck('text', 'lang')->map(fn ($t) => ['text' => $t])->all()));
            }
            $q->save();
            if ($d->options !== null) {
                if ($q->testQuestions()->exists() && count($d->options) !== $q->options()->count()) {
                    throw new DomainException('This question is used in tests. You can edit option text and the answer, but not add or remove options.');
                }
                $this->saveOptions($q, $d->options);
            }
            if ($d->label_ids !== null) {
                $q->labels()->sync($d->label_ids);
            }
            $q->load(self::WITH);
            $this->validateShape($q->type, $q->translations->mapWithKeys(fn ($t) => [$t->lang => ['text' => $t->text]])->all(),
                $q->options->map(fn ($o) => ['is_correct' => $o->is_correct])->all(), $q->numeric_answer);

            return $q;
        });
    }

    /** Adds / replaces one language (used by "match translations" import too). */
    public function addLanguage(Question $q, string $lang, string $text, ?string $solution, array $optionTexts): void
    {
        $q->translations()->updateOrCreate(['lang' => $lang], ['text' => $text, 'solution' => $solution]);
        foreach ($q->options()->orderBy('sort')->get()->values() as $i => $opt) {
            if (isset($optionTexts[$i]) && $optionTexts[$i] !== '') {
                $opt->translations()->updateOrCreate(['lang' => $lang], ['text' => $optionTexts[$i]]);
            }
        }
    }

    public function delete(Question $q): void
    {
        if ($q->testQuestions()->whereHas('test', fn ($t) => $t->where('status', 'published'))->exists()) {
            throw new DomainException('This question is in a published test. Remove it from the test first.');
        }
        $q->delete();
    }

    /** action: move|label|unlabel|delete|difficulty */
    public function bulk(array $ids, string $action, array $d): int
    {
        $qs = Question::whereIn('id', $ids);

        return match ($action) {
            'move' => $qs->update(['folder_id' => $d['folder_id'] ?? null]),
            'difficulty' => $qs->update(['difficulty' => $d['difficulty']]),
            'label' => tap(count($ids), fn () => $qs->get()->each(fn ($q) => $q->labels()->syncWithoutDetaching($d['label_ids'] ?? []))),
            'unlabel' => tap(count($ids), fn () => $qs->get()->each(fn ($q) => $q->labels()->detach($d['label_ids'] ?? []))),
            'delete' => $qs->whereDoesntHave('testQuestions', fn ($t) => $t->whereHas('test', fn ($x) => $x->where('status', 'published')))->delete(),
            default => throw new DomainException('Unknown action.'),
        };
    }

    public function duplicateOf(string $text): ?Question
    {
        return Question::where('hash', Question::hashFor($text))->first();
    }

    private function saveTranslations(Question $q, array $translations): void
    {
        foreach ($translations as $lang => $t) {
            if (! empty($t['text'])) {
                $q->translations()->updateOrCreate(['lang' => $lang], ['text' => $t['text'], 'solution' => $t['solution'] ?? null]);
            }
        }
    }

    private function saveOptions(Question $q, array $options): void
    {
        $existing = $q->options()->orderBy('sort')->get()->values();
        foreach ($options as $i => $o) {
            /** @var QuestionOption $opt */
            $opt = $existing[$i] ?? $q->options()->make();
            $opt->fill(['sort' => $i, 'is_correct' => (bool) ($o['is_correct'] ?? false)])->save();
            foreach ((array) ($o['text'] ?? []) as $lang => $text) {
                if ($text !== null && $text !== '') {
                    $opt->translations()->updateOrCreate(['lang' => $lang], ['text' => $text]);
                }
            }
        }
        // remove extra options (only possible when the question isn't used)
        $existing->slice(count($options))->each->delete();
    }

    private function validateShape(string $type, array $translations, array $options, ?string $numeric): void
    {
        if (! collect($translations)->first(fn ($t) => ! empty($t['text']))) {
            throw new DomainException('Write the question in at least one language.');
        }
        if ($type === 'numeric') {
            if ($numeric === null || $numeric === '') {
                throw new DomainException('Enter the numeric answer.');
            }

            return;
        }
        if ($type === 'passage') {
            return;
        }
        $min = $type === 'true_false' ? 2 : 2;
        if (count($options) < $min) {
            throw new DomainException('Add at least 2 options.');
        }
        $correct = collect($options)->where('is_correct', true)->count();
        if ($correct === 0) {
            throw new DomainException('Mark the correct answer.');
        }
        if ($type === 'mcq_single' && $correct > 1) {
            throw new DomainException('Single-answer questions can have only one correct option.');
        }
    }

    private function primaryText(?array $translations): string
    {
        return (string) ($translations['en']['text'] ?? collect($translations)->first()['text'] ?? '');
    }
}
