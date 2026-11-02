param([switch]$Store, [switch]$CopyPassword)
$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$credentialPath = Join-Path $projectRoot '.runtime\staging-owner.credential.xml'
if ($Store) {
    if (Test-Path -LiteralPath $credentialPath) { throw 'Existing owner credential will not be overwritten.' }
    $details = ($input | Out-String) | ConvertFrom-Json
    if ($details.username -ne 'nansan') { throw 'Unexpected staging owner username.' }
    $secure = ConvertTo-SecureString $details.password -AsPlainText -Force
    $credential = New-Object System.Management.Automation.PSCredential($details.username, $secure)
    $credential | Export-Clixml -LiteralPath $credentialPath
    Write-Output 'Owner credential encrypted using Windows DPAPI; no plaintext password written.'
    exit
}
$credential = Import-Clixml -LiteralPath $credentialPath
Write-Output ('Staging username: ' + $credential.UserName)
if ($CopyPassword) {
    Set-Clipboard -Value $credential.GetNetworkCredential().Password
    Write-Output 'Password copied to clipboard. Paste it into the staging login form.'
} else {
    Write-Output 'Run with -CopyPassword to copy the password without printing it.'
}
Start-Process 'http://127.0.0.1:8091/login' -WindowStyle Hidden
