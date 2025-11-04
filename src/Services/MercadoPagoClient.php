<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 04/11/2025 16:09:38
*/

namespace UendelSilveira\PaymentModuleManager\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use UendelSilveira\PaymentModuleManager\Contracts\MercadoPagoClientInterface;
use UendelSilveira\PaymentModuleManager\Contracts\SettingsRepositoryInterface;
use UendelSilveira\PaymentModuleManager\Exceptions\ExternalServiceException;
use UendelSilveira\PaymentModuleManager\Exceptions\InvalidConfigurationException;
use UendelSilveira\PaymentModuleManager\Exceptions\PaymentGatewayException;

class MercadoPagoClient implements MercadoPagoClientInterface
{
    protected Client $httpClient;

    protected string $accessToken;

    protected string $baseUrl;

    public function __construct(SettingsRepositoryInterface $settingsRepository)
    {
        $this->baseUrl = Config::get('payment.gateways.mercadopago.base_url', 'https://api.mercadopago.com');

        $this->accessToken = $settingsRepository->get(
            'mercadopago_access_token',
            Config::get('payment.gateways.mercadopago.access_token')
        );

        if (empty($this->accessToken)) {
            throw new InvalidConfigurationException('Mercado Pago access token não configurado.');
        }

        $this->httpClient = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Authorization' => 'Bearer '.$this->accessToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'http_errors' => false, // Não lança exceções para 4xx ou 5xx, permite verificar a resposta
        ]);
    }

    public function createPayment(array $requestData): object
    {
        try {
            $response = $this->httpClient->post('/v1/payments', [
                'json' => $requestData,
            ]);

            $body = json_decode($response->getBody()->getContents());

            if ($response->getStatusCode() >= 400) {
                Log::error('Erro na API do Mercado Pago ao criar pagamento.', [
                    'status_code' => $response->getStatusCode(),
                    'content' => $body,
                    'request_data' => $requestData,
                ]);

                throw new PaymentGatewayException('Erro ao criar pagamento com Mercado Pago: '.($body->message ?? 'Erro desconhecido'), $response->getStatusCode());
            }

            return $body;
        } catch (GuzzleException $e) {
            Log::error('Erro de conexão Guzzle ao criar pagamento no Mercado Pago: '.$e->getMessage());

            throw new ExternalServiceException('Erro de conexão ao criar pagamento: '.$e->getMessage());
        } catch (\Exception $e) {
            Log::error('Erro inesperado ao criar pagamento no Mercado Pago: '.$e->getMessage());

            throw new PaymentGatewayException('Erro inesperado ao criar pagamento: '.$e->getMessage(), 500);
        }
    }

    public function getPayment(string $paymentId): object
    {
        try {
            $response = $this->httpClient->get('/v1/payments/'.$paymentId);

            $body = json_decode($response->getBody()->getContents());

            if ($response->getStatusCode() >= 400) {
                Log::error('Erro na API do Mercado Pago ao obter pagamento.', [
                    'status_code' => $response->getStatusCode(),
                    'content' => $body,
                    'payment_id' => $paymentId,
                ]);

                throw new PaymentGatewayException('Erro ao obter pagamento com Mercado Pago: '.($body->message ?? 'Erro desconhecido'), $response->getStatusCode());
            }

            return $body;
        } catch (GuzzleException $e) {
            Log::error('Erro de conexão Guzzle ao obter pagamento no Mercado Pago: '.$e->getMessage());

            throw new ExternalServiceException('Erro de conexão ao obter pagamento: '.$e->getMessage());
        } catch (\Exception $e) {
            Log::error('Erro inesperado ao obter pagamento no Mercado Pago: '.$e->getMessage());

            throw new PaymentGatewayException('Erro inesperado ao obter pagamento: '.$e->getMessage(), 500);
        }
    }
}
