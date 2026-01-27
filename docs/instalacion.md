# Instalación y arranque (Docker)

Requisitos: Docker Desktop (Windows/Mac) o Docker Engine (Linux) y Docker Compose.

## 1) Variables de entorno

Copia el archivo de ejemplo y ajusta valores:

```
cp sys_beneficiarios/.env.example sys_beneficiarios/.env
```

Valores recomendados para Docker:

```
APP_URL=http://localhost
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=sys_beneficiarios
DB_USERNAME=root
DB_PASSWORD=secret
```

## 2) Levantar contenedores

```
docker compose up -d --build
```

Servicios incluidos (ver `docker-compose.yml`):
- `app`: PHP-FPM 8.2/8.3 (Laravel)
- `nginx`: servidor web (expuesto en `http://localhost`)
- `mysql`: base de datos MySQL 8
- `node`: entorno Node 20 para Vite

## 3) Inicialización

```
docker compose exec app php artisan key:generate
# Migraciones y seeders base (roles, admin, catálogos si hay CSVs)
docker compose exec app php artisan migrate --seed
# Assets
docker compose exec node npm install
docker compose exec node npm run build
```

Credenciales iniciales:
- Admin: `admin@example.com` / `Password123`

## 4) Catálogos (opcional)

Coloca archivos CSV en `sys_beneficiarios/database/seeders/data/`:
- `municipios.csv` con `clave,nombre`
- `secciones.csv` con `seccional,distrito_local,distrito_federal` y `municipio_id` o `municipio_clave`

Importa:

```
docker compose exec app php artisan catalogos:import --path=database/seeders/data
```

Opcionales:
- `--fresh` limpia tablas
- `--sql=/ruta/a/archivo.sql` ejecuta un SQL previo

## 5) Desarrollo

- Vite en modo dev (hot reload):

```
docker compose exec node npm run dev
```

- Comandos artisan frecuentes:

```
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan test
```

Abre `http://localhost` en el navegador.

