<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientRequestFactory extends Factory
{
    public function definition(): array
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        return ['client_id' => Client::factory(), 'title' => 'Website content', 'due_at' => now()->addWeek()->toDateString(), 'token' => $token, 'token_hash' => hash('sha256', $token)];
    }
}
