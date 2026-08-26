<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pre_auction_acquisitions')) {
            Schema::create('pre_auction_acquisitions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('token')->unique();
            $table->string('case_number', 30)->nullable()->unique();
            $table->string('status', 40)->default('research')->index();
            $table->foreignId('owner_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 120)->nullable();
            $table->string('state', 2)->default('FL')->index();
            $table->string('county', 120)->nullable()->index();
            $table->string('parcel_id', 120)->nullable()->index();
            $table->string('normalized_parcel_id', 120)->nullable()->index();
            $table->string('tax_deed_number', 120)->nullable()->index();
            $table->string('certificate_number', 120)->nullable();
            $table->dateTime('auction_at')->nullable()->index();
            $table->string('auction_url', 2048)->nullable();
            $table->date('purchase_deadline')->nullable()->index();
            $table->date('contract_date')->nullable();
            $table->date('closing_date')->nullable();
            $table->date('deed_recorded_date')->nullable();
            $table->string('recording_instrument_number', 160)->nullable();
            $table->date('non_redemption_reviewed_at')->nullable();
            $table->foreignId('non_redemption_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('purchase_price', 14, 2)->nullable();
            $table->decimal('closing_costs', 14, 2)->nullable();
            $table->decimal('other_costs', 14, 2)->nullable();
            $table->decimal('total_acquisition_cost', 14, 2)->nullable();
            $table->decimal('projected_surplus', 14, 2)->nullable();
            $table->decimal('projected_net', 14, 2)->nullable();
            $table->decimal('auction_winning_bid', 14, 2)->nullable();
            $table->decimal('surplus_generated', 14, 2)->nullable();
            $table->string('entitlement_status', 40)->default('not_reviewed')->index();
            $table->date('entitlement_reviewed_at')->nullable();
            $table->foreignId('entitlement_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('entitlement_notes')->nullable();
            $table->date('claim_submitted_at')->nullable();
            $table->date('paid_at')->nullable();
            $table->decimal('amount_recovered', 14, 2)->nullable();
            $table->decimal('actual_net', 14, 2)->nullable();
            $table->string('document_drive_url', 2048)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'assigned_user_id', 'auction_at'], 'pre_auction_status_user_auction_idx');
            $table->index(['state', 'county', 'normalized_parcel_id'], 'pre_auction_location_parcel_index');
            });
        } else {
            // MySQL commits CREATE TABLE before a later index statement can fail. These
            // guards let a shared-hosting deployment safely resume without dropping data.
            if (! Schema::hasIndex('pre_auction_acquisitions', 'pre_auction_status_user_auction_idx')) {
                Schema::table('pre_auction_acquisitions', function (Blueprint $table): void {
                    $table->index(['status', 'assigned_user_id', 'auction_at'], 'pre_auction_status_user_auction_idx');
                });
            }
            if (! Schema::hasIndex('pre_auction_acquisitions', 'pre_auction_location_parcel_index')) {
                Schema::table('pre_auction_acquisitions', function (Blueprint $table): void {
                    $table->index(['state', 'county', 'normalized_parcel_id'], 'pre_auction_location_parcel_index');
                });
            }
        }

        if (! Schema::hasTable('contact_pre_auction_acquisition')) {
            Schema::create('contact_pre_auction_acquisition', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pre_auction_acquisition_id');
            $table->foreign('pre_auction_acquisition_id', 'pre_auction_contact_case_fk')
                ->references('id')->on('pre_auction_acquisitions')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('role', 40)->default('other');
            $table->string('relationship_notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['pre_auction_acquisition_id', 'contact_id'], 'pre_auction_contact_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_pre_auction_acquisition');
        Schema::dropIfExists('pre_auction_acquisitions');
    }
};
