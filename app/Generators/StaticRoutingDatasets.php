<?php

namespace App\Generators;

use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class StaticRoutingDatasets
{
    private ?array $metadata = null;

    public function manifest(): array
    {
        return $this->metadata ??= json_decode(file_get_contents(resource_path('datasets/static-routing/manifest.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function artifact(string $application, string $mode): array
    {
        $entry = collect($this->manifest()['datasets'])->first(fn ($entry) => $entry['application'] === $application && $entry['mode'] === $mode);
        if (! $entry || ! isset($entry['parameter_file'])) {
            throw new HttpException(423, 'Dataset '.$application.' / '.$mode.' belum tersedia. Sumber referensi mengembalikan 404; tidak ada data pengganti otomatis.');
        }
        if (! preg_match('/^(?:tls-host|content)-[A-Za-z0-9-]+\.json$/D', $entry['parameter_file'])) {
            throw new RuntimeException('Invalid reviewed dataset filename.');
        }
        $json = file_get_contents(resource_path('datasets/static-routing/'.$entry['parameter_file']));
        if (! hash_equals($entry['parameter_sha256'], hash('sha256', $json))) {
            throw new RuntimeException('Dataset parameter integrity check failed.');
        }
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $script = "# NANSAN NET Tools - Static Routing dataset\n# Snapshot: ".$this->manifest()['version']."; review before operational use.\n";
        foreach ($data['commands'] as $command) {
            if (! in_array($command['path'], ['/ip firewall raw', '/ip firewall address-list'], true)) {
                throw new RuntimeException('Unexpected dataset command path.');
            }
            if ($command['operation'] === 'remove') {
                $key = $command['path'] === '/ip firewall raw' ? 'address-list' : 'list';
                if (($command['selector'][$key] ?? null) !== 'Mikrotik-'.$application) {
                    throw new RuntimeException('Unexpected dataset removal scope.');
                }
                $script .= $command['path'].' remove [find '.$key.'='.RouterOs::quote($command['selector'][$key])."]\n";
            } elseif ($command['operation'] === 'add') {
                $allowed = $command['path'] === '/ip firewall raw'
                    ? ['action', 'address-list', 'address-list-timeout', 'chain', 'content', 'dst-address-list', 'src-address-list', 'protocol', 'tls-host', 'dst-port']
                    : ['address', 'list'];
                if (array_diff(array_keys($command['fields']), $allowed) || ($command['fields']['address-list'] ?? $command['fields']['list'] ?? null) !== 'Mikrotik-'.$application) {
                    throw new RuntimeException('Unexpected dataset fields.');
                }
                $script .= $command['path'].' add '.implode(' ', array_map(fn ($key, $value) => $key.'='.RouterOs::quote($value), array_keys($command['fields']), $command['fields']))."\n";
            } else {
                throw new RuntimeException('Unexpected dataset operation.');
            }
        }

        return ['script' => $script, 'sha256' => hash('sha256', $script), 'source' => $entry];
    }
}
