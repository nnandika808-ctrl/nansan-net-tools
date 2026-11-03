# Hosting NANSAN NET Tools di aaPanel

Panduan ini memasang fondasi website. Generator yang belum disahkan tetap terkunci; kelulusan hosting bukan kelulusan dataset atau RouterOS. Tidak ada password default, pemasangan router otomatis, atau langganan berbayar.

## Paket yang dipakai

Gunakan commit/branch yang tercantum pada PR terbaru, bukan ZIP source `main` yang mungkin lebih lama. Pada GitHub → **Actions → Checks → run yang sukses → Artifacts**, unduh **nansan-hosting-[commit]**. Artifact bertahan 14 hari dan berisi ZIP aplikasi, checksum SHA256, `build.json`, dan `smoke.json`. Jika artifact kedaluwarsa, jalankan ulang workflow pada commit yang sama.

ZIP aplikasi sudah memuat `vendor` produksi dan frontend hasil build. Tidak perlu menjalankan Node di hosting. Ekstrak terlebih dahulu pembungkus artifact GitHub, lalu unggah ZIP `nansan-net-tools-*.zip` dan berkas checksum-nya. Verifikasi checksum sebelum mengekstrak ZIP aplikasi. Simpan ZIP di luar document root.

Di dalam ZIP terdapat `release.json` dengan commit dan SHA256 setiap file. CI menguji instalasi dengan benar-benar mengekstrak ZIP, menjalankan migrasi baru, seeding dua kali, optimasi Laravel, HTTP smoke, aset dan penolakan jalur privat. Smoke memakai SQLite terisolasi; konfigurasi MySQL/Redis server tetap harus diterima pada server tujuan. ZIP tidak memuat `.env`, database, upload, log, browser state atau dependency development.

Alternatif source Git membutuhkan Composer 2, Node 22.12+ dan `npm ci && npm run build`. Gunakan `composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader`, bukan `composer update`. Jangan mengunggah `.env` dari komputer pengembangan.

## Kebutuhan server

- PHP **8.4+**, versi CLI dan PHP website harus sama. Ekstensi: ctype, curl, DOM/XML, fileinfo, filter, GD, intl, mbstring, OpenSSL, PDO/pdo_mysql, session, sodium, tokenizer, zip. CLI worker memerlukan pcntl dan fungsi proses; backup memerlukan `proc_open`/`proc_close`.
- Apache **atau** Nginx yang sudah digunakan aaPanel. Apache memerlukan `mod_rewrite` dan izin membaca `public/.htaccess`. Tidak perlu mengganti Apache aktif menjadi Nginx.
- MySQL/MariaDB dengan database dan akun khusus aplikasi. Akun aplikasi hanya mendapat akses ke database miliknya.
- Redis privat pada loopback, Composer 2 untuk pemeriksaan platform, klien `mysqldump`/`mariadb-dump` dan `mysql`/`mariadb` untuk backup/restore.
- Supervisor atau pengelola proses untuk worker, serta cron setiap menit untuk scheduler.
- Domain dan sertifikat HTTPS yang valid, kunci Turnstile untuk hostname itu, backup privat, serta salinan backup dan kunci terpisah di luar server.

Audit server pemilik 2026-11-04 menemukan PHP 8.4.27, Apache, MariaDB 10.11.10, Composer, RAM 2 GB dan sekitar 11 GB kosong. CLI belum memuat fileinfo; Redis tidak ditemukan pada lokasi global standar. Ini bukan bukti server siap produksi atau benchmark kapasitas. Jangan melewati ownership guard staging lama: `nansan-tools-staging` kehilangan marker/current dan akses lamanya menghasilkan 502.

## Instalasi pertama

Langkah berikut dilakukan hanya setelah pemilik menyetujui deployment, pada direktori/site/database khusus yang baru.

