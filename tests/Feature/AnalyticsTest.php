<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_successful_public_page_requests_are_counted_and_prefetch_is_excluded(): void
    {
        $this->seed();
        $this->get('/')->assertOk();
        $this->get('/tools')->assertOk();
        $this->get('/tools')->assertOk();
        $this->withHeader('Purpose', 'prefetch')->get('/tools')->assertOk();
        $this->flushHeaders();
        $this->get('/robots.txt')->assertOk();
        $this->get('/docs/missing')->assertNotFound();
        $this->assertSame(1, (int) DB::table('page_view_daily')->where('path', '/')->value('views'));
        $this->assertSame(2, (int) DB::table('page_view_daily')->where('path', '/tools')->value('views'));
        $this->assertDatabaseCount('page_view_daily', 2);
    }

    public function test_analytics_are_admin_only_and_show_actual_usage_totals(): void
    {
        $this->seed();
        DB::table('tools')->where('generator_id', 'G03')->update(['usage_count' => 17]);
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin/analytics')->assertForbidden();
        $user->forceFill(['role' => 'admin'])->save();
        $this->get('/admin/analytics')->assertOk()->assertSee('generated_total');
        $this->get('/admin')->assertOk()->assertSee('17');
    }
}
