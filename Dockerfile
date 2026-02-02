# MapsProspector Pro - 100% PHP + JavaScript
# Servido com Apache (sem Node/React)

FROM php:8.2-apache

RUN a2enmod rewrite headers

# Extensões PHP necessárias
RUN docker-php-ext-install pdo pdo_mysql

COPY . /var/www/html/

# .htaccess e permissões
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
CMD ["apache2-foreground"]
