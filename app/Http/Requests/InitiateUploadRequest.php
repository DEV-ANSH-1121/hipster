<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InitiateUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filename' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string'],
            'total_size' => ['required', 'integer', 'min:1'],
            'chunk_size' => ['required', 'integer', 'min:1'],
        ];
    }
}
