<?php

if (PHP_SAPI !== 'cli' || count($argv) !== 4) {
    throw new RuntimeException('Expected archive, new isolated extraction directory and SHA256.');
}
[$script, $archive, $target, $expected] = $argv;
$runtime = realpath(dirname(__DIR__).'/.runtime');
$parent = realpath(dirname($target));
if (! $runtime || ! $parent || ! str_starts_with($parent.DIRECTORY_SEPARATOR, $runtime.DIRECTORY_SEPARATOR) || file_exists($target) || ! preg_match('/^[a-f0-9]{64}$/D', $expected) || ! hash_equals($expected, hash_file('sha256', $archive))) {
    throw new RuntimeException('Expected new runtime target and checksum-matched archive.');
}
$zip = new ZipArchive;
if ($zip->open($archive) !== true) {
    throw new RuntimeException('Cannot open release.');
}
$manifest = json_decode($zip->getFromName('release.json') ?: '', true, 512, JSON_THROW_ON_ERROR);
$files = $manifest['files'] ?? [];
$size = 0;
for ($index = 0; $index < $zip->numFiles; $index++) {
    $stat = $zip->statIndex($index);
    $name = $stat['name'];
    $zip->getExternalAttributesIndex($index, $system, $attributes);
    $type = ($attributes >> 16) & 0170000;
    $size += $stat['size'];
    if (! preg_match('~^[a-zA-Z0-9_@. /+\-]+$~D', $name) || str_starts_with($name, '/') || in_array('..', explode('/', $name), true) || $type === 0120000 || $size > 512 * 1024 * 1024) {
        throw new RuntimeException('Unsafe archive entry.');
    }
    if (! str_ends_with($name, '/') && $name !== 'release.json') {
        if (! isset($files[$name]) || ! hash_equals($files[$name], hash('sha256', $zip->getFromIndex($index)))) {
            throw new RuntimeException('Release inventory mismatch.');
        }
        unset($files[$name]);
    }
}
if ($files) {
    throw new RuntimeException('Release inventory contains missing files.');
}
mkdir($target, 0750);
if (! $zip->extractTo($target)) {
    throw new RuntimeException('Release extraction failed.');
}
$zip->close();
echo "Archive checksum and complete file inventory verified.\n";
