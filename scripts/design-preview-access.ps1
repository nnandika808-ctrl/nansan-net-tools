param([switch]$Store, [switch]$CopyPassword)
$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$credentialPath = Join-Path $projectRoot '.runtime\design-preview-owner.credential.xml'
if ($Store) {
    if (Test-Path -LiteralPath $credentialPath) { throw 'Existing preview credential preserved.' }
    $details = ($input | Out-String) | ConvertFrom-Json
    if ($details.username -ne 'nansan_preview') { throw 'Unexpected local preview username.' }
    $secure = ConvertTo-SecureString $details.password -AsPlainText -Force
    $credential = New-Object System.Management.Automation.PSCredential($details.username, $secure)
    $credential | Export-Clixml -LiteralPath $credentialPath
    Write-Output 'Local preview credential protected with Windows DPAPI.'
    exit
}
& node (Join-Path $PSScriptRoot 'start-design-preview.mjs')
if ($LASTEXITCODE -ne 0) { throw 'Local preview could not be started.' }
$credential = Import-Clixml -LiteralPath $credentialPath
Write-Output ('Username preview: ' + $credential.UserName)
if ($CopyPassword) { Set-Clipboard -Value $credential.GetNetworkCredential().Password; Write-Output 'Password tersalin. Tempel dengan Ctrl+V pada halaman login.' }
Start-Process 'http://127.0.0.1:8000/login' -WindowStyle Normal
