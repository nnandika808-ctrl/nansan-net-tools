# Decision required: exact duplicate address-list entries

The original master prompt requires an owner decision before changing a reference bug. No corrective dataset rewrite has been applied.

The final CHR 7.25 run imported the NANSAN rendering of all 92 parameter snapshots. Teams and Telegram failed in both match modes with `Script Error: failure: already have such entry` during address-list import. Four additional tests imported the hash-checked original public source RSC files on CHR 7.25 and reproduced the same duplicate-entry failures. See ../lab/STATIC-ROUTING-SOURCE-DUPLICATES-7.25.json. Their hashes are retained in the manifest. The generator website JavaScript was not inspected. These observations do not claim a defect on every RouterOS version or every installation.

| Application | Exact repeated prefix | Scope |
|---|---|---|
| Teams | 13.107.64.0/18 | Both TLS-HOST and CONTENT, list Mikrotik-Teams |
| Teams | 52.112.0.0/14 | Both TLS-HOST and CONTENT, list Mikrotik-Teams |
| Telegram | 91.108.4.0/22 | Both TLS-HOST and CONTENT, list Mikrotik-Telegram |

Proposed correction: preserve the original parameter snapshots and provenance, but have the artifact renderer emit only the first occurrence of an identical address/list pair for Teams and Telegram. This removes two repeated additions per Teams artifact and one per Telegram artifact. It changes four artifact hashes; it does not change the selected IP set, classifiers, routing mark, routing table, gateway or RAW matching rules. Netflix's repeated RAW rule would remain unchanged because it is not an address-list duplicate.

After approval, assign a new generator draft version, regenerate the four artifact indexes, and test corrected imports on CHR 6.49.19 and 7.25 before updating staging. Keep original failing evidence as historical evidence. No production promotion follows automatically.

Alternative: preserve the output parameters including duplicates, retain the four known ROS7 import failures in the evidence, and leave those app/matcher/version cases unsuitable for operational use. Other applications can continue through Admin preview. Shopify remains separately unavailable because its two source URLs return HTTP 404.
