<?php

declare(strict_types=1);

namespace Fomvasss\Visits\Console;

use Fomvasss\Visits\Models\Scopes\WithoutBotsScope;
use Fomvasss\Visits\Support\ModelResolver;
use Illuminate\Console\Command;

class CloseStaleSessionsCommand extends Command
{
    protected $signature = 'visits:close-stale-sessions';

    protected $description = 'Close visit sessions whose last activity is older than the configured timeout';

    public function handle(): int
    {
        $timeoutMinutes = (int) config('visits.session_timeout_minutes', 30);
        $cutoff = now()->subMinutes($timeoutMinutes);
        $count = 0;
        $sessionClass = ModelResolver::session();

        $sessionClass::withoutGlobalScope(WithoutBotsScope::class)
            ->whereNull('ended_at')
            ->where('last_activity_at', '<', $cutoff)
            ->chunkById(500, function ($sessions) use (&$count) {
                foreach ($sessions as $session) {
                    $session->update([
                        'ended_at' => $session->last_activity_at,
                        'duration_seconds' => $session->started_at->diffInSeconds($session->last_activity_at),
                    ]);
                    $count++;
                }
            });

        $this->info("Closed {$count} stale session(s).");

        return self::SUCCESS;
    }
}
