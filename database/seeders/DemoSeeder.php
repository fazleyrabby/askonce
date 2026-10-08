<?php

namespace Database\Seeders;

use App\Actions\Auth\RegisterUser;
use App\Actions\Requests\CreateRequest;
use App\Models\Client;
use App\Models\ClientRequest;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo data may only be seeded in local or testing environments.');
        }
        foreach ([
            ['email' => 'admin@askonce.test', 'name' => 'Demo Admin', 'business_name' => 'AskOnce Demo Admin', 'admin' => true],
            ['email' => 'demo@askonce.test', 'name' => 'Demo User', 'business_name' => 'North Studio', 'admin' => false],
        ] as $account) {
            $user = User::where('email', $account['email'])->first();
            if (! $user) {
                $user = app(RegisterUser::class)->handle([...$account, 'password' => 'password', 'timezone' => 'Asia/Dhaka']);
                $user->forceFill(['is_admin' => $account['admin'], 'email_verified_at' => now()])->save();
            }
            if ($user->is_admin !== $account['admin'] || ! $user->organizations()->exists()) {
                throw new \RuntimeException('A demo email is already used by a different account.');
            }
            app(CurrentOrganization::class)->set($user->organizations()->firstOrFail());
            foreach ([
                ['name' => 'ABC Restaurant', 'contact_name' => 'John', 'email' => 'john@example.test'],
                ['name' => 'Bloom Florist', 'contact_name' => 'Maya', 'email' => 'maya@example.test'],
                ['name' => 'Oak & Co.', 'contact_name' => 'Sam', 'email' => 'sam@example.test'],
            ] as $data) {
                $client = Client::firstOrCreate(['email' => $data['email']], $data);
                if (ClientRequest::where('client_id', $client->id)->where('title', 'Website content')->exists()) {
                    continue;
                }
                $request = app(CreateRequest::class)->handle([
                    'client_id' => $client->id, 'title' => 'Website content',
                    'description' => 'Please share the following for your new website. You can save each item and return later.',
                    'due_at' => now()->addWeek()->toDateString(), 'reminder_interval_days' => 3,
                    'items' => [
                        ['label' => 'Company logo', 'type' => 'file', 'required' => true, 'help_text' => 'A high-resolution PNG or JPG works well.'],
                        ['label' => 'About your business', 'type' => 'long_text', 'required' => true],
                        ['label' => 'Website URL', 'type' => 'url', 'required' => true],
                        ['label' => 'Contact phone number', 'type' => 'text', 'required' => true],
                        ['label' => 'I confirm these details are correct', 'type' => 'confirmation', 'required' => true],
                    ],
                ]);
                $request->forceFill(['status' => 'sent', 'sent_at' => now()])->save();
            }
        }
    }
}
