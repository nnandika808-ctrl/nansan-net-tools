<?php

namespace Tests\Feature;

use App\Generators\Modules\BurstCalculatorModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BurstCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_burst_calculation_produces_exact_observed_results(): void
    {
        $module = new BurstCalculatorModule;
        $output = $module->generate([]);

        $this->assertCount(3, $output->steps);

        $step1 = $output->steps[0]['script'];
        $this->assertStringContainsString('3M/5M 6M/8M 2250K/3750K 40/32 8 375K/625K', $step1);

        $step2 = $output->steps[1]['script'];
        $this->assertStringContainsString('/queue simple', $step2);
        $this->assertStringContainsString('max-limit="3M/5M"', $step2);
        $this->assertStringContainsString('burst-limit="6M/8M"', $step2);
        $this->assertStringContainsString('burst-threshold="2250K/3750K"', $step2);
        $this->assertStringContainsString('burst-time="40/32"', $step2);
        $this->assertStringContainsString('limit-at="375K/625K"', $step2);

        $step3 = $output->steps[2]['script'];
        $this->assertStringContainsString('/ppp profile', $step3);
        $this->assertStringContainsString('/ip hotspot user profile', $step3);
        $this->assertStringContainsString('rate-limit="3M/5M 6M/8M 2250K/3750K 40/32 8 375K/625K"', $step3);
    }

    public function test_burst_calculator_matches_all_5_observed_cases(): void
    {
        $module = new BurstCalculatorModule;
        $cases = json_decode(file_get_contents(base_path('docs/reference/evidence/G07-cases-semantics.json')), true, 512, JSON_THROW_ON_ERROR);

        foreach ($cases as $item) {
            $c = $item['case'];
            $expectedRate = str_replace('RATE LIMIT FOR HOTSPOT OR PPPOE ', '', $item['result']['rateLimit']);

            $output = $module->generate([
                'up-max-limit' => $c['upMax'],
                'up-speed-bonus' => $c['upBurst'],
                'up-interval-bonus' => (int) $c['upDur'],
                'down-max-limit' => $c['downMax'],
                'down-speed-bonus' => $c['downBurst'],
                'down-interval-bonus' => (int) $c['downDur'],
            ]);

            $this->assertStringContainsString($expectedRate, $output->steps[0]['script'], "Case {$c['id']} rate limit mismatch");
        }
    }

    public function test_validation_rejects_burst_lower_than_max_limit(): void
    {
        $module = new BurstCalculatorModule;

        $this->expectException(ValidationException::class);
        $module->generate([
            'up-max-limit' => '10M',
            'up-speed-bonus' => '5M',
        ]);
    }

    public function test_admin_can_preview_burst_calculator_draft(): void
    {
        $this->seed();
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->postJson('/admin/tools/mikrotik-burst-limit-calculator/preview', [
            'input' => [
                'up-max-limit' => '10M',
                'up-speed-bonus' => '20M',
                'up-interval-bonus' => 10,
                'down-max-limit' => '20M',
                'down-speed-bonus' => '40M',
                'down-interval-bonus' => 10,
            ],
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'steps' => [
                ['title', 'script'],
            ],
            'rosVersion',
            'warnings',
        ]);
    }
}
