<?php
namespace App\Console\Commands;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
class CreateAdmin extends Command
{
    protected $signature='nansan:admin {username : Username admin baru}';
    protected $description='Buat Admin pertama melalui prompt password tersembunyi';
    public function handle(): int
    {
        $username=strtolower($this->argument('username')); $password=$this->secret('Password (minimal 12 karakter, huruf dan angka)');
        $validation=Validator::make(['username'=>$username,'password'=>$password],['username'=>['required','min:3','max:32','regex:/^[a-z0-9_]+$/','unique:users,username'],'password'=>['required',Password::min(12)->letters()->numbers()]]);
        if($validation->fails()) { $this->error($validation->errors()->first()); return self::FAILURE; }
        if(!hash_equals($password,(string)$this->secret('Konfirmasi password'))) { $this->error('Konfirmasi tidak cocok.'); return self::FAILURE; }
        $user=User::create(['username'=>$username,'password'=>$password]); $user->forceFill(['role'=>'admin'])->save(); Audit::record('operator.admin_created','user',$user->id);
        $this->info('Admin dibuat. Buat dan simpan kode pemulihan melalui halaman akun.'); return self::SUCCESS;
    }
}
