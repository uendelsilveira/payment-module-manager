<?php

namespace Us\PaymentModuleManager\Gateways;

use Us\PaymentModuleManager\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Str;

class PayPalStrategy implements PaymentGatewayInterface
{
    /**
     * Processa uma cobrança usando a API (simulada) do PayPal.
     *
     * @param float $amount
     * @param array $data
     * @return array
     */
    public function charge(float $amount, array $data): array
    {
        // Em um cenário real, você usaria o SDK do PayPal ou o cliente HTTP
        // para fazer uma requisição para a API deles.

        // --- SIMULAÇÃO --- 
        // Simula uma pequena latência de rede.
        usleep(600000); // 0.6 segundos

        // Simula uma resposta de sucesso da API do PayPal.
        return [
            'id' => 'PP' . Str::uuid(), // ID de transação externo simulado
            'status' => 'COMPLETED', // Status simulado, PayPal usa 'COMPLETED'
            'purchase_units' => [
                [
                    'amount' => [
                        'currency_code' => 'BRL',
                        'value' => $amount,
                    ],
                    'description' => $data['description'],
                ]
            ],
            'create_time' => now()->toIso8601String(),
        ];
    }
}
