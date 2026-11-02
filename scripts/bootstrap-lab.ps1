$ErrorActionPreference = 'Stop'
$labTools = Join-Path $PSScriptRoot '..\.runtime\lab-tools'
New-Item -ItemType Directory -Force -Path $labTools | Out-Null
$installer = Join-Path $labTools 'qemu-setup.exe'
if (-not (Test-Path -LiteralPath $installer)) { Invoke-WebRequest -UseBasicParsing -Uri 'https://qemu.weilnetz.de/w64/qemu-w64-setup-20260811.exe' -OutFile $installer }
$expected = '5bcf9eed634e8575a37b74f445af41a2fe4106da512d0c30c368301d4c105037fdfab40a5287367a28a957624cddebbc8c07e16c88ab6634f554cdf3d16bf543'
if ((Get-FileHash -LiteralPath $installer -Algorithm SHA512).Hash.ToLowerInvariant() -ne $expected) { throw 'QEMU installer checksum mismatch' }
$qemuDir = Join-Path $labTools 'qemu'
if (-not (Test-Path -LiteralPath (Join-Path $qemuDir 'qemu-system-x86_64.exe'))) {
    & 'C:\Program Files\7-Zip\7z.exe' x $installer ('-o' + $qemuDir) -y | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'QEMU archive extraction failed' }
}
foreach ($rosVersion in '7.25','6.49.19') {
    $archive = Join-Path $labTools ('chr-' + $rosVersion + '.zip')
    if (-not (Test-Path -LiteralPath $archive)) { Invoke-WebRequest -UseBasicParsing -Uri ('https://download.mikrotik.com/routeros/' + $rosVersion + '/chr-' + $rosVersion + '.img.zip') -OutFile $archive }
    Expand-Archive -LiteralPath $archive -DestinationPath (Join-Path $labTools $rosVersion) -Force
    $digest = (Get-FileHash -LiteralPath $archive -Algorithm SHA256).Hash.ToLowerInvariant()
    Write-Output ('CHR ' + $rosVersion + ' official download archive SHA256: ' + $digest)
}
& (Join-Path $qemuDir 'qemu-system-x86_64.exe') --version
