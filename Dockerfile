FROM php:8.2-apache

# Install MySQL extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache rewrite module
RUN a2enmod rewrite

# Copy project files from digital-legacy-vault into Apache webroot
COPY digital-legacy-vault/ /var/www/html/

# Create uploads directory and set permissions
RUN mkdir -p /var/www/html/uploads && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 775 /var/www/html/uploads

# Copy entrypoint startup script
COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

ENV PORT=80
EXPOSE 80

CMD ["/usr/local/bin/start.sh"]
