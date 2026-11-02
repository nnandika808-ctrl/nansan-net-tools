<?php

namespace App\Generators;

interface GeneratorModule
{
    public function id(): string;

    public function version(): string;

    public function schema(): array;

    public function validate(array $input): array;

    public function generate(array $input): GeneratedScript;
}
