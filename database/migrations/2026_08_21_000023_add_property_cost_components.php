<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table): void {
            $table->decimal('attorney_fees', 14, 2)->nullable()->after('taxes');
            $table->decimal('realtor_fees', 14, 2)->nullable()->after('attorney_fees');
            $table->decimal('other_costs', 14, 2)->nullable()->after('realtor_fees');
        });

        DB::table('properties')
            ->select(['id', 'purchase_price', 'taxes', 'all_in_amount'])
            ->whereNotNull('all_in_amount')
            ->orderBy('id')
            ->chunkById(200, function ($properties): void {
                foreach ($properties as $property) {
                    $knownCents = (int) round(((float) ($property->purchase_price ?? 0) + (float) ($property->taxes ?? 0)) * 100);
                    $allInCents = (int) round((float) $property->all_in_amount * 100);
                    DB::table('properties')->where('id', $property->id)->update([
                        'other_costs' => number_format(max($allInCents - $knownCents, 0) / 100, 2, '.', ''),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table): void {
            $table->dropColumn(['attorney_fees', 'realtor_fees', 'other_costs']);
        });
    }
};
