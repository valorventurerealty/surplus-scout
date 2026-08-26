<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('surplus_cases')
            ->where('agreed_fee_percentage', '>', 12)
            ->orderBy('id')
            ->chunkById(100, function ($cases): void {
                foreach ($cases as $case) {
                    $expectedFee = $case->surplus_amount !== null
                        ? number_format(round((float) $case->surplus_amount * 12) / 100, 2, '.', '')
                        : null;

                    DB::table('surplus_cases')->where('id', $case->id)->update([
                        'agreed_fee_percentage' => 12,
                        'expected_fee' => $expectedFee,
                        'updated_at' => now(),
                    ]);
                }
            });

        DB::table('surplus_cases')
            ->whereNotNull('actual_fee')
            ->orderBy('id')
            ->chunkById(100, function ($cases): void {
                foreach ($cases as $case) {
                    $base = $case->recovered_amount ?? $case->surplus_amount;
                    if ($base === null) {
                        continue;
                    }
                    $maximumActualFee = number_format(round((float) $base * 12) / 100, 2, '.', '');
                    if ((float) $case->actual_fee > (float) $maximumActualFee) {
                        DB::table('surplus_cases')->where('id', $case->id)->update([
                            'actual_fee' => $maximumActualFee,
                            'updated_at' => now(),
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        // The prior non-compliant percentage cannot be reconstructed safely.
    }
};
