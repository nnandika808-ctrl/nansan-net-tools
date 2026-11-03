import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import {fileURLToPath} from 'node:url';
import {execFileSync,spawn} from 'node:child_process';

const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');const php=path.join(root,'.runtime/tools/php/php.exe');
const state=JSON.parse(execFileSync(php,[path.join(root,'scripts/local-preview-state.php')],{cwd:root,encoding:'utf8',windowsHide:true}));
const credential=path.join(root,'.runtime/design-preview-owner.credential.xml');
if(state.owner_exists&&fs.existsSync(credential)){console.log('Existing local preview owner preserved.');process.exit(0);}
if(state.admin_count||state.owner_exists||fs.existsSync(credential))throw new Error('Preview bootstrap requires an empty local Admin set and no existing preview credential.');
let password='Np7_'+crypto.randomBytes(24).toString('base64url');
await new Promise((resolve,reject)=>{const child=spawn('powershell.exe',['-NoProfile','-ExecutionPolicy','Bypass','-File',path.join(root,'scripts/design-preview-access.ps1'),'-Store'],{cwd:root,windowsHide:true,stdio:['pipe','pipe','pipe']});child.stdout.on('data',()=>{});child.stderr.on('data',()=>{});child.stdin.end(JSON.stringify({username:'nansan_preview',password}));child.on('error',reject);child.on('exit',code=>code?reject(new Error('Encrypted preview credential storage failed.')):resolve());});
try{
 await new Promise((resolve,reject)=>{const child=spawn(php,['artisan','nansan:admin','nansan_preview','--no-ansi'],{cwd:root,windowsHide:true,stdio:['pipe','pipe','pipe']});let output='',first=false,second=false;const timeout=setTimeout(()=>{child.kill();reject(new Error('Local hidden Artisan prompt timed out.'));},20000);const read=data=>{output+=data;if(!first&&output.includes('Password (minimal')){first=true;child.stdin.write(password+'\n');}if(!second&&output.includes('Konfirmasi password')){second=true;child.stdin.write(password+'\n');}};child.stdout.on('data',read);child.stderr.on('data',read);child.on('error',reject);child.on('exit',code=>{clearTimeout(timeout);code===0&&output.includes('Admin dibuat.')?resolve():reject(new Error('Artisan did not confirm local preview Admin creation.'));});});
 execFileSync(php,[path.join(root,'scripts/configure-design-preview.php')],{cwd:root,windowsHide:true,stdio:'pipe'});
 console.log('Local preview owner created through Artisan. Username: nansan_preview. Password stays encrypted in ignored runtime storage.');
}finally{password='';}
