<?php

namespace App\Generators\Modules;

use App\Generators\GamePortCatalog;
use App\Generators\GeneratedScript;
use App\Generators\GeneratorModule;
use App\Generators\RouterOs;
use App\Generators\UnverifiedGenerator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class RoutingGamesModule implements GeneratorModule
{
    public function id(): string
    {
        return 'G05';
    }

    public function version(): string
    {
        return '0.1.0-draft';
    }

    public function schema(): array
    {
        return [...(new UnverifiedGenerator($this->id()))->schema(), 'field_statuses' => app(GamePortCatalog::class)->fieldStatuses()];
    }

    public function validate(array $input): array
    {
        $fields = $this->schema()['fields'];
        $defaults = array_column($fields, 'default', 'key');
        if (array_diff(array_keys($input), array_keys($defaults))) {
            throw ValidationException::withMessages(['input' => 'Field yang tidak dikenal ditolak.']);
        }
        $rules = ['ros-list' => ['required', 'string', Rule::in(['ros6', 'ros7'])], 'ip-gateway-input' => ['bail', 'required', 'string', 'max:80', function ($attribute, $value, $fail) {
            if (! RouterOs::gateway($value)) {
                $fail('Gunakan IPv4, IPv4%interface, atau nama interface yang valid.');
            }
        }], 'myInput' => ['nullable', 'string', 'max:100', 'not_regex:/[\x00-\x1f\x7f]/']];
        foreach ($fields as $field) {
            if ($field['type'] === 'checkbox') {
                $rules[$field['key']] = ['required', 'boolean'];
            }
        }
        $data = Validator::make(array_replace($defaults, $input), $rules)->validate();
        foreach ($fields as $field) {
            if ($field['type'] === 'checkbox') {
                $data[$field['key']] = (bool) $data[$field['key']];
            }
        }

        return $data;
    }

    public function configuration(array $data): array
    {
        $entries = [];
        foreach (['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16'] as $subnet) {
            $entries[] = ['path' => '/ip firewall address-list', 'operation' => 'add', 'fields' => ['list' => 'LOCAL-IP', 'address' => $subnet]];
        }
        if ($data['ros-list'] === 'ros7') {
            $entries[] = ['path' => '/routing table', 'operation' => 'add', 'fields' => ['name' => 'routing-game', 'fib' => true]];
        }
        $entries[] = ['path' => '/ip route', 'operation' => 'add', 'fields' => ['gateway' => $data['ip-gateway-input'], $data['ros-list'] === 'ros7' ? 'routing-table' : 'routing-mark' => 'routing-game']];
        $entries[] = ['path' => '/ip firewall mangle', 'operation' => 'add', 'fields' => ['action' => 'mark-routing', 'chain' => 'prerouting', 'connection-mark' => 'conn-game', 'new-routing-mark' => 'routing-game', 'passthrough' => 'no', 'src-address-list' => 'LOCAL-IP', 'place-before' => '*0']];
        $entries[] = ['path' => '/ip firewall mangle', 'operation' => 'add', 'fields' => ['action' => 'mark-connection', 'chain' => 'prerouting', 'dst-address-list' => 'List-IP-Games', 'new-connection-mark' => 'conn-game', 'passthrough' => 'yes', 'place-before' => '*0']];
        $keys = array_column(array_filter($this->schema()['fields'], fn ($field) => $field['type'] === 'checkbox'), 'key');
        $selected = array_values(array_filter($keys, fn ($key) => $data[$key]));

        return [...$entries, ...app(GamePortCatalog::class)->rules($selected)];
    }

    public function generate(array $input): GeneratedScript
    {
        $data = $this->validate($input);
        $entries = $this->configuration($data);
        $warnings = ['Draf privat: 106 pilihan game diamati pada ROS6/ROS7. Port/protokol merupakan snapshot referensi; kebaruan dan kecocokan terhadap trafik game nyata belum diverifikasi secara independen.', 'Urutan perintah dan place-before=*0 dipertahankan, termasuk aturan routing-mark sebelum connection-mark pada teks script. Tinjau urutan rule aktual setelah diterapkan.', 'Tidak ada NAT atau koneksi WAN yang dibuat. Impor ulang dapat berkonflik dengan LOCAL-IP, routing table, route, dan mangle yang sudah ada; jangan gunakan pada jaringan operasional.'];
        if (! collect($entries)->contains(fn ($entry) => $entry['path'] === '/ip firewall raw')) {
            $warnings[] = 'Tidak ada game dipilih; bagian RAW kosong. Output hanya berisi konfigurasi routing dasar seperti yang diamati pada referensi.';
        }

        return new GeneratedScript([['title' => 'Routing Games / DRAFT', 'script' => RouterOs::render($entries)]], $data['ros-list'] === 'ros7' ? '7' : '6', $this->version(), $warnings);
    }
}
