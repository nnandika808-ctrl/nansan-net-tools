import fs from 'node:fs/promises';
import path from 'node:path';
import net from 'node:net';
import os from 'node:os';
import crypto from 'node:crypto';
import {spawn} from 'node:child_process';
import {Client} from 'ssh2';

export async function withRouterOsLab(version,task,exercise){
  if(!['6.49.19','7.25'].includes(version)||!['queue-qos','routing-games'].includes(task))throw new Error('Only approved local snapshot images/tasks; no router address accepted.');
  const memory=version.startsWith('7')?384:256;
  if(os.freemem()<(memory+256)*1024**2)throw new Error('Insufficient free memory for the lab and host headroom.');
  const root=process.cwd(),image=path.join(root,'.runtime/lab-tools',version,'chr-'+version+'.img');
  const imageHash=crypto.createHash('sha256').update(await fs.readFile(image)).digest('hex');
  const baseline=JSON.parse(await fs.readFile('docs/lab/CHR-'+version+'.json','utf8'));
  if(baseline.image_sha256!==imageHash)throw new Error('Approved lab image digest changed.');
  const port=async()=>{const s=net.createServer();await new Promise(r=>s.listen(0,'127.0.0.1',r));const p=s.address().port;await new Promise(r=>s.close(r));return p;};
  const serialPort=await port(),sshPort=await port();if(serialPort===sshPort)throw new Error('Management port collision.');
  const identity='NANSAN-'+task+'-'+crypto.randomBytes(4).toString('hex'),password=crypto.randomBytes(20).toString('hex');
  const args=['-machine','pc','-accel','tcg','-cpu','qemu64','-smp','1','-m',String(memory),'-snapshot','-drive','file='+image.replaceAll('\\','/')+',format=raw,if=ide','-display','none','-monitor','none','-serial',`tcp:127.0.0.1:${serialPort},server=on,wait=off`];
  for(let i=0;i<3;i++)args.push('-netdev',i===2?`user,id=net2,net=10.23.45.0/24,restrict=on,hostfwd=tcp:127.0.0.1:${sshPort}-10.23.45.15:22`:`user,id=net${i},net=${i?'198.51.100':'192.0.2'}.0/24,restrict=on`,'-device',`e1000,netdev=net${i},mac=52:54:00:52:00:0${i+1}`);
  const vm=spawn(path.join(root,'.runtime/lab-tools/qemu/qemu-system-x86_64.exe'),args,{stdio:['ignore','ignore','pipe'],windowsHide:true});
  let stderr='',socket,ssh,sshReady=false,buffer='';vm.stderr.on('data',d=>{stderr+=d;});
  const pause=ms=>new Promise(r=>setTimeout(r,ms)),plain=s=>s.replace(/\x1b\[[0-?]*[ -/]*[@-~]/g,'').replace(/\r/g,'');
  const safe=s=>plain(String(s)).replaceAll(password,'<redacted>').slice(-800);
  const prompt=/(?:\[[^\n]*@[^\n]*\]|<[^\n]*\])[^\n]*>\s*$/m;
  const failed=/bad command name|syntax error|expected end of command|input does not match|failure:|invalid value|script error|no such item|no such command/i;
  async function waitFor(pattern,timeout=30000){const until=Date.now()+timeout;while(Date.now()<until){if(pattern.test(plain(buffer)))return plain(buffer);if(vm.exitCode!==null||vm.signalCode!==null)throw new Error('Owned VM exited: '+safe(stderr));await pause(30);}throw new Error('Console timeout: '+safe(buffer));}
  async function exchange(text,pattern,timeout){buffer='';socket.write(text+'\r');return waitFor(pattern,timeout);}
  async function command(text){
    if(vm.exitCode!==null||vm.signalCode!==null)throw new Error('Owned VM exited; automation unavailable.');
    let result;
    if(sshReady){
      result=await new Promise((resolve,reject)=>ssh.exec(text,(error,stream)=>{
        if(error)return reject(error);let output='';const timeout=setTimeout(()=>{stream.close();reject(new Error('SSH command timeout'));},180000);
        stream.on('data',d=>{output+=d;});stream.stderr.on('data',d=>{output+=d;});stream.on('error',reject);
        stream.on('close',code=>{clearTimeout(timeout);if(code)return reject(new Error('RouterOS SSH exit '+code+': '+safe(output)));resolve(plain(output));});
      }));
    }else{
      const marker='NANSAN_ACK_'+crypto.randomBytes(5).toString('hex');result=await exchange(text+'; :put "'+marker+'"',new RegExp('(?:^|\\n)'+marker+'\\s*\\n[\\s\\S]*'+prompt.source),45000);
    }
    if(failed.test(result))throw new Error(text.includes('password=')?'Disposable lab credential setup failed.':safe(result));
    return result.trim();
  }
  const metadata={started_at:new Date().toISOString(),image_version:version,image_sha256:imageHash,emulator:'QEMU 11.1.0 TCG',isolation:'Fresh snapshot, three restricted user-mode networks, loopback console and management SSH; no host bridge, Internet packets or physical router address accepted',control_transport:'Noninteractive SSH after serial bootstrap',owned_vm_stopped:false};
  let result;
  try{
    console.log('Owned '+task+' CHR '+version+' launched.');
    for(let i=0;i<80;i++){try{socket=await new Promise((resolve,reject)=>{const s=net.connect({host:'127.0.0.1',port:serialPort});s.once('connect',()=>resolve(s));s.once('error',reject);});break;}catch{await pause(200);}}
    if(!socket)throw new Error('Owned serial console unavailable.');socket.on('data',d=>{buffer+=d;if(buffer.length>500000)buffer=buffer.slice(-250000);});socket.on('error',()=>{});
    socket.write('\r');await waitFor(/Login:/i,90000);
    const loginPrompt=/(?:Password:|(?:repeat )?new password>|software license\?[^\n]*:|\[[^\n]*@[^\n]*\][^\n]*>)\s*$/i;
    let output=await exchange('admin',loginPrompt,30000);
    for(let i=0;i<8&&!prompt.test(output);i++)output=await exchange(/new password>\s*$/i.test(output)?password:/software license\?[^\n]*:\s*$/i.test(output)?'n':'',loginPrompt,30000);
    if(!prompt.test(output))throw new Error('Disposable login did not complete: '+safe(output));
    await pause(350);await exchange('',prompt,30000);await pause(200);
    await command('/system identity set name='+identity);await command('/user set admin password="'+password+'"');
    await command('/ip address add address=10.23.45.15/24 interface=ether3');await command('/ip service enable ssh');
    console.log('Owned console initialized; waiting for private SSH management.');
    for(let attempt=0;attempt<4;attempt++){
      ssh=new Client();ssh.on('error',()=>{});
      try{await new Promise((resolve,reject)=>{ssh.once('ready',resolve);ssh.once('error',reject);ssh.connect({host:'127.0.0.1',port:sshPort,username:'admin',password,readyTimeout:120000});});sshReady=true;break;}
      catch(error){ssh.destroy();if(attempt===3)throw error;console.log('Owned SSH handshake retry '+(attempt+1)+': '+safe(error.message));await pause(1500);}
    }
    metadata.observed_version=await command(':put [/system resource get version]');if(!metadata.observed_version.startsWith(version))throw new Error('Observed RouterOS version differs from approved image.');
    const sftp=await new Promise((resolve,reject)=>ssh.sftp((e,s)=>e?reject(e):resolve(s)));
    const putFile=async(name,data)=>{
      if(!/^[A-Za-z][A-Za-z0-9_.-]{0,99}$/.test(name)||name.includes('..'))throw new Error('Unsafe lab filename.');
      await new Promise((resolve,reject)=>{const stream=sftp.createWriteStream(name);stream.on('error',reject);stream.on('close',resolve);stream.end(data);});
      const until=Date.now()+15000;while(Date.now()<until){if((await command(':put [:len [/file find where name="'+name+'"]]'))==='1')return;await pause(200);}throw new Error('SFTP file inventory did not become ready.');
    };
    const assertOwned=async()=>{if(!(await command(':put [/system identity get name]')).includes(identity))throw new Error('Owned VM identity mismatch.');};
    result=await exercise({command,putFile,assertOwned,metadata});
  }catch(error){
    error.lab_metadata=metadata;throw error;
  }finally{
    ssh?.end();socket?.destroy();if(vm.exitCode===null&&vm.signalCode===null){vm.kill();await Promise.race([new Promise(r=>vm.once('exit',r)),pause(3000)]);}
    metadata.owned_vm_stopped=vm.exitCode!==null||vm.signalCode!==null;metadata.completed_at=new Date().toISOString();console.log('Owned '+task+' VM stopped; snapshot discarded.');
  }
  return {metadata,result};
}
