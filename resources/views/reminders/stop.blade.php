@extends('layouts.app')
@section('title','Stop reminders')
@section('content')
<h1>Stop reminders</h1><p>Stop reminder emails for {{ $clientRequest->title }}. Your request link will still work.</p>
@if($clientRequest->reminders_stopped_reason === 'unsubscribed')<p>Reminders are stopped.</p>@else<form method="post" action="{{ route('public-request.unsubscribe',['token'=>$clientRequest->token]) }}">@csrf<button class="button">Stop reminders for this request</button></form>@endif
<a href="{{ $clientRequest->publicUrl() }}">Back to request</a>
@endsection
