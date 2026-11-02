import fs from 'node:fs';
import crypto from 'node:crypto';
import {Client} from 'ssh2';
const host=process.argv[2],fingerprint=process.argv[3];
if(!host||!fingerprint)throw new Error('Pass the authorized host and previously observed SHA256 hex host fingerprint.');
const archive='.runtime/nansan-staging-source.tar.gz';
const digest=crypto.createHash('sha256').update(fs.readFileSync(archive)).digest('hex');
const remoteArchive='/tmp/nansan-staging-'+digest.slice(0,16)+'.tar.gz';
process.stdout.write('SSH password input ready (hidden).\n');
if(process.stdin.isTTY)process.stdin.setRawMode(true);process.stdin.resume();let secret='';
process.stdin.on('data',chunk=>{secret+=chunk.toString();if(secret.includes('\u0003'))process.exit(130);if(!/[\r\n]/.test(secret))return;process.stdin.removeAllListeners('data');process.stdin.pause();if(process.stdin.isTTY)process.stdin.setRawMode(false);
 const client=new Client();client.on('error',e=>{console.error('Staging SSH failed: '+e.message);process.exitCode=1;});
 client.on('ready',()=>{secret='';client.sftp(async(error,sftp)=>{if(error)throw error;
  const put=(from,to)=>new Promise((resolve,reject)=>sftp.fastPut(from,to,e=>e?reject(e):resolve()));
  try{
   await put(archive,remoteArchive);await put('deployment/provision-staging.py','/tmp/nansan-staging-provision.py');await put('.runtime/tools/composer.phar','/tmp/nansan-staging-composer.phar');
   console.log('Source whitelist archive and verified private Composer uploaded.');
   client.exec('python3 /tmp/nansan-staging-provision.py --archive '+remoteArchive+' --sha256 '+digest,(error,stream)=>{if(error)throw error;stream.on('data',data=>process.stdout.write(data));stream.stderr.on('data',data=>process.stdout.write(data));stream.on('close',code=>{process.exitCode=code??1;client.end();});});
  }catch(e){console.error('Staging transfer failed: '+e.message);process.exitCode=1;client.end();}
 });});
 client.connect({host,port:22,username:'root',password:secret.trim(),readyTimeout:15000,hostHash:'sha256',hostVerifier:hash=>hash===fingerprint});
});
