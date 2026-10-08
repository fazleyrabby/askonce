@extends('layouts.app')
@section('title', $clientRequest->title)
@section('content')
<a class="back-link" href="{{ route('dashboard') }}">Back to requests</a>
<div class="page-heading"><div><h1>{{ $clientRequest->title }}</h1><p class="intro">{{ $clientRequest->client->name }} · {{ $clientRequest->client->email }}</p></div><span class="status-label">{{ str_replace('_',' ',$clientRequest->status) }}</span></div>
@if($clientRequest->description)<p class="request-description">{{ $clientRequest->description }}</p>@endif
@php($submittedCount = $clientRequest->items->where('status', 'submitted')->count())
<div class="request-progress request-summary"><progress value="{{ $submittedCount }}" max="{{ max(1, $clientRequest->items->count()) }}" aria-label="Items submitted"></progress><span>{{ $submittedCount }} of {{ $clientRequest->items->count() }} submitted</span></div>
<div class="share-panel">
@if($clientRequest->status === 'draft')
<p>Your request is ready to review. Activate its link when you’re ready.</p><form method="post" action="{{ route('requests.share', $clientRequest) }}">@csrf<button class="button">Activate client link</button></form>
@else
<label for="share-url">Client link</label><div class="share-controls"><input id="share-url" readonly value="{{ $clientRequest->publicUrl() }}"><button class="button" type="button" id="copy-link">Copy link</button></div><p id="copy-status" role="status"></p>
@if($clientRequest->isEditable())<form method="post" action="{{ route('requests.email',$clientRequest) }}">@csrf<button class="quiet-button">Email request to {{ $clientRequest->client->contact_name ?: 'client' }}</button></form>@endif
@endif
</div>
<section class="answer-row"><h2>Reminders</h2>
<p>{{ $clientRequest->reminders_sent }} of 5 reminders sent. Every {{ $clientRequest->reminder_interval_days }} days, in your business’s morning.</p>
@if($clientRequest->reminders_stopped_reason)<p>Stopped: {{ str_replace('_',' ',$clientRequest->reminders_stopped_reason) }}.</p>@elseif($clientRequest->reminders_paused)<p>Reminders paused.</p>@elseif($clientRequest->next_reminder_at)<p>Next reminder: {{ $clientRequest->next_reminder_at->timezone(app(App\Support\CurrentOrganization::class)->get()->timezone)->format('M j, g:i a') }}.</p>@endif
@if($clientRequest->isEditable())
@if(!$clientRequest->reminders_stopped_reason)
<div class="action-row">
@foreach([$clientRequest->reminders_paused ? 'resume' : 'pause' => $clientRequest->reminders_paused ? 'Resume reminders' : 'Pause reminders', 'nudge'=>'Send a nudge'] as $action=>$label)
<form method="post" action="{{ route('requests.reminders',$clientRequest) }}">@csrf<input type="hidden" name="action" value="{{ $action }}"><button class="quiet-button">{{ $label }}</button></form>
@endforeach
<form method="post" action="{{ route('requests.reminders',$clientRequest) }}">@csrf<input type="hidden" name="action" value="hard_bounce"><button class="link-button" data-confirm="Stop emails because this address bounced?">Report a bounced email</button></form>
</div>
@endif
@endif
@foreach($deliveries as $delivery)<p class="field-help">{{ str_starts_with($delivery->kind,'business:') ? 'Business update' : ucfirst($delivery->kind) }} · {{ $delivery->status }} · {{ $delivery->created_at }}</p>@endforeach
</section>
@foreach($clientRequest->items as $item)
<section class="answer-row"><div class="answer-heading"><h2>{{ $item->label }}</h2><span class="status-pill status-pill-{{ $item->status === 'submitted' ? 'submitted' : 'pending' }}">{{ $item->status }}{{ $item->required ? '' : ' · optional' }}</span></div>
@if($item->help_text)<p class="field-help">{{ $item->help_text }}</p>@endif
@if($item->type === 'file')
@forelse($item->submission?->uploads ?? [] as $upload)<a class="download-link" href="{{ route('uploads.download',$upload) }}">{{ $upload->original_name }} <span>{{ number_format($upload->size / 1024) }} KB</span></a>@empty<p class="field-help">No files yet.</p>@endforelse
@elseif($item->submission?->value)<p class="answer-value">{{ $item->type === 'confirmation' ? 'Confirmed' : $item->submission->value }}</p>@else<p class="field-help">No answer yet.</p>@endif
</section>
@endforeach
<div class="request-actions">
@if(in_array($clientRequest->status,['completed','expired','cancelled']))
<form method="post" action="{{ route('requests.state',$clientRequest) }}">@csrf<input type="hidden" name="action" value="reopen"><button class="quiet-button">Reopen request</button></form><p class="field-help request-actions-note">Reopening clears the old due date.</p>
@else
@foreach(['complete'=>'Mark complete','cancel'=>'Close request'] as $action=>$label)<form method="post" action="{{ route('requests.state',$clientRequest) }}">@csrf<input type="hidden" name="action" value="{{ $action }}"><button class="{{ $action === 'complete' ? 'quiet-button' : 'link-button' }}">{{ $label }}</button></form>@endforeach
@endif
<form method="post" action="{{ route('requests.state',$clientRequest) }}">@csrf<input type="hidden" name="action" value="regenerate"><button class="link-button" data-confirm="Replace the client link? The old link will stop working.">Replace client link</button></form>
<form class="request-actions-danger" method="post" action="{{ route('requests.destroy',$clientRequest) }}">@csrf @method('DELETE')<button class="link-button danger" data-confirm="Delete this request and all uploaded files permanently?">Delete request</button></form>
</div>
@endsection
