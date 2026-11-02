<?php
namespace Tests\Feature;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\{DB,Storage};
use Tests\TestCase;
class BackupTest extends TestCase
{
    use DatabaseMigrations;
    public function test_encrypted_backup_is_checksum_verified_and_restored_into_scratch_database(): void
    {
        if(!extension_loaded('sodium'))$this->markTestSkipped('Sodium extension unavailable.');
        Storage::fake('backups');config(['nansan.backup_disk'=>'backups','nansan.backup_key'=>base64_encode(random_bytes(32))]);
        $service=app(BackupService::class);$id=$service->create();$record=DB::table('backup_records')->find($id);
        $this->assertSame('created_not_restored',$record->status);$this->assertNull($record->restore_tested_at);$this->assertStringStartsWith('NANSANBK1',Storage::disk('backups')->get($record->path));
        $service->verifyRestore($id);$this->assertDatabaseHas('backup_records',['id'=>$id,'status'=>'restore_verified']);
    }
    public function test_backup_fails_closed_without_a_configured_key(): void
    {
        config(['nansan.backup_disk'=>'backups','nansan.backup_key'=>null]);$this->expectException(\RuntimeException::class);app(BackupService::class)->create();
    }
    public function test_scratch_mysql_restore_rejects_source_credentials_before_connecting(): void
    {
        config(['nansan.restore_mysql'=>['host'=>'127.0.0.1','port'=>3306,'username'=>'app_source','password'=>'unused-test-secret','client'=>'mysql'],'database.connections.mysql.username'=>'app_source']);
        $this->expectExceptionMessage('Restore account must differ');
        app(\App\Services\MysqlRestoreVerifier::class)->verify('unused.sql',[]);
    }
}
