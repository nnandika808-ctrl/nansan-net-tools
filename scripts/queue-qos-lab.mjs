import fs from 'node:fs/promises';
import crypto from 'node:crypto';
import {withRouterOsLab} from './lib/routeros-session.mjs';

const version=process.argv[2];if(process.argv.length!==3)throw new Error('Only an approved local CHR version is accepted.');
let report={status:'RUNNING',cases:[],scope:'Queue wrapper syntax, object counts, and read-back priority/max-limit. Shared data imports reuse G01 hash-matched evidence; no application traffic, direction counters or throughput/priority-under-contention proof.'};
try{
  const run=await withRouterOsLab(version,'queue-qos',async({command,assertOwned})=>{
    const all=JSON.parse(await fs.readFile('.runtime/queue-qos-lab-cases.json','utf8'));
    const fresh=[{...all.find(c=>c.application==='Youtube'&&c.queue_mode==='Queue-Simple'),fresh:true},{...all.find(c=>c.application==='Tiktok'&&c.queue_mode==='Queue-Tree'),fresh:true}];
    for(const test of [...fresh,...all]){
      const record={test_id:test.id+(test.fresh?'-fresh':'-existing-marks'),application:test.application,queue_mode:test.queue_mode,existing_packet_marks:!test.fresh,script_sha256:crypto.createHash('sha256').update(test.script).digest('hex'),shared_dataset_sha256:test.dataset_sha256};
      try{
        await assertOwned();for(const cleanup of ['/queue simple remove [find]','/queue tree remove [find]','/ip firewall mangle remove [find]','/ip firewall address-list remove [find]'])await command(cleanup);
        if(!test.fresh)for(const direction of ['Up','Down'])await command('/ip firewall mangle add chain=NANSAN-LAB-MARKS action=mark-packet new-packet-mark="'+test.application+'-'+direction+'" passthrough=yes comment="LAB-ONLY-MARK-PREREQUISITE"');
        // Queue/mangle wrapper only: full dataset artifacts and imports have separate G01 evidence.
        let section='';for(const raw of test.script.split('\n')){
          const line=raw.trim();if(!line||line.startsWith('#')||line.startsWith('/import ')||line.startsWith('/file '))continue;
          if(line.startsWith('/')&&!/\s(?:add|set|remove)\s/.test(line)){section=line;continue;}
          record.last_command=line.startsWith('/')?line:section+' '+line;await command(record.last_command);
        }
        const menu=test.queue_mode==='Queue-Simple'?'/queue simple':'/queue tree';
        const expected=test.queue_mode==='Queue-Simple'?1:2;
        record.queue_count=Number(await command(':put [:len ['+menu+' find]]'));if(record.queue_count!==expected)throw new Error('Unexpected queue count.');
        record.source_mangle_count=Number(await command(':put [:len [/ip firewall mangle find where chain!="NANSAN-LAB-MARKS"]]'));if(record.source_mangle_count!==3)throw new Error('Unexpected source mangle count.');
        record.queue_properties=[];
        for(const name of test.queue_mode==='Queue-Simple'?[test.input['Queue-Name']]:[test.input['Queue-Name']+' UP',test.input['Queue-Name']+' DOWN']){
          const selector='[find where name="'+name+'"]';const priority=await command(':put ['+menu+' get '+selector+' priority]');
          const wanted=test.queue_mode==='Queue-Simple'?test.input.Priority+'/'+test.input.Priority:test.input.Priority;
          if(priority!==wanted)throw new Error('Priority read-back mismatch: '+priority+' vs '+wanted);
          record.queue_properties.push({name,priority,max_limit:await command(':put ['+menu+' get '+selector+' max-limit]')});
        }
        delete record.last_command;record.status='QUEUE_WRAPPER_SYNTAX_AND_PROPERTIES_PASSED';
      }catch(error){record.status='FAILED';record.error=error.message.slice(-800);}
      report.cases.push(record);await fs.writeFile('.runtime/queue-qos-progress-'+version+'.json',JSON.stringify(report,null,2));
      console.log(JSON.stringify({id:record.test_id,status:record.status}));
      if(record.error&&/timeout|Owned VM exited|identity mismatch/i.test(record.error))throw new Error('Lab automation lost; remaining cases unverified.');
    }
  });
  report={...run.metadata,...report,status:report.cases.some(c=>c.status==='FAILED')?'SCOPED_FAILURES_RECORDED':'SCOPED_QUEUE_SYNTAX_PASSED'};
}catch(error){report={...error.lab_metadata,...report,status:'BLOCKED_AUTOMATION',error:error.message.slice(-800)};}
await fs.writeFile('docs/lab/QUEUE-QOS-'+version+'.json',JSON.stringify(report,null,2)+'\n');
console.log(JSON.stringify({status:report.status,cases:report.cases.length,failed:report.cases.filter(c=>c.status==='FAILED').length}));
if(report.status==='BLOCKED_AUTOMATION')process.exitCode=1;
