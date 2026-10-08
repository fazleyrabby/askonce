<?php

namespace Tests\Feature;

use App\Actions\Auth\RegisterUser;
use App\Mail\ClientRequestMail;
use App\Models\Client;
use App\Models\ClientRequest;
use App\Models\RequestItem;
use App\Models\Upload;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function owner(string $email = 'owner@example.test'): User
    {
        $user = app(RegisterUser::class)->handle(['name' => 'Owner', 'email' => $email, 'password' => 'password123', 'business_name' => 'Studio', 'timezone' => 'Asia/Dhaka']);
        $user->markEmailAsVerified();
        app(CurrentOrganization::class)->set($user->organizations()->firstOrFail());

        return $user;
    }

    private function requestWithItems(array $items = ['text', 'url']): ClientRequest
    {
        $request = ClientRequest::factory()->create(['status' => 'sent', 'sent_at' => now()]);
        foreach ($items as $position => $type) {
            RequestItem::factory()->create(['client_request_id' => $request->id, 'type' => $type, 'label' => 'Item '.$position, 'position' => $position]);
        }

        return $request->load('items');
    }

    private function pngUpload(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
    }

    private function saveUrl(ClientRequest $request, int $position = 0): string
    {
        return route('public-request.save', ['token' => $request->token, 'item' => $request->items[$position]->id]);
    }

    public function test_business_can_create_activate_and_email_a_request(): void
    {
        Mail::fake();
        $user = $this->owner();
        $client = Client::factory()->create();
        $this->actingAs($user)->post('/requests', [
            'client_id' => $client->id, 'title' => 'Website content', 'reminder_interval_days' => 3,
            'items' => [['label' => 'Logo', 'type' => 'file', 'required' => true]],
        ])->assertRedirect();
        $request = ClientRequest::firstOrFail();
        $this->assertSame('draft', $request->status);
        $this->assertSame(43, strlen($request->token));
        $this->assertSame(hash('sha256', $request->token), $request->token_hash);
        $this->assertNotSame($request->token, $request->getRawOriginal('token'));
        $this->get($request->publicUrl())->assertNotFound();
        $this->post(route('requests.share', $request))->assertRedirect();
        $this->assertSame('sent', $request->fresh()->status);
        $this->post(route('requests.email', $request))->assertRedirect();
        Mail::assertSent(ClientRequestMail::class, fn ($mail) => $mail->hasTo($client->email) && $mail->contactEmail === $user->email);
    }

    public function test_public_autosave_resumes_and_completes_required_items(): void
    {
        $this->owner();
        $request = $this->requestWithItems();
        $this->get($request->publicUrl())->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee('Don’t paste passwords here.');
        $this->postJson($this->saveUrl($request), ['value' => 'Hello client'])->assertOk()->assertJsonPath('completed', false);
        $this->assertSame('in_progress', $request->fresh()->status);
        $this->get($request->publicUrl())->assertSee('Hello client');
        $this->postJson(route('public-request.submit', ['token' => $request->token]))->assertOk()->assertJsonPath('missing', 1);
        $this->postJson($this->saveUrl($request, 1), ['value' => 'https://example.com'])->assertOk()->assertJsonPath('completed', true);
        $this->assertSame('completed', $request->fresh()->status);
        $this->assertNull($request->fresh()->next_reminder_at);
        $this->get($request->publicUrl())->assertSee('Thank you. Everything’s in.')->assertDontSee('data-item-form', false);
        $this->postJson($this->saveUrl($request), ['value' => 'Too late'])->assertStatus(409);
    }

    public function test_answers_can_be_replaced_or_cleared_while_open(): void
    {
        $this->owner();
        $request = $this->requestWithItems(['text', 'confirmation']);
        $this->postJson($this->saveUrl($request), ['value' => 'First'])->assertOk();
        $this->postJson($this->saveUrl($request), ['value' => 'Second'])->assertOk();
        $this->assertDatabaseHas('submissions', ['request_item_id' => $request->items[0]->id, 'value' => 'Second']);
        $this->postJson($this->saveUrl($request), ['value' => ''])->assertOk();
        $this->assertDatabaseHas('request_items', ['id' => $request->items[0]->id, 'status' => 'pending']);
        $this->postJson($this->saveUrl($request, 1), ['value' => false])->assertOk()->assertJsonPath('completed', false);
        $this->postJson($this->saveUrl($request, 1), ['value' => true])->assertOk()->assertJsonPath('completed', false);
    }

    public function test_url_and_file_validation_reject_unsafe_inputs(): void
    {
        Storage::fake('local');
        $this->owner();
        $request = $this->requestWithItems(['url', 'file']);
        $this->postJson($this->saveUrl($request), ['value' => 'javascript:alert(1)'])->assertUnprocessable();
        $this->postJson($this->saveUrl($request, 1), ['files' => [UploadedFile::fake()->create('payload.php', 1, 'text/plain')]])->assertUnprocessable();
        $this->postJson($this->saveUrl($request, 1), ['files' => [UploadedFile::fake()->create('huge.pdf', 51201, 'application/pdf')]])->assertUnprocessable();
        $this->assertDatabaseCount('uploads', 0);
    }

    public function test_private_uploads_replace_and_delete_with_the_request(): void
    {
        Storage::fake('local');
        $user = $this->owner();
        $request = $this->requestWithItems(['file', 'text']);
        $this->postJson($this->saveUrl($request), ['files' => [$this->pngUpload('logo.png')]])->assertOk();
        $upload = Upload::firstOrFail();
        Storage::disk('local')->assertExists($upload->path);
        $this->assertStringNotContainsString('logo.png', $upload->path);
        $this->get(route('uploads.download', $upload))->assertRedirect(route('login'));
        $this->actingAs($user)->get(route('uploads.download', $upload))->assertOk()->assertDownload('logo.png');
        $this->postJson($this->saveUrl($request), ['replace' => true, 'files' => [$this->pngUpload('new-logo.png')]])->assertOk();
        Storage::disk('local')->assertMissing($upload->path);
        $this->assertDatabaseCount('uploads', 1);
        $new = Upload::firstOrFail();
        $this->delete(route('requests.destroy', $request))->assertRedirect(route('dashboard'));
        Storage::disk('local')->assertMissing($new->path);
        $this->assertDatabaseCount('uploads', 0);
        $this->assertDatabaseCount('submissions', 0);
    }

    public function test_storage_quota_rejects_upload_without_partial_records(): void
    {
        Storage::fake('local');
        $this->owner();
        app(CurrentOrganization::class)->get()->forceFill(['storage_quota_bytes' => 1])->save();
        $request = $this->requestWithItems(['file']);
        $this->postJson($this->saveUrl($request), ['files' => [$this->pngUpload('logo.png')]])->assertUnprocessable()->assertJsonValidationErrors('files');
        $this->assertDatabaseCount('uploads', 0);
        $this->assertDatabaseCount('submissions', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_other_organizations_cannot_access_requests_files_or_assign_clients(): void
    {
        Storage::fake('local');
        $this->owner();
        $request = $this->requestWithItems(['file', 'text']);
        $this->postJson($this->saveUrl($request), ['files' => [$this->pngUpload('logo.png')]])->assertOk();
        $upload = Upload::firstOrFail();
        $other = $this->owner('other@example.test');
        $this->actingAs($other)->get(route('requests.show', $request))->assertNotFound();
        $this->get(route('uploads.download', $upload))->assertNotFound();
        $this->post('/requests', ['client_id' => $request->client_id, 'title' => 'Stolen', 'reminder_interval_days' => 3, 'items' => [['label' => 'Name', 'type' => 'text', 'required' => true]]])->assertSessionHasErrors('client_id');
        $otherRequest = $this->requestWithItems(['text']);
        $this->postJson(route('public-request.save', ['token' => $request->token, 'item' => $otherRequest->items[0]->id]), ['value' => 'Stolen'])->assertNotFound();
    }

    public function test_link_regeneration_cancellation_and_reopening(): void
    {
        $user = $this->owner();
        $request = $this->requestWithItems(['text']);
        $oldUrl = $request->publicUrl();
        $this->actingAs($user)->post(route('requests.state', $request), ['action' => 'regenerate'])->assertRedirect();
        $this->get($oldUrl)->assertNotFound();
        $request->refresh();
        $this->get($request->publicUrl())->assertOk();
        $this->post(route('requests.state', $request), ['action' => 'cancel'])->assertRedirect();
        $this->get($request->publicUrl())->assertSee('This request was closed.');
        $this->postJson($this->saveUrl($request), ['value' => 'No'])->assertStatus(409);
        $this->post(route('requests.state', $request), ['action' => 'reopen'])->assertRedirect();
        $this->postJson($this->saveUrl($request), ['value' => 'Yes'])->assertOk()->assertJsonPath('completed', true);
    }

    public function test_old_incomplete_request_expires_when_opened(): void
    {
        $this->owner();
        $request = $this->requestWithItems();
        $request->forceFill(['due_at' => now()->subDays(31)->toDateString()])->save();
        $this->get($request->publicUrl())->assertOk()->assertSee('This request was closed.');
        $this->assertSame('expired', $request->fresh()->status);
    }
}
