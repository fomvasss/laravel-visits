<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Support;

use Illuminate\Http\Request;

/**
 * Server-side counterpart to CORS for POST /visits/collect — CORS controls what a *browser*
 * lets a page fetch cross-origin, this controls what the *server* accepts regardless of client.
 * Origin/Referer are attacker-controlled and trivially spoofed by any non-browser client, so
 * this only filters casual/accidental misuse (a copy of the beacon left on a decommissioned
 * domain, a stray embed) — never treat it as authentication.
 */
class OriginValidator
{
    public function isAllowed(Request $request): bool
    {
        $allowed = config('visits.collect.allowed_origins');

        if (! $allowed) {
            return true;
        }

        $origin = $this->resolveOrigin($request);

        if (! $origin) {
            return false;
        }

        $normalized = array_map(static fn ($o) => rtrim((string) $o, '/'), (array) $allowed);

        return in_array(rtrim($origin, '/'), $normalized, true);
    }

    private function resolveOrigin(Request $request): ?string
    {
        $origin = $request->header('Origin');

        if (is_string($origin) && $origin !== '') {
            return $origin;
        }

        $referer = $request->header('Referer');

        if (! is_string($referer) || $referer === '') {
            return null;
        }

        $scheme = parse_url($referer, PHP_URL_SCHEME);
        $host = parse_url($referer, PHP_URL_HOST);

        if (! $scheme || ! $host) {
            return null;
        }

        $port = parse_url($referer, PHP_URL_PORT);

        return $scheme . '://' . $host . ($port ? ':' . $port : '');
    }
}
