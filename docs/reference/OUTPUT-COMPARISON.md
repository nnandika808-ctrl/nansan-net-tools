# Output comparison

| Measure | Result |
|---|---|
| Observed browser scenarios | 16 |
| G03 ordered rule-parameter projections compared | 5 matched / 5 compared |
| G04 ordered rule-parameter and limited DHCP projections | 7 matched / 7 compared |
| Full exact output fixtures with redistribution authorization | 0 |
| Exact match rate | N/A — no eligible full-text cases |
| Complete semantic equivalence cases | 0 |
| Semantic match rate | N/A — complete reference packet effects and full configuration not verified |
| RouterOS lab cases | G03: 5 syntax and 5 UDP/NAT cases passed; G04: 6 simulated-event cases and 3 ROS7 protocol cases passed; 2 ROS6 gateway-renewal cases FAILED |

PccRegressionTest compares independently built rule parameters against browser-observed parameters, preserving section and rule order. StarlinkRegressionTest parses the rendered script and nested DHCP statements using a separate lexical observer. Parameter order is canonicalized; descriptive comments are excluded, while route-selector comments inside DHCP/Netwatch remain functional values. These are **partial semantic projections**, not full semantic equivalence. Scoped virtual packet observations are recorded below. Physical ISP behavior, broader combinations and complete reference equivalence remain UNVERIFIED.

Explicit differences: draft output has NANSAN NET comments, deterministic formatting, quoted values and a DRAFT banner. Text equality with Buananet is neither attempted nor claimed. G03 rejects WAN counts other than 2 and ratios exceeding 256 rules; integer Mbps only. G04 supports 2–10 WAN but the disposable lab only exercises 2–3 WAN; manual ratios remain unreduced as observed (2:2 produces denominator 4). G04 rejects duplicate interfaces, invalid CIDRs and incomplete monitoring targets. Both reject unsafe input rather than constructing executable fragments.

app/Generators/OutputComparison.php supports exact byte/line differences and explicit parameter projections for authorized future fixtures. No normalization is applied for exact comparison. A future mismatch must record actual diff and cause; it must not be hidden by loosening the comparator.

Additional own-script effects: five PCC virtual packet cases passed (2,000 baseline flows); three ROS7 Starlink DHCP protocol cases passed; both ROS6 gateway-only renewal cases failed. These are separate from the five/seven external parameter projections. See ../lab/PACKET-RESULTS.md for evidence and scope. No full semantic/exact rate is inferred.
