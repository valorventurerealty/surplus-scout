<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('armory_scripts', function (Blueprint $table): void {
            $table->foreignId('default_next_script_id')->nullable()->after('status')
                ->constrained('armory_scripts')->nullOnDelete();
        });

        Schema::table('armory_script_step_options', function (Blueprint $table): void {
            $table->foreignId('next_script_id')->nullable()->after('next_step_id')
                ->constrained('armory_scripts')->nullOnDelete();
        });

        Schema::table('armory_sessions', function (Blueprint $table): void {
            $table->foreignId('started_armory_script_id')->nullable()->after('armory_script_id')
                ->constrained('armory_scripts')->nullOnDelete();
        });

        DB::table('armory_sessions')->whereNull('started_armory_script_id')->update([
            'started_armory_script_id' => DB::raw('armory_script_id'),
        ]);
    }

    public function down(): void
    {
        Schema::table('armory_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('started_armory_script_id');
        });

        Schema::table('armory_script_step_options', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('next_script_id');
        });

        Schema::table('armory_scripts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('default_next_script_id');
        });
    }
};
