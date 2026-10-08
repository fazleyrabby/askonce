<?php

namespace Tests\Feature;

use App\Actions\Auth\RegisterUser;
use App\Models\ClientRequest;
use App\Providers\AppServiceProvider;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ProductPolishTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_and_free_beta_signup_are_available(): void
    {
        $this->get('/')->assertOk()->assertSee('Start your first request')->assertSee('Free while in beta.');
        $this->get('/privacy')->assertOk();
        $this->get('/terms')->assertOk();
    }

    public function test_public_pages_carry_sharing_details_and_private_pages_are_not_indexed(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('<meta name="description" content="Collect files, answers and links', false)
            ->assertSee('<meta property="og:image" content="'.asset('og-image.png').'">', false)
            ->assertSee('<link rel="canonical" href="'.route('home').'">', false)
            ->assertDontSee('noindex');
        $this->get('/demo')->assertOk()->assertSee('Try AskOnce in a private demo workspace', false)->assertDontSee('noindex');
        $this->get('/login')->assertOk()->assertSee('<meta name="robots" content="noindex">', false)->assertDontSee('rel="canonical"', false);
        $this->get('/register')->assertOk()->assertSee('<meta name="robots" content="noindex">', false);
        $this->assertFileExists(public_path('og-image.png'));
    }

    public function test_robots_and_sitemap_list_only_public_pages(): void
    {
        $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: '.route('sitemap'));
        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml')
            ->assertSee('<loc>'.route('home').'</loc>', false)->assertSee('<loc>'.route('demo').'</loc>', false)
            ->assertDontSee('login')->assertDontSee('register');
    }

    public function test_guests_can_view_the_sample_demo_without_an_account(): void
    {
        $this->get('/')->assertOk()->assertSee(route('demo'))->assertSee('data-visits', false);
        $this->get('/demo')->assertOk()->assertSee('sample data')->assertSee('ABC Restaurant');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_visit_counter_is_only_rendered_on_landing_and_demo_pages(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('data-visits', false);
        $this->get('/privacy')->assertOk()->assertDontSee('data-visits', false);
    }

    public function test_settings_change_only_current_business_and_reschedule_reminders(): void
    {
        $user = app(RegisterUser::class)->handle(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'password123', 'business_name' => 'Studio', 'timezone' => 'UTC']);
        $user->markEmailAsVerified();
        $organization = $user->organizations()->firstOrFail();
        app(CurrentOrganization::class)->set($organization);
        $request = ClientRequest::factory()->create(['status' => 'sent', 'next_reminder_at' => now()]);
        $this->actingAs($user)->get(route('settings.edit'))->assertOk()->assertSee('100 MB');
        $this->put(route('settings.update'), ['name' => 'New Studio', 'timezone' => 'Asia/Dhaka'])->assertRedirect();
        $this->assertSame('New Studio', $organization->fresh()->name);
        $this->assertSame(9, $request->fresh()->next_reminder_at->timezone('Asia/Dhaka')->hour);
        $this->put(route('settings.update'), ['name' => 'Invalid', 'timezone' => 'Invalid/Timezone'])->assertSessionHasErrors('timezone');
        $this->assertDatabaseHas('activity_events', ['organization_id' => $organization->id, 'name' => 'user_registered']);
    }

    public function test_https_deployments_generate_secure_form_links(): void
    {
        config(['app.url' => 'https://askonce.fazleyrabbi.xyz']);
        (new AppServiceProvider($this->app))->boot();
        $this->get('/register')->assertOk()->assertSee('action="https://', false);
        URL::forceScheme(null);
    }
}
