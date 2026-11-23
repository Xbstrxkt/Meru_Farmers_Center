FROM php:8.2-apache

RUN rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf \
    && sed -i '/LoadModule mpm_event_module/d; /LoadModule mpm_worker_module/d' /etc/apache2/mods-enabled/*.load /etc/apache2/conf-enabled/*.conf 2>/dev/null || true \
    && a2enmod mpm_prefork \
    && docker-php-ext-install mysqli pdo_mysql

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
