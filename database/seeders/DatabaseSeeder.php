<?php

namespace Database\Seeders;

use App\Models\CmsPage;
use App\Models\DocumentationArticle;
use App\Models\GeneratorVersion;
use App\Models\Tool;
use App\Models\ToolCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $routing = ToolCategory::firstOrCreate(['slug' => 'routing'], ['name' => 'Routing', 'description' => 'Perencanaan jalur trafik aplikasi dan game.']);
        $qos = ToolCategory::firstOrCreate(['slug' => 'queue-qos'], ['name' => 'Queue & QoS', 'description' => 'Manajemen prioritas dan bandwidth.']);
        $balancing = ToolCategory::firstOrCreate(['slug' => 'load-balancing'], ['name' => 'Load Balancing', 'description' => 'Pembagian koneksi antar-WAN.']);
        $definitions = [
            ['G01', 'static-routing', 'Static Routing', 'Perencanaan routing trafik aplikasi melalui gateway pilihan.', $routing->id, 'route', 'Dataset independen dan pengujian RouterOS belum tersedia.', ['Routing', 'Application Routing']],
            ['G02', 'queue-qos', 'Queue QoS', 'Pengaturan prioritas trafik melalui Simple Queue atau Queue Tree.', $qos->id, 'queue', 'Dataset independen dan regresi output belum selesai.', ['QoS', 'Queue', 'Priority']],
            ['G03', 'pcc', 'PCC Load Balancing', 'Pembagian koneksi beberapa WAN dengan opsi rasio bandwidth.', $balancing->id, 'pcc', 'Regresi output dan pengujian lab RouterOS belum selesai.', ['PCC', 'Load Balance', 'Load Balancing']],
            ['G04', 'pcc-starlink', 'PCC Load Balancing Starlink', 'Perencanaan load balancing Starlink dengan gateway DHCP dinamis.', $balancing->id, 'starlink', 'Perilaku DHCP, Netwatch, dan pengujian lab belum terverifikasi.', ['Starlink PCC', 'Starlink', 'DHCP']],
            ['G05', 'static-routing-games', 'Static Routing Games', 'Pemilihan routing trafik game berdasarkan aturan yang terverifikasi.', $routing->id, 'games', 'Sumber port independen dan kompatibilitas RouterOS belum terverifikasi.', ['Routing Game', 'Game Routing', 'Games']],
                                    ['G06', 'simple-queue-or-queue-tree-generator', 'Bandwidth Management (Simple Queue & Queue Tree)', 'Manajemen bandwidth hierarkis Simple Queue atau Queue Tree dengan klasifikasi RAW dan Mangle.', $qos->id, 'queue', 'Form diaudit dari referensi; regresi parameter dan lab RouterOS sedang diverifikasi.', ['Bandwidth Management', 'Simple Queue Tree', 'Queue Tree', 'Manajemen Bandwidth']],
            ['G07', 'mikrotik-burst-limit-calculator', 'Burst Limit Calculator', 'Kalkulator burst limit MikroTik untuk Simple Queue, Queue Tree, Hotspot, dan PPPoE.', $qos->id, 'calculator', 'Form diaudit dari referensi; perhitungan burst time dan threshold terverifikasi.', ['Burst Limit', 'Burst Calculator', 'Kalkulator Burst', 'Rate Limit']],
        ];
        foreach ($definitions as $i => [$id,$slug,$name,$description,$category,$icon,$reason,$synonyms]) {
            $tool = Tool::firstOrCreate(['generator_id' => $id], ['slug' => $slug, 'name' => $name, 'description' => $description, 'tool_category_id' => $category, 'icon' => $icon, 'status' => 'BLOCKED', 'blocked_reason' => $reason, 'ros_versions' => ['6', '7'], 'featured' => true, 'sort_order' => $i]);
            foreach ($synonyms as $synonym) {
                $tool->synonyms()->firstOrCreate(['synonym' => $synonym]);
            }
            $version = GeneratorVersion::firstOrCreate(['tool_id' => $tool->id, 'version' => '0.1.0-audit'], ['status' => 'BLOCKED', 'changelog' => 'Form diaudit pada 2026-11-03; output dan lab belum terverifikasi.', 'evidence' => ['form' => 'docs/reference/evidence/'.$id.'-form.json', 'behavior' => 'docs/reference/evidence/behavior.json']]);
            if (! DB::table('generator_form_definitions')->where('generator_version_id', $version->id)->exists()) {
                DB::table('generator_form_definitions')->insert(['generator_version_id' => $version->id, 'schema' => file_get_contents(resource_path('generators/'.$id.'.json')), 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        foreach ([
            ['verification', 'Proses verifikasi generator', 'Kenali status publikasi dan perbedaan antara observasi, perbandingan output, dan uji RouterOS.', "## Ketepatan sebelum publikasi\n\nSetiap generator melewati audit form, validasi input, perbandingan output, dan pengujian pada lab RouterOS yang diizinkan.\n\n- **BLOCKED**: ada kebutuhan data atau verifikasi yang belum terpenuhi.\n- **OUTPUT MATCHED**: cocok untuk kasus yang diuji, bukan klaim semua kombinasi.\n- **ROUTEROS TESTED**: pengujian lab yang relevan tercatat.\n- **COMPLETE**: seluruh kriteria publikasi terpenuhi.\n\nExact match membandingkan seluruh teks yang diizinkan. Semantic match membandingkan efek konfigurasi. Keduanya dilaporkan terpisah.\n\nGenerator yang belum memenuhi syarat tetap terkunci. Tidak ada script contoh yang diklaim siap diterapkan."],
            ['account-recovery', 'Akun dan kode pemulihan', 'Daftar dengan username, simpan kode pemulihan, dan kelola sesi Anda.', "## Akun tanpa email wajib\n\nGunakan username unik dan password minimal 12 karakter yang mengandung huruf dan angka.\n\n## Simpan kode pemulihan\n\nSetelah registrasi, delapan kode pemulihan ditampilkan satu kali. Simpan di tempat aman. Satu kode hanya dapat digunakan sekali. Jangan bagikan kode atau password kepada orang lain.\n\nHalaman Akun memungkinkan Anda mengganti password, mencabut sesi perangkat, dan membuat kode pemulihan baru. Pembuatan kode baru membatalkan kode lama.\n\n## Penghapusan akun\n\nPermintaan penghapusan memerlukan password. Akun langsung dinonaktifkan dan dijadwalkan dihapus setelah 30 hari. Anda dapat login untuk membatalkan selama tenggang tersebut."],
            ['safe-workflow', 'Alur kerja konfigurasi yang aman', 'Tinjau parameter, dokumentasikan perubahan, dan verifikasi di lab.', "## Sebelum menggunakan output\n\n1. Catat versi RouterOS dan topologi jaringan.\n2. Periksa interface, subnet, gateway, dan rule yang sudah ada.\n3. Buat backup konfigurasi.\n4. Tinjau setiap langkah script secara manual.\n5. Uji di lab yang sesuai sebelum perubahan pada jaringan operasional.\n\nNANSAN NET Tools tidak membuka koneksi atau menjalankan konfigurasi pada router Anda. Simpan hanya parameter yang diperlukan; jangan memasukkan password atau token dalam nama, komentar, preset, atau feedback."],
        ] as [$slug,$title,$excerpt,$body]) {
            DocumentationArticle::firstOrCreate(['slug' => $slug], ['title' => $title, 'excerpt' => $excerpt, 'body' => $body, 'status' => 'published', 'published_at' => now()]);
        }
        foreach ([
            ['about', 'Tentang NANSAN NET Tools', "## Dibangun untuk komunitas jaringan\n\nNANSAN NET Tools adalah ruang kerja MikroTik milik NANSAN NET. Semua fitur gratis tanpa paket berbayar.\n\nLima generator prioritas sedang dikembangkan dengan mengutamakan bukti pengujian. Identitas dan kode aplikasi dikembangkan secara independen.", false],
            ['contact', 'Kontak', "## Hubungi NANSAN NET\n\nGunakan halaman **Masukan & bantuan** setelah login untuk mengirim laporan bug atau saran.\n\nKontak publik tambahan menunggu konfirmasi pemilik proyek. Jangan mengirim password, token, atau konfigurasi sensitif dalam laporan.", false],
            ['privacy-policy', 'Privacy Policy', "## Draf kebijakan privasi\n\nAplikasi menyimpan username, hash password, preferensi, serta konten yang Anda pilih untuk simpan. Sesi menyimpan informasi perangkat dan alamat IP untuk pengelolaan akses. Email tidak wajib.\n\nKode pemulihan disimpan sebagai hash. Turnstile digunakan untuk verifikasi registrasi pada lingkungan yang mengaktifkannya.\n\nAudit operasional disimpan 90 hari dan aktivitas pengguna 30 hari. Permintaan penghapusan terverifikasi memiliki tenggang 30 hari. Penanganan backup dan dasar retensi perlu disahkan pemilik sebelum peluncuran.\n\nTujuan pemrosesan, identitas pengendali data, kontak, pemroses pihak ketiga dan mekanisme hak pengguna perlu ditinjau pemilik proyek sebelum production.", true],
            ['terms', 'Terms & Conditions', "## Draf syarat penggunaan\n\nSemua fitur tersedia gratis. Pengguna bertanggung jawab menjaga kredensial akun, memahami konfigurasi jaringan, dan meninjau hasil sebelum digunakan. Jangan menggunakan platform untuk tindakan yang tidak diizinkan.\n\nKetersediaan generator mengikuti hasil verifikasi dan pengujian. Output yang belum diterbitkan tidak tersedia untuk digunakan.\n\nKetentuan tanggung jawab, yurisdiksi, penanganan sengketa, dan perubahan layanan perlu ditinjau pemilik proyek.", true],
            ['cookies', 'Cookie Policy', "## Draf kebijakan cookie\n\nCookie sesi dan perlindungan CSRF digunakan untuk autentikasi dan keamanan aplikasi. Pilihan remember me menggunakan cookie autentikasi persisten. Bahasa dan tema dapat disimpan di local storage perangkat.\n\nKonfigurasi cookie Cloudflare dan Turnstile pada deployment perlu ditinjau pemilik proyek. Inventaris cookie final harus diverifikasi pada staging.", true],
            ['disclaimer', 'MikroTik Script Disclaimer', "## Tinjau dan uji konfigurasi\n\nOutput generator adalah bantuan penyusunan konfigurasi. Jangan menerapkannya tanpa memahami efek setiap perintah terhadap router, jaringan, dan rule yang sudah ada.\n\nBuat backup dan gunakan lingkungan lab. Kesesuaian dengan topologi Anda harus ditinjau secara mandiri. NANSAN NET Tools tidak menjalankan script atau mengakses router pengguna secara otomatis.\n\nPernyataan ini merupakan draf untuk ditinjau pemilik proyek sebelum production.", true],
        ] as [$slug,$title,$body,$review]) {
            CmsPage::firstOrCreate(['slug' => $slug], ['title' => $title, 'body' => $body, 'status' => 'published', 'owner_review_required' => $review]);
        }
        $this->call(EnglishContentSeeder::class);
        $this->call(GeneratorEvidenceSeeder::class);
    }
}
