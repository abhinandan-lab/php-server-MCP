FROM php:8.3-apache

# Installing or enabling PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Ensure Apache allows .htaccess overrides for specific directories
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Ensure that .htaccess directives are allowed for the web root directory
RUN echo '<Directory /var/www/html>' >> /etc/apache2/apache2.conf && \
    echo '    AllowOverride All' >> /etc/apache2/apache2.conf && \
    echo '</Directory>' >> /etc/apache2/apache2.conf

# Set correct permissions for the web server user
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

# Expose the container port
EXPOSE 80
