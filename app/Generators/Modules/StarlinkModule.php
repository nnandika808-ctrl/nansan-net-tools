<?php

namespace App\Generators\Modules;

use App\Generators\GeneratedScript;
use App\Generators\GeneratorModule;
use App\Generators\RouterOs;
use App\Generators\UnverifiedGenerator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class StarlinkModule implements GeneratorModule
{
    public function id(): string
    {
        return 'G04';
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
        // The observed repeated fields have no HTML names. Keep stable internal keys.
        for ($i = 1; $i <= 10; $i++) {
            $defaults['connection-'.(2 * $i - 1)] = 'ether'.$i;
            $defaults['connection-'.(2 * $i)] = '1';
        }
        if (array_diff(array_keys($input), array_keys($defaults))) {
            throw ValidationException::withMessages(['input' => 'Field yang tidak dikenal ditolak.']);
        }
        $data = array_replace($defaults, $input);
        $classifiers = array_column(collect($this->schema()['fields'])->firstWhere('key', 'pccType')['options'], 'value');
        $data = Validator::make($data, [
            'ros' => ['required', Rule::in(['v6', 'v7'])],
            'count' => ['required', 'integer', 'min:2', 'max:10'],
            'mode' => ['required', Rule::in(['equal', 'manual'])],
            'pccType' => ['required', Rule::in($classifiers)],
            'localIpTarget' => ['required', 'string', 'max:500'],
            'netwatchEnable' => ['required', 'boolean'],
            'netwatchTargets' => ['nullable', 'string', 'max:200'],
            ...collect(range(1, 20))->mapWithKeys(fn ($n) => ['connection-'.$n => $n % 2
                ? ['required', 'string', fn ($a, $v, $fail) => RouterOs::interfaceName($v) ?: $fail('Interface tidak valid.')]
                : ['required', 'integer', 'min:1', 'max:256']])->all(),
        ])->validate();
        $subnets = array_map('trim', explode(',', $data['localIpTarget']));
        if (count($subnets) !== count(array_unique($subnets)) || collect($subnets)->contains(fn ($s) => ! RouterOs::ipv4Subnet($s))) {
            throw ValidationException::withMessages(['localIpTarget' => 'Gunakan IPv4/CIDR unik, dipisahkan koma.']);
        }
        $interfaces = [];
        $total = 0;
        for ($i = 1; $i <= (int) $data['count']; $i++) {
            $interfaces[] = $data['connection-'.(2 * $i - 1)];
            $total += $data['mode'] === 'manual' ? (int) $data['connection-'.(2 * $i)] : 1;
        }
        if (count(array_unique($interfaces)) !== count($interfaces)) {
            throw ValidationException::withMessages(['count' => 'Interface WAN harus berbeda.']);
        }
        // Reference uses raw integer weights: 2:2 stays denominator 4, unlike G03.
        if ($total > 256) {
            throw ValidationException::withMessages(['mode' => 'Maksimal 256 rule PCC.']);
        }
        if ($data['netwatchEnable'] && trim($data['netwatchTargets'] ?? '') !== '') {
            $targets = array_map('trim', explode(',', $data['netwatchTargets']));
            if (count($targets) !== (int) $data['count'] || collect($targets)->contains(fn ($t) => ! filter_var($t, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))) {
                throw ValidationException::withMessages(['netwatchTargets' => 'Isi satu target IPv4 per WAN.']);
            }
        }

        return $data;
    }

    public function configuration(array $data): array
    {
        $entries = [];
        $add = function (string $path, array $fields, string $operation = 'add') use (&$entries) {
            $entries[] = ['path' => $path, 'operation' => $operation, 'fields' => $fields];
        };
        $count = (int) $data['count'];
        $ros7 = $data['ros'] === 'v7';
        $watch = (bool) $data['netwatchEnable'];
        $add('/ip dns', ['servers' => '8.8.8.8,8.8.4.4,1.1.1.1,9.9.9.9'], 'set');
        foreach (array_map('trim', explode(',', $data['localIpTarget'])) as $subnet) {
            $add('/ip firewall address-list', ['address' => $subnet, 'list' => 'LOCAL-IP']);
        }
        if ($ros7) {
            foreach ($watch ? ['', '-MONITOR'] : [''] as $suffix) {
                for ($i = 1; $i <= $count; $i++) {
                    $add('/routing table', ['fib' => true, 'name' => 'STARLINK'.$i.$suffix]);
                }
            }
        }
        for ($i = 1; $i <= $count; $i++) {
            $add('/ip firewall nat', ['chain' => 'srcnat', 'out-interface' => $data['connection-'.(2 * $i - 1)], 'action' => 'masquerade']);
        }
        for ($i = 1; $i <= $count; $i++) {
            $add('/ip dhcp-client', ['interface' => $data['connection-'.(2 * $i - 1)], 'disabled' => 'no', 'add-default-route' => 'no', 'default-route-distance' => (string) $i, 'use-peer-dns' => 'no', 'comment' => 'STARLINK-'.$i, 'script' => $this->leaseScript($data, $i)]);
        }
        $add('/ip firewall mangle', ['action' => 'accept', 'chain' => 'prerouting', 'hotspot' => 'from-client,!auth', 'disabled' => 'yes']);
        foreach (['prerouting', 'postrouting', 'forward', 'input', 'output'] as $chain) {
            $add('/ip firewall mangle', ['action' => 'accept', 'chain' => $chain, 'dst-address-list' => 'LOCAL-IP', 'src-address-list' => 'LOCAL-IP']);
        }
        for ($i = 1; $i <= $count; $i++) {
            $add('/ip firewall mangle', ['action' => 'mark-connection', 'chain' => 'input', 'connection-mark' => 'no-mark', 'in-interface' => $data['connection-'.(2 * $i - 1)], 'new-connection-mark' => 'STARLINK'.$i.'-CONN', 'passthrough' => 'yes']);
        }
        for ($i = 1; $i <= $count; $i++) {
            $add('/ip firewall mangle', ['action' => 'mark-routing', 'chain' => 'output', 'connection-mark' => 'STARLINK'.$i.'-CONN', 'new-routing-mark' => 'STARLINK'.$i, 'passthrough' => 'no']);
        }
        $target = ['dst-address-list' => '!LOCAL-IP', 'src-address-list' => 'LOCAL-IP'];
        $weights = [];
        for ($i = 1; $i <= $count; $i++) {
            $weights[] = $data['mode'] === 'manual' ? (int) $data['connection-'.(2 * $i)] : 1;
        }
        $remainder = 0;
        foreach ($weights as $idx => $weight) {
            for ($n = 0; $n < $weight; $n++) {
                $add('/ip firewall mangle', ['action' => 'mark-connection', 'chain' => 'prerouting', 'connection-state' => 'new', 'connection-mark' => 'no-mark', 'new-connection-mark' => 'STARLINK'.($idx + 1).'-CONN', 'passthrough' => 'yes', 'per-connection-classifier' => $data['pccType'].':'.array_sum($weights).'/'.$remainder++, 'dst-address-type' => '!local', ...$target]);
            }
        }
        for ($i = 1; $i <= $count; $i++) {
            $add('/ip firewall mangle', ['action' => 'mark-routing', 'chain' => 'prerouting', 'connection-mark' => 'STARLINK'.$i.'-CONN', 'new-routing-mark' => 'STARLINK'.$i, 'passthrough' => 'yes', ...$target]);
        }
        if ($watch) {
            $targets = trim($data['netwatchTargets'] ?? '') === '' ? array_map(fn ($i) => '1.1.1.'.$i, range(1, $count)) : array_map('trim', explode(',', $data['netwatchTargets']));
            foreach ($targets as $idx => $ip) {
                $add($ros7 ? '/routing rule' : '/ip route rule', $ros7 ? ['action' => 'lookup', 'disabled' => 'no', 'dst-address' => $ip.'/32', 'table' => 'STARLINK'.($idx + 1).'-MONITOR'] : ['dst-address' => $ip.'/32', 'table' => 'STARLINK'.($idx + 1).'-MONITOR']);
            }
            foreach ($targets as $idx => $ip) {
                $selector = '[find comment="LB-STARLINK'.($idx + 1).'" or comment="MAIN-STARLINK'.($idx + 1).'"]';
                $add('/tool netwatch', ['host' => $ip, 'interval' => '10s', 'down-script' => '/ip route disable '.$selector, 'up-script' => '/ip route enable '.$selector]);
            }
        }

        return $entries;
    }

    private function leaseScript(array $data, int $i): string
    {
        $interface = $data['connection-'.(2 * $i - 1)];
        $selector = 'comment="LB-STARLINK'.$i.'" or comment="MAIN-STARLINK'.$i.'"';
        if ($data['netwatchEnable']) {
            $selector .= ' or comment="MONITOR-STARLINK'.$i.'"';
        }
        $gateway = '($"gateway-address" . '.RouterOs::quote('%'.$interface).')';
        $script = '/ip route remove [find '.$selector.']; :if ($bound = 1) do={ ';
        $script .= '/ip route add dst-address=0.0.0.0/0 gateway='.$gateway.' distance='.$i.' comment="MAIN-STARLINK'.$i.'"; ';
        foreach ($data['netwatchEnable'] ? ['', '-MONITOR'] : [''] as $suffix) {
            $script .= '/ip route add dst-address=0.0.0.0/0 gateway='.$gateway.' '.($data['ros'] === 'v7' ? 'routing-table' : 'routing-mark').'="STARLINK'.$i.$suffix.'" distance=1 comment="'.($suffix === '' ? 'LB' : 'MONITOR').'-STARLINK'.$i.'"; ';
        }

        return $script.'}';
    }

    public function generate(array $input): GeneratedScript
    {
        $data = $this->validate($input);

        return new GeneratedScript([['title' => 'Starlink — DRAFT', 'script' => RouterOs::render($this->configuration($data))]], $data['ros'] === 'v7' ? '7' : '6', $this->version(), [
            'Tujuh proyeksi parameter dibandingkan; tiga kasus ROS7 lolos DHCP/UDP/NAT virtual, perubahan gateway, unbind dan monitoring bila diaktifkan. Ini bukan verifikasi ISP nyata atau semua kombinasi.',
            'CHR 6.49.19 menerima gateway DHCP baru tetapi route draf tidak diperbarui saat gateway-only renewal. Kompatibilitas ROS6 memerlukan review; jangan gunakan pada jaringan operasional.',
            'Mengubah DNS router dan membuat DHCP client baru; periksa konflik konfigurasi. Bukan exact match dan belum dipublikasikan.',
        ]);
    }
}
