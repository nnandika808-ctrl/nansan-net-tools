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

final class StaticRoutingModule implements GeneratorModule
{
    public function id(): string
    {
        return 'G01';
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
        $applications = array_column(array_filter($this->schema()['fields'][0]['options'], fn ($option) => ! $option['disabled'] && $option['value'] !== ''), 'value');

        return Validator::make(array_replace($defaults, $input), [
            'routing-list' => ['required', 'string', Rule::in($applications)],
            'ros-list' => ['required', 'string', Rule::in(['ros6', 'ros7'])],
            'match-condition' => ['required', 'string', Rule::in(['tls-host', 'content'])],
            'gateway-option' => ['required', 'string', 'ipv4'],
            'routing-mark' => ['required', 'string', 'regex:/^[A-Za-z][A-Za-z0-9_.-]{0,63}$/D', 'not_in:main,local'],
        ])->validate();
    }

    public function configuration(array $data): array
    {
        $application = $data['routing-list'];
        $mark = $data['routing-mark'];
        $entries = [];
        if ($data['ros-list'] === 'ros7') {
            $entries[] = ['path' => '/routing table', 'operation' => 'add', 'fields' => ['name' => $mark, 'fib' => true]];
        }
        $entries[] = ['path' => '/ip route', 'operation' => 'add', 'fields' => ['check-gateway' => 'ping', 'distance' => '1', 'gateway' => $data['gateway-option'], $data['ros-list'] === 'ros7' ? 'routing-table' : 'routing-mark' => $mark]];
        foreach (['192.168.0.0/16', '172.16.0.0/12', '10.0.0.0/8'] as $subnet) {
            $entries[] = ['path' => '/ip firewall address-list', 'operation' => 'add', 'fields' => ['address' => $subnet, 'list' => 'LOCAL-IP']];
        }
        $entries[] = ['path' => '/ip firewall mangle', 'operation' => 'add', 'fields' => ['action' => 'mark-routing', 'chain' => 'prerouting', 'src-address-list' => 'LOCAL-IP', 'dst-address-list' => 'Mikrotik-'.$application, 'new-routing-mark' => $mark, 'passthrough' => 'no', 'place-before' => '*0', 'comment' => 'NANSAN-G01-'.$application]];

        return $entries;
    }

    public function generate(array $input): GeneratedScript
    {
        $data = $this->validate($input);
        $artifact = app(StaticRoutingDatasets::class)->artifact($data['routing-list'], $data['match-condition']);
        $filename = 'Nansan-'.$data['routing-list'].'-'.$data['match-condition'].'.rsc';
        $url = rtrim(config('app.url'), '/').'/datasets/static-routing/'.$artifact['sha256'].'.rsc';
        $first = '# NANSAN NET Tools - STEP 1 / DRAFT'."\n".'/file remove '.RouterOs::quote($filename)."\n".'/tool fetch url='.RouterOs::quote($url).' dst-path='.RouterOs::quote($filename)."\n";
        $second = '# NANSAN NET Tools - STEP 2 / DRAFT'."\n".'/import file-name='.RouterOs::quote($filename)."\n".'/file remove '.RouterOs::quote($filename)."\n";
        $entries = $this->configuration($data);
        $mangle = array_pop($entries);
        $second .= RouterOs::render($entries);
        $second .= '/ip firewall mangle remove [find comment='.RouterOs::quote('NANSAN-G01-'.$data['routing-list'])."]\n";
        $second .= RouterOs::render([$mangle]);

        $warnings = ['Draf privat: 188 kombinasi wrapper diamati. Dataset snapshot berasal dari URL publik yang diminta pemilik; cakupan aplikasi dan kebaruan prefix belum diverifikasi secara independen.',
            'URL dataset milik instalasi NANSAN ini; router harus dapat menjangkaunya. Staging melalui SSH/localhost bukan alamat unduhan untuk router operasional.',
            'Urutan import, penambahan LOCAL-IP, dan place-before=*0 mengikuti perilaku yang diamati. Impor ulang/konfigurasi yang sudah ada dapat berkonflik; hasil bukan exact match atau bukti trafik ISP nyata.',
        ];
        if ($data['ros-list'] === 'ros7' && in_array($data['routing-list'], ['Teams', 'Telegram'], true)) {
            $warnings[] = 'Dataset aplikasi ini mengandung entri alamat duplikat; impor pada CHR 7.25 gagal. Jangan gunakan draf ini pada jaringan operasional.';
        }

        return new GeneratedScript([['title' => 'STEP 1 - Dataset / DRAFT', 'script' => $first], ['title' => 'STEP 2 - Routing / DRAFT', 'script' => $second]], $data['ros-list'] === 'ros7' ? '7' : '6', $this->version(), $warnings);
    }
}
