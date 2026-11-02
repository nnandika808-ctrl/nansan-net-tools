<?php

namespace Tests\Feature;

use App\Generators\GeneratorRegistry;
use App\Http\Controllers\DocumentationController;
use App\Models\DocumentationArticle;
use App\Models\MediaFile;
use App\Models\ScriptHistory;
use App\Models\SiteSetting;
use App\Models\Tool;
use App\Models\User;
use App\Models\UserPreset;
use App\Services\Audit;
use App\Services\RecoveryCodes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlatformTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['nansan.turnstile.enabled' => false]);
    }

    private function seedTools(): Tool
    {
        $this->seed();

        return Tool::firstOrFail();
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['role' => 'admin'])->save();

        return $u;
    }

    public function test_public_pages_catalog_and_synonym_search_work(): void
    {
        $this->seedTools();
        foreach (['/', '/tools', '/docs', '/docs/verification', '/pages/privacy-policy', '/sitemap.xml', '/robots.txt'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->getJson('/search?q=Load%20Balance')->assertOk()->assertJsonFragment(['slug' => 'pcc']);
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_registration_uses_username_and_issues_only_hashed_recovery_codes(): void
    {
        $response = $this->post('/register', ['username' => 'Example_User', 'password' => 'SecurePassword123', 'password_confirmation' => 'SecurePassword123']);
        $response->assertRedirect('/dashboard')->assertSessionHas('recoveryCodes', fn ($codes) => count($codes) === 8);
        $user = User::firstOrFail();
        $this->assertSame('example_user', $user->username);
        $this->assertSame('user', $user->role);
        $this->assertTrue(Hash::check('SecurePassword123', $user->password));
        $this->assertDatabaseCount('recovery_codes', 8);
        $codes = session('recoveryCodes');
        $this->assertSame(hash('sha256', $codes[0]), DB::table('recovery_codes')->first()->code_hash);
    }

    public function test_registration_rejects_role_escalation_and_duplicate_username(): void
    {
        $payload = ['username' => 'member', 'password' => 'SecurePassword123', 'password_confirmation' => 'SecurePassword123'];
        $this->postJson('/register', [...$payload, 'role' => 'admin'])->assertUnprocessable()->assertJsonValidationErrors('role');
        User::factory()->create(['username' => 'member']);
        $this->postJson('/register', $payload)->assertUnprocessable()->assertJsonValidationErrors('username');
    }

    public function test_turnstile_fails_closed_without_credentials_and_rejects_wrong_hostname(): void
    {
        config(['nansan.turnstile.enabled' => true, 'nansan.turnstile.secret' => null]);
        $payload = ['username' => 'new_member', 'password' => 'SecurePassword123', 'password_confirmation' => 'SecurePassword123', 'cf-turnstile-response' => 'test-token'];
        $this->postJson('/register', $payload)->assertUnprocessable()->assertJsonValidationErrors('cf-turnstile-response');
        config(['nansan.turnstile.secret' => 'test-secret', 'nansan.turnstile.hostnames' => ['example.test']]);
        Http::fake(['*' => Http::response(['success' => true, 'hostname' => 'attacker.test', 'action' => 'register'])]);
        $this->postJson('/register', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_login_logout_and_suspended_account_denial(): void
    {
        $user = User::factory()->create(['username' => 'member']);
        $this->post('/login', ['username' => 'member', 'password' => 'TestPassword12345', 'remember' => true])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $user->forceFill(['status' => 'suspended'])->save();
        $this->postJson('/login', ['username' => 'member', 'password' => 'TestPassword12345'])->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/login', ['username' => 'unknown', 'password' => 'invalid'])->assertUnprocessable();
        }
        $this->postJson('/login', ['username' => 'unknown', 'password' => 'invalid'])->assertTooManyRequests();
    }

    public function test_single_use_recovery_requires_secret_and_revokes_sessions(): void
    {
        $user = User::factory()->create();
        $codes = app(RecoveryCodes::class)->issue($user);
        $payload = ['username' => $user->username, 'recovery_code' => $codes[0], 'password' => 'ChangedPassword123', 'password_confirmation' => 'ChangedPassword123'];
        $this->post('/recovery', $payload)->assertRedirect('/login');
        $this->assertGuest();
        $this->assertTrue(Hash::check('ChangedPassword123', $user->fresh()->password));
        $this->assertDatabaseCount('recovery_codes', 7);
        $this->postJson('/recovery', $payload)->assertUnprocessable()->assertJsonValidationErrors('recovery_code');
    }

    public function test_private_dashboard_and_admin_authorization(): void
    {
        $this->seedTools();
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        foreach (['/admin', '/admin/users', '/admin/tools', '/admin/content/articles', '/admin/monitoring'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->actingAs($this->admin())->get('/admin')->assertOk();
    }

    public function test_favorites_are_scoped_and_can_be_removed(): void
    {
        $tool = $this->seedTools();
        $u = User::factory()->create();
        $this->actingAs($u)->post('/favorites/'.$tool->slug)->assertRedirect();
        $this->assertDatabaseHas('favorites', ['user_id' => $u->id, 'tool_id' => $tool->id]);
        $this->post('/favorites/'.$tool->slug)->assertRedirect();
        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_unpublished_generators_and_presets_fail_closed_even_if_database_claims_complete(): void
    {
        $tool = $this->seedTools();
        $tool->update(['status' => 'COMPLETE']);
        $this->actingAs(User::factory()->create());
        $this->postJson('/tools/'.$tool->slug.'/generate', ['input' => ['password' => 'secret']])->assertStatus(423);
        $this->postJson('/tools/'.$tool->slug.'/presets', ['name' => 'preset', 'input' => []])->assertStatus(423);
        $this->assertDatabaseCount('script_histories', 0);
        $this->assertDatabaseCount('user_presets', 0);
    }

    public function test_private_records_cannot_be_read_changed_or_deleted_by_other_users(): void
    {
        $tool = $this->seedTools();
        $owner = User::factory()->create();
        $history = ScriptHistory::create(['user_id' => $owner->id, 'tool_id' => $tool->id, 'input' => [], 'output' => 'test-only-output', 'generator_version' => 'test', 'ros_version' => '7', 'output_sha256' => hash('sha256', 'test-only-output')]);
        $preset = UserPreset::create(['user_id' => $owner->id, 'tool_id' => $tool->id, 'input' => [], 'name' => 'private', 'generator_version' => 'test']);
        $this->actingAs(User::factory()->create())->get('/history/'.$history->id.'/download')->assertForbidden();
        $this->delete('/history/'.$history->id)->assertForbidden();
        $this->patch('/presets/'.$preset->id, ['name' => 'stolen'])->assertForbidden();
        $this->delete('/presets/'.$preset->id)->assertForbidden();
    }

    public function test_pcc_draft_preview_is_admin_only_and_not_published(): void
    {
        $this->seedTools();
        $tool = Tool::where('generator_id', 'G03')->firstOrFail();
        $this->actingAs(User::factory()->create())->postJson('/admin/tools/'.$tool->slug.'/preview', ['input' => ['num-wan-lines' => 2]])->assertForbidden();
        $this->actingAs($this->admin())->postJson('/admin/tools/'.$tool->slug.'/preview', ['input' => ['num-wan-lines' => 2]])->assertOk()->assertJsonPath('version', '0.1.0-draft')->assertJsonCount(1, 'steps');
        $this->assertDatabaseCount('script_histories', 0);
        $this->postJson('/tools/'.$tool->slug.'/generate', ['input' => ['num-wan-lines' => 2]])->assertStatus(423);
    }

    public function test_starlink_preview_is_private_and_cannot_publish_or_create_history(): void
    {
        $this->seedTools();
        $tool = Tool::where('generator_id', 'G04')->firstOrFail();
        $this->actingAs(User::factory()->create())->get('/admin/tools/'.$tool->slug.'/preview')->assertForbidden();
        $this->actingAs($this->admin())->postJson('/admin/tools/'.$tool->slug.'/preview', ['input' => ['count' => 3, 'mode' => 'manual', 'netwatchEnable' => true, 'netwatchTargets' => '']])->assertOk()->assertJsonCount(1, 'steps');
        $this->assertDatabaseCount('script_histories', 0);
        $this->postJson('/tools/'.$tool->slug.'/generate', ['input' => ['count' => 2]])->assertStatus(423);
    }

    public function test_password_change_requires_current_password(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->putJson('/account/password', ['current_password' => 'wrong', 'password' => 'NewSecurePassword123', 'password_confirmation' => 'NewSecurePassword123'])->assertUnprocessable();
        $this->put('/account/password', ['current_password' => 'TestPassword12345', 'password' => 'NewSecurePassword123', 'password_confirmation' => 'NewSecurePassword123'])->assertRedirect();
        $this->assertTrue(Hash::check('NewSecurePassword123', $u->fresh()->password));
    }

    public function test_user_details_are_admin_only_and_do_not_expose_secrets_or_private_scripts(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin/users/'.$user->id)->assertForbidden();
        $this->actingAs($this->admin())->get('/admin/users/'.$user->id)->assertOk()->assertDontSee($user->getRawOriginal('password'));
    }

    public function test_preset_loading_is_scoped_to_its_owner_and_tool_without_unlocking_generation(): void
    {
        $this->seedTools();
        $tool = Tool::where('generator_id', 'G03')->firstOrFail();
        $owner = User::factory()->create();
        $preset = $owner->presets()->create(['tool_id' => $tool->id, 'name' => 'Personal preset', 'input' => ['wan-isp-1' => 'ether5'], 'generator_version' => '0.1.0-draft']);
        $this->actingAs(User::factory()->create())->get('/tools/'.$tool->slug.'?preset='.$preset->id)->assertNotFound();
        $this->actingAs($owner)->get('/tools/'.$tool->slug.'?preset='.$preset->id)->assertOk()->assertSee('Personal preset');
        $this->get('/tools/static-routing?preset='.$preset->id)->assertNotFound();
        $this->postJson('/tools/'.$tool->slug.'/generate', ['input' => ['wan-isp-1' => 'ether5']])->assertStatus(423);
    }

    public function test_admin_can_edit_tags_icons_and_synonyms_without_unlocking_engine(): void
    {
        $tool = $this->seedTools();
        $this->actingAs($this->admin());
        $payload = ['name' => $tool->name, 'description' => $tool->description, 'featured' => true, 'sort_order' => 1, 'tool_category_id' => $tool->tool_category_id, 'tags' => ['Routing', 'Community'], 'synonyms' => ['Route assistant'], 'icon' => 'route'];
        $this->patch('/admin/tools/'.$tool->slug, $payload)->assertRedirect();
        $this->assertSame(2, $tool->tags()->count());
        $this->getJson('/search?q=Route%20assistant')->assertOk()->assertJsonFragment(['slug' => $tool->slug]);
        $this->patchJson('/admin/tools/'.$tool->slug, [...$payload, 'icon' => '<script>'])->assertUnprocessable();
        $this->assertFalse(app(GeneratorRegistry::class)->isPublished($tool->generator_id));
    }

    public function test_account_deletion_requires_password_and_can_be_cancelled(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->postJson('/account/deletion', ['password' => 'wrong', 'confirmation' => 'HAPUS'])->assertUnprocessable();
        $this->post('/account/deletion', ['password' => 'TestPassword12345', 'confirmation' => 'HAPUS'])->assertRedirect();
        $this->assertSame('deletion_pending', $u->fresh()->status);
        $this->get('/dashboard')->assertRedirect('/account');
        $this->post('/account/deletion/cancel', ['password' => 'TestPassword12345'])->assertRedirect();
        $this->assertSame('active', $u->fresh()->status);
    }

    public function test_scheduled_deletion_observes_full_grace_period(): void
    {
        $u = User::factory()->create(['status' => 'deletion_pending']);
        DB::table('deletion_requests')->insert(['user_id' => $u->id, 'requested_at' => now(), 'delete_after' => now()->addDays(30), 'created_at' => now(), 'updated_at' => now()]);
        $this->artisan('nansan:purge-accounts')->assertSuccessful();
        $this->assertDatabaseHas('users', ['id' => $u->id]);
        $this->travel(31)->days();
        $this->artisan('nansan:purge-accounts')->assertSuccessful();
        $this->assertDatabaseMissing('users', ['id' => $u->id]);
    }

    public function test_drafts_and_future_articles_are_not_public_and_html_is_sanitized(): void
    {
        DocumentationArticle::create(['title' => 'Draft', 'slug' => 'hidden', 'body' => 'hidden', 'status' => 'draft']);
        $this->get('/docs/hidden')->assertNotFound();
        DocumentationArticle::create(['title' => 'Future', 'slug' => 'future', 'body' => 'not yet', 'status' => 'published', 'published_at' => now()->addDay()]);
        $this->get('/docs/future')->assertNotFound();
        $html = app(DocumentationController::class)->markdown('<script>alert(1)</script> [bad](javascript:alert(1))');
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
    }

    public function test_admin_can_create_update_and_delete_cms_without_executing_content(): void
    {
        $this->actingAs($this->admin());
        $payload = ['title' => 'Test article', 'slug' => 'test-article', 'body' => '<?php echo "unsafe"; ?>', 'status' => 'draft'];
        $this->post('/admin/content/articles', $payload)->assertRedirect();
        $a = DocumentationArticle::firstOrFail();
        $this->put('/admin/content/articles/'.$a->id, [...$payload, 'title' => 'Published article', 'status' => 'published'])->assertRedirect();
        $this->get('/docs/test-article')->assertOk();
        $this->delete('/admin/content/articles/'.$a->id)->assertRedirect();
        $this->assertDatabaseCount('documentation_articles', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.content_saved']);
    }

    public function test_admin_cannot_publish_a_generator_by_metadata_or_accept_executable_handler(): void
    {
        $tool = $this->seedTools();
        $this->actingAs($this->admin())->patchJson('/admin/tools/'.$tool->slug, ['name' => 'Tool', 'description' => 'Test', 'featured' => true, 'sort_order' => 0, 'tool_category_id' => $tool->tool_category_id, 'status' => 'COMPLETE', 'code' => 'phpinfo();'])->assertUnprocessable()->assertJsonValidationErrors(['status', 'code']);
    }

    public function test_feedback_is_private_and_accepts_status_updates(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->post('/feedback', ['type' => 'bug', 'title' => 'A real issue', 'body' => 'Detailed description of the observed issue.'])->assertRedirect();
        $this->assertDatabaseHas('feedback_reports', ['user_id' => $u->id, 'status' => 'open']);
        $id = DB::table('feedback_reports')->value('id');
        $this->actingAs($this->admin())->patch('/admin/feedback/'.$id, ['status' => 'resolved', 'admin_reply' => 'Fixed and tested.'])->assertRedirect();
        $this->assertDatabaseHas('feedback_reports', ['id' => $id, 'status' => 'resolved']);
    }

    public function test_uploads_are_reencoded_and_private_media_is_protected(): void
    {
        Storage::fake('local');
        $u = $this->admin();
        $this->actingAs($u)->post('/admin/media', ['file' => UploadedFile::fake()->image('photo.jpg', 100, 100), 'folder' => 'test', 'is_public' => false])->assertRedirect();
        $media = MediaFile::firstOrFail();
        $this->assertSame('image/webp', $media->mime_type);
        Storage::disk('local')->assertExists($media->path);
        $this->actingAs(User::factory()->create())->get('/media/'.$media->id)->assertNotFound();
        $this->actingAs($u)->postJson('/admin/media', ['file' => UploadedFile::fake()->create('payload.svg', 1, 'image/svg+xml')])->assertUnprocessable();
    }

    public function test_audit_logs_drop_secret_fields(): void
    {
        Audit::record('test', null, null, ['password' => 'do-not-store', 'token' => 'secret', 'status' => 'ok']);
        $metadata = json_decode(DB::table('audit_logs')->first()->metadata, true);
        $this->assertSame(['status' => 'ok'], $metadata);
    }

    public function test_user_preferences_persist_and_security_headers_are_set(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->patch('/account/preferences', ['locale' => 'en', 'theme' => 'dark'])->assertRedirect();
        $this->assertSame('en', $u->fresh()->locale);
        $this->get('/account')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_maintenance_keeps_admin_and_login_accessible(): void
    {
        SiteSetting::create(['key' => 'maintenance', 'value' => ['enabled' => true, 'message' => 'Service work']]);
        $this->get('/')->assertServiceUnavailable();
        $this->get('/login')->assertOk();
        $this->actingAs($this->admin())->get('/admin')->assertOk();
    }
}
