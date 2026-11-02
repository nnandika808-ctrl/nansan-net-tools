<?php

use App\Generators\Modules\StarlinkModule;
use Illuminate\Contracts\Console\Kernel;
use Tests\Feature\StarlinkRegressionTest;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$module = new StarlinkModule;
$cases = array_values(array_filter(StarlinkRegressionTest::scenarios(), fn ($case) => (int) $case['input']['count'] === 2));
$cases[] = ['id' => 'ros7-two-netwatch-protocol', 'input' => ['ros' => 'v7', 'count' => 2, 'netwatchEnable' => true, 'netwatchTargets' => ''], 'basis' => 'Additional own-protocol combination; not a new external reference projection'];
$output = [];
foreach ($cases as $case) {
    $data = $module->validate($case['input']);
    $result = $module->generate($case['input']);
    $output[] = ['id' => $case['id'], 'ros_version' => $result->rosVersion, 'script' => $result->text(), 'wan_count' => 2, 'netwatch' => (bool) $data['netwatchEnable'], 'basis' => $case['basis'] ?? 'Previously observed input combination'];
}
file_put_contents(base_path('.runtime/starlink-protocol-lab-cases.json'), json_encode($output, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo count($output)." independent Starlink protocol cases exported.\n";
