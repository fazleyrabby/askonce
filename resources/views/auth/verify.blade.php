@extends('layouts.app')
@section('title', 'Verify email')
@section('main-class', 'auth-content')
@section('content')
<h1>Check your inbox.</h1><p class="intro">We sent a verification link to <strong>{{ auth()->user()->email }}</strong>. Open it to finish setting up your account.</p>
<form method="post" action="{{ route('verification.send') }}">@csrf<button class="button">Resend verification email</button></form>
<p class="form-foot">Check your spam folder if the email hasn’t arrived.</p>
@endsection
