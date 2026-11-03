import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {spawn} from 'node:child_process';

// Password is passed through stdin only, never arguments, environment or files.
const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
process.stdout.write('SSH password input ready (hidden).\n');
if(process.stdin.isTTY)process.stdin.setRawMode(true);
process.stdin.resume();let secret='';
process.stdin.on('data',chunk=>{
 secret+=chunk.toString();
 if(secret.includes('\u0003'))process.exit(130);
 if(!/[\r\n]/.test(secret))return;
 process.stdin.removeAllListeners('data');process.stdin.pause();
 if(process.stdin.isTTY)process.stdin.setRawMode(false);
 if(!secret.trim()){console.error('SSH password is required.');process.exitCode=1;return;}
 fs.mkdirSync(path.join(root,'.runtime'),{recursive:true});
 const log=fs.openSync(path.join(root,'.runtime/staging-connection.log'),'a');
 const child=spawn(process.execPath,[path.join(root,'scripts/staging-connection.mjs'),'192.168.1.136','7298122de007a6a99b20aabf6896ab2cd1d3437caad33f17501f6a69fead49ee'],{cwd:root,detached:true,windowsHide:true,stdio:['pipe',log,log]});
 fs.closeSync(log);
 child.on('error',()=>{secret='';console.error('Could not start private staging connection.');process.exitCode=1;});
 child.once('spawn',()=>{
  child.stdin.on('error',()=>{console.error('Private connection password input failed.');process.exitCode=1;});
  child.stdin.end(secret.trim()+'\n',()=>{secret='';child.stdin.destroy();child.unref();console.log(JSON.stringify({started:true,pid:child.pid,origin:'http://127.0.0.1:8091'}));});
 });
});
