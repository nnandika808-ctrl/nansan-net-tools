import fs from 'node:fs/promises';
import {call} from './reference-audit.mjs';

const scenarios = [
  {id:'G01',url:'https://buananet.com/mikrotik-static-routing.html',cases:[
    {name:'ros6-youtube-tls',fields:{'gateway-option':'192.168.1.1'},click:'#bt'},
    {name:'ros7-youtube-content',fields:{'ros-list':'ros7','match-condition':'content','gateway-option':'192.168.2.1','routing-mark':'To-ISP-2'},click:'#bt'},
    {name:'ros7-tiktok-tls',fields:{'routing-list':'Tiktok','match-condition':'tls-host'},click:'#bt'},
  ]},
  {id:'G02',url:'https://buananet.com/mikrotik-queue-qos.html',cases:[
    {name:'simple-youtube-default',fields:{},click:'#bt'},
    {name:'tree-youtube-priority1',fields:{'Queue-Options':'Queue-Tree','Priority':'1','Max-Limit':'50M'},click:'#bt'},
    {name:'tree-tiktok-content',fields:{'Queue-List':'Tiktok','match-condition':'content'},click:'#bt'},
  ]},
  {id:'G03',url:'https://buananet.com/pcc.html',output:'#script-output',cases:[
    {name:'ros6-two-wan-equal',fields:{},click:'#generate-script-btn'},
    {name:'ros7-two-wan-equal',fields:{'routeros-version':'v7.xx'},click:'#generate-script-btn'},
    {name:'ros7-ratio-50-100',fields:{'bandwidth-ratio-checkbox':true,'speed-isp-1':'50','speed-isp-2':'100'},click:'#generate-script-btn'},
    {name:'ros7-recursive',fields:{'recursive-checkbox':true,'ip-public-check-isp-1':'1.1.1.1','ip-public-check-isp-2':'8.8.8.8'},click:'#generate-script-btn'},
    {name:'ros7-interface-target',fields:{'local-target-type':'In. Interface','interface-target':'bridge','bandwidth-ratio-checkbox':false,'recursive-checkbox':false,'pccType':'src-address'},click:'#generate-script-btn'},
  ]},
  {id:'G04',url:'https://buananet.com/mikrotik-pcc-lb-starlink.html',output:'#out',cases:[
    {name:'ros6-two-equal',fields:{},click:'button[onclick*="generate"]'},
    {name:'ros7-three-manual',fields:{ros:'v7',count:'3',mode:'manual'},click:'button[onclick*="generate"]',ratio:[2,1,1]},
    {name:'ros7-netwatch',fields:{netwatchEnable:true},click:'button[onclick*="generate"]'},
  ]},
  {id:'G05',url:'https://buananet.com/static-routing-games.html',cases:[
    {name:'ros6-one-game',fields:{'ip-gateway-input':'192.168.2.1','ArenaOfValor-Mobile':true},click:'button[onclick*="generate"]'},
    {name:'ros7-two-games',fields:{'ros-list':'ros7','PUBG-PC':true},click:'button[onclick*="generate"]'},
  ]},
];

await fs.mkdir('docs/reference/evidence',{recursive:true});
const observations=[];
for(const scenario of scenarios) {
  await call('browser_navigate',{url:scenario.url});
  for(const test of scenario.cases) {
    try {
      const expression = `async () => {
        const fields=${JSON.stringify(test.fields)};
        for(const [id,value] of Object.entries(fields)){
          const element=document.getElementById(id);
          if(!element) throw new Error('Missing observed field: '+id);
          if(typeof value==='boolean') element.checked=value; else element.value=value;
          element.dispatchEvent(new Event('input',{bubbles:true}));
          element.dispatchEvent(new Event('change',{bubbles:true}));
        }
        const ratio=${JSON.stringify(test.ratio??null)};
        if(ratio){const elements=[...document.querySelectorAll('input[type=number]')]; if(elements.length!==ratio.length) throw new Error('Unverified ratio selectors'); elements.forEach((e,i)=>{e.value=ratio[i];e.dispatchEvent(new Event('input',{bubbles:true}));});}
        let button=document.querySelector(${JSON.stringify(test.click)});
        if(!button) button=[...document.querySelectorAll('button')].find(e=>/^Generate( Script)?$/.test(e.textContent.trim()));
        if(!button||button.disabled) throw new Error('Generator button inaccessible');
        button.click();
        await new Promise(resolve=>setTimeout(resolve,200));
        const out=${JSON.stringify(scenario.output??null)};
        const text=out ? document.querySelector(out).value : document.body.innerText;
        const commands=[...text.matchAll(/(?:^|\\n)\\s*(\\/[a-z][a-z0-9 -]*)(?=\\n|\\s+(?:add|set|remove|rem|:|url=))/g)].map(m=>m[1].trim());
        const pcc=[...text.matchAll(/per-connection-classifier=?"?([a-z-]+):(\\d+)\\/(\\d+)/g)].map(m=>({classifier:m[1],denominator:Number(m[2]),remainder:Number(m[3])}));
        return {outputLength:text.length,lineCount:text.split('\\n').length,commandPaths:[...new Set(commands)],pcc,
          hasRos6RoutingMark:/routing-mark=/.test(text),hasRos7RoutingTable:/routing-table=/.test(text),
          hasDhcpClient:/\\/ip dhcp-client/.test(text),hasNetwatch:/\\/tool netwatch/.test(text),
          hasRemoteDataset:/\\/tool fetch/.test(text),hasStep1:/STEP 1/.test(text),hasStep2:/STEP 2/.test(text),
          activeGameCount:document.querySelectorAll('input[type=checkbox]:checked').length,
          disabledFields:[...document.querySelectorAll('input:disabled,select:disabled')].map(e=>e.id),
          visibleFieldIds:[...document.querySelectorAll('input,select')].filter(e=>e.getClientRects().length).map(e=>e.id).filter(Boolean)};
      }`;
      const result=await call('browser_evaluate',{function:expression});
      const block=result.content.find(c=>c.type==='text'&&c.text.includes('### Result'));
      const content=block.text.match(/### Result\s+([\s\S]*?)(?:\n###|$)/)[1];
      const summary=JSON.parse(content.replace(/^```json\s*|\s*```$/g,''));
      const record={id:scenario.id,test:test.name,url:scenario.url,inputDelta:test.fields,ratio:test.ratio,observed_at:'2026-11-03',status:'OBSERVED; PARITY_UNVERIFIED',summary};
      observations.push(record);
      console.log(JSON.stringify(record));
    } catch(error) {
      const record={id:scenario.id,test:test.name,url:scenario.url,inputDelta:test.fields,status:'UNVERIFIED',error:error.message.slice(0,400)};
      observations.push(record);console.log(JSON.stringify(record));
    }
  }
}
await fs.writeFile('docs/reference/evidence/behavior.json',JSON.stringify(observations,null,2)+'\n');
