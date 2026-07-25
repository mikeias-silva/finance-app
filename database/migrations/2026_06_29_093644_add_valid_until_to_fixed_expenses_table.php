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
        Schema::table('fixed_expenses', function (Blueprint $table) {
            $table->date('valid_until')->nullable()->after('is_active')
                ->comment('Data limite de cobrança. Após essa data a despesa não é mais considerada.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fixed_expenses', function (Blueprint $table) {
            $table->dropColumn('valid_until');
        });
    }
};
