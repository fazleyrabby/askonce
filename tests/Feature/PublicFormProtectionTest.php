<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PublicFormProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TrustProxies::flushState();
        parent::tearDown();
    }

    /**
     * @param  array<string, string>  $server
     */
    private function failedLogin(string $email, array $server = []): int
    {
        return $this->withServerVariables($server)->post('/login', ['email' => $email, 'password' => 'wrong-password'])->getStatusCode();
    }

    public function test_login_attempts_on_one_account_are_limited_across_addresses(): void
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->assertSame(302, $this->failedLogin('owner@example.test', ['REMOTE_ADDR' => '203.0.113.'.$attempt]));
        }
        $this->assertSame(429, $this->failedLogin('Owner@Example.test', ['REMOTE_ADDR' => '203.0.113.50']));
        $this->assertSame(302, $this->failedLogin('someone-else@example.test', ['REMOTE_ADDR' => '203.0.113.50']));
    }

    public function test_login_attempts_from_one_address_are_limited_across_accounts(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->assertSame(302, $this->failedLogin("user{$attempt}@example.test", ['REMOTE_ADDR' => '203.0.113.7']));
        }
        $this->assertSame(429, $this->failedLogin('user6@example.test', ['REMOTE_ADDR' => '203.0.113.7']));
    }

    public function test_visitors_behind_the_tunnel_are_limited_separately(): void
    {
        TrustProxies::at('*');
        $tunnel = ['REMOTE_ADDR' => '172.18.0.5', 'HTTP_X_FORWARDED_FOR' => '172.18.0.1'];
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->assertSame(302, $this->failedLogin("first{$attempt}@example.test", [...$tunnel, 'HTTP_CF_CONNECTING_IP' => '198.51.100.1']));
        }
        $this->assertSame(429, $this->failedLogin('first6@example.test', [...$tunnel, 'HTTP_CF_CONNECTING_IP' => '198.51.100.1']));
        $this->assertSame(302, $this->failedLogin('second@example.test', [...$tunnel, 'HTTP_CF_CONNECTING_IP' => '198.51.100.2']));
    }

    public function test_forged_cloudflare_address_is_ignored_outside_the_tunnel(): void
    {
        TrustProxies::at('*');
        $direct = ['REMOTE_ADDR' => '172.18.0.5', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9'];
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->assertSame(302, $this->failedLogin("user{$attempt}@example.test", [...$direct, 'HTTP_CF_CONNECTING_IP' => '198.51.100.'.$attempt]));
        }
        $this->assertSame(429, $this->failedLogin('user6@example.test', [...$direct, 'HTTP_CF_CONNECTING_IP' => '198.51.100.99']));
    }

    public function test_forged_cloudflare_address_is_ignored_without_a_trusted_proxy(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->assertSame(302, $this->failedLogin("user{$attempt}@example.test", ['REMOTE_ADDR' => '10.0.0.4', 'HTTP_CF_CONNECTING_IP' => '198.51.100.'.$attempt]));
        }
        $this->assertSame(429, $this->failedLogin('user6@example.test', ['REMOTE_ADDR' => '10.0.0.4', 'HTTP_CF_CONNECTING_IP' => '198.51.100.99']));
    }

    public function test_production_registration_rejects_passwords_found_in_breaches(): void
    {
        Notification::fake();
        $this->app['env'] = 'production';
        Http::fake(['api.pwnedpasswords.com/*' => Http::response(substr(strtoupper(sha1('password123')), 5).':5000')]);
        $details = ['name' => 'User', 'email' => 'user@example.test', 'business_name' => 'Studio', 'timezone' => 'UTC', '_token' => 'csrf'];
        $this->withSession(['_token' => 'csrf'])->post('/register', [...$details, 'password' => 'password123', 'password_confirmation' => 'password123'])->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);
        $this->withSession(['_token' => 'csrf'])->post('/register', [...$details, 'password' => 'a-much-less-common-passphrase', 'password_confirmation' => 'a-much-less-common-passphrase'])->assertSessionHasNoErrors();
        $this->assertSame(1, User::count());
    }
}
