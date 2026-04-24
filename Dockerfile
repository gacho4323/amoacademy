FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    nginx \
    zip unzip git curl libpng-dev libjpeg-dev libfreetype6-dev \
    default-mysql-client \
    build-essential autoconf pkg-config \
    redis-tools \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql bcmath \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY . /var/www/html

#COPY ./storage/app/upc /var/www/html/storage_default/app/upc
COPY ./storage /var/www/html/storage_default

RUN git clone --depth 1 https://github.com/phpredis/phpredis.git /tmp/phpredis \
    && cd /tmp/phpredis \
    && phpize \
    && ./configure \
    && make \
    && make install \
    && docker-php-ext-enable redis \
    && rm -rf /tmp/phpredis

RUN composer install --no-dev --optimize-autoloader && \
    chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

RUN rm /etc/nginx/sites-enabled/default
COPY amoacademy.conf /etc/nginx/conf.d/

COPY docker-entrypoint.sh /usr/local/bin/
COPY ./custom-php.ini /usr/local/etc/php/conf.d/custom-php.ini
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]

EXPOSE 80

CMD service nginx start && php-fpm
