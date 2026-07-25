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
 * where cookies are unreliable) > cookie (plain browser) > freshly generated.
 */
class TokenResolver
{
    public const HEADER = 'X-Visitor-Token';
    public const INPUT_KEY = 'visitor_token';

    public function resolve(Request $request): string
    {
        $clientToken = $request->header(self::HEADER) ?: $request->input(self::INPUT_KEY);

        if (is_string($clientToken) && $this->isValidFormat($clientToken)) {
            return $clientToken;
        }

        $cookieToken = $request->cookie((string) config('visits.cookie.name'));

        if (is_string($cookieToken) && $this->isValidFormat($cookieToken)) {
            return $cookieToken;
        }

        return $this->generate();
    }

    public function generate(): string
    {
        return Str::random(40);
    }

    public function isValidFormat(string $token): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9]{20,64}$/', $token);
    }
}
