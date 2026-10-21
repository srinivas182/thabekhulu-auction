<?php

namespace App\Domains\Theme\Http\Requests;

use App\Domains\Theme\Support\ThemePalette;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveThemeRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $hex = ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];
        $rules = [
            'fonts' => ['required', Rule::in(array_keys(ThemePalette::PAIRINGS))],
            'radius' => ['required', Rule::in(array_keys(ThemePalette::RADII))],
            'logo_light' => ['nullable', 'file', 'max:1024', 'mimes:svg,png,webp'],
            'logo_dark' => ['nullable', 'file', 'max:1024', 'mimes:svg,png,webp'],
            'favicon' => ['nullable', 'file', 'max:1024', 'mimes:svg,png,webp'],
            'remove' => ['array'],
            'remove.*' => [Rule::in(['logo_light', 'logo_dark', 'favicon'])],
        ];

        foreach (ThemePalette::MODES as $mode) {
            foreach (array_keys(ThemePalette::COLOURS) as $key) {
                $rules["{$mode}.{$key}"] = $hex;
            }
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['*.*.regex' => __('Use a 6-digit hex colour, for example #173b33.')];
    }

    /**
     * Colour, font and corner values only; unknown keys (such as status
     * colours) are dropped.
     *
     * @return array{light: array<string, string>, dark: array<string, string>, fonts: string, radius: string}
     */
    public function themeValues(): array
    {
        $colours = fn (string $mode): array => collect(array_keys(ThemePalette::COLOURS))
            ->mapWithKeys(fn (string $key): array => [$key => strtolower((string) $this->input("{$mode}.{$key}"))])
            ->all();

        return [
            'light' => $colours('light'),
            'dark' => $colours('dark'),
            'fonts' => (string) $this->input('fonts'),
            'radius' => (string) $this->input('radius'),
        ];
    }
}
