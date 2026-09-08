FROM php:8.3-apache

# mysqli isn't compiled in by default on the base image — Db.php needs it.
RUN docker-php-ext-install mysqli

# Let per-project .htaccess rules take effect (they block /.env, /schema.sql,
# /Messenger/, /storage/, internal classes, ...) and make sure mod_rewrite is
# available for anything the app adds later.
RUN a2enmod rewrite \
 && { \
        echo '<Directory /var/www/html>'; \
        echo '    AllowOverride All'; \
    echo '</Directory>'; \
    } > /etc/apache2/conf-available/docker-allow-override.conf \
 && a2enconf docker-allow-override

# Attachments are written OUTSIDE the webroot (/var/www/storage). Create the
# directory up front and hand it to www-data (the user Apache/php runs as),
# otherwise mkdir()/move_uploaded_file() fail with permission denied.
RUN mkdir -p /var/www/storage/attachments \
 && chown -R www-data:www-data /var/www/storage

# Apache serves whatever's in the webroot; webhook URLs will be
# http://<host>/telegram_webhook.php and http://<host>/bale_webhook.php
COPY . /var/www/html/
