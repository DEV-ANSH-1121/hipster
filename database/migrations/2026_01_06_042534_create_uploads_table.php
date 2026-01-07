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
        Schema::create('uploads', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('total_size');
            $table->unsignedBigInteger('chunk_size');
            $table->unsignedInteger('total_chunks');
            $table->unsignedInteger('uploaded_chunks')->default(0);
            $table->string('checksum')->nullable();
            $table->string('status')->default('pending'); // pending, uploading, completed, failed
            $table->text('metadata')->nullable(); // JSON for chunk tracking
            $table->timestamps();
            
            $table->index('uuid');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uploads');
    }
};
