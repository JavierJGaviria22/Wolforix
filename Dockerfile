# Stage 1: Build
FROM php:8.3-fpm-alpine AS builder

# Instalamos dependencias con los nombres exactos que Alpine requiere para compilar
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

# Instalamos las extensiones de PHP
RUN docker-php-ext-configure gd --with-jpeg --with-webp
RUN docker-php-ext-install -j$(nproc) gd zip bcmath pdo pdo_mysql intl

# Instalamos Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

# Instalamos dependencias (ignorando scripts que puedan fallar sin BD)
RUN composer install --optimize-autoloader --no-dev --no-interaction --ignore-platform-reqs
RUN npm install && npm run build

# Stage 2: Runtime (La imagen que realmente se ejecutará)
FROM php:8.3-fpm-alpine

# Solo librerías de ejecución, no de compilación (más ligero)
RUN apk add --no-cache \
    libpng \
    libjpeg-turbo \
    libwebp \
    libzip \
    zlib \
    icu-libs \
    curl

RUN docker-php-ext-install pdo pdo_mysql bcmath gd zip

WORKDIR /app

# Copiamos solo lo necesario del builder
COPY --from=builder --chown=www-data:www-data /app /app

# Permisos para Laravel 12
RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache \
    && chmod -R 775 /app/storage /app/bootstrap/cache

EXPOSE 8000

# Usamos el formato JSON para CMD (evita advertencias de Docker)
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]