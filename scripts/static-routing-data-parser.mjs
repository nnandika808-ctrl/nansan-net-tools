import {tokens,fields} from './routeros-observation-parser.mjs';

const allowed={
  '/ip firewall raw':['action','address-list','address-list-timeout','chain','content','dst-address-list','src-address-list','protocol','tls-host','dst-port'],
  '/ip firewall address-list':['address','list'],
};

export function parseStaticRoutingDataset(body,application){
  if(!/^[A-Za-z0-9-]+$/.test(application))throw new Error('Invalid application key');
  let path='';const commands=[],seen=new Set();let duplicates=0;
  for(const raw of body.split(/\r?\n/)){
    const line=raw.trim();if(!line||line.startsWith('#'))continue;
    if(line.startsWith('/')){if(!allowed[line])throw new Error('Unknown command section');path=line;continue;}
    if(!allowed[path])throw new Error('Missing command section');
    const parts=tokens(line),operation=parts.shift();
    if(['rem','remove'].includes(operation)){
      const match=line.match(/^(?:rem|remove) \[find (address-list|list)="?([A-Za-z0-9-]+)"?\s*\]$/);
      if(!match||match[2]!=='Mikrotik-'+application||match[1]!==(path==='/ip firewall raw'?'address-list':'list'))throw new Error('Unexpected cleanup selector');
      commands.push({path,operation:'remove',selector:{[match[1]]:match[2]}});continue;
    }
    if(operation!=='add')throw new Error('Unexpected dataset operation');
    const data=fields(parts);
    if(Object.values(data).some(value=>/[\[\]$;()]/.test(value)))throw new Error('Executable expressions are not allowed in dataset values or comments');
    delete data.comment;
    if(Object.keys(data).some(k=>!allowed[path].includes(k))||parts.some(p=>!p.includes('=')))throw new Error('Unexpected dataset fields');
    if((data['address-list']??data.list)!=='Mikrotik-'+application)throw new Error('Unexpected target list');
    if(Object.values(data).some(v=>typeof v!=='string'||/[\x00-\x1f\x7f]/.test(v)||v.length>500))throw new Error('Unsafe dataset value');
    if(path==='/ip firewall raw'&&(data.action!=='add-dst-to-address-list'||data.chain!=='prerouting'||data['address-list-timeout']!=='1d'||data['src-address-list']!=='LOCAL-IP'))throw new Error('Unexpected RAW action');
    const command={path,operation:'add',fields:data},canonical=JSON.stringify(command);
    if(seen.has(canonical))duplicates++;seen.add(canonical);commands.push(command);
  }
  if(!commands.length)throw new Error('Empty dataset');
  return {commands,duplicates};
}
