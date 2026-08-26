<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('contacts')->orderBy('id')->chunkById(200, function ($contacts): void {
            foreach ($contacts as $contact) {
                DB::table('contacts')->where('id', $contact->id)->update([
                    'normalized_email' => filled($contact->email) ? strtolower(trim($contact->email)) : null,
                    'normalized_phone' => filled($contact->phone) ? (preg_replace('/\D+/', '', $contact->phone) ?: null) : null,
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::table('contacts')->update(['normalized_email' => null, 'normalized_phone' => null]);
    }
};
