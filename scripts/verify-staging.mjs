import fs from 'node:fs/promises';
import {Client} from 'ssh2';
import {chromium} from '@playwright/test';

const host=process.argv[2],fingerprint=process.argv[3];
if(host!=='192.168.1.136'||!fingerprint)throw new Error('The authorized private staging endpoint and verified fingerprint are required.');
process.stdout.write('SSH password input ready (hidden).\n');
if(process.stdin.isTTY)process.stdin.setRawMode(true);process.stdin.resume();let secret='';
process.stdin.on('data',chunk=>{secret+=chunk.toString();if(secret.includes('\u0003'))process.exit(130);if(!/[\r\n]/.test(secret))return;process.stdin.removeAllListeners('data');process.stdin.pause();if(process.stdin.isTTY)process.stdin.setRawMode(false);
 const client=new Client();client.on('error',e=>{console.error('Staging verification SSH failed: '+e.message);process.exitCode=1;});
 client.on('ready',async()=>{
  secret='';let identity,browser;const report={observed_at:new Date().toISOString(),scope:'Private staging only; temporary test identity; no production changes',status:'RUNNING'};
  const command=(action,input='')=>new Promise((resolve,reject)=>{
   const cli='runuser -u nansan-staging -- env PHP_INI_SCAN_DIR=/www/wwwroot/nansan-tools-staging/php-conf.d /www/server/php/84/bin/php -d disable_functions= /tmp/nansan-staging-smoke.php '+action;
   client.exec(cli,(error,stream)=>{if(error)return reject(error);let output='',failed='';stream.on('data',d=>{output+=d;});stream.stderr.on('data',d=>{failed+=d;});stream.end(input);stream.on('close',code=>{
    if(code)return reject(new Error('Private '+action+' failed: '+failed.slice(-1200)));
    try{resolve(JSON.parse(output));}catch{reject(new Error('Invalid '+action+' response: '+(action==='operations'?output.slice(-1200):'details withheld')));}
   });});
  });
  try{
   await new Promise((resolve,reject)=>client.sftp((e,sftp)=>e?reject(e):sftp.fastPut('deployment/staging-smoke.php','/tmp/nansan-staging-smoke.php',e=>e?reject(e):resolve())));
   report.operations=await command('operations');console.log(JSON.stringify(report.operations));
   identity=await command('start');
   browser=await chromium.launch({channel:'chrome',headless:true});const context=await browser.newContext();const page=await context.newPage();
   const origin='http://127.0.0.1:8091';
   for(const path of ['/','/tools','/docs','/robots.txt','/up']){const response=await context.request.get(origin+path);if(response.status()!==200)throw new Error('Public route '+path+' returned '+response.status());if(path==='/robots.txt'&&!(await response.text()).includes('Disallow: /'))throw new Error('Staging indexing guard missing.');}
   await page.goto(origin+'/login');await page.getByLabel('Username',{exact:true}).fill(identity.username);await page.getByLabel('Password',{exact:true}).fill(identity.password);await page.getByRole('button',{name:'Masuk',exact:true}).click();await page.waitForURL('**/dashboard');identity.password='';
   for(const path of ['/account','/admin','/admin/monitoring','/admin/tools/pcc-starlink/preview']){const response=await page.goto(origin+path);if(response.status()!==200)throw new Error('Authenticated route '+path+' returned '+response.status());}
   await page.getByRole('button',{name:'Preview draft',exact:true}).click();await page.locator('pre').filter({hasText:'STARLINK1'}).waitFor();
   report.http={public_routes:5,authenticated_routes:4,login:'passed',starlink_preview:'passed'};
   const elapsed=[];for(let i=0;i<12;i++){const started=performance.now();const response=await context.request.get(origin+'/tools');if(response.status()!==200)throw new Error('Sequential catalog probe failed.');elapsed.push(performance.now()-started);}elapsed.sort((a,b)=>a-b);
   report.performance={scope:'12 sequential catalog requests through SSH; not a capacity/load test',median_ms:Math.round(elapsed[6]),p95_ms:Math.round(elapsed[11])};
   report.status='PASSED';
  }catch(error){report.status='FAILED';report.error=error.message;process.exitCode=1;}
  finally{
   await browser?.close();if(identity){try{report.cleanup=await command('cleanup',JSON.stringify({id:identity.id,username:identity.username}));}catch{report.cleanup={temporary_identity_removed:false};process.exitCode=1;}}
   report.completed_at=new Date().toISOString();await fs.mkdir('docs/staging',{recursive:true});await fs.writeFile('docs/staging/verification.json',JSON.stringify(report,null,2)+'\n');console.log(JSON.stringify(report));client.end();
  }
 });
 client.connect({host,port:22,username:'root',password:secret.trim(),readyTimeout:15000,hostHash:'sha256',hostVerifier:hash=>hash===fingerprint});
});
