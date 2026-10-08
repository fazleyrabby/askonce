@extends('layouts.app')
@section('title', 'Create account')
@section('main-class', 'auth-content')
@section('content')
<h1>Less chasing.<br>More doing.</h1>
<p class="intro">Create your business account. Free during beta.</p>
<form class="form" method="post" action="{{ route('register') }}" x-data="{ timezone: {{ Illuminate\Support\Js::from(old('timezone')) }} || Intl.DateTimeFormat().resolvedOptions().timeZone }">@csrf
<label for="name">Your name</label><input id="name" name="name" value="{{ old('name') }}" autocomplete="name" required autofocus maxlength="255">
<label for="business_name">Business name</label><input id="business_name" name="business_name" value="{{ old('business_name') }}" autocomplete="organization" required maxlength="255">
<label for="email">Email address</label><input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required>
<label for="timezone">Business timezone</label><select id="timezone" name="timezone" x-model="timezone">@foreach(DateTimeZone::listIdentifiers() as $timezone)<option value="{{ $timezone }}" @selected(old('timezone', 'UTC') === $timezone)>{{ $timezone }}</option>@endforeach</select>
<p class="field-help">Reminders follow your business’s local time.</p>
<label for="password">Password</label><input id="password" type="password" name="password" autocomplete="new-password" required minlength="8"><p class="field-help">Use at least 8 characters.</p>
<label for="password_confirmation">Confirm password</label><input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required minlength="8">
<button class="button">Create account</button>
</form>
<p class="form-foot">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
@endsection
