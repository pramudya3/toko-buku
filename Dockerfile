# ---------- Stage 1: Binary Composer ----------
FROM composer:2 AS composer-src

# ---------- Stage 1b: Wayfinder generator (PHP + vendor) ----------
# Wayfinder files gitignored (resources/js/{routes,actions,wayfinder}) dan
# stage assets (node) tidak punya PHP — jadi generate di stage ini supaya
# build self-contained: tidak butuh script luar / file pra-generate.
FROM php:8.5-cli AS wayfinder
WORKDIR /app
# zip+xml dibutuhkan composer install (phpspreadsheet dll) — tanpanya
# composer tidak bisa extract paket ("zip extension and unzip/7z missing").
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev libxml2-dev unzip \
    && docker-php-ext-install -j"$(nproc)" zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*
COPY --from=composer-src /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
COPY . .
RUN composer install --no-dev --ignore-platform-reqs --no-interaction --prefer-dist --no-progress --optimize-autoloader \
    && php artisan wayfinder:generate --with-form

# ---------- Stage 2: Frontend assets ----------
FROM node:22-alpine AS assets
ENV WAYFINDER_SKIP=1
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
# Artefak wayfinder dari stage 1b (gitignored, tidak ada di COPY . .
# saat clone fresh) — WAYFINDER_SKIP=1 membuat plugin Vite memakai
# file ini tanpa mencoba generate (tidak ada PHP di stage ini).
COPY --from=wayfinder /app/resources/js/routes ./resources/js/routes
COPY --from=wayfinder /app/resources/js/actions ./resources/js/actions
COPY --from=wayfinder /app/resources/js/wayfinder ./resources/js/wayfinder
RUN npm run build

# ---------- Stage 3: Runtime (tanpa nginx — proxy di host) ----------
FROM php:8.5-cli AS runtime

# Ekstensi PHP: pgsql (DB), gd (intervention/image), zip+xml (phpspreadsheet), opcache
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev libzip-dev libpng-dev libjpeg62-turbo-dev libwebp-dev \
        libfreetype6-dev libxml2-dev libonig-dev curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql mbstring zip gd exif \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Opcache production
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html

# Dependensi PHP (runtime hanya; platform checks lolos karena ekstensi sudah ada)
COPY --from=composer-src /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress --no-scripts --optimize-autoloader

# Aplikasi
COPY . .

# Asset hasil build (public/build di-dockerignore, di-copy dari stage assets)
COPY --from=assets /app/public/build ./public/build

# Bersihkan artefak yang tidak diperlukan di runtime
RUN rm -rf node_modules tests \
    && rm -f bootstrap/cache/*.php \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

USER www-data

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
    CMD curl -fsS http://localhost:8000/ > /dev/null || exit 1

# package:discover dijalankan saat start (composer --no-scripts di atas);
# mkdir dir framework & log — tangguh terhadap volume storage kosong.
CMD ["sh", "-c", "mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs && php artisan package:discover --ansi && php -d memory_limit=512M artisan serve --host=0.0.0.0 --port=8000"]
