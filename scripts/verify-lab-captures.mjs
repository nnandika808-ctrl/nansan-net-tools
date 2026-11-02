import fs from 'node:fs/promises';
import {inspectCapture} from './lab-pcap.mjs';

for(const version of ['6.49.19','7.25']){
 const filename=`docs/lab/PCC-PACKETS-${version}.json`;const report=JSON.parse(await fs.readFile(filename,'utf8'));
 if(!report.cases.length||report.cases.some(test=>test.status!=='SYNTHETIC_UDP_DISTRIBUTION_AND_NAT_PASSED'))throw new Error('Only successful recorded packet cases can be verified.');
 report.capture_readback=[];
 for(let wan=1;wan<=2;wan++){
  const capture=await inspectCapture(`.runtime/pcc-packets-${version}-wan${wan}.pcap`);
  const expected=report.cases.reduce((sum,test)=>sum+test.packet_flow['wan'+wan]+(test.link_down_observation?.['wan'+wan]??0),0);
  if(capture.synthetic_udp_packets!==expected||Object.keys(capture.nat_source_counts).some(source=>source!==`192.168.${wan}.10`))throw new Error('PCAP does not corroborate the live packet report.');
  report.capture_readback.push({wan,...capture,expected_packets:expected,consistent:true});
 }
 report.capture_readback_recorded_at=new Date().toISOString();report.status='SCOPED_PACKET_CHECKS_PASSED';
 await fs.writeFile(filename,JSON.stringify(report,null,2)+'\n');console.log(JSON.stringify({version,pcap_packets:report.capture_readback.map(capture=>capture.synthetic_udp_packets),consistent:true}));
}
