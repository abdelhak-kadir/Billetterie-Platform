# syntax=docker/dockerfile:1

FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libzip-dev \
    && docker-php-ext-install -j"$(nproc)" intl pdo_mysql zip opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ARG UID=1000
ARG GID=1000

RUN groupadd --gid "${GID}" app \
    && useradd --uid "${UID}" --gid "${GID}" --create-home --shell /bin/bash app \
    && mkdir -p /home/app/.composer/cache \
    && chown -R app:app /home/app/.composer

ENV COMPOSER_HOME=/home/app/.composer

USER app

WORKDIR /app

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/index.php"]
