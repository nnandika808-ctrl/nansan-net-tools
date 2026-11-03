<?php

use App\Generators\Modules\StaticRoutingModule;
use App\Generators\StaticRoutingDatasets;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$module = new StaticRoutingModule;
$datasets = new StaticRoutingDatasets;
$cases = [];
foreach ($datasets->manifest()['datasets'] as $entry) {
    if (! isset($entry['parameter_file'])) {
        continue;
    }
    foreach (['ros6', 'ros7'] as $ros) {
        $input = ['routing-list' => $entry['application'], 'match-condition' => $entry['mode'], 'ros-list' => $ros];
        $output = $module->generate($input);
        $cases[] = ['id' => $ros.'-'.strtolower($entry['application']).'-'.$entry['mode'], 'ros_version' => $output->rosVersion, 'application' => $entry['application'], 'mode' => $entry['mode'], 'filename' => 'Nansan-'.$entry['application'].'-'.$entry['mode'].'.rsc', 'dataset' => $datasets->artifact($entry['application'], $entry['mode'])['script'], 'step2' => $output->steps[1]['script']];
    }
}
file_put_contents(base_path('.runtime/static-routing-lab-cases.json'), json_encode($cases, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo count($cases)." Static Routing dataset/import cases exported.\n";
