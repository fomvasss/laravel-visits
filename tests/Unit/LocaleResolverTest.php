<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests\Unit;

use Fomvasss\Visits\Support\LocaleResolver;
use Fomvasss\Visits\Tests\TestCase;
use Illuminate\Http\Request;

class LocaleResolverTest extends TestCase
{
    public function test_resolves_app_locale_and_browser_language(): void
    {
        app()->setLocale('uk');

        $request = Request::create('/');
        $request->headers->set('Accept-Language', 'de-DE,de;q=0.9,en;q=0.8');

        $result = (new LocaleResolver())->resolve($request);

        $this->assertSame('uk', $result['locale']);
        $this->assertSame('de_DE', $result['browser_language']);
    }

    public function test_browser_language_is_null_without_accept_language_header(): void
    {
        $request = Request::create('/');
        $request->headers->remove('Accept-Language');

        $result = (new LocaleResolver())->resolve($request);

        $this->assertNull($result['browser_language']);
    }
}
