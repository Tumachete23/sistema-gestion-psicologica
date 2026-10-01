$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
$local = Join-Path $root '.local'
$runtime = Get-Content "$local/runtime.json" -Raw | ConvertFrom-Json
$pg = "$($runtime.pgBin)/pg_ctl.exe"
& $pg -D $runtime.pgData status *> $null
if ($LASTEXITCODE -ne 0) {
    & $pg -D $runtime.pgData -l $runtime.pgLog -w start
    if ($LASTEXITCODE) { throw 'PostgreSQL no pudo iniciar.' }
}
& $runtime.php -c "$local/php/php.ini" "$PSScriptRoot/provision-local-db.php"
if ($LASTEXITCODE) { throw 'Falló la preparación de la base de datos.' }
$listener = Get-NetTCPConnection -LocalPort $runtime.backendPort -State Listen -ErrorAction SilentlyContinue
if ($listener) {
    $process = Get-CimInstance Win32_Process -Filter "ProcessId=$($listener[0].OwningProcess)"
    if ($process.Name -ne 'httpd.exe' -or $process.CommandLine -notlike "*$local/httpd.conf*") {
        throw 'El puerto del backend pertenece a otro proceso; no se modificará.'
    }
} else {
    Start-Process -FilePath $runtime.apache -ArgumentList @('-f', ('"' + "$local/httpd.conf" + '"')) -WindowStyle Hidden
}
$ready = $false
for ($attempt = 0; $attempt -lt 10; $attempt++) {
    try {
        $health = Invoke-RestMethod "http://127.0.0.1:$($runtime.backendPort)/api/health" -TimeoutSec 5
        if ($health.status -eq 'ok') { $ready = $true; break }
    } catch { Start-Sleep -Seconds 1 }
}
if (!$ready) { throw 'Apache inició, pero la API no respondió correctamente. Revisa .local/logs.' }
$viteEntry = Join-Path $root 'frontend/node_modules/vite/bin/vite.js'
if (!(Test-Path $viteEntry)) { throw 'Ejecuta npm ci dentro de frontend antes de iniciar.' }
$frontendListener = Get-NetTCPConnection -LocalPort 5173 -State Listen -ErrorAction SilentlyContinue
if (!$frontendListener) {
    $nodePath = (Get-Command node -ErrorAction Stop).Source
    $viteProcess = Start-Process -FilePath $nodePath -ArgumentList @(('"' + $viteEntry + '"'), '--host', '127.0.0.1', '--port', '5173', '--strictPort') -WorkingDirectory "$root/frontend" -WindowStyle Hidden -RedirectStandardOutput "$local/logs/vite.log" -RedirectStandardError "$local/logs/vite-error.log" -PassThru
    [IO.File]::WriteAllText("$local/vite.pid", [string]$viteProcess.Id)
} else {
    $viteOwner = Get-CimInstance Win32_Process -Filter "ProcessId=$($frontendListener[0].OwningProcess)"
    if ($viteOwner.CommandLine -notlike "*$viteEntry*") { throw 'El puerto 5173 pertenece a otro proceso; no se modificará.' }
}
Write-Output "API: http://127.0.0.1:$($runtime.backendPort)/api/health"
Write-Output 'Frontend: http://127.0.0.1:5173 (Redis debe estar iniciado desde Laragon)'
