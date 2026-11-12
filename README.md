# Class Skilled System

## Local setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Set `ADMIN_NAME`, `ADMIN_EMAIL` and `ADMIN_PASSWORD` in `.env`, create the MySQL database `class_skilled_system`, then:

```bash
php artisan migrate --seed
npm run build
```

## Checks

```bash
php artisan test
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
```
