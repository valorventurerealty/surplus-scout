<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pre_auction_acquisitions', function (Blueprint $table): void {
            if (! Schema::hasColumn('pre_auction_acquisitions', 'assessor_market_value')) {
                $table->decimal('assessor_market_value', 14, 2)->nullable()->after('certificate_number');
            }
            if (! Schema::hasColumn('pre_auction_acquisitions', 'appraiser_url')) {
                $table->string('appraiser_url', 2048)->nullable()->after('assessor_market_value');
            }
            if (! Schema::hasColumn('pre_auction_acquisitions', 'property_details_url')) {
                $table->string('property_details_url', 2048)->nullable()->after('appraiser_url');
            }
        });

        if (! Schema::hasTable('ai_pre_auction_csv_imports')) {
            Schema::create('ai_pre_auction_csv_imports', function (Blueprint $table): void {
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
                $table->unsignedInteger('row_count')->default(0);
                $table->unsignedInteger('valid_row_count')->default(0);
                $table->json('result_json')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamp('executed_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'status'], 'ai_paq_csv_user_status_idx');
            });
        }

        if (! Schema::hasTable('ai_pre_auction_csv_import_rows')) {
            Schema::create('ai_pre_auction_csv_import_rows', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('import_id');
                $table->foreign('import_id', 'ai_paq_csv_row_import_fk')->references('id')->on('ai_pre_auction_csv_imports')->cascadeOnDelete();
                $table->unsignedInteger('row_number');
                $table->string('first_name', 120)->nullable();
                $table->string('last_name', 120)->nullable();
                $table->string('owner_record_name')->nullable();
                $table->string('mailing_address_line_1')->nullable();
                $table->string('mailing_city', 120)->nullable();
                $table->string('mailing_state', 120)->nullable();
                $table->string('mailing_country', 2)->nullable();
                $table->string('mailing_postal_code', 20)->nullable();
                $table->string('listing_type', 80)->nullable();
                $table->decimal('assessor_market_value', 14, 2)->nullable();
                $table->dateTime('auction_at')->nullable();
                $table->string('parcel_id', 120)->nullable();
                $table->string('normalized_parcel_id', 120)->nullable()->index();
                $table->string('county', 120)->nullable()->index();
                $table->string('appraiser_url', 2048)->nullable();
                $table->string('property_details_url', 2048)->nullable();
                $table->string('status', 30)->default('ready')->index();
                $table->json('errors_json')->nullable();
                $table->json('warnings_json')->nullable();
                $table->unsignedBigInteger('matched_contact_id')->nullable();
                $table->foreign('matched_contact_id', 'ai_paq_csv_row_match_contact_fk')->references('id')->on('contacts')->nullOnDelete();
                $table->unsignedBigInteger('matched_pre_auction_id')->nullable();
                $table->foreign('matched_pre_auction_id', 'ai_paq_csv_row_match_case_fk')->references('id')->on('pre_auction_acquisitions')->nullOnDelete();
                $table->unsignedBigInteger('contact_id')->nullable();
                $table->foreign('contact_id', 'ai_paq_csv_row_contact_fk')->references('id')->on('contacts')->nullOnDelete();
                $table->unsignedBigInteger('pre_auction_id')->nullable();
                $table->foreign('pre_auction_id', 'ai_paq_csv_row_case_fk')->references('id')->on('pre_auction_acquisitions')->nullOnDelete();
                $table->timestamps();

                $table->unique(['import_id', 'row_number'], 'ai_paq_csv_import_row_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_pre_auction_csv_import_rows');
        Schema::dropIfExists('ai_pre_auction_csv_imports');
        Schema::table('pre_auction_acquisitions', function (Blueprint $table): void {
            $columns = collect(['assessor_market_value', 'appraiser_url', 'property_details_url'])
                ->filter(fn (string $column): bool => Schema::hasColumn('pre_auction_acquisitions', $column))->all();
            if ($columns !== []) $table->dropColumn($columns);
        });
    }
};
