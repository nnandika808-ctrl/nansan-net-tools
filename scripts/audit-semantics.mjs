import fs from 'node:fs/promises';
import {call} from './reference-audit.mjs';
await call('browser_tabs',{action:'select',index:0});
await call('browser_navigate',{url:'https://buananet.com/pcc.html'});
const response=await call('browser_evaluate',{function:`() => {
 document.getElementById('generate-script-btn').click();
 const text=document.getElementById('script-output').value;
 let path='';const entries=[];
 for(const line of text.split('\\n')){
  const trimmed=line.trim();if(trimmed.startsWith('/')){path=trimmed;continue;}
  if(!/^(add|set) /.test(trimmed))continue;
  const fields={};for(const match of trimmed.matchAll(/([a-z-]+)=("(?:\\\\.|[^"\\\\])*"|[^\\s]+)/g)){
   if(match[1]!=='comment')fields[match[1]]=match[2].replace(/^"|"$/g,'');
  }entries.push({path,operation:trimmed.split(' ')[0],fields});
 }
 return {entries};
}`});
const text=response.content.find(c=>c.type==='text'&&c.text.includes('### Result')).text;
const entries=JSON.parse(text.match(/### Result\s+([\s\S]*?)(?:\n###|$)/)[1]);
await fs.writeFile('docs/reference/evidence/G03-default-semantics.json',JSON.stringify({url:'https://buananet.com/pcc.html',observed_at:'2026-11-03',scenario:'Default ROS6, 2 equal WAN, IP-address-list target, no recursion',scope:'Observed command parameters, excluding comments; not an exact output fixture',...entries},null,2)+'\n');
console.log(JSON.stringify(entries));
