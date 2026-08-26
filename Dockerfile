FROM php:8.4-cli-bookworm AS base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
        libonig-dev \
        libzip-dev \
        poppler-utils \
        unzip \
    && docker-php-ext-install -j"$(nproc)" mbstring pcntl pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /app

FROM base AS development
COPY . .
RUN mkdir -p storage/app/private storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer install --no-interaction --prefer-dist

FROM base AS production
COPY . .
RUN mkdir -p storage/app/private storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data
CMD ["php", "artisan", "queue:work", "database", "--queue=surplus-research", "--sleep=3", "--rest=1", "--tries=3", "--timeout=180", "--max-time=3600"]
