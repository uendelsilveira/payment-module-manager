<?php

namespace Us\PaymentModuleManager\Gateways;

use Us\PaymentModuleManager\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Str;

class StripeStrategy implements PaymentGatewayInterface
{
    /**
     * Processa uma cobrança usando a API (simulada) do Stripe.
     *
     * @param float $amount
     * @param array $data
     * @return array
     */
    public function charge(float $amount, array $data): array
    {
        // Em um cenário real, você usaria o SDK do Stripe ou o cliente HTTP
        // para fazer uma requisição para a API deles.

        // --- SIMULAÇÃO --- 
        // Simula uma pequena latência de rede.
        usleep(550000); // 0.55 segundos

        // Simula uma resposta de sucesso da API do Stripe.
        return [
            'id' => 'pi_' . Str::random(24), // ID de PaymentIntent simulado
            'object' => 'payment_intent',
            'status' => 'succeeded', // Status simulado
            'amount' => $amount * 100, // Stripe usa centavos
            'currency' => 'brl',
            'description' => $data['description'],
            'created' => now()->timestamp,
        ];
    }
}
