<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Jobs;

use Fomvasss\Visits\DTO\VisitPayload;
use Fomvasss\Visits\Events\ConversionRecorded;
use Fomvasss\Visits\Events\SessionStarted;
use Fomvasss\Visits\Events\VisitorCreated;
use Fomvasss\Visits\Events\VisitRecorded;
use Fomvasss\Visits\Models\Event;
use Fomvasss\Visits\Models\Scopes\WithoutBotsScope;
use Fomvasss\Visits\Models\Session;
use Fomvasss\Visits\Models\Visitor;
use Fomvasss\Visits\Support\DeviceResolver;
use Fomvasss\Visits\Support\GeoResolver;
use Fomvasss\Visits\Support\IpExcluder;
use Fomvasss\Visits\Support\ModelResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Everything expensive lives here, off the request/response cycle: excluded-IP check first
 * (cheapest, most decisive), then bot detection (before geo, so bot traffic never pays for it),
 * then the per-visitor event budget, then geo, then the actual Visitor/Session/Event writes.
 */
class RecordVisitJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(public readonly VisitPayload $payload)
    {
    }

    public function handle(DeviceResolver $deviceResolver, GeoResolver $geoResolver, IpExcluder $ipExcluder): void
    {
        if ($ipExcluder->isExcluded($this->payload->ip)) {
            return;
        }

        $device = $deviceResolver->resolve($this->payload->userAgent);

        if ($this->isOverBudget()) {
            return;
        }

        // no point paying for a geo lookup on bot traffic
        $geo = $device['is_bot'] ? [] : $geoResolver->resolve((string) $this->payload->ip);

        $visitor = $this->resolveVisitor($device, $geo);
        $session = $this->resolveSession($visitor, $device, $geo);

        $event = $this->createEvent($visitor, $session, $device);

        if ($this->payload->type === Event::TYPE_PAGE_VIEW) {
            $session->increment('page_views_count');
        }

        $session->update(['last_activity_at' => now()]);
        $visitor->update(['last_seen_at' => now()]);

        VisitRecorded::dispatch($event);

        if ($this->payload->type === Event::TYPE_ACTION && $this->payload->eventableType) {
            ConversionRecorded::dispatch($event);
        }
    }

    private function isOverBudget(): bool
    {
        [$max, $decayMinutes] = $this->parseLimit((string) config('visits.rate_limit.visitor_budget', '120,1'));

        $allowed = RateLimiter::attempt(
            'visits:budget:' . $this->payload->token,
            $max,
            static fn () => true,
            $decayMinutes * 60,
        );

        return ! $allowed;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function parseLimit(string $limit): array
    {
        [$max, $decayMinutes] = array_pad(explode(',', $limit), 2, '1');

        return [(int) $max, (int) $decayMinutes];
    }

    /**
     * @param  array<string, mixed>  $device
     * @param  array<string, mixed>  $geo
     */
    private function resolveVisitor(array $device, array $geo): Visitor
    {
        $visitorClass = ModelResolver::visitor();

        /** @var Visitor $visitor */
        $visitor = $visitorClass::withoutGlobalScope(WithoutBotsScope::class)
            ->firstOrNew(['token' => $this->payload->token]);

        $isNew = ! $visitor->exists;

        $visitor->fill([
            'is_bot' => $device['is_bot'],
            // last-known, mutable — overwritten every time, unlike UTM below
            'country_code' => $geo['country_code'] ?? $visitor->country_code,
            'region' => $geo['region'] ?? $visitor->region,
            'city' => $geo['city'] ?? $visitor->city,
            'timezone' => $geo['timezone'] ?? $visitor->timezone,
            'lat' => $geo['lat'] ?? $visitor->lat,
            'lng' => $geo['lng'] ?? $visitor->lng,
            'geo_meta' => $this->geoMeta($geo) ?? $visitor->geo_meta,
            'locale' => $this->payload->locale,
            'browser_language' => $this->payload->browserLanguage,
            'device_type' => $device['device_type'],
            'platform' => $device['platform'],
            'browser' => $device['browser'],
            'client_type' => $device['client_type'],
            'device_meta' => $this->deviceMeta($device),
        ]);

        if ($isNew) {
            $visitor->fill([
                'first_seen_at' => now(),
                'last_seen_at' => now(),
                'first_landing_url' => $this->payload->url,
                'first_referrer_url' => $this->payload->referrer,
                'first_referrer_host' => $this->referrerHost(),
                'search_term' => $this->payload->searchTerm,
                'extra_params' => $this->payload->extraParams !== [] ? $this->payload->extraParams : null,
                ...$this->prefixedUtm($this->payload->utm),
            ]);
        }

        if ($this->payload->authUserType && $this->payload->authUserId !== null) {
            $visitor->user_type = $this->payload->authUserType;
            $visitor->user_id = $this->payload->authUserId;
        }

        $visitor->save();

        if ($isNew) {
            VisitorCreated::dispatch($visitor);
        }

        return $visitor;
    }

    /**
     * @param  array<string, mixed>  $device
     * @param  array<string, mixed>  $geo
     */
    private function resolveSession(Visitor $visitor, array $device, array $geo): Session
    {
        $timeoutMinutes = (int) config('visits.session_timeout_minutes', 30);
        $sessionClass = ModelResolver::session();

        $session = $sessionClass::withoutGlobalScope(WithoutBotsScope::class)
            ->where('visitor_id', $visitor->id)
            ->whereNull('ended_at')
            ->where('last_activity_at', '>=', now()->subMinutes($timeoutMinutes))
            ->latest('last_activity_at')
            ->first();

        if ($session) {
            // sticky: once any request in the session looks like a bot, the whole session is
            // treated as one — never flips back to false. Persisted by the update() call in handle().
            if ($device['is_bot'] && ! $session->is_bot) {
                $session->is_bot = true;
            }

            return $session;
        }

        $utm = $this->payload->utm !== [] ? $this->payload->utm : $this->visitorUtm($visitor);
        $extraParams = $this->payload->extraParams !== [] ? $this->payload->extraParams : ($visitor->extra_params ?? []);

        $session = $sessionClass::create([
            'visitor_id' => $visitor->id,
            'user_type' => $this->payload->authUserType,
            'user_id' => $this->payload->authUserId,
            'started_at' => now(),
            'last_activity_at' => now(),
            'landing_url' => $this->payload->url,
            'referrer_url' => $this->payload->referrer,
            'referrer_host' => $this->referrerHost(),
            'search_term' => $this->payload->searchTerm ?? $visitor->search_term,
            'extra_params' => $extraParams !== [] ? $extraParams : null,
            'ip' => $this->payload->ip,
            'country_code' => $geo['country_code'] ?? null,
            'region' => $geo['region'] ?? null,
            'city' => $geo['city'] ?? null,
            'timezone' => $geo['timezone'] ?? null,
            'lat' => $geo['lat'] ?? null,
            'lng' => $geo['lng'] ?? null,
            'geo_meta' => $this->geoMeta($geo),
            'locale' => $this->payload->locale,
            'browser_language' => $this->payload->browserLanguage,
            'device_type' => $device['device_type'],
            'platform' => $device['platform'],
            'browser' => $device['browser'],
            'client_type' => $device['client_type'],
            'device_meta' => $this->deviceMeta($device),
            'user_agent' => $this->payload->userAgent,
            'is_bot' => $device['is_bot'],
            ...$this->prefixedUtm($utm),
        ]);

        SessionStarted::dispatch($session);

        return $session;
    }

    /**
     * @param  array<string, mixed>  $device
     * @return array<string, mixed>|null
     */
    private function deviceMeta(array $device): ?array
    {
        $meta = array_filter([
            'device_family' => $device['device_family'] ?? null,
            'device_model' => $device['device_model'] ?? null,
            'platform_version' => $device['platform_version'] ?? null,
            'browser_version' => $device['browser_version'] ?? null,
            'browser_engine' => $device['browser_engine'] ?? null,
        ]);

        return $meta !== [] ? $meta : null;
    }

    /**
     * @param  array<string, mixed>  $geo
     * @return array<string, mixed>|null
     */
    private function geoMeta(array $geo): ?array
    {
        $meta = array_filter([
            'country_name' => $geo['country_name'] ?? null,
            'currency_code' => $geo['currency_code'] ?? null,
            'region_code' => $geo['region_code'] ?? null,
            'zip_code' => $geo['zip_code'] ?? null,
            'postal_code' => $geo['postal_code'] ?? null,
            'metro_code' => $geo['metro_code'] ?? null,
            'area_code' => $geo['area_code'] ?? null,
            'driver' => $geo['driver'] ?? null,
        ]);

        return $meta !== [] ? $meta : null;
    }

    private function createEvent(Visitor $visitor, Session $session, array $device): Event
    {
        $eventClass = ModelResolver::event();

        return $eventClass::create([
            'session_id' => $session->id,
            'visitor_id' => $visitor->id,
            'type' => $this->payload->type,
            'name' => $this->payload->name,
            'url' => $this->payload->url,
            'path' => $this->payload->url ? parse_url($this->payload->url, PHP_URL_PATH) : null,
            'route_name' => $this->payload->routeName,
            'is_bot' => $device['is_bot'],
            'bot_name' => $device['bot_name'],
            'bot_category' => $device['bot_category'],
            'eventable_type' => $this->payload->eventableType,
            'eventable_id' => $this->payload->eventableId,
            'meta' => $this->payload->meta,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function visitorUtm(Visitor $visitor): array
    {
        return [
            'utm_source' => $visitor->utm_source,
            'utm_medium' => $visitor->utm_medium,
            'utm_campaign' => $visitor->utm_campaign,
            'utm_term' => $visitor->utm_term,
            'utm_content' => $visitor->utm_content,
            'ref' => $visitor->ref,
        ];
    }

    /**
     * $utm is keyed by column name already (utm_source, utm_medium, ..., ref) — see
     * config('visits.tracking_params.core'). Only known columns pass through.
     *
     * @param  array<string, mixed>  $utm
     * @return array<string, mixed>
     */
    private function prefixedUtm(array $utm): array
    {
        $columns = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'ref'];

        return array_intersect_key($utm, array_flip($columns));
    }

    private function referrerHost(): ?string
    {
        if (! $this->payload->referrer) {
            return null;
        }

        return parse_url($this->payload->referrer, PHP_URL_HOST) ?: null;
    }
}
