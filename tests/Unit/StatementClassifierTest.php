<?php

namespace Tests\Unit;

use App\Services\StatementClassifier;
use PHPUnit\Framework\TestCase;

/**
 * O classificador é o que separa, no extrato, o que vira despesa variável do que
 * é gasto fixo já previsto, transferência entre contas próprias ou movimento de
 * caixinha. Um falso negativo aqui duplica o gasto na projeção.
 */
class StatementClassifierTest extends TestCase
{
    private StatementClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classifier = new StatementClassifier;
    }

    public function test_a_fixed_expense_is_matched_by_substring_regardless_of_case(): void
    {
        // Formato do CSV: nome seguido do CNPJ.
        $match = $this->classifier->matchFixedExpense('tim s a - 02.421.421/0001-11');

        $this->assertSame('Tim', $match['label']);
        $this->assertSame('Tim', $match['db_description']);
    }

    public function test_the_same_pattern_matches_the_api_format(): void
    {
        // Formato da API do Pluggy: tipo do lançamento e nome separados por barra.
        $match = $this->classifier->matchFixedExpense('Transferência enviada|TIM S A');

        $this->assertSame('Tim', $match['label']);
    }

    public function test_a_bill_payment_has_no_forecast_and_points_at_the_card(): void
    {
        $match = $this->classifier->matchFixedExpense('CARTOES CAIXA VISA PF');

        // Sem db_description não há valor previsto para comparar: só confirma a quitação.
        $this->assertNull($match['db_description']);
        $this->assertSame('Caixa - Visa', $match['credit_card']);
    }

    public function test_an_unknown_description_matches_nothing(): void
    {
        $this->assertNull($this->classifier->matchFixedExpense('PADARIA DO BAIRRO'));
    }

    public function test_it_recognises_the_account_holders_own_name(): void
    {
        $this->assertTrue($this->classifier->isSalary('Transferência recebida|MIKEIAS SILVA GOMES DE AZEVEDO'));
        $this->assertFalse($this->classifier->isSalary('Transferência recebida|FULANO DE TAL'));
    }

    public function test_rdb_movements_are_investments_not_expenses(): void
    {
        $this->assertTrue($this->classifier->isInvestmentMovement('Aplicação RDB'));
        $this->assertTrue($this->classifier->isInvestmentMovement('Resgate rdb'));
        $this->assertFalse($this->classifier->isInvestmentMovement('Compra no débito|MERCADO'));
    }

    public function test_an_own_transfer_is_detected_by_document_even_with_different_masks(): void
    {
        $this->assertTrue($this->classifier->isOwnTransfer([
            'payer' => ['documentNumber' => ['value' => '123.456.789-00']],
            'receiver' => ['documentNumber' => ['value' => '12345678900']],
        ]));
    }

    public function test_different_documents_are_not_an_own_transfer(): void
    {
        $this->assertFalse($this->classifier->isOwnTransfer([
            'payer' => ['documentNumber' => ['value' => '123.456.789-00']],
            'receiver' => ['documentNumber' => ['value' => '999.888.777-66']],
        ]));
    }

    public function test_a_missing_document_is_not_an_own_transfer(): void
    {
        $this->assertFalse($this->classifier->isOwnTransfer(null));
        $this->assertFalse($this->classifier->isOwnTransfer([
            'payer' => ['documentNumber' => ['value' => '12345678900']],
        ]));
    }
}
