# Static Routing import and syntax evidence - 2026-11-03

Both final runs used snapshot-only CHR, restricted user-mode networking and a loopback-only SSH management forward. Commands ran through noninteractive SSH after console bootstrap. The earlier interrupted serial-transport attempt is not counted as a source failure. The main cases use datasets safely rendered from parsed parameter snapshots. Four additional original public Teams/Telegram source RSCs were separately imported on CHR 7.25 after source-hash and allowlist checks; all four reproduced the duplicate-entry failure. Raw files remain ignored. See STATIC-ROUTING-SOURCE-DUPLICATES-7.25.json; those negative reference tests are separate from the main case total.

Both images exercised all 92 available application/matcher datasets plus a YouTube TLS empty-mangle case: 186 cases, 182 passed and 4 failed. The final ROS6 run awaited SFTP file visibility in the RouterOS inventory before import, and successful main cases on both images verified file cleanup; this avoids mistaking asynchronous SFTP inventory updates for a generator bug. Failures remain failures. Existing sentinel cases recorded internal rule ID *1; the literal reference place-before=*0 also passed with an empty table. An assumed requirement for an existing *0 rule was not established. Owned VMs stopped and snapshot overlays were discarded.

Shopify TLS/CONTENT source URLs returned HTTP 404; all four app/ROS/matcher combinations stay unavailable and are not counted as passing. Teams and Telegram fail ROS7 import in both modes on repeated address-list entries. An owner decision is pending before any corrective rewrite, as required by the original prompt; see ../reference/G01-DUPLICATE-DECISION.md. No automatic substitute or duplicate-removal rewrite was introduced. Parameter snapshots retain observed duplicate commands; successful syntax/import does not prove safety when combined with an existing configuration.

