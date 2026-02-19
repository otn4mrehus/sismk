FROM php:7.4.33-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN a2enmod rewrite

COPY php.ini /usr/local/etc/php/

RUN echo "date.timezone=Asia/Jakarta" > /usr/local/etc/php/conf.d/timezone.ini
