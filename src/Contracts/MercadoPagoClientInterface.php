<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 28/10/2025 20:43:21
*/

namespace UendelSilveira\PaymentModuleManager\Contracts;

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
