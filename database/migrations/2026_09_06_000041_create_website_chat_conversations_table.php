<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_chat_conversations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('token')->unique('wcc_token_uq');
            $table->string('session_id', 64)->unique('wcc_session_uq');
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->unsignedBigInteger('task_id')->nullable();
            $table->string('topic', 40);
            $table->string('status', 30)->default('open')->index('wcc_status_idx');
            $table->string('visitor_name', 160);
            $table->string('visitor_email', 200);
            $table->string('visitor_phone', 40);
            $table->string('property_address', 300)->nullable();
            $table->string('parcel_id', 200)->nullable();
            $table->text('message');
            $table->json('transcript');
            $table->string('page_url', 500)->nullable();
            $table->timestamp('consent_at');
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->foreign('contact_id', 'wcc_contact_fk')->references('id')->on('contacts')->nullOnDelete();
            $table->foreign('task_id', 'wcc_task_fk')->references('id')->on('tasks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_chat_conversations');
    }
};
