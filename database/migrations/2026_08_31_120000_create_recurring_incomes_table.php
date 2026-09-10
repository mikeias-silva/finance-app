<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Receita que se repete todo ciclo e cai junto do salário, sem ser o salário —
 * gratificação, ajuda de custo, adicional. O salário continua em `salaries`,
 * com um registro vigente por vez; aqui cabem várias em paralelo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_incomes', function (Blueprint $table) {
            $table->id();
            $table->string('description')->comment('Gratificação, ajuda de custo, etc.');
            $table->decimal('amount', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->comment('Primeiro pagamento em que passa a valer');
            $table->date('valid_until')->nullable()->comment('Último pagamento; null = sem prazo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_incomes');
    }
};
