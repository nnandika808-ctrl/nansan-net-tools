<?php

namespace App\Http\Controllers;

use App\Generators\GeneratorRegistry;
use App\Generators\StaticRoutingDatasets;

final class StaticRoutingDatasetController extends Controller
{
    public function __invoke(string $digest, StaticRoutingDatasets $datasets, GeneratorRegistry $registry)
    {
        abort_unless(app()->environment(['local', 'testing', 'staging']) || $registry->isPublished('G01'), 404);
        foreach ($datasets->manifest()['datasets'] as $entry) {
            if (($entry['artifact_sha256'] ?? null) !== $digest) {
                continue;
            }
            $artifact = $datasets->artifact($entry['application'], $entry['mode']);
            if (hash_equals($artifact['sha256'], $digest)) {
                return response($artifact['script'])->header('Content-Type', 'text/plain; charset=UTF-8')->header('Content-Disposition', 'attachment; filename="Nansan-'.$entry['application'].'-'.$entry['mode'].'.rsc"')->header('X-Robots-Tag', 'noindex, nofollow')->header('Cache-Control', 'public, max-age=31536000, immutable')->header('ETag', '"'.$digest.'"');
            }
        }
        abort(404);
    }
}
