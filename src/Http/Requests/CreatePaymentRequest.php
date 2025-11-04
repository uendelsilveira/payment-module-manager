<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:21
*/

namespace UendelSilveira\PaymentModuleManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use UendelSilveira\PaymentModuleManager\Services\CurrencyService;
use UendelSilveira\PaymentModuleManager\Services\MonetaryLimitsValidator;

class CreatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Define os métodos de pagamento aceitos
        $paymentMethods = ['pix', 'credit_card', 'boleto'];

        return [
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                function ($attribute, $value, $fail) {
                    $validator = app(MonetaryLimitsValidator::class);
                    $gateway = $this->input('method');
                    $paymentMethod = $this->input('payment_method_id');

                    $error = $validator->getValidationError($value, $gateway, $paymentMethod);
                    if ($error) {
                        $fail($error);
                    }
                },
            ],
            'method' => ['required', 'string', Rule::in(['mercadopago'])], // Gateway principal
            'currency' => [
                'sometimes',
                'string',
                'size:3',
                function ($attribute, $value, $fail) {
                    $currencyService = app(CurrencyService::class);
                    if (!$currencyService->isSupported($value)) {
                        $supported = implode(', ', array_keys($currencyService->getSupportedCurrencies()));
                        $fail("Currency {$value} is not supported. Supported: {$supported}");
                    }
                },
            ],
            'description' => ['required', 'string', 'max:255'],
            'payer_email' => ['required', 'email', 'max:255'],

            // Método de pagamento específico (PIX, Cartão, Boleto, etc.)
            'payment_method_id' => ['required', 'string', Rule::in($paymentMethods)],

            // Campos específicos para Cartão de Crédito
            'token' => ['required_if:payment_method_id,credit_card', 'string'],
            // Parcelamento: min:1 já está ok, mas podemos adicionar um max se o MP tiver limite
            'installments' => ['required_if:payment_method_id,credit_card', 'integer', 'min:1', 'max:12'], // Adicionado max:12 como exemplo
            'issuer_id' => ['required_if:payment_method_id,credit_card', 'string'],

            // Dados adicionais do pagador, importantes para cartão de crédito e boleto
            'payer' => ['sometimes', 'array'],
            'payer.first_name' => ['required_if:payment_method_id,credit_card', 'string'],
            'payer.last_name' => ['required_if:payment_method_id,credit_card', 'string'],
            'payer.identification.type' => ['required_if:payment_method_id,credit_card', 'string'],
            'payer.identification.number' => ['required_if:payment_method_id,credit_card', 'string'],

            // Campos específicos para Boleto (ex: dados de identificação do pagador)
            'payer.identification.type' => ['required_if:payment_method_id,boleto', 'string'],
            'payer.identification.number' => ['required_if:payment_method_id,boleto', 'string'],
            'payer.address.zip_code' => ['required_if:payment_method_id,boleto', 'string'],
            'payer.address.street_name' => ['required_if:payment_method_id,boleto', 'string'],
            'payer.address.street_number' => ['required_if:payment_method_id,boleto', 'string'],
            'payer.address.neighborhood' => ['required_if:payment_method_id,boleto', 'string'],
            'payer.address.city' => ['required_if:payment_method_id,boleto', 'string'],
            'payer.address.federal_unit' => ['required_if:payment_method_id,boleto', 'string'],
        ];
    }
}
