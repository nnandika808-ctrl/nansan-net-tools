<?php

namespace Tests\Feature;

use App\Models\Tool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShowcaseMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_overview_uses_recorded_activity_and_code_publication_status(): void
    {
        $this->seed();
        $this->withoutVite();
        $admin = User::factory()->create(['role' => 'admin']);
        Tool::where('generator_id', 'G01')->update(['usage_count' => 37, 'status' => 'COMPLETE']);
        foreach (['generator.generated', 'generator.generated', 'auth.login'] as $action) {
            DB::table('audit_logs')->insert(['action' => $action, 'created_at' => now()->subDay(), 'metadata' => '{}']);
        }
        DB::table('audit_logs')->insert(['action' => 'generator.generated', 'created_at' => now()->subDays(8), 'metadata' => '{}']);
        $page = $this->actingAs($admin)->get('/admin')->assertOk()->viewData('page');
        $this->assertSame(37, $page['props']['stats']['generated']);
        $this->assertSame(0, $page['props']['stats']['published_tools']);
        $this->assertCount(7, $page['props']['activity']);
        $this->assertSame(2, array_sum(array_column($page['props']['activity'], 'count')));
        $this->assertSame(37, $page['props']['popularTools'][0]['usage_count']);
    }

    public function test_dashboard_catalog_count_uses_database_records(): void
    {
        $this->seed();
        $this->withoutVite();
        $user = User::factory()->create();
        $page = $this->actingAs($user)->get('/dashboard')->assertOk()->viewData('page');
        $this->assertSame(Tool::count(), $page['props']['stats']['tools']);
        $this->assertSame(0, $page['props']['stats']['histories']);
        $this->assertSame(0, $page['props']['stats']['favorites']);
    }
}
