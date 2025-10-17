FROM php:8.4-cli-alpine

WORKDIR /app

# Install required PHP extensions
RUN apk add --no-cache \
    linux-headers \
    $PHPIZE_DEPS \
    && docker-php-ext-install sockets \
    && apk del $PHPIZE_DEPS

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy composer files
COPY composer.json composer.lock* ./

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy source code
COPY . .

# Container stays alive for dependency resolution
CMD ["tail", "-f", "/dev/null"]
