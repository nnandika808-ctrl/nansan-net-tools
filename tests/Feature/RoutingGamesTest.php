<?php

namespace Tests\Feature;

use App\Generators\GamePortCatalog;
use App\Generators\GeneratorRegistry;
use App\Generators\Modules\RoutingGamesModule;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class RoutingGamesTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_usable_observed_combinations_match_in_order(): void
    {
        $observations = json_decode(file_get_contents(base_path('docs/reference/evidence/G05-cases-semantics.json')), true, 512, JSON_THROW_ON_ERROR);
        $actual = [];
        $blocked = 0;
        foreach ($observations['cases'] as $case) {
            $input = $case['input'];
            foreach ($case['selected'] as $id) {
                $input[$id] = true;
            }
            try {
                $actual[] = ['id' => $case['test_id'], 'script' => (new RoutingGamesModule)->generate($input)->text()];
            } catch (HttpException $error) {
                $this->assertSame(423, $error->getStatusCode());
                $this->assertStringContainsString('FAILED', $error->getMessage());
                $blocked++;
            }
        }
        $this->assertSame(6, $blocked);
        $compare = new Process(['node', 'scripts/compare-routing-games.mjs'], base_path(), null, json_encode($actual, JSON_THROW_ON_ERROR));
        $compare->mustRun();
        $this->assertStringContainsString('220 Routing Games', $compare->getOutput());
    }

    public function test_bad_reference_entries_are_preserved_and_visibly_flagged(): void
    {
        $catalog = new GamePortCatalog;
        $this->assertCount(106, $catalog->data()['games']);
        $this->assertCount(2, $catalog->fieldStatuses());
        $game = collect($catalog->data()['games'])->firstWhere('id', 'Hearthstone-PC');
        $this->assertSame('FAILED_REFERENCE_SYNTAX', $game['status']);
        $this->assertSame('1119,3724 protocol=udp', $game['rules'][1]['fields']['dst-port']);
        $this->assertSame('FAILED', (new RoutingGamesModule)->schema()['field_statuses']['Hearthstone-PC']['status']);
    }

    public function test_search_does_not_remove_selected_games_and_no_selection_keeps_reference_base(): void
    {
        $module = new RoutingGamesModule;
        $input = ['ip-gateway-input' => '192.168.2.1', 'ArenaOfValor-Mobile' => true];
        $this->assertSame($module->generate($input)->text(), $module->generate([...$input, 'myInput' => 'NO_GAME_MATCH'])->text());
        $this->assertStringNotContainsString('/ip firewall raw', $module->generate(['ip-gateway-input' => 'pppoe-out'])->text());
        $this->assertStringContainsString('gateway="192.168.2.1%ether2"', $module->generate(['ip-gateway-input' => '192.168.2.1%ether2'])->text());
    }

    public function test_invalid_inputs_do_not_become_executable_fragments(): void
    {
        foreach ([['ip-gateway-input' => ''], ['ip-gateway-input' => []], ['ip-gateway-input' => '1.1.1.1; /system reboot'], ['ArenaOfValor-Mobile' => []], ['ArenaOfValor-Mobile' => 'yes'], ['ros-list' => []], ['ros-list' => 'ros8'], ['myInput' => []], ['game-ports' => '1234'], ['code' => 'anything']] as $input) {
            try {
                (new RoutingGamesModule)->generate(array_replace(['ip-gateway-input' => '192.168.2.1'], $input));
                $this->fail('Unsupported input accepted.');
            } catch (ValidationException $error) {
                $this->assertNotEmpty($error->errors());
            }
        }
    }

    public function test_game_preview_is_admin_only_and_cannot_publish_or_write_history(): void
    {
        $this->seed();
        $this->withoutVite();
        $tool = Tool::where('generator_id', 'G05')->firstOrFail();
        $user = User::factory()->create();
        $path = '/admin/tools/'.$tool->slug.'/preview';
        $input = ['ip-gateway-input' => '192.168.2.1', 'ArenaOfValor-Mobile' => true];
        $this->actingAs($user)->postJson($path, ['input' => $input])->assertForbidden();
        $user->forceFill(['role' => 'admin'])->save();
        $this->get($path)->assertOk();
        $this->postJson($path, ['input' => $input])->assertOk()->assertJsonCount(1, 'steps');
        $this->postJson($path, ['input' => [...$input, 'Hearthstone-PC' => true]])->assertStatus(423);
        $this->postJson('/tools/'.$tool->slug.'/generate', ['input' => $input])->assertStatus(423);
        $this->assertFalse(app(GeneratorRegistry::class)->isPublished('G05'));
        $this->assertSame(0, $tool->fresh()->usage_count);
        $this->assertDatabaseCount('script_histories', 0);
    }
}
