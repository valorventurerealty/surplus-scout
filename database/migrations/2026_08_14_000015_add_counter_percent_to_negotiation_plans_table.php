<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('negotiation_plans', function (Blueprint $table): void {
            $table->decimal('counter_percent', 5, 2)->nullable()->after('buyer_offer');
        });
    }

    public function down(): void
    {
        Schema::table('negotiation_plans', function (Blueprint $table): void {
            $table->dropColumn('counter_percent');
        });
    }
};
