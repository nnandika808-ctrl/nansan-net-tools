<?php

namespace App\Generators;

use App\Generators\Modules\BurstCalculatorModule;
use App\Generators\Modules\PccModule;
use App\Generators\Modules\QueueQosModule;
use App\Generators\Modules\RoutingGamesModule;
use App\Generators\Modules\SimpleQueueTreeModule;
use App\Generators\Modules\StarlinkModule;
use App\Generators\Modules\StaticRoutingModule;

final class GeneratorRegistry
{
    public const IDS = ['G01', 'G02', 'G03', 'G04', 'G05', 'G06', 'G07'];

    // Publication is a reviewed code change; database metadata cannot enable execution.
    private const PUBLISHED = [];

    public function module(string $id): GeneratorModule
    {
        abort_unless(in_array($id, self::IDS, true), 404);

        return match ($id) {
            'G01' => new StaticRoutingModule,
            'G02' => new QueueQosModule,
            'G03' => new PccModule,
            'G04' => new StarlinkModule,
            'G05' => new RoutingGamesModule,
            'G06' => new SimpleQueueTreeModule,
            'G07' => new BurstCalculatorModule,
            default => new UnverifiedGenerator($id),
        };
    }

    public function isPublished(string $id): bool
    {
        return in_array($id, self::PUBLISHED, true);
    }
}
