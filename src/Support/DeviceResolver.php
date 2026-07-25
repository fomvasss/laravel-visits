<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Support;

use DeviceDetector\DeviceDetector;

/**
 * Single detection pass via matomo/device-detector covering device/browser/OS AND bot
 * detection (name + category) — replaces the separate hisorange/browser-detect +
 * jaybizzle/crawler-detect combo the legacy projects would have needed.
 *
 * Runs first inside RecordVisitJob, before geo lookup, so bot traffic never pays for it.
 *
 * client_type distinguishes real browsers from mobile apps / HTTP libraries / feed readers —
 * matomo classifies plenty of that traffic as non-bot, so this is orthogonal to is_bot, not a
 * replacement for it.
 */
class DeviceResolver
{
    /**
     * @return array<string, mixed>
     */
    public function resolve(string $userAgent): array
    {
        $dd = new DeviceDetector($userAgent);
        $dd->parse();

        if ($dd->isBot()) {
            $bot = $dd->getBot();

            return [
                'is_bot' => true,
                'bot_name' => $bot['name'] ?? null,
                'bot_category' => $bot['category'] ?? null,
                'device_type' => null,
                'device_family' => null,
                'device_model' => null,
                'platform' => null,
                'platform_version' => null,
                'browser' => null,
                'browser_version' => null,
                'browser_engine' => null,
                'client_type' => null,
            ];
        }

        return [
            'is_bot' => false,
            'bot_name' => null,
            'bot_category' => null,
            'device_type' => $dd->getDeviceName() ?: null,
            'device_family' => $dd->getBrandName() ?: null,
            'device_model' => $dd->getModel() ?: null,
            'platform' => $dd->getOs('name') ?: null,
            'platform_version' => $dd->getOs('version') ?: null,
            'browser' => $dd->getClient('name') ?: null,
            'browser_version' => $dd->getClient('version') ?: null,
            'browser_engine' => $dd->getClient('engine') ?: null,
            'client_type' => $dd->getClient('type') ?: null,
        ];
    }
}
