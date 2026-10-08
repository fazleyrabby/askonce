@extends('layouts.app')
@section('title', 'Reset password')
@section('main-class', 'auth-content')
@section('content')
<h1>Forgot your password?</h1><p class="intro">We’ll send you a link to set a new one.</p>
<form class="form" action="{{ route('password.email') }}" method="post">@csrf
<label for="email">Email address</label><input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
<button class="button">Send reset link</button></form>
<p class="form-foot"><a href="{{ route('login') }}">Back to sign in</a></p>
@endsection
