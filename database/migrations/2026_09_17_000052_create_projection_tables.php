<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projection_scenarios', function (Blueprint $table): void {
            $table->id();
            $table->uuid('token')->unique();
            $table->string('name');
            $table->string('status', 20)->default('draft');
            $table->unsignedSmallInteger('start_year');
            $table->unsignedSmallInteger('end_year');
            $table->foreignId('contact_one_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('contact_two_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->boolean('is_default')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'is_default'], 'projection_scenarios_status_default_idx');
        });

        Schema::create('projection_assumptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('projection_scenario_id')->constrained()->cascadeOnDelete();
            $table->string('category', 40);
            $table->decimal('average_net_profit', 14, 2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['projection_scenario_id', 'category'], 'projection_assumptions_scenario_category_uq');
        });

        Schema::create('projection_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('projection_scenario_id')->constrained()->cascadeOnDelete();
            $table->string('category', 40);
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedInteger('projected_units')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(
                ['projection_scenario_id', 'category', 'year', 'month'],
                'projection_entries_scenario_period_uq'
            );
            $table->index(['projection_scenario_id', 'year'], 'projection_entries_scenario_year_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projection_entries');
        Schema::dropIfExists('projection_assumptions');
        Schema::dropIfExists('projection_scenarios');
    }
};
