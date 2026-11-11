FROM composer:2 AS composer-bin

FROM php:8.4-apache-bookworm AS runtime
RUN apt-get update && apt-get install -y --no-install-recommends libicu-dev libonig-dev libzip-dev unzip git \
    && docker-php-ext-install intl mbstring mysqli zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer
ENV APACHE_DOCUMENT_ROOT=/var/www/educenter/backend/public
RUN sed -ri 's!/var/www/html!/var/www/educenter/backend/public!g' /etc/apache2/sites-available/*.conf \
    && printf '<Directory /var/www/educenter/backend/public>\nAllowOverride All\nRequire all granted\n</Directory>\nServerName localhost\n' > /etc/apache2/conf-available/educenter.conf \
    && a2enconf educenter
WORKDIR /var/www/educenter/backend
COPY config/php.ini /usr/local/etc/php/conf.d/educenter.ini
COPY config/apache.conf /etc/apache2/conf-available/educenter-server.conf
RUN a2enconf educenter-server

FROM runtime AS application
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader
COPY backend/ ./
COPY shared/ /var/www/educenter/shared/
COPY frontend/src/ /var/www/educenter/frontend/src/
RUN composer dump-autoload --no-dev --optimize \
    && mkdir -p writable/cache writable/logs writable/session writable/uploads \
    && chown -R www-data:www-data writable
EXPOSE 80
