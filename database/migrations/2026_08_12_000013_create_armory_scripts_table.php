<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('armory_scripts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('token')->unique();
            $table->string('title', 180);
            $table->string('category', 50)->index();
            $table->string('status', 30)->default('draft')->index();
            $table->string('version_label', 40)->default('1.0');
            $table->text('description')->nullable();
            $table->longText('content_text')->nullable();
            $table->string('disk', 40)->nullable();
            $table->string('path', 500)->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->char('sha256', 64)->nullable()->unique();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('armory_scripts');
    }
};
