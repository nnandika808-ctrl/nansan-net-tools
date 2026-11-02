import fs from 'node:fs';
import {execFileSync} from 'node:child_process';
const files=execFileSync('git',['ls-files','--cached','--others','--exclude-standard','-z'],{encoding:'utf8'}).split('\0').filter(Boolean);
const forbidden=[/^\.env$/,/^\.runtime\//,/^\.cache\//,/^vendor\//,/^node_modules\//,/\.sqlite(?:-|$)/,/storage\/.*\.(?:log|nnb)$/,/bootstrap\/cache\/.*\.php$/,/storage-state.*\.json$/];
const secrets=[/gh[pousr]_[A-Za-z0-9]{20,}/,/github_pat_[A-Za-z0-9_]{30,}/,/-----BEGIN (?:RSA |OPENSSH |EC )?PRIVATE KEY-----/,/^APP_KEY=base64:.+/m];
const failures=[];
for(const file of new Set(files)){
 if(forbidden.some(re=>re.test(file))){failures.push({file,reason:'private runtime path'});continue;}
 if(!fs.existsSync(file)||!fs.statSync(file).isFile())continue;
 const content=fs.readFileSync(file,'utf8');
 if(secrets.some(re=>re.test(content)))failures.push({file,reason:'credential-like material'});
}
if(failures.length){console.error(JSON.stringify(failures));process.exitCode=1;}else console.log(files.length+' source paths checked; no forbidden runtime paths or detected credential material.');
