import fs from 'node:fs/promises';
import crypto from 'node:crypto';
import {withRouterOsLab} from './lib/routeros-session.mjs';

const version=process.argv[2];if(process.argv.length!==3)throw new Error('Only an approved local CHR version is accepted.');
let report={status:'RUNNING',cases:[],reference_failures:[],scope:'Generated configuration syntax, object counts and effective mangle order on isolated CHR. No game traffic, discovery accuracy, port freshness, ISP reachability or production readiness proved.'};
try{
 const run=await withRouterOsLab(version,'routing-games',async({command,putFile,assertOwned})=>{
  const ros=version.startsWith('7')?'7':'6';
  const tests=JSON.parse(await fs.readFile('.runtime/routing-games-lab-cases.json','utf8')).filter(c=>c.ros_version===ros);
  await command('/ip address add address=192.168.1.10/24 interface=ether1');
  await command('/ip address add address=192.168.2.10/24 interface=ether2');
  await command('/interface pppoe-client add name=pppoe-out interface=ether1 disabled=yes user=lab');
  const clean=async()=>{
   await assertOwned();
   for(const text of ['/ip firewall raw remove [find]','/ip firewall mangle remove [find]','/ip firewall address-list remove [find]',ros==='7'?'/ip route remove [find where routing-table="routing-game"]':'/ip route remove [find where routing-mark="routing-game"]',...(ros==='7'?['/routing table remove [find where name="routing-game"]']:[])])await command(text);
   for(let attempt=0;attempt<20;attempt++){
    const remaining=Number(await command(':put [:len [/ip route find where '+(ros==='7'?'routing-table':'routing-mark')+'="routing-game"]]'));
    if(remaining===0)break;if(attempt===19)throw new Error('Lab route cleanup did not settle.');await new Promise(r=>setTimeout(r,100));
   }
  };
  for(const test of tests){
   const row={test_id:test.id,script_sha256:crypto.createHash('sha256').update(test.script).digest('hex'),selected_games:test.selected.length,expected_raw_rules:test.raw_rules};
   try{
    await clean();let section='';
    for(const raw of test.script.split('\n')){
     const line=raw.trim();if(!line||line.startsWith('#'))continue;
     if(line.startsWith('/')&&!/\s(?:add|set|remove)\s/.test(line)){section=line;continue;}
     row.last_command=line.startsWith('/')?line:section+' '+line;await command(row.last_command);
    }
    const count=async(menu,where='')=>Number(await command(':put [:len ['+menu+' find'+where+']]'));
    row.raw_rules=await count('/ip firewall raw');row.local_entries=await count('/ip firewall address-list',' where list="LOCAL-IP"');row.mangle_rules=await count('/ip firewall mangle');
    row.game_routes=await count('/ip route',ros==='7'?' where routing-table="routing-game"':' where routing-mark="routing-game"');
    if(row.raw_rules!==test.raw_rules||row.local_entries!==3||row.mangle_rules!==2||row.game_routes!==1)throw new Error('Generated object counts differ.');
    if(ros==='7'&&await count('/routing table',' where name="routing-game"')!==1)throw new Error('Missing routing table.');
    row.effective_mangle_order=[];
    for(const i of [0,1])row.effective_mangle_order.push(await command(':put [/ip firewall mangle get [:pick [/ip firewall mangle find] '+i+'] action]'));
    if(row.effective_mangle_order.join(',')!=='mark-connection,mark-routing')throw new Error('Effective mangle order differs.');
    delete row.last_command;row.status='CONFIGURATION_SYNTAX_AND_OBJECTS_PASSED';
   }catch(error){row.status='FAILED';row.error=error.message.slice(-800);}
   report.cases.push(row);await fs.writeFile('.runtime/routing-games-progress-'+version+'.json',JSON.stringify(report,null,2));
   console.log(JSON.stringify({id:row.test_id,status:row.status}));
   if(row.error&&/timeout|Owned VM exited|identity mismatch/i.test(row.error))throw new Error('Lab automation lost; remaining cases unverified.');
  }
  const observed=JSON.parse(await fs.readFile('.runtime/G05-rendered-outputs.json','utf8'));
  for(const game of ['LeagueofLegend-PC','Hearthstone-PC']){
   await clean();const source=observed.find(c=>c.id==='ros'+ros+'-'+game);if(!source)throw new Error('Missing observed negative fixture.');
   if(source.script.split('\n').some(l=>l.startsWith('/')&&!/^\/(?:ip firewall (?:address-list|mangle|raw)|ip route|routing table)\s*$/.test(l.trim())))throw new Error('Unexpected command path in observed fixture.');
   const row={test_id:source.id,source_script_sha256:crypto.createHash('sha256').update(source.script).digest('hex')};
   await putFile('Nansan-Game-Source.rsc',source.script);
   try{row.import_response=(await command('/import file-name=Nansan-Game-Source.rsc')).slice(-400);row.status='SOURCE_IMPORT_RETURNED';}
   catch(error){row.native_error=error.message.slice(-800);row.status=/syntax|expected|end of command/i.test(row.native_error)?'FAILED_REFERENCE_SYNTAX_CONFIRMED':'UNVERIFIED_OTHER_FAILURE';}
   await command(':delay 1s');row.imported_raw_rules=Number(await command(':put [:len [/ip firewall raw find]]'));
   if(row.status==='SOURCE_IMPORT_RETURNED')row.status=row.imported_raw_rules<2?'FAILED_REFERENCE_INCOMPLETE_IMPORT_CONFIRMED':'UNEXPECTED_SOURCE_ACCEPTED';
   report.reference_failures.push(row);console.log(JSON.stringify({id:row.test_id,status:row.status}));
  }
 });
 report={...run.metadata,...report,status:report.cases.some(c=>c.status==='FAILED')||report.reference_failures.some(c=>!['FAILED_REFERENCE_SYNTAX_CONFIRMED','FAILED_REFERENCE_INCOMPLETE_IMPORT_CONFIRMED'].includes(c.status))?'SCOPED_FAILURES_RECORDED':'SCOPED_GAME_SYNTAX_PASSED'};
}catch(error){report={...error.lab_metadata,...report,status:'BLOCKED_AUTOMATION',error:error.message.slice(-800)};}
await fs.writeFile('docs/lab/ROUTING-GAMES-'+version+'.json',JSON.stringify(report,null,2)+'\n');
console.log(JSON.stringify({status:report.status,cases:report.cases.length,failed:report.cases.filter(c=>c.status==='FAILED').length,source_failures:report.reference_failures.length}));
if(report.status==='BLOCKED_AUTOMATION')process.exitCode=1;
