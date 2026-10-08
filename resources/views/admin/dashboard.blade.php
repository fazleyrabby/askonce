@extends('layouts.app')
@section('title', 'Admin')
@section('content')
<div class="page-heading"><div><h1>Admin overview</h1><p class="intro">{{ $userCount }} users · {{ $requestCount }} requests across AskOnce</p></div></div>
<div class="client-list"><div class="list-heading"><span>Organization</span><span>Timezone</span><span>Users</span><span>Created</span></div>
@foreach($organizations as $organization)<div class="client-row"><strong>{{ $organization->name }}</strong><span>{{ $organization->timezone }}</span><span>{{ $organization->users_count }}</span><span>{{ $organization->created_at->format('M j, Y') }}</span></div>@endforeach
</div>{{ $organizations->links() }}
@endsection
