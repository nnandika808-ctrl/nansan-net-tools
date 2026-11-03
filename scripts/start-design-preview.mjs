import fs from 'node:fs';
import path from 'node:path';
import http from 'node:http';
import {fileURLToPath} from 'node:url';
import {spawn,execFileSync} from 'node:child_process';

const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
const php=path.join(root,'.runtime/tools/php/php.exe');
const state=JSON.parse(execFileSync(php,[path.join(root,'scripts/local-preview-state.php')],{cwd:root,encoding:'utf8',windowsHide:true}));
const ready=()=>new Promise(resolve=>{const request=http.get({host:'127.0.0.1',port:8000,path:'/login',timeout:1200,agent:false},response=>{let body='';response.on('data',d=>body+=d);response.on('end',()=>resolve(response.statusCode===200&&body.includes('NANSAN NET')));});request.on('error',()=>resolve(false));request.on('timeout',()=>{request.destroy();resolve(false);});});
if(await ready()){console.log('Design preview ready: http://127.0.0.1:8000');process.exit(0);}
fs.mkdirSync(path.join(root,'.runtime'),{recursive:true});const log=fs.openSync(path.join(root,'.runtime/design-preview.log'),'a');
const child=spawn(php,['-S','127.0.0.1:8000',path.join(root,'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],{cwd:path.join(root,'public'),env:{...process.env,APP_ENV:'local',APP_URL:'http://127.0.0.1:8000',DB_CONNECTION:'sqlite',DB_DATABASE:state.database,SESSION_SECURE_COOKIE:'false',CACHE_STORE:'file',QUEUE_CONNECTION:'database',TURNSTILE_ENABLED:'false',SITE_INDEXABLE:'false'},detached:true,windowsHide:true,stdio:['ignore',log,log]});
fs.closeSync(log);child.unref();
for(let attempt=0;attempt<25;attempt++){if(await ready()){console.log('Design preview ready: http://127.0.0.1:8000');process.exit(0);}await new Promise(resolve=>setTimeout(resolve,200));}
throw new Error('Local preview did not become ready. Inspect .runtime/design-preview.log.');
