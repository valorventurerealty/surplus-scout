<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('armory_scripts', function (Blueprint $table): void {
            $table->foreignId('default_next_script_step_id')->nullable()->after('default_next_script_id')
                ->constrained('armory_script_steps')->nullOnDelete();
        });

        Schema::table('armory_script_step_options', function (Blueprint $table): void {
            $table->foreignId('next_script_step_id')->nullable()->after('next_script_id')
                ->constrained('armory_script_steps')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('armory_script_step_options', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('next_script_step_id');
        });

        Schema::table('armory_scripts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('default_next_script_step_id');
        });
    }
};
