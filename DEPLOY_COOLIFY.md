# Deployment en Coolify

Guía para desplegar esta aplicación Laravel en Coolify.

## Archivos creados

- **Dockerfile** - Imagen multi-stage optimizada para producción
- **.dockerignore** - Archivos a excluir del build
- **docker-compose.yml** - Stack completo (app + MySQL)
- **docker/** - Configuraciones de Nginx, PHP-FPM, Supervisor y entrypoint

## Pasos para desplegar en Coolify

### 1. Preparación

```bash
# Asegurate que todos los secrets/env estén configurados
# En Coolify: Settings > Environment Variables
```

### 2. Variables de entorno requeridas

Configura en Coolify (Projects > Environment):

```
APP_NAME=WhatsApp AI CRM
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:xxxxxxxxxxxx          # Generar con: php artisan key:generate
APP_URL=https://tu-dominio.com

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=whatsapp_crm
DB_USERNAME=crm_user
DB_PASSWORD=contraseña_segura

LOG_CHANNEL=stack
LOG_STACK=single
```

### 3. Crear aplicación en Coolify

1. Ve a **New Project** → **Docker**
2. Conecta tu repo Git
3. Configura el branch (main, develop, etc.)
4. En **Build** selecciona:
   - **Dockerfile path**: `/Dockerfile`
   - **Base directory**: `/` (raíz del proyecto)
5. En **Ports** expón:
   - **80:80** (HTTP)
   - Opcionalmente **443:443** si usas reverse proxy

### 4. Configurar base de datos

**Opción A: MySQL en Coolify** (Recomendado)
- Añade un servicio MySQL en el mismo proyecto
- Coolify inyectará automáticamente las variables `DB_HOST`, `DB_PORT`, etc.

**Opción B: Base de datos externa**
- Configura manualmente `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`

### 5. Migración de datos

Coolify ejecutará automáticamente:
```bash
php artisan migrate --force
```

Si necesitas custom commands post-deploy, modifica el `docker/entrypoint.sh`

### 6. Dominio y SSL

1. En Coolify, ve a **Domains**
2. Añade tu dominio
3. SSL se configurará automáticamente con Traefik

### 7. Volúmenes (persistencia)

Los siguientes directorios están configurados como volúmenes:
- `storage/` - Logs y uploads
- `bootstrap/cache/` - Cache de aplicación

Esto asegura que los datos persistan entre deployments.

## Características del Dockerfile

✅ **Multi-stage build** - Imagen final optimizada (~500MB)
✅ **PHP 8.2-FPM** - Con extensiones gd, zip, bcmath, pdo_mysql
✅ **Nginx** - Web server integrado con config de seguridad
✅ **Supervisor** - Gestiona Nginx + PHP-FPM
✅ **Producción ready** - Sin debgging, opcache habilitado
✅ **Health checks** - Endpoint `/health` para Coolify
✅ **Assets pre-built** - Vite buildeado en el build stage

## Comandos útiles

```bash
# Build local para testing
docker build -t whatsapp-ai-crm .

# Run local con docker-compose
docker-compose up

# Ver logs
docker-compose logs -f app

# Ejecutar comandos en el container
docker-compose exec app php artisan tinker
```

## Troubleshooting

### Error: "Composer install failed"
- Verifica que `composer.lock` esté en git
- Aumenta memoria en el Dockerfile si es necesario

### Error: "Assets not found" (404 en CSS/JS)
- Asegúrate que `npm run build` completó en el build stage
- Verifica que `public/build/` existe en el container

### Error: "Database connection refused"
- En Coolify, verifica que MySQL esté up
- Incrementa timeout en migrations (usar `--force` flag)

### Memoria insuficiente
- Ajusta `pm.max_children` en `docker/php/php-fpm.conf`
- Reduce `memory_limit` en `docker/php/php.ini` si es necesario

## Notas importantes

- **Uploads**: Usa `FILESYSTEM_DISK=s3` en producción (mejor que local)
- **Queues**: Descomentar el worker en `docker/supervisor/supervisord.conf` si usas jobs
- **Cache**: Para mejor performance, usa Redis (descomenta en compose)
- **Backups**: Configura backups automáticos en Coolify para la BD

¡Listo para deploy! 🚀
