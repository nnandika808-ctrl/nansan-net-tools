import fs from 'node:fs/promises';
import {call} from './reference-audit.mjs';
import {parse,tokens,fields} from './routeros-observation-parser.mjs';
const url='https://buananet.com/mikrotik-pcc-lb-starlink.html';
await call('browser_navigate',{url});
const scenarios=[
 {id:'ros6-two-equal',ros:'v6',count:2,mode:'equal',weights:[1,1],netwatch:false},
 {id:'ros7-two-equal',ros:'v7',count:2,mode:'equal',weights:[1,1],netwatch:false},
 {id:'ros7-three-manual',ros:'v7',count:3,mode:'manual',weights:[2,1,1],netwatch:false},
 {id:'ros7-manual-unreduced',ros:'v7',count:2,mode:'manual',weights:[2,2],netwatch:false},
 {id:'ros7-three-netwatch',ros:'v7',count:3,mode:'manual',weights:[2,1,1],netwatch:true},
 {id:'ros6-two-netwatch',ros:'v6',count:2,mode:'equal',weights:[1,1],netwatch:true},
 {id:'ros7-ten-equal',ros:'v7',count:10,mode:'equal',weights:Array(10).fill(1),netwatch:false},
];
const observed=[];
for(const scenario of scenarios){
 const result=await call('browser_evaluate',{function:`() => {
 const test=${JSON.stringify(scenario)};
 for(const [id,value] of Object.entries({ros:test.ros,count:String(test.count),mode:test.mode,netwatchEnable:test.netwatch})){
  const e=document.getElementById(id);if(e.disabled)throw new Error('Premium session is required');if(typeof value==='boolean')e.checked=value;else e.value=value;e.dispatchEvent(new Event('change',{bubbles:true}));
 }
 const weights=[...document.querySelectorAll('input[type=number]')];weights.forEach((e,i)=>{e.value=test.weights[i];e.dispatchEvent(new Event('input',{bubbles:true}));});
 document.getElementById('netwatchTargets').value='';
 [...document.querySelectorAll('button')].find(e=>e.textContent.trim()==='Generate Script').click();
 return {script:document.getElementById('out').value,interfaces:[...document.querySelectorAll('input[placeholder="e.g., ether1 or wlan1"]')].map(e=>e.value),targets:document.getElementById('netwatchTargets').value};
 }`});
 const text=result.content.find(c=>c.type==='text'&&c.text.includes('### Result')).text;
 const data=JSON.parse(text.match(/### Result\s+([\s\S]*?)(?:\n###|$)/)[1]);
 const entries=parse(data.script);const dhcp=[];const watch=[];
 for(const entry of entries){
  if(entry.path==='/ip dhcp-client'){
   const script=entry.fields.script;
   const routes=[...script.matchAll(/\/ip route add ([^;]+);/g)].map(m=>fields(tokens(m[1])));
   const semantics={interface:entry.fields.interface,removesBeforeBoundCheck:script.indexOf('/ip route remove')<script.indexOf(':if'),boundCondition:/\$bound\s*=\s*1/.test(script),routes:routes.map(r=>({...r,gateway:r.gateway.includes('gateway-address')?'DYNAMIC_GATEWAY%'+entry.fields.interface:r.gateway}))};
   dhcp.push(semantics);entry.fields.script={...semantics};
  }
  if(entry.path==='/tool netwatch'){
   watch.push({host:entry.fields.host,up:entry.fields['up-script'],down:entry.fields['down-script']});
   // Retain functional script statements, but no descriptive third-party comments.
  }
 }
 observed.push({test_id:scenario.id,url,observed_at:'2026-11-03',input:{...scenario,interfaces:data.interfaces,targets:data.targets},entries,dhcp});
 console.log(JSON.stringify({test_id:scenario.id,dhcp,netwatch:watch,pcc:entries.filter(e=>e.fields['per-connection-classifier']).map(e=>e.fields['per-connection-classifier'])}));
}
await fs.writeFile('docs/reference/evidence/G04-cases-semantics.json',JSON.stringify(observed,null,2)+'\n');
