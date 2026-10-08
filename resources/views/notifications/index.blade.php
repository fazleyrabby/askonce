@extends('layouts.app')
@section('title', 'Notifications')
@section('content')
<div class="page-heading notification-heading">
    <div><h1>Notifications</h1><p class="intro">Client updates and things that need your attention.</p></div>
</div>
@if($notifications->isNotEmpty())
<div class="notification-list" aria-label="Request updates">
@foreach($notifications as $notification)
@php
    $eventType = explode(':', $notification->data['event_key'] ?? '')[0];
    $eventLabel = match($eventType) {
        'completed' => 'Request completed',
        'progress' => 'Progress saved',
        'stop' => 'Reminders stopped',
        'overdue' => 'Overdue',
        'storage-full' => 'Storage full',
        default => 'Request update',
    };
    $eventTone = in_array($eventType, ['stop', 'overdue', 'storage-full']) ? 'attention' : 'positive';
    $isUnread = !$notification->read_at;
@endphp
<article class="notification-row {{ $isUnread ? 'notification-unread' : 'notification-read' }}" aria-labelledby="notification-{{ $notification->id }}">
    <div class="notification-symbol notification-symbol-{{ $eventTone }}" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            @if($eventType === 'completed')
                <path d="m7 12 3 3 7-7"/><circle cx="12" cy="12" r="9"/>
            @elseif($eventTone === 'attention')
                <path d="M12 8v5m0 3h.01M10.3 4.8 2.5 18.3a1.5 1.5 0 0 0 1.3 2.2h16.4a1.5 1.5 0 0 0 1.3-2.2L13.7 4.8a2 2 0 0 0-3.4 0Z"/>
            @else
                <path d="M8 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-3M16 3l5 5m-2.5-7.5a2.1 2.1 0 0 1 3 3L11 14l-4 1 1-4Z"/>
            @endif
        </svg>
    </div>
    <div class="notification-body">
        <div class="notification-title-row">
            <h2 id="notification-{{ $notification->id }}">{{ $notification->data['title'] }}</h2>
            <time datetime="{{ $notification->created_at->toIso8601String() }}" title="{{ $notification->created_at->timezone(app(App\Support\CurrentOrganization::class)->get()->timezone)->format('M j, Y · g:i a') }}">{{ $notification->created_at->diffForHumans() }}</time>
        </div>
        <div class="notification-meta"><span class="notification-event notification-event-{{ $eventTone }}">{{ $eventLabel }}</span><span class="notification-read-state">{{ $isUnread ? 'Unread' : 'Read' }}</span></div>
        <p class="notification-message">{{ $notification->data['message'] }}</p>
        <div class="notification-actions">
            <a class="notification-link" href="{{ route('requests.show', $notification->data['request_id']) }}">View request <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 10h12m-5-5 5 5-5 5"/></svg></a>
            @if($isUnread)
            <form method="post" action="{{ route('notifications.read', $notification->id) }}">@csrf<button class="notification-read-button" aria-label="Mark {{ $notification->data['title'] }} as read">Mark as read</button></form>
            @endif
        </div>
    </div>
</article>
@endforeach
</div>
{{ $notifications->links() }}
@else
<div class="empty-state notification-empty"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M4 4h16v16H4zM4 14h5l2 3h2l2-3h5"/></svg><h2>You’re all caught up.</h2><p>Client progress, completed requests, and reminder updates will appear here.</p><a href="{{ route('dashboard') }}">Back to requests</a></div>
@endif
@endsection
