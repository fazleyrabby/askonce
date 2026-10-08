<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DemoAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_is_idempotent_and_creates_both_workspaces(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('organizations', 2);
        $this->assertDatabaseCount('clients', 6);
        $this->assertDatabaseCount('client_requests', 6);
        $this->assertDatabaseCount('request_items', 30);
        $this->assertDatabaseHas('users', ['email' => 'admin@askonce.test', 'is_admin' => true]);
        $this->assertDatabaseHas('users', ['email' => 'demo@askonce.test', 'is_admin' => false]);
    }

    public function test_local_shortcuts_sign_in_to_the_correct_account(): void
    {
        $this->seed(DemoSeeder::class);
        $this->app['env'] = 'local';
        $this->get('/login')->assertSee('Demo admin')->assertSee('Demo user');
        $this->withSession(['_token' => 'demo-csrf'])->post('/demo-login', ['account' => 'admin', '_token' => 'demo-csrf'])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs(User::where('email', 'admin@askonce.test')->firstOrFail());
        $this->get('/admin')->assertOk()->assertSee('Admin overview');
        $this->withSession(['_token' => 'demo-csrf'])->post('/logout', ['_token' => 'demo-csrf']);
        $this->withSession(['_token' => 'demo-csrf'])->post('/demo-login', ['account' => 'user', '_token' => 'demo-csrf'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs(User::where('email', 'demo@askonce.test')->firstOrFail());
        $this->get('/dashboard')->assertOk()->assertSee('Website content');
        $this->get('/admin')->assertForbidden();
    }

    public function test_demo_shortcut_is_unavailable_in_production(): void
    {
        $this->seed(DemoSeeder::class);
        $this->app['env'] = 'production';
        $this->get('/login')->assertDontSee('Demo admin')->assertDontSee('Demo user');
        $this->withSession(['_token' => 'demo-csrf'])->post('/demo-login', ['account' => 'admin', '_token' => 'demo-csrf'])->assertNotFound();
        $this->assertGuest();
    }

    public function test_regular_registration_cannot_grant_admin_access(): void
    {
        Notification::fake();
        $this->post('/register', ['name' => 'User', 'email' => 'user@example.test', 'business_name' => 'Studio', 'timezone' => 'UTC', 'password' => 'password123', 'password_confirmation' => 'password123', 'is_admin' => true])->assertRedirect();
        $this->assertFalse(User::firstOrFail()->is_admin);
    }
}
