<?php

declare(strict_types=1);

namespace Fomvasss\Visits\DTO;

/**
 * Everything RecordVisitJob needs, captured synchronously and cheaply (no I/O) before
 * dispatch — geo/device/bot detection happen later, inside the job.
 */
readonly class VisitPayload
{
    public function __construct(
        public string $token,
        public string $type,
        public ?string $name,
        public ?string $url,
        public ?string $ip,
        public string $userAgent,
        public ?string $referrer,
        public array $utm,
        public array $extraParams,
        public ?string $locale,
        public ?string $browserLanguage,
        public ?string $authUserType,
        public int|string|null $authUserId,
        public ?string $eventableType,
        public int|string|null $eventableId,
        public ?array $meta,
    ) {
    }
}
