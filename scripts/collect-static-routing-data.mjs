import fs from 'node:fs/promises';
import crypto from 'node:crypto';

const evidence=JSON.parse(await fs.readFile('docs/reference/evidence/G01-cases-semantics.json','utf8'));
const urls=[...new Set(evidence.cases.flatMap(c=>c.steps.flat()).filter(e=>e.path==='/tool'&&e.operation==='fetch').map(e=>e.fields.url))];
await fs.mkdir('.runtime/reference-datasets',{recursive:true});
const observations=[];
let next=0;
async function collect(){
  while(next<urls.length){
    const url=urls[next++],parsed=new URL(url);
    if(parsed.origin!=='https://buananet.com'||!/^\/mikrotik-routeros-rsc\/(?:tls-host|content)\/Mikrotik-[A-Za-z0-9-]+\.rsc$/.test(parsed.pathname))throw new Error('Unexpected dataset source');
    const mode=parsed.pathname.split('/')[2],application=parsed.pathname.split('/')[3].slice(9,-4);
    try{
      const response=await fetch(url,{redirect:'error',signal:AbortSignal.timeout(20000)});
      if(!response.ok)throw new Error('HTTP '+response.status);
      const body=await response.text();
      if(Buffer.byteLength(body)>2_000_000||/<(?:html|script|!doctype)/i.test(body))throw new Error('Unexpected dataset body');
      if(!body.includes('/ip firewall'))throw new Error('No expected firewall commands');
      const filename=mode+'-'+application+'.rsc';await fs.writeFile('.runtime/reference-datasets/'+filename,body);
      observations.push({application,mode,url,filename,sha256:crypto.createHash('sha256').update(body).digest('hex'),bytes:Buffer.byteLength(body),status:'DOWNLOADED_NOT_EXECUTED'});
    }catch(error){observations.push({application,mode,url,status:'UNAVAILABLE',error:error.message});}
  }
}
await Promise.all(Array.from({length:4},collect));
observations.sort((a,b)=>(a.application+'/'+a.mode).localeCompare(b.application+'/'+b.mode));
await fs.writeFile('.runtime/G01-dataset-downloads.json',JSON.stringify({observed_at:new Date().toISOString(),authorization:'Owner reported permission to follow Buananet and explicitly requested all applications/data copied in this conversation; no independent license/legal attestation',sources:observations},null,2)+'\n');
console.log(JSON.stringify({requested:urls.length,downloaded:observations.filter(o=>o.status==='DOWNLOADED_NOT_EXECUTED').length,unavailable:observations.filter(o=>o.status==='UNAVAILABLE')}));
