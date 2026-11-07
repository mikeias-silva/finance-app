<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_card_purchases', function (Blueprint $table) {
            $table->boolean('excluded_from_projection')->default(false)->after('is_recurring');
        });
    }

    public function down(): void
    {
        Schema::table('credit_card_purchases', function (Blueprint $table) {
            $table->dropColumn('excluded_from_projection');
        });
    }
};
