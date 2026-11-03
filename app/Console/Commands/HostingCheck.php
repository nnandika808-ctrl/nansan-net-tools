<?php

namespace App\Console\Commands;

use App\Services\HostingReadiness;
use Illuminate\Console\Command;

class HostingCheck extends Command
{
    protected $signature = 'nansan:hosting-check {--production : Require production configuration} {--runtime : Probe database, Redis and background heartbeats}';

    protected $description = 'Report hosting prerequisites without exposing secrets or changing configuration';

    public function handle(HostingReadiness $readiness): int
    {
        $checks = $readiness->check((bool) $this->option('production'), (bool) $this->option('runtime'));
        $failed = in_array(false, array_column($checks, 'passed'), true);
        $this->line(json_encode(['ready' => ! $failed, 'scope' => 'hosting_prerequisites_only', 'checks' => $checks], JSON_THROW_ON_ERROR));

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
