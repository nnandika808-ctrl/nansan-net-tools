<?php

use App\Generators\StaticRoutingDatasets;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$datasets = new StaticRoutingDatasets;
$manifest = $datasets->manifest();
foreach ($manifest['datasets'] as &$entry) {
    if (isset($entry['parameter_file'])) {
        $entry['artifact_sha256'] = $datasets->artifact($entry['application'], $entry['mode'])['sha256'];
    }
}
unset($entry);
file_put_contents(resource_path('datasets/static-routing/manifest.json'), json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
echo "Indexed reviewed parameter artifacts by compiled SHA-256.\n";
