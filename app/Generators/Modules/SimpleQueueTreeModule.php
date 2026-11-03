<?php

namespace App\Generators\Modules;

use App\Generators\GeneratedScript;
use App\Generators\GeneratorModule;
use App\Generators\RouterOs;
use App\Generators\UnverifiedGenerator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class SimpleQueueTreeModule implements GeneratorModule
{
    public function id(): string
    {
        return 'G06';
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

        $queueTypes = ['default', 'default-small', 'pcq', 'sfq', 'codel', 'fq-codel', 'cake'];

        $data = Validator::make(array_replace($defaults, $input), [
            'globalQueueType' => ['required', 'string', Rule::in(['Simple Queue', 'Queue Tree'])],
            'category-streaming-video' => ['required', 'boolean'],
            'category-social-media' => ['required', 'boolean'],
            'category-e-commerce' => ['required', 'boolean'],
            'category-video-conference' => ['required', 'boolean'],
            'downloadBandwidthISP' => ['required', 'string', 'max:20', 'regex:/^[0-9]{1,12}(?:\.[0-9]{1,3})?[kKMG]?$/D'],
            'uploadBandwidthISP' => ['required', 'string', 'max:20', 'regex:/^[0-9]{1,12}(?:\.[0-9]{1,3})?[kKMG]?$/D'],
            'globalQueueTypeInput' => ['required', 'string', Rule::in($queueTypes)],
            'localTarget' => ['required', 'string', 'max:255', function ($attribute, $value, $fail) {
                if (preg_match('/[\x00-\x1f\x7f]/', $value) || strpbrk($value, '\"$;[]') !== false) {
                    $fail('Target lokal berisi karakter tidak didukung.');
                }
            }],
        ])->validate();

        foreach (['downloadBandwidthISP', 'uploadBandwidthISP'] as $field) {
            preg_match('/^([0-9.]+)([kKMG]?)$/D', $data[$field], $rate);
            $scale = match ($rate[2] ?? '') {
                'k', 'K' => 1000,
                'M' => 1000000,
                'G' => 1000000000,
                default => 1,
            };
            if ((float) $rate[1] * $scale > 1000000000000) {
                throw ValidationException::withMessages([$field => 'Batas bandwidth draf ini maksimal 1 Tbps.']);
            }
        }

        return $data;
    }

    public function generate(array $input): GeneratedScript
    {
        $data = $this->validate($input);

        $isTree = $data['globalQueueType'] === 'Queue Tree';
        $downStr = strtoupper($data['downloadBandwidthISP']);
        $upStr = strtoupper($data['uploadBandwidthISP']);
        $qtype = $data['globalQueueTypeInput'];
        $localTarget = $data['localTarget'];

        $downNum = $this->parseRateToMbps($downStr);
        $upNum = $this->parseRateToMbps($upStr);

        $selectedCategories = [];
        if ($data['category-streaming-video']) {
            $selectedCategories['Streaming-Video'] = [
                'name' => 'Streaming Video',
                'raw_list' => 'Streaming-Video',
                'conn_mark' => 'Streaming-Video-Conn',
                'packet_mark' => 'streaming_video_mark',
            ];
        }
        if ($data['category-social-media']) {
            $selectedCategories['Social-Media'] = [
                'name' => 'Social Media',
                'raw_list' => 'Social-Media',
                'conn_mark' => 'Social-Media-Conn',
                'packet_mark' => 'social_media_mark',
            ];
        }
        if ($data['category-e-commerce']) {
            $selectedCategories['E-Commerce'] = [
                'name' => 'E-Commerce',
                'raw_list' => 'E-Commerce',
                'conn_mark' => 'E-Commerce-Conn',
                'packet_mark' => 'e_commerce_mark',
            ];
        }
        if ($data['category-video-conference']) {
            $selectedCategories['Video-Conference'] = [
                'name' => 'Video Conference',
                'raw_list' => 'Video-Conference',
                'conn_mark' => 'Video-Conference-Conn',
                'packet_mark' => 'video_conference_mark',
            ];
        }

        $leafCount = count($selectedCategories) + 2; // + Fastest Priority + Heavy Traffic
        $poolUp = $upNum / 2;
        $poolDown = $downNum / 2;

        $limitAtUpNum = round($poolUp / $leafCount, 2);
        $limitAtDownNum = round($poolDown / $leafCount, 2);

        $limitAtUpStr = $this->formatMbps($limitAtUpNum);
        $limitAtDownStr = $this->formatMbps($limitAtDownNum);

        $rawHosts = file_exists(resource_path('generators/G06-categories.json'))
            ? json_decode(file_get_contents(resource_path('generators/G06-categories.json')), true)
            : [];

        $lines = [];
        $lines[] = '# NANSAN NET Tools — independent implementation';
        $lines[] = '# MikroTik Script Generated for '.($isTree ? 'Queue Tree' : 'Simple Queue');
        $lines[] = '# ISP Download Bandwidth: '.$downStr;
        $lines[] = '# ISP Upload Bandwidth: '.$upStr;
        $lines[] = '# Global Queue Type: '.$qtype;
        $lines[] = '# DRAFT: Tinjau dan uji di lab RouterOS sebelum diterapkan ke router produksi.';
        $lines[] = '';

        // Section 1: Address Lists
        $lines[] = '# Add Local IP Address';
        $lines[] = '/ip firewall address-list';
        $lines[] = 'add list=LOCAL-IP address=10.0.0.0/8 comment='.RouterOs::quote('NANSAN-G06-LOCAL-IP');
        $lines[] = 'add list=LOCAL-IP address=172.16.0.0/12';
        $lines[] = 'add list=LOCAL-IP address=192.168.0.0/16';
        $lines[] = '';

        // Section 2: RAW Rules
        $lines[] = '# RAW Classification Rules';
        $lines[] = '/ip firewall raw';
        foreach ($selectedCategories as $catKey => $cat) {
            $hosts = $rawHosts[$catKey] ?? [];
            $first = true;
            foreach ($hosts as $host) {
                $rule = 'add action=add-dst-to-address-list address-list='.RouterOs::quote($cat['raw_list']).' address-list-timeout=1d chain=prerouting protocol=tcp tls-host='.RouterOs::quote($host).' dst-address-list=!LOCAL-IP src-address-list=LOCAL-IP';
                if ($first) {
                    $rule .= ' comment='.RouterOs::quote('NANSAN-G06-'.$cat['name']);
                    $first = false;
                }
                $lines[] = $rule;
            }
        }

        // Speedtest RAW
        $speedtestHosts = $rawHosts['Speedtest'] ?? ['*.speedtest.*', '*.ooklaserver.*', '*.ookla.*', '*.fast.*', '*.nperf.*', '*.testmy.*', '*.meteor.*'];
        $firstSt = true;
        foreach ($speedtestHosts as $stHost) {
            $rule = 'add action=add-dst-to-address-list address-list=Speedtest address-list-timeout=1d chain=prerouting protocol=tcp tls-host='.RouterOs::quote($stHost).' dst-address-list=!LOCAL-IP src-address-list=LOCAL-IP';
            if ($firstSt) {
                $rule .= ' comment='.RouterOs::quote('NANSAN-G06-Speedtest');
                $firstSt = false;
            }
            $lines[] = $rule;
        }
        $lines[] = '';

        // Section 3: Mangle Rules
        $lines[] = '# Add Mangle Rules';
        $lines[] = '/ip firewall mangle';
        $lines[] = '# Custom Chain Jumps';
        $lines[] = 'add action=jump chain=prerouting jump-target=Nansan-Pre comment='.RouterOs::quote('NANSAN-G06-Mangle');
        $lines[] = 'add action=jump chain=postrouting jump-target=Nansan-Pos';
        $lines[] = 'add action=jump chain=input jump-target=Nansan-Inp';
        $lines[] = 'add action=jump chain=output jump-target=Nansan-Out';
        $lines[] = '';

        // Ping (ICMP)
        $lines[] = '# Ping (ICMP) + Latency';
        $lines[] = 'add action=mark-packet chain=Nansan-Pre new-packet-mark="ping_mark" passthrough=no packet-size=0-64 protocol=icmp comment='.RouterOs::quote('NANSAN-G06-Ping');
        $lines[] = 'add action=mark-packet chain=Nansan-Pos new-packet-mark="ping_mark" passthrough=no packet-size=0-64 protocol=icmp';
        $lines[] = '';

        // DNS
        $lines[] = '# DNS (53) + Tracert';
        $lines[] = 'add action=mark-packet chain=Nansan-Pre new-packet-mark="dns_mark" passthrough=no port=53,5353,33434-33534,30000,64872-64875 packet-size=0-256 protocol=udp comment='.RouterOs::quote('NANSAN-G06-DNS');
        $lines[] = 'add action=mark-packet chain=Nansan-Pos new-packet-mark="dns_mark" passthrough=no port=53,5353,33434-33534,30000,64872-64875 packet-size=0-256 protocol=udp';
        $lines[] = '';

        // Winbox
        $lines[] = '# Winbox & Network Tools';
        $lines[] = 'add action=mark-packet chain=Nansan-Pre new-packet-mark="winbox_mark" passthrough=no port=20-25,2112,3389,4495,5800,5900,5938,6568,7070,8291,8728,8729 protocol=tcp comment='.RouterOs::quote('NANSAN-G06-Winbox');
        $lines[] = 'add action=mark-packet chain=Nansan-Pos new-packet-mark="winbox_mark" passthrough=no port=20-25,2112,3389,4495,5800,5900,5938,6568,7070,8291,8728,8729 protocol=tcp';
        $lines[] = '';

        // Bypass Local
        $lines[] = '# Bypass Local Traffic';
        $lines[] = 'add action=mark-packet chain=Nansan-Pre new-packet-mark="local_bypass_mark" passthrough=no src-address-list=LOCAL-IP dst-address-list=LOCAL-IP comment='.RouterOs::quote('NANSAN-G06-LocalBypass');
        $lines[] = 'add action=mark-packet chain=Nansan-Pos new-packet-mark="local_bypass_mark" passthrough=no src-address-list=LOCAL-IP dst-address-list=LOCAL-IP';
        $lines[] = 'add action=mark-packet chain=Nansan-Inp new-packet-mark="local_bypass_mark" passthrough=no src-address-list=LOCAL-IP dst-address-list=LOCAL-IP';
        $lines[] = 'add action=mark-packet chain=Nansan-Out new-packet-mark="local_bypass_mark" passthrough=no src-address-list=LOCAL-IP dst-address-list=LOCAL-IP';
        $lines[] = '';

        // Online Games
        $lines[] = '# Online Games Mangle Rules';
        $lines[] = 'add action=mark-connection chain=Nansan-Pre dst-address-list=List-IP-Games new-connection-mark=conn-game-online src-address-list=LOCAL-IP comment='.RouterOs::quote('NANSAN-G06-Games');
        $lines[] = 'add action=jump chain=Nansan-Pre jump-target=jump-game-1A port="!0-1023,1080,1194,1337,1410-1480,1451-1471,1701-1723,1935,2088,2112" protocol=tcp src-address-list=LOCAL-IP';
        $lines[] = 'add action=jump chain=jump-game-1A jump-target=jump-game-2A port="!2400-2565,2453-2471,2545-2565,3128,3389,3478-3479,3999,4244,4380,4495" protocol=tcp src-address-list=LOCAL-IP';
        $lines[] = 'add action=jump chain=jump-game-2A jump-target=jump-game-3A port="!4500,4999,5060-5070,5200-5260,5200-5280,5349,5353,5800,5900,5938,6568" protocol=tcp src-address-list=LOCAL-IP';
        $lines[] = 'add action=jump chain=jump-game-3A jump-target=jump-game-4A port="!6667,7070,8001,8080,8100,8200,8291,8444-8471,8578,8690,8728,8729,8801-8811" protocol=tcp src-address-list=LOCAL-IP';
        $lines[] = 'add action=mark-connection chain=jump-game-4A new-connection-mark=conn-game-online port="!8888,9917,12673,19305-19309,30000,33434-33534,34784,45395,45620,50318,59234" protocol=tcp src-address-list=LOCAL-IP';
        $lines[] = 'add action=jump chain=Nansan-Pre jump-target=jump-game-1B port="!0-1023,1080,1194,1337,1410-1480,1451-1471,1701-1723,1935,2088,2112" protocol=udp src-address-list=LOCAL-IP';
        $lines[] = 'add action=jump chain=jump-game-1B jump-target=jump-game-2B port="!2400-2565,2453-2471,2545-2565,3128,3389,3478-3479,3999,4244,4380,4495" protocol=udp src-address-list=LOCAL-IP';
        $lines[] = 'add action=jump chain=jump-game-2B jump-target=jump-game-3B port="!4500,4999,5060-5070,5200-5260,5200-5280,5349,5353,5800,5900,5938,6568" protocol=udp src-address-list=LOCAL-IP';
        $lines[] = 'add action=jump chain=jump-game-3B jump-target=jump-game-4B port="!6667,7070,8001,8080,8100,8200,8291,8444-8471,8578,8690,8728,8729,8801-8811" protocol=udp src-address-list=LOCAL-IP';
        $lines[] = 'add action=mark-connection chain=jump-game-4B new-connection-mark=conn-game-online port="!8888,9917,12673,19305-19309,30000,33434-33534,34784,45395,45620,50318,59234" protocol=udp src-address-list=LOCAL-IP';
        $lines[] = 'add action=mark-packet chain=Nansan-Pre connection-mark=conn-game-online new-packet-mark="game_mark" passthrough=no src-address-list=LOCAL-IP packet-size=0-512 connection-rate=0-1M';
        $lines[] = 'add action=mark-packet chain=Nansan-Pos connection-mark=conn-game-online new-packet-mark="game_mark" passthrough=no dst-address-list=LOCAL-IP packet-size=0-1024 connection-rate=0-1M';
        $lines[] = '';

        // Speedtest
        $lines[] = '# Speedtest Mangle Rules';
        $lines[] = 'add chain=Nansan-Pre action=mark-connection new-connection-mark=speedtest_conn passthrough=yes src-address-list=LOCAL-IP dst-address-list=Speedtest comment='.RouterOs::quote('NANSAN-G06-Speedtest');
        $lines[] = 'add chain=Nansan-Pre action=mark-packet new-packet-mark="speedtest_mark" connection-mark=speedtest_conn src-address-list=LOCAL-IP passthrough=no';
        $lines[] = 'add chain=Nansan-Pos action=mark-packet new-packet-mark="speedtest_mark" connection-mark=speedtest_conn dst-address-list=LOCAL-IP passthrough=no';
        $lines[] = '';

        // Category Mangles
        foreach ($selectedCategories as $cat) {
            $lines[] = '# '.$cat['name'].' Mangle Rules';
            $lines[] = 'add action=mark-connection chain=Nansan-Pre new-connection-mark='.RouterOs::quote($cat['conn_mark']).' dst-address-list='.RouterOs::quote($cat['raw_list']).' passthrough=yes src-address-list=LOCAL-IP comment='.RouterOs::quote('NANSAN-G06-'.$cat['name']);
            if ($isTree) {
                $lines[] = 'add action=mark-packet chain=Nansan-Pre connection-mark='.RouterOs::quote($cat['conn_mark']).' src-address-list=LOCAL-IP new-packet-mark='.RouterOs::quote($cat['packet_mark'].'_up').' passthrough=no';
                $lines[] = 'add action=mark-packet chain=Nansan-Pos connection-mark='.RouterOs::quote($cat['conn_mark']).' dst-address-list=LOCAL-IP new-packet-mark='.RouterOs::quote($cat['packet_mark'].'_down').' passthrough=no';
            } else {
                $lines[] = 'add action=mark-packet chain=Nansan-Pre connection-mark='.RouterOs::quote($cat['conn_mark']).' src-address-list=LOCAL-IP new-packet-mark='.RouterOs::quote($cat['packet_mark']).' passthrough=no';
                $lines[] = 'add action=mark-packet chain=Nansan-Pos connection-mark='.RouterOs::quote($cat['conn_mark']).' dst-address-list=LOCAL-IP new-packet-mark='.RouterOs::quote($cat['packet_mark']).' passthrough=no';
            }
            $lines[] = '';
        }

        // Fastest Priority
        $lines[] = '# Fastest Priority Mangle Rules';
        if ($isTree) {
            $lines[] = 'add action=mark-packet chain=Nansan-Pre new-packet-mark="fastest_mark_up" passthrough=no src-address-list=LOCAL-IP connection-rate=0-1M packet-size=0-1024 comment='.RouterOs::quote('NANSAN-G06-FastestUp');
            $lines[] = 'add action=mark-packet chain=Nansan-Pos new-packet-mark="fastest_mark_down" passthrough=no dst-address-list=LOCAL-IP connection-rate=0-1M packet-size=0-2048';
        } else {
            $lines[] = 'add action=mark-packet chain=Nansan-Pre new-packet-mark="fastest_mark" passthrough=no src-address-list=LOCAL-IP connection-rate=0-1M packet-size=0-1024 comment='.RouterOs::quote('NANSAN-G06-Fastest');
            $lines[] = 'add action=mark-packet chain=Nansan-Pos new-packet-mark="fastest_mark" passthrough=no dst-address-list=LOCAL-IP connection-rate=0-1M packet-size=0-2048';
        }
        $lines[] = '';

        // Heavy Traffic
        $lines[] = '# Heavy Traffic Mangle Rules';
        if ($isTree) {
            $lines[] = 'add action=mark-packet chain=Nansan-Pre new-packet-mark="heavy_traffic_mark_up" passthrough=no src-address-list=LOCAL-IP comment='.RouterOs::quote('NANSAN-G06-HeavyUp');
            $lines[] = 'add action=mark-packet chain=Nansan-Pos new-packet-mark="heavy_traffic_mark_down" passthrough=no dst-address-list=LOCAL-IP';
        } else {
            $lines[] = 'add action=mark-packet chain=Nansan-Pre new-packet-mark="heavy_traffic_mark" passthrough=no src-address-list=LOCAL-IP comment='.RouterOs::quote('NANSAN-G06-Heavy');
            $lines[] = 'add action=mark-packet chain=Nansan-Pos new-packet-mark="heavy_traffic_mark" passthrough=no dst-address-list=LOCAL-IP';
        }
        $lines[] = '';

        // Return rules
        $lines[] = '# Return Custom Chains';
        $lines[] = '/ip firewall mangle add action=return chain=Nansan-Pre';
        $lines[] = '/ip firewall mangle add action=return chain=Nansan-Pos';
        $lines[] = '/ip firewall mangle add action=return chain=Nansan-Inp';
        $lines[] = '/ip firewall mangle add action=return chain=Nansan-Out';
        $lines[] = '';

        // Custom Queue Type if needed
        if (in_array($qtype, ['cake', 'codel', 'fq-codel'], true)) {
            $lines[] = '# --- Custom Queue Types ---';
            $lines[] = '/queue type';
            $lines[] = 'add name='.RouterOs::quote($qtype).' kind='.$qtype;
            $lines[] = '';
        }

        // Section 4: Queues
        if ($isTree) {
            $lines[] = '# --- Queue Tree Rules ---';
            $lines[] = '/queue tree';
            $lines[] = 'add name="GLOBAL CONNECTION" parent=global max-limit=0 limit-at=0 queue='.$qtype.' priority=8 comment='.RouterOs::quote('NANSAN-G06-GlobalTree');
            $lines[] = 'add name="1.Bypass Local Traffic" parent="GLOBAL CONNECTION" max-limit=1000M limit-at=0 packet-mark=local_bypass_mark queue='.$qtype.' priority=1';
            $lines[] = 'add name="2.Ping (icmp) + Latency" parent="GLOBAL CONNECTION" max-limit=5M limit-at=1M packet-mark=ping_mark queue='.$qtype.' priority=1';
            $lines[] = 'add name="3.Dns (53) + Tracert" parent="GLOBAL CONNECTION" max-limit=5M limit-at=1M packet-mark=dns_mark queue='.$qtype.' priority=1';
            $lines[] = 'add name="4.Winbox & Network Tools" parent="GLOBAL CONNECTION" max-limit=10M limit-at=1M packet-mark=winbox_mark queue='.$qtype.' priority=1';
            $lines[] = 'add name="5.Global SpeedTest" parent="GLOBAL CONNECTION" max-limit='.$downStr.' limit-at='.$downStr.' packet-mark=speedtest_mark queue='.$qtype.' priority=2';
            $lines[] = 'add name="6.Global Games Online" parent="GLOBAL CONNECTION" max-limit=10M limit-at=10M packet-mark=game_mark queue='.$qtype.' priority=1';
            $lines[] = 'add name="7.INBOUND PACKET" parent="GLOBAL CONNECTION" max-limit='.$upStr.' limit-at='.$this->formatMbps($poolUp).' queue='.$qtype.' priority=8';

            $idx = 1;
            foreach ($selectedCategories as $cat) {
                $lines[] = 'add name='.RouterOs::quote($idx++.'. '.$cat['name'].' Up').' parent="7.INBOUND PACKET" max-limit='.$upStr.' limit-at='.$limitAtUpStr.' packet-mark='.RouterOs::quote($cat['packet_mark'].'_up').' queue='.$qtype.' priority=8';
            }
            $lines[] = 'add name='.RouterOs::quote($idx++.'. Fastest Priority Up').' parent="7.INBOUND PACKET" max-limit='.$upStr.' limit-at='.$limitAtUpStr.' packet-mark="fastest_mark_up" queue='.$qtype.' priority=4';
            $lines[] = 'add name='.RouterOs::quote($idx++.'. Heavy Traffic Up').' parent="7.INBOUND PACKET" max-limit='.$upStr.' limit-at='.$limitAtUpStr.' packet-mark="heavy_traffic_mark_up" queue='.$qtype.' priority=8';

            $lines[] = 'add name="8.OUTBOUND PACKET" parent="GLOBAL CONNECTION" max-limit='.$downStr.' limit-at='.$this->formatMbps($poolDown).' queue='.$qtype.' priority=8';

            $idxDown = 1;
            foreach ($selectedCategories as $cat) {
                $lines[] = 'add name='.RouterOs::quote($idxDown++.'. '.$cat['name'].' Down').' parent="8.OUTBOUND PACKET" max-limit='.$downStr.' limit-at='.$limitAtDownStr.' packet-mark='.RouterOs::quote($cat['packet_mark'].'_down').' queue='.$qtype.' priority=8';
            }
            $lines[] = 'add name='.RouterOs::quote($idxDown++.'. Fastest Priority Down').' parent="8.OUTBOUND PACKET" max-limit='.$downStr.' limit-at='.$limitAtDownStr.' packet-mark="fastest_mark_down" queue='.$qtype.' priority=4';
            $lines[] = 'add name='.RouterOs::quote($idxDown++.'. Heavy Traffic Down').' parent="8.OUTBOUND PACKET" max-limit='.$downStr.' limit-at='.$limitAtDownStr.' packet-mark="heavy_traffic_mark_down" queue='.$qtype.' priority=8';
        } else {
            $queueTypePair = $qtype.'/'.$qtype;
            $lines[] = '# --- Simple Queue Rules ---';
            $lines[] = '/queue simple';
            $lines[] = 'add name="1. Bypass Local Traffic" target='.RouterOs::quote($localTarget).' max-limit=1000M/1000M limit-at=0/0 packet-marks=local_bypass_mark queue='.$queueTypePair.' priority=8/8 comment='.RouterOs::quote('NANSAN-G06-Simple');
            $lines[] = 'add name="2. Ping (icmp) + Latency" target='.RouterOs::quote($localTarget).' max-limit=5M/5M limit-at=1M/1M packet-marks=ping_mark queue='.$queueTypePair.' priority=1/1';
            $lines[] = 'add name="3. Dns (53) + Tracert" target='.RouterOs::quote($localTarget).' max-limit=5M/5M limit-at=1M/1M packet-marks=dns_mark queue='.$queueTypePair.' priority=1/1';
            $lines[] = 'add name="4. Winbox & Network Tools" target='.RouterOs::quote($localTarget).' max-limit=10M/10M limit-at=1M/1M packet-marks=winbox_mark queue='.$queueTypePair.' priority=1/1';
            $lines[] = 'add name="5. Fastest Speedtest" target='.RouterOs::quote($localTarget).' max-limit='.$downStr.'/'.$downStr.' limit-at='.$downStr.'/'.$downStr.' packet-marks=speedtest_mark queue='.$queueTypePair.' priority=2/2';
            $lines[] = 'add name="6. Global Games Online" target='.RouterOs::quote($localTarget).' max-limit=10M/10M limit-at=5M/5M packet-marks=game_mark queue='.$queueTypePair.' priority=1/1';
            $lines[] = 'add name="7. GLOBAL CONNECTION" target='.RouterOs::quote($localTarget).' max-limit='.$upStr.'/'.$downStr.' limit-at=0/0 queue='.$queueTypePair.' priority=8/8';

            $idx = 1;
            foreach ($selectedCategories as $cat) {
                $lines[] = 'add name='.RouterOs::quote($idx++.'. '.$cat['name']).' target='.RouterOs::quote($localTarget).' max-limit='.$upStr.'/'.$downStr.' limit-at='.$limitAtUpStr.'/'.$limitAtDownStr.' packet-marks='.RouterOs::quote($cat['packet_mark']).' queue='.$queueTypePair.' priority=8/8 parent="7. GLOBAL CONNECTION"';
            }
            $lines[] = 'add name='.RouterOs::quote($idx++.'. Fastest Priority').' target='.RouterOs::quote($localTarget).' max-limit='.$upStr.'/'.$downStr.' limit-at='.$limitAtUpStr.'/'.$limitAtDownStr.' packet-marks=fastest_mark queue='.$queueTypePair.' priority=4/4 parent="7. GLOBAL CONNECTION"';
            $lines[] = 'add name='.RouterOs::quote($idx++.'. Heavy Traffic').' target='.RouterOs::quote($localTarget).' max-limit='.$upStr.'/'.$downStr.' limit-at='.$limitAtUpStr.'/'.$limitAtDownStr.' packet-marks=heavy_traffic_mark queue='.$queueTypePair.' priority=8/8 parent="7. GLOBAL CONNECTION"';
        }

        $script = implode("\n", $lines)."\n";

        $warnings = [
            'DRAFT: Output manajemen bandwidth ini perlu ditinjau dan diuji di lab RouterOS sebelum diterapkan.',
        ];
        if (in_array($qtype, ['cake', 'codel', 'fq-codel'], true)) {
            $warnings[] = 'Queue type '.$qtype.' memerlukan RouterOS v7.xx.';
        }

        return new GeneratedScript(
            steps: [
                ['title' => 'Bandwidth Management Script', 'script' => $script],
            ],
            rosVersion: in_array($qtype, ['cake', 'codel', 'fq-codel'], true) ? '7' : 'any',
            version: $this->version(),
            warnings: $warnings,
        );
    }

    private function parseRateToMbps(string $rate): float
    {
        preg_match('/^([0-9.]+)([kKMG]?)$/D', $rate, $matches);
        $val = (float) ($matches[1] ?? 100);
        $unit = strtoupper($matches[2] ?? 'M');

        return match ($unit) {
            'K' => $val / 1000,
            'G' => $val * 1000,
            default => $val,
        };
    }

    private function formatMbps(float $mbps): string
    {
        if ($mbps < 1.0) {
            return round($mbps * 1000, 2).'k';
        }
        if (floor($mbps) == $mbps) {
            return ((int) $mbps).'M';
        }

        return round($mbps, 2).'M';
    }
}
