import fs from 'node:fs';
import crypto from 'node:crypto';
import {spawn} from 'node:child_process';
import {Client} from 'ssh2';

const host=process.argv[2],fingerprint=process.argv[3];
if(host!=='192.168.1.136'||!fingerprint)throw new Error('Authorized staging endpoint and verified fingerprint required.');
console.log('SSH password input ready (hidden).');if(process.stdin.isTTY)process.stdin.setRawMode(true);process.stdin.resume();let secret='';
process.stdin.on('data',chunk=>{secret+=chunk;if(!/[\r\n]/.test(secret))return;process.stdin.removeAllListeners('data');process.stdin.pause();if(process.stdin.isTTY)process.stdin.setRawMode(false);
 const client=new Client();client.on('error',e=>{console.error(e.message);process.exitCode=1;});client.on('ready',async()=>{secret='';
  const base='runuser -u nansan-staging -- env PHP_INI_SCAN_DIR=/www/wwwroot/nansan-tools-staging/php-conf.d /www/server/php/84/bin/php -d disable_functions= ';
  try{
   await new Promise((resolve,reject)=>client.sftp((e,sftp)=>e?reject(e):sftp.fastPut('deployment/staging-smoke.php','/tmp/nansan-staging-smoke.php',e=>e?reject(e):resolve())));
   const state=await new Promise((resolve,reject)=>client.exec(base+'/tmp/nansan-staging-smoke.php owner-state',(e,stream)=>{if(e)return reject(e);let out='';stream.on('data',d=>out+=d);stream.on('close',()=>{try{resolve(JSON.parse(out));}catch{reject(new Error('Staging guard returned invalid owner state.'));}});}));
   const local=fs.existsSync('.runtime/staging-owner.credential.xml');
   if(state.username_taken){if(local){console.log('Existing staging owner and encrypted credential preserved.');return;}throw new Error('Username already exists; no account/password was modified.');}
   if(state.admin_count!==0||local)throw new Error('Owner bootstrap is restricted to an empty Admin set and no existing credential.');
   const password='Aa7_'+crypto.randomBytes(24).toString('base64url');
   await new Promise((resolve,reject)=>{
    const child=spawn('powershell.exe',['-NoProfile','-ExecutionPolicy','Bypass','-File','scripts/staging-admin-access.ps1','-Store'],{stdio:['pipe','pipe','pipe'],windowsHide:true});
    let diagnostic='';child.stdout.on('data',()=>{});child.stderr.on('data',d=>diagnostic+=d);child.stdin.end(JSON.stringify({username:'nansan',password}));child.on('exit',code=>code?reject(new Error('DPAPI credential storage failed.')):resolve());
   });
   await new Promise((resolve,reject)=>client.exec(base+'/www/wwwroot/nansan-tools-staging/current/artisan nansan:admin nansan --no-ansi',{pty:{term:'xterm',cols:180,rows:30}},(e,stream)=>{
    if(e)return reject(e);let text='',first=false,second=false;
    // Keep the entire prompt exchange in memory. It is never logged or persisted.
    stream.on('data',d=>{text+=d;if(!first&&text.includes('Password (minimal')){first=true;stream.write(password+'\n');}if(!second&&text.includes('Konfirmasi password')){second=true;stream.write(password+'\n');}});
    stream.on('close',code=>code===0&&text.includes('Admin dibuat.')?resolve():reject(new Error('Artisan owner creation did not confirm success; encrypted credential retained for diagnosis.')));
   }));
   console.log(JSON.stringify({staging_admin_created:true,username:'nansan',password_storage:'Windows DPAPI, current user only; ignored .runtime/staging-owner.credential.xml',production_modified:false}));
  }catch(error){console.error(error.message);process.exitCode=1;}finally{client.end();}
 });client.connect({host,username:'root',password:secret.trim(),hostHash:'sha256',hostVerifier:hash=>hash===fingerprint});
});
