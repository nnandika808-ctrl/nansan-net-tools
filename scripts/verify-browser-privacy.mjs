import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import {call} from './reference-audit.mjs';

const text=result=>result.content.filter(block=>block.type==='text').map(block=>block.text).join('\n');
const navigation=await call('browser_navigate',{url:'http://127.0.0.1:8091/login'});
assert.ok(!text(navigation).includes('### Snapshot'),'Automatic DOM snapshots must be disabled.');
await new Promise(resolve=>setTimeout(resolve,1000));
for(const [name,args] of [['browser_evaluate',{function:'() => "must not execute on an authentication screen"'}],['browser_take_screenshot',{}]]){
 await assert.rejects(()=>call(name,args),/Authentication screen is private/);
}
await call('browser_navigate',{url:'http://127.0.0.1:8091/tools'});await new Promise(resolve=>setTimeout(resolve,500));
const result=await call('browser_evaluate',{function:'() => document.title'});assert.ok(text(result).includes('Tools'));assert.ok(!text(result).includes('### Snapshot'));
const report={observed_at:new Date().toISOString(),status:'PASSED',automatic_snapshots:'none',authentication_dom_inspection:'blocked',authentication_screenshots:'blocked',ordinary_page_inspection:'allowed',real_credentials_entered:false};
await fs.writeFile('docs/staging/browser-privacy.json',JSON.stringify(report,null,2)+'\n');console.log(JSON.stringify(report));
