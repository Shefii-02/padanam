<?php

namespace App\Modules\Tests\Services;

use App\Core\Support\DomainException;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\OmrSheet;
use App\Models\Test;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;

/**
 * OMR exams (offline centres / printed papers):
 *  - print blank OMR sheets (PDF) and the answer key
 *  - import a scanner CSV (phone,Q1,Q2,…) OR key in one student's bubbles
 *  - students can upload a photo of their sheet from the app → a teacher reviews and enters the bubbles
 * Every sheet becomes a normal attempt, so results, ranks and analytics are the same as online tests.
 */
class OmrService
{
    public function __construct(private ScoringService $scoring) {}

    /** Question no → ["A"] / ["A","C"] / "12" */
    public function answerKey(Test $test): array
    {
        $key = [];
        foreach ($this->ordered($test) as $no => $tq) {
            $q = $tq->question;
            $key[$no] = $q->type === 'numeric' ? $q->numeric_answer
                : $q->options->values()->filter(fn ($o) => $o->is_correct)->keys()->map(fn ($i) => chr(65 + $i))->values()->all();
        }

        return $key;
    }

    public function sheetPdf(Test $test)
    {
        $count = $test->total_questions ?: $test->testQuestions()->count();
        $options = max(4, (int) $test->testQuestions()->with('question.options')->get()->max(fn ($tq) => $tq->question->options->count()));
        $html = view()->exists('omr.sheet') ? view('omr.sheet', compact('test', 'count', 'options'))->render() : $this->sheetHtml($test, $count, $options);

        return Pdf::loadHTML($html)->setPaper('a4');
    }

    /** One student's bubbles. answers: {"1":"A","2":"B,D","3":"","4":"12"} */
    public function enter(Test $test, User $student, array $answers, ?OmrSheet $sheet = null): Attempt
    {
        if ($test->mode !== 'omr') {
            throw new DomainException('This test is not an OMR test.');
        }
        $ordered = $this->ordered($test);

        $attempt = Attempt::where('test_id', $test->id)->where('user_id', $student->id)->where('status', '!=', 'in_progress')->first()
            ?? Attempt::create(['test_id' => $test->id, 'user_id' => $student->id, 'status' => 'submitted', 'language' => 'en', 'started_at' => now(), 'submitted_at' => now()]);
        $attempt->answers()->delete();

        foreach ($ordered as $no => $tq) {
            $raw = trim((string) ($answers[$no] ?? $answers[(string) $no] ?? ''));
            if ($raw === '') {
                continue;
            }
            $opts = $tq->question->options->values();
            $selected = $tq->question->type === 'numeric'
                ? [$raw]
                : collect(preg_split('/[\s,]+/', strtoupper($raw)))->map(fn ($l) => $opts[ord($l) - 65]->id ?? null)->filter()->values()->all();
            AttemptAnswer::create(['attempt_id' => $attempt->id, 'test_question_id' => $tq->id, 'selected' => $selected, 'visited' => true]);
        }
        $attempt = $this->scoring->evaluate($attempt->fresh('test'));

        if ($sheet) {
            $sheet->update(['attempt_id' => $attempt->id, 'parsed_answers' => $answers, 'status' => 'evaluated', 'evaluated_by' => auth()->id()]);
        } else {
            OmrSheet::updateOrCreate(['test_id' => $test->id, 'user_id' => $student->id], [
                'attempt_id' => $attempt->id, 'parsed_answers' => $answers, 'status' => 'evaluated', 'evaluated_by' => auth()->id(),
            ]);
        }

        return $attempt;
    }

    /**
     * Scanner CSV: first column phone (or user id), then Q1…Qn.
     * Returns {evaluated, not_found:[phones], errors:[]}
     */
    public function importCsv(Test $test, UploadedFile $file): array
    {
        $fh = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($fh);
        $done = 0;
        $missing = [];
        $errors = [];
        $line = 1;
        while (($row = fgetcsv($fh)) !== false) {
            $line++;
            $id = trim((string) array_shift($row));
            if ($id === '') {
                continue;
            }
            $phone = \App\Core\Support\Phone::normalize($id);
            $student = $phone ? User::firstWhere('phone', $phone) : User::find((int) $id);
            if (! $student) {
                $missing[] = $id;

                continue;
            }
            try {
                $answers = [];
                foreach ($row as $i => $v) {
                    $answers[$i + 1] = $v;
                }
                $this->enter($test, $student, $answers);
                $done++;
            } catch (\Throwable $e) {
                $errors[] = "Line $line: ".$e->getMessage();
            }
        }
        fclose($fh);
        $this->scoring->rerank($test);

        return ['evaluated' => $done, 'not_found' => $missing, 'errors' => $errors];
    }

    /** From the app: student uploads a photo of the filled sheet. */
    public function uploadPhoto(Test $test, User $student, UploadedFile $photo): OmrSheet
    {
        if ($test->mode !== 'omr') {
            throw new DomainException('This test is not an OMR test.');
        }

        return OmrSheet::updateOrCreate(['test_id' => $test->id, 'user_id' => $student->id], [
            'image_path' => $photo->store('omr/'.$test->id, 'public'),
            'status' => 'needs_review',
        ]);
    }

    /** @return array<int, \App\Models\TestQuestion> question no (1-based) → test question */
    private function ordered(Test $test): array
    {
        $test->loadMissing('sections.testQuestions.question.options');
        $out = [];
        $n = 0;
        foreach ($test->sections as $s) {
            foreach ($s->testQuestions as $tq) {
                $out[++$n] = $tq;
            }
        }

        return $out;
    }

    private function sheetHtml(Test $test, int $count, int $options): string
    {
        $cols = 4;
        $perCol = (int) ceil($count / $cols);
        $letters = array_map(fn ($i) => chr(65 + $i), range(0, $options - 1));
        $bubble = fn ($l) => '<span style="display:inline-block;width:15px;height:15px;border:1px solid #333;border-radius:50%;font-size:8px;text-align:center;line-height:15px;margin:0 2px">'.$l.'</span>';
        $html = '<html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:11px}td{padding:2px 6px;vertical-align:top}.box{border:1px solid #333;padding:8px;margin-bottom:8px}</style></head><body>';
        $html .= '<h3 style="margin:0">'.e($test->title).' — OMR Answer Sheet</h3>';
        $html .= '<div class="box">Name: ______________________________ &nbsp; Mobile: ____________________ &nbsp; Centre: __________</div>';
        $html .= '<div style="font-size:9px;margin-bottom:6px">Use a blue/black ball pen. Fill the circle completely. Do not fold the sheet.</div><table><tr>';
        for ($c = 0; $c < $cols; $c++) {
            $html .= '<td>';
            for ($i = $c * $perCol + 1; $i <= min($count, ($c + 1) * $perCol); $i++) {
                $html .= '<div style="margin:3px 0"><b style="display:inline-block;width:22px">'.$i.'</b>'.implode('', array_map($bubble, $letters)).'</div>';
            }
            $html .= '</td>';
        }

        return $html.'</tr></table></body></html>';
    }
}
