<?php

namespace App\Actions\Requests;

use App\Actions\Reminders\RequestAutomation;
use App\Models\ClientRequest;
use Illuminate\Support\Facades\DB;

class ChangeRequestState
{
    public function handle(ClientRequest $clientRequest, string $action): void
    {
        DB::transaction(function () use ($clientRequest, $action): void {
            $request = ClientRequest::lockForUpdate()->findOrFail($clientRequest->id);
            if ($action === 'activate') {
                if ($request->status === 'draft') {
                    $request->status = 'sent';
                    $request->sent_at = now();
                }
                abort_unless($request->isEditable(), 409, 'This request is closed.');
            } elseif ($action === 'regenerate') {
                $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
                $request->token = $token;
                $request->token_hash = hash('sha256', $token);
            } elseif ($action === 'reopen') {
                abort_unless(in_array($request->status, ['completed', 'cancelled', 'expired'], true), 409);
                $request->status = 'sent';
                $request->completed_at = null;
                $request->due_at = null;
            } else {
                $request->status = $action === 'complete' ? 'completed' : 'cancelled';
                $request->completed_at = $request->status === 'completed' ? now() : null;
            }
            if (in_array($action, ['regenerate', 'reopen'], true)) {
                $request->delivery_generation++;
            }
            if ($action === 'reopen') {
                $request->overdue_notified_at = null;
                if (in_array($request->reminders_stopped_reason, ['reminder_limit', 'delivery_uncertain'], true)) {
                    $request->reminders_stopped_reason = null;
                }
                $request->reminders_sent = 0;
            }
            $automation = app(RequestAutomation::class);
            if ($action === 'complete' && $clientRequest->status !== 'completed') {
                $automation->notify($request, 'completed', 'Your request is complete.', 'completed:'.$request->id.':'.$request->delivery_generation);
            }
            $request->next_reminder_at = $request->isEditable() && ! $request->reminders_paused && ! $request->reminders_stopped_reason ? $automation->nextMorning($request) : null;
            $request->save();
        });

    }
}
