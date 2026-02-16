#!/usr/bin/env bash
var=${PHP_BIN:-php}
var=${COMPOSER_BIN:-composer}

if [ ! -d "bootstrap/cache" ]; then
  mkdir bootstrap/cache
fi

$COMPOSER_BIN install --no-dev
$PHP_BIN artisan migrate --force


echo
${PHP_BIN} artisan env

echo
echo "Clearing cache"
$PHP_BIN artisan config:cache
$PHP_BIN artisan cache:clear

### Run upgrade scripts
echo
echo "Running upgrade scripts"
$PHP_BIN artisan migrate --force

### Optimize
echo
echo "Caching config & routes"
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache

## Clear opcache
echo
echo "Clearing opcache"
$PHP_BIN artisan opcache:clear
