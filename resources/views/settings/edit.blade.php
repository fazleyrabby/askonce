@extends('layouts.app')
@section('title','Business settings')
@section('main-class','form-content')
@section('content')
<h1>Business settings</h1><p class="intro">The name clients see and the timezone for reminders.</p>
<form class="form" method="post" action="{{ route('settings.update') }}">@csrf @method('PUT')
<label for="name">Business name</label><input id="name" name="name" value="{{ old('name',$organization->name) }}" required maxlength="255" autocomplete="organization">
<label for="timezone">Business timezone</label><select id="timezone" name="timezone">@foreach(DateTimeZone::listIdentifiers() as $timezone)<option value="{{ $timezone }}" @selected(old('timezone',$organization->timezone) === $timezone)>{{ $timezone }}</option>@endforeach</select>
<p class="field-help">Reminders send between 9 am and noon. Changing timezone reschedules pending reminders.</p><button class="button">Save settings</button></form>
<section class="answer-row"><h2>Usage</h2><p class="field-help">What the free beta includes for your business.</p>
<dl class="usage-list">
<div><dt>Storage</dt><dd>{{ number_format($usedBytes / 1048576,1) }} of {{ number_format($organization->storage_quota_bytes / 1048576) }} MB</dd></div>
<div><dt>Clients</dt><dd>{{ $clientCount }} of {{ $limits['clients'] }}</dd></div>
<div><dt>Open requests</dt><dd>{{ $openRequestCount }} of {{ $limits['open_requests'] }}</dd></div>
<div><dt>Client emails, last 24 hours</dt><dd>{{ $clientEmailsToday }} of {{ $limits['client_emails_per_day'] }}</dd></div>
</dl>
<p class="field-help">Delete requests you no longer need to free their uploaded files.</p></section>
@endsection
