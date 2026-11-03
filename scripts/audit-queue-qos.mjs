import fs from 'node:fs/promises';
import {call} from './reference-audit.mjs';
import {parse} from './routeros-observation-parser.mjs';

const schema=JSON.parse(await fs.readFile('resources/generators/G02.json','utf8'));
const apps=schema.fields.find(f=>f.key==='Queue-List').options.filter(o=>!o.disabled&&o.value).map(o=>o.value);
const make=(app,queue,matcher,name,priority='8',limit='20M',suffix='')=>({id:app.toLowerCase()+'-'+queue.toLowerCase()+'-'+matcher+suffix,input:{'Queue-Options':queue,'Queue-List':app,'Queue-Name':name,Priority:priority,'Max-Limit':limit,'match-condition':matcher}});
const cases=apps.flatMap(app=>['Queue-Simple','Queue-Tree'].flatMap(queue=>['tls-host','content'].map(matcher=>make(app,queue,matcher,'Audit '+app))));
for(const queue of ['Queue-Simple','Queue-Tree']){
  for(let priority=1;priority<=8;priority++)cases.push(make('Youtube',queue,'tls-host','Priority '+priority,String(priority),'50M','-priority-'+priority));
  for(const limit of ['0','512k','1G','1000000','1.5M'])cases.push(make('Tiktok',queue,'content','Rate '+limit,'3',limit,'-rate-'+limit));
}
const observations=[],raw=[];
for(let start=0;start<cases.length;start+=8){
  const batch=cases.slice(start,start+8);
  const result=await call('browser_evaluate',{function:`async () => {
    const rows=[];for(const test of ${JSON.stringify(batch)}){
      for(const [id,value] of Object.entries(test.input)){const e=document.getElementById(id);if(!e||e.disabled)throw new Error('Field inaccessible: '+id);e.value=value;e.dispatchEvent(new Event('input',{bubbles:true}));e.dispatchEvent(new Event('change',{bubbles:true}));}
      const button=document.getElementById('bt');if(!button||button.disabled)throw new Error('Generate inaccessible');button.click();await new Promise(r=>setTimeout(r,30));
      rows.push({...test,step1:document.getElementById('list-output-content').innerText,step2:document.getElementById('list-output-content2').innerText});
    }return rows;
  }`});
  const text=result.content.find(b=>b.type==='text'&&b.text.includes('### Result'))?.text;if(!text)throw new Error('Missing observation');
  const rows=JSON.parse(text.match(/### Result\s+([\s\S]*?)(?:\n###|$)/)[1]);
  for(const row of rows){
    raw.push(row);
    const dataset=row.step1.match(/\/tool fetch url="([^"]+)" dst-path="([^"]+)"/);if(!dataset)throw new Error('Dataset reference changed');
    observations.push({test_id:row.id,input:row.input,dataset_url:dataset[1],dataset_file:dataset[2],configuration:parse(row.step2).filter(e=>e.operation==='add')});
  }
  console.log('Observed '+observations.length+'/'+cases.length+' Queue QoS cases.');
}
await fs.writeFile('.runtime/G02-rendered-outputs.json',JSON.stringify(raw,null,2)+'\n');
await fs.writeFile('docs/reference/evidence/G02-cases-semantics.json',JSON.stringify({generator:'G02',source:schema.source,observed_at:new Date().toISOString(),method:'Authenticated manual Premium session; rendered DOM only; no generator source extraction',scope:'188 app/queue/matcher combinations plus 26 priority/rate cases; ordered queue/mangle/LOCAL-IP parameters, not exact text or full shaping effects',cases:observations},null,2)+'\n');
console.log('Saved 214 functional projections; rendered full output remains ignored.');
