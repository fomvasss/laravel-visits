<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Resolves the durable visitor identity token — replaces the anon_id + fingerprint + IP-guess
 * trio from the legacy dropshop/greespi implementations with a single mechanism.
 *
 * Precedence: client-supplied token (header/body — SPA localStorage, native/mobile clients
 * where cookies are unreliable) > cookie (plain browser) > $fallback, if given > freshly
 * generated.
 */
class TokenResolver
{
    public const HEADER = 'X-Visitor-Id';
    public const INPUT_KEY = 'visitor_id';

    /**
     * @param  (callable(): ?string)|null  $fallback  consulted only when the request itself
     *   carries no identity signal at all (no header/input, no cookie) — e.g.
     *   VisitsManager::track()'s `inheritFrom`, resolving a prior event's visitor for a
     *   server-to-server call (a payment webhook) that has no browser identity of its own.
     *   Never overrides an actual request-derived token.
     */
    public function resolve(Request $request, ?callable $fallback = null): string
    {
        $clientToken = $request->header(self::HEADER) ?: $request->input(self::INPUT_KEY);

        if (is_string($clientToken) && $this->isValidFormat($clientToken)) {
            return $clientToken;
        }

        $cookieToken = $request->cookie((string) config('visits.cookie.name'));

        if (is_string($cookieToken) && $this->isValidFormat($cookieToken)) {
            return $cookieToken;
        }

        if ($fallback) {
            $inherited = $fallback();

            if (is_string($inherited) && $this->isValidFormat($inherited)) {
                return $inherited;
            }
        }

        return $this->generate();
    }

    public function generate(): string
    {
        return Str::random(40);
    }

    public function isValidFormat(string $token): bool
    {
        return (bool) preg_match((string) config('visits.visitor_id.format_regex'), $token);
    }
}
