<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('armory_email_templates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('token')->unique();
            $table->string('name', 180);
            $table->string('category', 50)->index();
            $table->string('status', 30)->default('draft')->index();
            $table->string('version_label', 40)->default('1.0');
            $table->text('description')->nullable();
            $table->string('subject', 255);
            $table->longText('body_text');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'status', 'updated_at'], 'armory_email_templates_filter_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('armory_email_templates');
    }
};
