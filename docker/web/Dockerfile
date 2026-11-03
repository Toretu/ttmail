FROM php:8.3-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends libzip-dev \
 && rm -rf /var/lib/apt/lists/* \
 && docker-php-ext-install pdo_mysql zip \
 && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY apache.conf /etc/apache2/conf-available/mailings.conf
RUN a2enconf mailings \
 && sed -i 's#/var/www/html#/var/www/html/public#g' /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
