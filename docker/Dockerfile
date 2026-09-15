FROM php:8.3-fpm-alpine

# Instalar dependencias del sistema y extensiones PHP necesarias para Symfony
RUN apk add --no-cache \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    icu-dev \
    mariadb-client \
    linux-headers \
    $PHPIZE_DEPS

# Configurar e instalar extensiones PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        intl \
        pdo_mysql \
        zip \
        gd \
        opcache

# Instalar e instalar Xdebug (esencial para desarrollo en dev)
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Copiar configuración de desarrollo de PHP y Xdebug
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
RUN echo "xdebug.mode=debug" >> $PHP_INI_DIR/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> $PHP_INI_DIR/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> $PHP_INI_DIR/conf.d/docker-php-ext-xdebug.ini

# Instalar Composer globalmente
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/nurschool
