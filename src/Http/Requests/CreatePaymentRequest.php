<?php

namespace Us\PaymentModuleManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Us\PaymentModuleManager\Enums\PaymentGateway;

class CreatePaymentRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Em um cenário real, você pode querer verificar se o usuário autenticado
        // tem permissão para criar uma cobrança. Por enquanto, vamos permitir a todos.
        return true;
    }

    /**
     * Retorna as regras de validação que se aplicam à requisição.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string', Rule::in([
                PaymentGateway::MERCADOPAGO,
                PaymentGateway::PAGSEGURO,
                PaymentGateway::PAYPAL,
                PaymentGateway::STRIPE,
            ])],
            'description' => ['required', 'string', 'max:255'],
        ];
    }
}
