<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_copilot_playbooks', function (Blueprint $table): void {
            $table->id(); $table->uuid('token')->unique(); $table->string('title'); $table->string('slug')->unique();
            $table->string('category', 80)->index(); $table->string('scenario')->nullable(); $table->string('prospect_type', 80)->nullable();
            $table->json('trigger_phrases'); $table->text('recommended_response'); $table->json('tones');
            $table->text('objective'); $table->string('stage', 80)->index(); $table->json('follow_up_questions')->nullable();
            $table->json('branches')->nullable(); $table->json('listen_for')->nullable(); $table->json('mistakes_to_avoid')->nullable();
            $table->text('notes')->nullable(); $table->unsignedSmallInteger('priority')->default(50)->index();
            $table->boolean('active')->default(true)->index(); $table->boolean('vvr_approved')->default(false)->index();
            $table->boolean('owner_authored')->default(false)->index(); $table->string('source_reference')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps(); $table->softDeletes();
            $table->index(['active', 'vvr_approved', 'owner_authored', 'priority'], 'copilot_playbook_rank_idx');
        });

        Schema::create('sales_copilot_sessions', function (Blueprint $table): void {
            $table->id(); $table->uuid('token')->unique(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('surplus_case_id')->nullable()->constrained()->nullOnDelete();
            $table->string('prospect_name')->nullable(); $table->string('call_type', 80)->default('other');
            $table->string('prospect_relationship', 80)->nullable(); $table->string('current_stage', 80)->default('connection');
            $table->string('resistance_level', 40)->default('neutral'); $table->string('county', 120)->nullable();
            $table->string('parcel_id', 120)->nullable(); $table->decimal('estimated_surplus', 14, 2)->nullable();
            $table->text('previous_conversation_summary')->nullable(); $table->text('additional_notes')->nullable();
            $table->json('state')->nullable(); $table->string('status', 40)->default('active')->index();
            $table->timestamp('last_coached_at')->nullable()->index(); $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status', 'last_coached_at'], 'copilot_session_user_status_idx');
        });

        Schema::create('sales_copilot_turns', function (Blueprint $table): void {
            $table->id(); $table->foreignId('sales_copilot_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence'); $table->text('prospect_statement'); $table->text('salesperson_previous')->nullable();
            $table->string('classification', 80)->index(); $table->decimal('classification_confidence', 5, 4)->nullable();
            $table->string('conversation_stage', 80); $table->string('resistance_level', 40);
            $table->json('response'); $table->foreignId('matched_playbook_id')->nullable()->constrained('sales_copilot_playbooks')->nullOnDelete();
            $table->string('provider_response_id')->nullable(); $table->unsignedInteger('input_tokens')->nullable(); $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('latency_ms')->nullable(); $table->boolean('used_fallback')->default(false);
            $table->boolean('requires_human_review')->default(false)->index(); $table->boolean('requires_legal_review')->default(false)->index();
            $table->timestamps(); $table->unique(['sales_copilot_session_id', 'sequence'], 'copilot_turn_session_sequence_unique');
        });

        Schema::create('sales_copilot_feedback', function (Blueprint $table): void {
            $table->id(); $table->foreignId('sales_copilot_turn_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->string('rating', 20);
            $table->text('original_response'); $table->text('final_wording')->nullable(); $table->text('notes')->nullable();
            $table->boolean('save_to_playbook')->default(false); $table->timestamps();
            $table->unique(['sales_copilot_turn_id', 'user_id'], 'copilot_feedback_turn_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_copilot_feedback'); Schema::dropIfExists('sales_copilot_turns');
        Schema::dropIfExists('sales_copilot_sessions'); Schema::dropIfExists('sales_copilot_playbooks');
    }
};
