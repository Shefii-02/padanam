<?php

namespace App\Modules\Users\DTOs;

use App\Core\DTO\Data;
use Illuminate\Http\Request;

/** The 9-step setup in the app. */
final class ProfileSetupData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $avatar,
        public readonly ?string $gender,
        public readonly ?string $dob,
        public readonly ?string $district,
        public readonly ?string $state,
        public readonly ?string $town,
        public readonly ?string $pincode,
        public readonly ?string $qualification,
        public readonly array $exams,          // [category_slug|id, ...]
        public readonly array $target_posts,   // {category: post}
        public readonly ?string $level,
        public readonly ?string $aim,
        public readonly ?string $attempt,
        public readonly int $study_hours,
        public readonly array $study_days,
        public readonly ?string $study_slot,
        public readonly bool $reminder,
        public readonly ?string $language,
    ) {}

    public static function fromRequest(Request $r): static
    {
        return new self(
            name: trim((string) $r->input('name')),
            avatar: $r->input('avatar'),
            gender: $r->input('gender'),
            dob: $r->input('dob'),
            district: $r->input('district'),
            state: $r->input('state', 'Kerala'),
            town: $r->input('town'),
            pincode: $r->input('pincode'),
            qualification: $r->input('qualification'),
            exams: (array) $r->input('exams', []),
            target_posts: (array) $r->input('target_posts', []),
            level: $r->input('level'),
            aim: $r->input('aim'),
            attempt: $r->input('attempt'),
            study_hours: (int) $r->input('study_hours', 2),
            study_days: array_map('boolval', (array) $r->input('study_days', [true, true, true, true, true, true, false])),
            study_slot: $r->input('study_slot'),
            reminder: $r->boolean('reminder', true),
            language: $r->input('language'),
        );
    }
}
