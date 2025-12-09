<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 19/11/2025 14:24:12
*/

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

    /**
     * @param array<string, mixed> $config
     */
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
            $creator = $this->customCreators[$name];
            $result = $creator($config);

            if (! $result instanceof PaymentGatewayInterface) {
                throw new InvalidArgumentException(sprintf('Custom creator for gateway [%s] did not return a PaymentGatewayInterface instance.', $name));
            }

            /** @var PaymentGatewayInterface $result */
            return $result;
        }

        // 2. Tenta instanciar a classe definida na configuração do gateway
        if (isset($config['class']) && is_string($config['class']) && class_exists($config['class'])) {
            $gatewayClass = $config['class'];

            $instance = new $gatewayClass($config);

            if (! $instance instanceof PaymentGatewayInterface) {
                throw new InvalidArgumentException(sprintf('Gateway class [%s] does not implement PaymentGatewayInterface.', $gatewayClass));
            }

            /** @var PaymentGatewayInterface $instance */
            return $instance;
        }

        // 3. Se nada funcionar, o gateway não é suportado ou configurado corretamente
        throw new InvalidArgumentException(sprintf('Gateway [%s] is not supported or its class is not defined in configuration.', $name));
    }

    /**
     * Get the configuration for a gateway.
     *
     * @throws InvalidArgumentException
     *
     * @return array<string, mixed>
     */
    protected function getGatewayConfig(string $name): array
    {
        $gateways = is_array($this->config['gateways'] ?? null) ? $this->config['gateways'] : [];
        $config = $gateways[$name] ?? null;

        if (! is_array($config)) {
            throw new InvalidArgumentException(sprintf('Gateway [%s] is not configured.', $name));
        }

        // Garantir que é array<string, mixed>
        /** @var array<string, mixed> $config */
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

        if (! is_string($default) || $default === '') {
            throw new InvalidArgumentException('No default payment gateway has been specified.');
        }

        // Validar que o gateway padrão existe na configuração
        $gateways = is_array($this->config['gateways'] ?? null) ? $this->config['gateways'] : [];

        if (! array_key_exists($default, $gateways)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Default gateway "%s" is not configured. Available gateways: %s',
                    $default,
                    implode(', ', array_keys($gateways))
                )
            );
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

    /**
     * Check if a gateway is configured.
     */
    public function isGatewayConfigured(string $name): bool
    {
        $gateways = is_array($this->config['gateways'] ?? null) ? $this->config['gateways'] : [];

        return array_key_exists($name, $gateways);
    }

    /**
     * Get list of configured gateway names.
     *
     * @return array<int, string>
     */
    public function getConfiguredGateways(): array
    {
        $gateways = is_array($this->config['gateways'] ?? null) ? $this->config['gateways'] : [];

        return array_keys($gateways);
    }
}
