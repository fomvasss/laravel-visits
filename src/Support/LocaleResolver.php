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
            'browser_language' => $this->browserLanguage($request),
        ];
    }

    /**
     * First real language from Accept-Language. `*` ("any language" — sent by HTTP clients and
     * some embedded webviews) is not a language, and a tag longer than the column would fail the
     * whole visit on insert, so both are skipped in favour of the next entry.
     */
    protected function browserLanguage(Request $request): ?string
    {
        foreach ($request->getLanguages() as $language) {
            if (strlen($language) <= 10 && preg_match('/^[a-z]{2,3}(_[a-z0-9]{2,8})*$/i', $language)) {
                return $language;
            }
        }

        return null;
    }
}
