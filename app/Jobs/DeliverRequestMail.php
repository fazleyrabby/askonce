<?php

namespace App\Jobs;

use App\Actions\Reminders\RequestAutomation;
use App\Mail\BusinessUpdateMail;
use App\Mail\ClientRequestMail;
use App\Mail\ReminderMail;
use App\Models\ClientRequest;
use App\Models\Organization;
use App\Support\CurrentOrganization;
use App\Support\RecordActivity;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class DeliverRequestMail implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public int $organizationId, public int $requestId, public int $deliveryId) {}

    public function handle(RequestAutomation $automation): void
    {
        $organization = Organization::find($this->organizationId);
        if (! $organization) {
            return;
        }
        $context = app(CurrentOrganization::class);
        $previous = $context->current();
        $context->set($organization);
        try {
            $claimed = DB::transaction(function () use ($automation): bool {
                $request = ClientRequest::lockForUpdate()->find($this->requestId);
                $delivery = DB::table('reminders')->where('id', $this->deliveryId)->lockForUpdate()->first();
                if (! $request || ! $delivery || $delivery->status !== 'queued') {
                    return false;
                }
                $business = str_starts_with($delivery->kind, 'business:');
                if (! $business) {
                    $automation->process($request, false);
                }
                if (! $business && ($delivery->generation !== $request->delivery_generation || ! $request->isEditable() || $request->reminders_stopped_reason || ($delivery->kind === 'reminder' && ($request->reminders_paused || ! $request->next_reminder_at || $request->reminders_sent >= 5)))) {
                    DB::table('reminders')->where('id', $this->deliveryId)->update(['status' => 'suppressed', 'updated_at' => now()]);

                    return false;
                }
                if ($delivery->kind === 'reminder') {
                    if ($request->next_reminder_at->gt(now())) {
                        return false;
                    }
                    $local = now(app(CurrentOrganization::class)->get()->timezone);
                    if ($local->hour < 9 || $local->hour >= 12) {
                        $request->next_reminder_at = $local->copy()->startOfDay()->hour(9)->addDays($local->hour >= 12 ? 1 : 0)->utc();
                        $request->save();

                        return false;
                    }
                }
                DB::table('reminders')->where('id', $this->deliveryId)->update(['status' => 'sending', 'claimed_at' => now(), 'updated_at' => now()]);

                return true;
            });
            if (! $claimed) {
                return;
            }
            try {
                DB::transaction(function () use ($organization, $automation): void {
                    $request = ClientRequest::lockForUpdate()->find($this->requestId);
                    $delivery = DB::table('reminders')->where('id', $this->deliveryId)->lockForUpdate()->first();
                    if (! $request || ! $delivery || $delivery->status !== 'sending') {
                        return;
                    }
                    $business = str_starts_with($delivery->kind, 'business:');
                    if (! $business && (! $request->isEditable() || $request->delivery_generation !== $delivery->generation || $request->reminders_stopped_reason || ($delivery->kind === 'reminder' && $request->reminders_paused))) {
                        DB::table('reminders')->where('id', $this->deliveryId)->update(['status' => 'suppressed', 'updated_at' => now()]);

                        return;
                    }
                    $owner = $organization->users()->wherePivot('role', 'owner')->firstOrFail();
                    if ($business) {
                        if (! $organization->is_demo) {
                            Mail::to($owner->email)->send(new BusinessUpdateMail($request->title, substr($delivery->kind, 9), route('requests.show', $request)));
                        }
                    } else {
                        $mail = $delivery->kind === 'request' ? new ClientRequestMail($request->title, $request->publicUrl(), $organization->name, $owner->email, $request->client->contact_name) : new ReminderMail($request->title, $request->publicUrl(), $organization->name, $owner->email, $request->client->contact_name, $request->items()->where('required', true)->where('status', 'pending')->pluck('label')->all(), $request->items()->where('status', 'submitted')->count(), $request->items()->count(), route('public-request.stop', ['token' => $request->token]));
                        /** Demo workspaces follow the full delivery flow but never send real email. */
                        if (! $organization->is_demo) {
                            Mail::to($request->client->email)->send($mail);
                        }
                        app(RecordActivity::class)->record($delivery->kind === 'request' ? 'request_sent' : 'reminder_sent', $request->organization_id, $request->id, 'delivery:'.$delivery->id);
                        if ($delivery->kind === 'reminder') {
                            $request->reminders_sent++;
                            $request->next_reminder_at = $automation->nextMorning($request);
                            $request->save();
                            if ($request->reminders_sent >= 5) {
                                $automation->stop($request, 'reminder_limit');
                            }
                        }
                    }
                    DB::table('reminders')->where('id', $this->deliveryId)->update(['status' => 'sent', 'sent_at' => now(), 'updated_at' => now()]);
                });
            } catch (Throwable $exception) {
                DB::transaction(function () use ($automation): void {
                    DB::table('reminders')->where('id', $this->deliveryId)->where('status', 'sending')->update(['status' => 'uncertain', 'updated_at' => now()]);
                    $request = ClientRequest::lockForUpdate()->find($this->requestId);
                    $kind = DB::table('reminders')->where('id', $this->deliveryId)->value('kind');
                    if ($request && ! str_starts_with($kind, 'business:')) {
                        $automation->stop($request, 'delivery_uncertain');
                    }
                });
                /** SMTP may have accepted the message; retrying risks duplicate delivery. */
            }
        } finally {
            $context->restore($previous);
        }
    }
}
