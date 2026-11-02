<?php
namespace App\Services;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;
class BackupService
{
    public function create(): int
    {
        $disk=config('nansan.backup_disk');
        if(!$disk) throw new RuntimeException('A private backup disk must be configured first.');
        $key=$this->key();$directory=storage_path('app/private/backup-work/'.Str::uuid());
        if(!mkdir($directory,0700,true)) throw new RuntimeException('Cannot create private temporary directory.');
        $id=DB::table('backup_records')->insertGetId(['status'=>'running','disk'=>$disk,'created_at'=>now(),'updated_at'=>now()]);$zip=null;
        try{
            $driver=DB::connection()->getDriverName();
            $name=$driver==='sqlite'?'database.sqlite':'database.sql';$snapshot=$directory.'/'.$name;
            if($driver==='sqlite') DB::statement('VACUUM INTO '.DB::getPdo()->quote($snapshot));
            elseif($driver==='mysql'){
                $connection=config('database.connections.mysql');$handle=fopen($snapshot,'wb');
                $process=new Process([config('nansan.mysql_dump_path'),'--single-transaction','--quick','--skip-lock-tables','--no-tablespaces','--host='.$connection['host'],'--port='.$connection['port'],'--user='.$connection['username'],$connection['database']],base_path(),['MYSQL_PWD'=>$connection['password']],null,300);
                try{$process->run(function($type,$buffer)use($handle){if($type===Process::OUT)fwrite($handle,$buffer);});}finally{fclose($handle);}
                if(!$process->isSuccessful()||filesize($snapshot)<100)throw new RuntimeException('Database snapshot failed. No backup success recorded.');
            }else throw new RuntimeException('Unsupported database driver.');
            $manifest=['version'=>1,'created_at'=>now()->toIso8601String(),'driver'=>$driver,'database_file'=>$name,'counts'=>[],'files'=>[]];
            foreach(['users','tools','documentation_articles'] as $table)$manifest['counts'][$table]=DB::table($table)->count();
            $zip=new ZipArchive;
            if($zip->open($directory.'/archive.zip',ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Cannot create backup archive.');
            $zip->addFile($snapshot,$name);
            foreach(DB::table('media_files')->get(['disk','path']) as $media){
                if($media->disk!=='local'||!preg_match('~^media/[a-f0-9-]+\.webp$~D',$media->path))throw new RuntimeException('Unexpected media path in backup inventory.');
                $path=Storage::disk('local')->path($media->path);
                if(!is_file($path))throw new RuntimeException('Backup inventory contains a missing media file.');
                $archivePath='uploads/'.$media->path;$manifest['files'][$archivePath]=hash_file('sha256',$path);$zip->addFile($path,$archivePath);
            }
            $zip->addFromString('manifest.json',json_encode($manifest,JSON_THROW_ON_ERROR));$zip->close();
            $encrypted=$directory.'/archive.enc';$this->encrypt($directory.'/archive.zip',$encrypted,$key);
            $path='nansan/'.now()->format('Y/m/d').'/'.Str::uuid().'.nnb';$stream=fopen($encrypted,'rb');
            try{if(!Storage::disk($disk)->put($path,$stream))throw new RuntimeException('Backup write failed.');}finally{fclose($stream);}
            $expected=hash_file('sha256',$encrypted);$read=Storage::disk($disk)->readStream($path);$hash=hash_init('sha256');hash_update_stream($hash,$read);fclose($read);
            if(!hash_equals($expected,hash_final($hash)))throw new RuntimeException('Stored backup checksum mismatch.');
            DB::table('backup_records')->where('id',$id)->update(['status'=>'created_not_restored','path'=>$path,'size'=>Storage::disk($disk)->size($path),'sha256'=>$expected,'message'=>'Encrypted database and media archive; restore test pending.','updated_at'=>now()]);
            Audit::record('backup.created','backup',$id);return $id;
        }catch(\Throwable $error){DB::table('backup_records')->where('id',$id)->update(['status'=>'failed','message'=>'Backup failed; no successful restore claim.','updated_at'=>now()]);throw $error;}
        finally{if($zip)try{$zip->close();}catch(\Throwable){}foreach(glob($directory.'/*')?:[] as $file)if(is_file($file))unlink($file);rmdir($directory);}
    }
    public function verifyRestore(int $id): void
    {
        $record=DB::table('backup_records')->where('id',$id)->first();
        if(!$record||!$record->path||!in_array($record->status,['created_not_restored','restore_verified'],true))throw new RuntimeException('Backup is not eligible for restore testing.');
        $directory=storage_path('app/private/backup-work/'.Str::uuid());mkdir($directory,0700,true);$zip=null;$restored=null;
        try{
            $input=Storage::disk($record->disk)->readStream($record->path);$encrypted=fopen($directory.'/archive.enc','wb');stream_copy_to_stream($input,$encrypted);fclose($input);fclose($encrypted);
            if(!hash_equals($record->sha256,hash_file('sha256',$directory.'/archive.enc')))throw new RuntimeException('Backup checksum mismatch.');
            $this->decrypt($directory.'/archive.enc',$directory.'/archive.zip',$this->key());
            $zip=new ZipArchive;if($zip->open($directory.'/archive.zip')!==true)throw new RuntimeException('Invalid archive.');
            $manifest=json_decode($zip->getFromName('manifest.json'),true,512,JSON_THROW_ON_ERROR);
            if(($manifest['driver']??null)==='mysql'){
                $bytes=$zip->getFromName('database.sql');if($bytes===false)throw new RuntimeException('Database snapshot missing.');
                file_put_contents($directory.'/database.sql',$bytes);
                app(MysqlRestoreVerifier::class)->verify($directory.'/database.sql',$manifest['counts']);
            }elseif(($manifest['driver']??null)==='sqlite'){
            $bytes=$zip->getFromName('database.sqlite');if($bytes===false)throw new RuntimeException('Database snapshot missing.');file_put_contents($directory.'/restored.sqlite',$bytes);
            $restored=new \PDO('sqlite:'.$directory.'/restored.sqlite');
            if($restored->query('PRAGMA integrity_check')->fetchColumn()!=='ok')throw new RuntimeException('Restored database integrity check failed.');
            foreach($manifest['counts'] as $table=>$count){if(!in_array($table,['users','tools','documentation_articles'],true))throw new RuntimeException('Unexpected count target.');if((int)$restored->query('SELECT COUNT(*) FROM '.$table)->fetchColumn()!==$count)throw new RuntimeException('Restored row count mismatch.');}
            }else throw new RuntimeException('Unsupported snapshot driver.');
            foreach($manifest['files'] as $name=>$digest){$bytes=$zip->getFromName($name);if($bytes===false||!hash_equals($digest,hash('sha256',$bytes)))throw new RuntimeException('Restored media hash mismatch.');}
            $restored=null;$zip->close();
            DB::table('backup_records')->where('id',$id)->update(['status'=>'restore_verified','restore_tested_at'=>now(),'message'=>$manifest['driver'].' snapshot restored in isolated scratch DB; media checksums verified.','updated_at'=>now()]);Audit::record('backup.restore_verified','backup',$id);
        }finally{$restored=null;if($zip)try{$zip->close();}catch(\Throwable){}foreach(glob($directory.'/*')?:[] as $file)if(is_file($file))unlink($file);rmdir($directory);}
    }
    private function key(): string
    {
        if(!function_exists('sodium_crypto_secretstream_xchacha20poly1305_init_push'))throw new RuntimeException('Sodium extension is required for encrypted backups.');
        $key=base64_decode((string)config('nansan.backup_key'),true);
        if($key===false||strlen($key)!==SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES)throw new RuntimeException('A separate 32-byte base64 backup encryption key must be configured.');return $key;
    }
    private function encrypt(string $source,string $target,string $key): void
    {
        [$state,$header]=sodium_crypto_secretstream_xchacha20poly1305_init_push($key);$in=fopen($source,'rb');$out=fopen($target,'wb');fwrite($out,'NANSANBK1'.$header);
        try{while(!feof($in)){$chunk=fread($in,65536);if($chunk==='')break;$encrypted=sodium_crypto_secretstream_xchacha20poly1305_push($state,$chunk);fwrite($out,pack('N',strlen($encrypted)).$encrypted);}$final=sodium_crypto_secretstream_xchacha20poly1305_push($state,'','',SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);fwrite($out,pack('N',strlen($final)).$final);}finally{fclose($in);fclose($out);}
    }
    private function decrypt(string $source,string $target,string $key): void
    {
        $in=fopen($source,'rb');$out=fopen($target,'wb');$final=false;
        try{
            if(fread($in,9)!=='NANSANBK1')throw new RuntimeException('Unexpected backup format.');
            $header=fread($in,SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);$state=sodium_crypto_secretstream_xchacha20poly1305_init_pull($header,$key);
            while(!feof($in)){$prefix=fread($in,4);if($prefix==='')break;if(strlen($prefix)!==4)throw new RuntimeException('Truncated frame header.');$length=unpack('N',$prefix)[1];if($length<17||$length>65553)throw new RuntimeException('Invalid encrypted frame size.');$frame=fread($in,$length);if(strlen($frame)!==$length)throw new RuntimeException('Truncated backup frame.');$result=sodium_crypto_secretstream_xchacha20poly1305_pull($state,$frame);if($result===false||$final)throw new RuntimeException('Backup authentication failed.');[$chunk,$tag]=$result;fwrite($out,$chunk);$final=$tag===SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;}
            if(!$final)throw new RuntimeException('Backup is missing the authenticated final frame.');
        }finally{fclose($in);fclose($out);}
    }
}
