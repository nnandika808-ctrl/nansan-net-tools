<?php

namespace Tests\Feature;

use App\Generators\GeneratorRegistry;
use App\Generators\Modules\StaticRoutingModule;
use App\Generators\StaticRoutingDatasets;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class StaticRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_available_wrapper_projections_match_observed_cases(): void
    {
        $module = new StaticRoutingModule;
        $observations = json_decode(file_get_contents(base_path('docs/reference/evidence/G01-cases-semantics.json')), true, 512, JSON_THROW_ON_ERROR);
        $actual = [];
        foreach ($observations['cases'] as $case) {
            try {
                $output = $module->generate($case['input']);
                $actual[] = ['id' => $case['test_id'], 'steps' => $output->steps];
            } catch (HttpException $error) {
                $this->assertSame('Shopify', $case['input']['routing-list']);
                $this->assertSame(423, $error->getStatusCode());
            }
        }
        $process = new Process(['node', 'scripts/compare-static-routing.mjs'], base_path(), null, json_encode($actual, JSON_THROW_ON_ERROR));
        $process->mustRun();
        $this->assertStringContainsString('184', $process->getOutput());
    }

    public function test_datasets_are_complete_integrity_checked_parameters_and_self_hosted(): void
    {
        $datasets = new StaticRoutingDatasets;
        $available = array_filter($datasets->manifest()['datasets'], fn ($entry) => isset($entry['parameter_file']));
        $this->assertCount(92, $available);
        foreach ($available as $entry) {
            $artifact = $datasets->artifact($entry['application'], $entry['mode']);
            $this->assertSame($entry['artifact_sha256'], hash('sha256', $artifact['script']));
            $this->assertStringNotContainsString('/tool fetch', $artifact['script']);
            $this->assertStringNotContainsString('/system', $artifact['script']);
        }
        config(['app.url' => 'https://tools.example.test']);
        $output = (new StaticRoutingModule)->generate([]);
        $this->assertStringContainsString('https://tools.example.test/datasets/static-routing/', $output->steps[0]['script']);
        $this->assertStringNotContainsString('buananet.com', $output->text());
    }

    public function test_invalid_and_injected_inputs_are_rejected_before_script_creation(): void
    {
        foreach ([['routing-list' => ['Youtube']], ['ros-list' => ['ros6']], ['match-condition' => ['tls-host']], ['routing-list' => 'unknown'], ['routing-list' => '../Youtube'], ['gateway-option' => '1.1.1.1; /system reboot'], ['gateway-option' => 'ether1'], ['gateway-option' => '999.1.1.1'], ['routing-mark' => 'main'], ['routing-mark' => 'x"; :error bad'], ['routing-mark' => 'x$variable'], ['ros-list' => 'ros8'], ['match-condition' => 'layer7'], ['code' => 'anything']] as $input) {
            try {
                (new StaticRoutingModule)->generate($input);
                $this->fail('Invalid input accepted');
            } catch (ValidationException $error) {
                $this->assertNotEmpty($error->errors());
            }
        }
    }

    public function test_static_routing_preview_requires_admin_and_does_not_publish_or_count_usage(): void
    {
        $this->seed();
        $this->withoutVite();
        $tool = Tool::where('generator_id', 'G01')->firstOrFail();
        $member = User::factory()->create();
        $path = '/admin/tools/'.$tool->slug.'/preview';
        $this->actingAs($member)->postJson($path, ['input' => []])->assertForbidden();
        $member->forceFill(['role' => 'admin'])->save();
        $this->get($path)->assertOk();
        $this->postJson($path, ['input' => ['routing-list' => 'Youtube']])->assertOk()->assertJsonCount(2, 'steps');
        $this->postJson($path, ['input' => ['routing-list' => 'Shopify']])->assertStatus(423);
        $this->postJson('/tools/'.$tool->slug.'/generate', ['input' => ['routing-list' => 'Youtube']])->assertStatus(423);
        $this->assertFalse(app(GeneratorRegistry::class)->isPublished('G01'));
        $this->assertSame(0, $tool->fresh()->usage_count);
        $this->assertDatabaseCount('script_histories', 0);
    }

    public function test_dataset_endpoint_serves_only_known_hashes_and_stays_closed_in_production(): void
    {
        $artifact = (new StaticRoutingDatasets)->artifact('Youtube', 'tls-host');
        $path = '/datasets/static-routing/'.$artifact['sha256'].'.rsc';
        $this->get($path)->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee('tls-host', false);
        $this->get('/datasets/static-routing/'.str_repeat('0', 64).'.rsc')->assertNotFound();
        $this->get('/datasets/static-routing/../manifest.json')->assertNotFound();
        $this->app->instance('env', 'production');
        $this->get($path)->assertNotFound();
    }
}
