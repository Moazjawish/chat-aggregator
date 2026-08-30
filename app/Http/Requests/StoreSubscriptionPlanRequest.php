<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionPlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules.
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:subscription_plans,name',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'price' => [
                'required',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],

            'billing_interval' => [
                'required',
                Rule::in([
                    'month',
                    'year',
                ]),
            ],

            'status' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    /**
     * Custom validation messages.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The subscription plan name is required.',
            'name.unique' => 'This subscription plan already exists.',

            'price.required' => 'The subscription plan price is required.',
            'price.numeric' => 'The price must be a number.',
            'price.min' => 'The price cannot be negative.',

            'billing_interval.required' =>
                'The billing interval is required.',

            'billing_interval.in' =>
                'The billing interval must be month or year.',
        ];
    }
}
