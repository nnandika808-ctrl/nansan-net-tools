$ErrorActionPreference = 'Stop'
$toolRoot = Join-Path $PSScriptRoot '..\.runtime\tools'
New-Item -ItemType Directory -Force -Path $toolRoot | Out-Null
$phpZip = Join-Path $toolRoot 'php.zip'
Invoke-WebRequest -UseBasicParsing -Uri 'https://downloads.php.net/~windows/releases/archives/php-8.5.12-1-nts-Win32-vs17-x64.zip' -OutFile $phpZip
if ((Get-FileHash -LiteralPath $phpZip -Algorithm SHA256).Hash.ToLowerInvariant() -ne '2a36a2edcb7a5b9252f638fd5e4cf84d7f6e066092654220dc0b63e5cf4e9723') { throw 'PHP checksum mismatch' }
Expand-Archive -LiteralPath $phpZip -DestinationPath (Join-Path $toolRoot 'php') -Force
$composerPath = Join-Path $toolRoot 'composer.phar'
Invoke-WebRequest -UseBasicParsing -Uri 'https://getcomposer.org/download/2.11.1/composer.phar' -OutFile $composerPath
if ((Get-FileHash -LiteralPath $composerPath -Algorithm SHA256).Hash.ToLowerInvariant() -ne '6f1b891e1d4927d62740276c540fb490e47689e19e102c49e8da849794882f8c') { throw 'Composer checksum mismatch' }
Invoke-WebRequest -UseBasicParsing -Uri 'https://curl.se/ca/cacert.pem' -OutFile (Join-Path $toolRoot 'cacert.pem')
$phpRoot = (Resolve-Path (Join-Path $toolRoot 'php')).Path.Replace('\','/')
$caPath = (Resolve-Path (Join-Path $toolRoot 'cacert.pem')).Path.Replace('\','/')
@"
[PHP]
extension_dir="$phpRoot/ext"
extension=curl
extension=fileinfo
extension=gd
extension=intl
extension=mbstring
extension=openssl
extension=pdo_mysql
extension=pdo_sqlite
extension=sqlite3
extension=sodium
extension=zip
memory_limit=512M
max_execution_time=120
date.timezone=Asia/Jakarta
curl.cainfo="$caPath"
openssl.cafile="$caPath"
"@ | Set-Content -LiteralPath (Join-Path $toolRoot 'php\php.ini') -Encoding ASCII
& (Join-Path $toolRoot 'php\php.exe') --version
& (Join-Path $toolRoot 'php\php.exe') $composerPath --version
