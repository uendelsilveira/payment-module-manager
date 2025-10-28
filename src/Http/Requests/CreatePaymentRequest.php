<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:22
*/

namespace UendelSilveira\PaymentModuleManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\In;
use UendelSilveira\PaymentModuleManager\Enums\PaymentGateway;

class CreatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string', new In([PaymentGateway::MERCADOPAGO])],
            'description' => ['required', 'string', 'max:255'],
            'payer_email' => ['required', 'email', 'max:255'], // Adicionado para o Mercado Pago
        ];
    }
}
