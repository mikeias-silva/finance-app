# Finance App

Finanças pessoais olhando para frente: período a período, quanto da receita já está comprometido e quanto vai sobrar na conta.

O período vai do último dia útil do mês, quando o salário cai, até o penúltimo dia útil do mês seguinte, considerando fins de semana e feriados nacionais.

## O que faz

- Projeta o saldo em conta ciclo a ciclo: salário, receitas recorrentes, gastos fixos, faturas de cartão, gastos variáveis e o rendimento das caixinhas.
- Importa conta e cartões pelo [Pluggy](https://pluggy.ai) (Open Finance), sem duplicar o que foi lançado à mão.
- Mostra a projeção em três dashboards. O Dashboard 3 traz os gráficos interativos de saldo e de entradas e saídas.
- Mantém compras feitas para outra pessoa registradas, mas fora dos cálculos.

## Como os dados fluem

```mermaid
flowchart LR
    Bancos["Nubank e Caixa"] --> Pluggy["Pluggy<br/>Open Finance"]
    Pluggy --> Import["Importadores<br/>cron diário e botão Atualizar"]
    Manual["Lançamentos manuais"] --> DB[("SQLite")]
    Import --> DB
    DB --> Proj["ProjectionService<br/>BillingPeriod"]
    Proj --> Dash["Dashboards 1, 2 e 3"]
```

Os dashboards nunca chamam o Pluggy. Eles leem o saldo gravado na última importação.

## API do Pluggy

```mermaid
sequenceDiagram
    participant App as Finance App
    participant P as Pluggy API
    App->>P: POST /auth (client id e secret)
    P-->>App: apiKey, guardada em cache
    App->>P: GET /accounts?itemId=...
    App->>P: GET /v2/transactions?accountId=... (paginado)
    App->>P: GET /investments?itemId=... (caixinhas)
    App->>P: GET /bills?accountId=... (faturas do cartão)
    App->>App: classifica, reconcilia e grava
```

## Cada lançamento da conta

```mermaid
flowchart TD
    L["Lançamento do extrato"] --> RDB{"Caixinha?"}
    RDB -- sim --> Cx["Sincroniza as caixinhas"]
    RDB -- não --> Sal{"Salário?"}
    Sal -- sim --> SalD["Grava só a diferença<br/>para o salário cadastrado"]
    Sal -- não --> Rec{"Receita recorrente<br/>ou devolução de terceiro?"}
    Rec -- sim --> Ok["Confirmado, não vira receita"]
    Rec -- não --> Fixo{"Gasto fixo?"}
    Fixo -- sim --> FixoD["Marca como pago<br/>e grava só a diferença"]
    Fixo -- não --> Var["Receita ou despesa variável"]
```

## Compras do cartão sem duplicar

```mermaid
flowchart TD
    C["Compra vinda do Pluggy"] --> Ini{"Antes do início<br/>da sincronização?"}
    Ini -- sim --> Ign["Ignorada"]
    Ini -- não --> Ass{"Assinatura cadastrada?"}
    Ass -- sim --> AssD["Mantém a recorrente<br/>e lança só a diferença do mês"]
    Ass -- não --> Ig{"Mesma data, parcela<br/>e número de parcelas?"}
    Ig -- sim --> Ja["Já existe"]
    Ig -- não --> Par{"Parecida com uma manual?<br/>nome ou parcelas iguais,<br/>até R$ 5 e 5 dias"}
    Par -- sim --> Cor["Corrige a manual<br/>com o valor do Pluggy"]
    Par -- não --> Nova["Cria a compra"]
```

O saldo de cada ciclo é o saldo do ciclo anterior, mais as entradas, menos as saídas, mais o rendimento das caixinhas. O ciclo atual parte do saldo real e soma só o que ainda falta acontecer.

## Stack

Laravel 13 (PHP 8.3), SQLite, Bootstrap 5 e Docker Compose.

## Rodando

```bash
make setup          # primeira vez: .env, containers, dependências e banco
make up             # app em http://localhost:8080
```

Gere a senha de acesso com `php artisan app:password` e cole o resultado em `APP_PASSWORD_HASH`, no `.env`, **entre aspas simples**. Para importar do Pluggy, preencha as variáveis `PLUGGY_*`.

| Comando | Para quê |
|---|---|
| `import:pluggy-nubank` | Movimentação da conta e caixinhas |
| `import:pluggy-caixa-card` | Compras do cartão (`--dry-run` mostra sem gravar) |
| `pluggy:connect` | Conecta uma instituição e devolve o `itemId` |

## Testes

```bash
php artisan test    # SQLite em memória, sem container
```

## Deploy

Passo a passo em [DEPLOY.md](DEPLOY.md).
