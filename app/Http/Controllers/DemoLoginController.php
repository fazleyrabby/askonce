<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class DemoLoginController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(app()->environment('local'), 404);
        $data = $request->validate(['account' => ['required', Rule::in(['admin', 'user'])]]);
        $email = $data['account'] === 'admin' ? 'admin@askonce.test' : 'demo@askonce.test';
        $user = User::where('email', $email)->whereNotNull('email_verified_at')->first();
        if (! $user) {
            return back()->withErrors(['demo' => 'Demo accounts are not available yet. Run php artisan db:seed.']);
        }
        abort_unless($user->is_admin === ($data['account'] === 'admin'), 404);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route($user->is_admin ? 'admin.dashboard' : 'dashboard');
    }
}
