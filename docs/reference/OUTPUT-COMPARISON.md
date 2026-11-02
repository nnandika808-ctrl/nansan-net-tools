# Output comparison

| Measure | Result |
|---|---|
| Observed browser scenarios | 16 |
| G03 ordered rule-parameter projections compared | 5 matched / 5 compared |
| Full exact output fixtures with redistribution authorization | 0 |
| Exact match rate | N/A — no eligible full-text cases |
| Complete semantic equivalence cases | 0 |
| Semantic match rate | N/A — packet effects and full configuration not verified |
| RouterOS lab cases | 0 at this report revision |

PccRegressionTest compares independently built rule parameters against browser-observed parameters, preserving section and rule order. Parameter order is canonicalized for comparison; comments are excluded. This is a **partial semantic projection**, not full semantic equivalence. All five cases remain UNVERIFIED for real RouterOS behavior.

Explicit differences: the draft output has NANSAN NET comments, deterministic formatting, quoted parameter values, and a DRAFT/LAB UNVERIFIED banner. Text equality with Buananet is neither attempted nor claimed. G03 currently rejects WAN counts other than 2 and ratios exceeding 256 classifier rules; integer Mbps only. These limitations are documented rather than silently changing the reference.

app/Generators/OutputComparison.php supports exact byte/line differences and explicit parameter projections for authorized future fixtures. No normalization is applied for exact comparison. A future mismatch must record actual diff and cause; it must not be hidden by loosening the comparator.
