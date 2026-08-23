<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** CLAUDE.md §4: fields here are gated per-field, not just per-route - see `notes`/`tags` below, both invisible to the customer themselves and to any unauthorized staff role. */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->whenLoaded('user', fn () => $this->user->name),
            'email' => $this->whenLoaded('user', fn () => $this->user->email),
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'marketing_opt_in' => $this->marketing_opt_in,
            // Staff-internal annotations - never shown to the customer themselves.
            'notes' => $this->when(
                $request->user()?->hasAnyPermission(['customers.manage']),
                $this->notes,
            ),
            // Structured, admin-curated labels (bounded vocabulary, no free
            // text) - lower sensitivity than the free-text notes above, so
            // customers.view alone is enough to read them (useful for
            // support triage), same as the rest of the profile.
            'tags' => $this->when(
                $request->user()?->hasAnyPermission(['customers.view', 'customers.manage']),
                fn () => CustomerTagResource::collection($this->whenLoaded('tags')),
            ),
            'addresses' => AddressResource::collection($this->whenLoaded('addresses')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
