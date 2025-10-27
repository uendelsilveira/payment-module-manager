<?php

namespace Us\PaymentModuleManager\Contracts;

interface MercadoPagoClientInterface
{
    /**
     * Cria um pagamento no Mercado Pago.
     *
     * @param array $requestData
     * @return object A resposta do pagamento do Mercado Pago.
     * @throws \Exception
     */
    public function createPayment(array $requestData): object;

    /**
     * Obtém os detalhes de um pagamento no Mercado Pago.
     *
     * @param string $paymentId
     * @return object A resposta do pagamento do Mercado Pago.
     * @throws \Exception
     */
    public function getPayment(string $paymentId): object;
}
