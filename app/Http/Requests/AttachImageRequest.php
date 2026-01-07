<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttachImageRequest extends FormRequest
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
            'upload_uuid' => ['required', 'string', 'uuid'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'set_as_primary' => ['sometimes', 'boolean'],
        ];
    }
}
