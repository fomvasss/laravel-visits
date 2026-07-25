<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests\Unit;

use Fomvasss\Visits\Support\TrackingParamsExtractor;
use Fomvasss\Visits\Tests\TestCase;
use Illuminate\Http\Request;

class TrackingParamsExtractorTest extends TestCase
{
    private TrackingParamsExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new TrackingParamsExtractor();
    }

    public function test_extracts_only_present_core_params(): void
    {
        $request = Request::create('/?utm_source=google&utm_medium=cpc');

        $core = $this->extractor->extractCore($request);

        $this->assertSame(['utm_source' => 'google', 'utm_medium' => 'cpc'], $core);
    }

    public function test_core_extraction_ignores_empty_values(): void
    {
        $request = Request::create('/?utm_source=&utm_medium=cpc');

        $core = $this->extractor->extractCore($request);

        $this->assertArrayNotHasKey('utm_source', $core);
        $this->assertSame('cpc', $core['utm_medium']);
    }

    public function test_extracts_configured_extra_keys(): void
    {
        $request = Request::create('/?gclid=abc123&fbclid=xyz789&unrelated=1');

        $extra = $this->extractor->extractExtra($request);

        $this->assertSame(['gclid' => 'abc123', 'fbclid' => 'xyz789'], $extra);
    }

    public function test_extra_pattern_captures_matching_unknown_params(): void
    {
        config(['visits.tracking_params.extra_pattern' => '/^custom_/']);

        $request = Request::create('/?custom_foo=1&custom_bar=2&irrelevant=3');

        $extra = $this->extractor->extractExtra($request);

        $this->assertSame(['custom_foo' => '1', 'custom_bar' => '2'], $extra);
    }

    public function test_extra_pattern_disabled_by_default(): void
    {
        $request = Request::create('/?custom_foo=1');

        $extra = $this->extractor->extractExtra($request);

        $this->assertSame([], $extra);
    }

    public function test_extra_pattern_does_not_duplicate_core_params(): void
    {
        config(['visits.tracking_params.extra_pattern' => '/^utm_/']);

        $request = Request::create('/?utm_source=google');

        $extra = $this->extractor->extractExtra($request);

        $this->assertArrayNotHasKey('utm_source', $extra);
    }
}
