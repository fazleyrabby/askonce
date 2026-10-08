<?php

namespace Tests\Feature;

use App\Actions\Auth\RegisterUser;
use App\Models\Client;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    private function owner(string $email = 'owner@example.com', bool $verified = true): User
    {
        $user = app(RegisterUser::class)->handle([
            'name' => 'Owner', 'business_name' => 'Studio', 'email' => $email,
            'password' => 'password123', 'timezone' => 'Asia/Dhaka',
        ]);
        if ($verified) {
            $user->markEmailAsVerified();
        }

        return $user;
    }

    public function test_registration_creates_owner_and_sends_verification(): void
    {
        Notification::fake();
        $this->post('/register', [
            'name' => 'Jane', 'business_name' => 'Jane Studio', 'email' => 'jane@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123', 'timezone' => 'Asia/Dhaka',
        ])->assertRedirect(route('verification.notice'));
        $user = User::firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertSame('owner', $user->organizations()->first()->pivot->role);
        $this->assertDatabaseHas('organizations', ['name' => 'Jane Studio', 'timezone' => 'Asia/Dhaka', 'storage_quota_bytes' => 104857600]);
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->get('/clients')->assertRedirect(route('verification.notice'));
    }

    public function test_invalid_registration_does_not_create_partial_records(): void
    {
        $this->post('/register', ['name' => 'Jane', 'business_name' => 'Studio', 'email' => 'bad', 'timezone' => 'invalid', 'password' => 'short'])->assertSessionHasErrors();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('organizations', 0);
    }

    public function test_signed_email_verification_unlocks_dashboard(): void
    {
        $user = $this->owner(verified: false);
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->actingAs($user)->get($url)->assertRedirect(route('dashboard'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->get('/dashboard')->assertOk();
    }

    public function test_login_logout_and_wrong_password(): void
    {
        $user = $this->owner();
        $this->post('/login', ['email' => $user->email, 'password' => 'incorrect'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => $user->email, 'password' => 'password123'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_password_reset_uses_real_notification_token(): void
    {
        Notification::fake();
        $user = $this->owner();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->post('/reset-password', [
                'email' => $user->email, 'token' => $notification->token,
                'password' => 'newpassword123', 'password_confirmation' => 'newpassword123',
            ])->assertRedirect(route('login'));

            return true;
        });
        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }

    public function test_clients_are_scoped_and_cannot_be_moved_by_input(): void
    {
        $alice = $this->owner('alice@example.com');
        $bob = $this->owner('bob@example.com');
        $this->actingAs($alice)->post('/clients', ['name' => 'Alice client', 'email' => 'client@example.com', 'organization_id' => $bob->organizations()->first()->id])->assertRedirect(route('clients.index'));
        $client = Client::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($alice->organizations()->first()->id, $client->organization_id);
        $this->actingAs($bob)->get('/clients')->assertOk()->assertDontSee('Alice client');
        $this->get('/clients/'.$client->id.'/edit')->assertNotFound();
        $this->put('/clients/'.$client->id, ['name' => 'Stolen', 'email' => 'bad@example.com'])->assertNotFound();
        $this->actingAs($alice)->put('/clients/'.$client->id, ['name' => 'Updated client', 'email' => 'client@example.com', 'organization_id' => $bob->organizations()->first()->id])->assertRedirect(route('clients.index'));
        $this->assertDatabaseHas('clients', ['id' => $client->id, 'name' => 'Updated client', 'organization_id' => $alice->organizations()->first()->id]);
    }

    public function test_client_queries_fail_closed_without_organization(): void
    {
        $this->expectException(\LogicException::class);
        Client::count();
    }

    public function test_guest_cannot_access_clients(): void
    {
        $this->get('/clients')->assertRedirect(route('login'));
        $this->post('/clients', ['name' => 'Client', 'email' => 'test@example.com'])->assertRedirect(route('login'));
    }
}
