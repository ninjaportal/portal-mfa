<?php

namespace NinjaPortal\Mfa\Console\Commands;

use Illuminate\Console\Command;
use NinjaPortal\Mfa\Services\PruneMfaChallengesService;

class PruneMfaChallengesCommand extends Command
{
    protected $signature = 'portal-mfa:challenges:prune';

    protected $description = 'Prune expired MFA challenges';

    public function handle(PruneMfaChallengesService $service): int
    {
        $count = $service->handle();
        $this->info(sprintf('Pruned %d MFA challenge record(s).', $count));

        return self::SUCCESS;
    }
}
