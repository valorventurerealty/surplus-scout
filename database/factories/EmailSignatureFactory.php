<?php

namespace Database\Factories;

use App\Models\EmailSignature;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EmailSignature> */
class EmailSignatureFactory extends Factory
{
    protected $model = EmailSignature::class;
    public function definition(): array
    {
        return ['token' => fake()->uuid(), 'name' => fake()->name().' Signature', 'is_default' => false, 'is_active' => true, 'body_text' => "Thank you,\n\n".fake()->name(), 'body_html' => '<p>Thank you,</p><p>'.e(fake()->name()).'</p>'];
    }
}
