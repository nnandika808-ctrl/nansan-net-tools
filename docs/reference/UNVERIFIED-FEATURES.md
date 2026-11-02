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
| G04 DHCP script | Initial ROS6 output | UNVERIFIED — output visibly contains escaped quote characters; confirm byte representation and lab parsing before diagnosing or changing behavior |
| G04 | DHCP renew/unbind, duplicate gateway, interface changes, Netwatch | UNVERIFIED — controlled lab events and packet flow required; never invent static gateways |
| Rights | Full text fixtures and exclusive datasets | Scope of owner-reported Facebook permission is not established |

No blocker is counted as passing. No generator is public until reviewed code, validated cases and authorized lab evidence meet publication criteria.
