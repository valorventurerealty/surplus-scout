<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('surplus_cases')
            ->orderBy('id')
            ->chunkById(100, function ($cases): void {
                foreach ($cases as $case) {
                    DB::table('surplus_cases')->where('id', $case->id)->update([
                        'agreed_fee_percentage' => 12,
                        'expected_fee' => $case->surplus_amount !== null
                            ? number_format(round((float) $case->surplus_amount * 12) / 100, 2, '.', '')
                            : null,
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Prior percentages and expected fees cannot be reconstructed safely.
    }
};
