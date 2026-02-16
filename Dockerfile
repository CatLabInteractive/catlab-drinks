ARG PHP_EXTENSIONS="mysqli pdo_mysql bcmath zip intl gd"
ARG NODE_VERSION=22

FROM thecodingmachine/php:8.1-v5-slim-apache
ENV APACHE_DOCUMENT_ROOT=public/

# Configure locales
USER root
RUN apt-get update && apt-get install -y language-pack-nl && apt-get clean;
USER docker

# Copy the source code in /www into the container at /var/www/html
COPY --chown=docker:docker . /var/www/html

WORKDIR /var/www/html

RUN composer install

RUN npm install
RUN npm run prod
