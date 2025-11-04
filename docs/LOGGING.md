# Sistema de Logging Estruturado

O payment-module-manager implementa um sistema de logging estruturado que facilita o rastreamento e debugging de operações de pagamento.

## Características

- **Logging estruturado**: Todos os logs incluem contexto rico em formato estruturado
- **Correlation IDs**: Rastreamento de operações relacionadas através de IDs de correlação
- **Múltiplos canais**: Canais separados para diferentes tipos de operações (payment, webhook, gateway, transaction)
- **Mascaramento de dados sensíveis**: Proteção automática de informações sensíveis como tokens e senhas
- **Métricas de performance**: Rastreamento automático de duração de operações

## Canais de Log

O sistema utiliza quatro canais principais:

### 1. Payment Channel
Para operações gerais de pagamento.
```php
Log::channel('payment')->info('Payment processed', $context);
```

### 2. Webhook Channel
Para processamento de webhooks.
```php
Log::channel('webhook')->info('Webhook received', $context);
```

### 3. Gateway Channel
Para comunicação com gateways de pagamento.
```php
Log::channel('gateway')->info('Gateway charge initiated', $context);
```

### 4. Transaction Channel
Para operações específicas de transações.
```php
Log::channel('transaction')->info('Transaction created', $context);
```

## LogContext Helper

A classe `LogContext` fornece uma interface fluente para construir contexto de log estruturado.

### Exemplo Básico

```php
use UendelSilveira\PaymentModuleManager\Support\LogContext;
use Illuminate\Support\Facades\Log;

$context = LogContext::create()
    ->withCorrelationId()
    ->withGateway('mercadopago')
    ->withAmount(100.00)
    ->withPaymentMethod('pix')
    ->maskSensitiveData();

Log::channel('payment')->info('Payment initiated', $context->toArray());
```

### Métodos Disponíveis

#### Identificação e Rastreamento

```php
// Adicionar ID de correlação (gerado automaticamente se não fornecido)
->withCorrelationId(?string $correlationId = null)

// Adicionar ID de requisição
->withRequestId(?string $requestId = null)
```

#### Contexto de Transação

```php
// Adicionar contexto completo de transação
->withTransaction(Transaction $transaction)

// Adicionar apenas ID da transação
->withTransactionId($transactionId)

// Adicionar ID externo (do gateway)
->withExternalId(string $externalId)
```

#### Contexto de Pagamento

```php
// Adicionar gateway
->withGateway(string $gateway)

// Adicionar método de pagamento
->withPaymentMethod(string $paymentMethod)

// Adicionar valor
->withAmount(float $amount)
```

#### Contexto de Webhook

```php
// Adicionar informações de webhook
->withWebhook(array $data)
```

#### Contexto de Erro

```php
// Adicionar informações de exceção
->withError(\Throwable $exception)
```

#### Contexto de Retry

```php
// Adicionar informações de tentativa
->withRetry(int $attempt, int $maxAttempts)
```

#### Contexto de Usuário

```php
// Adicionar informações do usuário
->withUser($user)
```

#### Métricas de Performance

```php
// Adicionar duração da operação
->withDuration(float $startTime)
```

#### Campos Customizados

```php
// Adicionar campo único
->with(string $key, $value)

// Adicionar múltiplos campos
->withMany(array $data)
```

#### Segurança

```php
// Mascarar dados sensíveis
->maskSensitiveData()
```

## Exemplo Completo

```php
use UendelSilveira\PaymentModuleManager\Support\LogContext;
use Illuminate\Support\Facades\Log;

public function processPayment(array $data): Transaction
{
    $startTime = microtime(true);
    $correlationId = LogContext::create()->withCorrelationId()->toArray()['correlation_id'];
    
    $context = LogContext::create()
        ->withCorrelationId($correlationId)
        ->withGateway($data['method'])
        ->withAmount($data['amount'])
        ->withPaymentMethod($data['payment_method_id'] ?? 'unknown')
        ->withRequestId()
        ->maskSensitiveData();
    
    Log::channel('payment')->info('Starting payment processing', $context->toArray());
    
    try {
        // Processar pagamento...
        $transaction = $this->createTransaction($data);
        
        $context->withTransaction($transaction)
            ->withDuration($startTime);
        
        Log::channel('payment')->info('Payment processed successfully', $context->toArray());
        
        return $transaction;
        
    } catch (\Throwable $e) {
        $context->withError($e)
            ->withDuration($startTime);
        
        Log::channel('payment')->error('Payment processing failed', $context->toArray());
        
        throw $e;
    }
}
```

