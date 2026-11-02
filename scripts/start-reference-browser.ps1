param([switch]$Headless)
$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$runtimeRoot = Join-Path $projectRoot '.runtime'
$nodePath = (Get-Command node.exe).Source
$chromePath = 'C:\Program Files\Google\Chrome\Application\chrome.exe'
if (!(Test-Path -LiteralPath $chromePath)) { throw 'Chrome is required for manual Premium login.' }
foreach ($port in 8931,8932) {
    $probe = New-Object Net.Sockets.TcpClient
    try { $probe.Connect('127.0.0.1',$port); throw ('Audit port already in use: '+$port) }
    catch [Net.Sockets.SocketException] { }
    finally { $probe.Dispose() }
}
New-Item -ItemType Directory -Path $runtimeRoot -Force | Out-Null
$mcpArgs = @('"'+(Join-Path $projectRoot 'node_modules\@playwright\mcp\cli.js')+'"','--port','8931','--host','127.0.0.1','--isolated','--snapshot-mode','none','--codegen','none','--no-webmcp','--redact-url-params','access_token,code,token','--viewport-size','1440x1000','--executable-path','"'+$chromePath+'"','--output-dir','"'+$runtimeRoot+'"')
if($Headless){$mcpArgs+='--headless'}
$server = Start-Process -FilePath $nodePath -ArgumentList $mcpArgs -WorkingDirectory $projectRoot -WindowStyle Hidden -RedirectStandardOutput (Join-Path $runtimeRoot 'reference-server.log') -RedirectStandardError (Join-Path $runtimeRoot 'reference-server-errors.log') -PassThru
$ready = $false
for ($attempt=0; $attempt -lt 40; $attempt++) {
    Start-Sleep -Milliseconds 250
    $probe = New-Object Net.Sockets.TcpClient
    try { $probe.Connect('127.0.0.1',8931); $ready=$true; break } catch [Net.Sockets.SocketException] { } finally { $probe.Dispose() }
}
if (!$ready) { throw 'Reference MCP server did not become ready. Inspect the private runtime error log.' }
$previousMode = $env:NANSAN_AUDIT_SNAPSHOT_MODE
try {
    $env:NANSAN_AUDIT_SNAPSHOT_MODE = 'none'
    $bridge = Start-Process -FilePath $nodePath -ArgumentList ('"'+(Join-Path $PSScriptRoot 'audit-browser.mjs')+'"') -WorkingDirectory $projectRoot -WindowStyle Hidden -RedirectStandardOutput (Join-Path $runtimeRoot 'reference-bridge.log') -RedirectStandardError (Join-Path $runtimeRoot 'reference-bridge-errors.log') -PassThru
} finally { $env:NANSAN_AUDIT_SNAPSHOT_MODE = $previousMode }
@{server=$server.Id;bridge=$bridge.Id;snapshot_mode='none'} | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $runtimeRoot 'reference-processes.json')
Write-Output 'Reference audit ready on loopback 8932. Login is manual; authentication screens are private.'
