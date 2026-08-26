<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('negotiation_plans', function (Blueprint $table): void {
            $table->boolean('sync_property_financials')->default(true)->after('property_id');
            $table->timestamp('financials_synced_at')->nullable()->after('all_in_amount');
        });
    }

    public function down(): void
    {
        Schema::table('negotiation_plans', function (Blueprint $table): void {
            $table->dropColumn(['sync_property_financials', 'financials_synced_at']);
        });
    }
};
