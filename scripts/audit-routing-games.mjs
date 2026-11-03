import fs from 'node:fs/promises';
import {call} from './reference-audit.mjs';
import crypto from 'node:crypto';
import {parse} from './routeros-observation-parser.mjs';

const schema=JSON.parse(await fs.readFile('resources/generators/G05.json','utf8'));
const games=schema.fields.filter(f=>f.type==='checkbox');
const cases=[];
for(const ros of ['ros6','ros7']){
  for(const game of games)cases.push({id:ros+'-'+game.key,input:{'ros-list':ros,'ip-gateway-input':'192.168.2.1',myInput:''},selected:[game.key]});
  for(const [label,selected,gateway,search] of [
    ['none',[],'192.168.2.1',''],['all',games.map(g=>g.key),'192.168.2.1',''],['all-safe',games.filter(g=>!['LeagueofLegend-PC','Hearthstone-PC'].includes(g.key)).map(g=>g.key),'192.168.2.1',''],
    ['mixed',[games[0].key,games[35].key,games.at(-1).key],'192.168.2.1',''],
    ['pppoe',['PUBG-PC'],'pppoe-out',''],['scoped-gateway',[games[0].key],'192.168.2.1%ether2',''],
    ['hidden-selection',[games[0].key],'192.168.2.1','NO_GAME_MATCH'],
  ])cases.push({id:ros+'-'+label,input:{'ros-list':ros,'ip-gateway-input':gateway,myInput:search},selected});
}
function removeInlineComment(line){
  let quoted=false,escaped=false;
  for(let i=0;i<line.length;i++){
    const c=line[i];if(escaped){escaped=false;continue;}if(c==='\\'){escaped=true;continue;}if(c==='"')quoted=!quoted;if(c==='#'&&!quoted)return line.slice(0,i);
  }
  return line;
}
let observations=[],raw=[];
const edges=process.argv.includes('--edges');const requested=edges?cases.filter(c=>c.id.endsWith('-hidden-selection')||c.id.endsWith('-all-safe')):cases;
for(let start=0;start<requested.length;start+=5){
  const result=await call('browser_evaluate',{function:`async () => {
    const rows=[];for(const test of ${JSON.stringify(requested.slice(start,start+5))}){
      for(const checkbox of document.querySelectorAll('input[type=checkbox]'))checkbox.checked=false;
      for(const [id,value] of Object.entries(test.input)){const e=document.getElementById(id);if(!e||e.disabled)throw new Error('Field unavailable: '+id);e.value=value;e.dispatchEvent(new Event('input',{bubbles:true}));e.dispatchEvent(new Event('change',{bubbles:true}));if(id==='myInput')e.dispatchEvent(new KeyboardEvent('keyup',{key:'x',bubbles:true}));}
      for(const id of test.selected){const e=document.getElementById(id);if(!e||e.disabled)throw new Error('Game unavailable: '+id);e.checked=true;}
      const button=[...document.querySelectorAll('button')].find(e=>e.textContent.trim()==='Generate');if(!button||button.disabled)throw new Error('Generate unavailable');button.click();await new Promise(r=>setTimeout(r,30));
      const output=document.querySelector('.list-output-content');if(!output)throw new Error('Output container changed');rows.push({...test,selected_hidden:test.selected.map(id=>!document.getElementById(id).getClientRects().length),visible_games:[...document.querySelectorAll('input[type=checkbox]')].filter(e=>e.getClientRects().length).length,script:output.innerText});
    }return rows;
  }`});
  const text=result.content.find(c=>c.type==='text'&&c.text.includes('### Result'))?.text;if(!text)throw new Error('Missing observation');
  const rows=JSON.parse(text.match(/### Result\s+([\s\S]*?)(?:\n###|$)/)[1]);
  for(const row of rows){raw.push(row);observations.push({test_id:row.id,input:row.input,selected:row.selected,selected_hidden:row.selected_hidden,visible_games:row.visible_games,configuration:parse(row.script.split('\n').map(removeInlineComment).join('\n')).filter(e=>e.operation==='add')});}
  console.log('Observed '+observations.length+'/'+requested.length+' Routing Games cases.');
}
if(edges){const oldRaw=JSON.parse(await fs.readFile('.runtime/G05-rendered-outputs.json','utf8')),old=JSON.parse(await fs.readFile('docs/reference/evidence/G05-cases-semantics.json','utf8'));const nextRaw=raw,nextObservations=observations;raw=oldRaw.map(c=>nextRaw.find(n=>n.id===c.id)??c);raw.push(...nextRaw.filter(c=>!oldRaw.some(o=>o.id===c.id)));observations=old.cases.map(c=>nextObservations.find(n=>n.test_id===c.test_id)??c);observations.push(...nextObservations.filter(c=>!old.cases.some(o=>o.test_id===c.test_id)));}
if(observations.length!==226)throw new Error('Incomplete observation set');
await fs.writeFile('.runtime/G05-rendered-outputs.json',JSON.stringify(raw,null,2)+'\n');
await fs.writeFile('docs/reference/evidence/G05-cases-semantics.json',JSON.stringify({generator:'G05',source:schema.source,observed_at:new Date().toISOString(),method:'Authenticated manual Premium session; rendered selected-game output, no generator JavaScript extraction',scope:'106 games x two ROS versions plus empty/all/all-safe/mixed/interface/scoped-gateway/hidden-selection cases; functional command parameters, not independent port freshness or real game traffic proof',cases:observations},null,2)+'\n');
await fs.mkdir('resources/datasets/routing-games',{recursive:true});
const data=games.map(game=>{
  const six=observations.find(c=>c.test_id==='ros6-'+game.key),seven=observations.find(c=>c.test_id==='ros7-'+game.key);
  const rules=six.configuration.filter(c=>c.path==='/ip firewall raw'),v7=seven.configuration.filter(c=>c.path==='/ip firewall raw');
  if(JSON.stringify(rules)!==JSON.stringify(v7))throw new Error('Version-dependent raw rules need review: '+game.key);
  const original=raw.find(c=>c.id==='ros6-'+game.key).script;const malformed=original.split('\n').filter(line=>line.trim().startsWith('add ')).some(line=>{let quoted=false,escape=false;for(const c of line){if(escape){escape=false;continue;}if(c==='\\'){escape=true;continue;}if(c==='"')quoted=!quoted;}return quoted;});
  return {id:game.key,label:game.label,status:malformed?'FAILED_REFERENCE_SYNTAX':rules.length?'PARAMETERS_OBSERVED':'NO_RAW_RULES_OBSERVED',failure:malformed?'Unbalanced quote in rendered reference command':null,rules};
});
const catalog=JSON.stringify({version:'2026-11-03-observed',source:schema.source,observed_at:new Date().toISOString(),scope:'Source-attributed observed game-port/protocol parameters, not independently verified current ports. Original descriptive comments/headers and generator source excluded.',games:data},null,2)+'\n';
await fs.writeFile('resources/datasets/routing-games/manifest.json',catalog);await fs.writeFile('resources/datasets/routing-games/manifest.sha256',crypto.createHash('sha256').update(catalog).digest('hex')+'\n');
console.log(JSON.stringify({observed:observations.length,games:data.length,no_rules:data.filter(g=>!g.rules.length).map(g=>g.id)}));
