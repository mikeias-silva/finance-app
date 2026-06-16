<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_card_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_card_id')
                  ->constrained()
                  ->restrictOnDelete();

            $table->string('description');
            $table->decimal('total_amount', 10, 2)->comment('valor total da compra');
            $table->unsignedTinyInteger('installments')->default(1)->comment('qtd de parcelas');

            $table->date('purchased_at')->comment('Data real da compra — exibição formata para mês/ano');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_card_purchases');
    }
};
