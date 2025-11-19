Pendências e Melhorias do Módulo de Pagamento
Este documento lista as correções críticas, melhorias arquiteturais e pendências identificadas após a análise do projeto.

🚨 Correções Críticas (Prioridade Alta)
1. Implementar Lógica do
   ProcessWebhookJob
   Estado Atual: O arquivo
   src/Jobs/ProcessWebhookJob.php
   contém apenas um TODO e logs. Ele não realiza nenhuma ação no banco de dados.
   Problema: O sistema recebe o webhook, coloca na fila, mas o processamento efetivo (atualizar o status da transação) nunca acontece.
   Ação Necessária:
   Localizar a transação correspondente usando o external_id (do payload do gateway).
   Atualizar o status da transação no banco de dados.
   Disparar eventos de domínio (ex: PaymentProcessed, PaymentFailed).
   🛠 Melhorias Arquiteturais (Refatoração)
2. Refatorar Idempotência para Serviço Dedicado (IdempotencyService)
   Estado Atual: A lógica de idempotência está implementada diretamente no middleware
   EnsureIdempotency
   .
   Melhoria: Extrair a lógica de verificação, cache e recuperação de resposta para uma classe IdempotencyService.
   Benefício: Permite reutilizar a lógica de idempotência em outros contextos (fora do HTTP, se necessário) e facilita testes unitários isolados da camada HTTP. O middleware passaria a depender deste serviço.
3. Extrair WebhookController
   Estado Atual: O método
   handleWebhook
   reside dentro do
   PaymentController
   .
   Melhoria: Mover este método para um controlador dedicado WebhookController.
   Benefício: Adesão ao Princípio da Responsabilidade Única (SRP). O
   PaymentController
   deve focar em ações iniciadas pelo cliente (pagar, estornar), enquanto o WebhookController lida com callbacks assíncronos dos gateways.
4. Implementar RetryService com Backoff Exponencial
   Estado Atual: A lógica de retentativa existe mas está acoplada dentro de PaymentService::reprocess.
   Melhoria: Criar um RetryService agnóstico que aceite um callable e execute a lógica de retry com backoff exponencial e jitter.
   Benefício: Desacopla a estratégia de resiliência da lógica de negócio de pagamento. Permite usar a mesma lógica de retry para outras operações instáveis (ex: chamadas a APIs de terceiros em outros contextos).
5. Centralizar Validadores de Pagamento
   Estado Atual: Validações específicas de métodos de pagamento (ex: algoritmo de Luhn para cartão de crédito, validação de chave PIX) não estão claras ou estão misturadas no serviço.
   Melhoria: Criar classes validadoras dedicadas em src/Validators (ex: CreditCardValidator, PixValidator).
   Benefício: Organização do código e facilidade de manutenção. Permite adicionar novos métodos de pagamento sem poluir o serviço principal.
   📝 Funcionalidades Pendentes (Backlog)
6. Implementação Completa de Gateways Adicionais
   Stripe: Implementação atual é parcial (stub). Necessário implementar mapeamento completo de status e webhooks.
   PayPal: Implementação atual é parcial (stub).
   Nota: O usuário solicitou priorizar outras áreas por enquanto.
7. Testes Automatizados
   Ação: Garantir cobertura de testes para os novos serviços e jobs, especialmente para o fluxo de webhook que é crítico e assíncrono.
