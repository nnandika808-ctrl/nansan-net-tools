import fs from 'node:fs/promises';
import crypto from 'node:crypto';
import {parseStaticRoutingDataset} from './static-routing-data-parser.mjs';

const downloaded=JSON.parse(await fs.readFile('.runtime/G01-dataset-downloads.json','utf8'));
const directory='resources/datasets/static-routing';await fs.mkdir(directory,{recursive:true});
const manifest={version:'2026-11-03-snapshot',observed_at:downloaded.observed_at,source:'https://buananet.com/mikrotik-static-routing.html',authorization:downloaded.authorization,scope:'Functional firewall parameters from owner-requested public RSC downloads; generator JavaScript and original comments/source headers excluded. Freshness and application coverage are not independently established.',datasets:[]};
for(const item of downloaded.sources){
  if(item.status==='UNAVAILABLE'){manifest.datasets.push(item);continue;}
  const body=await fs.readFile('.runtime/reference-datasets/'+item.filename,'utf8');
  const {commands,duplicates}=parseStaticRoutingDataset(body,item.application);
  const filename=item.mode+'-'+item.application+'.json';
  const content=JSON.stringify({application:item.application,mode:item.mode,commands},null,2)+'\n';await fs.writeFile(directory+'/'+filename,content);
  manifest.datasets.push({...item,status:'PARSED_PARAMETERS_NOT_LAB_VERIFIED',parameter_file:filename,parameter_sha256:crypto.createHash('sha256').update(content).digest('hex'),commands:commands.length,duplicate_commands:duplicates});
}
await fs.writeFile(directory+'/manifest.json',JSON.stringify(manifest,null,2)+'\n');
console.log('Prepared '+manifest.datasets.filter(d=>d.parameter_file).length+' parsed parameter datasets; unavailable sources remain explicit.');
