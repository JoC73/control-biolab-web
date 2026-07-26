# Control BIOLAB en Railway

## Servicios

1. Crear un proyecto en Railway.
2. Agregar un servicio PostgreSQL.
3. Agregar el servicio web desde GitHub o `railway up`.
4. Generar dominio publico en `Settings > Networking`.

## Variables recomendadas

```env
APP_NAME="Control BIOLAB"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:REEMPLAZAR_CON_KEY_GENERADA
APP_URL=https://${{RAILWAY_PUBLIC_DOMAIN}}

BIOLAB_STORAGE=database
DB_CONNECTION=pgsql
DB_URL=${{Postgres.DATABASE_URL}}
DB_SSLMODE=require

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync
LOG_CHANNEL=stderr
LOG_STDERR_FORMATTER=Monolog\Formatter\JsonFormatter

BIOLAB_ADMIN_NAME=Administrador
BIOLAB_ADMIN_EMAIL=admin@biolab.local
BIOLAB_ADMIN_PASSWORD=CAMBIAR
BIOLAB_RECEPTION_NAME=Recepcion
BIOLAB_RECEPTION_EMAIL=recepcion@biolab.local
BIOLAB_RECEPTION_PASSWORD=CAMBIAR
BIOLAB_LAB_NAME=Laboratorio
BIOLAB_LAB_EMAIL=lab@biolab.local
BIOLAB_LAB_PASSWORD=CAMBIAR
BIOLAB_CASHIER_NAME=Caja
BIOLAB_CASHIER_EMAIL=caja@biolab.local
BIOLAB_CASHIER_PASSWORD=CAMBIAR
```

## Notas

- Railway tiene filesystem efimero. En produccion usa `BIOLAB_STORAGE=database`.
- Los PDF se generan al vuelo; no se guardan como archivo permanente.
- El pre-deploy ejecuta migraciones y cachea configuracion/rutas/vistas.
- Cambia todas las contrasenas antes de abrir el dominio publico.
