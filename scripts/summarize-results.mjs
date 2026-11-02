import fs from 'node:fs';
const report=JSON.parse(fs.readFileSync('.runtime/e2e-results.json','utf8'));
console.log(JSON.stringify(report.stats));
function visit(s){for(const spec of s.specs??[])for(const test of spec.tests)console.log(JSON.stringify({title:spec.title,status:test.status,errors:test.results.flatMap(r=>r.errors??[]).map(e=>e.message?.replace(/\u001b\[[0-9;]*m/g,'').slice(0,1400))}));for(const child of s.suites??[])visit(child);}
for(const suite of report.suites)visit(suite);
