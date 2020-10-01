FROM php:7.0-fpm

# Copy composer.lock and composer.json
COPY composer.lock composer.json /var/www/

# Set working directory
WORKDIR /var/www

# Install dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    zlib1g-dev \
    libsasl2-dev \
    libssl-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libpng-dev \
    libxpm-dev \
    libvpx-dev \
    libxml2-dev

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install extensions
RUN docker-php-ext-configure gd --with-gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ --with-png-dir=/usr/include/
RUN docker-php-ext-install pdo_mysql mbstring zip calendar soap gd

# Install mongodb extension
RUN pecl install mongodb-1.4.4 \
    && echo "extension=mongodb.so" > /usr/local/etc/php/conf.d/mongo.ini

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Add user for laravel application
RUN groupadd -g 1000 www
RUN useradd -u 1000 -ms /bin/bash -g www www

# Copy existing application directory contents
# COPY . /var/www

# Copy existing application directory permissions
# COPY --chown=www:www . /var/www

# Change current user to www
USER www

# Expose port 9000 and start php-fpm server
EXPOSE 9000
CMD ["php-fpm"]