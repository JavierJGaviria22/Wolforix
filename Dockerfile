# Stage 1: Build (Compilación de Assets y PHP)
FROM php:8.3-fpm-alpine AS builder

# Instalamos dependencias de compilación
RUN apk add --no-cache \
    build-base \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    zlib-dev \
    libzip-dev \
    icu-dev \
    libxml2-dev \
    curl \
    git \
    npm \
    nodejs

# Instalamos extensiones de PHP necesarias
RUN docker-php-ext-configure gd --with-jpeg --with-webp
RUN docker-php-ext-install -j$(nproc) gd zip bcmath pdo pdo_mysql intl

# Instalamos Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

# Instalamos dependencias de PHP y JS
RUN composer install --optimize-autoloader --no-dev --no-interaction --ignore-platform-reqs
RUN npm install && npm run build

# Stage 2: Runtime (Servidor final)
FROM php:8.3-fpm-alpine

# Solo instalamos librerías finales (no las de compilación)
RUN apk add --no-cache \
    libpng \
    libjpeg-turbo \
    libwebp \
    libzip \
    zlib \
    icu-libs \
    curl

# IMPORTANTE: Copiamos las extensiones YA COMPILADAS del builder para no repetir el error
COPY --from=builder /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=builder /usr/local/etc/php/conf.d /usr/local/etc/php/conf.d

WORKDIR /app

# Copiamos el proyecto limpio desde el builder
COPY --from=builder --chown=www-data:www-data /app /app

# Configurar permisos para Laravel
RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache \
    && chmod -R 775 /app/storage /app/bootstrap/cache

EXPOSE 8000

# Comando para iniciar el servidor de desarrollo de Laravel
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]