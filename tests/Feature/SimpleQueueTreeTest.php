<?php

namespace Tests\Feature;

use App\Generators\Modules\SimpleQueueTreeModule;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SimpleQueueTreeTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_simple_queue_generates_valid_script(): void
    {
        $module = new SimpleQueueTreeModule;
        $output = $module->generate([]);

        $this->assertCount(1, $output->steps);
        $script = $output->steps[0]['script'];

        $this->assertStringContainsString('/ip firewall address-list', $script);
        $this->assertStringContainsString('/ip firewall mangle', $script);
        $this->assertStringContainsString('/queue simple', $script);
        $this->assertStringContainsString('add name="1. Bypass Local Traffic"', $script);
        $this->assertStringContainsString('add name="7. GLOBAL CONNECTION"', $script);
        $this->assertStringContainsString('max-limit=50M/100M', $script);
        // With 0 categories, 2 children (Fastest + Heavy) -> 50% pool = 25M up / 50M down / 2 = 12.5M / 25M
        $this->assertStringContainsString('limit-at=12.5M/25M', $script);
    }

    public function test_bandwidth_mathematical_limits_are_calculated_accurately(): void
    {
        $module = new SimpleQueueTreeModule;

        // With 2 categories checked (total 4 children: Cat1, Cat2, Fastest, Heavy)
        // ISP: 100M down, 50M up -> Pool: 50M down, 25M up
        // Limit-at per child: 25M / 4 = 6.25M up, 50M / 4 = 12.5M down
        $output = $module->generate([
            'category-streaming-video' => true,
            'category-social-media' => true,
            'downloadBandwidthISP' => '100M',
            'uploadBandwidthISP' => '50M',
        ]);

        $script = $output->steps[0]['script'];
        $this->assertStringContainsString('limit-at=6.25M/12.5M', $script);
        $this->assertStringContainsString('packet-marks="streaming_video_mark"', $script);
        $this->assertStringContainsString('packet-marks="social_media_mark"', $script);
    }

    public function test_queue_tree_mode_generates_inbound_outbound_and_directional_marks(): void
    {
        $module = new SimpleQueueTreeModule;
        $output = $module->generate([
            'globalQueueType' => 'Queue Tree',
            'category-streaming-video' => true,
            'downloadBandwidthISP' => '100M',
            'uploadBandwidthISP' => '50M',
        ]);

        $script = $output->steps[0]['script'];
        $this->assertStringContainsString('/queue tree', $script);
        $this->assertStringContainsString('add name="GLOBAL CONNECTION" parent=global', $script);
        $this->assertStringContainsString('add name="7.INBOUND PACKET" parent="GLOBAL CONNECTION"', $script);
        $this->assertStringContainsString('add name="8.OUTBOUND PACKET" parent="GLOBAL CONNECTION"', $script);
        $this->assertStringContainsString('packet-mark="streaming_video_mark_up"', $script);
        $this->assertStringContainsString('packet-mark="streaming_video_mark_down"', $script);
    }

    public function test_custom_queue_types_emit_definitions_and_warnings(): void
    {
        $module = new SimpleQueueTreeModule;
        $output = $module->generate([
            'globalQueueTypeInput' => 'cake',
        ]);

        $script = $output->steps[0]['script'];
        $this->assertStringContainsString('/queue type', $script);
        $this->assertStringContainsString('add name="cake" kind=cake', $script);
        $this->assertSame('7', $output->rosVersion);
        $this->assertStringContainsString('RouterOS v7.xx', implode(' ', $output->warnings));
    }

    public function test_invalid_bandwidth_and_inputs_are_rejected(): void
    {
        $module = new SimpleQueueTreeModule;

        $this->expectException(ValidationException::class);
        $module->generate([
            'downloadBandwidthISP' => 'INVALID_RATE',
        ]);
    }

    public function test_admin_can_preview_simple_queue_tree_draft(): void
    {
        $this->seed();
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->postJson('/admin/tools/simple-queue-or-queue-tree-generator/preview', [
            'input' => [
                'globalQueueType' => 'Queue Tree',
                'category-streaming-video' => true,
                'downloadBandwidthISP' => '100M',
                'uploadBandwidthISP' => '50M',
                'globalQueueTypeInput' => 'default-small',
                'localTarget' => '192.168.0.0/16,10.0.0.0/8,172.16.0.0/12',
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
