<?php

namespace Us\PaymentModuleManager\Contracts;

interface MercadoPagoClientInterface
{
    /**
     * Cria um pagamento no Mercado Pago.
     *
     * @throws \Exception
     *
     * @return object A resposta do pagamento do Mercado Pago.
     */
    public function createPayment(array $requestData): object;

    /**
     * Obtém os detalhes de um pagamento no Mercado Pago.
     *
     * @throws \Exception
     *
     * @return object A resposta do pagamento do Mercado Pago.
     */
    public function getPayment(string $paymentId): object;
}
