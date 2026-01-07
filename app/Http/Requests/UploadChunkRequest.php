<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadChunkRequest extends FormRequest
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
            'uuid' => ['required', 'string', 'uuid'],
            'chunk_index' => ['required', 'integer', 'min:0'],
            'chunk_data' => ['required', 'string'],
        ];
    }
}
