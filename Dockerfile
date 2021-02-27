FROM php:8 AS builder
RUN apt-get update \
 && apt-get install -y git \
 && rm -rf /var/lib/apt/lists/*
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
COPY app /var/www
WORKDIR /var/www
RUN /usr/local/bin/composer update

FROM php:8-apache
RUN a2enmod rewrite
RUN sed -i 's!/var/www/html!/var/www/public!g' /etc/apache2/sites-available/000-default.conf \
 && mv /var/www/html /var/www/public
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY --from=builder /var/www /var/www
WORKDIR /var/www
EXPOSE 80/tcp