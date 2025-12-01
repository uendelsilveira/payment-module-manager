<?php

namespace UendelSilveira\PaymentModuleManager\Contracts;

use MercadoPago\Exceptions\MPApiException;

interface MercadoPagoClientInterface
{
    /**
     * Cria um pagamento.
     *
     * @param array<string, mixed> $request
     *
     * @throws MPApiException
     */
    public function create(array $request): object;

    /**
     * Obtém os detalhes de um pagamento.
     *
     * @throws MPApiException
     */
    public function get(int $id): object;

    /**
     * Cancela um pagamento.
     *
     * @throws MPApiException
     */
    public function cancel(int $id): object;
}
