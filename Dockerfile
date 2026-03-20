# Stage 1: Build dependencies
FROM php:8.2-fpm-alpine AS builder

# Install system dependencies
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

# Install PHP extensions
RUN docker-php-ext-install -j$(nproc) \
    gd \
    zip \
    bcmath \
    pdo \
    pdo_mysql

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy project files
COPY . .

# Install PHP dependencies
RUN composer install --optimize-autoloader --no-dev --no-interaction

# Install Node dependencies and build assets
RUN npm install && npm run build

# Remove node_modules and npm cache to reduce image size
RUN rm -rf node_modules npm-cache package-lock.json

---

# Stage 2: Runtime
FROM php:8.2-fpm-alpine

# Install runtime dependencies
RUN apk add --no-cache \
    libpng \
    libjpeg-turbo \
    libwebp \
    zlib \
    libzip \
    nginx \
    supervisor \
    curl

# Install PHP extensions
RUN docker-php-ext-install -j$(nproc) \
    gd \
    zip \
    bcmath \
    pdo \
    pdo_mysql

# Copy PHP configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# Copy Nginx configuration
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Copy Supervisor configuration
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

WORKDIR /app

# Copy built application from builder stage
COPY --from=builder /app /app
COPY --chown=www-data:www-data . .

# Create necessary directories and set permissions
RUN mkdir -p storage/logs storage/framework/{cache,sessions,testing} bootstrap/cache public/build \
    && chown -R www-data:www-data /app \
    && chmod -R 755 storage bootstrap/cache

# Expose port
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

# Start supervisor (manages nginx and php-fpm)
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
