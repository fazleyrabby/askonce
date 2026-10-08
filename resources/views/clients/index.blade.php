@extends('layouts.app')
@section('title', 'Clients')
@section('content')
<div class="page-heading"><div><h1>Clients</h1><p class="intro">The people you’re working with.</p></div><a class="button" href="{{ route('clients.create') }}">Add client</a></div>
@if($clients->isEmpty())
<div class="empty-state"><h2>A home for your clients.</h2><p>Add a business and its contact details. You’ll have them ready when you create a request.</p><a href="{{ route('clients.create') }}">Add your first client</a></div>
@else
<div class="client-list"><div class="list-heading"><span>Business</span><span>Contact</span><span>Email</span><span></span></div>
@foreach($clients as $client)
<div class="client-row"><strong>{{ $client->name }}</strong><span>{{ $client->contact_name ?: 'No contact name' }}</span><span class="client-email">{{ $client->email }}</span><a aria-label="Edit {{ $client->name }}" href="{{ route('clients.edit', $client) }}">Edit</a></div>
@endforeach</div>
{{ $clients->links() }}
@endif
@endsection
