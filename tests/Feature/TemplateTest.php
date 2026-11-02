<?php

namespace Tests\Feature;

use App\Generators\GeneratorRegistry;
use App\Models\GeneratorTemplate;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_validated_template_crud_and_activation_do_not_publish_generator(): void
    {
        $this->seed();
        $tool = Tool::where('generator_id', 'G04')->firstOrFail();
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $this->actingAs($admin);
        $path = '/admin/tools/'.$tool->slug.'/templates';
        $payload = ['name' => 'Three WAN', 'input' => ['count' => 3, 'mode' => 'manual', 'netwatchTargets' => ''], 'is_active' => false];
        $this->post($path, $payload)->assertRedirect();
        $template = GeneratorTemplate::firstOrFail();
        $this->assertSame('0.1.0-draft', $template->version);
        $this->assertSame(3, $template->defaults['count']);
        $this->get('/tools/'.$tool->slug)->assertDontSee('Three WAN');
        $this->put($path.'/'.$template->id, [...$payload, 'is_active' => true])->assertRedirect();
        $this->get('/tools/'.$tool->slug)->assertSee('Three WAN');
        $this->assertFalse(app(GeneratorRegistry::class)->isPublished('G04'));
        $this->postJson('/tools/'.$tool->slug.'/generate', ['input' => ['count' => 3]])->assertStatus(423);
        $this->delete($path.'/'.$template->id)->assertRedirect();
        $this->assertDatabaseCount('generator_templates', 0);
    }

    public function test_templates_reject_code_unsafe_inputs_and_cross_tool_updates(): void
    {
        $this->seed();
        $tool = Tool::where('generator_id', 'G04')->firstOrFail();
        $other = Tool::where('generator_id', 'G03')->firstOrFail();
        $member = User::factory()->create();
        $path = '/admin/tools/'.$tool->slug.'/templates';
        $payload = ['name' => 'Draft', 'input' => ['count' => 2], 'is_active' => true];
        $this->actingAs($member)->postJson($path, $payload)->assertForbidden();
        $member->forceFill(['role' => 'admin'])->save();
        $this->postJson($path, [...$payload, 'code' => 'phpinfo();'])->assertUnprocessable();
        $this->postJson($path, [...$payload, 'input' => ['connection-1' => 'ether1; /system reset-configuration']])->assertUnprocessable();
        $template = GeneratorTemplate::create(['tool_id' => $other->id, 'name' => 'Other tool', 'version' => '0.1.0-draft', 'defaults' => []]);
        $this->putJson($path.'/'.$template->id, $payload)->assertNotFound();
        $this->deleteJson($path.'/'.$template->id)->assertNotFound();
    }
}
