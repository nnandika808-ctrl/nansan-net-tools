# Unverified features and blockers

| Scope / source | Scenario | Status / needed evidence |
|---|---|---|
| G01: https://buananet.com/mikrotik-static-routing.html | All untested combinations | UNVERIFIED — exhaustive behavior coverage, validation, exact formatting and RouterOS lab required |
| G02: https://buananet.com/mikrotik-queue-qos.html | All untested combinations | UNVERIFIED — exhaustive behavior coverage, validation, exact formatting and RouterOS lab required |
| G03: https://buananet.com/pcc.html | All untested combinations | UNVERIFIED — exhaustive behavior coverage, validation, exact formatting and RouterOS lab required |
| G04: https://buananet.com/mikrotik-pcc-lb-starlink.html | All untested combinations | UNVERIFIED — exhaustive behavior coverage, validation, exact formatting and RouterOS lab required |
| G05: https://buananet.com/static-routing-games.html | All untested combinations | UNVERIFIED — exhaustive behavior coverage, validation, exact formatting and RouterOS lab required |
| G01/G02 remote datasets | All applications and TLS/content choices | BLOCKED — obtain explicit dataset redistribution rights or independently sourced, reviewed datasets; no hotlink fallback |
| G05 game ports | All 106 observed game entries | BLOCKED — independently verify port/protocol rules and provenance; do not invent ports |
| G03 | More than two WAN, decimal ratios and unexercised classifiers/local modes | UNVERIFIED — expand audit and tests before support claims |
| G04 quoting | Initial escaping candidate | WITHDRAWN — byte-level inspection shows normal outer quotes. Nested script escaping is a separate context; no reference bug established |
| G04 | DHCP renew/unbind and Netwatch | Six disposable CHR cases pass simulated bound/repeated-bound/unbound and route enable/disable events; real DHCP server interactions, reachability and traffic failover remain UNVERIFIED |
| G04 | More than 3 WAN in CHR, custom classifiers/targets, interface changes | UNVERIFIED — ten-WAN parameters were observed, but not exercised in CHR |
| Rights | Full text fixtures and exclusive datasets | Scope of owner-reported Facebook permission is not established |

No blocker is counted as passing. No generator is public until reviewed code, validated cases and authorized lab evidence meet publication criteria.

Confirmed own-draft compatibility blocker: on CHR 6.49.19, DHCP client gateway changes to 192.168.1.254 but LB-STARLINK1 keeps 192.168.1.1%ether1 on gateway-only renewal. See ../lab/STARLINK-PROTOCOL-6.49.19.json. It is not a confirmed Buananet bug; the full reference was not run for that event sequence. No automatic workaround was applied.
