<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_calendar_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('google_account_email')->nullable();
            $table->string('calendar_id')->default('primary');
            $table->string('calendar_name')->nullable();
            $table->longText('access_token')->nullable();
            $table->longText('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->foreignId('google_calendar_connection_id')->nullable()->after('updated_by')
                ->constrained('google_calendar_connections')->nullOnDelete();
            $table->string('google_calendar_id')->nullable()->after('google_calendar_connection_id');
            $table->string('google_event_id', 1024)->nullable()->after('google_calendar_id');
            $table->string('google_event_html_link', 2048)->nullable()->after('google_event_id');
            $table->string('google_event_etag')->nullable()->after('google_event_html_link');
            $table->string('google_sync_status', 30)->default('not_configured')->after('google_event_etag')->index();
            $table->unsignedInteger('google_sync_version')->default(0)->after('google_sync_status');
            $table->text('google_sync_error')->nullable()->after('google_sync_version');
            $table->timestamp('google_sync_attempted_at')->nullable()->after('google_sync_error');
            $table->timestamp('google_synced_at')->nullable()->after('google_sync_attempted_at');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('google_calendar_connection_id');
            $table->dropColumn([
                'google_calendar_id', 'google_event_id', 'google_event_html_link', 'google_event_etag',
                'google_sync_status', 'google_sync_version', 'google_sync_error',
                'google_sync_attempted_at', 'google_synced_at',
            ]);
        });

        Schema::dropIfExists('google_calendar_connections');
    }
};
