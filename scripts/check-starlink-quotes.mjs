import fs from 'node:fs/promises';
import {call} from './reference-audit.mjs';
await call('browser_tabs',{action:'new',url:'https://buananet.com/mikrotik-pcc-lb-starlink.html'});
const observations=[];
for(const version of ['v6','v7']){
 const result=await call('browser_evaluate',{function:`() => {
 const ros=document.getElementById('ros');if(ros.disabled)return {access:'BLOCKED'};
 ros.value=${JSON.stringify(version)};ros.dispatchEvent(new Event('change',{bubbles:true}));
 [...document.querySelectorAll('button')].find(e=>e.textContent.trim()==='Generate Script').click();
 const text=document.getElementById('out').value;const prefix='out-interface=';const i=text.indexOf(prefix)+prefix.length;
 return {access:'authenticated_form',ros:ros.value,interfaceValuePrefixCodepoints:[...text.slice(i,i+8)].map(c=>c.codePointAt(0)),containsLiteralEscapedQuote:text.includes(String.fromCharCode(92,34)),outputLength:text.length};
 }`});
 const text=result.content.find(c=>c.type==='text'&&c.text.includes('### Result')).text;
 const observed=JSON.parse(text.match(/### Result\s+([\s\S]*?)(?:\n###|$)/)[1]);
 observations.push({url:'https://buananet.com/mikrotik-pcc-lb-starlink.html',observed_at:'2026-11-03',scenario:version+' default two Starlink',...observed});
}
await fs.writeFile('docs/reference/evidence/G04-escaping-observation.json',JSON.stringify(observations,null,2)+'\n');console.log(JSON.stringify(observations));
