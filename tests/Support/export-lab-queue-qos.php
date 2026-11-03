<?php

use App\Generators\Modules\QueueQosModule;
use App\Generators\StaticRoutingDatasets;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$module = new QueueQosModule;
$source = json_decode(file_get_contents(base_path('docs/reference/evidence/G02-cases-semantics.json')), true, 512, JSON_THROW_ON_ERROR);
$cases = [];
foreach ($source['cases'] as $case) {
    if ($case['input']['Queue-List'] === 'Shopify') {
        continue;
    }
    $data = $module->validate($case['input']);
    $output = $module->generate($data);
    $cases[] = ['id' => $case['test_id'], 'application' => $data['Queue-List'], 'queue_mode' => $data['Queue-Options'], 'input' => $data, 'dataset_sha256' => app(StaticRoutingDatasets::class)->artifact($data['Queue-List'], $data['match-condition'])['sha256'], 'script' => $output->steps[1]['script']];
}
file_put_contents(base_path('.runtime/queue-qos-lab-cases.json'), json_encode($cases, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo count($cases)." Queue QoS wrapper cases exported; source data import evidence is shared with G01.\n";
