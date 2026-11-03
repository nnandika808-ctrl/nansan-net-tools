import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import net from 'node:net';
import assert from 'node:assert/strict';
import {spawn,execFileSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';

const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
const sha=execFileSync('git',['rev-parse','HEAD'],{cwd:root,encoding:'utf8'}).trim();
const output=path.join(root,'.runtime','hosting-release-'+sha.slice(0,12));
const site=path.join(output,'installed');
if(!fs.existsSync(path.join(output,'build.json'))||fs.existsSync(site))throw Error('Only a fresh isolated release may be smoke-tested.');
const php=process.env.PHP_BINARY||(process.platform==='win32'?path.join(root,'.runtime/tools/php/php.exe'):'php');
const build=JSON.parse(fs.readFileSync(path.join(output,'build.json'),'utf8'));
assert.equal(build.commit,sha);
execFileSync(php,[path.join(root,'deployment/extract-hosting-release.php'),path.join(output,build.archive),site,build.sha256],{cwd:root,stdio:'pipe',windowsHide:true});
const database=path.join(site,'database/hosting-smoke.sqlite');fs.writeFileSync(database,'',{flag:'wx'});
const port=await new Promise(resolve=>{const server=net.createServer();server.listen(0,'127.0.0.1',()=>{const value=server.address().port;server.close(()=>resolve(value));});});
const env={...process.env,APP_ENV:'production',APP_DEBUG:'false',APP_KEY:'base64:'+crypto.randomBytes(32).toString('base64'),APP_URL:'http://127.0.0.1:'+port,DB_CONNECTION:'sqlite',DB_DATABASE:database,CACHE_STORE:'file',QUEUE_CONNECTION:'database',SESSION_DRIVER:'database',SESSION_ENCRYPT:'true',SESSION_SECURE_COOKIE:'false',SESSION_COOKIE:'hosting_smoke_only',TURNSTILE_ENABLED:'true',SITE_INDEXABLE:'false'};
const artisan=args=>execFileSync(php,['artisan',...args],{cwd:site,env,stdio:'pipe',encoding:'utf8',windowsHide:true});
artisan(['migrate','--force']);artisan(['db:seed','--force']);artisan(['db:seed','--force']);artisan(['optimize']);
const manifest=JSON.parse(fs.readFileSync(path.join(site,'public/build/manifest.json'),'utf8'));
const server=spawn(php,['-S','127.0.0.1:'+port,path.join(site,'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],{cwd:path.join(site,'public'),env,stdio:'ignore',windowsHide:true});
try{
 let ready=false;
 for(let attempt=0;attempt<60;attempt++){try{ready=(await fetch(env.APP_URL+'/up')).ok;if(ready)break;}catch{}await new Promise(resolve=>setTimeout(resolve,150));}
 assert.equal(ready,true,'Fresh no-dev release must boot.');
 for(const url of ['/','/login','/tools','/docs/verification','/build/'+manifest['resources/js/app.tsx'].file]){
  const response=await fetch(env.APP_URL+url);assert.equal(response.status,200,url);
  if(!url.startsWith('/build/')){const html=await response.text();assert(!html.includes(env.APP_KEY));assert(!html.includes('@vite/client'));}
 }
 for(const url of ['/.env','/composer.json','/database/hosting-smoke.sqlite','/storage/logs/laravel.log','/release.json'])assert.equal((await fetch(env.APP_URL+url)).status,404,url);
 assert.equal((await fetch(env.APP_URL+'/admin',{redirect:'manual'})).status,302);
 const report={commit:sha,result:'passed',archive_extracted_and_verified:true,production_environment:true,dependencies:'no-dev',fresh_migrations:true,idempotent_seeding:true,optimized_boot:true,private_paths_denied:true,admin_gate:true,scope:'isolated SQLite HTTP smoke; real hosting acceptance still required'};
 fs.writeFileSync(path.join(output,'smoke.json'),JSON.stringify(report,null,2));console.log(JSON.stringify(report));
}finally{server.kill();}
