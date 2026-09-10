<?php

namespace App\Services;

/**
 * Classifica lançamentos do extrato da conta, tanto do CSV quanto da API do Pluggy.
 * Os padrões são substrings da descrição, então funcionam nos dois formatos
 * ("TIM S A - 02.421.421/0001-11" no CSV, "Transferência enviada|TIM S A" na API).
 */
class StatementClassifier
{
    /** Transferências do próprio titular (apenas confirmação, não entra como receita variável). */
    private array $salaryPatterns = [
        'MIKEIAS SILVA GOMES DE AZEVEDO',
    ];

    /**
     * Gastos fixos identificados no extrato.
     * 'db_description' é o nome exato em fixed_expenses para buscar o valor previsto.
     * null = sem previsão (pagamento de fatura), apenas confirma sem calcular diferença;
     * nesse caso 'credit_card' aponta o cartão cuja fatura foi quitada.
     */
    private array $fixedExpensePatterns = [
        'MUNICIPIO DE PONTA GROSSA' => ['label' => 'IPTU',                   'db_description' => 'IPTU Lote'],
        'BANCO GM S.A'              => ['label' => 'Financiamento Onix',     'db_description' => 'Financiamento Onix'],
        'MSS VECTRA LOT SPE LTDA'   => ['label' => 'Financiamento Terreno',  'db_description' => 'Financiamento Terreno'],
        'TIM S A'                   => ['label' => 'Tim',                    'db_description' => 'Tim'],
        'STAR PROTECAO VEICULAR'    => ['label' => 'Seguro Onix',            'db_description' => 'Seguro Onix'],
        'CARTOES CAIXA VISA PF'     => ['label' => 'Pagamento Cartão Caixa', 'db_description' => null, 'credit_card' => 'Caixa - Visa'],
        'Pagamento de fatura'       => ['label' => 'Pagamento Fatura Nubank','db_description' => null, 'credit_card' => 'Nubank - Mastercard'],
    ];

    public function matchFixedExpense(string $description): ?array
    {
        foreach ($this->fixedExpensePatterns as $pattern => $config) {
            if (stripos($description, $pattern) !== false) {
                return $config;
            }
        }

        return null;
    }

    public function isSalary(string $description): bool
    {
        foreach ($this->salaryPatterns as $pattern) {
            if (stripos($description, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /** Aplicação/resgate de caixinha (RDB), tratado como investimento, não como despesa. */
    public function isInvestmentMovement(string $description): bool
    {
        return stripos($description, 'RDB') !== false;
    }

    /**
     * Transferência entre contas do próprio titular: pagador e recebedor têm o mesmo
     * documento. Não é receita nem despesa nova — só dinheiro trocando de banco.
     * Mais confiável que o nome na descrição, que a API às vezes omite (é assim que
     * o próprio salário chega: "Transferência Recebida", sem nome).
     */
    public function isOwnTransfer(?array $paymentData): bool
    {
        $payer = $paymentData['payer']['documentNumber']['value'] ?? null;
        $receiver = $paymentData['receiver']['documentNumber']['value'] ?? null;

        if ($payer === null || $receiver === null) {
            return false;
        }

        return $this->onlyDigits($payer) === $this->onlyDigits($receiver);
    }

    private function onlyDigits(string $document): string
    {
        return preg_replace('/\D/', '', $document);
    }
}
