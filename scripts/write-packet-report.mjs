import fs from 'node:fs/promises';

const read=async file=>JSON.parse(await fs.readFile('docs/lab/'+file,'utf8'));
const pcc6=await read('PCC-PACKETS-6.49.19.json'),pcc7=await read('PCC-PACKETS-7.25.json');
const star6=await read('STARLINK-PROTOCOL-6.49.19.json'),star7=await read('STARLINK-PROTOCOL-7.25.json'),edge=await read('STARLINK-EDGE-7.25.json');
const addressLine=text=>text?.split('\n').map(line=>line.trim()).find(line=>/^(?:\d{1,3}\.){3}\d{1,3}(?:%[A-Za-z0-9_.-]+)?$/.test(line))??null;
for(const test of star6.cases)if(test.diagnostics){test.diagnostics.client_gateway=addressLine(test.diagnostics.client_gateway_output);test.diagnostics.route_gateway=addressLine(test.diagnostics.route_gateway_output);}
star6.scope='Real synthetic DHCP exchanges and UDP forwarding; gateway-only renewal failed on CHR 6.49.19. Failed cases are not passing compatibility evidence.';
star7.scope='Loopback-only virtual Ethernet: actual synthetic DHCP DORA/renewal, UDP/NAT, gateway change, unbind and Netwatch events where enabled. No real ISP/modem tested.';
edge.scope='Additional own ROS7 renewal-while-monitor-Down observation; not a new external reference comparison. No all-interleavings claim.';
for(const [file,report] of [['STARLINK-PROTOCOL-6.49.19.json',star6],['STARLINK-PROTOCOL-7.25.json',star7],['STARLINK-EDGE-7.25.json',edge]])await fs.writeFile('docs/lab/'+file,JSON.stringify(report,null,2)+'\n');
const packetRows=reports=>reports.flatMap(report=>report.cases.map(test=>{const flow=test.packet_flow??test.diagnostics?.packet_flow;return `| ${report.image_version} | ${test.test_id} | ${flow?.wan1??'N/A'} | ${flow?.wan2??'N/A'} | ${flow?.missing??'N/A'} | ${test.status} |`;})).join('\n');
const text=`# Packet and DHCP protocol evidence - 2026-11-03

All tests used official snapshot-only CHR images and synthetic Ethernet peers on loopback TCP. No physical/production router or Internet endpoint received these packets. Node emulates ARP, ICMP and a small DHCP server; it is not a general-purpose DHCP implementation. PCAP files remain in ignored .runtime storage; report readbacks include their SHA-256 digests.

## PCC draft

| CHR | Input case | WAN1 flows | WAN2 flows | Missing | Result |
|---|---|---:|---:|---:|---|
${packetRows([pcc6,pcc7])}

Each baseline sends 400 new UDP flows: 2,000 total, all observed with the expected synthetic WAN source NAT address and no duplicate lab payloads. The ratio checks use a finite sample with maximum absolute share deviation 0.12; they do not prove exact bandwidth ratios or all hash inputs. A separate ROS7 link-disable observation forwarded all 100 new flows through WAN2. Independent PCAP readback corroborated 2,100 observed UDP payloads. See PCC-PACKETS-*.json.

## Starlink draft

| CHR | Input case | WAN1 baseline flows | WAN2 baseline flows | Missing | Overall result |
|---|---|---:|---:|---:|---|
${packetRows([star6,star7])}

Three ROS7 protocol cases passed actual synthetic DHCP binding, route creation, 400-flow forwarding/NAT per case, gateway change on renewal, unbind cleanup, fallback forwarding and peer-DNS rejection. The enabled monitoring case also exercised real virtual-ICMP Netwatch down/up transitions and 100-flow WAN2 forwarding while the monitor was down. The extra ROS7 two-WAN monitoring combination is an own test, not an eighth reference projection.

**ROS6 renewal is a confirmed compatibility gap in this independent draft.** In both tested CHR 6.49.19 cases, the DHCP server sent gateway 192.168.1.254, the client reported that new gateway, but LB-STARLINK1 retained 192.168.1.1%ether1. Baseline UDP forwarding passed; the complete renewal cases failed and remain failed. See STARLINK-PROTOCOL-6.49.19.json diagnostics. A later test of the full ROS6 sample pasted by the owner reproduced the same gateway-only renewal limitation, starting with zero DHCP clients; initial import and all 400 baseline flows passed for both the supplied sample and this draft. See [owner-supplied comparison](OWNER-SUPPLIED-STARLINK-6.49.19.json). This is a shared scoped compatibility observation, not an implementation mismatch or a universal vendor bug claim.

The vendor's [official 7.16 changelog](https://download.mikrotik.com/routeros/7.16/CHANGELOG) records a change to trigger DHCP client scripts on DNS/gateway changes. That is consistent with the observed version dependency; every older release has not been tested. No firmware was upgraded and no compatibility workaround was silently inserted into generated commands.

The additional ROS7 renewal-while-monitor-Down observation recorded monitor_still_down=true, enabled_main_and_lb_routes=0 and review_required=false. It did not establish the hypothesized route-reactivation issue; it does not prove every possible event interleaving. See STARLINK-EDGE-7.25.json.

## Protocol and timing sources

QEMU's [socket backend and filter-dump documentation](https://www.qemu.org/docs/master/system/invocation.html) and [socket transport source](https://gitlab.com/qemu-project/qemu/-/blob/master/net/socket.c) describe the loopback transport/framing and libpcap evidence path used by the harness. The DHCP peer follows the fields/options needed for these direct client exchanges from [RFC 2131](https://datatracker.ietf.org/doc/html/rfc2131) and echoes the client identifier per [RFC 6842](https://www.rfc-editor.org/rfc/rfc6842.html).

MikroTik documents the [DHCP lease-script variables/events](https://help.mikrotik.com/docs/spaces/ROS/pages/24805500/DHCP). ROS7 Netwatch has a default startup delay after boot; the protocol test waited for it without overriding probe defaults, as described in the [Netwatch manual](https://manual.mikrotik.com/docs/diagnostics-monitoring-and-troubleshooting/netwatch/).

## Publication limits and next decision

G03 remains TESTING. G04 remains BLOCKED on ROS6 compatibility/review. Both are private Admin drafts. Exact match remains N/A and complete reference semantic equivalence is unproven. These tests cover synthetic IPv4 UDP, controlled gateways and selected events; not TCP, real Starlink modems, public Internet reachability, all classifiers/WAN counts, all RouterOS versions or production capacity.

Keep the ROS6 path blocked until a version-support decision and fresh authorized reference audit are available. A changed renewal strategy would be an explicit, separately reviewed behavior change, not an automatic alteration of reference parity. The original quote-escaping candidate remains withdrawn.
`;
await fs.writeFile('docs/lab/PACKET-RESULTS.md',text);console.log('Packet/protocol report written; ROS6 failures preserved.');
