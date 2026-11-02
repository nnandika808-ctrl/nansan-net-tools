// Synthetic Ethernet over QEMU's loopback TCP framing. No raw host sockets,
// OS routes, bridges or external packets. These peers emulate lab gateways.
import net from 'node:net';
import crypto from 'node:crypto';

const pause=ms=>new Promise(resolve=>setTimeout(resolve,ms));
const ip=value=>Buffer.from(value.split('.').map(Number));
const mac=value=>Buffer.from(value.split(':').map(part=>parseInt(part,16)));
function checksum(bytes){let sum=0;for(let i=0;i<bytes.length;i+=2)sum+=(bytes[i]<<8)+(bytes[i+1]??0);while(sum>>>16)sum=(sum&65535)+(sum>>>16);return (~sum)&65535;}
function ipv4(source,destination,protocol,body,id=1){const header=Buffer.alloc(20);header[0]=0x45;header.writeUInt16BE(20+body.length,2);header.writeUInt16BE(id&65535,4);header[8]=64;header[9]=protocol;ip(source).copy(header,12);ip(destination).copy(header,16);header.writeUInt16BE(checksum(header),10);return Buffer.concat([header,body]);}
function ethernet(destination,source,type,body){const header=Buffer.alloc(14);mac(destination).copy(header);mac(source).copy(header,6);header.writeUInt16BE(type,12);const frame=Buffer.concat([header,body]);return frame.length<60?Buffer.concat([frame,Buffer.alloc(60-frame.length)]):frame;}
const macText=bytes=>[...bytes].map(byte=>byte.toString(16).padStart(2,'0')).join(':');
const ipText=bytes=>[...bytes].join('.');

export class VirtualPeer {
 constructor({port,index,onPacket}){this.port=port;this.index=index;this.onPacket=onPacket;this.enabled=true;this.buffer=Buffer.alloc(0);this.ownMac='02:4e:41:4e:53:0'+(index+1);this.echoes=0;this.arpReplies=0;this.dhcp=false;this.gateway=`192.168.${index+1}.1`;this.lease=`192.168.${index+1}.10`;this.echoDisabled=new Set();this.dhcpEvents=[];}
 async connect(){
  for(let attempt=0;attempt<100;attempt++){
   try{this.socket=await new Promise((resolve,reject)=>{const socket=net.connect({host:'127.0.0.1',port:this.port});socket.once('connect',()=>resolve(socket));socket.once('error',reject);});break;}catch{await pause(100);}
  }
  if(!this.socket)throw new Error('Disposable QEMU Ethernet socket unavailable.');
  this.socket.on('error',()=>{});this.socket.on('data',chunk=>{this.buffer=Buffer.concat([this.buffer,chunk]);while(this.buffer.length>=4){const length=this.buffer.readUInt32BE(0);if(length>65536){this.socket.destroy();return;}if(this.buffer.length<length+4)return;const frame=this.buffer.subarray(4,length+4);this.buffer=this.buffer.subarray(length+4);this.receive(frame);}});
 }
 send(frame){if(!this.socket||this.socket.destroyed)return;const header=Buffer.alloc(4);header.writeUInt32BE(frame.length);this.socket.write(Buffer.concat([header,frame]));}
 receive(frame){
  if(frame.length<14)return;this.onPacket?.(frame,this.index);if(!this.enabled)return;
  const type=frame.readUInt16BE(12);
  if(type===0x0806&&frame.length>=42&&frame.readUInt16BE(20)===1){
   const target=ipText(frame.subarray(38,42));const allowed=this.index<2?[`192.168.${this.index+1}.1`,`192.168.${this.index+1}.254`].includes(target):(target.startsWith('192.168.88.')&&Number(target.split('.').at(-1))>=2);if(!allowed)return;
   const reply=Buffer.alloc(28);reply.writeUInt16BE(1,0);reply.writeUInt16BE(0x0800,2);reply[4]=6;reply[5]=4;reply.writeUInt16BE(2,6);mac(this.ownMac).copy(reply,8);frame.copy(reply,14,38,42);frame.copy(reply,18,22,28);frame.copy(reply,24,28,32);
   this.send(ethernet(macText(frame.subarray(6,12)),this.ownMac,0x0806,reply));this.arpReplies++;return;
  }
  if(type!==0x0800||frame.length<34||this.index>=2)return;
  const ihl=(frame[14]&15)*4;const protocol=frame[23];
  if(this.dhcp&&protocol===17&&frame.length>=14+ihl+8&&frame.readUInt16BE(14+ihl)===68&&frame.readUInt16BE(14+ihl+2)===67){this.replyDhcp(frame,ihl);return;}
  if(protocol!==1||frame.length<14+ihl+8||frame[14+ihl]!==8)return;
  const destination=ipText(frame.subarray(30,34));if(this.echoDisabled.has(destination)||![`192.168.${this.index+1}.1`,`192.168.${this.index+1}.254`,'1.1.1.1','1.1.1.2','8.8.8.8'].includes(destination))return;
  const body=Buffer.from(frame.subarray(14+ihl,14+frame.readUInt16BE(16)));body[0]=0;body.writeUInt16BE(0,2);body.writeUInt16BE(checksum(body),2);
  this.send(ethernet(macText(frame.subarray(6,12)),this.ownMac,0x0800,ipv4(destination,ipText(frame.subarray(26,30)),1,body)));this.echoes++;
 }
 replyDhcp(frame,ihl){
  const request=frame.subarray(14+ihl+8);if(request.length<240||request[0]!==1||!request.subarray(236,240).equals(Buffer.from([99,130,83,99])))return;
  const options=new Map();for(let offset=240;offset<request.length;){const code=request[offset++];if(code===255)break;if(code===0)continue;const length=request[offset++];if(offset+length>request.length)break;options.set(code,request.subarray(offset,offset+length));offset+=length;}
  const type=options.get(53)?.[0];if(![1,3].includes(type))return;
  const server=`192.168.${this.index+1}.1`;if(options.has(54)&&!options.get(54).equals(ip(server)))return;
  const reply=Buffer.alloc(240);reply[0]=2;reply[1]=1;reply[2]=6;request.copy(reply,4,4,8);request.copy(reply,10,10,12);if(type===3)request.copy(reply,12,12,16);ip(this.lease).copy(reply,16);ip(server).copy(reply,20);request.copy(reply,28,28,44);Buffer.from([99,130,83,99]).copy(reply,236);
  const option=(code,value)=>Buffer.concat([Buffer.from([code,value.length]),value]);const duration=value=>{const bytes=Buffer.alloc(4);bytes.writeUInt32BE(value);return bytes;};
  const responseOptions=[option(53,Buffer.from([type===1?2:5])),option(54,ip(server)),option(1,ip('255.255.255.0')),option(3,ip(this.gateway)),option(6,ip('10.255.0.53')),option(51,duration(120)),option(58,duration(30)),option(59,duration(90))];
  if(options.has(61))responseOptions.push(option(61,options.get(61)));responseOptions.push(Buffer.from([255]));const body=Buffer.concat([reply,...responseOptions]);
  const udp=Buffer.alloc(8);udp.writeUInt16BE(67,0);udp.writeUInt16BE(68,2);udp.writeUInt16BE(8+body.length,4);
  const current=ipText(request.subarray(12,16));const broadcast=(request.readUInt16BE(10)&0x8000)!==0;const destination=current!=='0.0.0.0'?current:broadcast?'255.255.255.255':this.lease;
  this.send(ethernet(broadcast?'ff:ff:ff:ff:ff:ff':macText(request.subarray(28,34)),this.ownMac,0x0800,ipv4(server,destination,17,Buffer.concat([udp,body]))));
  this.dhcpEvents.push({message:type===1?'OFFER':'ACK',gateway:this.gateway,lease:this.lease,xid:request.readUInt32BE(4)});
 }
 close(){this.socket?.destroy();}
}

