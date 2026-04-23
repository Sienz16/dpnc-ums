FROM php:8.5-fpm-alpine AS app

ENV APP_ENV=production \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0 \
    LOG_CHANNEL=stderr

WORKDIR /var/www/html

RUN apk add --no-cache \
    $PHPIZE_DEPS \
    nginx \
    curl \
    nodejs \
    npm \
    icu-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    oniguruma-dev \
    postgresql-dev \
    unzip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        pcntl \
        pdo_pgsql \
        zip \
    && apk del --no-network $PHPIZE_DEPS \
    && rm -rf /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader \
    --no-scripts

COPY . .

RUN npm ci && npm run build

COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint

RUN chmod +x /usr/local/bin/docker-entrypoint \
    && php artisan package:discover --ansi \
    && mkdir -p storage/framework/{cache,sessions,views} bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/docker-entrypoint"]
