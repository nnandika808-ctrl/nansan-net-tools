# Unverified features and blockers

| Scope / source | Scenario | Status / needed evidence |
|---|---|---|
| G01: https://buananet.com/mikrotik-static-routing.html | All untested combinations | UNVERIFIED — exhaustive behavior coverage, validation, exact formatting and RouterOS lab required |
| G02: https://buananet.com/mikrotik-queue-qos.html | All untested combinations | UNVERIFIED — exhaustive behavior coverage, validation, exact formatting and RouterOS lab required |
| G03: https://buananet.com/pcc.html | All untested combinations | UNVERIFIED — exhaustive behavior coverage, validation, exact formatting and RouterOS lab required |
| G04: https://buananet.com/mikrotik-pcc-lb-starlink.html | All untested combinations | UNVERIFIED — exhaustive behavior coverage, validation, exact formatting and RouterOS lab required |
| G05: https://buananet.com/static-routing-games.html | All untested combinations | UNVERIFIED — exhaustive behavior coverage, validation, exact formatting and RouterOS lab required |
| G01 datasets | All 47 applications and two match modes | 92 parameter snapshots for 46 apps; Shopify sources HTTP 404. Prefix freshness, complete application coverage and public release review remain open; no hotlink fallback. |
| G02 remote datasets | All choices | Uses G01 hash-matched snapshots; Shopify 404 and ROS7 Teams/Telegram duplicate failures retained. Real shaping and classifier accuracy unverified |
| G05 game ports | All 106 observed game entries | Source-attributed snapshots retained; two malformed entries blocked. Port freshness, real game traffic and publication review remain open; no invented ports |
| G03 | More than two WAN, decimal ratios and unexercised classifiers/local modes | UNVERIFIED — expand audit and tests before support claims |
| G04 quoting | Initial escaping candidate | WITHDRAWN — byte-level inspection shows normal outer quotes. Nested script escaping is a separate context; no reference bug established |
| G04 | DHCP renew/unbind and Netwatch | Six simulated-event cases passed. Three ROS7 synthetic-DHCP/UDP/NAT/renew/unbind protocol cases passed; two ROS6 gateway-only renewal cases FAILED. Physical ISP behavior and broader combinations remain UNVERIFIED |
| G04 | More than 3 WAN in CHR, custom classifiers/targets, interface changes | UNVERIFIED — ten-WAN parameters were observed, but not exercised in CHR |
| Rights | Full text fixtures and exclusive datasets | Scope of owner-reported Facebook permission is not established |

No blocker is counted as passing. No generator is public until reviewed code, validated cases and authorized lab evidence meet publication criteria.

Confirmed own-draft compatibility blocker: on CHR 6.49.19, DHCP client gateway changes to 192.168.1.254 but LB-STARLINK1 keeps 192.168.1.1%ether1 on gateway-only renewal. See ../lab/STARLINK-PROTOCOL-6.49.19.json. A subsequent owner-supplied full ROS6 sample reproduced the same scoped behavior from zero DHCP clients; initial import and 400-flow forwarding passed for each script. See ../lab/OWNER-SUPPLIED-STARLINK-6.49.19.json. This is a shared compatibility limitation in the tested scenario, not evidence of a draft/reference mismatch. No automatic workaround was applied.

G01 confirmed ROS7 dataset issue: Teams and Telegram contain repeated address/list additions. Both matchers fail import on CHR 7.25; four original public source RSC tests reproduce it. ROS6 final strict cases pass. Owner decision on 2026-11-03: preserve original entries and mark failed cases; do not deduplicate. See G01-DUPLICATE-DECISION.md. Shopify source TLS/CONTENT URLs are separately HTTP 404.
