@extends('layouts.app')
@section('title', 'Requests')
@section('content')
<div class="page-heading"><div><h1>Requests</h1><p class="intro">{{ app(App\Support\CurrentOrganization::class)->get()->name }}</p></div><a class="button" href="{{ route('requests.create') }}">New request</a></div>
@if($requests->isEmpty())
<div class="empty-state"><h2>Ask once. Keep things moving.</h2><p>Create a request for the files and answers you need. Your client gets one simple link.</p><a class="button" href="{{ route('requests.create') }}">Create your first request</a></div>
@else
<div class="request-list">
@foreach($requests as $request)
<a class="request-row" href="{{ route('requests.show', $request) }}"><div><h2>{{ $request->title }}</h2><p>{{ $request->client->name }} <span class="status-label">{{ str_replace('_', ' ', $request->status) }}</span></p></div><div class="request-progress"><progress value="{{ $request->submitted_count }}" max="{{ max(1, $request->items_count) }}" aria-label="Items submitted"></progress><span>{{ $request->submitted_count }} / {{ $request->items_count }} submitted</span></div><span>{{ $request->due_at ? 'Due '.$request->due_at->format('M j') : 'No due date' }}@if($request->isOverdue()) <strong class="overdue">Overdue</strong>@endif</span></a>
@endforeach
</div>{{ $requests->links() }}
@endif
@endsection