| Application | Matcher | CHR 6.49.19 | CHR 7.25 | Dataset commands |
|---|---|---|---|---:|
| Alibaba | content | PASSED | PASSED | 16 |
| Alibaba | tls-host | PASSED | PASSED | 16 |
| Amazon | content | PASSED | PASSED | 10 |
| Amazon | tls-host | PASSED | PASSED | 10 |
| Bank-Indonesia | content | PASSED | PASSED | 217 |
| Bank-Indonesia | tls-host | PASSED | PASSED | 217 |
| Blibli | content | PASSED | PASSED | 13 |
| Blibli | tls-host | PASSED | PASSED | 13 |
| Bukalapak | content | PASSED | PASSED | 15 |
| Bukalapak | tls-host | PASSED | PASSED | 15 |
| Discord | content | PASSED | PASSED | 13 |
| Discord | tls-host | PASSED | PASSED | 13 |
| Etsy | content | PASSED | PASSED | 7 |
| Etsy | tls-host | PASSED | PASSED | 7 |
| Facebook | content | PASSED | PASSED | 29 |
| Facebook | tls-host | PASSED | PASSED | 29 |
| GGC | content | PASSED | PASSED | 86 |
| GGC | tls-host | PASSED | PASSED | 86 |
| Google-Meet | content | PASSED | PASSED | 7 |
| Google-Meet | tls-host | PASSED | PASSED | 7 |
| Google | content | PASSED | PASSED | 152 |
| Google | tls-host | PASSED | PASSED | 152 |
| Iflix | content | PASSED | PASSED | 9 |
| Iflix | tls-host | PASSED | PASSED | 9 |
| Imo | content | PASSED | PASSED | 17 |
| Imo | tls-host | PASSED | PASSED | 17 |
| Instagram | content | PASSED | PASSED | 14 |
| Instagram | tls-host | PASSED | PASSED | 15 |
| Jitsi | content | PASSED | PASSED | 4 |
| Jitsi | tls-host | PASSED | PASSED | 4 |
| Lazada | content | PASSED | PASSED | 9 |
| Lazada | tls-host | PASSED | PASSED | 9 |
| Linkedin | content | PASSED | PASSED | 10 |
| Linkedin | tls-host | PASSED | PASSED | 10 |
| Meta | content | PASSED | PASSED | 23 |
| Meta | tls-host | PASSED | PASSED | 23 |
| Netflix | content | PASSED | PASSED | 215 |
| Netflix | tls-host | PASSED | PASSED | 215 |
| NICE-OIXP | content | PASSED | PASSED | 8306 |
| NICE-OIXP | tls-host | PASSED | PASSED | 8306 |
| Olx | content | PASSED | PASSED | 15 |
| Olx | tls-host | PASSED | PASSED | 15 |
| Pinterest | content | PASSED | PASSED | 13 |
| Pinterest | tls-host | PASSED | PASSED | 13 |
| Rakuten | content | PASSED | PASSED | 46 |
| Rakuten | tls-host | PASSED | PASSED | 46 |
| Ralali | content | PASSED | PASSED | 5 |
| Ralali | tls-host | PASSED | PASSED | 5 |
| Shopee | content | PASSED | PASSED | 10 |
| Shopee | tls-host | PASSED | PASSED | 10 |
| Skype | content | PASSED | PASSED | 5 |
| Skype | tls-host | PASSED | PASSED | 5 |
| Snapchat | content | PASSED | PASSED | 11 |
| Snapchat | tls-host | PASSED | PASSED | 11 |
| Speedtest | content | PASSED | PASSED | 8389 |
| Speedtest | tls-host | PASSED | PASSED | 8389 |
| Spotify | content | PASSED | PASSED | 6 |
| Spotify | tls-host | PASSED | PASSED | 6 |
| Teams | content | PASSED | FAILED | 33 |
| Teams | tls-host | PASSED | FAILED | 33 |
| Telegram | content | PASSED | FAILED | 15 |
| Telegram | tls-host | PASSED | FAILED | 15 |
| Temu | content | PASSED | PASSED | 12 |
| Temu | tls-host | PASSED | PASSED | 12 |
| Threads | content | PASSED | PASSED | 25 |
| Threads | tls-host | PASSED | PASSED | 25 |
| Tiktok | content | PASSED | PASSED | 38 |
| Tiktok | tls-host | PASSED | PASSED | 38 |
| Tokopedia | content | PASSED | PASSED | 14 |
| Tokopedia | tls-host | PASSED | PASSED | 14 |
| Twitch | content | PASSED | PASSED | 27 |
| Twitch | tls-host | PASSED | PASSED | 27 |
| Twitter-X | content | PASSED | PASSED | 95 |
| Twitter-X | tls-host | PASSED | PASSED | 95 |
| Vidio | content | PASSED | PASSED | 13 |
| Vidio | tls-host | PASSED | PASSED | 13 |
| Vimeo | content | PASSED | PASSED | 13 |
| Vimeo | tls-host | PASSED | PASSED | 13 |
| Webex | content | PASSED | PASSED | 29 |
| Webex | tls-host | PASSED | PASSED | 29 |
| Wetv | content | PASSED | PASSED | 13 |
| Wetv | tls-host | PASSED | PASSED | 13 |
| WhatsApp | content | PASSED | PASSED | 6 |
| WhatsApp | tls-host | PASSED | PASSED | 7 |
| Whereby | content | PASSED | PASSED | 4 |
| Whereby | tls-host | PASSED | PASSED | 4 |
| Wish | content | PASSED | PASSED | 12 |
| Wish | tls-host | PASSED | PASSED | 12 |
| Youtube | content | PASSED | PASSED | 20 |
| Youtube | tls-host | PASSED | PASSED | 20 |
| Zoom | content | PASSED | PASSED | 103 |
| Zoom | tls-host | PASSED | PASSED | 103 |

Evidence: STATIC-ROUTING-6.49.19.json and STATIC-ROUTING-7.25.json record image/dataset/script hashes and individual outcomes. The manifest accepts pass/fail lab flags only when compiled artifact hashes match those reports. An additional strict file-cleanup smoke, if present, is recorded separately as STATIC-ROUTING-SMOKE-6.49.19.json and is not added to the 186-case total.

These checks do not execute STEP 1 HTTPS fetch on CHR, establish prefix freshness or exclusive application ownership, observe real app traffic/TLS/QUIC, prove ISP routing/capacity or guarantee repeated-import safety. The staging browser separately downloads a self-hosted artifact and checks its SHA-256. G01 remains an unpublished Admin draft; production artifacts stay closed until reviewed publication.

MikroTik documents the [RAW matcher semantics and TLS SNI fragmentation limitation](https://manual.mikrotik.com/docs/cli-reference/ip/firewall/raw/). Console handling follows the vendor's [console documentation](https://manual.mikrotik.com/docs/management-tools/console/); lab automation is not a firmware or generator behavior change.
