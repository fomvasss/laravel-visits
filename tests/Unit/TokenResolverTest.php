<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests\Unit;

use Fomvasss\Visits\Support\TokenResolver;
use Fomvasss\Visits\Tests\TestCase;
use Illuminate\Http\Request;

class TokenResolverTest extends TestCase
{
    private TokenResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new TokenResolver();
    }

    public function test_generate_returns_forty_char_alphanumeric_string(): void
    {
        $token = $this->resolver->generate();

        $this->assertSame(40, strlen($token));
        $this->assertTrue($this->resolver->isValidFormat($token));
    }

    public function test_rejects_tokens_outside_length_bounds(): void
    {
        $this->assertFalse($this->resolver->isValidFormat(str_repeat('a', 19)));
        $this->assertTrue($this->resolver->isValidFormat(str_repeat('a', 20)));
        $this->assertTrue($this->resolver->isValidFormat(str_repeat('a', 64)));
        $this->assertFalse($this->resolver->isValidFormat(str_repeat('a', 65)));
    }

    public function test_rejects_non_alphanumeric_characters(): void
    {
        $this->assertFalse($this->resolver->isValidFormat(str_repeat('a', 19) . '_'));
        $this->assertFalse($this->resolver->isValidFormat(str_repeat('a', 19) . '-'));
    }

    public function test_client_header_token_wins_over_cookie(): void
    {
        $headerToken = str_repeat('h', 40);
        $cookieToken = str_repeat('c', 40);

        $request = Request::create('/', 'GET');
        $request->headers->set(TokenResolver::HEADER, $headerToken);
        $request->cookies->set((string) config('visits.cookie.name'), $cookieToken);

        $this->assertSame($headerToken, $this->resolver->resolve($request));
    }

    public function test_client_body_token_wins_over_cookie(): void
    {
        $bodyToken = str_repeat('b', 40);
        $cookieToken = str_repeat('c', 40);

        $request = Request::create('/', 'POST', [TokenResolver::INPUT_KEY => $bodyToken]);
        $request->cookies->set((string) config('visits.cookie.name'), $cookieToken);

        $this->assertSame($bodyToken, $this->resolver->resolve($request));
    }

    public function test_falls_back_to_cookie_when_no_client_token(): void
    {
        $cookieToken = str_repeat('c', 40);

        $request = Request::create('/', 'GET');
        $request->cookies->set((string) config('visits.cookie.name'), $cookieToken);

        $this->assertSame($cookieToken, $this->resolver->resolve($request));
    }

    public function test_generates_fresh_token_when_nothing_present(): void
    {
        $request = Request::create('/', 'GET');

        $token = $this->resolver->resolve($request);

        $this->assertTrue($this->resolver->isValidFormat($token));
    }

    public function test_malformed_client_token_is_ignored_in_favor_of_cookie(): void
    {
        $cookieToken = str_repeat('c', 40);

        $request = Request::create('/', 'GET');
        $request->headers->set(TokenResolver::HEADER, 'not_a_valid_token!!');
        $request->cookies->set((string) config('visits.cookie.name'), $cookieToken);

        $this->assertSame($cookieToken, $this->resolver->resolve($request));
    }

    public function test_malformed_cookie_token_falls_back_to_generated(): void
    {
        $request = Request::create('/', 'GET');
        $request->cookies->set((string) config('visits.cookie.name'), 'bad token with spaces');

        $token = $this->resolver->resolve($request);

        $this->assertTrue($this->resolver->isValidFormat($token));
        $this->assertNotSame('bad token with spaces', $token);
    }
}
