<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_surplus_csv_imports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->string('disk', 40)->default('local');
            $table->string('path', 1000);
            $table->string('original_name');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64)->index();
            $table->string('status', 30)->default('ready')->index();
            $table->string('default_state', 2)->default('FL');
            $table->string('default_county', 120)->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('valid_row_count')->default(0);
            $table->json('result_json')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('ai_surplus_csv_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('import_id')->constrained('ai_surplus_csv_imports')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('first_name', 120)->nullable();
            $table->string('last_name', 120)->nullable();
            $table->string('mailing_address_line_1')->nullable();
            $table->string('mailing_city', 120)->nullable();
            $table->string('mailing_state', 120)->nullable();
            $table->string('mailing_country', 2)->nullable();
            $table->string('mailing_postal_code', 20)->nullable();
            $table->string('parcel_id', 120)->nullable();
            $table->string('normalized_parcel_id', 120)->nullable()->index();
            $table->decimal('surplus_amount', 14, 2)->nullable();
            $table->string('status', 30)->default('ready')->index();
            $table->json('errors_json')->nullable();
            $table->json('warnings_json')->nullable();
            $table->foreignId('matched_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('matched_surplus_case_id')->nullable()->constrained('surplus_cases')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('surplus_case_id')->nullable()->constrained('surplus_cases')->nullOnDelete();
            $table->timestamps();

            $table->unique(['import_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_surplus_csv_import_rows');
        Schema::dropIfExists('ai_surplus_csv_imports');
    }
};
