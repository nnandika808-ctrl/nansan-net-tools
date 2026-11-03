<?php

namespace App\Generators\Modules;

use App\Generators\GeneratedScript;
use App\Generators\GeneratorModule;
use App\Generators\RouterOs;
use App\Generators\StaticRoutingDatasets;
use App\Generators\UnverifiedGenerator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class QueueQosModule implements GeneratorModule
{
    public function id(): string
    {
        return 'G02';
    }

    public function version(): string
    {
        return '0.1.0-draft';
    }

    public function schema(): array
    {
        return (new UnverifiedGenerator($this->id()))->schema();
    }

    public function validate(array $input): array
    {
        $defaults = array_column($this->schema()['fields'], 'default', 'key');
        if (array_diff(array_keys($input), array_keys($defaults))) {
            throw ValidationException::withMessages(['input' => 'Field yang tidak dikenal ditolak.']);
        }
        $apps = array_column(array_filter(collect($this->schema()['fields'])->firstWhere('key', 'Queue-List')['options'], fn ($option) => ! $option['disabled'] && $option['value'] !== ''), 'value');
        $data = Validator::make(array_replace($defaults, $input), [
            'Queue-Options' => ['required', 'string', Rule::in(['Queue-Simple', 'Queue-Tree'])],
            'Queue-List' => ['required', 'string', Rule::in($apps)],
            'Queue-Name' => ['bail', 'required', 'string', 'max:80', function ($attribute, $value, $fail) {
                if (preg_match('/[\x00-\x1f\x7f]/', $value) || strpbrk($value, '\"$;[]') !== false) {
                    $fail('Nama queue berisi karakter yang tidak didukung.');
                }
            }],
            'Priority' => ['required', 'integer', 'between:1,8'],
            'Max-Limit' => ['required', 'string', 'max:20', 'regex:/^[0-9]{1,12}(?:\.[0-9]{1,3})?[kKMG]?$/D'],
            'match-condition' => ['required', 'string', Rule::in(['tls-host', 'content'])],
        ])->validate();
        preg_match('/^([0-9.]+)([kKMG]?)$/D', $data['Max-Limit'], $rate);
        $scale = match ($rate[2]) {
            'k', 'K' => 1000, 'M' => 1000000, 'G' => 1000000000, default => 1
        };
        if ((float) $rate[1] * $scale > 1000000000000) {
            throw ValidationException::withMessages(['Max-Limit' => 'Batas draf ini maksimal 1 Tbps.']);
        }

        return $data;
    }

    public function configuration(array $data): array
    {
        $app = $data['Queue-List'];
        $name = $data['Queue-Name'];
        $priority = (string) $data['Priority'];
        $limit = $data['Max-Limit'];
        $entries = [];
        if ($data['Queue-Options'] === 'Queue-Simple') {
            $entries[] = ['path' => '/queue simple', 'operation' => 'add', 'fields' => ['priority' => $priority.'/'.$priority, 'max-limit' => $limit.'/'.$limit, 'name' => $name, 'packet-marks' => $app.'-Up,'.$app.'-Down', 'target' => '192.168.0.0/16,172.16.0.0/12,10.0.0.0/8']];
        } else {
            foreach (['UP' => 'Up', 'DOWN' => 'Down'] as $suffix => $mark) {
                $entries[] = ['path' => '/queue tree', 'operation' => 'add', 'fields' => ['parent' => 'global', 'priority' => $priority, 'max-limit' => $limit, 'name' => $name.' '.$suffix, 'packet-mark' => $app.'-'.$mark]];
            }
        }
        $entries[] = ['path' => '/ip firewall mangle', 'operation' => 'add', 'fields' => ['action' => 'mark-connection', 'chain' => 'prerouting', 'new-connection-mark' => $app.'-conn', 'dst-address-list' => 'Mikrotik-'.$app, 'passthrough' => 'yes', 'src-address-list' => 'LOCAL-IP', 'comment' => 'NANSAN-G02-'.$app]];
        $entries[] = ['path' => '/ip firewall mangle', 'operation' => 'add', 'fields' => ['action' => 'mark-packet', 'chain' => 'prerouting', 'connection-mark' => $app.'-conn', 'src-address-list' => 'LOCAL-IP', 'new-packet-mark' => $app.'-Up', 'passthrough' => 'no']];
        $entries[] = ['path' => '/ip firewall mangle', 'operation' => 'add', 'fields' => ['action' => 'mark-packet', 'chain' => 'postrouting', 'connection-mark' => $app.'-conn', 'dst-address-list' => 'LOCAL-IP', 'new-packet-mark' => $app.'-Down', 'passthrough' => 'no']];
        foreach (['192.168.0.0/16', '172.16.0.0/12', '10.0.0.0/8'] as $subnet) {
            $entries[] = ['path' => '/ip firewall address-list', 'operation' => 'add', 'fields' => ['address' => $subnet, 'list' => 'LOCAL-IP']];
        }

        return $entries;
    }

    public function generate(array $input): GeneratedScript
    {
        $data = $this->validate($input);
        $artifact = app(StaticRoutingDatasets::class)->artifact($data['Queue-List'], $data['match-condition']);
        $filename = 'Nansan-QoS-'.$data['Queue-List'].'-'.$data['match-condition'].'.rsc';
        $url = rtrim(config('app.url'), '/').'/datasets/static-routing/'.$artifact['sha256'].'.rsc';
        $first = '# NANSAN NET Tools - Queue QoS STEP 1 / DRAFT'."\n".'/file remove '.RouterOs::quote($filename)."\n".'/tool fetch url='.RouterOs::quote($url).' dst-path='.RouterOs::quote($filename)."\n";
        $second = '# NANSAN NET Tools - Queue QoS STEP 2 / DRAFT'."\n".'/import file-name='.RouterOs::quote($filename)."\n".'/file remove '.RouterOs::quote($filename)."\n".RouterOs::render($this->configuration($data));
        $warnings = [
            'Draf privat: parameter 214 kasus diamati; urutan queue sebelum mangle dipertahankan. Hasil shaping, prioritas saat kontensi, dan trafik aplikasi nyata belum diverifikasi.',
            'Menggunakan snapshot dataset yang sama dengan Static Routing. Prefix dapat mencakup trafik di luar satu aplikasi; jangan menganggap daftar ini selalu mutakhir.',
            'Periksa hierarki queue, urutan mangle, FastTrack dan konfigurasi yang sudah ada. Draf tidak mematikan FastTrack atau mengubah metode referensi secara otomatis.',
            'URL dataset mengikuti origin NANSAN ini. Staging SSH/localhost bukan alamat unduhan untuk router operasional. Impor ulang dapat menimbulkan konflik nama queue/LOCAL-IP.',
        ];
        if (in_array($data['Queue-List'], ['Teams', 'Telegram'], true)) {
            $warnings[] = 'FAILED pada CHR 7.25: dataset aplikasi ini mengandung alamat duplikat. Duplikat dipertahankan sesuai keputusan pemilik; jangan gunakan pada jaringan operasional ROS7.';
        }

        return new GeneratedScript([['title' => 'STEP 1 - Dataset / DRAFT', 'script' => $first], ['title' => 'STEP 2 - Queue QoS / DRAFT', 'script' => $second]], '6/7', $this->version(), $warnings);
    }
}
