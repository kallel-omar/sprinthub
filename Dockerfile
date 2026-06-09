FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libxml2-dev \
    default-mysql-client \
    && docker-php-ext-install pdo pdo_mysql zip intl

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

ENV APP_ENV=prod
ENV APP_DEBUG=0

RUN composer install --no-dev --optimize-autoloader

RUN a2enmod rewrite

RUN printf '%s\n' \
'<VirtualHost *:80>' \
'    DocumentRoot /var/www/html/public' \
'    <Directory /var/www/html/public>' \
'        AllowOverride All' \
'        Require all granted' \
'        FallbackResource /index.php' \
'    </Directory>' \
'    ErrorLog ${APACHE_LOG_DIR}/error.log' \
'    CustomLog ${APACHE_LOG_DIR}/access.log combined' \
'</VirtualHost>' \
> /etc/apache2/sites-available/000-default.conf

EXPOSE 80

CMD ["apache2-foreground"]