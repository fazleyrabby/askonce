@extends('layouts.app')
@section('title', isset($client) ? 'Edit client' : 'Add client')
@section('main-class', 'form-content')
@section('content')
<a class="back-link" href="{{ route('clients.index') }}">Back to clients</a>
<h1>{{ isset($client) ? 'Edit client' : 'Add a client.' }}</h1><p class="intro">A business name and someone to reach.</p>
<form class="form" action="{{ isset($client) ? route('clients.update', $client) : route('clients.store') }}" method="post">@csrf @isset($client) @method('PUT') @endisset
<label for="name">Client / business name</label><input id="name" name="name" value="{{ old('name', $client->name ?? '') }}" required autofocus maxlength="255">
<label for="contact_name">Contact name <span class="optional">(optional)</span></label><input id="contact_name" name="contact_name" value="{{ old('contact_name', $client->contact_name ?? '') }}" maxlength="255" autocomplete="name">
<label for="email">Contact email</label><input id="email" type="email" name="email" value="{{ old('email', $client->email ?? '') }}" required autocomplete="email">
<button class="button">{{ isset($client) ? 'Save changes' : 'Add client' }}</button>
</form>
@endsection
