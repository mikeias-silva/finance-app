<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_boxes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color')->default('#10b981');
            $table->decimal('rate_percentage', 6, 2);
            $table->decimal('max_amount', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_boxes');
    }
};
