<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;
use App\Support\CurrentOrganization;

class ClientPolicy
{
    public function update(User $user, Client $client): bool
    {
        return $client->organization_id === app(CurrentOrganization::class)->get()->id
            && $user->organizations()->whereKey($client->organization_id)->exists();
    }
}
