<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ChatFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'model_id' => [
                'required',
                'integer',
                'exists:models,id',
            ],

            'message' => [
                'required',
                'string',
                'min:1',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'model_id.required' => 'The model is required.',
            'model_id.exists' => 'The selected model does not exist.',
            'message.required' => 'The message is required.',
            'message.string' => 'The message must be a string.',
        ];
    }
}
