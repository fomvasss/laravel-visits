<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests\Unit;

use Fomvasss\Visits\Support\IpExcluder;
use Fomvasss\Visits\Tests\TestCase;

class IpExcluderTest extends TestCase
{
    private IpExcluder $excluder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->excluder = new IpExcluder();
    }

    public function test_no_exclusions_configured_excludes_nothing(): void
    {
        $this->assertFalse($this->excluder->isExcluded('203.0.113.10'));
    }

    public function test_matches_literal_ip(): void
    {
        config(['visits.exclude_ips' => ['203.0.113.10']]);

        $this->assertTrue($this->excluder->isExcluded('203.0.113.10'));
        $this->assertFalse($this->excluder->isExcluded('203.0.113.11'));
    }

    public function test_matches_ipv4_cidr_range(): void
    {
        config(['visits.exclude_ips' => ['198.51.100.0/24']]);

        $this->assertTrue($this->excluder->isExcluded('198.51.100.42'));
        $this->assertFalse($this->excluder->isExcluded('198.51.101.1'));
    }

    public function test_matches_ipv4_cidr_range_with_non_octet_aligned_bits(): void
    {
        config(['visits.exclude_ips' => ['198.51.100.0/28']]);

        $this->assertTrue($this->excluder->isExcluded('198.51.100.15'));
        $this->assertFalse($this->excluder->isExcluded('198.51.100.16'));
    }

    public function test_matches_ipv6_cidr_range(): void
    {
        config(['visits.exclude_ips' => ['2001:db8::/32']]);

        $this->assertTrue($this->excluder->isExcluded('2001:db8::1'));
        $this->assertFalse($this->excluder->isExcluded('2001:db9::1'));
    }

    public function test_null_ip_is_never_excluded(): void
    {
        config(['visits.exclude_ips' => ['203.0.113.10']]);

        $this->assertFalse($this->excluder->isExcluded(null));
    }
}
