<?php

namespace Database\Factories;

use App\Models\ClientRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

class RequestItemFactory extends Factory
{
    public function definition(): array
    {
        return ['client_request_id' => ClientRequest::factory(), 'label' => 'Company name', 'type' => 'text', 'required' => true, 'position' => 0];
    }
}
