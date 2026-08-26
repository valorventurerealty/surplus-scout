<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->string('parcel_number', 120);
            $table->string('normalized_parcel_number', 120);
            $table->string('event_type', 40)->index();
            $table->timestamp('starts_at')->index();
            $table->string('auction_url', 2048);
            $table->string('property_address', 255);
            $table->string('county', 40)->index();
            $table->decimal('max_bid', 14, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['normalized_parcel_number', 'event_type', 'starts_at'],
                'calendar_events_parcel_type_start_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
