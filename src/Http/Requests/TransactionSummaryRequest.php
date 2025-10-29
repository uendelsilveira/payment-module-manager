<?php

namespace UendelSilveira\PaymentModuleManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransactionSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'A data final deve ser igual ou posterior à data inicial.',
        ];
    }
}
