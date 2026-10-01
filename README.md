# Sistema de gestión psicológica

Entorno principal aprobado: **Laragon y servicios locales en Windows**. Docker se conserva como alternativa independiente. Solo se implementó la base: Laravel, React/Vite, PostgreSQL, Redis y health; no hay módulos clínicos ni autenticación implementada.

## Requisitos

- Laragon con Apache 2.4 y PHP 8.3.30 (la selección de PHP es explícita).
- Composer 2, Node compatible con Vite 8 y npm.
- Redis de Laragon iniciado, escuchando en 127.0.0.1:6379.
- Internet para descargar dependencias y PostgreSQL 17.11 desde EDB.

## Preparación local

Desde PowerShell en la raíz del repositorio, define la ubicación real de Laragon:

```powershell
$laragonRoot = Read-Host 'Carpeta de Laragon'
./scripts/setup-local.ps1 -LaragonRoot $laragonRoot
$r = Get-Content .local/runtime.json -Raw | ConvertFrom-Json
Push-Location backend
composer install
Pop-Location
cd frontend
npm ci
cd ..
./scripts/start-local.ps1
```

Composer resuelve dependencias para PHP 8.3.30 mediante config.platform.php. El instalador local crea backend/.env solo si no existe y conserva la APP_KEY del .env raíz cuando está disponible. No sobreescribe entornos existentes. backend/.env.example es la plantilla local; .env.example en la raíz sigue siendo la plantilla Docker.

PostgreSQL se descarga en .local/postgresql y conserva sus datos en .local/pgdata. Se crea un rol de aplicación sin privilegios de superusuario y una base propia. Las credenciales se generan localmente, no se imprimen y están excluidas de Git. En Windows, PostgreSQL usa la ruta corta NTFS para evitar problemas con los acentos del directorio; si no existe ruta corta, el instalador se detiene con explicación.

Apache y PHP usan los ejecutables de Laragon con configuración exclusiva del proyecto. Esto permite mantener los otros sitios de Laragon y sus versiones/configuraciones intactos. Esta instancia se inicia y detiene mediante los scripts del proyecto; no mediante el botón general de Laragon. PHP tiene una copia local de php.ini con pdo_pgsql habilitado.

## Iniciar y abrir

```powershell
./scripts/start-local.ps1
```

- **Frontend:** http://127.0.0.1:5173
- **API:** http://127.0.0.1:18000/api/health
- PostgreSQL: 127.0.0.1:5433, base psicologia.
- Redis: 127.0.0.1:6379, con prefijos propios para la aplicación.

**No abras frontend/index.html con file://**: React necesita el servidor Vite. La pantalla debe mostrar “API disponible”. El proxy /api apunta al Apache local; API_PROXY_TARGET permite cambiarlo.

## Migraciones y validación

Desde la raíz:

```powershell
$r = Get-Content .local/runtime.json -Raw | ConvertFrom-Json
Push-Location backend
& $r.php -c ../.local/php/php.ini artisan migrate --no-interaction
& $r.php -c ../.local/php/php.ini ../scripts/check-services.php
& $r.php -c ../.local/php/php.ini artisan test --display-warnings
Pop-Location
npm --prefix frontend test
npm --prefix frontend run build
Invoke-RestMethod http://127.0.0.1:18000/api/health
Invoke-RestMethod http://127.0.0.1:5173/api/health
```

Las migraciones incluyen la jerarquía Organization → Clinic → User. Si existen usuarios anteriores sin tenant, la nueva migración se detiene antes de modificar datos y requiere definir su pertenencia explícitamente. Los tests locales con phpunit.xml usan SQLite en memoria y una clave efímera, sin modificar PostgreSQL; CI usa su propia base PostgreSQL sgp_test mediante phpunit.ci.xml. El health devuelve únicamente status=ok; la comprobación de servicios valida PostgreSQL y Redis por separado, sin mostrar credenciales.

## Detener

El script detiene Vite, Apache y PostgreSQL de este proyecto:

```powershell
./scripts/stop-local.ps1
```

Se conservan datos y configuración. Redis compartido y los otros sitios de Laragon no se detienen. No borres .local: contiene la base de desarrollo.

## Alternativa Docker

Consulta docs/docker.md. Para coexistir con Laragon, en la terminal Docker:

```powershell
$env:BACKEND_PORT = '18001'
$env:FRONTEND_PORT = '5174'
docker compose up -d --build --wait --wait-timeout 300
```

API Docker: http://127.0.0.1:18001/api/health. Frontend Docker: http://127.0.0.1:5174. Sus volúmenes PostgreSQL/Redis son independientes de los datos locales. No uses docker compose down -v. No se necesita cambiar el .env existente para seleccionar estos puertos.

## Compatibilidad

Laravel 13 se conserva. composer.lock se resolvió para PHP 8.3.30 y usa Predis para Redis sin requerir una DLL adicional de PHP. Redis 5.0.14.1 incluido en este Laragon se utiliza únicamente para desarrollo local; Docker conserva Redis 7.4. No se afirma equivalencia completa entre ambas versiones ni aptitud de ese port antiguo para producción.

El entorno y su validación están en docs/HU-001.md. El pipeline de integración continua (HU-002) está documentado en [docs/HU-002.md](docs/HU-002.md): ejecuta migraciones y PHPUnit sobre PostgreSQL aislado, Pint y lint/tests/build del frontend en cada push y pull request.

La jerarquía y el aislamiento multi-tenant de HU-003/HU-004 se documentan en [docs/HU-003-004.md](docs/HU-003-004.md). Todavía no hay autenticación real, RBAC ni módulos clínicos.
