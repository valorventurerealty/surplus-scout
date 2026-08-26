<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surplus_research_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('token')->unique();
            $table->string('county', 120)->index();
            $table->string('source_name', 160);
            $table->string('source_url', 2048);
            $table->timestamp('source_report_date')->nullable();
            $table->string('source_file_disk', 40)->nullable();
            $table->string('source_file_path', 1024)->nullable();
            $table->char('source_file_hash', 64)->nullable()->index();
            $table->unsignedBigInteger('source_file_size')->nullable();
            $table->string('status', 40)->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('records_found')->default(0);
            $table->unsignedInteger('new_records')->default(0);
            $table->unsignedInteger('existing_records')->default(0);
            $table->unsignedInteger('amount_changes')->default(0);
            $table->unsignedInteger('removed_records')->default(0);
            $table->unsignedInteger('failed_records')->default(0);
            $table->unsignedInteger('warning_count')->default(0);
            $table->json('warnings')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['county', 'status', 'created_at'], 'surplus_runs_county_status_created_idx');
        });

        Schema::table('surplus_cases', function (Blueprint $table): void {
            $table->string('research_status', 60)->nullable()->after('status')->index();
            $table->string('surplus_availability', 40)->nullable()->after('research_status')->index();
            $table->string('parcel_id_raw', 120)->nullable()->after('parcel_id');
            $table->string('clerk_unique_key', 255)->nullable()->after('normalized_parcel_id')->unique();
            $table->string('source_name', 160)->nullable()->after('source');
            $table->string('source_url', 2048)->nullable()->after('source_name');
            $table->timestamp('source_report_date')->nullable()->after('source_url');
            $table->timestamp('source_last_seen_at')->nullable()->after('source_report_date');
            $table->foreignId('last_surplus_research_run_id')->nullable()->after('source_last_seen_at')
                ->constrained('surplus_research_runs')->nullOnDelete();
        });

        Schema::create('surplus_amount_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('surplus_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('research_run_id')->constrained('surplus_research_runs')->cascadeOnDelete();
            $table->decimal('previous_amount', 14, 2)->nullable();
            $table->decimal('new_amount', 14, 2);
            $table->timestamp('changed_at');
            $table->timestamps();
            $table->unique(['surplus_case_id', 'research_run_id'], 'surplus_amount_history_case_run_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surplus_amount_histories');
        Schema::table('surplus_cases', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('last_surplus_research_run_id');
            $table->dropUnique(['clerk_unique_key']);
            $table->dropColumn([
                'research_status', 'surplus_availability', 'parcel_id_raw', 'clerk_unique_key',
                'source_name', 'source_url', 'source_report_date', 'source_last_seen_at',
            ]);
        });
        Schema::dropIfExists('surplus_research_runs');
    }
};
