FROM php:8.4-apache-bookworm
RUN echo "session.upload_progress.enabled=0" > /usr/local/etc/php/conf.d/security.ini

RUN apt-get update && apt-get install -y --no-install-recommends \
    libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    libonig-dev unzip git \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j"$(nproc)" pdo_mysql mysqli intl mbstring gd zip bcmath opcache \
  && a2enmod rewrite headers expires \
  && rm -rf /var/lib/apt/lists/*

RUN printf 'variables_order = "EGPCS"\n' > /usr/local/etc/php/conf.d/zz-amea-env.ini

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader
COPY . .
COPY docker/apache-security.conf /etc/apache2/conf-available/amea-security.conf
RUN a2enconf amea-security \
  && mkdir -p uploads storage/mail-queue storage/cache logs \
  && chown -R www-data:www-data uploads storage logs \
  && chmod -R o-rwx uploads storage logs

EXPOSE 80
