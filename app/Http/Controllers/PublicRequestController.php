<?php

namespace App\Http\Controllers;

use App\Actions\Reminders\RequestAutomation;
use App\Actions\Submissions\SaveItem;
use App\Models\ClientRequest;
use App\Models\Organization;
use App\Support\CurrentOrganization;
use App\Support\RecordActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PublicRequestController extends Controller
{
    private function resolve(string $token): ClientRequest
    {
        abort_unless(preg_match('/^[A-Za-z0-9_-]{43}$/', $token), 404);
        $request = ClientRequest::withoutGlobalScope('organization')->where('token_hash', hash('sha256', $token))->firstOrFail();
        $organization = Organization::findOrFail($request->organization_id);
        app(CurrentOrganization::class)->set($organization);
        abort_if($request->status === 'draft', 404);
        if ($request->isEditable() && $request->due_at && now($organization->timezone)->greaterThan(Carbon::parse($request->due_at->format('Y-m-d'), $organization->timezone)->addDays(30)->endOfDay())) {
            ClientRequest::whereKey($request->id)->whereIn('status', ['sent', 'in_progress'])->update(['status' => 'expired', 'next_reminder_at' => null]);
            $request->refresh();
        }

        return $request;
    }

    public function stop(string $token): View
    {
        return view('reminders.stop', ['clientRequest' => $this->resolve($token)]);
    }

    public function unsubscribe(string $token): RedirectResponse
    {
        $request = $this->resolve($token);
        DB::transaction(function () use ($request): void {
            $locked = ClientRequest::lockForUpdate()->findOrFail($request->id);
            abort_unless(hash_equals($locked->token_hash, $request->token_hash), 404);
            app(RequestAutomation::class)->stop($locked, 'unsubscribed');
        });

        return back()->with('status', 'Reminders stopped. You can still use your request link.');
    }

    public function show(string $token): View
    {
        $request = $this->resolve($token);
        $organization = app(CurrentOrganization::class)->get();

        app(RecordActivity::class)->record('client_opened', $request->organization_id, $request->id, 'opened:'.$request->id.':'.$request->delivery_generation);

        return view('public-request', [
            'clientRequest' => $request->load('client', 'items.submission.uploads'),
            'organization' => $organization,
            'contactEmail' => $organization->users()->wherePivot('role', 'owner')->firstOrFail()->email,
        ]);
    }

    public function save(Request $httpRequest, string $token, int $item, SaveItem $save): JsonResponse
    {
        $request = $this->resolve($token);
        abort_unless($request->isEditable(), 409, 'This request is closed.');
        $requestItem = $request->items()->findOrFail($item);
        $rules = match ($requestItem->type) {
            'file' => [
                'replace' => ['sometimes', 'boolean'],
                'files' => ['required', 'array', 'min:1', 'max:10'],
                'files.*' => ['required', 'file', 'max:51200', 'extensions:jpg,jpeg,png,webp,gif,pdf,txt,csv,doc,docx,xls,xlsx,ppt,pptx', 'mimes:jpg,jpeg,png,webp,gif,pdf,txt,csv,doc,docx,xls,xlsx,ppt,pptx'],
            ],
            'url' => ['value' => ['nullable', 'url:http,https', 'max:2048']],
            'confirmation' => ['value' => ['required', 'boolean']],
            'text' => ['value' => ['nullable', 'string', 'max:1000']],
            default => ['value' => ['nullable', 'string', 'max:20000']],
        };
        $data = $httpRequest->validate($rules);
        if ($requestItem->type === 'file' && array_sum(array_map(fn ($file) => $file->getSize(), $data['files'])) > 90 * 1024 * 1024) {
            throw ValidationException::withMessages(['files' => 'Choose files totalling no more than 90 MB per batch.']);
        }
        $save->handle($request, $item, $data);

        return response()->json(['saved' => true, 'completed' => $request->fresh()->status === 'completed']);
    }

    public function submit(string $token): JsonResponse
    {
        $request = $this->resolve($token);
        DB::transaction(function () use ($request): void {
            $locked = ClientRequest::lockForUpdate()->findOrFail($request->id);
            abort_unless(hash_equals($locked->token_hash, $request->token_hash), 404);
            if ($locked->isEditable()) {
                $locked->last_submitted_at = now();
                $locked->save();
                $locked->refreshProgress();
                app(RequestAutomation::class)->progress($locked);
            } else {
                abort_unless($locked->status === 'completed', 409, 'This request is closed.');
            }
        });
        $missing = $request->items()->where('required', true)->where('status', 'pending')->count();

        return response()->json(['completed' => $request->fresh()->status === 'completed', 'missing' => $missing]);
    }
}
