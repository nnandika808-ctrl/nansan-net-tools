import fs from 'node:fs/promises';

const reports=await Promise.all(['6.49.19','7.25'].map(async version=>JSON.parse(await fs.readFile('docs/lab/STATIC-ROUTING-'+version+'.json','utf8'))));
const manifest=JSON.parse(await fs.readFile('resources/datasets/static-routing/manifest.json','utf8'));
for(const report of reports){
  if(!['SCOPED_IMPORT_SYNTAX_PASSED','SCOPED_FAILURES_RECORDED'].includes(report.status)||!report.owned_vm_stopped||report.cases.length!==93)throw new Error('Incomplete/failed lab report; do not label it passing.');
  for(const entry of manifest.datasets.filter(e=>e.parameter_file)){
    const actual=report.cases.find(c=>c.application===entry.application&&c.mode===entry.mode&&c.mangle_anchor);
    if(!actual||actual.dataset_sha256!==entry.artifact_sha256)throw new Error('Dataset/lab scope mismatch: '+entry.application+'/'+entry.mode);
    entry.lab_import_syntax??={};if(actual.status==='DATASET_IMPORT_AND_WRAPPER_SYNTAX_PASSED'&&(actual.dataset_import!=='PASSED'||!actual.dataset_file_removed))throw new Error('Strict file lifecycle checks missing.');
    entry.lab_import_syntax[report.image_version]=actual.status==='DATASET_IMPORT_AND_WRAPPER_SYNTAX_PASSED'?'PASSED':'FAILED';
    if(actual.status==='FAILED'){entry.lab_errors??={};entry.lab_errors[report.image_version]={stage:actual.failed_stage,error:actual.error};}
  }
}
await fs.writeFile('resources/datasets/static-routing/manifest.json',JSON.stringify(manifest,null,2)+'\n');
const total=reports.reduce((n,r)=>n+r.cases.length,0),passed=reports.reduce((n,r)=>n+r.cases.filter(c=>c.status==='DATASET_IMPORT_AND_WRAPPER_SYNTAX_PASSED').length,0);
const rows=manifest.datasets.filter(e=>e.parameter_file).map(e=>`| ${e.application} | ${e.mode} | ${e.lab_import_syntax['6.49.19']} | ${e.lab_import_syntax['7.25']} | ${e.commands} |`).join('\n');
await fs.writeFile('docs/lab/STATIC-ROUTING-RESULTS.md',`# Static Routing import and syntax evidence - 2026-11-03

Both final runs used snapshot-only CHR, restricted user-mode networking and a loopback-only SSH management forward. Commands ran through noninteractive SSH after console bootstrap. The earlier interrupted serial-transport attempt is not counted as a source failure. The main cases use datasets safely rendered from parsed parameter snapshots. Four additional original public Teams/Telegram source RSCs were separately imported on CHR 7.25 after source-hash and allowlist checks; all four reproduced the duplicate-entry failure. Raw files remain ignored. See STATIC-ROUTING-SOURCE-DUPLICATES-7.25.json; those negative reference tests are separate from the main case total.

Both images exercised all 92 available application/matcher datasets plus a YouTube TLS empty-mangle case: ${total} cases, ${passed} passed and ${total-passed} failed. The final ROS6 run awaited SFTP file visibility in the RouterOS inventory before import, and successful main cases on both images verified file cleanup; this avoids mistaking asynchronous SFTP inventory updates for a generator bug. Failures remain failures. Existing sentinel cases recorded internal rule ID *1; the literal reference place-before=*0 also passed with an empty table. An assumed requirement for an existing *0 rule was not established. Owned VMs stopped and snapshot overlays were discarded.

Shopify TLS/CONTENT source URLs returned HTTP 404; all four app/ROS/matcher combinations stay unavailable and are not counted as passing. Teams and Telegram fail ROS7 import in both modes on repeated address-list entries. An owner decision is pending before any corrective rewrite, as required by the original prompt; see ../reference/G01-DUPLICATE-DECISION.md. No automatic substitute or duplicate-removal rewrite was introduced. Parameter snapshots retain observed duplicate commands; successful syntax/import does not prove safety when combined with an existing configuration.

| Application | Matcher | CHR 6.49.19 | CHR 7.25 | Dataset commands |
|---|---|---|---|---:|
${rows}

Evidence: STATIC-ROUTING-6.49.19.json and STATIC-ROUTING-7.25.json record image/dataset/script hashes and individual outcomes. The manifest accepts pass/fail lab flags only when compiled artifact hashes match those reports. An additional strict file-cleanup smoke, if present, is recorded separately as STATIC-ROUTING-SMOKE-6.49.19.json and is not added to the 186-case total.

These checks do not execute STEP 1 HTTPS fetch on CHR, establish prefix freshness or exclusive application ownership, observe real app traffic/TLS/QUIC, prove ISP routing/capacity or guarantee repeated-import safety. The staging browser separately downloads a self-hosted artifact and checks its SHA-256. G01 remains an unpublished Admin draft; production artifacts stay closed until reviewed publication.

MikroTik documents the [RAW matcher semantics and TLS SNI fragmentation limitation](https://manual.mikrotik.com/docs/cli-reference/ip/firewall/raw/). Console handling follows the vendor's [console documentation](https://manual.mikrotik.com/docs/management-tools/console/); lab automation is not a firmware or generator behavior change.
`);
console.log(JSON.stringify({total,passed,failed:total-passed,scope:'Actual imports and STEP 2 syntax only; no failed case counted as passing'}));
