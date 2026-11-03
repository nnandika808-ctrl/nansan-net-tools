import fs from 'node:fs/promises';
import crypto from 'node:crypto';
import assert from 'node:assert/strict';
import {Client} from 'ssh2';
import {chromium} from '@playwright/test';
import {parse} from './routeros-observation-parser.mjs';

const host=process.argv[2],fingerprint=process.argv[3],baseline=process.argv[4]==='--baseline';
if(host!=='192.168.1.136'||fingerprint!=='7298122de007a6a99b20aabf6896ab2cd1d3437caad33f17501f6a69fead49ee'||process.argv.length>5||(process.argv[4]&&!baseline))throw new Error('Only the authorized pinned private staging endpoint is accepted.');
const canonical=value=>Array.isArray(value)?value.map(canonical):value&&typeof value==='object'?Object.fromEntries(Object.entries(value).sort(([a],[b])=>a.localeCompare(b)).map(([key,item])=>[key,canonical(item)])):value;
console.log('SSH password input ready (hidden).');if(process.stdin.isTTY)process.stdin.setRawMode(true);process.stdin.resume();let secret='';
process.stdin.on('data',chunk=>{
 secret+=chunk.toString();if(secret.includes('\u0003'))process.exit(130);if(!/[\r\n]/.test(secret))return;
 process.stdin.removeAllListeners('data');process.stdin.pause();if(process.stdin.isTTY)process.stdin.setRawMode(false);
 const client=new Client();client.on('error',error=>{console.error('Private staging SSH unavailable: '+error.message);process.exitCode=1;});
 client.on('ready',async()=>{
  secret='';let browser,identity;const report={observed_at:new Date().toISOString(),status:'RUNNING',scope:'Private staging CLI generated-parameter comparisons plus real browser preview. No router execution or current game-traffic proof.',cases:[]};
  const command=(action,input='')=>new Promise((resolve,reject)=>client.exec('runuser -u nansan-staging -- env PHP_INI_SCAN_DIR=/www/wwwroot/nansan-tools-staging/php-conf.d /www/server/php/84/bin/php -d disable_functions= /tmp/nansan-staging-smoke.php '+action,(error,stream)=>{
   if(error)return reject(error);let output='',stderr='';stream.on('data',d=>output+=d);stream.stderr.on('data',d=>stderr+=d);stream.end(input);
   stream.on('close',code=>{if(code)return reject(new Error('Staging '+action+' failed: '+stderr.slice(-500)));try{resolve(JSON.parse(output));}catch{reject(new Error('Invalid private audit response; details withheld.'));}});
  }));
  try{
   await new Promise((resolve,reject)=>client.sftp((error,sftp)=>error?reject(error):sftp.fastPut('deployment/staging-smoke.php','/tmp/nansan-staging-smoke.php',error=>error?reject(error):resolve())));
   const observations=JSON.parse(await fs.readFile('docs/reference/evidence/G05-cases-semantics.json','utf8'));
   const fresh=JSON.parse(await fs.readFile('docs/reference/evidence/G05-port-reaudit.json','utf8'));
   report.reference_checked_at=fresh.observed_at;
   const failed=new Set(fresh.games.filter(game=>game.status==='FAILED_REFERENCE_SYNTAX').map(game=>game.id));
   const input=observations.cases.map(row=>({id:row.test_id,input:{...row.input,...Object.fromEntries(row.selected.map(id=>[id,true]))}}));
   const actual=await command('game-port-audit',JSON.stringify(input));report.release=actual.release;
   for(const expected of observations.cases){
    const result=actual.cases.find(row=>row.id===expected.test_id);assert.ok(result,expected.test_id);
    const record={id:expected.test_id,games:expected.selected,ros:expected.input['ros-list']};
    if(expected.selected.some(id=>failed.has(id))){assert.equal(result.status,423);record.status='BLOCKED_REFERENCE_SYNTAX';}
    else{
     assert.equal(result.status,200,expected.test_id);
     const configuration=parse(result.script).filter(row=>row.operation==='add');
     assert.deepEqual(canonical(configuration),canonical(expected.configuration),expected.test_id);
     record.status='ORDERED_PARAMETERS_MATCHED';record.script_sha256=crypto.createHash('sha256').update(result.script).digest('hex');
     record.ports=configuration.filter(row=>row.path==='/ip firewall raw').map(row=>({protocol:row.fields.protocol,ports:row.fields['dst-port']}));
    }
    report.cases.push(record);
   }
   assert.equal(report.cases.filter(row=>row.status==='ORDERED_PARAMETERS_MATCHED').length,220);assert.equal(report.cases.filter(row=>row.status==='BLOCKED_REFERENCE_SYNTAX').length,6);
   identity=await command('start');browser=await chromium.launch({channel:'chrome',headless:true});const context=await browser.newContext({viewport:{width:1440,height:1000},reducedMotion:'reduce'});const page=await context.newPage();const origin='http://127.0.0.1:8091';
   await page.goto(origin+'/login');await page.getByLabel('Username',{exact:true}).fill(identity.username);await page.getByLabel('Password',{exact:true}).fill(identity.password);await page.getByRole('button',{name:'Masuk',exact:true}).click();await page.waitForURL('**/dashboard');identity.password='';
   if(baseline)await page.goto(origin+'/admin/tools/static-routing-games/preview');
   else{await page.goto(origin+'/tools/static-routing-games');await page.getByRole('link',{name:'Buka preview Admin'}).click();await page.waitForURL('**/admin/tools/static-routing-games/preview');}
   report.browser={game_choices:await page.locator('.dynamic-fields input[type=checkbox]').count(),port_overview_rows:await page.locator('.game-port-values').count()};assert.equal(report.browser.game_choices,106);
   if(!baseline){assert.equal(report.browser.port_overview_rows,106);assert.ok((await page.locator('.game-option').first().innerText()).includes('10001-10094'));}
   await page.locator('#field-ip-gateway-input').fill('192.168.2.1');await page.locator('#field-ros-list').selectOption('ros7');await page.locator('#field-ArenaOfValor-Mobile').check();
   const preview=page.waitForResponse(response=>response.url().endsWith('/admin/tools/static-routing-games/preview')&&response.request().method()==='POST');await page.getByRole('button',{name:'Preview draft',exact:true}).click();assert.equal((await preview).status(),200);await page.locator('pre').waitFor();
   assert.ok((await page.locator('pre').innerText()).includes('10001-10094'));
   if(!baseline)await page.waitForFunction(()=>{const top=document.querySelector('.output-step pre')?.getBoundingClientRect().top;return typeof top==='number'&&top>=0&&top<innerHeight;});
   report.browser.output_present=true;report.browser.output_top=Math.round((await page.locator('.output').boundingBox()).y);report.browser.code_top=Math.round((await page.locator('.output-step pre').boundingBox()).y);report.browser.viewport_height=1000;
   if(!baseline){
    await page.getByLabel('Find Games',{exact:true}).fill('Hearthstone');await page.locator('#field-Hearthstone-PC').check();
    const denied=page.waitForResponse(response=>response.url().endsWith('/admin/tools/static-routing-games/preview')&&response.request().method()==='POST');await page.getByRole('button',{name:'Preview draft',exact:true}).click();assert.equal((await denied).status(),423);assert.equal(await page.locator('pre').count(),0);report.browser.malformed_source='blocked_423_and_old_output_cleared';
    await page.setViewportSize({width:390,height:844});await page.goto(origin+'/admin/tools/static-routing-games/preview');assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));report.browser.mobile_overflow='none';
    await page.locator('#field-ip-gateway-input').fill('192.168.2.1');await page.getByLabel('Find Games',{exact:true}).fill('Arena Of Valor');await page.locator('#field-ArenaOfValor-Mobile').check();
    const mobile=page.waitForResponse(response=>response.url().endsWith('/admin/tools/static-routing-games/preview')&&response.request().method()==='POST');await page.getByRole('button',{name:'Preview draft',exact:true}).click();assert.equal((await mobile).status(),200);
    await page.waitForFunction(()=>{const top=document.querySelector('.output-step pre')?.getBoundingClientRect().top;return typeof top==='number'&&top>=0&&top<innerHeight;});report.browser.mobile_code_visible=true;
   }
   report.status='PASSED';
  }catch(error){report.status='FAILED';report.error=error.message.slice(0,1400);process.exitCode=1;}
  finally{
   await browser?.close();if(identity)try{report.cleanup=await command('cleanup',JSON.stringify({id:identity.id,username:identity.username}));}catch{report.cleanup={temporary_identity_removed:false};process.exitCode=1;}
   report.completed_at=new Date().toISOString();await fs.writeFile(baseline?'.runtime/game-port-baseline.json':'docs/staging/game-port-comparison.json',JSON.stringify(report,null,2)+'\n');
   console.log(JSON.stringify({status:report.status,release:report.release,compared:report.cases.filter(row=>row.status==='ORDERED_PARAMETERS_MATCHED').length,blocked:report.cases.filter(row=>row.status==='BLOCKED_REFERENCE_SYNTAX').length,browser:report.browser,cleanup:report.cleanup,error:report.error}));client.end();
  }
 });
 client.connect({host,username:'root',password:secret.trim(),readyTimeout:15000,hostHash:'sha256',hostVerifier:hash=>hash===fingerprint});
});
