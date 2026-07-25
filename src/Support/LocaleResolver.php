<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Support;

use Illuminate\Http\Request;

/**
 * No external calls, so unlike geo it makes no real difference whether this runs in
 * middleware or in the job — kept here so RecordVisitJob's raw-payload input stays uniform.
 */
class LocaleResolver
{
    /**
     * @return array{locale: ?string, browser_language: ?string}
     */
    public function resolve(Request $request): array
    {
        return [
            'locale' => app()->getLocale() ?: null,
            'browser_language' => $request->getLanguages()[0] ?? null,
        ];
    }
}
