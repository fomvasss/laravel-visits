<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests\Unit;

use Fomvasss\Visits\Support\SearchTermExtractor;
use Fomvasss\Visits\Tests\TestCase;

class SearchTermExtractorTest extends TestCase
{
    private SearchTermExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new SearchTermExtractor();
    }

    public function test_extracts_keyword_from_google_referrer(): void
    {
        $term = $this->extractor->extract('https://www.google.com/search?q=laravel+tracking&source=hp');

        $this->assertSame('laravel tracking', $term);
    }

    public function test_extracts_keyword_from_bing_referrer(): void
    {
        $term = $this->extractor->extract('https://www.bing.com/search?q=laravel+package');

        $this->assertSame('laravel package', $term);
    }

    public function test_extracts_keyword_from_yahoo_referrer_using_p_param(): void
    {
        $term = $this->extractor->extract('https://search.yahoo.com/search?p=laravel+visits');

        $this->assertSame('laravel visits', $term);
    }

    public function test_returns_null_for_non_search_engine_referrer(): void
    {
        $term = $this->extractor->extract('https://example.test/some/page?q=not+a+search+engine');

        $this->assertNull($term);
    }

    public function test_returns_null_when_search_engine_referrer_has_no_keyword_param(): void
    {
        // HTTPS-to-HTTPS search referrers usually carry no query at all ("keyword not provided")
        $term = $this->extractor->extract('https://www.google.com/');

        $this->assertNull($term);
    }

    public function test_returns_null_for_null_referrer(): void
    {
        $this->assertNull($this->extractor->extract(null));
    }

    public function test_respects_custom_search_engines_config(): void
    {
        config(['visits.search_engines' => ['mysearch.example' => 'kw']]);

        $term = $this->extractor->extract('https://mysearch.example/find?kw=custom+engine');

        $this->assertSame('custom engine', $term);
    }
}
