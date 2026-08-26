<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surplus_owner_research_batches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('token')->unique();
            $table->string('county', 120)->index();
            $table->string('mode', 30);
            $table->string('status', 30)->index();
            $table->unsignedInteger('total_cases')->default(0);
            $table->unsignedInteger('processed_cases')->default(0);
            $table->unsignedInteger('verified_owners')->default(0);
            $table->unsignedInteger('ready_for_skip_trace')->default(0);
            $table->unsignedInteger('business_research_needed')->default(0);
            $table->unsignedInteger('estate_research_needed')->default(0);
            $table->unsignedInteger('trust_research_needed')->default(0);
            $table->unsignedInteger('manual_review')->default(0);
            $table->unsignedInteger('errors')->default(0);
            $table->json('case_ids');
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['county', 'status', 'created_at'], 'owner_batch_county_status_created_idx');
        });

        Schema::table('surplus_cases', function (Blueprint $table): void {
            $table->text('current_owner_raw')->nullable()->after('research_status');
            $table->string('current_owner_normalized', 500)->nullable()->after('current_owner_raw');
            $table->text('previous_owner_raw')->nullable()->after('current_owner_normalized');
            $table->string('previous_owner_normalized', 500)->nullable()->after('previous_owner_raw');
            $table->text('co_owner_raw')->nullable()->after('previous_owner_normalized');
            $table->string('claimant_mailing_address', 500)->nullable()->after('co_owner_raw');
            $table->string('claimant_mailing_city', 160)->nullable()->after('claimant_mailing_address');
            $table->string('claimant_mailing_state', 2)->nullable()->after('claimant_mailing_city');
            $table->string('claimant_mailing_zip', 20)->nullable()->after('claimant_mailing_state');
            $table->unsignedSmallInteger('historical_trim_year')->nullable()->after('claimant_mailing_zip');
            $table->string('property_appraiser_address', 500)->nullable()->after('historical_trim_year');
            $table->string('owner_type', 40)->nullable()->after('property_appraiser_address')->index();
            $table->boolean('property_appraiser_verified')->default(false)->after('owner_type');
            $table->boolean('historical_owner_verified')->default(false)->after('property_appraiser_verified');
            $table->timestamp('owner_researched_at')->nullable()->after('historical_owner_verified');
            $table->text('owner_research_notes')->nullable()->after('owner_researched_at');
        });

        Schema::create('surplus_owner_research_attempts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('surplus_owner_research_batch_id')->nullable();
            $table->foreign('surplus_owner_research_batch_id', 'owner_attempt_batch_fk')
                ->references('id')->on('surplus_owner_research_batches')->nullOnDelete();
            $table->foreignId('surplus_case_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->string('status', 60)->index();
            $table->string('parcel_searched', 120);
            $table->string('parcel_returned', 120)->nullable();
            $table->text('current_owner_found')->nullable();
            $table->json('trim_years_checked')->nullable();
            $table->unsignedSmallInteger('selected_trim_year')->nullable();
            $table->text('historical_owner_found')->nullable();
            $table->string('classification', 40)->nullable();
            $table->string('property_source_reference', 2048)->nullable();
            $table->string('trim_source_reference', 2048)->nullable();
            $table->string('trim_file_disk', 40)->nullable();
            $table->string('trim_file_path', 1024)->nullable();
            $table->char('trim_file_hash', 64)->nullable();
            $table->text('extracted_text_excerpt')->nullable();
            $table->text('browser_error')->nullable();
            $table->text('extraction_warning')->nullable();
            $table->text('research_notes')->nullable();
            $table->string('diagnostic_reference', 1024)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['surplus_case_id', 'attempt_number'], 'owner_attempt_case_number_unique');
            $table->index(['surplus_owner_research_batch_id', 'status'], 'owner_attempt_batch_status_idx');
        });

        Schema::create('surplus_owner_research_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('surplus_owner_research_attempt_id');
            $table->foreign('surplus_owner_research_attempt_id', 'owner_event_attempt_fk')
                ->references('id')->on('surplus_owner_research_attempts')->cascadeOnDelete();
            $table->string('event', 120);
            $table->json('context')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['surplus_owner_research_attempt_id', 'occurred_at'], 'owner_event_attempt_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surplus_owner_research_events');
        Schema::dropIfExists('surplus_owner_research_attempts');
        Schema::table('surplus_cases', function (Blueprint $table): void {
            $table->dropColumn([
                'current_owner_raw', 'current_owner_normalized', 'previous_owner_raw', 'previous_owner_normalized',
                'co_owner_raw', 'claimant_mailing_address', 'claimant_mailing_city', 'claimant_mailing_state',
                'claimant_mailing_zip', 'historical_trim_year', 'property_appraiser_address', 'owner_type',
                'property_appraiser_verified', 'historical_owner_verified', 'owner_researched_at', 'owner_research_notes',
            ]);
        });
        Schema::dropIfExists('surplus_owner_research_batches');
    }
};
