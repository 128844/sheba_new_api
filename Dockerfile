FROM php:7.0-fpm

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
RUN docker-php-ext-install -j$(nproc) pdo_mysql mbstring zip calendar soap gd intl

# Install mongodb extension
RUN pecl install mongodb-1.4.4 \
    && echo "extension=mongodb.so" > /usr/local/etc/php/conf.d/mongo.ini

# Install php-ext-apfd
RUN pecl install apfd && docker-php-ext-enable apfd

# Make fpm log script executable
COPY fpm_log_file_created.sh /etc/fpm_log_file_created.sh
RUN chmod +x /etc/fpm_log_file_created.sh
RUN /etc/fpm_log_file_created.sh

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer --version=1.8.6
RUN composer global require hirak/prestissimo

COPY entrypoint.sh /etc/entrypoint.sh
RUN chmod +x /etc/entrypoint.sh

RUN usermod -u 1000 www-data

# Set working directory
WORKDIR /var/www

EXPOSE 80 443
