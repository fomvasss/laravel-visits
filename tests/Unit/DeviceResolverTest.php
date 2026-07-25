<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Tests\Unit;

use Fomvasss\Visits\Support\DeviceResolver;
use Fomvasss\Visits\Tests\TestCase;

class DeviceResolverTest extends TestCase
{
    private const DESKTOP_CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    private const ANDROID_CHROME = 'Mozilla/5.0 (Linux; Android 14; SM-S911B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.6099.144 Mobile Safari/537.36';

    private const GOOGLEBOT = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

    public function test_resolves_desktop_browser_details(): void
    {
        $device = (new DeviceResolver())->resolve(self::DESKTOP_CHROME);

        $this->assertFalse($device['is_bot']);
        $this->assertSame('desktop', $device['device_type']);
        $this->assertSame('Windows', $device['platform']);
        $this->assertSame('Chrome', $device['browser']);
        $this->assertSame('browser', $device['client_type']);
        $this->assertNotNull($device['browser_version']);
        $this->assertNotNull($device['browser_engine']);
    }

    public function test_resolves_mobile_device_details(): void
    {
        $device = (new DeviceResolver())->resolve(self::ANDROID_CHROME);

        $this->assertFalse($device['is_bot']);
        $this->assertSame('smartphone', $device['device_type']);
        $this->assertSame('Android', $device['platform']);
    }

    public function test_detects_bot_and_skips_device_fields(): void
    {
        $device = (new DeviceResolver())->resolve(self::GOOGLEBOT);

        $this->assertTrue($device['is_bot']);
        $this->assertSame('Googlebot', $device['bot_name']);
        $this->assertNotNull($device['bot_category']);
        $this->assertNull($device['device_type']);
        $this->assertNull($device['platform']);
        $this->assertNull($device['browser']);
        $this->assertNull($device['client_type']);
    }

    public function test_handles_empty_user_agent_gracefully(): void
    {
        $device = (new DeviceResolver())->resolve('');

        $this->assertIsArray($device);
        $this->assertArrayHasKey('is_bot', $device);
    }
}
