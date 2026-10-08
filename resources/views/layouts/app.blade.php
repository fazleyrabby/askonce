<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'AskOnce') · AskOnce</title>
    {{ Illuminate\Support\Facades\Vite::fonts() }}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="header">
    <a class="wordmark" href="{{ url('/') }}">AskOnce<span class="brand-dot" aria-hidden="true"></span></a>
    @auth
        @if(auth()->user()->hasVerifiedEmail())
        <nav aria-label="Main navigation">
            @if(auth()->user()->is_admin)<a href="{{ route('admin.dashboard') }}" @if(request()->routeIs('admin.*')) aria-current="page" @endif>Admin</a>@endif
            <a href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif>Requests</a>
            <a href="{{ route('notifications.index') }}" @if(request()->routeIs('notifications.*')) aria-current="page" @endif>Notifications</a>
            <a href="{{ route('settings.edit') }}" @if(request()->routeIs('settings.*')) aria-current="page" @endif>Settings</a>
            <a href="{{ route('clients.index') }}" @if(request()->routeIs('clients.*')) aria-current="page" @endif>Clients</a>
        </nav>
        @endif
        <form action="{{ route('logout') }}" method="post">@csrf<button class="text-button">Sign out</button></form>
    @else
        <a class="header-note" href="{{ route('login') }}">Sign in</a>
    @endauth
</header>
<main id="main" class="@yield('main-class', 'content')">
    @if(session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())
        <div class="error-summary" role="alert"><strong>Please check your details.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @yield('content')
</main>
<footer class="footer">AskOnce · A little less chasing. <a href="{{ route('privacy') }}">Privacy</a> <a href="{{ route('terms') }}">Beta terms</a>@if(request()->routeIs('home', 'demo'))<span class="visits" title="Total visits" data-visits hidden>Visits <span data-visits-count aria-live="polite"></span></span>@endif</footer>
</body>
</html>
