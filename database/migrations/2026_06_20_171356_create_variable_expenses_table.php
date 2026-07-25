<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('variable_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('description')->comment('Mercado, Farmácia, Restaurante');
            $table->string('category')->nullable()->comment('Alimentação, Saúde, Lazer, etc.');
            $table->decimal('amount', 10, 2);
            $table->date('paid_at')->comment('Data real do pagamento — exibição formata para mês/ano');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('variable_expenses');
    }
};
