<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('armory_script_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('armory_script_id')->constrained()->cascadeOnDelete();
            $table->string('title', 180);
            $table->text('prompt_text');
            $table->text('guidance_text')->nullable();
            $table->unsignedSmallInteger('sequence');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['armory_script_id', 'sequence']);
        });

        Schema::create('armory_script_step_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('armory_script_step_id')->constrained()->cascadeOnDelete();
            $table->string('label', 180);
            $table->text('response_text')->nullable();
            $table->string('outcome_code', 80)->nullable();
            $table->unsignedSmallInteger('sequence');
            $table->timestamps();
            $table->unique(['armory_script_step_id', 'sequence'], 'armory_step_option_sequence_unique');
        });

        Schema::table('armory_script_step_options', function (Blueprint $table): void {
            $table->foreignId('next_step_id')->nullable()->after('response_text')
                ->constrained('armory_script_steps')->nullOnDelete();
        });

        Schema::create('armory_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('armory_script_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('current_step_id')->nullable()->constrained('armory_script_steps')->nullOnDelete();
            $table->string('status', 30)->default('in_progress')->index();
            $table->string('caller_name', 180)->nullable();
            $table->text('notes')->nullable();
            $table->string('outcome', 120)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('armory_session_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('armory_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('armory_script_step_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('armory_script_step_option_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 40);
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['armory_session_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('armory_session_events');
        Schema::dropIfExists('armory_sessions');
        Schema::dropIfExists('armory_script_step_options');
        Schema::dropIfExists('armory_script_steps');
    }
};
