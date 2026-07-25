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
        Schema::table('credit_cards', function (Blueprint $table) {
            // Dia do vencimento atual
            $table->unsignedTinyInteger('due_day')->nullable()->after('closing_day');
            // Dia do vencimento antes da mudança
            $table->unsignedTinyInteger('old_due_day')->nullable()->after('old_closing_day');
            // Meses sem fatura (JSON, ex: ["2026-07"]) — meses onde a fatura foi pulada na transição
            $table->json('billing_skip_months')->nullable()->after('closing_changed_at');
        });
    }

    public function down(): void
    {
        Schema::table('credit_cards', function (Blueprint $table) {
            $table->dropColumn(['due_day', 'old_due_day', 'billing_skip_months']);
        });
    }
};
