@extends('layouts.app')
@section('title', 'Sign in')
@section('main-class', 'auth-content')
@section('content')
<h1>Welcome back.</h1>
<p class="intro">Your client work, all in one place.</p>
<form class="form" method="post" action="{{ route('login') }}">@csrf
<label for="email">Email address</label><input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
<label for="password">Password</label><input id="password" type="password" name="password" autocomplete="current-password" required>
<div class="form-row"><label class="checkbox"><input type="checkbox" name="remember" value="1">Remember me</label><a href="{{ route('password.request') }}">Forgot password?</a></div>
<button class="button">Sign in</button>
</form>
<p class="form-foot">New to AskOnce? <a href="{{ route('register') }}">Create an account</a></p>
@if(app()->environment('local'))
<div class="demo-logins"><p>Try the demo · Local development only</p><div class="demo-login-buttons">
<form method="post" action="{{ route('demo.login') }}">@csrf<input type="hidden" name="account" value="admin"><button class="button">Demo admin</button></form>
<form method="post" action="{{ route('demo.login') }}">@csrf<input type="hidden" name="account" value="user"><button class="button">Demo user</button></form>
</div></div>
@endif
@endsection
