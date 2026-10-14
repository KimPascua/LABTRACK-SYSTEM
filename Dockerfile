
FROM php:8.3-apache


RUN a2dismod mpm_event mpm_worker mpm_prefork 2>/dev/null || true \
    && rm -f /etc/apache2/mods-enabled/mpm_event.load \
              /etc/apache2/mods-enabled/mpm_event.conf \
              /etc/apache2/mods-enabled/mpm_worker.load \
              /etc/apache2/mods-enabled/mpm_worker.conf \
              /etc/apache2/mods-enabled/mpm_prefork.load \
              /etc/apache2/mods-enabled/mpm_prefork.conf \
    && a2enmod mpm_prefork \
    && apache2ctl -M


RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libicu-dev libonig-dev libxml2-dev libzip-dev \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev libcurl4-openssl-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
       bcmath exif gd intl mbstring opcache pcntl pdo_mysql xml zip curl \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY --chown=www-data:www-data . /var/www/html

RUN mkdir -p storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && if [ -d public/storage ] && [ ! -L public/storage ]; then cp -an public/storage/. storage/app/public/ && rm -rf public/storage; fi \
    && if [ ! -e public/storage ] && [ ! -L public/storage ]; then ln -s ../storage/app/public public/storage; fi \
    && composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 80


CMD ["sh", "-lc", "rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf && a2enmod mpm_prefork && exec apache2-foreground"]
