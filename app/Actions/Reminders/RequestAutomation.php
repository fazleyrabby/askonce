<?php

namespace App\Actions\Reminders;

use App\Jobs\DeliverRequestMail;
use App\Models\ClientRequest;
use App\Notifications\BusinessUpdate;
use App\Support\CurrentOrganization;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RequestAutomation
{
    public function nextMorning(ClientRequest $request): Carbon
    {
        return now(app(CurrentOrganization::class)->get()->timezone)->addDays($request->reminder_interval_days)->startOfDay()->hour(9)->utc();
    }

    /**
     * Emails sent to clients on a business's behalf in the last 24 hours.
     */
    public function clientEmailsToday(int $organizationId): int
    {
        return DB::table('reminders')->where('organization_id', $organizationId)->where('kind', 'not like', 'business:%')->whereNotIn('status', ['suppressed'])->where('created_at', '>=', now()->subDay())->count();
    }

    /**
     * Queue a delivery once per key. Returns false when the business has used its daily client email allowance.
     */
    public function queue(ClientRequest $request, string $kind, string $key): bool
    {
        $id = DB::table('reminders')->where('delivery_key', $key)->value('id');
        if (! $id && ! str_starts_with($kind, 'business:') && $this->clientEmailsToday($request->organization_id) >= config('app.limits.client_emails_per_day')) {
            $this->notify($request, 'email_limit', 'Daily email limit reached. Client emails resume tomorrow.', 'email-limit:'.$request->organization_id.':'.now()->toDateString());

            return false;
        }
        if (! $id) {
            $id = DB::table('reminders')->insertGetId(['organization_id' => $request->organization_id, 'client_request_id' => $request->id, 'delivery_key' => $key, 'kind' => $kind, 'status' => 'queued', 'generation' => $request->delivery_generation, 'created_at' => now(), 'updated_at' => now()]);
        }
        if (DB::table('reminders')->where('id', $id)->value('status') === 'queued') {
            DeliverRequestMail::dispatch($request->organization_id, $request->id, $id)->afterCommit();
        }

        return true;
    }

    public function notify(ClientRequest $request, string $kind, string $message, string $key): void
    {
        $owner = app(CurrentOrganization::class)->get()->users()->wherePivot('role', 'owner')->first();
        if (! $owner) {
            return;
        }
        $exists = $owner->notifications()->where('data->event_key', $key)->exists();
        if ($exists) {
            return;
        }
        $owner->notify(new BusinessUpdate($request->organization_id, $request->id, $request->title, $message, $key));
        $this->queue($request, 'business:'.$message, $key);
    }

    public function stop(ClientRequest $request, string $reason): void
    {
        if ($request->reminders_stopped_reason === $reason) {
            return;
        }
        $request->reminders_stopped_reason = $reason;
        $request->next_reminder_at = null;
        $request->save();
        $this->notify($request, 'stopped', 'Reminders stopped: '.str_replace('_', ' ', $reason).'.', 'stop:'.$request->id.':'.$request->delivery_generation.':'.$reason);
    }

    public function process(ClientRequest $request, bool $queueReminders = true): void
    {
        if ($request->isEditable() && $request->due_at) {
            $end = Carbon::parse($request->due_at->format('Y-m-d'), app(CurrentOrganization::class)->get()->timezone)->endOfDay();
            if (now()->greaterThan($end->copy()->addDays(30))) {
                $request->status = 'expired';
                $request->next_reminder_at = null;
                $request->save();
            } elseif (now()->greaterThan($end) && ! $request->overdue_notified_at) {
                $this->notify($request, 'overdue', 'This request is overdue.', 'overdue:'.$request->id.':'.$request->delivery_generation);
                $request->overdue_notified_at = now();
                $request->save();
            }
        }
        if ($request->progress_notify_at && $request->progress_notify_at->lte(now())) {
            $this->progress($request);
        }
        if (! $request->isEditable() || $request->reminders_paused || $request->reminders_stopped_reason) {
            return;
        }
        if ($request->reminders_sent >= 5) {
            $this->stop($request, 'reminder_limit');

            return;
        }
        if ($queueReminders && $request->next_reminder_at && $request->next_reminder_at->lte(now())) {
            $local = now(app(CurrentOrganization::class)->get()->timezone);
            if ($local->hour < 9 || $local->hour >= 12) {
                $request->next_reminder_at = $local->copy()->startOfDay()->hour(9)->addDays($local->hour >= 12 ? 1 : 0)->utc();
                $request->save();

                return;
            }
            $this->queue($request, 'reminder', 'reminder:'.$request->id.':'.$request->delivery_generation.':'.$request->reminders_sent.':'.$request->next_reminder_at->timestamp);
        }
    }

    public function progress(ClientRequest $request): void
    {
        if ($request->progress_revision <= $request->notified_revision) {
            return;
        }
        $this->notify($request, 'progress', 'Your client saved progress.', 'progress:'.$request->id.':'.$request->delivery_generation.':'.$request->progress_revision);
        $request->notified_revision = $request->progress_revision;
        $request->progress_notify_at = null;
        $request->save();
    }
}
