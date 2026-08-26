<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surplus_cases', function (Blueprint $table): void {
            $table->string('normalized_parcel_id', 120)->nullable()->after('parcel_id')->index();
        });

        DB::table('surplus_cases')->whereNotNull('parcel_id')->orderBy('id')->chunkById(200, function ($cases): void {
            foreach ($cases as $case) {
                $normalized = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', (string) $case->parcel_id) ?? '');
                DB::table('surplus_cases')->where('id', $case->id)->update([
                    'normalized_parcel_id' => $normalized !== '' ? $normalized : null,
                ]);
            }
        });

        Schema::table('surplus_cases', function (Blueprint $table): void {
            $table->index(['state', 'county', 'normalized_parcel_id'], 'surplus_cases_jurisdiction_normalized_parcel_index');
        });
    }

    public function down(): void
    {
        Schema::table('surplus_cases', function (Blueprint $table): void {
            $table->dropIndex('surplus_cases_jurisdiction_normalized_parcel_index');
            $table->dropIndex(['normalized_parcel_id']);
            $table->dropColumn('normalized_parcel_id');
        });
    }
};
