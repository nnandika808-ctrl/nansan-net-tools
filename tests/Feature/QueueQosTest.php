<?php

namespace Tests\Feature;

use App\Generators\GeneratorRegistry;
use App\Generators\Modules\QueueQosModule;
use App\Generators\StaticRoutingDatasets;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class QueueQosTest extends TestCase
{
    use RefreshDatabase;

    public function test_rendered_output_matches_210_available_observations(): void
    {
        $source = json_decode(file_get_contents(base_path('docs/reference/evidence/G02-cases-semantics.json')), true, 512, JSON_THROW_ON_ERROR);
        $module = new QueueQosModule;
        $actual = [];
        foreach ($source['cases'] as $case) {
            try {
                $actual[] = ['id' => $case['test_id'], 'steps' => $module->generate($case['input'])->steps];
            } catch (HttpException $error) {
                $this->assertSame('Shopify', $case['input']['Queue-List']);
                $this->assertSame(423, $error->getStatusCode());
            }
        }
        $compare = new Process(['node', 'scripts/compare-queue-qos.mjs'], base_path(), null, json_encode($actual, JSON_THROW_ON_ERROR));
        $compare->mustRun();
        $this->assertStringContainsString('210 Queue QoS', $compare->getOutput());
    }

    public function test_reference_order_and_shared_failures_are_preserved(): void
    {
        $module = new QueueQosModule;
        $script = $module->generate([])->steps[1]['script'];
        $this->assertLessThan(strpos($script, '/ip firewall mangle'), strpos($script, '/queue simple'));
        $this->assertStringContainsString('chain="postrouting"', $script);
        $artifact = (new StaticRoutingDatasets)->artifact('Teams', 'tls-host');
        $this->assertSame(2, $artifact['source']['duplicate_commands']);
        $this->assertStringContainsString('FAILED', implode(' ', $module->generate(['Queue-List' => 'Teams'])->warnings));
    }

    public function test_invalid_inputs_and_code_fragments_are_rejected(): void
    {
        foreach ([['Queue-Name' => []], ['Queue-Options' => []], ['Queue-Options' => 'Other'], ['Queue-List' => ['Youtube']], ['Queue-List' => '../Youtube'], ['Queue-Name' => 'x"; /system reboot'], ['Queue-Name' => 'x$variable'], ['Queue-Name' => "x\n/system reboot"], ['Priority' => 0], ['Priority' => 9], ['Priority' => '1/1'], ['Max-Limit' => '20M/10M'], ['Max-Limit' => '20M; :error bad'], ['Max-Limit' => '100000G'], ['Max-Limit' => []], ['ros' => 'v7'], ['script' => 'anything']] as $input) {
            try {
                (new QueueQosModule)->generate($input);
                $this->fail('Unsafe/unsupported input accepted.');
            } catch (ValidationException $error) {
                $this->assertNotEmpty($error->errors());
            }
        }
    }

    public function test_admin_preview_does_not_publish_or_record_user_history(): void
    {
        $this->seed();
        $this->withoutVite();
        $tool = Tool::where('generator_id', 'G02')->firstOrFail();
        $user = User::factory()->create();
        $path = '/admin/tools/'.$tool->slug.'/preview';
        $this->actingAs($user)->postJson($path, ['input' => ['Queue-List' => 'Youtube']])->assertForbidden();
        $user->forceFill(['role' => 'admin'])->save();
        $this->get($path)->assertOk();
        $this->postJson($path, ['input' => ['Queue-Options' => 'Queue-Tree', 'Priority' => '1']])->assertOk()->assertJsonPath('rosVersion', '6/7')->assertJsonCount(2, 'steps');
        $this->postJson($path, ['input' => ['Queue-List' => 'Shopify']])->assertStatus(423);
        $this->postJson('/tools/'.$tool->slug.'/generate', ['input' => ['Queue-List' => 'Youtube']])->assertStatus(423);
        $this->assertFalse(app(GeneratorRegistry::class)->isPublished('G02'));
        $this->assertSame(0, $tool->fresh()->usage_count);
        $this->assertDatabaseCount('script_histories', 0);
    }
}
