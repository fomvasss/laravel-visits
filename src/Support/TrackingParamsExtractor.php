<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Support;

use Illuminate\Http\Request;

/**
 * Captures UTM/ref (core, indexed columns) + click-ids and unknown-but-matched params
 * (extra_params json bucket) from the current request's query string.
 *
 * Config-driven, no migration needed to support a new click-id or campaign param —
 * see config('visits.tracking_params').
 */
class TrackingParamsExtractor
{
    /**
     * @return array<string, string> column => value, only for params actually present
     */
    public function extractCore(Request $request): array
    {
        $core = [];

        foreach ((array) config('visits.tracking_params.core', []) as $column => $param) {
            $value = $request->query($param);

            if (is_string($value) && $value !== '') {
                $core[$column] = $value;
            }
        }

        return $core;
    }

    /**
     * @return array<string, string>
     */
    public function extractExtra(Request $request): array
    {
        $extra = [];

        foreach ((array) config('visits.tracking_params.extra_keys', []) as $key) {
            $value = $request->query($key);

            if (is_string($value) && $value !== '') {
                $extra[$key] = $value;
            }
        }

        $pattern = config('visits.tracking_params.extra_pattern');

        if (is_string($pattern) && $pattern !== '') {
            $coreParams = array_values((array) config('visits.tracking_params.core', []));

            foreach ($request->query() as $key => $value) {
                if (! is_string($value) || $value === '') {
                    continue;
                }

                if (in_array($key, $coreParams, true) || array_key_exists($key, $extra)) {
                    continue;
                }

                if (@preg_match($pattern, (string) $key) === 1) {
                    $extra[$key] = $value;
                }
            }
        }

        return $extra;
    }
}
