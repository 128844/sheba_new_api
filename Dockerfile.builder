FROM php:7.0-fpm AS api-base-php-fpm-70

ARG APP_ENV

ENV PHP_OPCACHE_VALIDATE_TIMESTAMPS="0" \
    PHP_OPCACHE_MAX_ACCELERATED_FILES="10000" \
    PHP_OPCACHE_MEMORY_CONSUMPTION="192" \
    PHP_OPCACHE_MAX_WASTED_PERCENTAGE="10"

# PHP_CPPFLAGS are used by the docker-php-ext-* scripts
ENV PHP_CPPFLAGS="$PHP_CPPFLAGS -std=c++11"

# Install dependencies
RUN apt-get update && apt-get install -y \
    nginx \
    libpng-dev \
    zlib1g-dev \
    libsasl2-dev \
    libssl-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libpng-dev \
    libxpm-dev \
    libvpx-dev \
    libxml2-dev \
    libicu-dev \
    git

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install extensions
RUN docker-php-ext-configure gd --with-gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ --with-png-dir=/usr/include/
RUN docker-php-ext-configure intl
RUN docker-php-ext-install -j$(nproc) pdo_mysql mbstring zip calendar soap gd intl opcache

# Install mongodb extension
RUN pecl install mongodb-1.4.4 \
    && echo "extension=mongodb.so" > /usr/local/etc/php/conf.d/mongo.ini

# Install php-ext-apfd
RUN pecl install apfd && docker-php-ext-enable apfd

# Install sentry
RUN curl -sL https://sentry.io/get-cli/ | bash

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer --version=1.8.6
RUN composer global require hirak/prestissimo