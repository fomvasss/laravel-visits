<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Http\Controllers;

use Fomvasss\Visits\VisitsManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Public, read-only "what do you see about me" endpoint — an ifconfig.me-style utility.
 * No Visitor/Session/Event is written and no cookie is set; safe to call from any other
 * project/service that just wants IP/geo/device/UTM detection without adopting the whole
 * tracking pipeline. Same data as Visits::whoami(), for callers that can't use PHP directly.
 */
class WhoAmIController extends Controller
{
    public function __invoke(Request $request, VisitsManager $visits): JsonResponse
    {
        $ip = $request->input('ip');
        $ip = is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;

        return response()->json($visits->whoami($request, $ip));
    }
}
