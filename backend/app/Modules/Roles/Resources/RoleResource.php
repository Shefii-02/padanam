<?php

namespace App\Modules\Roles\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'label' => $this->label ?? ucfirst(str_replace('_', ' ', $this->name)),
            'is_system' => (bool) $this->is_system,
            'users_count' => $this->users_count ?? null,
            'permissions' => $this->name === 'super_admin' ? ['*'] : $this->whenLoaded('permissions', fn () => $this->permissions->pluck('name')),
        ];
    }
}
