<?php

namespace App\Modules\QuestionBank\DTOs;

use App\Core\DTO\Data;
use Illuminate\Http\Request;

/**
 * translations: { en: {text, solution}, ml: {text, solution} }
 * options:      [ { is_correct: bool, text: { en: "...", ml: "..." } } ]
 */
final class QuestionData extends Data
{
    public function __construct(
        public readonly ?int $folder_id = null,
        public readonly ?string $type = null,
        public readonly ?string $difficulty = null,
        public readonly ?string $subject = null,
        public readonly ?string $topic = null,
        public readonly ?float $default_marks = null,
        public readonly ?float $default_negative = null,
        public readonly ?string $numeric_answer = null,
        public readonly ?string $source = null,
        public readonly ?int $year = null,
        public readonly ?int $passage_id = null,
        public readonly ?array $translations = null,
        public readonly ?array $options = null,
        public readonly ?array $label_ids = null,
    ) {}

    public static function fromRequest(Request $r): static
    {
        $keys = ['folder_id', 'type', 'difficulty', 'subject', 'topic', 'default_marks', 'default_negative', 'numeric_answer',
            'source', 'year', 'passage_id', 'translations', 'options', 'label_ids'];

        return (new self(...array_map(fn ($k) => $r->input($k), array_combine($keys, $keys))))->withProvided(array_keys($r->only($keys)));
    }

    public static function fromArray(array $a): self
    {
        return new self(
            folder_id: $a['folder_id'] ?? null, type: $a['type'] ?? 'mcq_single', difficulty: $a['difficulty'] ?? 'moderate',
            subject: $a['subject'] ?? null, topic: $a['topic'] ?? null, default_marks: $a['default_marks'] ?? 1, default_negative: $a['default_negative'] ?? 0,
            numeric_answer: $a['numeric_answer'] ?? null, source: $a['source'] ?? null, year: $a['year'] ?? null, passage_id: null,
            translations: $a['translations'] ?? [], options: $a['options'] ?? [], label_ids: $a['label_ids'] ?? [],
        );
    }

    public function columns(): array
    {
        return array_diff_key($this->toArray(), array_flip(['translations', 'options', 'label_ids']));
    }
}
