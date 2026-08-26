<?php

namespace Database\Seeders;

use App\Models\EmailSignature;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EmailWorkspaceSeeder extends Seeder
{
    public function run(): void
    {
        EmailSignature::query()->firstOrCreate(['name' => 'Mark Lewis — Default'], ['token' => (string) Str::uuid(), 'is_default' => true, 'is_active' => true, 'body_text' => "Thank you,\n\nMark Lewis, MBA\nManaging Partner, President\nValor Venture Realty, LLC\nhttps://valorventure.us/\n(407) 900-6554\nSchedule Meeting: https://valorventure.us/meetings/valorventurerealty", 'body_html' => '<p>Thank you,</p><p><strong>Mark Lewis, MBA</strong><br>Managing Partner, President<br>Valor Venture Realty, LLC<br><a href="https://valorventure.us/">ValorVenture.us</a><br><a href="tel:+14079006554">(407) 900-6554</a><br><a href="https://valorventure.us/meetings/valorventurerealty">Schedule Meeting</a></p>']);
    }
}
