<?php

namespace App\Modules\Catalog\DTOs;

use App\Core\DTO\Data;
use Illuminate\Http\Request;

final class CategoryData extends Data
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?int $parent_id = null,
        public readonly ?string $icon = null,
        public readonly ?string $color = null,
        public readonly ?int $sort = null,
        public readonly ?bool $is_active = null,
    ) {}

    public static function fromRequest(Request $r): static
    {
        return (new self(
            name: $r->input('name'),
            parent_id: $r->input('parent_id'),
            icon: $r->input('icon'),
            color: $r->input('color'),
            sort: $r->input('sort'),
            is_active: $r->has('is_active') ? $r->boolean('is_active') : null,
        ))->withProvided(array_keys($r->only(['name', 'parent_id', 'icon', 'color', 'sort', 'is_active'])));
    }
}
