<?php

namespace Tests\Feature;

use App\Generators\Modules\StarlinkModule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class StarlinkRegressionTest extends TestCase
{
    public static function scenarios(): array
    {
        $observed = json_decode(file_get_contents(dirname(__DIR__, 2).'/docs/reference/evidence/G04-cases-semantics.json'), true, 512, JSON_THROW_ON_ERROR);

        return array_map(function ($case) {
            $input = $case['input'];
            $data = ['ros' => $input['ros'], 'count' => $input['count'], 'mode' => $input['mode'], 'netwatchEnable' => $input['netwatch'], 'netwatchTargets' => $input['targets']];
            foreach ($input['interfaces'] as $index => $interface) {
                $data['connection-'.(2 * $index + 1)] = $interface;
                $data['connection-'.(2 * $index + 2)] = $input['weights'][$index];
            }

            return ['id' => $case['test_id'], 'input' => $data];
        }, $observed);
    }

    public function test_rendered_scripts_match_seven_observed_parameter_and_dhcp_projections(): void
    {
        $module = new StarlinkModule;
        $actual = array_map(fn ($case) => ['id' => $case['id'], 'script' => $module->generate($case['input'])->text()], self::scenarios());
        $process = new Process(['node', 'scripts/compare-starlink.mjs'], base_path(), null, json_encode($actual, JSON_THROW_ON_ERROR));
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getOutput().$process->getErrorOutput());
        $this->assertStringContainsString('7 observed', $process->getOutput());
    }

    public function test_unsafe_or_ambiguous_configs_fail_validation(): void
    {
        foreach ([['count' => 11], ['count' => 1], ['connection-1' => 'ether1; :error bad'], ['connection-3' => 'ether1'], ['mode' => 'manual', 'connection-2' => 256], ['netwatchEnable' => true, 'netwatchTargets' => '1.1.1.1'], ['netwatchEnable' => true, 'netwatchTargets' => 'example.com,1.1.1.1'], ['localIpTarget' => '192.168.1.0/99'], ['script' => ':error bad']] as $input) {
            try {
                (new StarlinkModule)->validate($input);
                $this->fail('Unsafe configuration accepted');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
    }
}
