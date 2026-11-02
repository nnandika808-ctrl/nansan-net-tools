# Generator inventory

Observed 2026-11-03 (Asia/Jakarta), through an owner-authenticated Playwright MCP session. Only these five generators are in scope.

| ID | Name | Source | Inputs at default | Scenarios | Status |
|---|---|---|---:|---:|---|
| G01 | Static Routing | https://buananet.com/mikrotik-static-routing.html | 5 | 3 | BLOCKED (output/dataset/lab verification pending) |
| G02 | Queue QoS | https://buananet.com/mikrotik-queue-qos.html | 6 | 3 | BLOCKED (output/dataset/lab verification pending) |
| G03 | PCC Load Balancing | https://buananet.com/pcc.html | 15 | 5 | TESTING (five rule-parameter projections matched; lab pending) |
| G04 | PCC Load Balancing Starlink | https://buananet.com/mikrotik-pcc-lb-starlink.html | 11 | 3 | BLOCKED (output/dataset/lab verification pending) |
| G05 | Static Routing Games | https://buananet.com/static-routing-games.html | 109 | 2 | BLOCKED (output/dataset/lab verification pending) |

The G05 inventory contains 106 visible game labels. This is not a verified port dataset. G03 allows an unbounded WAN count in the observed form (minimum 2); this draft implementation intentionally accepts only the two-WAN cases audited in depth. No other counts are claimed to match.
