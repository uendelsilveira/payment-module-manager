<?php

namespace Us\PaymentModuleManager\Gateways;

use Us\PaymentModuleManager\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Str;

class PagSeguroStrategy implements PaymentGatewayInterface
{
    /**
     * Processa uma cobrança usando a API (simulada) do PagSeguro.
     *
     * @param float $amount
     * @param array $data
     * @return array
     */
    public function charge(float $amount, array $data): array
    {
        // Em um cenário real, você usaria o SDK do PagSeguro ou o cliente HTTP
        // para fazer uma requisição para a API deles.

        // --- SIMULAÇÃO --- 
        // Simula uma pequena latência de rede.
        usleep(450000); // 0.45 segundos

        // Simula uma resposta de sucesso da API do PagSeguro.
        return [
            'id' => 'PS' . Str::uuid(), // ID de transação externo simulado
            'status' => 'approved', // Status simulado
            'amount' => [
                'value' => $amount,
                'currency' => 'BRL',
            ],
            'description' => $data['description'],
            'created_at' => now()->toIso8601String(),
        ];
    }
}
