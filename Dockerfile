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

FROM alpine:3.22 AS ssh-tunnel
ARG APP_UID=1000
ARG APP_GID=1000

RUN apk add --no-cache autossh openssh-client \
    && addgroup -g "${APP_GID}" scout \
    && adduser -D -u "${APP_UID}" -G scout scout

USER scout
ENTRYPOINT ["autossh"]

FROM base AS production
ARG APP_UID=1000
ARG APP_GID=1000

COPY . .
RUN mkdir -p storage/app/private storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && groupadd --gid "${APP_GID}" scout \
    && useradd --uid "${APP_UID}" --gid "${APP_GID}" --create-home --shell /usr/sbin/nologin scout \
    && chown -R scout:scout storage bootstrap/cache

USER scout
CMD ["php", "artisan", "queue:work", "database", "--queue=surplus-research", "--sleep=3", "--rest=1", "--tries=3", "--timeout=180", "--max-time=3600"]
