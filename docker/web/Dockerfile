# FROM php:8.3-apache

# # Install PostgreSQL client libs and build extensions
# RUN apt-get update \
#     && apt-get install -y libpq-dev \
#     && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
#     && docker-php-ext-install \
#         mysqli \
#         pdo_mysql \
#         pdo_pgsql \
#         pgsql

# # Enable Apache mod_rewrite
# RUN a2enmod rewrite

# # Allow .htaccess overrides
# RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# RUN echo '<Directory /var/www/html>' >> /etc/apache2/apache2.conf && \
#     echo '    AllowOverride All' >> /etc/apache2/apache2.conf && \
#     echo '</Directory>' >> /etc/apache2/apache2.conf

# # Permissions
# RUN chown -R www-data:www-data /var/www/html
# RUN chmod -R 755 /var/www/html

# EXPOSE 80







FROM php:8.3-apache

# Install PostgreSQL client libs and extensions
RUN apt-get update \
    && apt-get install -y libpq-dev \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install mysqli pdo_mysql pdo_pgsql pgsql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Allow .htaccess overrides for /var/www/html
RUN echo '<Directory /var/www/html>' >> /etc/apache2/apache2.conf && \
    echo '    Options Indexes FollowSymLinks' >> /etc/apache2/apache2.conf && \
    echo '    AllowOverride All' >> /etc/apache2/apache2.conf && \
    echo '    Require all granted' >> /etc/apache2/apache2.conf && \
    echo '</Directory>' >> /etc/apache2/apache2.conf

# Permissions
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

EXPOSE 80
