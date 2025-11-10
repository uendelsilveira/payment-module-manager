<?php

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
        $accessToken = $settingsRepository->get('mercadopago_access_token', Config::get('payment.gateways.mercadopago.access_token'));

        if (in_array($accessToken, [null, '', '0'], true)) {
            throw new InvalidConfigurationException('Mercado Pago access token não configurado.');
        }

        $this->accessToken = $accessToken;
        $this->httpClient = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Authorization' => 'Bearer '.$this->accessToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'http_errors' => false,
        ]);
    }

    public function createPayment(float $amount, array $data): array
    {
        $requestData = array_merge($data, ['transaction_amount' => $amount]);

        return $this->sendRequest('POST', '/v1/payments', ['json' => $requestData]);
    }

    public function getPayment(string $paymentId): array
    {
        return $this->sendRequest('GET', '/v1/payments/'.$paymentId);
    }

    public function getPaymentMethods(): array
    {
        $response = $this->sendRequest('GET', '/v1/payment_methods');

        // Convert array items to objects as required by interface and reindex
        return array_values(array_map(fn ($item): object => (object) $item, $response));
    }

    public function refundPayment(string $paymentId, ?float $amount = null): array
    {
        $payload = $amount ? ['amount' => $amount] : [];

        return $this->sendRequest('POST', '/v1/payments/'.$paymentId.'/refunds', ['json' => $payload]);
    }

    public function cancelPayment(string $paymentId): array
    {
        return $this->sendRequest('PUT', '/v1/payments/'.$paymentId, ['json' => ['status' => 'cancelled']]);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws PaymentGatewayException|ExternalServiceException
     *
     * @return array<string, mixed>
     */
    private function sendRequest(string $method, string $uri, array $options = []): array
    {
        try {
            $response = $this->httpClient->request($method, $uri, $options);
            $body = json_decode($response->getBody()->getContents(), true);

            if ($response->getStatusCode() >= 400) {
                $message = is_array($body) && isset($body['message']) && is_string($body['message']) ? $body['message'] : 'Erro desconhecido';
                Log::error('Erro na API do Mercado Pago: '.$message, [
                    'status_code' => $response->getStatusCode(),
                    'content' => $body,
                    'uri' => $uri,
                ]);

                throw new PaymentGatewayException('Erro na API do Mercado Pago: '.$message, $response->getStatusCode());
            }

            return is_array($body) ? $body : [];
        } catch (GuzzleException $guzzleException) {
            Log::error('Erro de conexão Guzzle: '.$guzzleException->getMessage());

            throw new ExternalServiceException('Erro de conexão com o gateway de pagamento: '.$guzzleException->getMessage());
        }
    }
}
