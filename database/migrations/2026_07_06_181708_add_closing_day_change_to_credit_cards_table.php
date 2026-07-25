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
            // Fechamento anterior (antes da mudança)
            $table->unsignedTinyInteger('old_closing_day')->nullable()->after('closing_day');
            // Data a partir da qual o novo closing_day passa a valer
            $table->date('closing_changed_at')->nullable()->after('old_closing_day');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credit_cards', function (Blueprint $table) {
            $table->dropColumn(['old_closing_day', 'closing_changed_at']);
        });
    }
};
