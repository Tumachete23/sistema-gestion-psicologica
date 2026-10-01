param(
    [Parameter(Mandatory)][string]$LaragonRoot,
    [int]$BackendPort = 18000,
    [int]$PostgresPort = 5433
)
$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
$local = Join-Path $root '.local'
if (Test-Path "$local/runtime.json") {
    $existing = Get-Content "$local/runtime.json" -Raw | ConvertFrom-Json
    if ($existing.backendPort -ne $BackendPort -or $existing.postgresPort -ne $PostgresPort) {
        throw 'Los puertos solicitados difieren del entorno existente. No se cambiarán automáticamente.'
    }
}
$utf8 = New-Object System.Text.UTF8Encoding($false)
$phpDir = (Get-ChildItem "$LaragonRoot/bin/php" -Directory | Where-Object Name -Like 'php-8.3.*' | Sort-Object Name -Descending | Select-Object -First 1).FullName
$apacheDir = (Get-ChildItem "$LaragonRoot/bin/apache" -Directory | Sort-Object Name -Descending | Select-Object -First 1).FullName
if (!$phpDir -or !$apacheDir) { throw 'Se necesita PHP 8.3 y Apache en Laragon.' }
New-Item -ItemType Directory -Force -Path "$local/php", "$local/logs", "$local/downloads" | Out-Null

# Copia exclusiva del proyecto: no cambia php.ini ni los sitios de Laragon.
$iniPath = "$local/php/php.ini"
if (!(Test-Path $iniPath)) {
    $ini = [IO.File]::ReadAllText("$phpDir/php.ini")
    $ini = $ini -replace '(?m)^;extension=pdo_pgsql\s*$', 'extension=pdo_pgsql'
    [IO.File]::WriteAllText($iniPath, $ini, $utf8)
}
$envPath = "$root/backend/.env"
if (!(Test-Path $envPath)) {
    & "$phpDir/php.exe" "$PSScriptRoot/init-local-env.php" $BackendPort $PostgresPort
    if ($LASTEXITCODE) { throw 'Falló la preparación de backend/.env.' }
}

$pg = "$local/postgresql/pgsql/bin"
if (!(Test-Path "$pg/initdb.exe")) {
    $archive = "$local/downloads/postgresql-17.11.zip"
    if (!(Test-Path $archive)) {
        Invoke-WebRequest 'https://sbp.enterprisedb.com/getfile.jsp?fileid=1260569' -OutFile $archive
    }
    Expand-Archive -LiteralPath $archive -DestinationPath "$local/postgresql"
}
$shortLocal = (New-Object -ComObject Scripting.FileSystemObject).GetFolder($local).ShortPath
if ($shortLocal -match '[^\x00-\x7F]') { throw 'PostgreSQL necesita una ruta ASCII o nombres cortos NTFS habilitados.' }
$pg = "$shortLocal/postgresql/pgsql/bin"
$data = "$shortLocal/pgdata"
if (!(Test-Path "$data/PG_VERSION")) {
    if ((Test-Path $data) -and (Get-ChildItem $data -Force | Select-Object -First 1)) {
        throw 'El directorio PostgreSQL contiene archivos: no se inicializará encima.'
    }
    $bytes = New-Object byte[] 32
    $rng = [Security.Cryptography.RandomNumberGenerator]::Create()
    $rng.GetBytes($bytes)
    $rng.Dispose()
    [IO.File]::WriteAllText("$local/pg-admin.password", [Convert]::ToBase64String($bytes), $utf8)
    & "$pg/initdb.exe" -D $data -U postgres -A scram-sha-256 --encoding=UTF8 --locale=C "--pwfile=$shortLocal/pg-admin.password"
    if ($LASTEXITCODE) { throw 'Falló initdb.' }
    [IO.File]::AppendAllText("$data/postgresql.conf", "`nlisten_addresses = '127.0.0.1'`nport = $PostgresPort`n", $utf8)
}

$confPath = "$local/httpd.conf"
$apache = $apacheDir.Replace('\','/')
$php = $phpDir.Replace('\','/')
$workspace = $root.Replace('\','/')
$config = @"
ServerRoot "$apache"
Listen 127.0.0.1:$BackendPort
ServerName localhost
PidFile "$workspace/.local/apache.pid"
LoadModule authz_core_module modules/mod_authz_core.so
LoadModule authz_host_module modules/mod_authz_host.so
LoadModule dir_module modules/mod_dir.so
LoadModule mime_module modules/mod_mime.so
LoadModule rewrite_module modules/mod_rewrite.so
LoadModule log_config_module modules/mod_log_config.so
LoadModule env_module modules/mod_env.so
LoadFile "$php/libcrypto-3-x64.dll"
LoadFile "$php/libssl-3-x64.dll"
LoadModule php_module "$php/php8apache2_4.dll"
PHPIniDir "$workspace/.local/php"
TypesConfig "$apache/conf/mime.types"
AddType application/x-httpd-php .php
DirectoryIndex index.php
DocumentRoot "$workspace/backend/public"
ErrorLog "$workspace/.local/logs/apache-error.log"
LogLevel warn
<Directory "$workspace/backend/public">
    AllowOverride All
    Options -Indexes +FollowSymLinks
    Require local
</Directory>
"@
if (!(Test-Path $confPath)) { [IO.File]::WriteAllText($confPath, $config, $utf8) }
$settings = @{ php = "$phpDir/php.exe"; apache = "$apacheDir/bin/httpd.exe"; backendPort = $BackendPort; postgresPort = $PostgresPort; pgBin = $pg; pgData = $data; pgLog = "$shortLocal/logs/postgres.log" }
[IO.File]::WriteAllText("$local/runtime.json", ($settings | ConvertTo-Json), $utf8)
& $settings.apache -t -f $confPath
if ($LASTEXITCODE) { throw 'Configuración Apache inválida.' }
Write-Output 'Preparación local completa. Ejecuta scripts/start-local.ps1.'
