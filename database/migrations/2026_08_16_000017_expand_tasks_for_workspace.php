<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->timestamp('reminder_at')->nullable()->index()->after('due_at');
            $table->timestamp('reminder_sent_at')->nullable()->after('reminder_at');
            $table->string('recurrence_frequency', 20)->nullable()->after('completed_at');
            $table->unsignedSmallInteger('recurrence_interval')->default(1)->after('recurrence_frequency');
            $table->timestamp('recurrence_ends_at')->nullable()->after('recurrence_interval');
            $table->foreignId('recurrence_parent_id')->nullable()->after('recurrence_ends_at')->constrained('tasks')->nullOnDelete();
            $table->string('recurrence_key', 64)->nullable()->unique()->after('recurrence_parent_id');
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');

        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropForeign(['recurrence_parent_id']);
            $table->dropUnique(['recurrence_key']);
            $table->dropColumn([
                'reminder_at', 'reminder_sent_at', 'recurrence_frequency', 'recurrence_interval',
                'recurrence_ends_at', 'recurrence_parent_id', 'recurrence_key',
            ]);
        });
    }
};
