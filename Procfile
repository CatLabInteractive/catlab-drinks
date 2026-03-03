web: bash -c 'if command -v heroku-php-apache2 &> /dev/null; then heroku-php-apache2 public/; else apache2-foreground; fi'
release: php artisan migrate --force

