<?php

namespace App\Generators;

use App\Generators\Modules\PccModule;
use App\Generators\Modules\StarlinkModule;

final class GeneratorRegistry
{
    public const IDS = ['G01', 'G02', 'G03', 'G04', 'G05'];

    // Publication is a reviewed code change; database metadata cannot enable execution.
    private const PUBLISHED = [];

    public function module(string $id): GeneratorModule
    {
        abort_unless(in_array($id, self::IDS, true), 404);

        return match ($id) {
            'G03' => new PccModule,
            'G04' => new StarlinkModule,
            default => new UnverifiedGenerator($id),
        };
    }

    public function isPublished(string $id): bool
    {
        return in_array($id, self::PUBLISHED, true);
    }
}
