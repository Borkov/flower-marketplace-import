FROM php:8.2-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libzip-dev \
    && docker-php-ext-install pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

RUN docker-php-ext-enable opcache

RUN echo "opcache.enable=1\nopcache.enable_cli=1\nopcache.memory_consumption=256\nopcache.max_accelerated_files=10000\nopcache.revalidate_freq=0\nopcache.validate_timestamps=1\nrealpath_cache_size=4096K\nrealpath_cache_ttl=3600" > /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /app

# Сначала только манифесты — слой с зависимостями кэшируется, пока composer.lock не менялся
COPY backend/composer.json backend/composer.lock /app/
RUN composer install --no-interaction --prefer-dist --no-scripts

# Код приложения (vendor не попадает — он уже собран на предыдущем шаге)
COPY backend /app

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
