<?php

namespace App\Http\Requests\Media;

use Illuminate\Foundation\Http\FormRequest;

class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // CLAUDE.md §12: only authenticated, authorized staff can upload
        // product/content assets - customers never get this capability.
        return $this->user()->hasAnyPermission(['products.manage', 'content.manage']);
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            // 'image' validates the upload is genuinely decodable image
            // data (getimagesize), not just a correctly-named file
            // (CLAUDE.md §12) - mimes/max add a server-side MIME allow-list
            // and a hard size cap on top of that.
            'file' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }
}
