<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pagamentos de contas fixas e faturas identificados no extrato.
 * Serve para o acumulado saber o que já saiu da conta no ciclo corrente e não
 * contar duas vezes — o saldo real da conta já reflete esses débitos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statement_payments', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->foreignId('fixed_expense_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('credit_card_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('paid_at');
            $table->string('identifier')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statement_payments');
    }
};
