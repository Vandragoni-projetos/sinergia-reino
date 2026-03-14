FROM php:8.2-apache

# Instalar dependências do sistema
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    libzip-dev

# Instalar extensões PHP necessárias
RUN docker-php-ext-install pdo pdo_mysql zip

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Definir diretório da aplicação
WORKDIR /var/www/html

# Copiar arquivos do projeto
COPY . .

# Instalar dependências do Composer automaticamente
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

# Ajustar permissões
RUN chown -R www-data:www-data /var/www/html

# Expor porta do Apache
EXPOSE 80
