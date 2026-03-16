<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('megacombo_leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('objective');
            $table->unsignedSmallInteger('urgency_months')->default(0);
            $table->decimal('initial_capital', 12, 2)->default(0);
            $table->string('risk_profile')->default('moderado');
            $table->unsignedTinyInteger('fit_score')->default(0);
            $table->string('recommended_flow')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('megacombo_leads');
    }
};
