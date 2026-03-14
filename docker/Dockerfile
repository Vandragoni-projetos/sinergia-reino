# SinergIA Core - Imagem da aplicação PHP
FROM php:8.2-apache

# Extensões PHP necessárias
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    curl \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_mysql \
    mbstring \
    zip \
    exif \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Habilitar mod_rewrite
RUN a2enmod rewrite

# PHP: timezone e limites de upload
RUN echo "date.timezone = America/Sao_Paulo" >> /usr/local/etc/php/conf.d/timezone.ini \
    && echo "upload_max_filesize = 100M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 100M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "display_errors = Off" >> /usr/local/etc/php/conf.d/production.ini \
    && echo "log_errors = On" >> /usr/local/etc/php/conf.d/production.ini

# Virtual host Apache (DocumentRoot = raiz do projeto)
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Cópia da aplicação (contexto = raiz do projeto)
WORKDIR /var/www/html
COPY . .

# Instalar dependências do Composer no build
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

# Script de entrypoint: garante vendor/ se volume sobrescrever (ex.: Easypanel)
RUN echo '#!/bin/bash' > /entrypoint-app.sh \
    && echo 'set -e' >> /entrypoint-app.sh \
    && echo 'if [ ! -f /var/www/html/vendor/autoload.php ]; then' >> /entrypoint-app.sh \
    && echo '  echo "[SinergIA] vendor/ não encontrado. Executando composer install..."' >> /entrypoint-app.sh \
    && echo '  cd /var/www/html && composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader' >> /entrypoint-app.sh \
    && echo '  chown -R www-data:www-data /var/www/html/vendor 2>/dev/null || true' >> /entrypoint-app.sh \
    && echo '  echo "[SinergIA] composer install concluído."' >> /entrypoint-app.sh \
    && echo 'fi' >> /entrypoint-app.sh \
    && echo 'exec "$@"' >> /entrypoint-app.sh \
    && chmod +x /entrypoint-app.sh

# Permissões: aplicação e uploads gravável pelo www-data
RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/uploads

ENTRYPOINT ["/entrypoint-app.sh"]
CMD ["apache2-foreground"]

EXPOSE 80
