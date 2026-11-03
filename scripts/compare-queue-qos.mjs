import fs from 'node:fs';
import assert from 'node:assert/strict';
import {parse} from './routeros-observation-parser.mjs';
const actual=JSON.parse(fs.readFileSync(0,'utf8'));
const observations=JSON.parse(fs.readFileSync('docs/reference/evidence/G02-cases-semantics.json','utf8'));
const manifest=JSON.parse(fs.readFileSync('resources/datasets/static-routing/manifest.json','utf8'));
const sorted=v=>Array.isArray(v)?v.map(sorted):v&&typeof v==='object'?Object.fromEntries(Object.keys(v).sort().map(k=>[k,sorted(v[k])])):v;
let compared=0;
for(const observed of observations.cases){
  const source=manifest.datasets.find(d=>d.application===observed.input['Queue-List']&&d.mode===observed.input['match-condition']);
  assert.equal(source?.url,observed.dataset_url,'Shared dataset source mismatch');
  if(observed.input['Queue-List']==='Shopify')continue;
  const output=actual.find(c=>c.id===observed.test_id);assert.ok(output,observed.test_id);assert.equal(output.steps.length,2);
  assert.deepEqual(sorted(parse(output.steps[1].script).filter(c=>c.operation==='add')),sorted(observed.configuration),observed.test_id);
  const url=output.steps[0].script.match(/\/datasets\/static-routing\/([a-f0-9]{64})\.rsc/);assert.ok(url);assert.equal(url[1],source.artifact_sha256);
  const file=output.steps[0].script.match(/dst-path="([^"]+)"/)[1];assert.ok(output.steps[1].script.includes('/import file-name="'+file+'"'));
  compared++;
}
assert.equal(compared,210);
console.log(compared+' Queue QoS ordered queue/mangle/LOCAL-IP projections matched; shared dataset identities verified. Not full shaping or exact-text equivalence.');
