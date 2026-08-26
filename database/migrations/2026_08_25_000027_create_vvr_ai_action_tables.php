<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_action_plans', function (Blueprint $table): void {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('intent', 80)->index();
            $table->text('summary');
            $table->unsignedTinyInteger('risk_level')->default(0);
            $table->string('status', 30)->default('pending')->index();
            $table->json('missing_information_json')->nullable();
            $table->json('warnings_json')->nullable();
            $table->json('result_json')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_tool_calls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('action_plan_id')->constrained('ai_action_plans')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('tool_name', 100)->index();
            $table->text('action_summary');
            $table->unsignedTinyInteger('risk_level');
            $table->boolean('requires_approval')->default(true);
            $table->json('arguments_json');
            $table->string('status', 30)->default('proposed')->index();
            $table->char('idempotency_key', 64)->unique();
            $table->json('result_json')->nullable();
            $table->json('error_json')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['action_plan_id', 'sequence']);
        });

        Schema::create('ai_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
            $table->foreignId('action_plan_id')->nullable()->constrained('ai_action_plans')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 100)->index();
            $table->string('tool_name', 100)->nullable();
            $table->json('metadata_json')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('ai_usage_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 40);
            $table->string('model', 120);
            $table->string('operation', 80)->index();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->boolean('successful')->default(true);
            $table->string('error_code', 100)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_records');
        Schema::dropIfExists('ai_audit_logs');
        Schema::dropIfExists('ai_tool_calls');
        Schema::dropIfExists('ai_action_plans');
    }
};
