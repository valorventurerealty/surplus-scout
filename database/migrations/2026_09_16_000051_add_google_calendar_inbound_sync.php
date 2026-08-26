<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('google_calendar_connections', function (Blueprint $table): void {
            $table->boolean('inbound_sync_enabled')->default(false)->after('last_synced_at');
            $table->timestamp('inbound_sync_started_at')->nullable()->after('inbound_sync_enabled');
            $table->text('inbound_sync_token')->nullable()->after('inbound_sync_started_at');
            $table->timestamp('last_inbound_sync_at')->nullable()->after('inbound_sync_token');
            $table->text('inbound_sync_error')->nullable()->after('last_inbound_sync_at');
        });

        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->timestamp('ends_at')->nullable()->after('starts_at');
            $table->string('source', 20)->default('vvr')->after('event_type')->index();
            $table->char('google_event_key', 64)->nullable()->after('google_event_id');
            $table->json('google_attendees')->nullable()->after('google_event_etag');
            $table->string('google_organizer_email')->nullable()->after('google_attendees');
            $table->timestamp('google_updated_at')->nullable()->after('google_organizer_email');
            $table->timestamp('google_cancelled_at')->nullable()->after('google_updated_at');
        });

        $seen = [];
        DB::table('calendar_events')
            ->whereNotNull('google_event_id')
            ->whereNotNull('google_calendar_connection_id')
            ->orderBy('id')
            ->chunkById(100, function ($events) use (&$seen): void {
                foreach ($events as $event) {
                    $key = hash('sha256', $event->google_calendar_connection_id.'|'.$event->google_calendar_id.'|'.$event->google_event_id);
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    DB::table('calendar_events')->where('id', $event->id)->update(['google_event_key' => $key]);
                }
            });

        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->unique('google_event_key', 'cal_events_google_event_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->dropUnique('cal_events_google_event_key_unique');
            $table->dropIndex(['source']);
            $table->dropColumn([
                'ends_at', 'source', 'google_event_key', 'google_attendees',
                'google_organizer_email', 'google_updated_at', 'google_cancelled_at',
            ]);
        });

        Schema::table('google_calendar_connections', function (Blueprint $table): void {
            $table->dropColumn([
                'inbound_sync_enabled', 'inbound_sync_started_at', 'inbound_sync_token',
                'last_inbound_sync_at', 'inbound_sync_error',
            ]);
        });
    }
};
