import fs from 'node:fs';
import assert from 'node:assert/strict';
import {parse,tokens,fields} from './routeros-observation-parser.mjs';

// Compare an independently rendered draft with observations of generated output.
// This covers ordered command parameters and a limited DHCP projection, not traffic effects.
const actual = JSON.parse(fs.readFileSync(0, 'utf8'));
const observations = JSON.parse(fs.readFileSync('docs/reference/evidence/G04-cases-semantics.json', 'utf8'));
function sorted(value) {
  if (Array.isArray(value)) return value.map(sorted);
  if (value && typeof value === 'object') return Object.fromEntries(Object.keys(value).sort().map(k => [k, sorted(value[k])]));
  return value;
}
for (const observation of observations) {
  const result = actual.find(c => c.id === observation.test_id);
  assert.ok(result, 'Missing scenario '+observation.test_id);
  const entries = parse(result.script);
  for (const entry of entries) {
    if (entry.path !== '/ip dhcp-client') continue;
    const script = entry.fields.script;
    const routes = [...script.matchAll(/\/ip route add ([^;]+);/g)].map(m => fields(tokens(m[1])));
    entry.fields.script = {
      interface: entry.fields.interface,
      removesBeforeBoundCheck: script.indexOf('/ip route remove') < script.indexOf(':if'),
      boundCondition: /\$bound\s*=\s*1/.test(script),
      routes: routes.map(r => ({...r, gateway:r.gateway.includes('gateway-address') ? 'DYNAMIC_GATEWAY%'+entry.fields.interface : r.gateway})),
    };
  }
  assert.deepEqual(sorted(entries), sorted(observation.entries), observation.test_id);
}
console.log(observations.length+' observed Starlink parameter/DHCP projections matched; full semantic parity is unverified.');
