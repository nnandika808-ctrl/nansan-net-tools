$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
function Test-StagingLogin {
    try {
        $response = Invoke-WebRequest -Uri 'http://127.0.0.1:8091/login' -UseBasicParsing -TimeoutSec 3
        return $response.StatusCode -eq 200 -and $response.Content.Contains('csrf-token')
    } catch { return $false }
}
if (Test-StagingLogin) { return }

Write-Host 'Menghubungkan website ke Ubuntu 192.168.1.136.'
$sshPassword = Read-Host 'Password SSH root Ubuntu (input disembunyikan)' -AsSecureString
$nodePath = (Get-Command node -ErrorAction Stop).Source
$start = New-Object System.Diagnostics.ProcessStartInfo
$start.FileName = $nodePath
$start.Arguments = '"' + (Join-Path $PSScriptRoot 'start-staging-connection.mjs') + '"'
$start.WorkingDirectory = $projectRoot
$start.UseShellExecute = $false
$start.CreateNoWindow = $true
$start.RedirectStandardInput = $true
$start.RedirectStandardOutput = $true
$start.RedirectStandardError = $true
$process = New-Object System.Diagnostics.Process
$process.StartInfo = $start
try {
    [void]$process.Start()
    $process.StandardInput.WriteLine((New-Object System.Net.NetworkCredential('', $sshPassword)).Password)
    $process.StandardInput.Close()
    if (-not $process.WaitForExit(15000)) { throw 'Peluncur koneksi tidak selesai. Cek .runtime/staging-connection.log.' }
    if ($process.ExitCode -ne 0) { throw 'Koneksi tidak dapat dijalankan. Cek .runtime/staging-connection.log.' }
} finally {
    $sshPassword.Dispose()
    $process.Dispose()
}
for ($attempt = 0; $attempt -lt 20; $attempt++) {
    if (Test-StagingLogin) { Write-Host 'Website siap dibuka.'; return }
    Start-Sleep -Milliseconds 500
}
throw 'Website belum terhubung. Pastikan Ubuntu menyala dan password SSH benar. Cek .runtime/staging-connection.log.'
