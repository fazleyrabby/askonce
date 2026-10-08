@extends('layouts.app')
@section('title','Business settings')
@section('main-class','form-content')
@section('content')
<h1>Business settings</h1><p class="intro">The name clients see and the timezone for reminders.</p>
<form class="form" method="post" action="{{ route('settings.update') }}">@csrf @method('PUT')
<label for="name">Business name</label><input id="name" name="name" value="{{ old('name',$organization->name) }}" required maxlength="255" autocomplete="organization">
<label for="timezone">Business timezone</label><select id="timezone" name="timezone">@foreach(DateTimeZone::listIdentifiers() as $timezone)<option value="{{ $timezone }}" @selected(old('timezone',$organization->timezone) === $timezone)>{{ $timezone }}</option>@endforeach</select>
<p class="field-help">Reminders send between 9 am and noon. Changing timezone reschedules pending reminders.</p><button class="button">Save settings</button></form>
<section class="answer-row"><h2>Storage</h2><p>{{ number_format($usedBytes / 1048576,1) }} MB used of {{ number_format($organization->storage_quota_bytes / 1048576) }} MB.</p><p>Delete requests you no longer need to free their uploaded files.</p></section>
@endsection
