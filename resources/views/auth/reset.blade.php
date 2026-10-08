@extends('layouts.app')
@section('title', 'Set new password')
@section('main-class', 'auth-content')
@section('content')
<h1>A fresh start.</h1><p class="intro">Choose a new password for your account.</p>
<form class="form" action="{{ route('password.update') }}" method="post">@csrf
<input type="hidden" name="token" value="{{ $token }}">
<label for="email">Email address</label><input id="email" type="email" name="email" value="{{ old('email', request('email')) }}" autocomplete="username" required>
<label for="password">New password</label><input id="password" type="password" name="password" autocomplete="new-password" required minlength="8"><p class="field-help">Use at least 8 characters.</p>
<label for="password_confirmation">Confirm new password</label><input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required minlength="8">
<button class="button">Reset password</button></form>
@endsection
