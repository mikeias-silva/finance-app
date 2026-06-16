FROM php:8.5-fpm-alpine

LABEL description="PHP-FPM 8.5 para Laravel — Finance Manager"

# install-php-extensions resolve automaticamente os pacotes Alpine
# corretos para cada extensão, independente da versão do Alpine
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN chmod +x /usr/local/bin/install-php-extensions \
 && install-php-extensions \
        pdo_pgsql \
        gd \
        zip \
        intl \
        bcmath \
        opcache \
        pcntl \
 && apk add --no-cache bash git curl unzip \
 && rm -rf /var/cache/apk/*

# Composer 2
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Usuário não-root
RUN addgroup -g 1000 -S app \
 && adduser  -u 1000 -S app -G app \
 && sed -i \
        -e 's/user = www-data/user = app/' \
        -e 's/group = www-data/group = app/' \
        /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html
RUN chown app:app /var/www/html

USER app
EXPOSE 9000
CMD ["php-fpm"]