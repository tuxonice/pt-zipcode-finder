FROM php:8.3-cli

# Install dependencies
RUN apt-get update && apt-get install -y \
    unzip \
    wget \
    libzip-dev \
    sqlite3 \
    libsqlite3-dev \
    && docker-php-ext-install zip


# Install PHP extensions
RUN docker-php-ext-install zip pdo pdo_sqlite

# Install the latest Composer manually
RUN EXPECTED_SIGNATURE="$(wget -q -O - https://composer.github.io/installer.sig)" && \
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    ACTUAL_SIGNATURE="$(php -r "echo hash_file('sha384', 'composer-setup.php');")" && \
    if [ "$EXPECTED_SIGNATURE" != "$ACTUAL_SIGNATURE" ]; then >&2 echo 'Invalid installer signature'; exit 1; fi && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
    rm composer-setup.php


# Set working directory
WORKDIR /app

# Copy composer files
COPY composer.json composer.lock* ./

# Install dependencies including dev dependencies (PHPUnit)
RUN composer install

# Copy the rest of the application
COPY . .

# Make sure vendor/bin is in PATH
ENV PATH="/app/vendor/bin:${PATH}"

# Generate autoloader
RUN composer dump-autoload --optimize