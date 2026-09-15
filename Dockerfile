FROM php:8.2-apache

# Install SQLite PDO extension dependencies
RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Set permissions for web server
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/data

# Expose web port
EXPOSE 80

CMD ["apache2-foreground"]
