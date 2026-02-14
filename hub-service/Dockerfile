# HubService - consumes RabbitMQ, serves APIs
FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libxml2-dev libicu-dev libonig-dev libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql zip bcmath intl mbstring xml opcache sockets \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

EXPOSE 9000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=9000"]
