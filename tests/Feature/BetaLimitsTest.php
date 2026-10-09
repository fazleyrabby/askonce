<?php

namespace Tests\Feature;

use App\Actions\Auth\RegisterUser;
use App\Models\Client;
use App\Models\ClientRequest;
use App\Models\RequestItem;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BetaLimitsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Mail::fake();
        $this->owner = app(RegisterUser::class)->handle(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'password123', 'business_name' => 'Studio', 'timezone' => 'UTC']);
        $this->owner->markEmailAsVerified();
        app(CurrentOrganization::class)->set($this->owner->organizations()->firstOrFail());
        $this->actingAs($this->owner);
    }

    private function sentRequest(): ClientRequest
    {
        return ClientRequest::factory()->create(['status' => 'sent', 'sent_at' => now()]);
    }

    private function clientEmailCount(): int
    {
        return DB::table('reminders')->where('kind', 'not like', 'business:%')->count();
    }

    public function test_clients_cannot_be_added_beyond_the_beta_allowance(): void
    {
        config(['app.limits.clients' => 2]);
        Client::factory()->count(2)->create();
        $this->post('/clients', ['name' => 'Third', 'email' => 'third@example.test'])->assertSessionHasErrors('limit');
        $this->assertSame(2, Client::count());
    }

    public function test_open_requests_are_capped_and_closed_requests_free_a_place(): void
    {
        config(['app.limits.open_requests' => 1]);
        $open = $this->sentRequest();
        $payload = ['client_id' => $open->client_id, 'title' => 'Second', 'reminder_interval_days' => 3, 'items' => [['label' => 'Logo', 'type' => 'text', 'required' => true]]];
        $this->get('/requests/create')->assertRedirect(route('dashboard'))->assertSessionHasErrors('limit');
        $this->post('/requests', $payload)->assertSessionHasErrors('limit');
        $this->assertSame(1, ClientRequest::count());

        $this->post(route('requests.state', $open), ['action' => 'cancel'])->assertSessionHasNoErrors();
        $this->post('/requests', $payload)->assertSessionHasNoErrors();
        $this->assertSame(2, ClientRequest::count());

        $this->post(route('requests.state', $open), ['action' => 'reopen'])->assertSessionHasErrors('limit');
        $this->assertSame('cancelled', $open->fresh()->status);
    }

    public function test_a_request_can_be_emailed_only_once_an_hour(): void
    {
        $this->travelTo(now()->startOfDay()->hour(12)->minute(58));
        $request = $this->sentRequest();
        $this->post(route('requests.email', $request))->assertSessionHasNoErrors();
        $this->travel(5)->minutes();
        $this->post(route('requests.email', $request))->assertSessionHasErrors('email');
        $this->assertSame(1, $this->clientEmailCount());
        $this->travel(1)->hours();
        $this->post(route('requests.email', $request))->assertSessionHasNoErrors();
        $this->assertSame(2, $this->clientEmailCount());
    }

    public function test_client_emails_stop_at_the_daily_allowance_and_the_business_is_told_once(): void
    {
        config(['app.limits.client_emails_per_day' => 1]);
        $first = $this->sentRequest();
        $second = $this->sentRequest();
        $this->post(route('requests.email', $first))->assertSessionHasNoErrors();
        $this->post(route('requests.email', $second))->assertSessionHasErrors('email');
        $this->post(route('requests.reminders', $second), ['action' => 'nudge'])->assertSessionHasErrors('email');
        $this->assertSame(1, $this->clientEmailCount());
        $this->assertSame(1, $this->owner->notifications()->where('data->message', 'like', 'Daily email limit reached%')->count());

        $this->travel(25)->hours();
        $this->post(route('requests.email', $second))->assertSessionHasNoErrors();
        $this->assertSame(2, $this->clientEmailCount());
    }

    public function test_business_is_warned_when_storage_is_nearly_full(): void
    {
        Storage::fake('local');
        $request = $this->sentRequest();
        $item = RequestItem::factory()->create(['client_request_id' => $request->id, 'type' => 'file', 'label' => 'Logo', 'position' => 0]);
        RequestItem::factory()->create(['client_request_id' => $request->id, 'type' => 'text', 'label' => 'Still needed', 'position' => 1]);
        $file = UploadedFile::fake()->createWithContent('logo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
        $organization = app(CurrentOrganization::class)->get();
        $organization->forceFill(['storage_quota_bytes' => $file->getSize() * 10])->save();
        $url = route('public-request.save', ['token' => $request->token, 'item' => $item->id]);
        $this->postJson($url, ['files' => [$file]])->assertOk();
        $this->assertSame(0, $this->owner->notifications()->where('data->message', 'like', 'Your storage is over 80% full%')->count());

        $organization->forceFill(['storage_quota_bytes' => (int) ($file->getSize() * 2.2)])->save();
        $this->postJson($url, ['files' => [$file]])->assertOk();
        $this->postJson($url, ['files' => [$file], 'replace' => true])->assertOk();
        $this->assertSame(1, $this->owner->notifications()->where('data->message', 'like', 'Your storage is over 80% full%')->count());
    }

    public function test_settings_show_usage_against_each_allowance(): void
    {
        config(['app.limits.clients' => 50, 'app.limits.open_requests' => 25]);
        $this->sentRequest();
        $this->get('/settings')->assertOk()->assertSee('1 of 50')->assertSee('1 of 25')->assertSee('0 of 100');
    }
}
