<?php

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateThemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyPermission(['content.manage']);
    }

    public function rules(): array
    {
        $theme = $this->route('theme');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'alpha_dash', Rule::unique('themes', 'slug')->ignore($theme->id)],

            // A full config replacement, same shape as creation - editing a
            // theme is expected to send the whole config back (the admin
            // UI's own preview panel already holds it all), not a partial
            // patch that could leave the JSON in a half-valid shape.
            'config' => ['sometimes', 'array'],
            'config.background' => ['required_with:config', 'string', 'max:20'],
            'config.surface' => ['required_with:config', 'string', 'max:20'],
            'config.text_color' => ['required_with:config', 'string', 'max:20'],
            'config.primary_color' => ['required_with:config', 'string', 'max:20'],
            'config.secondary_color' => ['required_with:config', 'string', 'max:20'],

            'config.typography' => ['required_with:config', 'array'],
            'config.typography.font_family' => ['required_with:config', 'string', 'max:255'],
            'config.typography.heading_font_family' => ['required_with:config', 'string', 'max:255'],

            'config.font_sizes' => ['required_with:config', 'array'],
            'config.font_sizes.sm' => ['required_with:config', 'string', 'max:10'],
            'config.font_sizes.base' => ['required_with:config', 'string', 'max:10'],
            'config.font_sizes.lg' => ['required_with:config', 'string', 'max:10'],
            'config.font_sizes.xl' => ['required_with:config', 'string', 'max:10'],
            'config.font_sizes.2xl' => ['required_with:config', 'string', 'max:10'],

            'config.buttons' => ['required_with:config', 'array'],
            'config.buttons.radius' => ['required_with:config', 'string', 'max:10'],
            'config.buttons.primary_bg' => ['required_with:config', 'string', 'max:20'],
            'config.buttons.primary_text' => ['required_with:config', 'string', 'max:20'],
            'config.buttons.secondary_bg' => ['required_with:config', 'string', 'max:20'],
            'config.buttons.secondary_text' => ['required_with:config', 'string', 'max:20'],
        ];
    }
}
