<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ResponseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $content = $this->input('content', []);

        $decoded = collect($content)->map(function ($item) {
            if (is_array($item)) {
                return $item; // already an array, e.g. sent as a native JSON body
            }

            $value = json_decode($item, true);

            // null on failure lets the 'array' rule below reject it cleanly
            // instead of the service throwing a TypeError downstream.
            return json_last_error() === JSON_ERROR_NONE ? $value : null;
        });

        $this->merge(['content' => $decoded->all()]);
    }

    public function rules(): array
    {
        return [
            'copy_id'             => ['required', 'integer', 'exists:copies,id'],
            'content'             => ['required', 'array', 'min:1'],
            'is_completed'        => ['required', 'boolean'],
            'image'               => ['nullable', 'array'],
            'image.*'             => ['nullable', 'array']
        ];
    }
}
