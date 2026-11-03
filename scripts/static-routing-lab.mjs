import fs from 'node:fs/promises';
import path from 'node:path';
import net from 'node:net';
import os from 'node:os';
import crypto from 'node:crypto';
import {spawn} from 'node:child_process';
import {Client} from 'ssh2';
import {parseStaticRoutingDataset} from './static-routing-data-parser.mjs';

const version=process.argv[2];
const smoke=process.argv[3]==='--smoke';
const referenceDuplicates=process.argv[3]==='--reference-duplicates';
if(!['6.49.19','7.25'].includes(version)||process.argv.length>4||(process.argv[3]&&!smoke&&!referenceDuplicates))throw new Error('Only approved disposable local CHR versions and named local scenarios; no router address accepted.');
const memory=version.startsWith('7')?384:256;
if(os.freemem()<(memory+256)*1024**2)throw new Error('Insufficient memory for isolated VM and host headroom.');
const root=process.cwd(),image=path.join(root,'.runtime/lab-tools',version,'chr-'+version+'.img');
const freePort=async()=>{const s=net.createServer();await new Promise(r=>s.listen(0,'127.0.0.1',r));const p=s.address().port;await new Promise(r=>s.close(r));return p;};
const serialPort=await freePort(),sshPort=await freePort();
const identity='NANSAN-STATIC-LAB-'+crypto.randomBytes(5).toString('hex'),password=crypto.randomBytes(20).toString('hex');
const args=['-machine','pc','-accel','tcg','-cpu','qemu64','-smp','1','-m',String(memory),'-snapshot','-drive','file='+image.replaceAll('\\','/')+',format=raw,if=ide','-display','none','-monitor','none','-serial',`tcp:127.0.0.1:${serialPort},server=on,wait=off`];
for(let i=0;i<3;i++)args.push('-netdev',i===2?`user,id=net2,net=10.23.45.0/24,restrict=on,hostfwd=tcp:127.0.0.1:${sshPort}-10.23.45.15:22`:`user,id=net${i},net=${i?'198.51.100':'192.0.2'}.0/24,restrict=on`,'-device',`e1000,netdev=net${i},mac=52:54:00:51:00:0${i+1}`);
const vm=spawn(path.join(root,'.runtime/lab-tools/qemu/qemu-system-x86_64.exe'),args,{stdio:['ignore','ignore','pipe'],windowsHide:true});
let stderr='',socket,ssh,sshReady=false,buffer='';vm.stderr.on('data',d=>{stderr+=d;});
const pause=ms=>new Promise(r=>setTimeout(r,ms));
const plain=s=>s.replace(/\x1b\[[0-?]*[ -/]*[@-~]/g,'').replace(/\r/g,'');
const redact=s=>plain(s).replaceAll(password,'<redacted>').slice(-800);
const prompt=/(?:\[[^\n]*@[^\n]*\]|<[^\n]*\])[^\n]*>\s*$/m;
async function waitFor(pattern,timeout=30000){const until=Date.now()+timeout;while(Date.now()<until){if(pattern.test(plain(buffer)))return plain(buffer);if(vm.exitCode!==null)throw new Error('Owned VM exited: '+stderr.slice(-200));await pause(30);}throw new Error('Console timeout: '+redact(buffer));}
async function exchange(text,pattern,timeout){buffer='';socket.write(text+'\r');return waitFor(pattern,timeout);}
async function command(text){
  if(vm.exitCode!==null||vm.signalCode!==null)throw new Error('Owned VM exited; automation unavailable.');
  let out;
  if(sshReady){
    out=await new Promise((resolve,reject)=>{
      ssh.exec(text,(error,stream)=>{
        if(error)return reject(error);let response='';
        const timeout=setTimeout(()=>{stream.close();reject(new Error('SSH command timeout'));},180000);
        stream.on('data',d=>{response+=d;});stream.stderr.on('data',d=>{response+=d;});stream.on('error',reject);
        stream.on('close',code=>{clearTimeout(timeout);if(code&&code!==0)return reject(new Error('RouterOS SSH exit '+code+': '+redact(response)));resolve(plain(response));});
      });
    });
  }else{
    const marker='NANSAN_ACK_'+crypto.randomBytes(5).toString('hex');out=await exchange(text+'; :put "'+marker+'"',new RegExp('(?:^|\\n)'+marker+'\\s*\\n[\\s\\S]*'+prompt.source),45000);
  }
  if(/bad command name|syntax error|expected end of command|input does not match|failure:|invalid value|script error|no such item|no such command/i.test(out))throw new Error(redact(out));return out;
}
const report={started_at:new Date().toISOString(),image_version:version,image_sha256:crypto.createHash('sha256').update(await fs.readFile(image)).digest('hex'),emulator:'QEMU 11.1.0 TCG',isolation:'Fresh snapshot CHR; three restricted user-mode networks, loopback-only console and host-forwarded management SSH; no physical router, bridge or Internet access',scope:'Actual SFTP-staged dataset import and generated STEP 2 command acceptance. STEP 1 HTTPS download, application traffic, prefix freshness and ISP routing are not exercised.',preconditions:['Disposable WAN addresses and SSH management address are lab-only','An existing sentinel mangle rule is preserved; reference place-before=*0 is tested as rendered and an empty-mangle case is checked separately'],cases:[],status:'RUNNING'};
try{
  console.log('Isolated Static Routing CHR '+version+' launched.');
  for(let i=0;i<80;i++){try{socket=await new Promise((resolve,reject)=>{const s=net.connect({host:'127.0.0.1',port:serialPort});s.once('connect',()=>resolve(s));s.once('error',reject);});break;}catch{await pause(200);}}
  if(!socket)throw new Error('Owned serial console unavailable');
  socket.on('data',d=>{buffer+=d;if(buffer.length>500000)buffer=buffer.slice(-250000);});socket.on('error',()=>{});
  socket.write('\r');await waitFor(/Login:/i,90000);
  const loginPrompt=/(?:Password:|(?:repeat )?new password>|software license\?[^\n]*:|\[[^\n]*@[^\n]*\][^\n]*>)\s*$/i;
  let out=await exchange('admin',loginPrompt,30000);
  for(let i=0;i<8&&!prompt.test(out);i++){
    const answer=/new password>\s*$/i.test(out)?password:/software license\?[^\n]*:\s*$/i.test(out)?'n':'';
    out=await exchange(answer,loginPrompt,30000);
  }
  if(!prompt.test(out))throw new Error('Disposable console login unavailable: '+redact(out));
  await pause(350);await exchange('',prompt,30000);await pause(200);
  await command('/system identity set name='+identity);
  await command('/user set admin password="'+password+'"');
  await command('/ip address add address=10.23.45.15/24 interface=ether3');
  await command('/ip address add address=192.168.1.10/24 interface=ether1');
  await command('/ip address add address=192.168.2.10/24 interface=ether2');
  await command('/ip service enable ssh');
  await command('/ip firewall mangle add chain=prerouting action=passthrough comment="NANSAN-LAB-ANCHOR"');
  const anchor=await command(':put [/ip firewall mangle find where comment="NANSAN-LAB-ANCHOR"]');
  report.anchor_internal_id=anchor.match(/^\s*\*[a-f0-9]+\s*$/mi)?.[0]?.trim()??'unparsed';
  ssh=new Client();ssh.on('error',()=>{});await new Promise((resolve,reject)=>{ssh.once('ready',resolve);ssh.once('error',reject);ssh.connect({host:'127.0.0.1',port:sshPort,username:'admin',password,readyTimeout:120000,debug:line=>{if(/Remote ident|Socket connected|Handshake completed/.test(line)){report.ssh_progress??=[];report.ssh_progress.push(line);console.log(line);}}});});
  sshReady=true;report.control_transport='Noninteractive SSH exec on the owned loopback management forward, after serial bootstrap';
  const sftp=await new Promise((resolve,reject)=>ssh.sftp((e,s)=>e?reject(e):resolve(s)));
  const put=(name,data)=>new Promise((resolve,reject)=>{const stream=sftp.createWriteStream(name);stream.on('error',reject);stream.on('close',resolve);stream.end(data);});
  const cases=JSON.parse(await fs.readFile('.runtime/static-routing-lab-cases.json','utf8')).filter(c=>c.ros_version===(version.startsWith('7')?'7':'6')&&(!smoke||['Youtube','Tiktok','Netflix','Teams','Telegram'].includes(c.application))&&(!referenceDuplicates||['Teams','Telegram'].includes(c.application)));
  if(referenceDuplicates){
    const manifest=JSON.parse(await fs.readFile('resources/datasets/static-routing/manifest.json','utf8'));
    for(const test of cases){
      const entry=manifest.datasets.find(e=>e.application===test.application&&e.mode===test.mode);
      const original=await fs.readFile('.runtime/reference-datasets/'+entry.filename,'utf8');
      if(crypto.createHash('sha256').update(original).digest('hex')!==entry.sha256)throw new Error('Original source hash changed.');
      parseStaticRoutingDataset(original,test.application);
      const filename='Mikrotik-'+test.application+'.rsc';test.step2=test.step2.replaceAll(test.filename,filename);test.filename=filename;test.dataset=original;test.id='source-'+test.id;
    }
  }
  report.selection=referenceDuplicates?'Original public source RSCs for Teams/Telegram, hashes checked and command allowlist parsed before execution':smoke?'Additional strict file-cleanup smoke: Youtube/Tiktok/Netflix/Teams/Telegram, both matchers':'All 92 available app/matcher datasets';
  const empty={...cases.find(c=>c.application==='Youtube'&&c.mode==='tls-host'),id:'ros'+(version.startsWith('7')?'7':'6')+'-youtube-tls-host-empty-mangle',empty:true};
  for(const test of referenceDuplicates?cases:[...cases,empty]){
    let stage='cleanup';
    const record={test_id:test.id,application:test.application,mode:test.mode,dataset_sha256:crypto.createHash('sha256').update(test.dataset).digest('hex'),step2_sha256:crypto.createHash('sha256').update(test.step2).digest('hex'),mangle_anchor:!test.empty,status:'RUNNING'};
    try{
      const proof=await command(':put [/system identity get name]');if(!proof.includes(identity))throw new Error('Owned VM identity check failed');
      for(const cleanup of ['/ip firewall raw remove [find]','/ip firewall address-list remove [find]',test.empty?'/ip firewall mangle remove [find]':'/ip firewall mangle remove [find where comment!="NANSAN-LAB-ANCHOR"]','/ip route remove [find where dynamic=no]',...(version.startsWith('7')?['/routing table remove [find where name!="main"]']:[])])await command(cleanup);
      stage='upload';await put(test.filename,test.dataset);
      const visibleUntil=Date.now()+15000;let visible=false;
      while(Date.now()<visibleUntil){const count=await command(':put [:len [/file find where name="'+test.filename+'"]]');if(/^\s*1\s*$/m.test(count)){visible=true;break;}await pause(200);}
      if(!visible)throw new Error('SFTP upload is not visible in RouterOS file inventory; lab staging unavailable.');record.upload_file_visible=true;
      for(const raw of test.step2.split('\n')){
        const line=raw.trim();if(!line||line.startsWith('#'))continue;
        if(line.startsWith('/import '))stage='dataset_import';else if(line.startsWith('/file '))stage='file_cleanup';else stage='routing_wrapper';
        // RouterOs::render emits section headers; preserve the current section.
        if(line.startsWith('/')&&!/\s(?:add|set|remove)\s/.test(line)&&!line.startsWith('/import ')){record.section=line;continue;}
        record.last_command=line.startsWith('/')?line:record.section+' '+line;await command(record.last_command);
        if(line.startsWith('/import '))record.dataset_import='PASSED';
      }
      delete record.section;delete record.last_command;
      const fileCount=await command(':put [:len [/file find where name="'+test.filename+'"]]');if(!/^\s*0\s*$/m.test(fileCount))throw new Error('Dataset file cleanup was incomplete');record.dataset_file_removed=true;
      const count=await command(':put [:len [/ip firewall mangle find where action="mark-routing"]]');
      if(!/^\s*1\s*$/m.test(count))throw new Error('Expected one mark-routing rule');
      record.status='DATASET_IMPORT_AND_WRAPPER_SYNTAX_PASSED';
    }catch(error){delete record.section;record.status='FAILED';record.failed_stage=stage;record.error=redact(error.message);}
    report.cases.push(record);console.log(JSON.stringify({test_id:record.test_id,status:record.status,stage:record.failed_stage}));
    await fs.writeFile('.runtime/static-routing-progress-'+version+'.json',JSON.stringify(report,null,2));
    if(record.error&&/Console timeout|SSH command timeout|Owned VM exited/.test(record.error))throw new Error('Automation transport unavailable; stopping owned lab.');
  }
  report.status=report.cases.some(c=>c.status==='FAILED')?'SCOPED_FAILURES_RECORDED':'SCOPED_IMPORT_SYNTAX_PASSED';
}catch(error){report.status='BLOCKED_AUTOMATION';report.error=redact(error.message);console.log(JSON.stringify({status:report.status,error:report.error}));}
finally{
  ssh?.end();socket?.destroy();if(vm.exitCode===null){vm.kill();await Promise.race([new Promise(r=>vm.once('exit',r)),pause(3000)]);}
  report.completed_at=new Date().toISOString();report.owned_vm_stopped=vm.exitCode!==null||vm.signalCode!==null;
  await fs.writeFile('docs/lab/STATIC-ROUTING-'+(referenceDuplicates?'SOURCE-DUPLICATES-':smoke?'SMOKE-':'')+version+'.json',JSON.stringify(report,null,2)+'\n');console.log('Owned VM stopped; Static Routing report saved.');
}
if(report.status==='BLOCKED_AUTOMATION')process.exitCode=1;
