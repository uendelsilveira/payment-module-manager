<?php

namespace UendelSilveira\PaymentModuleManager;

use InvalidArgumentException;
use UendelSilveira\PaymentModuleManager\Contracts\PaymentGatewayInterface;

class PaymentGatewayManager
{
    /**
     * The array of resolved gateway instances.
     *
     * @var array<string, PaymentGatewayInterface>
     */
    protected array $gateways = [];

    /**
     * The registered custom gateway creators.
     *
     * @var array<string, callable>
     */
    protected array $customCreators = [];

    public function __construct(protected array $config) {}

    /**
     * Get a payment gateway instance.
     *
     * @param string|null $name The name of the gateway, or null for the default gateway.
     *
     * @throws InvalidArgumentException
     */
    public function gateway(?string $name = null): PaymentGatewayInterface
    {
        $name = $name ?: $this->getDefaultGateway();

        if (! isset($this->gateways[$name])) {
            $this->gateways[$name] = $this->resolve($name);
        }

        return $this->gateways[$name];
    }

    /**
     * Resolve the given gateway.
     *
     * @throws InvalidArgumentException
     */
    public function resolve(string $name): PaymentGatewayInterface
    {
        $config = $this->getGatewayConfig($name);

        // 1. Tenta usar um criador customizado (definido via extend)
        if (isset($this->customCreators[$name])) {
            return $this->customCreators[$name]($config);
        }

        // 2. Tenta instanciar a classe definida na configuração do gateway
        if (isset($config['class']) && class_exists($config['class'])) {
            $gatewayClass = $config['class'];

            return new $gatewayClass($config);
        }

        // 3. Se nada funcionar, o gateway não é suportado ou configurado corretamente
        throw new InvalidArgumentException(sprintf('Gateway [%s] is not supported or its class is not defined in configuration.', $name));
    }

    /**
     * Get the configuration for a gateway.
     *
     * @throws InvalidArgumentException
     */
    protected function getGatewayConfig(string $name): array
    {
        $config = $this->config['gateways'][$name] ?? null;

        if (is_null($config)) {
            throw new InvalidArgumentException(sprintf('Gateway [%s] is not configured.', $name));
        }

        return $config;
    }

    /**
     * Get the default gateway name.
     *
     * @throws InvalidArgumentException
     */
    public function getDefaultGateway(): string
    {
        $default = $this->config['default_gateway'] ?? null;

        if (is_null($default)) {
            throw new InvalidArgumentException('No default payment gateway has been specified.');
        }

        return $default;
    }

    /**
     * Set the default gateway name.
     */
    public function setDefaultGateway(string $name): void
    {
        $this->config['default_gateway'] = $name;
    }

    /**
     * Register a custom gateway creator Closure.
     *
     * @return $this
     */
    public function extend(string $driver, \Closure $callback): static
    {
        $this->customCreators[$driver] = $callback;

        return $this;
    }
}
