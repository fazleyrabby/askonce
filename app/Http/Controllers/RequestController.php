<?php

namespace App\Http\Controllers;

use App\Actions\Reminders\RequestAutomation;
use App\Actions\Requests\ChangeRequestState;
use App\Actions\Requests\CreateRequest;
use App\Actions\Requests\DeleteRequest;
use App\Models\Client;
use App\Models\ClientRequest;
use App\Models\Upload;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RequestController extends Controller
{
    private const string EMAIL_LIMIT_MESSAGE = 'Your business has reached today’s client email limit. Emails resume tomorrow.';

    public function index(): View
    {
        return view('dashboard', ['requests' => ClientRequest::with('client')->withCount(['items', 'items as submitted_count' => fn ($query) => $query->where('status', 'submitted')])->latest()->paginate(20)]);
    }

    private function openRequestLimitMessage(): ?string
    {
        $limit = config('app.limits.open_requests');

        return ClientRequest::whereIn('status', ['draft', 'sent', 'in_progress'])->count() >= $limit ? "The free beta includes up to {$limit} open requests. Complete, close or delete one to make room." : null;
    }

    public function create(): View|RedirectResponse
    {
        if ($message = $this->openRequestLimitMessage()) {
            return redirect()->route('dashboard')->withErrors(['limit' => $message]);
        }

        return view('requests.create', ['clients' => Client::orderBy('name')->get()]);
    }

    public function store(Request $request, CreateRequest $create): RedirectResponse
    {
        if ($message = $this->openRequestLimitMessage()) {
            return redirect()->route('dashboard')->withErrors(['limit' => $message]);
        }
        $data = $request->validate([
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')->where('organization_id', app(CurrentOrganization::class)->get()->id)],
            'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'],
            'due_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'reminder_interval_days' => ['required', 'integer', Rule::in([1, 3, 7])],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*' => ['required', 'array:label,type,help_text,required'],
            'items.*.label' => ['required', 'string', 'max:255'],
            'items.*.type' => ['required', Rule::in(['file', 'text', 'long_text', 'url', 'confirmation'])],
            'items.*.help_text' => ['nullable', 'string', 'max:1000'],
            'items.*.required' => ['required', 'boolean'],
        ]);

        return redirect()->route('requests.show', $create->handle($data))->with('status', 'Request created. Activate its link when you’re ready to share.');
    }

    public function show(ClientRequest $clientRequest): View
    {
        return view('requests.show', ['clientRequest' => $clientRequest->load('client', 'items.submission.uploads'), 'deliveries' => DB::table('reminders')->where('organization_id', $clientRequest->organization_id)->where('client_request_id', $clientRequest->id)->latest('id')->limit(10)->get()]);
    }

    public function share(ClientRequest $clientRequest, ChangeRequestState $change): RedirectResponse
    {
        $change->handle($clientRequest, 'activate');

        return back()->with('status', 'Link activated. Copy it below and send it to your client.');
    }

    public function email(ClientRequest $clientRequest): RedirectResponse
    {
        abort_unless($clientRequest->isEditable(), 409, 'Activate or reopen the request first.');
        $error = DB::transaction(function () use ($clientRequest): ?string {
            $request = ClientRequest::lockForUpdate()->findOrFail($clientRequest->id);
            abort_unless($request->isEditable() && ! $request->reminders_stopped_reason, 409, 'Email delivery is stopped for this request.');
            $key = 'request:'.$request->id.':'.$request->delivery_generation.':'.now()->format('YmdH');
            if (DB::table('reminders')->where('client_request_id', $request->id)->where('kind', 'request')->where('generation', $request->delivery_generation)->where('created_at', '>', now()->subHour())->exists()) {
                return 'This request was already emailed in the last hour. Try again later.';
            }

            return app(RequestAutomation::class)->queue($request, 'request', $key) ? null : self::EMAIL_LIMIT_MESSAGE;
        });

        return $error ? back()->withErrors(['email' => $error]) : back()->with('status', 'Request email queued.');
    }

    public function reminders(Request $httpRequest, ClientRequest $clientRequest, RequestAutomation $automation): RedirectResponse
    {
        $action = $httpRequest->validate(['action' => ['required', Rule::in(['pause', 'resume', 'nudge', 'hard_bounce'])]])['action'];
        $queued = DB::transaction(function () use ($clientRequest, $action, $automation): bool {
            $request = ClientRequest::lockForUpdate()->findOrFail($clientRequest->id);
            abort_unless($request->isEditable(), 409);
            if ($action === 'hard_bounce') {
                $automation->stop($request, 'hard_bounce');
            } elseif ($action === 'nudge') {
                abort_if((bool) $request->reminders_stopped_reason, 409, 'This client has stopped receiving email.');

                return $automation->queue($request, 'nudge', 'nudge:'.$request->id.':'.$request->delivery_generation.':'.now()->format('YmdH'));
            } else {
                $request->reminders_paused = $action === 'pause';
                $request->next_reminder_at = $action === 'resume' && ! $request->reminders_stopped_reason ? $automation->nextMorning($request) : null;
                $request->save();
            }

            return true;
        });

        return $queued ? back()->with('status', 'Reminder preferences updated.') : back()->withErrors(['email' => self::EMAIL_LIMIT_MESSAGE]);
    }

    public function state(Request $request, ClientRequest $clientRequest, ChangeRequestState $change): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', Rule::in(['complete', 'cancel', 'reopen', 'regenerate'])]]);
        if ($data['action'] === 'reopen' && ($message = $this->openRequestLimitMessage())) {
            return back()->withErrors(['limit' => $message]);
        }
        $change->handle($clientRequest, $data['action']);

        return back()->with('status', 'Request updated.');
    }

    public function download(Upload $upload): StreamedResponse
    {
        return Storage::disk($upload->disk)->download($upload->path, $upload->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function destroy(ClientRequest $clientRequest, DeleteRequest $delete): RedirectResponse
    {
        $delete->handle($clientRequest);

        return redirect()->route('dashboard')->with('status', 'Request and its files deleted.');
    }
}
