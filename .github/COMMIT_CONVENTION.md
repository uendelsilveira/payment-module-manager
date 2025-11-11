# 📝 Guia de Commits Convencionais

Este projeto utiliza [Conventional Commits](https://www.conventionalcommits.org/) para automatizar o versionamento e geração de changelogs.

## 📋 Formato

```
<tipo>[escopo opcional]: <descrição>

[corpo opcional]

[rodapé opcional]
```

## 🎯 Tipos de Commit

### Incrementam a versão:

- **feat**: Nova funcionalidade (incrementa MINOR: 1.0.0 → 1.1.0)
  ```bash
  feat: adicionar suporte para pagamento via PIX
  feat(webhook): implementar validação de assinatura
  ```

- **fix**: Correção de bug (incrementa PATCH: 1.0.0 → 1.0.1)
  ```bash
  fix: corrigir validação de CPF inválido
  fix(api): resolver timeout em requisições longas
  ```

- **perf**: Melhoria de performance (incrementa PATCH: 1.0.0 → 1.0.1)
  ```bash
  perf: otimizar query de busca de transações
  perf(cache): implementar cache de configurações
  ```

### Breaking Changes (incrementa MAJOR: 1.0.0 → 2.0.0):

Adicione `!` após o tipo ou inclua `BREAKING CHANGE:` no rodapé:

```bash
feat!: remover suporte para PHP 7.4

BREAKING CHANGE: A versão mínima do PHP agora é 8.2
```

### Não incrementam versão:

- **docs**: Mudanças na documentação
  ```bash
  docs: atualizar README com exemplos de uso
  ```

- **style**: Formatação, espaços em branco (sem mudanças de código)
  ```bash
  style: aplicar Laravel Pint
  ```

- **refactor**: Refatoração sem adicionar funcionalidades ou corrigir bugs
  ```bash
  refactor: simplificar lógica de validação de pagamento
  ```

- **test**: Adicionar ou modificar testes
  ```bash
  test: adicionar testes para webhook handler
  ```

- **chore**: Manutenção, configuração, dependências
  ```bash
  chore: atualizar dependências do composer
  chore(ci): adicionar workflow de release automático
  ```

- **ci**: Mudanças em CI/CD
  ```bash
  ci: adicionar step de cobertura de código
  ```

## 🚀 Como funciona o versionamento automático

1. **Push para branch `main`**: Dispara o workflow de release
2. **Análise de commits**: O workflow analisa todos os commits desde a última tag
3. **Determinação da versão**:
   - `feat!` ou `BREAKING CHANGE` → incrementa MAJOR (2.0.0)
   - `feat:` → incrementa MINOR (1.1.0)
   - `fix:` ou `perf:` → incrementa PATCH (1.0.1)
4. **Geração automática**:
   - Atualiza `README.md` e `composer.json`
   - Gera changelog categorizado
   - Cria tag Git
   - Publica GitHub Release

## 💡 Boas Práticas

1. **Commits atômicos**: Um commit = uma mudança lógica
2. **Descrições claras**: Use verbos no imperativo ("adicionar", não "adicionado")
3. **Escopos úteis**: Use escopos quando apropriado (`auth`, `api`, `webhook`, `payment`)
4. **Breaking changes**: Sempre documente mudanças que quebram compatibilidade

## ⚠️ Importante

- Commits sem os prefixos acima **não disparam releases**
- Use `[skip ci]` na mensagem para pular o CI quando necessário
- O workflow valida testes e lint antes de criar o release

## 📚 Exemplos Reais

```bash
# Nova feature (minor bump)
git commit -m "feat(pix): adicionar QR code dinâmico"

# Bug fix (patch bump)
git commit -m "fix(webhook): corrigir validação de timestamp"

# Breaking change (major bump)
git commit -m "feat!: migrar para nova API do Gateway"

# Sem release
git commit -m "docs: atualizar guia de instalação"
git commit -m "chore: atualizar dependências"
```
