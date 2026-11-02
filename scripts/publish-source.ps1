param([string]$Branch = 'feature/platform-audit')
$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$env:GH_CONFIG_DIR = Join-Path $projectRoot '.runtime\github-auth'
$ghPath = (Join-Path $projectRoot '.runtime\tools\github\bin\gh.exe').Replace('\','/')
$remote = & git remote get-url origin
if ($remote -notmatch '^https://github\.com/nnandika808-ctrl/nansan-net-tools(?:\.git)?$') { throw 'Unexpected project remote' }
& node (Join-Path $PSScriptRoot 'check-source-secrets.mjs')
if ($LASTEXITCODE -ne 0) { throw 'Source safety check failed' }
& git -c credential.helper= -c ('credential.helper=!' + $ghPath + ' auth git-credential') push --set-upstream origin $Branch
if ($LASTEXITCODE -ne 0) { throw 'Private source push failed' }
