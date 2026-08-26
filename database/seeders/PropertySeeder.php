<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Seeder;

class PropertySeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $user = User::query()->first();
        $contacts = Contact::query()->limit(10)->get();

        Property::factory()->count(12)->make()->each(function (Property $property, int $index) use ($contacts, $user): void {
            $property->owner_contact_id = $contacts->get($index % max($contacts->count(), 1))?->id;
            $property->created_by = $user?->id;
            $property->updated_by = $user?->id;
            $property->save();
        });
    }
}
