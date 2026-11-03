import fs from 'node:fs/promises';
import {call} from './reference-audit.mjs';
import {tokens,fields} from './routeros-observation-parser.mjs';

const schema=JSON.parse(await fs.readFile('resources/generators/G01.json','utf8'));
const apps=schema.fields.find(f=>f.key==='routing-list').options.filter(o=>!o.disabled&&o.value).map(o=>o.value);
const scenarios=apps.flatMap(app=>['ros6','ros7'].flatMap(ros=>['tls-host','content'].map(mode=>({
  id:ros+'-'+app.toLowerCase()+'-'+mode,
  input:{'routing-list':app,'ros-list':ros,'match-condition':mode,'gateway-option':ros==='ros6'?'192.168.1.1':'192.168.2.1','routing-mark':ros==='ros6'?'To-ISP-1':'To-ISP-2'},
}))));
function commandProjection(text){
  const result=[];
  for(const raw of text.split('\n')){
    const line=raw.trim();if(!line.startsWith('/'))continue;
    const parts=tokens(line);const op=parts.findIndex(p=>['add','set','remove','rem','fetch'].includes(p));
    if(line.startsWith('/import ')){result.push({path:'/import',operation:'import',fields:fields(parts.slice(1))});continue;}
    if(op<0)throw new Error('Unexpected rendered command');
    const path=parts.slice(0,op).join(' '),operation=parts[op]==='rem'?'remove':parts[op];
    if(path==='/file'){result.push({path,operation,file:parts[op+1]});continue;}
    if(operation==='remove'&&line.includes('[find')){
      const selector=line.match(/\[find(?: where)? comment="([^"]+)"\]/);
      if(!selector)throw new Error('Unexpected removal selector');
      result.push({path,operation,selector:{comment:selector[1]}});continue;
    }
    const values=fields(parts.slice(op+1));delete values.comment;
    result.push({path,operation,fields:values});
  }
  return result;
}
const observations=[],rawOutputs=[];
for(let offset=0;offset<scenarios.length;offset+=8){
  const batch=scenarios.slice(offset,offset+8);
  const result=await call('browser_evaluate',{function:`async () => {
    const results=[];
    for(const test of ${JSON.stringify(batch)}){
      for(const [id,value] of Object.entries(test.input)){
        const e=document.getElementById(id);if(!e||e.disabled)throw new Error('Generator field inaccessible: '+id);
        e.value=value;e.dispatchEvent(new Event('input',{bubbles:true}));e.dispatchEvent(new Event('change',{bubbles:true}));
      }
      const button=document.getElementById('bt');if(!button||button.disabled)throw new Error('Generate inaccessible');button.click();
      await new Promise(resolve=>setTimeout(resolve,30));
      const first=document.getElementById('list-output-content'),second=document.getElementById('list-output-content2');
      if(!first||!second)throw new Error('Observed output containers changed');
      results.push({...test,step1:first.innerText,step2:second.innerText});
    }return results;
  }`});
  const text=result.content.find(c=>c.type==='text'&&c.text.includes('### Result'))?.text;
  if(!text)throw new Error('Missing browser observation');
  const rows=JSON.parse(text.match(/### Result\s+([\s\S]*?)(?:\n###|$)/)[1]);
  for(const row of rows){
    rawOutputs.push(row);
    observations.push({test_id:row.id,input:row.input,steps:[commandProjection(row.step1),commandProjection(row.step2)]});
  }
  console.log('Observed '+observations.length+'/'+scenarios.length+' Static Routing combinations.');
}
await fs.writeFile('.runtime/G01-rendered-outputs.json',JSON.stringify(rawOutputs,null,2)+'\n');
await fs.writeFile('docs/reference/evidence/G01-cases-semantics.json',JSON.stringify({
  generator:'G01',source:schema.source,observed_at:new Date().toISOString(),method:'Authenticated manual Premium session; Playwright rendered DOM only; no source extraction or dataset download',
  scope:'Ordered wrapper-command parameters; descriptive comments excluded. Dataset contents, exact-text and full routing effects not compared.',cases:observations,
},null,2)+'\n');
console.log('Saved functional projections only; full rendered outputs remain ignored.');
