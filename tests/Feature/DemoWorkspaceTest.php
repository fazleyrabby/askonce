<?php

namespace Tests\Feature;

use App\Actions\Demo\StartDemoWorkspace;
use App\Actions\Reminders\RequestAutomation;
use App\Jobs\DeliverRequestMail;
use App\Models\ClientRequest;
use App\Models\Organization;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function startDemo(): User
    {
        $this->withSession(['_token' => 'demo-csrf'])->post('/demo/start', ['_token' => 'demo-csrf'])->assertRedirect(route('dashboard'));

        return User::latest('id')->firstOrFail();
    }

    public function test_visitor_gets_a_private_seeded_workspace_without_signing_up(): void
    {
        Mail::fake();
        $this->get('/demo')->assertOk()->assertSee('Open the live demo');
        $user = $this->startDemo();
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->is_admin);
        $this->assertTrue($user->hasVerifiedEmail());
        $organization = $user->organizations()->firstOrFail();
        $this->assertTrue($organization->is_demo);
        $this->assertSame(StartDemoWorkspace::STORAGE_QUOTA_BYTES, (int) $organization->storage_quota_bytes);
        $this->get('/dashboard')->assertOk()->assertSee('demo workspace')->assertSee('Website content')->assertSee('3 / 5 submitted')->assertSee('Tax season documents');
        $this->get('/notifications')->assertOk()->assertSee('Your client saved progress.');
        $this->get('/admin')->assertForbidden();
        Mail::assertNothingSent();
    }

    public function test_each_visitor_gets_a_separate_workspace(): void
    {
        $first = $this->startDemo();
        $this->withSession(['_token' => 'demo-csrf'])->post('/logout', ['_token' => 'demo-csrf']);
        $second = $this->startDemo();
        $this->assertNotSame($first->id, $second->id);
        $this->assertNotSame($first->organizations()->firstOrFail()->id, $second->organizations()->firstOrFail()->id);
        $this->assertSame(2, Organization::where('is_demo', true)->count());
    }

    public function test_demo_workspace_never_sends_real_email_but_completes_the_delivery_flow(): void
    {
        Mail::fake();
        $user = $this->startDemo();
        $organization = $user->organizations()->firstOrFail();
        app(CurrentOrganization::class)->set($organization);
        $request = ClientRequest::where('status', 'sent')->firstOrFail();
        app(RequestAutomation::class)->queue($request, 'request', 'request:test:'.$request->id);
        $deliveryId = DB::table('reminders')->where('delivery_key', 'request:test:'.$request->id)->value('id');
        (new DeliverRequestMail($organization->id, $request->id, $deliveryId))->handle(app(RequestAutomation::class));
        Mail::assertNothingSent();
        $this->assertDatabaseHas('reminders', ['id' => $deliveryId, 'status' => 'sent']);
    }

    public function test_demo_start_is_refused_when_too_many_workspaces_exist(): void
    {
        Organization::factory()->count(StartDemoWorkspace::MAX_ACTIVE)->create()->each(fn (Organization $organization) => $organization->forceFill(['is_demo' => true])->save());
        $this->from('/demo')->withSession(['_token' => 'demo-csrf'])->post('/demo/start', ['_token' => 'demo-csrf'])->assertRedirect('/demo')->assertSessionHasErrors('demo');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_expired_demo_workspaces_are_purged_with_their_files_and_real_accounts_are_kept(): void
    {
        Storage::fake('local');
        $user = $this->startDemo();
        $organization = $user->organizations()->firstOrFail();
        app(CurrentOrganization::class)->set($organization);
        $item = ClientRequest::where('status', 'sent')->firstOrFail()->items()->where('type', 'file')->firstOrFail();
        Storage::disk('local')->put('organizations/'.$organization->id.'/demo.txt', 'demo');
        $item->submission()->create([])->uploads()->create(['disk' => 'local', 'path' => 'organizations/'.$organization->id.'/demo.txt', 'original_name' => 'demo.txt', 'size' => 4, 'mime_type' => 'text/plain']);
        $real = User::factory()->create();
        $realOrganization = Organization::factory()->create();
        $realOrganization->users()->attach($real, ['role' => 'owner']);

        $this->artisan('askonce:purge-demos')->assertSuccessful();
        $this->assertDatabaseHas('organizations', ['id' => $organization->id]);

        $this->travel(StartDemoWorkspace::LIFETIME_HOURS + 1)->hours();
        $this->artisan('askonce:purge-demos')->assertSuccessful();
        $this->assertDatabaseMissing('organizations', ['id' => $organization->id]);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('client_requests', ['organization_id' => $organization->id]);
        $this->assertDatabaseMissing('clients', ['organization_id' => $organization->id]);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $user->id]);
        Storage::disk('local')->assertMissing('organizations/'.$organization->id.'/demo.txt');
        $this->assertDatabaseHas('organizations', ['id' => $realOrganization->id]);
        $this->assertDatabaseHas('users', ['id' => $real->id]);
    }
}
