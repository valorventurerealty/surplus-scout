<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->string('mailing_address_line_1')->nullable()->after('phone');
            $table->string('mailing_address_line_2')->nullable()->after('mailing_address_line_1');
            $table->string('mailing_city', 120)->nullable()->after('mailing_address_line_2');
            $table->string('mailing_state_province', 120)->nullable()->after('mailing_city');
            $table->string('mailing_postal_code', 30)->nullable()->after('mailing_state_province');
            $table->string('mailing_country', 100)->nullable()->after('mailing_postal_code');
        });

        Schema::create('surplus_intake_files', function (Blueprint $table): void {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ai_conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('surplus_case_id')->nullable()->constrained('surplus_cases')->restrictOnDelete();
            $table->string('disk', 40)->default('local');
            $table->string('path', 500);
            $table->string('original_name');
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64)->index();
            $table->char('request_fingerprint', 64)->nullable()->index();
            $table->string('status', 30)->default('processing')->index();
            $table->text('user_prompt')->nullable();
            $table->string('provider', 40)->nullable();
            $table->string('model', 120)->nullable();
            $table->string('provider_response_id', 150)->nullable();
            $table->json('extraction_json')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('attached_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'sha256', 'status']);
        });

        Schema::create('property_tax_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_intake_id')->nullable()->constrained('surplus_intake_files')->nullOnDelete();
            $table->unsignedSmallInteger('tax_year');
            $table->decimal('market_value', 14, 2)->nullable();
            $table->decimal('assessed_value', 14, 2)->nullable();
            $table->decimal('taxable_value', 14, 2)->nullable();
            $table->decimal('prior_year_final_tax', 14, 2)->nullable();
            $table->decimal('proposed_tax', 14, 2)->nullable();
            $table->decimal('no_budget_change_tax', 14, 2)->nullable();
            $table->decimal('non_ad_valorem_assessments', 14, 2)->nullable();
            $table->string('assessment_classification')->nullable();
            $table->string('source_document_type', 80)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['property_id', 'tax_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_tax_records');
        Schema::dropIfExists('surplus_intake_files');

        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropColumn([
                'mailing_address_line_1', 'mailing_address_line_2', 'mailing_city',
                'mailing_state_province', 'mailing_postal_code', 'mailing_country',
            ]);
        });
    }
};
