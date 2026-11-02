import fs from 'node:fs/promises';
import {call} from './reference-audit.mjs';
await call('browser_tabs',{action:'select',index:0});await call('browser_navigate',{url:'https://buananet.com/pcc.html'});
const cases=JSON.parse(await fs.readFile('docs/reference/evidence/behavior.json','utf8')).filter(c=>c.id==='G03');
const observations=[];
for(const test of cases){
 const result=await call('browser_evaluate',{function:`() => {
 for(const [id,value] of Object.entries(${JSON.stringify(test.inputDelta)})){const e=document.getElementById(id);if(typeof value==='boolean')e.checked=value;else e.value=value;e.dispatchEvent(new Event('input',{bubbles:true}));e.dispatchEvent(new Event('change',{bubbles:true}));}
 document.getElementById('generate-script-btn').click();const text=document.getElementById('script-output').value;let path='';const entries=[];
 for(const line of text.split('\\n')){const value=line.trim();if(value.startsWith('/')){path=value;continue;}if(!/^(add|set) /.test(value))continue;const fields={};for(const m of value.matchAll(/([a-z-]+)=("(?:\\\\.|[^"\\\\])*"|[^\\s]+)/g))if(m[1]!=='comment')fields[m[1]]=m[2].replace(/^"|"$/g,'');if(/(?:^| )fib(?: |$)/.test(value))fields.fib=true;entries.push({path,operation:value.split(' ')[0],fields});}
 const input=Object.fromEntries([...document.querySelectorAll('input,select')].filter(e=>e.id&&!/chat|password|email|captcha/.test(e.id)).map(e=>[e.id,e.type==='checkbox'?e.checked:e.value]));
 return {input,entries};
 }`});
 const text=result.content.find(c=>c.type==='text'&&c.text.includes('### Result')).text;
 const observed=JSON.parse(text.match(/### Result\s+([\s\S]*?)(?:\n###|$)/)[1]);
 observations.push({test_id:test.test,url:test.url,observed_at:'2026-11-03',status:'REFERENCE_OBSERVED; IMPLEMENTATION_UNVERIFIED',...observed});
 console.log(test.test+': '+observed.entries.length+' rules observed');
}
await fs.writeFile('docs/reference/evidence/G03-cases-semantics.json',JSON.stringify(observations,null,2)+'\n');
console.log(JSON.stringify(observations.filter(o=>/recursive|interface/.test(o.test_id)).map(o=>({test_id:o.test_id,entries:o.entries}))));
