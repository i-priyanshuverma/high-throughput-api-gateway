# Multi-stage Dockerfile for High-Throughput API Gateway (PHP 8.3 / Swoole / Octane)

# ----------------------------------------------------
# Stage 1: Base PHP image with extensions
# ----------------------------------------------------
FROM php:8.3-cli-alpine AS base

# Install system dependencies & build tools
RUN apk add --no-linux-headers --no-cache \
    bzip2-dev \
    curl-dev \
    freetype-dev \
    gettext-dev \
    icu-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libzip-dev \
    oniguruma-dev \
    openssl-dev \
    pcre-dev \
    zlib-dev \
    $PHPIZE_DEPS \
    linux-headers

# Install PHP extensions required for Octane & Swoole
RUN docker-php-ext-install pcntl pdo pdo_mysql opcache zip sockets bcmath \
    && pecl install redis swoole \
    && docker-php-ext-enable redis swoole \
    && apk del $PHPIZE_DEPS

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ----------------------------------------------------
# Stage 2: Vendor Dependencies
# ----------------------------------------------------
FROM base AS dependencies

COPY composer.json composer.lock* ./

RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# ----------------------------------------------------
# Stage 3: Production Runner
# ----------------------------------------------------
FROM base AS runner

# Create non-root system user
RUN addgroup -g 1000 -S www && adduser -u 1000 -S www -G www

# Copy application source
COPY --chown=www:www . /var/www/html
COPY --from=dependencies /var/www/html/vendor /var/www/html/vendor

RUN composer dump-autoload --optimize

# Set permissions for Laravel storage and cache
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chmod -R 775 storage bootstrap/cache \
    && chown -R www:www storage bootstrap/cache

USER www

EXPOSE 8000

# Entrypoint running Laravel Octane with Swoole driver
ENTRYPOINT ["php", "artisan", "octane:start", "--server=swoole", "--host=0.0.0.0", "--port=8000", "--workers=auto", "--task-workers=auto"]
