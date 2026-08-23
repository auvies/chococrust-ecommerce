<?php

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;

class UploadHeroBannerImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyPermission(['content.manage']);
    }

    public function rules(): array
    {
        return [
            // Desktop/mobile are genuinely different crops, not one image
            // scaled - at least one is required so this endpoint always
            // does something, but neither is required on its own (an admin
            // replacing just the mobile crop shouldn't have to resend
            // desktop too).
            'desktop_image' => ['required_without:mobile_image', 'nullable', 'image', 'mimes:jpeg,png,webp', 'max:8192'],
            'mobile_image' => ['required_without:desktop_image', 'nullable', 'image', 'mimes:jpeg,png,webp', 'max:8192'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ];
    }
}
