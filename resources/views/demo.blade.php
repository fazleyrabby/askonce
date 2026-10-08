@extends('layouts.app')
@section('title', 'Demo')
@section('description', 'Try AskOnce in a private demo workspace with sample clients and requests. No sign-up, and no emails are sent.')
@section('content')
@php
$requests = [
    ['title' => 'Website content', 'client' => 'ABC Restaurant', 'status' => 'In progress', 'submitted' => 3, 'total' => 5, 'due' => 'Due in 4 days'],
    ['title' => 'Brand photoshoot brief', 'client' => 'Bloom Florist', 'status' => 'Sent', 'submitted' => 0, 'total' => 4, 'due' => 'Due in 9 days'],
    ['title' => 'Tax season documents', 'client' => 'Oak & Co.', 'status' => 'Completed', 'submitted' => 6, 'total' => 6, 'due' => 'No due date'],
];
$items = [
    ['label' => 'Company logo', 'status' => 'Submitted', 'help' => 'A high-resolution PNG or JPG works well.', 'answer' => 'abc-restaurant-logo.png · 248 KB'],
    ['label' => 'About your business', 'status' => 'Submitted', 'help' => null, 'answer' => 'Family-run since 1998. Wood-fired cooking, a short seasonal menu, and a room that seats forty.'],
    ['label' => 'Contact phone number', 'status' => 'Submitted', 'help' => null, 'answer' => '555 0100'],
    ['label' => 'Menu PDF', 'status' => 'Pending', 'help' => 'The current dinner menu is enough for now.', 'answer' => null],
    ['label' => 'I confirm these details are correct', 'status' => 'Pending', 'help' => null, 'answer' => null],
];
@endphp
<div class="demo-start"><div><h1>Try AskOnce.</h1><p class="intro">Open a private demo workspace with sample clients and requests. Create a request, open the client link, and fill it in yourself. No sign-up, no emails sent, deleted after {{ App\Actions\Demo\StartDemoWorkspace::LIFETIME_HOURS }} hours.</p></div><form method="post" action="{{ route('demo.start') }}">@csrf<button class="button">Open the live demo</button></form></div>
<p class="demo-banner" role="note">Or look first: this is what a workspace looks like, with sample data.</p>
<div class="page-heading"><div><h2>Requests</h2><p class="intro">North Studio</p></div></div>
<div class="request-list">
@foreach($requests as $request)
<div class="request-row"><div><h2>{{ $request['title'] }}</h2><p>{{ $request['client'] }} <span class="status-label">{{ $request['status'] }}</span></p></div><div class="request-progress"><progress value="{{ $request['submitted'] }}" max="{{ $request['total'] }}" aria-label="Items submitted"></progress><span>{{ $request['submitted'] }} / {{ $request['total'] }} submitted</span></div><span>{{ $request['due'] }}</span></div>
@endforeach
</div>
<section class="demo-request" aria-labelledby="demo-request-title">
<div class="page-heading"><div><h2 id="demo-request-title">Website content</h2><p class="intro">ABC Restaurant</p></div><span class="status-label">In progress</span></div>
<p class="request-description">Please share the following for your new website. You can save each item and return later.</p>
<section class="answer-row"><h2>Reminders</h2><p>1 of 5 reminders sent. Every 3 days, in your business’s morning.</p><p class="field-help">Each reminder lists only what’s still missing. They stop once every required item is in.</p></section>
@foreach($items as $item)
<section class="answer-row"><div class="answer-heading"><h2>{{ $item['label'] }}</h2><span class="status-label">{{ $item['status'] }}</span></div>
@if($item['help'])<p class="field-help">{{ $item['help'] }}</p>@endif
@if($item['answer'])<p class="answer-value">{{ $item['answer'] }}</p>@else<p class="field-help">No answer yet.</p>@endif
</section>
@endforeach
</section>
<div class="demo-close"><form method="post" action="{{ route('demo.start') }}">@csrf<button class="button">Open the live demo</button></form><a href="{{ route('register') }}">Create your free account</a></div>
@endsection
