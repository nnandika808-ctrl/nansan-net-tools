$ErrorActionPreference='Stop'
$projectRoot=(Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$state=Get-Content -LiteralPath (Join-Path $projectRoot '.runtime\reference-processes.json') -Raw | ConvertFrom-Json
$expected=@{bridge=(Join-Path $PSScriptRoot 'audit-browser.mjs');server=(Join-Path $projectRoot 'node_modules\@playwright\mcp\cli.js')}
foreach($kind in 'bridge','server'){
    $entry=Get-CimInstance Win32_Process -Filter ('ProcessId='+[int]$state.$kind)
    if($entry -and $entry.CommandLine.IndexOf($expected[$kind],[StringComparison]::OrdinalIgnoreCase) -lt 0){throw 'Process ownership mismatch; nothing stopped.'}
}
try { Invoke-RestMethod -Uri 'http://127.0.0.1:8932/call' -Method Post -ContentType 'application/json' -Body '{"name":"browser_close","arguments":{}}' | Out-Null } catch { Write-Output 'Browser close was unavailable; stopping only verified audit server processes.' }
foreach($kind in 'bridge','server'){if(Get-Process -Id ([int]$state.$kind) -ErrorAction SilentlyContinue){Stop-Process -Id ([int]$state.$kind)}}
Write-Output 'Owned reference browser/server stopped.'
