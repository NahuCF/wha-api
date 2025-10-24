<?php

namespace App\Http\Requests;

use App\Enums\BillingCycle;
use App\Enums\PlanType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CreateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_type' => ['required', new Enum(PlanType::class)],
            'billing_cycle' => ['required', new Enum(BillingCycle::class)],
            'extra_users' => [
                'nullable', 
                'integer', 
                'min:0', 
                'max:100',
                function ($attribute, $value, $fail) {
                    if ($value > 0 && $this->plan_type === PlanType::GROWTH->value) {
                        $fail('The Growth plan does not allow extra users.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'plan_type.required' => 'The plan type is required',
            'plan_type.enum' => 'Invalid plan type. Must be one of: growth, scale, pro',
            'billing_cycle.required' => 'The billing cycle is required',
            'billing_cycle.enum' => 'Invalid billing cycle. Must be one of: monthly, yearly',
            'extra_users.integer' => 'Extra users must be a valid number',
            'extra_users.min' => 'Extra users cannot be negative',
            'extra_users.max' => 'You cannot purchase more than 100 extra users at once',
        ];
    }
}
