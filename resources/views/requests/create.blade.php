@extends('layouts.app')
@section('title', 'New request')
@section('main-class', 'builder-content')
@section('content')
<a class="back-link" href="{{ route('dashboard') }}">Back to requests</a><h1>What are you waiting for?</h1><p class="intro">Give your client one clear list.</p>
@if($clients->isEmpty())
<div class="notice">Add a client before creating a request. <a href="{{ route('clients.create') }}">Add client</a></div>
@else
<form class="form" id="request-builder" method="post" action="{{ route('requests.store') }}">@csrf
<div class="builder-details"><div>
<label for="client_id">Client</label><select id="client_id" name="client_id" required>@foreach($clients as $client)<option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>{{ $client->name }}</option>@endforeach</select>
<label for="title">Request title</label><input id="title" name="title" value="{{ old('title') }}" placeholder="Website content" required maxlength="255">
<label for="description">Description <span class="optional">(optional)</span></label><textarea id="description" name="description" rows="3" maxlength="5000">{{ old('description') }}</textarea>
</div><div>
<label for="due_at">Due date <span class="optional">(optional)</span></label><input id="due_at" type="date" name="due_at" value="{{ old('due_at') }}" min="{{ now()->toDateString() }}">
<label for="reminder_interval_days">Reminder frequency</label><select id="reminder_interval_days" name="reminder_interval_days">@foreach([1,3,7] as $days)<option value="{{ $days }}" @selected(old('reminder_interval_days',3) == $days)>Every {{ $days }} {{ $days === 1 ? 'day' : 'days' }}</option>@endforeach</select><p class="field-help">Reminders arrive in your business’s morning and stop after completion or five reminders.</p>
</div></div>
<h2>What do you need?</h2><p class="intro">Files, a few words, a link, or a simple confirmation.</p>
<div id="builder-items">
@foreach(old('items', [['label'=>'','type'=>'file','required'=>1,'help_text'=>'']]) as $index => $item)
@include('requests.item-fields', ['index'=>$index, 'item'=>$item])
@endforeach
</div>
<button class="text-button add-item" type="button" id="add-item">Add another item</button>
<div class="builder-submit"><button class="button">Create request</button><p class="field-help">You can review the request before sharing it.</p></div>
</form>
<template id="item-template">@include('requests.item-fields', ['index'=>'__INDEX__','item'=>['label'=>'','type'=>'text','required'=>1,'help_text'=>'']])</template>
@endif
@endsection
