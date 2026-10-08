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
            'organizations' => Organization::withCount('users')->latest()->paginate(20),
            'userCount' => User::count(),
            'requestCount' => ClientRequest::withoutGlobalScope('organization')->count(),
        ]);
    }
}
