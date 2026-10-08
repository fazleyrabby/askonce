<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'AskOnce') · AskOnce</title>
    <meta name="description" content="@yield('description', 'Collect files, answers and links from clients with one simple link. Automatic reminders for what’s still missing. No client login.')">
    @if(request()->routeIs('home', 'demo', 'privacy', 'terms'))
    <link rel="canonical" href="{{ url()->current() }}">
    @else
    <meta name="robots" content="noindex">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="AskOnce">
    <meta property="og:title" content="@yield('title', 'AskOnce') · AskOnce">
    <meta property="og:description" content="@yield('description', 'Collect files, answers and links from clients with one simple link. Automatic reminders for what’s still missing. No client login.')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('og-image.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="AskOnce. Ask once. Stop chasing.">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icon-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <meta name="theme-color" content="#faf9f6">
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
    @if(app(App\Support\CurrentOrganization::class)->current()?->is_demo)<p class="demo-banner" role="note">You’re in a demo workspace with sample data. Emails aren’t really sent, and everything here is deleted after {{ App\Actions\Demo\StartDemoWorkspace::LIFETIME_HOURS }} hours. Sign out when you’re ready to create a free account.</p>@endif
    @if(session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())
        <div class="error-summary" role="alert"><strong>Please check your details.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @yield('content')
</main>
<footer class="footer">AskOnce · A little less chasing. <a href="{{ route('home') }}#pricing">Pricing</a> <a href="{{ route('privacy') }}">Privacy</a> <a href="{{ route('terms') }}">Beta terms</a>@if(request()->routeIs('home', 'demo'))<span class="visits" title="Total visits" data-visits hidden>Visits <span data-visits-count aria-live="polite"></span></span>@endif</footer>
</body>
</html>
