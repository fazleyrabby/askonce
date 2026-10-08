<?php

namespace App\Http\Controllers;

use App\Models\ClientRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'organizations' => Organization::where('is_demo', false)->withCount('users')->latest()->paginate(20),
            'userCount' => User::whereDoesntHave('organizations', fn ($query) => $query->where('is_demo', true))->count(),
            'requestCount' => ClientRequest::withoutGlobalScope('organization')->whereNotIn('organization_id', Organization::where('is_demo', true)->select('id'))->count(),
        ]);
    }
}
