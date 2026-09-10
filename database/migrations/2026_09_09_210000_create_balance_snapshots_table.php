<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O saldo real lido do Pluggy no momento da importação. O dashboard ancora o
 * acumulado nele em vez de consultar a API a cada carregamento — a leitura
 * acontece quando o usuário manda atualizar, e não quando abre a tela.
 *
 * Uma linha por importação: além de servir de âncora, o histórico mostra como o
 * saldo real andou.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->decimal('checking', 12, 2);
            $table->decimal('boxes', 12, 2);
            $table->decimal('total', 12, 2);
            // null = a importação não cobriu o início do ciclo e não tem como
            // afirmar; a projeção então decide pelo calendário.
            $table->boolean('salary_credited')->nullable();
            $table->timestamp('synced_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_snapshots');
    }
};
