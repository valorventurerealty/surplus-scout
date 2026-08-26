<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_signatures', function (Blueprint $table): void {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->text('body_text');
            $table->text('body_html');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('outbound_emails', function (Blueprint $table): void {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('primary_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('armory_email_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('email_signature_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('related');
            $table->string('status', 30)->default('draft')->index();
            $table->string('from_address');
            $table->string('from_name');
            $table->json('to_json');
            $table->json('cc_json')->nullable();
            $table->json('bcc_json')->nullable();
            $table->string('subject');
            $table->longText('body_text');
            $table->longText('final_text')->nullable();
            $table->longText('final_html')->nullable();
            $table->text('signature_text')->nullable();
            $table->text('signature_html')->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->text('failure_message')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sending_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status', 'created_at']);
        });

        Schema::create('outbound_email_attachments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('outbound_email_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 30)->default('local');
            $table->string('path', 500);
            $table->string('original_name');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64)->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $text = "Thank you,\n\nMark Lewis, MBA\nManaging Partner, President\nValor Venture Realty, LLC\nhttps://valorventure.us/\n(407) 900-6554\nSchedule Meeting: https://valorventure.us/meetings/valorventurerealty";
        $html = '<p>Thank you,</p><p><strong>Mark Lewis, MBA</strong><br>Managing Partner, President<br>Valor Venture Realty, LLC<br><a href="https://valorventure.us/">ValorVenture.us</a><br><a href="tel:+14079006554">(407) 900-6554</a><br><a href="https://valorventure.us/meetings/valorventurerealty">Schedule Meeting</a></p>';
        DB::table('email_signatures')->insert(['token' => (string) Str::uuid(), 'name' => 'Mark Lewis — Default', 'is_default' => true, 'is_active' => true, 'body_text' => $text, 'body_html' => $html, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_email_attachments');
        Schema::dropIfExists('outbound_emails');
        Schema::dropIfExists('email_signatures');
    }
};
