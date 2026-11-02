import fs from 'node:fs/promises';
import crypto from 'node:crypto';

// Independent readback of QEMU filter-dump evidence (classic Ethernet libpcap).
export async function inspectCapture(filename){
 const bytes=await fs.readFile(filename);if(bytes.length<24)throw new Error('Missing libpcap header.');
 const little=bytes.readUInt32LE(0)===0xa1b2c3d4;
 if(!little&&bytes.readUInt32BE(0)!==0xa1b2c3d4)throw new Error('Unsupported pcap magic.');
 const u32=offset=>little?bytes.readUInt32LE(offset):bytes.readUInt32BE(offset);
 if(u32(20)!==1)throw new Error('Expected Ethernet capture data.');
 let offset=24,records=0,packets=0;const sources={},batches={};
 while(offset<bytes.length){
  if(offset+16>bytes.length)throw new Error('Truncated pcap record header.');const length=u32(offset+8);offset+=16;
  if(length>65536||offset+length>bytes.length)throw new Error('Truncated pcap packet.');
  const frame=bytes.subarray(offset,offset+length);offset+=length;records++;
  if(frame.length<42||frame.readUInt16BE(12)!==0x0800||frame[23]!==17)continue;
  const ihl=(frame[14]&15)*4;const payload=frame.subarray(14+ihl+8).toString('ascii');const match=payload.match(/^NANSAN_PCC_([a-f0-9]+):(\d+)/);if(!match)continue;
  packets++;const source=[...frame.subarray(26,30)].join('.');sources[source]=(sources[source]??0)+1;batches[match[1]]=(batches[match[1]]??0)+1;
 }
 return {sha256:crypto.createHash('sha256').update(bytes).digest('hex'),bytes:bytes.length,ethernet_records:records,synthetic_udp_packets:packets,nat_source_counts:sources,batch_counts:batches};
}
if(process.argv[1]?.replaceAll('\\','/').endsWith('/lab-pcap.mjs'))for(const filename of process.argv.slice(2))console.log(JSON.stringify({file:filename,...await inspectCapture(filename)}));
