<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'value' => $this->value,
            'type' => $this->type,
            'group' => $this->group,
            // Only staff who can manage settings see the visibility flag
            // itself and full metadata; a public-settings consumer just
            // gets key/value.
            'is_public' => $this->when(
                $request->user()?->hasAnyPermission(['settings.manage']),
                $this->is_public,
            ),
        ];
    }
}
