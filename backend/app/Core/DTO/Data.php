<?php

namespace App\Core\DTO;

use Illuminate\Http\Request;

/**
 * Base DTO. Subclasses declare public readonly properties and a static fromRequest().
 * toArray() returns only the fields that were provided (null means "not sent"),
 * so PATCH requests update only what changed.
 */
abstract class Data
{
    /** @var list<string> */
    protected array $provided = [];

    abstract public static function fromRequest(Request $request): static;

    protected static function pick(Request $request, array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            if ($request->exists($k)) {
                $out[$k] = $request->input($k);
            }
        }

        return $out;
    }

    public function withProvided(array $keys): static
    {
        $this->provided = $keys;

        return $this;
    }

    public function toArray(): array
    {
        $all = get_object_vars($this);
        unset($all['provided']);
        if (! $this->provided) {
            return array_filter($all, fn ($v) => $v !== null);
        }

        return array_intersect_key($all, array_flip($this->provided));
    }
}