export class PacketLab {
 constructor(ports,routerMac,{dhcp=false}={}){
  this.routerMac=routerMac;this.activeBatch=null;this.captured=new Map();this.duplicates=0;this.invalid=[];this.batchCounter=0;
  this.peers=ports.map((port,index)=>new VirtualPeer({port,index,onPacket:(frame,wan)=>this.capture(frame,wan)}));
  for(const peer of this.peers.slice(0,2))peer.dhcp=dhcp;
 }
 async connect(){await Promise.all(this.peers.map(peer=>peer.connect()));}
 capture(frame,wan){
  if(wan>1||frame.length<42||frame.readUInt16BE(12)!==0x0800||frame[23]!==17)return;
  const ihl=(frame[14]&15)*4;const payload=frame.subarray(14+ihl+8).toString('ascii');const prefix='NANSAN_PCC_'+this.activeBatch+':';if(!payload.startsWith(prefix))return;
  const sequence=Number(payload.slice(prefix.length));if(!Number.isInteger(sequence))return;
  const source=ipText(frame.subarray(26,30));const destination=ipText(frame.subarray(30,34));
  if(source!==`192.168.${wan+1}.10`||destination!=='198.18.0.123')this.invalid.push({sequence,wan:wan+1,source,destination});
  if(this.captured.has(sequence))this.duplicates++;
  this.captured.set(sequence,{wan:wan+1,source,destination});
 }
 async flowBatch(count=400){
  this.activeBatch=crypto.randomBytes(6).toString('hex');this.captured.clear();this.invalid=[];this.duplicates=0;
  const basePort=12000+this.batchCounter++*1000;
  for(let sequence=0;sequence<count;sequence++){
   const payload=Buffer.from('NANSAN_PCC_'+this.activeBatch+':'+sequence);const udp=Buffer.alloc(8);udp.writeUInt16BE(basePort+sequence,0);udp.writeUInt16BE(40000+sequence,2);udp.writeUInt16BE(8+payload.length,4);
   const packet=ipv4('192.168.88.'+(2+sequence%240),'198.18.0.123',17,Buffer.concat([udp,payload]),sequence);
   this.peers[2].send(ethernet(this.routerMac,this.peers[2].ownMac,0x0800,packet));await pause(3);
  }
  const until=Date.now()+3000;while(this.captured.size<count&&Date.now()<until)await pause(50);
  return {flows_sent:count,flows_observed:this.captured.size,wan1:[...this.captured.values()].filter(packet=>packet.wan===1).length,wan2:[...this.captured.values()].filter(packet=>packet.wan===2).length,missing:count-this.captured.size,duplicates:this.duplicates,invalid_nat_or_destination:this.invalid,samples:[...this.captured.entries()].slice(0,4).map(([sequence,packet])=>({sequence,...packet}))};
 }
 close(){for(const peer of this.peers)peer.close();}
}