## Configuração

### Variáveis de Ambiente

Configure os níveis de log através de variáveis de ambiente:

```env
# Canal padrão
PAYMENT_LOG_CHANNEL=payment

# Níveis de log por canal
PAYMENT_LOG_LEVEL=info
PAYMENT_WEBHOOK_LOG_LEVEL=info
PAYMENT_GATEWAY_LOG_LEVEL=info
PAYMENT_TRANSACTION_LOG_LEVEL=info
```

### Níveis de Log Disponíveis

- `emergency`: Sistema inutilizável
- `alert`: Ação deve ser tomada imediatamente
- `critical`: Condições críticas
- `error`: Erros que não param a aplicação
- `warning`: Avisos (deprecated, poor usage, etc)
- `notice`: Eventos normais mas significativos
- `info`: Eventos informativos
- `debug`: Informações detalhadas de debug

### Campos Sensíveis

Os seguintes campos são automaticamente mascarados quando `maskSensitiveData()` é chamado:

- `token`
- `access_token`
- `password`
- `card_number`
- `cvv`
- `security_code`
- `webhook_secret`

Você pode adicionar mais campos editando `config/logging.php`:

```php
'sensitive_fields' => [
    'token',
    'access_token',
    'password',
    // Adicione seus campos aqui
],
```

## Localização dos Logs

Os logs são armazenados em:

- `storage/logs/payment.log` - Logs gerais de pagamento
- `storage/logs/payment-webhook.log` - Logs de webhook
- `storage/logs/payment-gateway.log` - Logs de gateway
- `storage/logs/payment-transaction.log` - Logs de transação

Todos os canais usam rotação diária e mantêm logs por 14-30 dias dependendo do canal.

## Melhores Práticas

1. **Use IDs de correlação**: Sempre inclua correlation IDs para rastrear operações relacionadas
2. **Meça duração**: Use `withDuration()` para medir performance de operações críticas
3. **Mascare dados sensíveis**: Sempre chame `maskSensitiveData()` antes de logar contexto com dados de usuário
4. **Use canais apropriados**: Direcione logs para o canal correto para facilitar análise
5. **Inclua contexto rico**: Quanto mais contexto, mais fácil o debugging
6. **Níveis de log apropriados**:
   - `info`: Para operações normais bem-sucedidas
   - `warning`: Para situações inesperadas mas não críticas
   - `error`: Para falhas que requerem atenção
   - `debug`: Para informações detalhadas durante desenvolvimento

## Exemplo de Saída

```json
{
  "correlation_id": "9c4e7e91-8c6f-4b8a-9d1a-2e3f4a5b6c7d",
  "request_id": "req-123456",
  "gateway": "mercadopago",
  "amount": 100.50,
  "payment_method": "pix",
  "transaction": {
    "id": 42,
    "external_id": "mp-987654321",
    "gateway": "mercadopago",
    "status": "approved",
    "amount": 100.50
  },
  "duration_ms": 234.56,
  "message": "Payment processed successfully"
}
```

## Monitoramento

Para monitoramento em produção, considere:

1. Agregar logs usando ferramentas como ELK Stack, Datadog ou CloudWatch
2. Configurar alertas baseados em:
   - Taxa de erros elevada
   - Duração de operações acima do normal
   - Falhas consecutivas de webhook
3. Usar correlation IDs para rastrear fluxos completos de pagamento
4. Analisar métricas de performance para otimização

## Troubleshooting

### Logs não estão sendo gerados

Verifique se:
1. O diretório `storage/logs` tem permissões de escrita
2. As variáveis de ambiente de log estão configuradas corretamente
3. O nível de log não está muito restritivo (use `debug` durante desenvolvimento)

### Dados sensíveis aparecem nos logs

Certifique-se de chamar `maskSensitiveData()` antes de logar:

```php
$context = LogContext::create()
    ->withMany($userData)
    ->maskSensitiveData(); // Importante!

Log::channel('payment')->info('User action', $context->toArray());
```

### Logs muito grandes

Considere:
1. Aumentar nível de log em produção (usar `info` ou `warning` ao invés de `debug`)
2. Reduzir período de retenção nos canais
3. Implementar rotação mais agressiva
