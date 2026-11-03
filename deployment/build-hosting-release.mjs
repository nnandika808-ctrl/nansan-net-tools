import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import {execFileSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';

const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
const run=(file,args,cwd=root)=>execFileSync(file,args,{cwd,encoding:'utf8',windowsHide:true,stdio:['ignore','pipe','pipe'],maxBuffer:12*1024*1024});
if(run('git',['status','--porcelain']).trim())throw Error('Commit source before building a hosting release.');
const sha=run('git',['rev-parse','HEAD']).trim();
const output=path.join(root,'.runtime','hosting-release-'+sha.slice(0,12));
if(fs.existsSync(output))throw Error('Release output already exists; preserve it and use a new checkout for rebuilding.');
const site=path.join(output,'site');fs.mkdirSync(site,{recursive:true});
const allowed=/^(app\/|bootstrap\/|config\/|database\/(migrations|seeders|factories)\/|public\/|resources\/|routes\/|artisan$|composer\.(json|lock)$|\.env\.example$|README\.md$|docs\/(AAPANEL-HOSTING|IMPLEMENTATION-STATUS)\.md$|deployment\/hosting\/)/;
const files=run('git',['ls-files','-z']).split('\0').filter(file=>file&&allowed.test(file));
function copy(from,to){
 if(fs.lstatSync(from).isSymbolicLink())throw Error('Symlinks are not allowed in release inputs.');
 fs.mkdirSync(path.dirname(to),{recursive:true});fs.copyFileSync(from,to);
}
for(const file of files){
 if(file.startsWith('bootstrap/cache/')&&file!=='bootstrap/cache/.gitignore')continue;
 if(file.startsWith('public/build/')||file==='public/hot')continue;
 copy(path.join(root,file),path.join(site,file));
}
if(!fs.existsSync(path.join(root,'public/build/manifest.json'))||fs.existsSync(path.join(root,'public/hot')))throw Error('Build production assets and stop the Vite dev marker before packaging.');
fs.cpSync(path.join(root,'public/build'),path.join(site,'public/build'),{recursive:true,verbatimSymlinks:true});
for(const directory of ['app/private','app/public','framework/cache/data','framework/sessions','framework/views','logs'])fs.mkdirSync(path.join(site,'storage',directory),{recursive:true});
const php=process.env.PHP_BINARY||(process.platform==='win32'?path.join(root,'.runtime/tools/php/php.exe'):'php');
const composer=process.env.COMPOSER_BINARY||'composer';
const args=['install','--no-dev','--prefer-dist','--no-interaction','--optimize-autoloader'];
console.log('Installing locked production dependencies in the isolated release directory.');
if(composer.endsWith('.phar'))run(php,[path.resolve(composer),...args],site);else run(composer,args,site);
run(php,['artisan','nansan:hosting-check'],site);
// Package discovery is reproducible at boot; ship no compiled environment/config.
for(const file of fs.readdirSync(path.join(site,'bootstrap/cache')))if(file.endsWith('.php'))fs.unlinkSync(path.join(site,'bootstrap/cache',file));
const archive=path.join(output,'nansan-net-tools-'+sha.slice(0,12)+'.zip');
run(php,[path.join(root,'deployment/zip-hosting-release.php'),site,archive,sha]);
const digest=crypto.createHash('sha256').update(fs.readFileSync(archive)).digest('hex');
fs.writeFileSync(archive+'.sha256',digest+'  '+path.basename(archive)+'\n');
fs.writeFileSync(path.join(output,'build.json'),JSON.stringify({commit:sha,archive:path.basename(archive),sha256:digest,production_dependencies:true,frontend_built:true},null,2));
console.log(JSON.stringify({output:path.relative(root,output),sha256:digest,commit:sha}));
