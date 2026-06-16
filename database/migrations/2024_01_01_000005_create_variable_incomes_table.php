<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variable_incomes', function (Blueprint $table) {
            $table->id();
            $table->string('description')->comment('Freela, Bônus');
            $table->decimal('amount', 10, 2);
            $table->date('received_at')->comment('Data real do recebimento — exibição formata para mês/ano');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variable_incomes');
    }
};
