import fs from 'node:fs/promises';
import {call} from './reference-audit.mjs';
const findings=[];
await call('browser_resize',{width:390,height:844});
for(const id of ['G01','G02','G03','G04','G05']){
  const form=JSON.parse(await fs.readFile('docs/reference/evidence/'+id+'-form.json','utf8'));
  await call('browser_navigate',{url:form.url});
  const result=await call('browser_evaluate',{function:`() => ({viewport:innerWidth,documentWidth:document.documentElement.scrollWidth,horizontalOverflow:document.documentElement.scrollWidth>innerWidth,visibleInputs:[...document.querySelectorAll('input,select')].filter(e=>e.getClientRects().length&&!e.disabled).length})`});
  findings.push({id,url:form.url,observed_at:'2026-11-03',result});
  console.log(id+' mobile observed');
}
await call('browser_resize',{width:1440,height:1000});
await fs.writeFile('docs/reference/evidence/mobile.json',JSON.stringify(findings,null,2)+'\n');
