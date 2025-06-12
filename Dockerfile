# Gunakan image resmi PHP 8.2 dengan server Apache
FROM php:8.2-apache

# Install dependensi sistem yang dibutuhkan untuk ekstensi PHP
# Juga install git dan unzip yang dibutuhkan oleh Composer
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install ekstensi-ekstensi PHP yang kita butuhkan
# (pdo, pdo_pgsql untuk koneksi Postgres, intl untuk Carbon/bahasa, zip untuk composer)
RUN docker-php-ext-install pdo pdo_pgsql pgsql intl zip

# Install Composer secara global
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Arahkan Document Root Apache ke folder /public Laravel
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Aktifkan mod_rewrite untuk URL yang cantik (pretty URLs)
RUN a2enmod rewrite

# Copy seluruh kode aplikasi ke dalam image
COPY . /var/www/html

# Install dependensi Laravel via Composer
# Kita pindahkan ke sini agar perubahan kode tidak selalu memicu composer install ulang
WORKDIR /var/www/html
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Atur kepemilikan file agar server bisa menulis ke folder storage dan cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Perintah start tidak perlu didefinisikan, karena image php:apache sudah akan menjalankan Apache secara default.