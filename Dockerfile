ARG PHP_VERSION=8.1
FROM php:${PHP_VERSION}-apache

# --- System packages needed to build common PHP extensions ---
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    && rm -rf /var/lib/apt/lists/*

# --- PHP extensions CodeIgniter 3 / typical apps need ---
RUN docker-php-ext-install \
    mysqli \
    pdo \
    pdo_mysql \
    mbstring \
    zip \
    gd \
    xml \
    opcache

# --- Enable Apache mod_rewrite (CodeIgniter routing) ---
RUN a2enmod rewrite

# --- Composer (grabbed from the official composer image, not installed via curl) ---
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# --- Apache: let CodeIgniter's .htaccess overrides work ---
RUN sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

WORKDIR /var/www/html

# Composer needs a non-root-safe dir when running as root inside the container
RUN composer config -g allow-plugins true 2>/dev/null || true

EXPOSE 80
