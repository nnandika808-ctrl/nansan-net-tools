<?php

namespace App\Generators;

final readonly class GeneratedScript
{
    public function __construct(public array $steps, public string $rosVersion, public string $version, public array $warnings = []) {}

    public function text(): string
    {
        return implode("\n\n", array_column($this->steps, 'script'));
    }
}
