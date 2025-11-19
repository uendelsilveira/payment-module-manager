<?php

namespace UendelSilveira\PaymentModuleManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RefundRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Implement authorization logic here.
        // For now, assuming true for demonstration.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => 'nullable|numeric|min:0.01',
            'reason' => 'nullable|string|max:255',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.numeric' => 'O valor do estorno deve ser um número.',
            'amount.min' => 'O valor do estorno deve ser no mínimo :min.',
            'reason.string' => 'A razão do estorno deve ser uma string.',
            'reason.max' => 'A razão do estorno não pode exceder :max caracteres.',
        ];
    }
}
