<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('parcel_id', 120)->nullable();
            $table->string('normalized_parcel_id', 120)->nullable();
            $table->string('county', 120);
            $table->string('normalized_county', 120);
            $table->string('address', 255);
            $table->string('city', 120);
            $table->char('state', 2);
            $table->string('postal_code', 10)->nullable();
            $table->string('normalized_address', 500)->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('property_type', 40)->index();
            $table->string('status', 40)->default('research')->index();
            $table->decimal('acreage', 12, 4)->nullable();
            $table->string('zoning', 120)->nullable();
            $table->string('flood_zone', 120)->nullable();
            $table->string('wetlands_status', 40)->default('unknown');
            $table->string('road_access', 160)->nullable();
            $table->json('utilities')->nullable();
            $table->json('gis_links')->nullable();
            $table->foreignId('owner_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->decimal('purchase_price', 14, 2)->nullable();
            $table->decimal('arv', 14, 2)->nullable();
            $table->decimal('wholesale_price', 14, 2)->nullable();
            $table->decimal('investor_price', 14, 2)->nullable();
            $table->text('legal_description')->nullable();
            $table->text('research_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['state', 'normalized_county', 'normalized_parcel_id'], 'properties_jurisdiction_parcel_unique');
            $table->index(['state', 'normalized_county']);
            $table->index(['owner_contact_id', 'status']);
            $table->index(['status', 'property_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
