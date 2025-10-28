<?php

namespace Us\PaymentModuleManager\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;
use Us\PaymentModuleManager\Contracts\MercadoPagoClientInterface;

class MercadoPagoClient implements MercadoPagoClientInterface
{
    protected PaymentClient $client;

    public function __construct()
    {
        $accessToken = Config::get('payment.gateways.mercadopago.access_token');

        if (empty($accessToken)) {
            throw new \InvalidArgumentException('Mercado Pago access token não configurado.');
        }

        MercadoPagoConfig::setAccessToken($accessToken);
        $this->client = new PaymentClient;
    }

    /**
     * Cria um pagamento no Mercado Pago.
     *
     * @throws \Exception
     */
    public function createPayment(array $requestData): object
    {
        try {
            return $this->client->create($requestData);
        } catch (MPApiException $e) {
            Log::error('Erro na API do Mercado Pago ao criar pagamento: '.$e->getMessage(), [
                'status_code' => $e->getApiResponse()->getStatusCode(),
                'content' => $e->getApiResponse()->getContent(),
            ]);

            throw new \Exception('Erro ao criar pagamento com Mercado Pago: '.$e->getMessage());
        } catch (\Exception $e) {
            Log::error('Erro inesperado ao criar pagamento no Mercado Pago: '.$e->getMessage());

            throw new \Exception('Erro inesperado ao criar pagamento: '.$e->getMessage());
        }
    }

    /**
     * Obtém os detalhes de um pagamento no Mercado Pago.
     *
     * @throws \Exception
     */
    public function getPayment(string $paymentId): object
    {
        try {
            return $this->client->get($paymentId);
        } catch (MPApiException $e) {
            Log::error('Erro na API do Mercado Pago ao obter pagamento: '.$e->getMessage(), [
                'status_code' => $e->getApiResponse()->getStatusCode(),
                'content' => $e->getApiResponse()->getContent(),
            ]);

            throw new \Exception('Erro ao obter pagamento com Mercado Pago: '.$e->getMessage());
        } catch (\Exception $e) {
            Log::error('Erro inesperado ao obter pagamento no Mercado Pago: '.$e->getMessage());

            throw new \Exception('Erro inesperado ao obter pagamento: '.$e->getMessage());
        }
    }
}
