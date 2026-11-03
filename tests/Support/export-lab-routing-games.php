<?php

use App\Generators\Modules\RoutingGamesModule;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\HttpKernel\Exception\HttpException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$source = json_decode(file_get_contents(base_path('docs/reference/evidence/G05-cases-semantics.json')), true, 512, JSON_THROW_ON_ERROR);
$cases = [];
foreach ($source['cases'] as $case) {
    $input = $case['input'];
    foreach ($case['selected'] as $id) {
        $input[$id] = true;
    }
    try {
        $module = new RoutingGamesModule;
        $data = $module->validate($input);
        $output = $module->generate($data);
        $cases[] = ['id' => $case['test_id'], 'ros_version' => $output->rosVersion, 'input' => $input, 'selected' => $case['selected'], 'raw_rules' => count(array_filter($module->configuration($data), fn ($entry) => $entry['path'] === '/ip firewall raw')), 'script' => $output->text()];
    } catch (HttpException $error) {
        if ($error->getStatusCode() !== 423) {
            throw $error;
        }
    }
}
file_put_contents(base_path('.runtime/routing-games-lab-cases.json'), json_encode($cases, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo count($cases)." Routing Games syntax cases exported.\n";
