<?php

namespace App\Modules\QuestionBank\Services;

use App\Core\Support\DomainException;
use App\Models\Label;
use App\Models\Question;
use App\Models\QuestionImport;
use App\Modules\QuestionBank\DTOs\QuestionData;
use App\Modules\QuestionBank\Import\CsvQuestionParser;
use App\Modules\QuestionBank\Import\DocxQuestionParser;
use App\Modules\QuestionBank\Import\ParsedRow;
use App\Modules\QuestionBank\Jobs\RunQuestionImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * 3 steps (same as the admin preview):
 *  1. upload()  → parse, find errors + duplicates, save a preview
 *  2. admin checks the preview
 *  3. confirm() → queued job creates the questions; errors downloadable as CSV
 */
class ImportService
{
    public function __construct(private QuestionService $questions) {}

    public function upload(UploadedFile $file, array $opts): QuestionImport
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['csv', 'txt', 'docx'], true)) {
            throw new DomainException('Upload a .csv or .docx file.');
        }
        $path = $file->store('imports', 'local');
        $import = QuestionImport::create([
            'file_path' => $path,
            'format' => $ext === 'docx' ? 'docx' : 'csv',
            'lang' => $opts['lang'] ?? 'en',
            'folder_id' => $opts['folder_id'] ?? null,
            'label_ids' => $opts['label_ids'] ?? [],
            'default_marks' => $opts['default_marks'] ?? 1,
            'default_negative' => $opts['default_negative'] ?? 0,
            'match_translations' => (bool) ($opts['match_translations'] ?? false),
            'created_by' => auth()->id(),
        ]);

        return $this->parse($import);
    }

    public function parse(QuestionImport $import): QuestionImport
    {
        $full = Storage::disk('local')->path($import->file_path);
        try {
            $rows = $import->format === 'docx'
                ? (new DocxQuestionParser)->parse($full, $import->lang)
                : (new CsvQuestionParser)->parse($full, $import->lang);
        } catch (\Throwable $e) {
            $import->update(['status' => 'failed']);
            throw new DomainException('Could not read the file: '.$e->getMessage());
        }

        foreach ($rows as $r) {
            if ($import->match_translations) {
                if (! $r->ref || ! Question::whereKey($r->ref)->exists()) {
                    $r->errors[] = 'ref must be an existing question id when adding a translation';
                }
            } elseif (! $r->errors && ($dup = $this->questions->duplicateOf($r->primaryText()))) {
                $r->duplicateOf = $dup->id;
            }
        }

        Storage::disk('local')->put($this->rowsPath($import), json_encode(array_map(fn ($r) => $r->toArray(), $rows)));
        $ready = count(array_filter($rows, fn ($r) => ! $r->errors && ! $r->duplicateOf));
        $import->update([
            'status' => 'parsed',
            'total' => count($rows),
            'ready' => $ready,
            'duplicates' => count(array_filter($rows, fn ($r) => $r->duplicateOf)),
            'failed' => count(array_filter($rows, fn ($r) => $r->errors)),
            'preview' => array_map(fn ($r) => $this->previewRow($r, $import->lang), array_slice($rows, 0, 50)),
        ]);

        return $import->fresh();
    }

    /** skip_duplicates=true (default) leaves duplicates out; false imports them anyway. */
    public function confirm(QuestionImport $import, bool $skipDuplicates = true): QuestionImport
    {
        if ($import->status !== 'parsed') {
            throw new DomainException('This import is '.$import->status.'.');
        }
        $import->update(['status' => 'importing']);
        RunQuestionImport::dispatch($import->id, $skipDuplicates);

        return $import->fresh();
    }

    /** Called by the job. */
    public function run(QuestionImport $import, bool $skipDuplicates): void
    {
        $rows = array_map([ParsedRow::class, 'fromArray'], json_decode(Storage::disk('local')->get($this->rowsPath($import)), true) ?: []);
        $labelCache = [];
        $imported = 0;
        $errors = [];

        foreach ($rows as $r) {
            if ($r->errors || ($skipDuplicates && $r->duplicateOf)) {
                if ($r->errors) {
                    $errors[] = [$r->line, implode('; ', $r->errors), strip_tags($r->primaryText())];
                }

                continue;
            }
            try {
                if ($import->match_translations) {
                    $q = Question::with('options')->findOrFail($r->ref);
                    $t = $r->translations[$import->lang] ?? reset($r->translations);
                    $this->questions->addLanguage($q, $import->lang, $t['text'], $t['solution'] ?? null, array_map(fn ($o) => $o[$import->lang] ?? '', $r->options));
                } else {
                    $labelIds = array_merge($import->label_ids ?? [], array_map(function ($name) use (&$labelCache) {
                        return $labelCache[mb_strtolower($name)] ??= Label::firstOrCreate(['name' => $name])->id;
                    }, $r->labels));
                    $this->questions->create(QuestionData::fromArray([
                        'folder_id' => $import->folder_id,
                        'type' => $r->type,
                        'difficulty' => $r->difficulty ?? 'moderate',
                        'subject' => $r->subject,
                        'topic' => $r->topic,
                        'default_marks' => $r->marks ?? $import->default_marks,
                        'default_negative' => $r->negative ?? $import->default_negative,
                        'numeric_answer' => $r->numeric,
                        'year' => $r->year,
                        'source' => $r->source,
                        'translations' => $r->translations,
                        'options' => array_map(fn ($text, $i) => ['text' => $text, 'is_correct' => in_array($i, $r->answers, true)], $r->options, array_keys($r->options)),
                        'label_ids' => array_values(array_unique($labelIds)),
                    ]), $import->id);
                }
                $imported++;
            } catch (\Throwable $e) {
                $errors[] = [$r->line, $e->getMessage(), strip_tags($r->primaryText())];
            }
        }

        $report = null;
        if ($errors) {
            $report = 'imports/errors_'.$import->id.'.csv';
            $fh = fopen('php://temp', 'w+');
            fwrite($fh, "\xEF\xBB\xBF");
            fputcsv($fh, ['Line', 'Problem', 'Question']);
            foreach ($errors as $e) {
                fputcsv($fh, $e);
            }
            rewind($fh);
            Storage::disk('local')->put($report, stream_get_contents($fh));
            fclose($fh);
        }
        $import->update(['status' => 'done', 'imported' => $imported, 'failed' => count($errors), 'error_report_path' => $report]);
    }

    /** Sample CSV teachers can download and fill. */
    public function templateCsv(): string
    {
        $rows = [
            ['ref', 'question', 'question_ml', 'option_a', 'option_b', 'option_c', 'option_d', 'option_a_ml', 'option_b_ml', 'option_c_ml', 'option_d_ml', 'answer', 'solution', 'subject', 'topic', 'difficulty', 'marks', 'negative', 'labels'],
            ['', 'Who founded the SNDP Yogam?', 'SNDP യോഗം സ്ഥാപിച്ചത് ആര്?', 'Sree Narayana Guru', 'Ayyankali', 'Chattampi Swamikal', 'Mannathu Padmanabhan', 'ശ്രീനാരായണഗുരു', 'അയ്യങ്കാളി', 'ചട്ടമ്പിസ്വാമികൾ', 'മന്നത്ത് പത്മനാഭൻ', 'A', 'Founded in 1903.', 'GK', 'Kerala Renaissance', 'easy', '1', '0.33', 'LDC 2027|PYQ'],
        ];
        $fh = fopen('php://temp', 'w+');
        fwrite($fh, "\xEF\xBB\xBF");
        foreach ($rows as $r) {
            fputcsv($fh, $r);
        }
        rewind($fh);

        return stream_get_contents($fh);
    }

    private function previewRow(ParsedRow $r, string $lang): array
    {
        return [
            'line' => $r->line,
            'question' => mb_strimwidth(strip_tags($r->translations[$lang]['text'] ?? $r->primaryText()), 0, 180, '…'),
            'languages' => array_keys($r->translations),
            'options' => array_map(fn ($o) => strip_tags($o[$lang] ?? reset($o) ?: ''), $r->options),
            'answers' => array_map(fn ($i) => chr(65 + $i), $r->answers),
            'numeric' => $r->numeric,
            'has_image' => str_contains(json_encode($r->translations).json_encode($r->options), '<img'),
            'subject' => $r->subject,
            'difficulty' => $r->difficulty,
            'labels' => $r->labels,
            'status' => $r->errors ? 'error' : ($r->duplicateOf ? 'duplicate' : 'ready'),
            'errors' => $r->errors,
            'duplicate_of' => $r->duplicateOf,
        ];
    }

    private function rowsPath(QuestionImport $i): string
    {
        return 'imports/rows_'.$i->id.'.json';
    }
}
