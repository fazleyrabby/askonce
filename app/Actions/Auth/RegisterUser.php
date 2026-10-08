<?php

namespace App\Actions\Auth;

use App\Models\Organization;
use App\Models\User;
use App\Support\RecordActivity;
use Illuminate\Support\Facades\DB;

class RegisterUser
{
    public function handle(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
            $organization = Organization::create(['name' => $data['business_name'], 'timezone' => $data['timezone']]);
            $organization->users()->attach($user, ['role' => 'owner']);
            app(RecordActivity::class)->record('user_registered', $organization->id);

            return $user;
        });
    }
}
