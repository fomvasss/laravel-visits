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
 * where cookies are unreliable) > cookie (plain browser) > $fallback, if given > the
 * authenticated request's own known Visitor, if any > freshly generated.
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

        $authInherited = $this->inheritFromAuthenticatedUser($request);

        if (is_string($authInherited) && $this->isValidFormat($authInherited)) {
            return $authInherited;
        }

        return $this->generate();
    }

    /**
     * Last resort before generating a brand-new identity. A Bearer-token API (Sanctum
     * personal access tokens, most mobile/SPA backends without CORS credentials) never gets
     * the visits cookie back on a cross-origin request — without this, every tracked action
     * from an already-known, authenticated user (login, purchase, ...) would otherwise
     * fragment into its own disconnected anonymous Visitor. Reuses their own most recently
     * active one instead, via HasVisits::visitorProfiles() — silently skipped if the auth
     * model doesn't use that trait.
     */
    private function inheritFromAuthenticatedUser(Request $request): ?string
    {
        $user = $request->user();

        if (! $user || ! method_exists($user, 'visitorProfiles')) {
            return null;
        }

        return $user->visitorProfiles()->latest('last_seen_at')->value('token');
    }

    /**
     * Whether the request itself already carries a returning-visitor signal (header/input
     * token, or the durable cookie) — distinct from resolve()'s full precedence chain, which
     * also considers $fallback and the authenticated user's own history. Used by TrackVisit's
     * `page_views: first_only` mode to tell a brand-new visitor's first hit apart from a
     * returning one, without generating or persisting anything.
     */
    public function hasRequestIdentity(Request $request): bool
    {
        $clientToken = $request->header(self::HEADER) ?: $request->input(self::INPUT_KEY);

        if (is_string($clientToken) && $this->isValidFormat($clientToken)) {
            return true;
        }

        $cookieToken = $request->cookie((string) config('visits.cookie.name'));

        return is_string($cookieToken) && $this->isValidFormat($cookieToken);
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
