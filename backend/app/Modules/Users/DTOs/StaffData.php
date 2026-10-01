<?php

namespace App\Modules\Users\DTOs;

use App\Core\DTO\Data;
use App\Core\Support\Phone;
use Illuminate\Http\Request;

final class StaffData extends Data
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
        public readonly ?string $password = null,
        public readonly ?string $role = null,
        public readonly ?string $designation = null,
        public readonly ?array $subjects = null,
        public readonly ?bool $is_teacher = null,
        public readonly ?array $course_ids = null,
    ) {}

    public static function fromRequest(Request $r): static
    {
        $d = new self(
            name: $r->input('name'),
            email: $r->input('email'),
            phone: Phone::normalize($r->input('phone')),
            password: $r->input('password'),
            role: $r->input('role'),
            designation: $r->input('designation'),
            subjects: $r->input('subjects'),
            is_teacher: $r->has('is_teacher') ? $r->boolean('is_teacher') : null,
            course_ids: $r->input('course_ids'),
        );

        return $d->withProvided(array_keys($r->only(['name', 'email', 'phone', 'password', 'role', 'designation', 'subjects', 'is_teacher', 'course_ids'])));
    }
}
