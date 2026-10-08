<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UseCloudflareClientIp
{
    /**
     * Use the visitor address Cloudflare reports when the request arrived through the local tunnel.
     *
     * The reverse proxy replaces the forwarded address with the tunnel's private address, which would
     * put every visitor in one rate-limit bucket. A request that reached the proxy from a public
     * address did not come through the tunnel, so its Cloudflare header is ignored.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $visitor = $request->headers->get('CF-Connecting-IP');
        if ($visitor && filter_var($visitor, FILTER_VALIDATE_IP) && $request->isFromTrustedProxy() && ! $this->isPublic((string) $request->ip())) {
            $request->headers->set('X-Forwarded-For', $visitor);
        }

        return $next($request);
    }

    private function isPublic(string $address): bool
    {
        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
