# G06 — Simple Queue and Queue Tree Bandwidth Management

Source: https://buananet.com/simple-queue-or-queue-tree-generator.html  
Date: 2026-11-03 (Asia/Jakarta)  
Audit Method: Authenticated Playwright MCP Chrome session via loopback bridge (port 8932).  
Status: FORM_OBSERVED; AUDITED; IMPLEMENTED AS ADMIN DRAFT; OUTPUT UNVERIFIED ON PHYSICAL ROUTER.

---

## 1. Overview & Architecture

Generator ini menyediakan konfigurasi manajemen bandwidth bertingkat (hierarchical bandwidth management) untuk MikroTik RouterOS dengan dua opsi arsitektur:
1. **Simple Queue:** Menggunakan struktur parent-child `/queue simple` dengan target subnet lokal dan limit dua arah (upload/download).
2. **Queue Tree:** Menggunakan struktur tree `/queue tree` di bawah parent `GLOBAL CONNECTION` (parent=global) yang dibagi menjadi `7.INBOUND PACKET` (upload) dan `8.OUTBOUND PACKET` (download) dengan pemisahan packet mark arah naik (`_up`) dan arah turun (`_down`).

---

## 2. Form Inventory & Defaults

| Key / Field | Tipe | Default | Opsi / Rentang | Keterangan |
|---|---|---|---|---|
| `globalQueueType` | select | Simple Queue | Simple Queue, Queue Tree | Memilih arsitektur queue |
| `category-streaming-video` | checkbox | false | true / false | RAW match: YouTube, TikTok, Netflix, Spotify, Vidio, dll. |
| `category-social-media` | checkbox | false | true / false | RAW match: WhatsApp/Telegram, Facebook, Instagram, Twitter/X, dll. |
| `category-e-commerce` | checkbox | false | true / false | RAW match: Tokopedia, Shopee, Bukalapak, Lazada, Blibli, Amazon, dll. |
| `category-video-conference` | checkbox | false | true / false | RAW match: Zoom, Google Meet, MS Teams, Webex, Skype, Discord, dll. |
| `downloadBandwidthISP` | text | 100M | format: numeric + k/M/G | Total kapasitas download link ISP |
| `uploadBandwidthISP` | text | 50M | format: numeric + k/M/G | Total kapasitas upload link ISP |
| `globalQueueTypeInput` | select | default-small | default, default-small, pcq, sfq, codel (v7), fq-codel (v7), cake (v7) | Jenis algoritma antrean |
| `localTarget` | text | 192.168.0.0/16,10.0.0.0/8,172.16.0.0/12 | Subnet IPv4 valid | Target jaringan lokal |

---

## 3. Perhitungan Matematis Bandwidth (Limit Calculation)

- **ISP Upload Pool:** `uploadBandwidthISP / 2` (Default: 50M / 2 = 25M)
- **ISP Download Pool:** `downloadBandwidthISP / 2` (Default: 100M / 2 = 50M)
- **Leaf Children:** `2 (Fastest Priority + Heavy Traffic) + (jumlah kategori yang dicentang)`
  - 0 kategori: 2 leaf -> limit-at = 12.5M up / 25M down
  - 1 kategori: 3 leaf -> limit-at = 8.33M up / 16.67M down
  - 2 kategori: 4 leaf -> limit-at = 6.25M up / 12.5M down
  - 3 kategori: 5 leaf -> limit-at = 5M up / 10M down
  - 4 kategori: 6 leaf -> limit-at = 4.17M up / 8.33M down

---

## 4. Struktur Script yang Dihasilkan

1. **/ip firewall address-list:**
   - Menambahkan subnet privat ke list `LOCAL-IP` (`10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`).
2. **/ip firewall raw:**
   - Menangkap TLS host kategori yang dipilih ke address-list masing-masing dengan timeout 1 hari.
   - Menangkap server Speedtest ke address-list `Speedtest`.
3. **/ip firewall mangle:**
   - Custom chain jump: `Nansan-Pre`, `Nansan-Pos`, `Nansan-Inp`, `Nansan-Out`.
   - Packet mark: `ping_mark` (ICMP packet-size 0-64, passthrough=no).
   - Packet mark: `dns_mark` (UDP 53, 5353, 30000, 33434-33534, packet-size 0-256).
   - Packet mark: `winbox_mark` (TCP Winbox & tools).
   - Packet mark: `local_bypass_mark` (src & dst di LOCAL-IP).
   - Multi-jump online games (`conn-game-online` dan `game_mark`).
   - Speedtest connection & packet mark (`speedtest_mark`).
   - Category connection & packet marks:
     - Simple Queue: mark tunggal (`streaming_video_mark`, dll.).
     - Queue Tree: mark terpisah arah (`streaming_video_mark_up` dan `streaming_video_mark_down`).
   - Fastest Priority: koneksi berkecepatan rendah (connection-rate=0-1M, packet-size 0-1024 up / 0-2048 down).
   - Heavy Traffic: penampung trafik sisa.
   - Return rules untuk seluruh custom chain.
4. **/queue type:**
   - Dibuat otomatis jika memilih queue type modern v7 (`cake`, `codel`, `fq-codel`).
5. **/queue simple ATAU /queue tree:**
   - Simple Queue: 1–6 antrean prioritas tinggi (Bypass, Ping, DNS, Winbox, Speedtest, Games) + parent `7. GLOBAL CONNECTION` beserta anak-anaknya.
   - Queue Tree: Parent `GLOBAL CONNECTION` (global) + bypass, ping, dns, winbox, speedtest, games + `7.INBOUND PACKET` + `8.OUTBOUND PACKET`.

---

## 5. Kompatibilitas RouterOS & Batasan

- Pilihan queue type `default`, `default-small`, `pcq`, dan `sfq` kompatibel dengan RouterOS v6.xx dan v7.xx.
- Pilihan `cake`, `codel`, dan `fq-codel` hanya didukung pada RouterOS v7.xx.
- Status publikasi: **TERKUNCI** (`PUBLISHED = []`). Generator ini hanya dapat diakses melalui preview internal Admin di `/admin/tools/simple-queue-or-queue-tree-generator/preview`.
