import fs from 'node:fs/promises';
import path from 'node:path';
import net from 'node:net';
import os from 'node:os';
import crypto from 'node:crypto';
import {spawn} from 'node:child_process';
import {PacketLab} from './lab-ethernet.mjs';
import {inspectCapture} from './lab-pcap.mjs';

const version=process.argv[2];
const moduleName=process.argv[3]??'pcc';
if(!['pcc','starlink','pcc-packets','starlink-protocol'].includes(moduleName))throw new Error('Unsupported lab module.');
const starlinkProtocol=moduleName==='starlink-protocol';
const edgeOnly=process.argv[4]==='edge';
if(process.argv[4]&&!edgeOnly)throw new Error('Unsupported lab scenario filter.');
if(edgeOnly&&(!starlinkProtocol||version!=='7.25'))throw new Error('The additional edge observation is scoped to the ROS7 Starlink protocol lab.');
const packetMode=moduleName==='pcc-packets'||starlinkProtocol;
const packetTag=edgeOnly?'starlink-edge':starlinkProtocol?'starlink-protocol':'pcc-packets';
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
const ethernetPorts=[];
if(packetMode)for(let i=0;i<3;i++){const server=net.createServer();await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));ethernetPorts.push(server.address().port);await new Promise(resolve=>server.close(resolve));}
for(let i=0;i<3;i++){
 args.push('-netdev',packetMode?`socket,id=net${i},listen=127.0.0.1:${ethernetPorts[i]}`:`user,id=net${i},net=${['192.0.2.0','198.51.100.0','203.0.113.0'][i]}/24,restrict=on`,'-device',`e1000,netdev=net${i},mac=52:54:00:7${version.startsWith('7')?'7':'6'}:00:0${i+1}`);
 if(packetMode&&i<2)args.push('-object',`filter-dump,id=dump${i},netdev=net${i},file=${path.join(root,'.runtime',`${packetTag}-${version}-wan${i+1}.pcap`).replaceAll('\\','/')}`);
}
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
const wires=packetMode?new PacketLab(ethernetPorts,`52:54:00:7${version.startsWith('7')?'7':'6'}:00:03`,{dhcp:starlinkProtocol}):null;
if(packetMode){report.isolation='Snapshot-only CHR; three virtual Ethernet peers on loopback TCP; Node buffers emulate ARP/ICMP gateways; no host bridge, raw socket, SLIRP, Internet traffic or router IP accepted';report.scope='Synthetic UDP forwarding/PCC distribution/NAT through virtual WANs. A local link-outage observation is separate from Internet/end-to-end failover proof.';}
try{
 if(wires)await wires.connect();
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
 // Drain delayed password-prompt redraws before correlating the first command.
 await pause(350);await exchange('',prompt,30000);await pause(200);buffer='';
 await command('/system identity set name='+identity);
 const resource=await command('/system resource print');report.observed_version=resource.match(/^\s*version:\s*(.+)$/m)?.[1]?.trim()??'unverified';
 console.log('Disposable lab authenticated; reported RouterOS version: '+report.observed_version);
 await command('/interface bridge add name=bridge');
 if(packetMode){
  await command(`/interface bridge set bridge protocol-mode=none auto-mac=no admin-mac=52:54:00:7${version.startsWith('7')?'7':'6'}:00:03`);
  await command('/interface bridge port add bridge=bridge interface=ether3');
  for(const [address,iface] of (starlinkProtocol?[['192.168.88.1/24','bridge']]:[['192.168.1.10/24','ether1'],['192.168.2.10/24','ether2'],['192.168.88.1/24','bridge']]))await command('/ip address add address='+address+' interface='+iface);
 }
 report.preconditions=['ether1 and ether2 are disposable virtual WAN interfaces','bridge LAN interface created by the lab harness, as required by the interface-target fixture'];
 if(packetMode)report.preconditions.push('WANs 192.168.1.10/24 and 192.168.2.10/24 with emulated .1 gateways; bridge LAN 192.168.88.1/24 and ether3, all supplied only by the lab harness');
 const cases=JSON.parse(await fs.readFile('.runtime/'+(starlinkProtocol?'starlink-protocol':packetMode?'pcc':moduleName)+'-lab-cases.json','utf8')).filter(c=>c.ros_version===(version.startsWith('7')?'7':'6')&&(!edgeOnly||c.id==='ros7-two-netwatch-protocol'));
 for(const test of cases){
  try{
   if(starlinkProtocol)for(const peer of wires.peers.slice(0,2)){peer.gateway=`192.168.${peer.index+1}.1`;peer.echoDisabled.clear();peer.dhcpEvents=[];}
   const proof=await command(':put [/system identity get name]');if(!proof.includes(identity))throw new Error('Lab identity guard failed.');
   const extra=moduleName==='starlink'||starlinkProtocol?['/tool netwatch remove [find]','/ip dhcp-client remove [find]',version.startsWith('7')?'/routing rule remove [find]':'/ip route rule remove [find]']:[];
   for(const cleanup of [...extra,'/ip firewall mangle remove [find]','/ip firewall nat remove [find]','/ip firewall address-list remove [find]','/ip route remove [find where dynamic=no]',...(version.startsWith('7')?['/routing table remove [find where name~"^to-ISP|^STARLINK"]']:[])])await command(cleanup);
   let count=0;let section='';
   for(const line of test.script.split('\n').map(l=>l.trim()).filter(l=>l&&!l.startsWith('#'))){
    if(line.startsWith('/')&&!/\s(?:add|set|remove)\s/.test(line)){section=line;continue;}
    await command(line.startsWith('/')?line:section+' '+line);count++;
   }
   const natOutput=await command('/ip firewall nat print count-only');const nat=Number(natOutput.match(/^\s*(\d+)\s*$/m)?.[1]);if(nat!==(test.wan_count??2))throw new Error('Unexpected NAT rule count '+nat);
   const record={test_id:test.id,status:'SYNTAX_AND_NAT_COUNT_PASSED',commands:count,nat_rules:nat,script_sha256:crypto.createHash('sha256').update(test.script).digest('hex')};
   const scalar=async(expression)=>{const output=await command(':put '+expression);return output.match(/^\s*([^\n]+)\s*$/gm)?.map(line=>line.trim()).find(line=>/^\d+$/.test(line))??null;};
   const waitCount=async(expression,expected,timeout=20000)=>{const until=Date.now()+timeout;let observed;do{observed=Number(await scalar(expression));if(observed===expected)return observed;await pause(700);}while(Date.now()<until);throw new Error('Protocol count mismatch: expected '+expected+', observed '+observed+' for '+expression);};
   if(starlinkProtocol){
    record.input_basis=test.basis;
    record.dhcp_bound_clients=await waitCount('[:len [/ip dhcp-client find where status=bound]]',2);
    record.bound_route_count=await waitCount('[:len [/ip route find where comment~"^(MAIN|LB|MONITOR)-STARLINK"]]',test.netwatch?6:4);
    await waitCount('[:len [/ip route find where comment="LB-STARLINK1" and gateway="192.168.1.1%ether1"]]',1);
    if(test.netwatch){console.log('Waiting for unchanged RouterOS Netwatch startup delay; no probe defaults are overridden.');record.netwatch_initial_up=await waitCount('[:len [/tool netwatch find where status=up]]',2,330000);}
   }
   if(packetMode){
    for(let i=1;i<=2;i++){const probe=await command('/ping address=192.168.'+i+'.1 count=1 interface=ether'+i);if(!/received=1/.test(probe))throw new Error('Synthetic WAN gateway did not answer ICMP: '+redact(probe));}
    await pause(1500);
    const flow=await wires.flowBatch(400);const classifiers=test.script.split('\n').filter(line=>line.includes('per-connection-classifier='));const expected=classifiers.filter(line=>line.includes('new-connection-mark="'+(starlinkProtocol?'STARLINK1-CONN':'ISP1-conn')+'"')).length/classifiers.length;
    record.packet_flow={...flow,expected_wan1_share:expected,maximum_absolute_share_deviation:0.12};
    if(flow.flows_observed!==400||flow.duplicates||flow.invalid_nat_or_destination.length||Math.abs(flow.wan1/400-expected)>0.12)throw new Error('Synthetic PCC packet verification failed: '+JSON.stringify(record.packet_flow));
    record.status='SYNTHETIC_UDP_DISTRIBUTION_AND_NAT_PASSED';
    if(test.id==='ros7-two-wan-equal'){
     await command('/interface ethernet disable ether1');await pause(1000);record.link_down_observation=await wires.flowBatch(100);await command('/interface ethernet enable ether1');
    }
    if(starlinkProtocol){
     if(test.netwatch){
      wires.peers[0].echoDisabled.add('1.1.1.1');record.netwatch_down=await waitCount('[:len [/tool netwatch find where host=1.1.1.1 and status=down]]',1,45000);
      record.disabled_routes=await waitCount('[:len [/ip route find where disabled=yes and comment~"^(MAIN|LB)-STARLINK1"]]',2);
      record.monitor_down_flow=await wires.flowBatch(100);if(record.monitor_down_flow.wan2!==100||record.monitor_down_flow.invalid_nat_or_destination.length)throw new Error('Netwatch outage did not forward all new flows through WAN2.');
      if(edgeOnly){
       wires.peers[0].gateway='192.168.1.254';await command('/ip dhcp-client renew [find interface=ether1]');await waitCount('[:len [/ip route find where comment="LB-STARLINK1" and gateway="192.168.1.254%ether1"]]',1);
       const stillDown=Number(await scalar('[:len [/tool netwatch find where host=1.1.1.1 and status=down]]'))===1;
       const enabled=Number(await scalar('[:len [/ip route find where disabled=no and comment~"^(MAIN|LB)-STARLINK1"]]'));
       record.renew_while_down={monitor_still_down:stillDown,enabled_main_and_lb_routes:enabled,review_required:stillDown&&enabled>0};
       wires.peers[0].gateway='192.168.1.1';await command('/ip dhcp-client renew [find interface=ether1]');await waitCount('[:len [/ip route find where comment="LB-STARLINK1" and gateway="192.168.1.1%ether1"]]',1);
      }
      wires.peers[0].echoDisabled.clear();record.netwatch_restored_up=await waitCount('[:len [/tool netwatch find where status=up]]',2,45000);await waitCount('[:len [/ip route find where disabled=yes and comment~"^(MAIN|LB)-STARLINK1"]]',0);
     }
     wires.peers[0].gateway='192.168.1.254';await command('/ip dhcp-client renew [find interface=ether1]');
     try{await waitCount('[:len [/ip route find where comment="LB-STARLINK1" and gateway="192.168.1.254%ether1"]]',1);}catch(error){
      const client=await command(':put [/ip dhcp-client get [find interface=ether1] gateway]');const route=await command(':put [/ip route get [find comment="LB-STARLINK1"] gateway]');
      error.lab_diagnostics={server_replies:wires.peers[0].dhcpEvents.slice(-4),client_gateway_output:redact(client),route_gateway_output:redact(route),packet_flow:record.packet_flow};throw error;
     }
     record.renewed_gateway='192.168.1.254%ether1';await waitCount('[:len [/ip route find where comment~"^(MAIN|LB|MONITOR)-STARLINK"]]',test.netwatch?6:4);
     await command('/ip dhcp-client disable [find interface=ether1]');record.unbound_route_count=await waitCount('[:len [/ip route find where comment~"^(MAIN|LB|MONITOR)-STARLINK1"]]',0);
     record.unbound_flow=await wires.flowBatch(100);if(record.unbound_flow.wan2!==100||record.unbound_flow.invalid_nat_or_destination.length)throw new Error('DHCP unbind did not retain forwarding through WAN2.');
     const dns=await command('/ip dns get servers');if(dns.includes('10.255.0.53'))throw new Error('DHCP peer DNS overrode use-peer-dns=no.');record.peer_dns_ignored=true;record.dhcp_protocol_replies=wires.peers.slice(0,2).map(peer=>peer.dhcpEvents.length);record.dhcp_gateway_options_per_wan=wires.peers.slice(0,2).map(peer=>[...new Set(peer.dhcpEvents.map(event=>event.gateway))]);
     wires.peers[0].gateway='192.168.1.1';record.status=record.renew_while_down?.review_required?'OBSERVED_EDGE_REQUIRES_REVIEW':'DHCP_PROTOCOL_UDP_NAT_RENEW_UNBIND_AND_MONITORING_PASSED';
    }
   }
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
  }catch(error){const record={test_id:test.id,status:error.message.startsWith('Console timeout')?'BLOCKED_AUTOMATION':'FAILED',error:redact(error.message),...(error.lab_diagnostics?{diagnostics:error.lab_diagnostics}:{})};report.cases.push(record);console.log(JSON.stringify(record));if(record.status==='BLOCKED_AUTOMATION')break;}
 }
 if(version.startsWith('7')&&moduleName==='pcc'){
  const escapedQuote=String.fromCharCode(92,34);
  const probe='/ip firewall nat add chain=srcnat action=masquerade out-interface='+escapedQuote+'ether1'+escapedQuote+' comment="NANSAN-QUOTE-PROBE"';
  const result=await exchange(probe,prompt,20000);
  report.escaped_interface_quote_probe={input_quote_codepoints:[...escapedQuote].map(c=>c.codePointAt(0)),accepted:!/bad command name|syntax error|expected end of command|input does not match|failure:|invalid value/i.test(result),result:redact(result)};
  console.log(JSON.stringify({probe:'escaped_interface_quote',accepted:report.escaped_interface_quote_probe.accepted,result:report.escaped_interface_quote_probe.result}));
 }
 report.status=report.cases.some(c=>c.status==='BLOCKED_AUTOMATION')?'BLOCKED':report.cases.some(c=>c.status==='OBSERVED_EDGE_REQUIRES_REVIEW')?'REVIEW_REQUIRED':report.cases.every(c=>c.status!=='FAILED')?(packetMode?'SCOPED_PACKET_CHECKS_PASSED':'SCOPED_SYNTAX_CHECKS_PASSED'):'FAILED';
}catch(error){report.status='BLOCKED';report.error=redact(error.message);console.log(JSON.stringify({status:report.status,error:report.error}));}
finally{
 socket?.destroy();wires?.close();if(vm.exitCode===null)vm.kill();
 if(packetMode){
  await pause(500);
  report.capture_readback=[];
  for(let i=1;i<=2;i++)try{report.capture_readback.push({wan:i,...await inspectCapture(path.join(root,'.runtime',`${packetTag}-${version}-wan${i}.pcap`))});}catch(error){report.capture_readback.push({wan:i,error:error.message});}
 }
 report.completed_at=new Date().toISOString();await fs.mkdir('docs/lab',{recursive:true});const filename='docs/lab/'+(edgeOnly?'STARLINK-EDGE-':starlinkProtocol?'STARLINK-PROTOCOL-':packetMode?'PCC-PACKETS-':moduleName==='starlink'?'STARLINK-':'CHR-')+version+'.json';await fs.writeFile(filename,JSON.stringify(report,null,2)+'\n');
 console.log('Owned VM stopped; temporary snapshot discarded. Report: '+filename);
}
if(report.status==='BLOCKED'||report.status==='FAILED')process.exitCode=1;
if(report.status==='REVIEW_REQUIRED')process.exitCode=2;
