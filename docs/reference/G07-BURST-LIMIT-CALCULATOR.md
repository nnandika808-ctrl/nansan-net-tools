# G07 — MikroTik Burst Limit Calculator & Traffic Shaper Tool

Source: https://buananet.com/mikrotik-burst-limit-calculator.html  
Date: 2026-11-03 (Asia/Jakarta)  
Audit Method: Authenticated Playwright MCP Chrome session via loopback bridge (port 8932).  
Status: FORM_OBSERVED; AUDITED; IMPLEMENTED AS ADMIN DRAFT; 5/5 EXACT RATE LIMIT MATCHED.

---

## 1. Overview & Fungsi

Generator dan kalkulator ini menghitung parameter burst rate MikroTik RouterOS secara otomatis untuk diterapkan pada:
1. **/queue simple:** Parameter `max-limit`, `burst-limit`, `burst-threshold`, `burst-time`, `limit-at`, dan `priority`.
2. **/ppp profile:** Atribut `rate-limit` untuk pelanggan PPPoE / L2TP / SSTP.
3. **/ip hotspot user profile:** Atribut `rate-limit` untuk voucher / hotspot MikroTik.
4. **RADIUS / User Manager:** Standar string MikroTik Rate-Limit.

---

## 2. Parameter Input & Default

| Key / Field | Tipe | Default | Placeholder | Keterangan |
|---|---|---|---|---|
| `up-max-limit` | text | 3M | 3M | Kecepatan normal Upload (Max Limit) |
| `up-speed-bonus` | text | 6M | 6M | Kecepatan puncak saat burst Upload |
| `up-interval-bonus` | number | 15 | 15 | Estimasi durasi bonus yang diinginkan (detik) |
| `down-max-limit` | text | 5M | 5M | Kecepatan normal Download (Max Limit) |
| `down-speed-bonus` | text | 8M | 8M | Kecepatan puncak saat burst Download |
| `down-interval-bonus`| number | 15 | 15 | Estimasi durasi bonus yang diinginkan (detik) |
| `client-name` | text | Burst-User | Burst-User | Nama antrean atau profil user |
| `target-ip` | text | 192.168.1.0/24 | 192.168.1.0/24 | Target IP/Subnet untuk Simple Queue |

---

## 3. Rumus Matematis yang Terverifikasi

MikroTik RouterOS menghitung burst berdasarkan *moving average* pemakaian bandwidth:

1. **Burst Threshold (Batas Pemicu):**
   `Burst Threshold = round(Max Limit * 3/4)` (75% dari Max Limit)
2. **Limit At (Garansi Bandwidth Minimum):**
   `Limit At = round(Max Limit * 1/8)` (12.5% dari Max Limit)
3. **Burst Time (Jendela Pengamatan Antrean):**
   `Burst Time = ceil( (Bonus Duration * Burst Limit) / Burst Threshold )`
4. **Durasi Aktual (Actual Burst Duration):**
   `Actual Duration = (Burst Threshold * Burst Time) / Burst Limit`

### Aturan Format Satuan:
- Jika nilai dalam Kbps kelipatan genap 1000K, diformat sebagai `M` (contoh: 75000K -> `75M`, 15000K -> `15M`).
- Jika bukan kelipatan 1000K, tetap menggunakan `K` (contoh: `2250K`, `3750K`, `375K`, `625K`, `37500K`).

---

## 4. Kasus yang Terverifikasi (5/5 Exact Match)

| ID | Upload (Max / Burst / Dur) | Download (Max / Burst / Dur) | Hasil Rate Limit String | Status |
|---|---|---|---|---|
| C01 | 3M / 6M / 15s | 5M / 8M / 15s | `3M/5M 6M/8M 2250K/3750K 40/32 8 375K/625K` | **MATCH** |
| C02 | 10M / 20M / 10s | 20M / 40M / 10s | `10M/20M 20M/40M 7500K/15M 27/27 8 1250K/2500K` | **MATCH** |
| C03 | 1M / 2M / 20s | 2M / 4M / 20s | `1M/2M 2M/4M 750K/1500K 54/54 8 125K/250K` | **MATCH** |
| C04 | 512K / 1M / 15s | 1M / 2M / 15s | `512K/1M 1M/2M 384K/750K 40/40 8 64K/125K` | **MATCH** |
| C05 | 50M / 100M / 8s | 100M / 200M / 8s | `50M/100M 100M/200M 37500K/75M 22/22 8 6250K/12500K` | **MATCH** |

---

## 5. Status Akses & Publikasi

- Status publikasi: **ADMIN DRAFT** (`PUBLISHED = []`).
- Akses preview: `/admin/tools/mikrotik-burst-limit-calculator/preview`.
