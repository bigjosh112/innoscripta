# HR Service - Laravel app (PostgreSQL + RabbitMQ)
# PHP 8.4 to match composer.lock (Symfony 8.x / Carbon 3.11 require 8.4)
FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    libxml2-dev \
    libicu-dev \
    libonig-dev \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        zip \
        bcmath \
        intl \
        mbstring \
        xml \
        opcache \
        sockets \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy app only; vendor is installed at container startup (see docker-compose command)
COPY . .

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
