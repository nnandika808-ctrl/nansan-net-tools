import fs from 'node:fs';
import path from 'node:path';
import {spawn,spawnSync} from 'node:child_process';
const root=process.cwd();fs.mkdirSync('.runtime',{recursive:true});
const db=path.join(root,'.runtime','e2e.sqlite');
if(!fs.existsSync(db))fs.closeSync(fs.openSync(db,'a'));
if(fs.lstatSync(db).isSymbolicLink()||fs.realpathSync(path.dirname(db))!==path.resolve(root,'.runtime'))throw new Error('E2E database must be a regular file in this workspace runtime directory.');
const php=process.env.PHP_BINARY||(process.platform==='win32'?path.join(root,'.runtime/tools/php/php.exe'):'php');
const env={...process.env,APP_ENV:'local',APP_URL:'http://127.0.0.1:8001',DB_CONNECTION:'sqlite',DB_DATABASE:db,SESSION_SECURE_COOKIE:'false',CACHE_STORE:'array',QUEUE_CONNECTION:'sync',TURNSTILE_ENABLED:'false',SITE_INDEXABLE:'false'};
for(const args of [['artisan','migrate:fresh','--force'],['artisan','db:seed','--force'],['tests/Support/bootstrap-e2e.php']]){
 const result=spawnSync(php,args,{env,stdio:'inherit'});if(result.status!==0)process.exit(result.status??1);
}
const log=fs.openSync(path.join(root,'.runtime','e2e-server.log'),'w');
const server=spawn(php,['-S','127.0.0.1:8001',path.join(root,'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],{cwd:path.join(root,'public'),env,stdio:['ignore',log,log]});
process.on('SIGTERM',()=>server.kill());process.on('SIGINT',()=>server.kill());server.on('exit',code=>process.exit(code??0));
