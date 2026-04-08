FROM php:8.2-apache
RUN docker-php-ext-install pdo pdo_mysql
RUN a2enmod rewrite
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf
RUN echo '<Directory /var/www/html>\n\
    AllowOverride All\n\
</Directory>' >> /etc/apache2/apache2.conf
RUN sed -i 's/80/8080/g' /etc/apache2/ports.conf
RUN sed -i 's/:80/:8080/g' /etc/apache2/sites-available/000-default.conf
RUN printf "upload_max_filesize=25M\npost_max_size=25M\nmax_file_uploads=20\n" > /usr/local/etc/php/conf.d/uploads.ini
COPY . /var/www/html/
RUN mkdir -p /var/www/html/public/uploads/bai_nop \
    /var/www/html/public/uploads/bai_tap \
    /var/www/html/public/uploads/tai_lieu \
    && chown -R www-data:www-data /var/www/html/public/uploads \
    && chmod -R 775 /var/www/html/public/uploads

EXPOSE 8080