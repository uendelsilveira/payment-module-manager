<?php

namespace Us\PaymentModuleManager\Gateways;

use Us\PaymentModuleManager\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http; // Usaremos o cliente HTTP do Laravel
use Illuminate\Support\Str;

class MercadoPagoStrategy implements PaymentGatewayInterface
{
    /**
     * Processa uma cobrança usando a API (simulada) do Mercado Pago.
     *
     * @param float $amount
     * @param array $data
     * @return array
     * @throws \Illuminate\Http\Client\RequestException
     */
    public function charge(float $amount, array $data): array
    {
        // Em um cenário real, você usaria o SDK do Mercado Pago ou o cliente HTTP
        // para fazer uma requisição para a API deles.
        // $accessToken = config('payment.gateways.mercadopago.access_token');
        //
        // $response = Http::withToken($accessToken)->post('https://api.mercadopago.com/v1/payments', [
        //     'transaction_amount' => $amount,
        //     'description' => $data['description'],
        //     // ... outros dados necessários
        // ]);
        //
        // $response->throw(); // Lança exceção se a requisição falhar
        //
        // return $response->json();

        // --- SIMULAÇÃO --- 
        // Para fins de desenvolvimento sem credenciais reais, vamos simular uma resposta bem-sucedida.
        
        // Simula uma pequena latência de rede.
        usleep(500000); // 0.5 segundos

        // Simula uma resposta de sucesso da API do Mercado Pago.
        return [
            'id' => 'MP' . Str::uuid(), // ID de transação externo simulado
            'status' => 'approved', // Status simulado
            'transaction_amount' => $amount,
            'description' => $data['description'],
            'payment_method_id' => 'pix',
            'date_created' => now()->toIso8601String(),
        ];
    }
}
