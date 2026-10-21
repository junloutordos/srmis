<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    // database/factories/UserFactory.php
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->userName() . '@crc.pshs.edu.ph',
            'email_verified_at' => now(),
            'password' => bcrypt('password'), // or Hash::make()
            'remember_token' => Str::random(10),
            'role_id' => null, // roles managed via role_user pivot
            // Signature + PIN are mandatory to file or sign; PIN is "123456" in tests.
            'electronic_signature' => 'signatures/test.png',
            'signature_pin' => '123456',
        ];
    }


    /** A user who has not done the signature + PIN setup yet. */
    public function withoutSignature(): static
    {
        return $this->state(fn () => ['electronic_signature' => null, 'signature_pin' => null]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
