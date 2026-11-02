<?php

namespace Tests\Feature;

use App\Generators\GeneratorRegistry;
use App\Models\GeneratorVersion;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_draft_crud_never_changes_audited_schema_or_publication(): void
    {
        $this->seed();
        $tool = Tool::where('generator_id', 'G03')->firstOrFail();
        $user = User::factory()->create();
        $path = '/admin/tools/'.$tool->slug.'/versions';
        $payload = ['version' => '0.2.0-draft', 'changelog' => 'Review field wording', 'fields' => [['key' => 'newField', 'label' => 'Draft only', 'type' => 'text', 'default' => '']]];
        $this->actingAs($user)->postJson($path, $payload)->assertForbidden();
        $user->forceFill(['role' => 'admin'])->save();
        $before = app(GeneratorRegistry::class)->module('G03')->schema();
        $this->post($path, $payload)->assertRedirect();
        $draft = GeneratorVersion::where('tool_id', $tool->id)->where('status', 'DRAFT')->firstOrFail();
        $this->assertNull($draft->evidence);
        $this->put($path.'/'.$draft->id, [...$payload, 'changelog' => 'Updated review'])->assertRedirect();
        $this->assertSame($before, app(GeneratorRegistry::class)->module('G03')->schema());
        $this->assertFalse(app(GeneratorRegistry::class)->isPublished('G03'));
        $audited = GeneratorVersion::where('tool_id', $tool->id)->where('status', 'BLOCKED')->firstOrFail();
        $this->putJson($path.'/'.$audited->id, $payload)->assertStatus(423);
        $this->deleteJson($path.'/'.$audited->id)->assertStatus(423);
        $this->delete($path.'/'.$draft->id)->assertRedirect();
        $this->assertFalse(DB::table('generator_form_definitions')->where('generator_version_id', $draft->id)->exists());
    }

    public function test_schema_validation_rejects_handlers_duplicate_keys_and_invalid_conditions(): void
    {
        $this->seed();
        $tool = Tool::firstOrFail();
        $user = User::factory()->create();
        $user->forceFill(['role' => 'admin'])->save();
        $this->actingAs($user);
        $path = '/admin/tools/'.$tool->slug.'/versions';
        $field = ['key' => 'example', 'label' => 'Example', 'type' => 'text', 'default' => ''];
        $payload = ['version' => '0.2.0-draft', 'changelog' => 'Test', 'fields' => [$field]];
        foreach ([[...$payload, 'status' => 'COMPLETE'], [...$payload, 'code' => 'phpinfo();'], [...$payload, 'fields' => [$field, $field]], [...$payload, 'fields' => [[...$field, 'conditional' => 'missing']]], [...$payload, 'fields' => [[...$field, 'handler' => 'exec']]]] as $bad) {
            $this->postJson($path, $bad)->assertUnprocessable();
        }
    }
}
