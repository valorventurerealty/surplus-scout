<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sops', function (Blueprint $table): void {
            $table->id();
            $table->uuid('token')->unique();
            $table->string('title', 180);
            $table->string('department', 60)->index();
            $table->string('status', 30)->default('draft')->index();
            $table->string('version_label', 40)->default('1.0');
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('summary')->nullable();
            $table->longText('content_text')->nullable();
            $table->date('effective_date')->nullable();
            $table->date('review_date')->nullable()->index();
            $table->string('drive_url', 2048)->nullable();
            $table->string('disk', 40)->nullable();
            $table->string('path', 2048)->nullable();
            $table->string('original_name', 255)->nullable();
            $table->string('mime_type', 160)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->char('sha256', 64)->nullable()->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['department', 'status', 'review_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sops');
    }
};