1. aaPanel → Website/PHP Project → buat site untuk domain yang disetujui, pilih PHP 8.4 dan database khusus. Contoh project root `/www/wwwroot/nansan-tools`; **running directory/document root wajib `/public`**. Jangan arahkan web root ke root project.
2. Verifikasi checksum, ekstrak ZIP aplikasi ke project root. Pastikan `artisan`, `vendor`, `resources`, dan `public` berada sejajar, tanpa folder tambahan yang tidak sengaja bersarang.
3. Aktifkan ekstensi yang kurang untuk PHP site **dan CLI**. Pastikan kebijakan `open_basedir` site mengizinkan root project dan runtime yang diperlukan; jangan menonaktifkan pembatasan global. Pilih PHP CLI 8.4 di panel, atau gunakan `/www/server/php/84/bin/php` secara eksplisit.
4. Salin `.env.example` menjadi `.env` pada server. Isi domain HTTPS, database, Redis, Turnstile dan backup melalui editor/secret manager. `APP_ENV=production`, `APP_DEBUG=false`, cookie aman dan enkripsi sesi wajib aktif. Pertahankan namespace Redis/cache/cookie khusus site; gunakan namespace berbeda untuk staging. Jangan kirim `.env` atau kunci ke GitHub.
5. Atur pemilik/group sesuai user PHP site. Hanya `storage` dan `bootstrap/cache` perlu dapat ditulis oleh aplikasi. Lindungi `.env` (contoh mode 640 dengan group PHP yang benar); jangan menggunakan 777 atau membuat seluruh source writable oleh web worker.
6. Dari root project, gunakan PHP yang benar:

```sh
composer check-platform-reqs --no-dev
/www/server/php/84/bin/php artisan key:generate --force
/www/server/php/84/bin/php artisan migrate --force
/www/server/php/84/bin/php artisan db:seed --force
/www/server/php/84/bin/php artisan nansan:admin nama_admin_anda
/www/server/php/84/bin/php artisan optimize
```

Jalankan `key:generate` **hanya pada instalasi baru tanpa APP_KEY**. Pertahankan APP_KEY saat update karena sesi/data terenkripsi bergantung padanya. Admin dibuat dari prompt password tersembunyi; registrasi publik tidak menerima role. Simpan recovery codes dari halaman Akun.

7. Aktifkan HTTPS untuk domain. Untuk Apache gunakan `public/.htaccess` yang disertakan. Untuk Nginx tersedia contoh `deployment/hosting/nginx-locations.conf.example`; gabungkan ke vhost site, sesuaikan socket PHP sebenarnya dan hilangkan aturan PHP generik yang bertabrakan. Jalankan config test sebelum reload site. Jangan menyalin contoh sebagai konfigurasi global atau mengubah website lain. Request body limit minimal 6 MB; set PHP `upload_max_filesize=5M`, `post_max_size=8M` untuk batas upload aplikasi 5 MB.
8. Jika ada reverse proxy, kirim Host/proto/port yang benar. Aplikasi hanya mempercayai proxy loopback. Jangan mengubah trusted proxy menjadi `*`; proxy lain membutuhkan konfigurasi yang ditinjau sesuai topologi sebenarnya.

## Worker dan scheduler

Gunakan template `deployment/hosting/worker.conf.example`; ganti user, root dan binary PHP sesuai site. Worker memakai timeout **610 detik**, sementara `REDIS_QUEUE_RETRY_AFTER=900` dan `DB_QUEUE_RETRY_AFTER=900`, sehingga pekerjaan backup 600 detik tidak muncul ulang saat masih berjalan. Supervisor harus menunggu penghentian worker setidaknya 920 detik dan menjalankannya sebagai user aplikasi, bukan root.

Tambahkan cron setiap menit melalui aaPanel, dijalankan sebagai user site:

```cron
* * * * * cd /www/wwwroot/nansan-tools && /www/server/php/84/bin/php artisan schedule:run >> /dev/null 2>&1
```

Jangan menjalankan scheduler ganda dari cron dan timer systemd sekaligus. Rotasi log worker/Laravel diperlukan; `.env.example` memakai Laravel daily log 14 hari. Backup dijadwalkan pukul 01:00 pada timezone aplikasi Asia/Jakarta saat disk backup dikonfigurasi.

