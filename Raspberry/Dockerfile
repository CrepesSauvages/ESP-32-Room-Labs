FROM php:8.1-apache

RUN docker-php-ext-install pdo pdo_mysql mysqli

RUN a2enmod rewrite

RUN echo '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/override.conf

RUN a2enconf override

RUN echo 'log_errors = On' >> /usr/local/etc/php/conf.d/docker-php-ext-logging.ini
RUN echo 'error_log = /var/log/apache2/php_errors.log' >> /usr/local/etc/php/conf.d/docker-php-ext-logging.ini

RUN mkdir -p /var/log/apache2 && chown www-data:www-data /var/log/apache2

COPY src/ /var/www/html/

RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]