<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surplus_cases', function (Blueprint $table): void {
            $table->id();
            $table->uuid('token')->unique();
            $table->string('case_number', 30)->nullable()->unique();
            $table->string('status', 40)->default('research')->index();
            $table->foreignId('claimant_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 120)->nullable();
            $table->string('state', 2)->nullable()->index();
            $table->string('county', 120)->nullable()->index();
            $table->string('parcel_id', 120)->nullable()->index();
            $table->string('foreclosure_case_number', 120)->nullable()->index();
            $table->string('certificate_number', 120)->nullable();
            $table->decimal('surplus_amount', 14, 2)->nullable();
            $table->decimal('agreed_fee_percentage', 5, 2)->nullable();
            $table->decimal('expected_fee', 14, 2)->nullable();
            $table->decimal('recovered_amount', 14, 2)->nullable();
            $table->decimal('actual_fee', 14, 2)->nullable();
            $table->date('sale_date')->nullable();
            $table->date('claim_deadline')->nullable()->index();
            $table->date('agreement_date')->nullable();
            $table->date('submitted_at')->nullable();
            $table->date('approved_at')->nullable();
            $table->date('paid_at')->nullable();
            $table->string('document_drive_url', 2048)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'assigned_user_id', 'claim_deadline']);
            $table->index(['state', 'county', 'parcel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surplus_cases');
    }
};
