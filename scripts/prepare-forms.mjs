import fs from 'node:fs/promises';
const labels={'routing-list':'Routing Options','ros-list':'RouterOS Version','match-condition':'Match Filter RAW','gateway-option':'ISP Gateway','routing-mark':'Routing Mark','Queue-Options':'Queue Options','Queue-List':'Queue List','Queue-Name':'Queue Name','Priority':'Priority (1–8)','Max-Limit':'Max-Limit','pccType':'PCC Classifier',ros:'RouterOS Version',count:'Starlink Count',mode:'Load Balance Mode',localIpTarget:'Local IP Target (RFC1918)',netwatchTargets:'Target IP per WAN','ip-gateway-input':'Gateway to WAN / ISP Game',myInput:'Find Games'};
await fs.mkdir('resources/generators',{recursive:true});
for(const id of ['G01','G02','G03','G04','G05']){
 const audit=JSON.parse(await fs.readFile('docs/reference/evidence/'+id+'-form.json','utf8'));let connection=0;
 const fields=audit.fields.filter(f=>f.tag!=='TEXTAREA'&&!/chat/.test(f.id)).map(f=>{
  const key=f.id||('connection-'+(++connection));
  const conditional=f.id?.startsWith('speed-isp-')?'bandwidth-ratio-checkbox':f.id?.startsWith('ip-public-check-isp-')?'recursive-checkbox':f.id==='netwatchTargets'?'netwatchEnable':undefined;
  return {key,label:f.label||labels[key]||(f.id?key:'Starlink '+Math.ceil(connection/2)+(f.type==='number'?' ratio':' interface')),type:f.tag==='SELECT'?'select':f.type,default:f.type==='checkbox'?f.checked:f.value,placeholder:f.placeholder??undefined,options:f.options?.map(({label,value,disabled})=>({label,value,disabled})),min:f.min?Number(f.min):undefined,max:f.max?Number(f.max):undefined,conditional,referenceId:f.id};
 });
 await fs.writeFile('resources/generators/'+id+'.json',JSON.stringify({id,source:audit.url,status:'FORM_OBSERVED; OUTPUT_UNVERIFIED',observedAt:'2026-11-03',fields},null,2)+'\n');
 console.log(id+': '+fields.length+' observed fields');
}
