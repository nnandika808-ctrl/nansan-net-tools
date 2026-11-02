$ErrorActionPreference = 'Stop'
$githubTools = Join-Path $PSScriptRoot '..\.runtime\tools\github'
New-Item -ItemType Directory -Force -Path $githubTools | Out-Null
$release = '2.103.3'
$asset = 'gh_' + $release + '_windows_amd64.zip'
$baseUrl = 'https://github.com/cli/cli/releases/download/v' + $release + '/'
$archive = Join-Path $githubTools $asset
$checksums = (Invoke-WebRequest -UseBasicParsing -Uri ($baseUrl + 'gh_' + $release + '_checksums.txt')).Content
if ($checksums -is [byte[]]) { $checksums = [System.Text.Encoding]::UTF8.GetString($checksums) }
$match = [regex]::Match($checksums, '(?m)^([a-fA-F0-9]{64})\s+' + [regex]::Escape($asset) + '\s*$')
if (-not $match.Success) { throw 'Official GitHub CLI checksum entry missing' }
Invoke-WebRequest -UseBasicParsing -Uri ($baseUrl + $asset) -OutFile $archive
if ((Get-FileHash -LiteralPath $archive -Algorithm SHA256).Hash.ToLowerInvariant() -ne $match.Groups[1].Value.ToLowerInvariant()) { throw 'GitHub CLI checksum mismatch' }
Expand-Archive -LiteralPath $archive -DestinationPath $githubTools -Force
Get-ChildItem -LiteralPath $githubTools -Filter gh.exe -Recurse | Select-Object -ExpandProperty FullName
