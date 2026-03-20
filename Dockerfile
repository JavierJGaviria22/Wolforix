# Stage 1: Construir dependencias y assets
FROM php:8.3-fpm-alpine AS builder

RUN apk add --no-cache \
    build-base \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    zlib-dev \
    libzip-dev \
    curl \
    git \
    npm \
    nodejs

RUN docker-php-ext-install gd zip bcmath pdo pdo_mysql

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

# Instalamos todo
RUN composer install --optimize-autoloader --no-dev --no-interaction
RUN npm install && npm run build

# Stage 2: Runtime ligero
FROM php:8.3-fpm-alpine

# Dependencias mínimas para que PHP corra
RUN apk add --no-cache \
    libpng \
    libjpeg-turbo \
    libwebp \
    libzip \
    curl

RUN docker-php-ext-install gd zip bcmath pdo pdo_mysql

WORKDIR /app

# Copiamos el resultado del builder
COPY --from=builder --chown=www-data:www-data /app /app

# Permisos críticos para Laravel
RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache \
    && chmod -R 775 /app/storage /app/bootstrap/cache

# Exponemos el puerto de Artisan
EXPOSE 8000

# Comando directo para arrancar el prototipo
CMD php artisan optimize && php artisan serve --host=0.0.0.0 --port=8000