import fs from 'node:fs/promises';
import path from 'node:path';
import net from 'node:net';
import os from 'node:os';
import crypto from 'node:crypto';
import {spawn} from 'node:child_process';

const version=process.argv[2];
const moduleName=process.argv[3]??'pcc';
if(!['pcc','starlink'].includes(moduleName))throw new Error('Unsupported lab module.');
if(!['7.25','6.49.19'].includes(version))throw new Error('Only explicitly downloaded disposable lab images are accepted.');
const memory=version.startsWith('7')?384:256;
if(os.freemem()<(memory+256)*1024**2)throw new Error('Insufficient free memory for an isolated VM plus host headroom.');
const root=process.cwd();
const qemuPath=path.join(root,'.runtime/lab-tools/qemu/qemu-system-x86_64.exe');
const imagePath=path.join(root,'.runtime/lab-tools',version,`chr-${version}.img`);
const imageHash=crypto.createHash('sha256').update(await fs.readFile(imagePath)).digest('hex');
const listener=net.createServer();await new Promise(resolve=>listener.listen(0,'127.0.0.1',resolve));
const port=listener.address().port;await new Promise(resolve=>listener.close(resolve));
const identity='NANSAN-LAB-'+crypto.randomBytes(6).toString('hex');
const password=crypto.randomBytes(20).toString('hex');
const started=new Date().toISOString();
const args=['-machine','pc','-accel','tcg','-cpu','qemu64','-smp','1','-m',String(memory),'-snapshot','-drive',`file=${imagePath.replaceAll('\\','/')},format=raw,if=ide`,
 '-display','none','-monitor','none','-serial',`tcp:127.0.0.1:${port},server=on,wait=off`];
