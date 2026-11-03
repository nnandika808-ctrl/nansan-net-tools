<?php

namespace App\Generators;

use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class GamePortCatalog
{
    private ?array $metadata = null;

    public function data(): array
    {
        if ($this->metadata !== null) {
            return $this->metadata;
        }
        $body = file_get_contents(resource_path('datasets/routing-games/manifest.json'));
        $expected = trim(file_get_contents(resource_path('datasets/routing-games/manifest.sha256')));
        if (! hash_equals($expected, hash('sha256', $body))) {
            throw new RuntimeException('Game parameter snapshot integrity check failed.');
        }

        return $this->metadata = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }

    public function fieldStatuses(): array
    {
        $statuses = [];
        foreach ($this->data()['games'] as $game) {
            if ($game['status'] === 'FAILED_REFERENCE_SYNTAX') {
                $statuses[$game['id']] = ['status' => 'FAILED', 'message' => 'Quote pada output referensi tidak berpasangan.'];
            }
        }

        return $statuses;
    }

    public function rules(array $selected): array
    {
        $entries = [];
        foreach ($this->data()['games'] as $game) {
            if (! in_array($game['id'], $selected, true)) {
                continue;
            }
            if ($game['status'] === 'FAILED_REFERENCE_SYNTAX') {
                throw new HttpException(423, 'FAILED: '.$game['label'].' memiliki quote tidak berpasangan pada output referensi. Data dipertahankan; tidak ada port pengganti atau perbaikan otomatis.');
            }
            foreach ($game['rules'] as $rule) {
                $fields = $rule['fields'];
                if ($rule['path'] !== '/ip firewall raw' || $rule['operation'] !== 'add' || array_diff(array_keys($fields), ['action', 'address-list', 'address-list-timeout', 'chain', 'dst-address-list', 'dst-port', 'protocol']) || $fields['action'] !== 'add-dst-to-address-list' || $fields['address-list'] !== 'List-IP-Games' || $fields['address-list-timeout'] !== '1d' || $fields['chain'] !== 'prerouting' || $fields['dst-address-list'] !== '!LOCAL-IP' || ! in_array($fields['protocol'] ?? null, ['tcp', 'udp'], true) || ! preg_match('/^[0-9,-]{1,500}$/D', $fields['dst-port'] ?? '')) {
                    throw new RuntimeException('Unreviewed or malformed game rule.');
                }
                $entries[] = $rule;
            }
        }

        return $entries;
    }
}
