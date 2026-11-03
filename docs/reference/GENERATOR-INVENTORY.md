# Generator inventory

Observed 2026-11-03 (Asia/Jakarta), through an owner-authenticated Playwright MCP session. Only these five generators are in scope.

| ID | Name | Source | Inputs at default | Scenarios | Status |
|---|---|---|---:|---:|---|
| G01 | Static Routing | https://buananet.com/mikrotik-static-routing.html | 5 | 188 | TESTING; 184 wrapper projections matched; 182/186 main lab cases pass; Shopify 404; ROS7 Teams/Telegram duplicate import failures |
| G02 | Queue QoS | https://buananet.com/mikrotik-queue-qos.html | 6 | 214 | Admin draft / TESTING; 214 observed, 210 usable projections |
| G03 | PCC Load Balancing | https://buananet.com/pcc.html | 15 | 5 | TESTING; five parameter and virtual UDP/NAT cases passed |
| G04 | PCC Load Balancing Starlink | https://buananet.com/mikrotik-pcc-lb-starlink.html | 11 | 7 | BLOCKED on scoped ROS6 renewal limitation; three ROS7 protocol cases passed |
| G05 | Static Routing Games | https://buananet.com/static-routing-games.html | 109 | 226 | Admin draft / TESTING; 226 observed, 220 usable projections; two malformed games blocked |
| G06 | Bandwidth Management (Simple Queue & Queue Tree) | https://buananet.com/simple-queue-or-queue-tree-generator.html | 9 | 12 | Admin draft / AUDITED; 12 observed scenarios; hierarchical math verified; publication locked |
| G07 | Burst Limit Calculator | https://buananet.com/mikrotik-burst-limit-calculator.html | 8 | 5 | Admin draft / AUDITED; 5/5 exact rate-limit cases matched; math verified; publication locked |

The G05 inventory contains 106 visible game labels. This is not a verified port dataset. G03 allows an unbounded WAN count in the observed form (minimum 2); this draft implementation intentionally accepts only the two-WAN cases audited in depth. No other counts are claimed to match.
