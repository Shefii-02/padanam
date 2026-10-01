<?php

namespace App\Modules\QuestionBank\Import;

/**
 * CSV / Excel-saved-as-CSV. Header names (case-insensitive):
 *   ref, question, option_a … option_f, answer, solution, type, subject, topic, difficulty, marks, negative, labels, year, source
 * Other languages: add a suffix → question_ml, option_a_ml, solution_ml (any code: _hi, _ta …)
 * Columns without a suffix are in the import language chosen on upload.
 */
class CsvQuestionParser
{
    /** @return ParsedRow[] */
    public function parse(string $path, string $lang): array
    {
        $fh = fopen($path, 'r');
        $first = fgets($fh);
        rewind($fh);
        if (str_starts_with($first, "\xEF\xBB\xBF")) {
            fread($fh, 3);
        }
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/\s+/', '_', (string) $h))), fgetcsv($fh) ?: []);
        $rows = [];
        $line = 1;
        while (($cells = fgetcsv($fh)) !== false) {
            $line++;
            if (! array_filter($cells, fn ($c) => trim((string) $c) !== '')) {
                continue;
            }
            $data = [];
            foreach ($header as $i => $h) {
                $data[$h] = trim((string) ($cells[$i] ?? ''));
            }
            $rows[] = $this->row($line, $data, $lang)->finalize();
        }
        fclose($fh);

        return $rows;
    }

    private function row(int $line, array $d, string $lang): ParsedRow
    {
        $r = new ParsedRow($line);
        $d = array_map(fn ($v) => $v === '' ? null : $v, $d);
        foreach ($d as $col => $val) {
            if ($val === null) {
                continue;
            }
            [$base, $l] = $this->split($col, $lang);
            if ($base === 'question') {
                $r->translations[$l]['text'] = nl2br(e($val), false);
            } elseif ($base === 'solution' || $base === 'explanation') {
                $r->translations[$l]['solution'] = nl2br(e($val), false);
            } elseif (preg_match('/^option_?([a-f])$/', $base, $m)) {
                $r->options[ord($m[1]) - 97][$l] = e($val);
            }
        }
        ksort($r->options);
        $r->options = array_values($r->options);

        $answer = (string) ($d['answer'] ?? $d['correct'] ?? $d['correct_answer'] ?? '');
        if (($d['type'] ?? '') === 'numeric' || (is_numeric($answer) && empty($r->options))) {
            $r->numeric = $answer;
            $r->type = 'numeric';
        } else {
            $r->answers = ParsedRow::letters($answer);
        }
        $r->ref = isset($d['ref']) && ctype_digit($d['ref']) ? (int) $d['ref'] : null;
        $r->subject = $d['subject'] ?? null;
        $r->topic = $d['topic'] ?? null;
        $r->difficulty = isset($d['difficulty']) ? strtolower($d['difficulty']) : null;
        $r->marks = isset($d['marks']) && is_numeric($d['marks']) ? (float) $d['marks'] : null;
        $r->negative = isset($d['negative']) && is_numeric($d['negative']) ? (float) $d['negative'] : null;
        $r->labels = isset($d['labels']) ? array_values(array_filter(array_map('trim', preg_split('/[|,;]/', $d['labels'])))) : [];
        $r->year = isset($d['year']) && ctype_digit($d['year']) ? (int) $d['year'] : null;
        $r->source = $d['source'] ?? null;

        return $r;
    }

    /** "option_a_ml" → ["option_a", "ml"], "question" → ["question", $default] */
    private function split(string $col, string $default): array
    {
        if (preg_match('/^(question|solution|explanation|option_?[a-f])_([a-z]{2})$/', $col, $m)) {
            return [$m[1], $m[2]];
        }

        return [$col, $default];
    }
}
