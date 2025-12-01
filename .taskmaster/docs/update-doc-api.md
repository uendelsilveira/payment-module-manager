# API Documentation Backlog

Escopo: alinhar [docs/openapi.yaml](cci:7://file:///Users/uendelsilveira/Documents/my_projects/payment-module-manager/docs/openapi.yaml:0:0-0:0) com os endpoints, contratos e comportamentos atuais do módulo, cobrindo segurança, headers e variações por método de pagamento.

## Prioridades
- Alta: itens que impactam consumidores diretamente ou segurança.
- Média: consistência de schemas e erros.
- Baixa: melhorias de DX e automação.

## Tarefas (Checklist)
- [ ] Documentar endpoint GET `/api/health`
    - Critérios de aceite:
        - Path `/health` sob servidor `/api` com resposta 200 e payload mínimo `{ status: up|down }` ou mensagem equivalente.
        - Referência: [routes/api.php](cci:7://file:///Users/uendelsilveira/Documents/my_projects/payment-module-manager/routes/api.php:0:0-0:0) (HealthCheckController@check)

- [ ] Documentar endpoint POST `/api/payment/webhook/{gateway}` (headers e payload)
    - Critérios de aceite:
        - Path com parâmetro [gateway](cci:1://file:///Users/uendelsilveira/Documents/my_projects/payment-module-manager/src/PaymentGatewayManager.php:35:4-51:5) em `paths`.
        - Headers documentados: `x-signature`, `x-request-id` (quando aplicável), formato `ts`/`v1` com janela de tempo.
        - Body: payload genérico com notas por gateway; incluir exemplo.
        - Respostas: 200 (ack), 400/403 para assinatura inválida, 500 para falhas internas.
        - Referências: [routes/api.php](cci:7://file:///Users/uendelsilveira/Documents/my_projects/payment-module-manager/routes/api.php:0:0-0:0), [Http/Middleware/VerifySignature.php](cci:7://file:///Users/uendelsilveira/Documents/my_projects/payment-module-manager/src/Http/Middleware/VerifySignature.php:0:0-0:0), [Gateways/MercadoPagoGateway::processWebhook()](cci:1://file:///Users/uendelsilveira/Documents/my_projects/payment-module-manager/src/Gateways/MercadoPagoGateway.php:198:4-236:5)

- [ ] Documentar POST `/api/payments/{transaction}/refund` e POST `/api/payments/{transaction}/cancel`
    - Critérios de aceite:
        - Parâmetro de path [transaction](cci:1://file:///Users/uendelsilveira/Documents/my_projects/payment-module-manager/src/Models/Refund.php:65:4-73:5) (integer).
        - Request body de refund: `amount` opcional (parcial) + exemplos.
        - Códigos: 200 (sucesso), 400/409 (regras de negócio), 404 (não encontrado), 422 (validação), 500 (erro).
        - Referências: [routes/api.php](cci:7://file:///Users/uendelsilveira/Documents/my_projects/payment-module-manager/routes/api.php:0:0-0:0), `PaymentController@refund/@cancel`, `Services/RefundService`, `Services/CancellationService`.

- [ ] Adicionar `securitySchemes` (Bearer/Sanctum) e aplicar `security` às rotas protegidas
    - Critérios de aceite:
        - `components.securitySchemes.bearerAuth` (HTTP bearer) definido.
        - Rotas protegidas (`/payment/process`, `/payments/*`, `/reports/*`, `settings/*`, `connect/*`) anotadas com `security`.
        - Referências: [routes/api.php](cci:7://file:///Users/uendelsilveira/Documents/my_projects/payment-module-manager/routes/api.php:0:0-0:0) (middleware `auth:sanctum`).

- [ ] Incluir cabeçalho `Idempotency-Key` em POST `/payment/process`
    - Critérios de aceite:
        - Header documentado como opcional/obrigatório conforme política.
        - Descrição do formato: alfanumérico com `_`/`-`, 16–100 chars.
        - Referência: [Http/Middleware/EnsureIdempotency.php](cci:7://file:///Users/uendelsilveira/Documents/my_projects/payment-module-manager/src/Http/Middleware/EnsureIdempotency.php:0:0-0:0).

- [ ] Alinhar schema [Transaction](cci:2://file:///Users/uendelsilveira/Documents/my_projects/payment-module-manager/src/Models/Transaction.php:54:0-111:1)
    - Critérios de aceite:
        - `amount` como `number` (format float).
        - Incluir `external_id`, `metadata`, `retries_count`, `last_attempt_at`.
        - Compatível com `Http/Resources/TransactionResource`.
        - Referências: [Models/Transaction.php](cci:7://file:///Users/uendelsilveira/Documents/my_projects/payment-module-manager/src/Models/Transaction.php:0:0-0:0), `TransactionResource`.

- [ ] Modelar variações por método de pagamento (PIX/Cartão/Boleto)
    - Critérios de aceite:
        - `CreatePaymentRequest` usa `oneOf`/`discriminator` por `payment_method_id`.
        - Campos obrigatórios condicionais (token/issuer_id/identification/address etc.).
        - Exemplos por método.
        - Referência: `Gateways/MercadoPagoGateway::build*Payment`.

- [ ] Definir mapeamento de erros por endpoint
    - Critérios de aceite:
        - Tabela/catálogo na doc: 400/401/403/404/409/422/500 com mensagens típicas.
        - Respostas padronizadas (`ErrorResponse`).
        - Referências: Controllers e Services (exceções e códigos atuais).

- [ ] Documentar endpoints de Settings/Connect (estado TBD/501)
    - Critérios de aceite:
        - Paths: `GET/POST /settings/{gateway}`, `GET /connect/{gateway}`, `GET /connect/{gateway}/callback`.
        - Marcar como `501 Not Implemented` enquanto houver TODO no controller.
        - Referências: [SettingsController.php](cci:7://file:///Users/uendelsilveira/Documents/my_projects/payment-module-manager/src/Http/Controllers/SettingsController.php:0:0-0:0), [routes/api.php](cci:7://file:///Users/uendelsilveira/Documents/my_projects/payment-module-manager/routes/api.php:0:0-0:0).

- [ ] Adicionar lint/validação do OpenAPI no CI
    - Critérios de aceite:
        - Step em `ci.yml` para validar [docs/openapi.yaml](cci:7://file:///Users/uendelsilveira/Documents/my_projects/payment-module-manager/docs/openapi.yaml:0:0-0:0) (ex.: `redocly/cli` ou `swagger-cli`).
        - Build falha em caso de schema inválido.
        - Referência: `.github/workflows/ci.yml`.

- [ ] Extrair estratégia de verificação de assinatura por gateway (design) e refletir na doc
    - Critérios de aceite:
        - Seção "Webhooks e Assinaturas" explicando variações por gateway.
        - Documentar cabeçalhos e cálculo de assinatura por provedor quando aplicável.
        - Referências: [VerifySignature.php](cci:7://file:///Users/uendelsilveira/Documents/my_projects/payment-module-manager/src/Http/Middleware/VerifySignature.php:0:0-0:0) (genérico), [MercadoPagoGateway::validateWebhookSignature](cci:1://file:///Users/uendelsilveira/Documents/my_projects/payment-module-manager/src/Gateways/MercadoPagoGateway.php:399:4-436:5) (MP).

- [ ] Remover side-effects do [WebhookValidationMiddleware](cci:2://file:///Users/uendelsilveira/Documents/my_projects/payment-module-manager/src/Http/Middleware/WebhookValidationMiddleware.php:17:0-29:1) (apenas validação) e ajustar doc
    - Critérios de aceite:
        - Middleware não dispara [processWebhook](cci:1://file:///Users/uendelsilveira/Documents/my_projects/payment-module-manager/src/Gateways/MercadoPagoGateway.php:198:4-236:5); apenas valida.
        - Doc do webhook reflete processamento assíncrono via `ProcessWebhookJob`.
        - Referências: [WebhookValidationMiddleware.php](cci:7://file:///Users/uendelsilveira/Documents/my_projects/payment-module-manager/src/Http/Middleware/WebhookValidationMiddleware.php:0:0-0:0), [WebhookController.php](cci:7://file:///Users/uendelsilveira/Documents/my_projects/payment-module-manager/src/Http/Controllers/WebhookController.php:0:0-0:0), [Jobs/ProcessWebhookJob.php](cci:7://file:///Users/uendelsilveira/Documents/my_projects/payment-module-manager/src/Jobs/ProcessWebhookJob.php:0:0-0:0).

## Observações
- Atualizar exemplos no OpenAPI com base nos exemplos do README (PIX/cartão/boleto) para consistência.
- Ao finalizar cada tarefa, atualizar `version` no OpenAPI se houver mudanças breaking na interface ou contratos de resposta.

## Referências rápidas
- Rotas: [routes/api.php](cci:7://file:///Users/uendelsilveira/Documents/my_projects/payment-module-manager/routes/api.php:0:0-0:0)
- Controllers: `Http/Controllers/*`
- Middlewares: `Http/Middleware/*`
- Services: `Services/*`
- Gateways: `Gateways/*`
- OpenAPI: [docs/openapi.yaml](cci:7://file:///Users/uendelsilveira/Documents/my_projects/payment-module-manager/docs/openapi.yaml:0:0-0:0)
- CI: `.github/workflows/ci.yml`
