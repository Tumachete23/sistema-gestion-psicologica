# Sistema de gestión psicológica

HU-001: base de desarrollo. Monorepo Laravel + React/Vite, PostgreSQL y Redis.
No incluye módulos clínicos, autenticación ni CI.

## Estructura

```text
backend/              Laravel, tests y Dockerfile
frontend/             React + Vite y tests
scripts/              Inicialización local y smoke test de servicios
docs/                 Estado de validación de HU-001
docker-compose.yml    Cuatro servicios de desarrollo
.env.example          Plantilla sin secretos
```

## Requisitos

- Docker Desktop con contenedores Linux y WSL 2 en Windows; motor iniciado.
- Docker Compose v2 o superior y Git.
- Para trabajar fuera de Docker: PHP 8.5 + Composer 2.9 y Node 24 LTS + npm.
- Internet para la primera descarga de imágenes y dependencias.

## Preparación (PowerShell, desde la raíz)

```powershell
if (!(Test-Path .env)) { Copy-Item .env.example .env }
docker run --rm -v "${PWD}:/workspace" -w /workspace php:8.5-cli-bookworm php scripts/init-env.php
docker compose config --quiet
docker compose up -d --build --wait --wait-timeout 300
```

Si PHP ya está instalado, `php scripts/init-env.php` sustituye al comando `docker run`.
El script completa únicamente APP_KEY y DB_PASSWORD vacíos con valores aleatorios locales.
Si el puerto 8000 está ocupado, cambia BACKEND_PORT=18000 y APP_URL=http://localhost:18000
en `.env` antes del arranque, y usa ese puerto en las comprobaciones HTTP.
No cambia claves existentes ni muestra sus valores. No publiques `.env`.
Compose toma las variables del `.env` raíz; no requiere crear `backend/.env`.
`backend/.env.example` se conserva como referencia para ejecución local independiente.

Los contenedores instalan las versiones de `composer.lock` y `package-lock.json`.
No ejecutes `composer setup` ni instales el frontend del esqueleto Laravel:
la SPA de este monorepo está en `frontend/`.

## Acceso y comprobaciones

- Frontend: <http://localhost:5173>.
- API: <http://localhost:8000/api/health>, HTTP 200 y `{"status":"ok"}`.
- La pantalla inicial consulta `/api/health` a través del proxy Vite.

```powershell
docker compose ps
Invoke-RestMethod http://localhost:8000/api/health
Invoke-RestMethod http://localhost:5173/api/health
docker compose exec backend php artisan migrate --no-interaction
```

Para verificar PostgreSQL y Redis desde Laravel en PowerShell:

```powershell
Get-Content scripts/check-services.php -Raw | docker compose exec -T backend php /dev/stdin
```

Las migraciones son exclusivamente las estándar de Laravel. No se ejecutan automáticamente
en cada arranque. El health HTTP solo verifica la aplicación; el script comprueba conexiones
reales sin exponer errores internos ni credenciales.

## Tests y compilación

```powershell
docker compose exec backend php artisan test
docker compose exec frontend npm test
docker compose exec frontend npm run build
```

Alternativa local:

```powershell
cd backend
composer install
php artisan test
cd ../frontend
npm ci
npm test
npm run build
```

Las pruebas Laravel usan SQLite en memoria y no modifican PostgreSQL.
El frontend usa un proxy de desarrollo; una futura publicación necesitará configurar el
servidor que atienda la SPA y la API. Los servidores actuales son solo para desarrollo.

## Detener y reiniciar

```powershell
docker compose stop
docker compose start
# Retirar contenedores conservando los datos:
docker compose down
```

No uses `down -v`: elimina los volúmenes persistentes. Cambiar DB_PASSWORD después de
inicializar PostgreSQL no cambia automáticamente la contraseña almacenada en la base.

## Windows y Linux

- Rutas relativas y puertos publicados solamente en localhost.
- `.gitattributes` conserva LF para scripts Linux.
- `vendor` y `node_modules` usan volúmenes Docker separados del host.
- Vite usa polling, configurable con VITE_USE_POLLING.
- PostgreSQL y Redis conservan datos en volúmenes nombrados y no publican puertos.
- El entrypoint crea los directorios de almacenamiento que Laravel necesita.
- PHP 8.5, Node 24, PostgreSQL 17 y Redis 7.4 fijan líneas de versiones; no se usa `latest`.

Si Docker no conecta al motor Linux, abre Docker Desktop y comprueba WSL 2.
`docker compose config --quiet` valida configuración, pero no prueba servicios activos.
Consulta `docs/HU-001.md` para distinguir resultados ejecutados de verificaciones pendientes.
