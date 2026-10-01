$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
$local = Join-Path $root '.local'
$runtime = Get-Content "$local/runtime.json" -Raw | ConvertFrom-Json
if (Test-Path "$local/vite.pid") {
    $vitePid = [int](Get-Content "$local/vite.pid" -Raw).Trim()
    $viteProcess = Get-CimInstance Win32_Process -Filter "ProcessId=$vitePid"
    $viteEntry = Join-Path $root 'frontend/node_modules/vite/bin/vite.js'
    if ($viteProcess -and $viteProcess.CommandLine -like "*$viteEntry*") { Stop-Process -Id $vitePid }
}
# Solo detiene la instancia Apache cuyo archivo de configuración es de este proyecto.
$owned = Get-CimInstance Win32_Process -Filter "name='httpd.exe'" | Where-Object { $_.CommandLine -like "*$local/httpd.conf*" }
if ($owned -and (Test-Path "$local/apache.pid")) {
    $apachePid = [int](Get-Content "$local/apache.pid" -Raw).Trim()
    if ($apachePid -notin $owned.ProcessId) { throw 'El PID no corresponde al Apache del proyecto.' }
    # Mecanismo nativo de Apache mpm_winnt para procesos que no son servicios.
    $shutdownEvent = [Threading.EventWaitHandle]::OpenExisting("ap${apachePid}_shutdown")
    try { $shutdownEvent.Set() | Out-Null } finally { $shutdownEvent.Dispose() }
    Wait-Process -Id $apachePid -Timeout 30 -ErrorAction Stop
}
& "$($runtime.pgBin)/pg_ctl.exe" -D $runtime.pgData status *> $null
if ($LASTEXITCODE -eq 0) {
    & "$($runtime.pgBin)/pg_ctl.exe" -D $runtime.pgData -m fast -w stop
    if ($LASTEXITCODE) { throw 'PostgreSQL no pudo detenerse.' }
}
Write-Output 'Datos conservados. Redis de Laragon y otros sitios no se detuvieron.'
