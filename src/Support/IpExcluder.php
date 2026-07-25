<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Support;

/**
 * config('visits.exclude_ips') — literal IPs and/or CIDR ranges (IPv4 or IPv6) never tracked,
 * e.g. the office/internal network. Checked centrally in RecordVisitJob rather than duplicated
 * in TrackVisit/CollectController/VisitsManager, so it applies uniformly regardless of which
 * entry point produced the request (automatic page view, JS beacon, or a server-side
 * Visits::track() call).
 */
class IpExcluder
{
    public function isExcluded(?string $ip): bool
    {
        if (! $ip) {
            return false;
        }

        foreach ((array) config('visits.exclude_ips', []) as $pattern) {
            if ($this->matches($ip, (string) $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function matches(string $ip, string $pattern): bool
    {
        if (! str_contains($pattern, '/')) {
            return $ip === $pattern;
        }

        [$subnet, $bits] = explode('/', $pattern, 2);

        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $bits = (int) $bits;
        $bytes = intdiv($bits, 8);
        $remainderBits = $bits % 8;

        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
            return false;
        }

        if ($remainderBits === 0) {
            return true;
        }

        $mask = chr((0xFF << (8 - $remainderBits)) & 0xFF);

        return (substr($ipBin, $bytes, 1) & $mask) === (substr($subnetBin, $bytes, 1) & $mask);
    }
}
