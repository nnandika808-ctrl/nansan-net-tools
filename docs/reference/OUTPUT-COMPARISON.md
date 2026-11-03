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

G01 expanded audit: 47 app choices x 2 ROS versions x 2 matchers = 188 observed wrapper cases. 184 available cases match ordered route/table/LOCAL-IP/mangle add parameters; Shopify sources return 404, so four cases stay blocked. All 92 safely rendered dataset command-parameter sequences round-trip to their parsed source snapshots, preserving order and duplicates. Main RouterOS imports/STEP 2 checks: 182/186 pass, four ROS7 Teams/Telegram imports fail on duplicate entries. Four original source RSC tests independently reproduce those failures. Fetch URLs/files and ownership comments explicitly differ for NANSAN hosting. No exact/full-semantic rate is inferred.

G02: 214 visible-output observations cover all 47 apps, Simple/Tree, TLS/CONTENT, priorities 1-8 and representative zero/k/M/G/numeric/fractional rates. 210 available ordered queue/mangle/LOCAL-IP projections match. Four Shopify combinations are blocked because the shared data sources are unavailable. Source dataset URL identities match G01 snapshots. NANSAN filenames/origin/comments explicitly differ. Queue names reject executable characters; rates are capped at 1 Tbps.

G05: 226 observations cover 106 games on both ROS versions, no-games, all-games, all 104 well-formed games, mixed choices, interface/scoped gateways and selected games hidden by search. 220 available ordered add-parameter projections match; six observations select malformed source games and are blocked. League of Legends PC and Hearthstone PC have an unbalanced quote in the observed UDP line. Snapshots retain the malformed literal; no replacement port/protocol is invented. Full exact and packet semantic equivalence remain unverified.
