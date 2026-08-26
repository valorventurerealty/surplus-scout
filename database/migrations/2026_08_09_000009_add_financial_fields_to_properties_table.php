<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table): void {
            $table->decimal('taxes', 14, 2)->nullable()->after('investor_price');
            $table->decimal('expected_sales_price', 14, 2)->nullable()->after('taxes');
            $table->decimal('actual_sales_price', 14, 2)->nullable()->after('expected_sales_price');
            $table->decimal('expected_profit', 14, 2)->nullable()->after('actual_sales_price');
            $table->decimal('actual_profit', 14, 2)->nullable()->after('expected_profit');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table): void {
            $table->dropColumn([
                'taxes',
                'expected_sales_price',
                'actual_sales_price',
                'expected_profit',
                'actual_profit',
            ]);
        });
    }
};
