<?php

namespace App\Http\Requests\Categories;

use Illuminate\Foundation\Http\FormRequest;

class UploadCategoryImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyPermission(['categories.manage']);
    }

    public function rules(): array
    {
        return [
            // 'image' validates genuinely decodable image data
            // (getimagesize), not just a correctly-named file - mimes/max
            // add a server-side MIME allow-list and a hard size cap
            // (CLAUDE.md §12), same posture as StoreMediaRequest.
            'file' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ];
    }
}
