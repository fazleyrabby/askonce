<?php

namespace Tests\Feature;

use App\Actions\Auth\RegisterUser;
use App\Actions\Reminders\RequestAutomation;
use App\Actions\Requests\ChangeRequestState;
use App\Jobs\DeliverRequestMail;
use App\Mail\BusinessUpdateMail;
use App\Mail\ClientRequestMail;
use App\Mail\ReminderMail;
use App\Models\ClientRequest;
use App\Models\RequestItem;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RequestAutomationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Mail::fake();
        $this->travelTo(now()->setDate(2026, 10, 8)->setTime(3, 30));
        $this->owner = app(RegisterUser::class)->handle(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'password123', 'business_name' => 'Studio', 'timezone' => 'Asia/Dhaka']);
        $this->owner->markEmailAsVerified();
        app(CurrentOrganization::class)->set($this->owner->organizations()->firstOrFail());
    }

    private function makeRequest(): ClientRequest
    {
        $request = ClientRequest::factory()->create(['status' => 'sent', 'next_reminder_at' => now()->subMinute()]);
        RequestItem::factory()->create(['client_request_id' => $request->id, 'type' => 'text', 'label' => 'Company name']);
        RequestItem::factory()->create(['client_request_id' => $request->id, 'type' => 'text', 'label' => 'About you']);

        return $request->load('items');
    }

    private function delivery(ClientRequest $request, string $kind = 'reminder'): DeliverRequestMail
    {
        $automation = app(RequestAutomation::class);
        $automation->queue($request, $kind, $kind.':'.$request->id.':'.$request->delivery_generation.':'.$request->reminders_sent.($kind === 'reminder' ? ':'.$request->next_reminder_at->timestamp : ''));
        $id = DB::table('reminders')->where('client_request_id', $request->id)->where('kind', $kind)->latest('id')->value('id');

        return new DeliverRequestMail($request->organization_id, $request->id, $id);
    }

    public function test_scheduler_and_duplicate_jobs_send_once_and_schedule_local_morning(): void
    {
        $request = $this->makeRequest();
        $this->artisan('requests:automate')->assertSuccessful();
        $this->artisan('requests:automate')->assertSuccessful();
        $this->assertDatabaseCount('reminders', 1);
        $job = $this->delivery($request);
        $job->handle(app(RequestAutomation::class));
        $job->handle(app(RequestAutomation::class));
        Mail::assertSent(ReminderMail::class, 1);
        Mail::assertSent(ReminderMail::class, fn ($mail) => $mail->missing === ['Company name', 'About you'] && $mail->completed === 0 && $mail->contactEmail === $this->owner->email);
        $request->refresh();
        $this->assertSame(1, $request->reminders_sent);
        $this->assertSame('2026-10-11 09:00', $request->next_reminder_at->timezone('Asia/Dhaka')->format('Y-m-d H:i'));
    }

    public function test_delayed_job_waits_for_the_next_local_morning(): void
    {
        $request = $this->makeRequest();
        $job = $this->delivery($request);
        $this->travelTo(now()->setTime(10, 0));
        $job->handle(app(RequestAutomation::class));
        Mail::assertNothingSent();
        $this->assertSame('2026-10-09 09:00', $request->fresh()->next_reminder_at->timezone('Asia/Dhaka')->format('Y-m-d H:i'));
    }

    public function test_completed_cancelled_expired_paused_and_unsubscribed_requests_suppress_jobs(): void
    {
        foreach (['completed', 'cancelled', 'expired', 'paused', 'unsubscribed'] as $state) {
            $request = $this->makeRequest();
            $job = $this->delivery($request);
            $request->forceFill(match ($state) {
                'paused' => ['reminders_paused' => true], 'unsubscribed' => ['reminders_stopped_reason' => 'unsubscribed'], default => ['status' => $state]
            })->save();
            $job->handle(app(RequestAutomation::class));
        }
        Mail::assertNothingSent();
        $this->assertSame(5, DB::table('reminders')->where('status', 'suppressed')->count());
    }

    public function test_regenerated_link_suppresses_old_delivery_and_uses_current_link_for_new_delivery(): void
    {
        $request = $this->makeRequest();
        $old = $request->publicUrl();
        $job = $this->delivery($request, 'request');
        app(ChangeRequestState::class)->handle($request, 'regenerate');
        $job->handle(app(RequestAutomation::class));
        Mail::assertNothingSent();
        $request->refresh();
        $this->delivery($request, 'request')->handle(app(RequestAutomation::class));
        Mail::assertSent(ClientRequestMail::class, fn ($mail) => $mail->requestUrl === $request->publicUrl() && $mail->requestUrl !== $old);
        $this->get($old)->assertNotFound();
    }

    public function test_fifth_reminder_stops_and_notifies_once(): void
    {
        $request = $this->makeRequest();
        $request->forceFill(['reminders_sent' => 4])->save();
        $job = $this->delivery($request);
        $job->handle(app(RequestAutomation::class));
        $job->handle(app(RequestAutomation::class));
        $this->assertSame('reminder_limit', $request->fresh()->reminders_stopped_reason);
        $this->assertNull($request->fresh()->next_reminder_at);
        $this->assertSame(1, $this->owner->notifications()->count());
        Mail::assertSent(ReminderMail::class, 1);
    }

    public function test_progress_is_batched_until_submit_or_thirty_minutes_after_last_save(): void
    {
        $request = $this->makeRequest();
        $url = route('public-request.save', ['token' => $request->token, 'item' => $request->items[0]->id]);
        $this->postJson($url, ['value' => 'First'])->assertOk();
        $this->travel(20)->minutes();
        $this->postJson($url, ['value' => 'Second'])->assertOk();
        $this->travel(20)->minutes();
        $this->artisan('requests:automate')->assertSuccessful();
        $this->assertSame(0, $this->owner->notifications()->count());
        $this->travel(11)->minutes();
        $this->artisan('requests:automate')->assertSuccessful();
        $this->assertSame(1, $this->owner->notifications()->count());
        $this->postJson(route('public-request.submit', ['token' => $request->token]))->assertOk();
        $this->assertSame(1, $this->owner->notifications()->count());
        $this->postJson($url, ['value' => 'Third'])->assertOk();
        $this->postJson(route('public-request.submit', ['token' => $request->token]))->assertOk();
        $this->assertSame(2, $this->owner->notifications()->count());
    }

    public function test_completion_sends_one_business_update_and_stops_reminders(): void
    {
        $request = $this->makeRequest();
        foreach ($request->items as $item) {
            $this->postJson(route('public-request.save', ['token' => $request->token, 'item' => $item->id]), ['value' => 'Done'])->assertOk();
        }
        $this->postJson(route('public-request.submit', ['token' => $request->token]))->assertOk();
        $this->assertSame(1, $this->owner->notifications()->count());
        $this->assertNull($request->fresh()->progress_notify_at);
        $delivery = DB::table('reminders')->where('client_request_id', $request->id)->where('kind', 'like', 'business:%')->first();
        $job = new DeliverRequestMail($request->organization_id, $request->id, $delivery->id);
        $job->handle(app(RequestAutomation::class));
        $job->handle(app(RequestAutomation::class));
        Mail::assertSent(BusinessUpdateMail::class, 1);
        Mail::assertSent(BusinessUpdateMail::class, fn ($mail) => $mail->hasTo($this->owner->email) && $mail->requestUrl === route('requests.show', $request) && $mail->updateMessage === 'Your request is complete.');
    }

    public function test_stop_link_requires_post_and_survives_reopening_without_overriding_unsubscribe(): void
    {
        $request = $this->makeRequest();
        $url = route('public-request.stop', ['token' => $request->token]);
        $this->get($url)->assertOk();
        $this->assertNull($request->fresh()->reminders_stopped_reason);
        $this->post($url)->assertRedirect();
        $this->post($url)->assertRedirect();
        $this->assertSame(1, $this->owner->notifications()->count());
        app(ChangeRequestState::class)->handle($request, 'cancel');
        app(ChangeRequestState::class)->handle($request, 'reopen');
        $this->assertSame('unsubscribed', $request->fresh()->reminders_stopped_reason);
    }

    public function test_ambiguous_transport_failure_is_not_retried_and_is_visible(): void
    {
        $request = $this->makeRequest();
        $job = $this->delivery($request);
        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('Transport disconnected'));
        $job->handle(app(RequestAutomation::class));
        $job->handle(app(RequestAutomation::class));
        $this->assertSame('uncertain', DB::table('reminders')->where('id', $job->deliveryId)->value('status'));
        $this->assertSame('delivery_uncertain', $request->fresh()->reminders_stopped_reason);
    }

    public function test_abandoned_claim_is_marked_uncertain_instead_of_resent(): void
    {
        $request = $this->makeRequest();
        $job = $this->delivery($request);
        DB::table('reminders')->where('id', $job->deliveryId)->update(['status' => 'sending', 'claimed_at' => now()->subMinutes(11)]);
        $this->artisan('requests:automate')->assertSuccessful();
        $job->handle(app(RequestAutomation::class));
        Mail::assertNothingSent();
        $this->assertSame('delivery_uncertain', $request->fresh()->reminders_stopped_reason);
    }

    public function test_overdue_is_derived_and_notified_once_and_expiry_stops_sending(): void
    {
        $request = $this->makeRequest();
        $request->forceFill(['due_at' => now()->subDay()->toDateString()])->save();
        $this->artisan('requests:automate')->assertSuccessful();
        $this->artisan('requests:automate')->assertSuccessful();
        $this->assertSame(1, $this->owner->notifications()->count());
        $this->assertSame('sent', $request->fresh()->status);
        $request->forceFill(['due_at' => now()->subDays(31)->toDateString()])->save();
        $this->delivery($request)->handle(app(RequestAutomation::class));
        Mail::assertNothingSent();
        $this->assertSame('expired', $request->fresh()->status);
    }

    public function test_inbox_and_reminder_controls_are_tenant_safe_and_jobs_restore_context(): void
    {
        $request = $this->makeRequest();
        app(RequestAutomation::class)->stop($request, 'hard_bounce');
        $notification = $this->owner->notifications()->firstOrFail();
        $other = app(RegisterUser::class)->handle(['name' => 'Other', 'email' => 'other@example.test', 'password' => 'password123', 'business_name' => 'Other', 'timezone' => 'UTC']);
        $other->markEmailAsVerified();
        $this->actingAs($other)->get(route('notifications.index'))->assertOk()->assertDontSee('Website content');
        $this->post(route('notifications.read', $notification->id))->assertNotFound();
        $this->post(route('requests.reminders', $request), ['action' => 'resume'])->assertNotFound();
        $context = app(CurrentOrganization::class);
        $previous = $context->current();
        $this->delivery($request, 'request')->handle(app(RequestAutomation::class));
        $this->assertSame($previous, $context->current());
        Mail::assertNothingSent();
    }

    public function test_pause_resume_and_manual_nudges_keep_distinct_delivery_claims(): void
    {
        $request = $this->makeRequest();
        $job = $this->delivery($request);
        $this->actingAs($this->owner)->post(route('requests.reminders', $request), ['action' => 'pause'])->assertRedirect();
        $job->handle(app(RequestAutomation::class));
        Mail::assertNothingSent();
        $this->post(route('requests.reminders', $request), ['action' => 'nudge'])->assertRedirect();
        $this->post(route('requests.reminders', $request), ['action' => 'nudge'])->assertRedirect();
        $this->assertSame(1, DB::table('reminders')->where('kind', 'nudge')->count());
        $this->post(route('requests.reminders', $request), ['action' => 'resume'])->assertRedirect();
        $request->refresh();
        $this->travelTo($request->next_reminder_at->copy()->addMinutes(15));
        $this->artisan('requests:automate')->assertSuccessful();
        $id = DB::table('reminders')->where('kind', 'reminder')->where('status', 'queued')->value('id');
        (new DeliverRequestMail($request->organization_id, $request->id, $id))->handle(app(RequestAutomation::class));
        Mail::assertSent(ReminderMail::class, 1);
    }
}
