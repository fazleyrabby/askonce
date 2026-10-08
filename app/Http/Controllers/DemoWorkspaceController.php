<?php

namespace App\Http\Controllers;

use App\Actions\Demo\StartDemoWorkspace;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DemoWorkspaceController extends Controller
{
    public function __invoke(Request $request, StartDemoWorkspace $start): RedirectResponse
    {
        if (Organization::where('is_demo', true)->count() >= StartDemoWorkspace::MAX_ACTIVE) {
            return back()->withErrors(['demo' => 'The live demo is busy right now. Please try again later.']);
        }
        Auth::login($start->handle());
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
