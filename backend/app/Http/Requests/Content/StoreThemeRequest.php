<?php

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `config`'s shape (background, primary/secondary colors, typography, font
 * sizes, buttons) is validated here rather than left as an opaque `array`,
 * so a theme created through the API can't silently be missing the fields
 * the storefront actually reads to render it.
 */
class StoreThemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyPermission(['content.manage']);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', 'unique:themes,slug'],
            'is_active' => ['sometimes', 'boolean'],

            'config' => ['required', 'array'],
            'config.background' => ['required', 'string', 'max:20'],
            'config.surface' => ['required', 'string', 'max:20'],
            'config.text_color' => ['required', 'string', 'max:20'],
            'config.primary_color' => ['required', 'string', 'max:20'],
            'config.secondary_color' => ['required', 'string', 'max:20'],

            'config.typography' => ['required', 'array'],
            'config.typography.font_family' => ['required', 'string', 'max:255'],
            'config.typography.heading_font_family' => ['required', 'string', 'max:255'],

            'config.font_sizes' => ['required', 'array'],
            'config.font_sizes.sm' => ['required', 'string', 'max:10'],
            'config.font_sizes.base' => ['required', 'string', 'max:10'],
            'config.font_sizes.lg' => ['required', 'string', 'max:10'],
            'config.font_sizes.xl' => ['required', 'string', 'max:10'],
            'config.font_sizes.2xl' => ['required', 'string', 'max:10'],

            'config.buttons' => ['required', 'array'],
            'config.buttons.radius' => ['required', 'string', 'max:10'],
            'config.buttons.primary_bg' => ['required', 'string', 'max:20'],
            'config.buttons.primary_text' => ['required', 'string', 'max:20'],
            'config.buttons.secondary_bg' => ['required', 'string', 'max:20'],
            'config.buttons.secondary_text' => ['required', 'string', 'max:20'],
        ];
    }
}
