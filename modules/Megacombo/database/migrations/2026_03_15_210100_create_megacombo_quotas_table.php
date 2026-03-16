<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('megacombo_quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->nullable()->constrained('megacombo_leads')->nullOnDelete();
            $table->string('group_code');
            $table->string('quota_number');
            $table->decimal('credit_value', 12, 2);
            $table->decimal('installment_value', 12, 2);
            $table->unsignedInteger('remaining_installments');
            $table->string('status')->default('active');
            $table->timestamp('contemplated_at')->nullable();
            $table->timestamps();
            $table->index(['group_code', 'quota_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('megacombo_quotas');
    }
};
