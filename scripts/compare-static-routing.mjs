import fs from 'node:fs';
import assert from 'node:assert/strict';
import {parse} from './routeros-observation-parser.mjs';
const actual=JSON.parse(fs.readFileSync(0,'utf8'));
const observations=JSON.parse(fs.readFileSync('docs/reference/evidence/G01-cases-semantics.json','utf8'));
const sorted=v=>Array.isArray(v)?v.map(sorted):v&&typeof v==='object'?Object.fromEntries(Object.keys(v).sort().map(k=>[k,sorted(v[k])])):v;
let compared=0;
for(const observation of observations.cases){
  if(observation.input['routing-list']==='Shopify')continue;
  const generated=actual.find(c=>c.id===observation.test_id);assert.ok(generated,observation.test_id);
  assert.equal(generated.steps.length,2);
  const expected=observation.steps[1].filter(e=>e.operation==='add');
  const entries=parse(generated.steps[1].script).filter(e=>e.operation==='add');
  assert.deepEqual(sorted(entries),sorted(expected),observation.test_id);
  assert.ok(/\/tool fetch url="[^"\n]+\/datasets\/static-routing\/[a-f0-9]{64}\.rsc"/.test(generated.steps[0].script));
  const filename=generated.steps[0].script.match(/dst-path="([^"]+)"/)[1];
  assert.ok(generated.steps[1].script.includes('/import file-name="'+filename+'"'));
  const application=observation.input['routing-list'];assert.ok(generated.steps[1].script.includes('remove [find comment="NANSAN-G01-'+application+'"]'));
  compared++;
}
assert.equal(compared,184);
console.log(compared+' ordered route/table/LOCAL-IP/mangle parameter projections matched; self-hosted fetch/files and NANSAN cleanup comments differ explicitly. Exact/full semantic parity unverified.');
