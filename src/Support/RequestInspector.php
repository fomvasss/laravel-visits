<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Support;

use Illuminate\Http\Request;

/**
 * Read-only "what do we see about you right now" snapshot — same detection pipeline
 * RecordVisitJob uses (device/bot, geo, locale, tracking params), but nothing is written
 * anywhere: no Visitor/Session/Event row, no cookie. Powers both the public whoami JSON
 * endpoint and the dashboard's "Who am I" page.
 */
class RequestInspector
{
    public function __construct(
        private readonly DeviceResolver $deviceResolver,
        private readonly GeoResolver $geoResolver,
        private readonly LocaleResolver $localeResolver,
        private readonly TrackingParamsExtractor $paramsExtractor,
    ) {
    }

    /**
     * @param  string|null  $ipOverride  look up geo for this IP instead of the request's own —
     *   device/locale/tracking_params still reflect the real request, only geo (and the
     *   reported 'ip') changes, since there's nothing meaningful to simulate for someone else's
     *   device/browser
     * @return array<string, mixed>
     */
    public function inspect(Request $request, ?string $ipOverride = null): array
    {
        $ip = $ipOverride ?: (string) $request->ip();
        $userAgent = (string) $request->userAgent();

        $device = $this->deviceResolver->resolve($userAgent);
        $geo = $this->geoResolver->resolve($ip);
        $locale = $this->localeResolver->resolve($request);

        return [
            'ip' => $ip,
            // read-only — the client-supplied header/cookie value as-is, never generated,
            // since nothing here gets persisted for it to be an identity for
            'visitor_token' => $this->currentToken($request),
            'user_agent' => $userAgent,
            'bot' => [
                'is_bot' => $device['is_bot'],
                'bot_name' => $device['bot_name'],
                'bot_category' => $device['bot_category'],
            ],
            'device' => [
                'device_type' => $device['device_type'],
                'device_family' => $device['device_family'],
                'device_model' => $device['device_model'],
                'platform' => $device['platform'],
                'platform_version' => $device['platform_version'],
                'browser' => $device['browser'],
                'browser_version' => $device['browser_version'],
                'browser_engine' => $device['browser_engine'],
                'client_type' => $device['client_type'],
            ],
            'geo' => $geo,
            'locale' => $locale,
            'referrer' => $request->header('referer'),
            'tracking_params' => [
                'utm' => $this->paramsExtractor->extractCore($request),
                'extra' => $this->paramsExtractor->extractExtra($request),
            ],
        ];
    }

    private function currentToken(Request $request): ?string
    {
        $clientToken = $request->header(TokenResolver::HEADER) ?: $request->input(TokenResolver::INPUT_KEY);

        if (is_string($clientToken) && $clientToken !== '') {
            return $clientToken;
        }

        return $request->cookie((string) config('visits.cookie.name'));
    }
}
