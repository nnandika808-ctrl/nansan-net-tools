import fs from 'node:fs';
import assert from 'node:assert/strict';
import {parse} from './routeros-observation-parser.mjs';
const actual=JSON.parse(fs.readFileSync(0,'utf8'));
const observations=JSON.parse(fs.readFileSync('docs/reference/evidence/G05-cases-semantics.json','utf8'));
const catalog=JSON.parse(fs.readFileSync('resources/datasets/routing-games/manifest.json','utf8'));
const failed=new Set(catalog.games.filter(g=>g.status==='FAILED_REFERENCE_SYNTAX').map(g=>g.id));
const sorted=v=>Array.isArray(v)?v.map(sorted):v&&typeof v==='object'?Object.fromEntries(Object.keys(v).sort().map(k=>[k,sorted(v[k])])):v;
let compared=0,blocked=0;
for(const observed of observations.cases){
  if(observed.selected.some(id=>failed.has(id))){blocked++;continue;}
  const output=actual.find(c=>c.id===observed.test_id);assert.ok(output,observed.test_id);
  assert.deepEqual(sorted(parse(output.script).filter(c=>c.operation==='add')),sorted(observed.configuration),observed.test_id);compared++;
}
assert.equal(compared,220);assert.equal(blocked,6);
console.log(compared+' Routing Games ordered parameter projections matched; '+blocked+' source-syntax cases blocked. Not exact text, current-port or game-traffic equivalence.');