## Backup dan penerimaan

Set `BACKUP_DISK=backups`, `BACKUP_ROOT` ke direktori privat yang sudah dibuat di luar `public`, serta `BACKUP_ENCRYPTION_KEY` base64 32 byte yang independen dari APP_KEY. Buat/simpan kunci melalui secret manager; jangan memasukkannya ke command line atau riwayat shell. Atur `MYSQLDUMP_PATH`/`MYSQL_CLIENT_PATH` sesuai binary server. Simpan salinan backup dan key escrow di lokasi terpisah di luar server. Backup satu host saja tidak cukup untuk pemulihan kehilangan server.

Untuk uji restore gunakan akun terpisah dengan hak hanya pada database scratch bernama `nansan_restore_%`, bukan database aplikasi. Isi `RESTORE_MYSQL_*`; jangan menggunakan akun aplikasi atau root. Uji berikut tidak menimpa database hidup:

```sh
php artisan nansan:backup
php artisan nansan:backup --verify-id=ID_DARI_OUTPUT_SEBELUMNYA
php artisan nansan:doctor --queue
php artisan nansan:hosting-check --production --runtime
```

Jalankan pemeriksaan penerimaan sebagai user PHP/worker site yang tidak memiliki hak root, misalnya `sudo -u www /www/server/php/84/bin/php artisan nansan:hosting-check --production --runtime` dari root project. Gunakan binary PHP 8.4 eksplisit jika `php` di PATH berbeda. Tunggu scheduler dan worker memproses heartbeat. `hosting-check` menghasilkan JSON tanpa mencetak secret dan exit code nonzero jika prasyarat gagal. Ia memeriksa aset, ekstensi, konfigurasi, migrasi, koneksi, admin awal, heartbeat dan backup restore terverifikasi dalam 24 jam. Perintah ini tidak memverifikasi sertifikat dari luar, validitas kunci Turnstile ke Cloudflare, grant database scratch, off-host recovery, kapasitas trafik, legal review, atau perilaku router. `doctor --queue` membuktikan worker menangani probe unik; queue `sync` tidak dihitung sebagai bukti worker.

Terima site melalui browser HTTPS nyata: homepage/aset, login Admin, logout, registrasi dengan Turnstile nyata, recovery, upload, ownership akun, dashboard, preview Admin, dan gate generator publik. Request `/.env`, `/composer.json`, `/database/database.sqlite`, `/storage/logs/laravel.log` harus 403/404 tanpa membocorkan isi. Jangan membagikan screenshot berisi password atau recovery codes. Catat hasil, waktu, domain dan commit; jangan menandai produksi siap hanya dari metadata atau `/up`.

## Update dan rollback

Buat backup terenkripsi baru **dan uji scratch restore** sebelum migrasi. Siapkan release baru di folder terpisah, pertahankan `.env`, APP_KEY, storage privat dan database. Jalankan pemeriksaan platform, migrasi kompatibel dan optimasi pada release yang benar, kemudian alihkan vhost/symlink yang memang milik site dan restart worker/FPM site. Jangan menjalankan ulang seed yang mengubah konten tanpa meninjau perubahan; seed awal memakai `firstOrCreate` untuk menjaga data yang sudah ada.

Jika penerimaan gagal, kembalikan kode ke release sebelumnya yang kompatibel dengan schema saat ini dan restart service site. Jangan otomatis melakukan `migrate:rollback` atau restore menimpa database hidup. Operasi pemulihan database memerlukan keputusan pemilik dan rencana tersendiri.

Sumber utama: [deployment Laravel](https://laravel.com/docs/13.x/deployment), [timeout/retry queue Laravel](https://laravel.com/docs/13.x/queues#job-expirations-and-timeouts), [PHP Project aaPanel](https://www.aapanel.com/docs/Function/php.html), [artifact GitHub](https://docs.github.com/en/actions/how-tos/manage-workflow-runs/download-workflow-artifacts).
