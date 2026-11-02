<?php

namespace Tests\Feature;

use App\Models\DocumentationArticle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_english_content_is_seeded_preserves_owner_edits_and_sanitizes_both_languages(): void
    {
        $this->seed();
        $article = DocumentationArticle::where('slug', 'verification')->firstOrFail();
        $this->assertSame('Generator verification', $article->title_en);
        $article->update(['title_en' => 'Owner translation', 'body_en' => '<script>alert(1)</script> **Safe English**']);
        $this->seed();
        $this->assertSame('Owner translation', $article->fresh()->title_en);
        $this->get('/docs/verification')->assertOk()->assertSee('Safe English')->assertDontSee('<script>alert(1)</script>', false);
        $this->getJson('/search?q=Owner%20translation')->assertOk()->assertJsonFragment(['title_en' => 'Owner translation']);
    }

    public function test_admin_metadata_is_escaped_and_cannot_make_private_routes_indexable(): void
    {
        $this->seed();
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $payload = ['path' => '/tools/pcc', 'title' => 'PCC <script>alert(1)</script>', 'description' => 'Description with "quotes"', 'noindex' => true, 'image_id' => null];
        $this->actingAs($admin)->post('/admin/seo', $payload)->assertRedirect();
        $this->postJson('/admin/seo', [...$payload, 'path' => '/account'])->assertUnprocessable();
        $this->postJson('/admin/seo', [...$payload, 'path' => 'https://attacker.example'])->assertUnprocessable();
        $this->app['auth']->forgetGuards();
        config(['nansan.indexable' => true]);
        $this->get('/tools/pcc')->assertOk()->assertSee('PCC &lt;script&gt;', false)->assertSee('noindex,nofollow', false);
        $this->get('/login')->assertSee('noindex,nofollow', false);
    }

    public function test_regular_users_cannot_edit_metadata(): void
    {
        $this->actingAs(User::factory()->create())->postJson('/admin/seo', [])->assertForbidden();
    }

    public function test_faq_is_editable_in_both_languages_and_is_public_text(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $this->actingAs($admin)->put('/admin/faq', ['items' => [['locale' => 'id', 'question' => 'Pertanyaan aman', 'answer' => '<script>literal text</script>'], ['locale' => 'en', 'question' => 'Safe question', 'answer' => 'Safe answer']]])->assertRedirect();
        $this->get('/')->assertOk()->assertSee('Pertanyaan aman');
        $this->putJson('/admin/faq', ['items' => [['locale' => 'invalid', 'question' => 'Q', 'answer' => 'A']]])->assertUnprocessable();
    }
}
