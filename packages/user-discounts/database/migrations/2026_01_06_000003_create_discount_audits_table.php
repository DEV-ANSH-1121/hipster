<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('discount_id')->constrained()->onDelete('cascade');
            $table->string('action'); // assigned, revoked, applied
            $table->decimal('original_amount', 10, 2)->nullable();
            $table->decimal('discount_amount', 10, 2)->nullable();
            $table->decimal('final_amount', 10, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'discount_id']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_audits');
    }
};

