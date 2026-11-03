import fs from 'node:fs';
import assert from 'node:assert/strict';
import {tokens,fields} from './routeros-observation-parser.mjs';

const actual=JSON.parse(fs.readFileSync(0,'utf8'));
const sorted=v=>Array.isArray(v)?v.map(sorted):v&&typeof v==='object'?Object.fromEntries(Object.keys(v).sort().map(k=>[k,sorted(v[k])])):v;
for(const artifact of actual){
  const observed=JSON.parse(fs.readFileSync('resources/datasets/static-routing/'+artifact.mode+'-'+artifact.application+'.json','utf8'));
  const commands=[];
  for(const raw of artifact.script.split('\n')){
    const line=raw.trim();if(!line||line.startsWith('#'))continue;
    const parts=tokens(line),index=parts.findIndex(p=>['add','remove'].includes(p));assert.ok(index>0);
    const path=parts.slice(0,index).join(' '),operation=parts[index];
    if(operation==='remove'){
      const match=line.match(/\[find (address-list|list)="([^"]+)"\]/);assert.ok(match);commands.push({path,operation,selector:{[match[1]]:match[2]}});
    }else commands.push({path,operation,fields:fields(parts.slice(index+1))});
  }
  assert.deepEqual(sorted(commands),sorted(observed.commands),artifact.application+'/'+artifact.mode);
}
assert.equal(actual.length,92);
console.log('92 rendered dataset command-parameter sequences match parsed source snapshots, including ordering and duplicates; not exact text or live traffic equivalence.');
