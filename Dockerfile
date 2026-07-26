FROM php:8.2-apache

# Postgres driver for PDO (this app talks to Neon via pdo_pgsql).
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite headers

# The official Apache image ships with AllowOverride None for /var/www/,
# which silently ignores backend/.htaccess (the Authorization-header
# rewrite rule in particular). Turn overrides on for the docroot.
RUN printf '<Directory /var/www/html>\n  AllowOverride All\n</Directory>\n' > /etc/apache2/conf-available/allow-override.conf \
    && a2enconf allow-override

# Frontend at webroot, backend/ as a child folder — same layout the app
# already expects (frontend/extend.html's '../backend' and index.html's
# 'backend' relative paths both keep working unchanged).
COPY frontend/index.html /var/www/html/index.html
COPY frontend/extend.html /var/www/html/extend.html
COPY backend/ /var/www/html/backend/

# Don't ship the InfinityFree-only DB-credential installer or the raw
# MySQL schema files inside the running container (they're not used by
# this Postgres/Render path and only add attack surface).
RUN rm -f /var/www/html/backend/install.php \
          /var/www/html/backend/schema.sql \
          /var/www/html/backend/migrate_add_signup.sql

RUN chown -R www-data:www-data /var/www/html

# Render injects PORT at runtime and expects the container to listen on
# it — Apache's default config hardcodes 80, so rewrite it at startup.
RUN printf '#!/bin/sh\nset -e\nPORT="${PORT:-80}"\nsed -ri "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf\nsed -ri "s/:80>/:${PORT}>/g" /etc/apache2/sites-available/000-default.conf\nexec apache2-foreground\n' > /usr/local/bin/start-apache.sh \
    && chmod +x /usr/local/bin/start-apache.sh

EXPOSE 80
CMD ["/usr/local/bin/start-apache.sh"]
