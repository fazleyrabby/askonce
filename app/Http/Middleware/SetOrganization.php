<?php

namespace App\Http\Middleware;

use App\Support\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        $organization = $request->user()->organizations()->first();
        abort_unless($organization, 403, 'Your account has no organization.');
        app(CurrentOrganization::class)->set($organization);

        return $next($request);
    }
}
