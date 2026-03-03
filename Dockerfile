FROM php:8.1-apache

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    locales \
    libzip-dev \
    libicu-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip unzip git curl \
    && locale-gen nl_NL.UTF-8 \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install mysqli pdo_mysql bcmath zip intl gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite and mod_headers
RUN a2enmod rewrite headers

# Set document root to /var/www/html/public
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|g' /etc/apache2/sites-available/000-default.conf \
    && sed -i 's|<Directory /var/www/>|<Directory /var/www/html/public/>|g' /etc/apache2/apache2.conf \
    && echo '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>' \
       >> /etc/apache2/sites-available/000-default.conf

# Install Node.js 22
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy source code
COPY --chown=www-data:www-data . /var/www/html

WORKDIR /var/www/html

RUN composer install --no-interaction --prefer-dist --optimize-autoloader

RUN npm install
RUN npm run prod

# Run as www-data (no sudo needed)
USER www-data

