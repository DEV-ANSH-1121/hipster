<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('discount_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('usage_count')->default(0);
            $table->unsignedInteger('usage_limit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('assigned_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            
            $table->unique(['user_id', 'discount_id']);
            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_discounts');
    }
};

