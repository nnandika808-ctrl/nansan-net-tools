<?php

namespace App\Generators\Modules;

use App\Generators\GeneratedScript;
use App\Generators\GeneratorModule;
use App\Generators\RouterOs;
use App\Generators\UnverifiedGenerator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class BurstCalculatorModule implements GeneratorModule
{
    public function id(): string
    {
        return 'G07';
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

        $data = Validator::make(array_replace($defaults, $input), [
            'up-max-limit' => ['required', 'string', 'max:20', 'regex:/^[0-9]{1,12}(?:\.[0-9]{1,3})?[kKMG]?$/D'],
            'up-speed-bonus' => ['required', 'string', 'max:20', 'regex:/^[0-9]{1,12}(?:\.[0-9]{1,3})?[kKMG]?$/D'],
            'up-interval-bonus' => ['required', 'integer', 'between:1,3600'],
            'down-max-limit' => ['required', 'string', 'max:20', 'regex:/^[0-9]{1,12}(?:\.[0-9]{1,3})?[kKMG]?$/D'],
            'down-speed-bonus' => ['required', 'string', 'max:20', 'regex:/^[0-9]{1,12}(?:\.[0-9]{1,3})?[kKMG]?$/D'],
            'down-interval-bonus' => ['required', 'integer', 'between:1,3600'],
            'client-name' => ['required', 'string', 'max:80', function ($attribute, $value, $fail) {
                if (preg_match('/[\x00-\x1f\x7f]/', $value) || strpbrk($value, '\"$;[]') !== false) {
                    $fail('Nama client berisi karakter yang tidak didukung.');
                }
            }],
            'target-ip' => ['nullable', 'string', 'max:255', function ($attribute, $value, $fail) {
                if ($value && ! RouterOs::ipv4Subnet($value)) {
                    $fail('Target IP harus berupa IPv4 atau subnet IPv4 valid.');
                }
            }],
        ])->validate();

        $upMax = $this->parseRateToKbps($data['up-max-limit']);
        $upBurst = $this->parseRateToKbps($data['up-speed-bonus']);
        if ($upBurst < $upMax) {
            throw ValidationException::withMessages(['up-speed-bonus' => 'Burst limit upload harus lebih besar atau sama dengan max limit.']);
        }

        $downMax = $this->parseRateToKbps($data['down-max-limit']);
        $downBurst = $this->parseRateToKbps($data['down-speed-bonus']);
        if ($downBurst < $downMax) {
            throw ValidationException::withMessages(['down-speed-bonus' => 'Burst limit download harus lebih besar atau sama dengan max limit.']);
        }

        return $data;
    }

    public function generate(array $input): GeneratedScript
    {
        $data = $this->validate($input);

        $clientName = $data['client-name'];
        $targetIp = $data['target-ip'] ?: '192.168.1.0/24';

        // Upload calculations
        $upMaxKbps = $this->parseRateToKbps($data['up-max-limit']);
        $upBurstKbps = $this->parseRateToKbps($data['up-speed-bonus']);
        $upDur = (int) $data['up-interval-bonus'];

        $upThresholdKbps = round($upMaxKbps * 0.75);
        $upBurstTime = (int) ceil(($upDur * $upBurstKbps) / $upThresholdKbps);
        $upLimitAtKbps = round($upMaxKbps / 8);
        $upActualDur = round(($upThresholdKbps * $upBurstTime) / $upBurstKbps, 1);

        // Download calculations
        $downMaxKbps = $this->parseRateToKbps($data['down-max-limit']);
        $downBurstKbps = $this->parseRateToKbps($data['down-speed-bonus']);
        $downDur = (int) $data['down-interval-bonus'];

        $downThresholdKbps = round($downMaxKbps * 0.75);
        $downBurstTime = (int) ceil(($downDur * $downBurstKbps) / $downThresholdKbps);
        $downLimitAtKbps = round($downMaxKbps / 8);
        $downActualDur = round(($downThresholdKbps * $downBurstTime) / $downBurstKbps, 1);

        // Formatting
        $upMaxStr = $this->formatRate($upMaxKbps);
        $downMaxStr = $this->formatRate($downMaxKbps);
        $upBurstStr = $this->formatRate($upBurstKbps);
        $downBurstStr = $this->formatRate($downBurstKbps);
        $upThresholdStr = $this->formatRate($upThresholdKbps);
        $downThresholdStr = $this->formatRate($downThresholdKbps);
        $upLimitAtStr = $this->formatRate($upLimitAtKbps);
        $downLimitAtStr = $this->formatRate($downLimitAtKbps);

        $rateLimitString = "{$upMaxStr}/{$downMaxStr} {$upBurstStr}/{$downBurstStr} {$upThresholdStr}/{$downThresholdStr} {$upBurstTime}/{$downBurstTime} 8 {$upLimitAtStr}/{$downLimitAtStr}";

        // Step 1: Summary & Rate Limit
        $step1 = [];
        $step1[] = '# NANSAN NET Tools — MikroTik Burst Limit Calculation';
        $step1[] = '# General: Upload Max: '.$upMaxStr.', Download Max: '.$downMaxStr;
        $step1[] = '# Burst: Upload Burst: '.$upBurstStr.', Download Burst: '.$downBurstStr;
        $step1[] = '# Threshold (75%): '.$upThresholdStr.' / '.$downThresholdStr;
        $step1[] = '# Burst Time: '.$upBurstTime.'s / '.$downBurstTime.'s (Durasi aktual: '.$upActualDur.'s upload, '.$downActualDur.'s download)';
        $step1[] = '# Limit At (12.5%): '.$upLimitAtStr.' / '.$downLimitAtStr;
        $step1[] = '';
        $step1[] = '# RADIUS / PPPoE / Hotspot Rate-Limit String:';
        $step1[] = '# rate-limit = "'.$rateLimitString.'"';
        $step1[] = $rateLimitString;

        // Step 2: Simple Queue Script
        $step2 = [];
        $step2[] = '# Simple Queue Burst Configuration';
        $step2[] = '/queue simple';
        $step2[] = 'add name='.RouterOs::quote($clientName).' target='.RouterOs::quote($targetIp).' max-limit="'.$upMaxStr.'/'.$downMaxStr.'" burst-limit="'.$upBurstStr.'/'.$downBurstStr.'" burst-threshold="'.$upThresholdStr.'/'.$downThresholdStr.'" burst-time="'.$upBurstTime.'/'.$downBurstTime.'" limit-at="'.$upLimitAtStr.'/'.$downLimitAtStr.'" priority="8/8" comment='.RouterOs::quote('NANSAN-G07-'.$clientName);

        // Step 3: PPPoE & Hotspot User Profile
        $step3 = [];
        $step3[] = '# PPPoE Profile';
        $step3[] = '/ppp profile';
        $step3[] = 'add name='.RouterOs::quote($clientName.'-PPPoE').' rate-limit='.RouterOs::quote($rateLimitString).' comment='.RouterOs::quote('NANSAN-G07-PPPoE');
        $step3[] = '';
        $step3[] = '# Hotspot User Profile';
        $step3[] = '/ip hotspot user profile';
        $step3[] = 'add name='.RouterOs::quote($clientName.'-Hotspot').' rate-limit='.RouterOs::quote($rateLimitString).' comment='.RouterOs::quote('NANSAN-G07-Hotspot');

        return new GeneratedScript(
            steps: [
                ['title' => 'Rate Limit String', 'script' => implode("\n", $step1)."\n"],
                ['title' => 'Simple Queue Script', 'script' => implode("\n", $step2)."\n"],
                ['title' => 'PPPoE & Hotspot Profile Script', 'script' => implode("\n", $step3)."\n"],
            ],
            rosVersion: 'any',
            version: $this->version(),
            warnings: [
                'DRAFT: Nilai burst limit ini dihitung berdasarkan formula MikroTik (Threshold = 75%, Limit-at = 12.5%). Uji di router lab sebelum diterapkan.',
            ],
        );
    }

    public function parseRateToKbps(string $rate): float
    {
        preg_match('/^([0-9.]+)([kKMG]?)$/D', trim($rate), $matches);
        $val = (float) ($matches[1] ?? 0);
        $unit = strtoupper($matches[2] ?? 'K');

        return match ($unit) {
            'M' => $val >= 10 && floor($val) == $val ? $val * 1000 : ($val == 1 ? 1000 : round($val * 1000)),
            'G' => $val * 1000000,
            default => $val,
        };
    }

    public function formatRate(float $kbps): string
    {
        if ($kbps >= 1000 && ((int) $kbps) % 1000 === 0) {
            return ((int) ($kbps / 1000)).'M';
        }

        return ((int) round($kbps)).'K';
    }
}
