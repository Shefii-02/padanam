<?php

namespace App\Modules\QuestionBank\Import;

/** One question parsed from a file, before saving. */
final class ParsedRow
{
    public array $translations = [];   // lang => [text, solution]
    public array $options = [];        // [ [lang => text] ]
    public array $answers = [];        // option indexes (0-based)
    public ?string $numeric = null;
    public ?string $type = null;
    public ?string $subject = null;
    public ?string $topic = null;
    public ?string $difficulty = null;
    public ?float $marks = null;
    public ?float $negative = null;
    public array $labels = [];          // label names
    public ?int $ref = null;            // existing question id (match translations)
    public ?int $year = null;
    public ?string $source = null;
    public array $errors = [];
    public ?int $duplicateOf = null;

    public function __construct(public int $line) {}

    public function finalize(): self
    {
        $this->type ??= $this->numeric !== null && ! $this->options ? 'numeric' : (count($this->answers) > 1 ? 'mcq_multi' : 'mcq_single');
        if (! array_filter(array_column($this->translations, 'text'))) {
            $this->errors[] = 'Question text is empty';
        }
        if ($this->type !== 'numeric' && ! $this->ref) {
            if (count($this->options) < 2) {
                $this->errors[] = 'Needs at least 2 options';
            }
            if (! $this->answers) {
                $this->errors[] = 'Answer is missing or not A–F';
            }
            foreach ($this->answers as $a) {
                if (! isset($this->options[$a])) {
                    $this->errors[] = 'Answer points to a missing option';
                    break;
                }
            }
        }
        if ($this->difficulty && ! in_array($this->difficulty, ['easy', 'moderate', 'hard'], true)) {
            $this->difficulty = match (strtolower($this->difficulty)) { 'e', 'simple' => 'easy', 'h', 'difficult', 'tough' => 'hard', default => 'moderate' };
        }

        return $this;
    }

    public function primaryText(): string
    {
        return (string) ($this->translations['en']['text'] ?? (reset($this->translations)['text'] ?? ''));
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public static function fromArray(array $a): self
    {
        $r = new self($a['line']);
        foreach ($a as $k => $v) {
            $r->{$k} = $v;
        }

        return $r;
    }

    /** "A", "b", "1", "A,C", "A & C" → [0] / [0,2] */
    public static function letters(string $answer): array
    {
        preg_match_all('/\b([A-Fa-f]|[1-6])\b/', $answer, $m);

        return array_values(array_unique(array_map(fn ($x) => ctype_digit($x) ? (int) $x - 1 : ord(strtoupper($x)) - 65, $m[1])));
    }
}