for(let i=0;i<3;i++)args.push('-netdev',`user,id=net${i},net=${['192.0.2.0','198.51.100.0','203.0.113.0'][i]}/24,restrict=on`,'-device',`e1000,netdev=net${i},mac=52:54:00:7${version.startsWith('7')?'7':'6'}:00:0${i+1}`);
const vm=spawn(qemuPath,args,{stdio:['ignore','ignore','pipe'],windowsHide:true});
let vmError='';vm.stderr.on('data',data=>{vmError+=data;});
let socket,buffer='';
const plain=value=>value.replace(/\x1b\[[0-?]*[ -/]*[@-~]/g,'').replace(/\r/g,'');
const redact=value=>plain(value).replaceAll(password,'<redacted>').slice(-700);
const pause=ms=>new Promise(resolve=>setTimeout(resolve,ms));
async function waitFor(pattern,timeout=15000){const until=Date.now()+timeout;while(Date.now()<until){if(pattern.test(plain(buffer)))return plain(buffer);if(vm.exitCode!==null)throw new Error('Owned VM exited: '+vmError.slice(-400));await pause(40);}throw new Error('Console timeout: '+redact(buffer));}
async function exchange(text,pattern,timeout){buffer='';socket.write(text+'\r');return waitFor(pattern,timeout);}
const prompt=/(?:\[[^\n]*@[^\n]*\]|<[^\n]*\])[^\n]*>\s*$/m;
async function command(text){
 const marker='NANSAN_ACK_'+crypto.randomBytes(5).toString('hex');
 const output=await exchange(text+'; :put "'+marker+'"',new RegExp('(?:^|\\n)'+marker+'\\s*\\n[\\s\\S]*'+prompt.source),30000);
 if(/bad command name|syntax error|expected end of command|input does not match|failure:|invalid value/i.test(output))throw new Error('RouterOS rejected command: '+redact(output));
 return output;
}
const report={started_at:started,completed_at:null,image_version:version,image_sha256:imageHash,emulator:'QEMU 11.1.0 TCG',isolation:'Fresh official CHR; snapshot overlay discarded; three restricted user-mode virtual networks; no bridge; serial console bound to loopback; no router IP accepted',qemu_pid:vm.pid,identity,cases:[],scope:'Command import syntax and selected object counts only. No traffic distribution/failover proof.',status:'RUNNING'};
try{
 console.log('Owned isolated CHR '+version+' VM launched; waiting for serial console.');
 for(let attempt=0;attempt<80;attempt++){
  try{socket=await new Promise((resolve,reject)=>{const s=net.connect({host:'127.0.0.1',port});s.once('connect',()=>resolve(s));s.once('error',reject);});break;}catch{await pause(200);}
 }
 if(!socket)throw new Error('Local VM serial console unavailable: '+vmError.slice(-400));
 socket.on('data',data=>{buffer+=data.toString();if(buffer.length>200000)buffer=buffer.slice(-100000);});
 socket.on('error',()=>{});
 socket.write('\r');await waitFor(/Login:/i,90000);
 let output=await exchange('admin',/(?:Password:|new password|software license|\[[^\n]*@[^\n]*\][^\n]*>\s*$)/im,30000);
 for(let step=0;step<8&&!prompt.test(output);step++){
  const next=/repeat new password/i.test(output)?password:/new password/i.test(output)?password:/software license/i.test(output)?'n':'';
  output=await exchange(next,/(?:Password:|new password|software license|\[[^\n]*@[^\n]*\][^\n]*>\s*$)/im,30000);
 }
 if(!prompt.test(output))throw new Error('Disposable lab login did not reach a prompt: '+redact(output));
 await command('/system identity set name='+identity);
 const resource=await command('/system resource print');report.observed_version=resource.match(/^\s*version:\s*(.+)$/m)?.[1]?.trim()??'unverified';
 console.log('Disposable lab authenticated; reported RouterOS version: '+report.observed_version);
 await command('/interface bridge add name=bridge');
 report.preconditions=['ether1 and ether2 are disposable virtual WAN interfaces','bridge LAN interface created by the lab harness, as required by the interface-target fixture'];
 const cases=JSON.parse(await fs.readFile('.runtime/'+moduleName+'-lab-cases.json','utf8')).filter(c=>c.ros_version===(version.startsWith('7')?'7':'6'));
 for(const test of cases){
  try{
   const proof=await command(':put [/system identity get name]');if(!proof.includes(identity))throw new Error('Lab identity guard failed.');
   const extra=moduleName==='starlink'?['/tool netwatch remove [find]','/ip dhcp-client remove [find]',version.startsWith('7')?'/routing rule remove [find]':'/ip route rule remove [find]']:[];
   for(const cleanup of [...extra,'/ip firewall mangle remove [find]','/ip firewall nat remove [find]','/ip firewall address-list remove [find]','/ip route remove [find where dynamic=no]',...(version.startsWith('7')?['/routing table remove [find where name~"^to-ISP|^STARLINK"]']:[])])await command(cleanup);
   let count=0;let section='';
   for(const line of test.script.split('\n').map(l=>l.trim()).filter(l=>l&&!l.startsWith('#'))){
    if(line.startsWith('/')&&!/\s(?:add|set|remove)\s/.test(line)){section=line;continue;}
    await command(line.startsWith('/')?line:section+' '+line);count++;
   }
   const natOutput=await command('/ip firewall nat print count-only');const nat=Number(natOutput.match(/^\s*(\d+)\s*$/m)?.[1]);if(nat!==(test.wan_count??2))throw new Error('Unexpected NAT rule count '+nat);
   const record={test_id:test.id,status:'SYNTAX_AND_NAT_COUNT_PASSED',commands:count,nat_rules:nat};
   if(moduleName==='starlink'){
    await command('/tool netwatch disable [find]');await command('/ip dhcp-client disable [find]');
    for(const lease of test.leases)await command(':do { :local bound 1; :local "gateway-address" "192.0.2.1"; '+lease+' }');
    const routeCount=async(where)=>{const output=await command(':put [:len [/ip route find where '+where+']]');return Number(output.match(/^\s*(\d+)\s*$/m)?.[1]);};
    const bound=await routeCount('comment~"^(MAIN|LB|MONITOR)-STARLINK"');
    if(bound!==test.wan_count*(test.netwatch?3:2))throw new Error('DHCP bound route count mismatch: '+bound);
    // A repeated lease event must replace the generator-owned routes rather than duplicate them.
    for(const lease of test.leases)await command(':do { :local bound 1; :local "gateway-address" "192.0.2.1"; '+lease+' }');
    if(await routeCount('comment~"^(MAIN|LB|MONITOR)-STARLINK"')!==bound)throw new Error('Repeated DHCP event duplicated routes.');
    let toggled=null;
    if(test.netwatch){
     for(const scripts of test.watch_scripts)await command(scripts.down);
     toggled=await routeCount('disabled=yes and comment~"^(MAIN|LB)-STARLINK"');
     if(toggled!==test.wan_count*2)throw new Error('Netwatch down did not disable expected routes.');
     for(const scripts of test.watch_scripts)await command(scripts.up);
     if(await routeCount('disabled=yes and comment~"^(MAIN|LB)-STARLINK"')!==0)throw new Error('Netwatch up did not enable expected routes.');
    }
    for(const lease of test.leases)await command(':do { :local bound 0; :local "gateway-address" "192.0.2.1"; '+lease+' }');
    if(await routeCount('comment~"^(MAIN|LB|MONITOR)-STARLINK"')!==0)throw new Error('DHCP unbound left owned routes.');
    Object.assign(record,{status:'SYNTAX_AND_SIMULATED_EVENTS_PASSED',bound_route_count:bound,repeated_bound_no_duplicates:true,unbound_route_count:0,netwatch_disabled_routes:toggled});
   }
   report.cases.push(record);console.log(JSON.stringify(record));
  }catch(error){const record={test_id:test.id,status:error.message.startsWith('Console timeout')?'BLOCKED_AUTOMATION':'FAILED',error:redact(error.message)};report.cases.push(record);console.log(JSON.stringify(record));if(record.status==='BLOCKED_AUTOMATION')break;}
 }
 if(version.startsWith('7')&&moduleName==='pcc'){
  const escapedQuote=String.fromCharCode(92,34);
  const probe='/ip firewall nat add chain=srcnat action=masquerade out-interface='+escapedQuote+'ether1'+escapedQuote+' comment="NANSAN-QUOTE-PROBE"';
  const result=await exchange(probe,prompt,20000);
  report.escaped_interface_quote_probe={input_quote_codepoints:[...escapedQuote].map(c=>c.codePointAt(0)),accepted:!/bad command name|syntax error|expected end of command|input does not match|failure:|invalid value/i.test(result),result:redact(result)};
  console.log(JSON.stringify({probe:'escaped_interface_quote',accepted:report.escaped_interface_quote_probe.accepted,result:report.escaped_interface_quote_probe.result}));
 }
 report.status=report.cases.some(c=>c.status==='BLOCKED_AUTOMATION')?'BLOCKED':report.cases.every(c=>c.status!=='FAILED')?'SCOPED_SYNTAX_CHECKS_PASSED':'FAILED';
}catch(error){report.status='BLOCKED';report.error=redact(error.message);console.log(JSON.stringify({status:report.status,error:report.error}));}
finally{
 socket?.destroy();if(vm.exitCode===null)vm.kill();
 report.completed_at=new Date().toISOString();await fs.mkdir('docs/lab',{recursive:true});const filename='docs/lab/'+(moduleName==='starlink'?'STARLINK-':'CHR-')+version+'.json';await fs.writeFile(filename,JSON.stringify(report,null,2)+'\n');
 console.log('Owned VM stopped; temporary snapshot discarded. Report: '+filename);
}
if(report.status==='BLOCKED'||report.status==='FAILED')process.exitCode=1;
