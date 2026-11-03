<?php

// CLI packaging helper; never place deployment tooling in the public directory.
if (PHP_SAPI !== 'cli' || count($argv) !== 4) {
    throw new RuntimeException('Expected site directory, new ZIP path and commit.');
}
[$script, $site, $archive, $commit] = $argv;
$root = realpath(dirname(__DIR__));
$site = realpath($site);
if (! $site || ! str_starts_with($site, $root.DIRECTORY_SEPARATOR.'.runtime'.DIRECTORY_SEPARATOR) || realpath(dirname($archive)) !== dirname($site) || ! preg_match('/^[a-f0-9]{40}$/D', $commit) || file_exists($archive)) {
    throw new RuntimeException('Release paths must be new and confined to workspace runtime.');
}
$installed = json_decode(file_get_contents($site.'/vendor/composer/installed.json'), true, 512, JSON_THROW_ON_ERROR);
if (($installed['dev'] ?? true) || ($installed['dev-package-names'] ?? [])) {
    throw new RuntimeException('Development dependencies are not allowed in hosting archives.');
}
$inventory = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($site, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($site) + 1));
    if ($file->isLink() || ! $file->isFile()) {
        throw new RuntimeException('Only regular files are permitted.');
    }
    if (preg_match('~(^|/)(\.env(?!\.example$)(?:\.|$)|\.git|auth\.json$|node_modules|\.runtime)(/|$)|\.sqlite(?:$|-)|storage-state|\.cookie$~', $relative)) {
        throw new RuntimeException('Forbidden runtime or credential path in release.');
    }
    if ((str_starts_with($relative, 'storage/') || str_starts_with($relative, 'bootstrap/cache/')) && basename($relative) !== '.gitignore') {
        throw new RuntimeException('Runtime state must not enter the archive.');
    }
    $inventory[$relative] = hash_file('sha256', $file->getPathname());
}
ksort($inventory);
$zip = new ZipArchive;
if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
    throw new RuntimeException('Cannot create new archive.');
}
foreach ($inventory as $relative => $hash) {
    $zip->addFile($site.'/'.$relative, $relative);
    $zip->setExternalAttributesName($relative, ZipArchive::OPSYS_UNIX, 0100644 << 16);
}
foreach (['storage/app/private', 'storage/app/public', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $directory) {
    $zip->addEmptyDir($directory);
}
$zip->addFromString('release.json', json_encode(['commit' => $commit, 'files' => $inventory], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
$zip->close();
echo 'Production archive created with '.count($inventory)." verified files.\n";
