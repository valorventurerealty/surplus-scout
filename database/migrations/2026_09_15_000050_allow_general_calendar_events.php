<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->string('title', 255)->nullable()->after('property_id');
            $table->string('parcel_number', 120)->nullable()->change();
            $table->string('normalized_parcel_number', 120)->nullable()->change();
            $table->string('auction_url', 2048)->nullable()->change();
            $table->string('property_address', 255)->nullable()->change();
            $table->string('county', 40)->nullable()->change();
        });
    }

    public function down(): void
    {
        $hasNullableEventData = DB::table('calendar_events')
            ->whereNull('parcel_number')
            ->orWhereNull('normalized_parcel_number')
            ->orWhereNull('auction_url')
            ->orWhereNull('property_address')
            ->orWhereNull('county')
            ->exists();

        if ($hasNullableEventData) {
            throw new LogicException('Calendar events with optional auction fields exist; preserve them before rolling back this migration.');
        }

        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->string('parcel_number', 120)->nullable(false)->change();
            $table->string('normalized_parcel_number', 120)->nullable(false)->change();
            $table->string('auction_url', 2048)->nullable(false)->change();
            $table->string('property_address', 255)->nullable(false)->change();
            $table->string('county', 40)->nullable(false)->change();
            $table->dropColumn('title');
        });
    }
};
