FROM php:8.1-apache-bookworm

LABEL org.opencontainers.image.title="cacti-web" \
      org.opencontainers.image.description="Cacti Web + Poller (Apache, PHP 8.1, RRDtool, SNMP)" \
      org.opencontainers.image.version="1.2.31"

ENV DEBIAN_FRONTEND=noninteractive \
    TZ=Asia/Jakarta

# Install system dependencies
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        cron \
        supervisor \
        rrdtool \
        librrd-dev \
        snmp \
        snmpd \
        libsnmp-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libxml2-dev \
        libicu-dev \
        libgmp-dev \
        libldap2-dev \
        libsasl2-dev \
        libonig-dev \
        libzip-dev \
        unzip \
        mariadb-client \
        iputils-ping \
        procps \
        nano \
        ca-certificates \
        apt-utils; \
    rm -rf /var/lib/apt/lists/*

# Configure and install PHP extensions
RUN set -eux; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu; \
    docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mysqli \
        mbstring \
        xml \
        gd \
        intl \
        gmp \
        ldap \
        snmp \
        sockets \
        pcntl \
        posix \
        zip \
        bcmath \
        opcache; \
    pecl install rrd; \
    docker-php-ext-enable rrd

# Enable Apache modules
RUN set -eux; \
    a2enmod rewrite; \
    a2enmod headers; \
    a2enmod expires

# Set PHP timezone
RUN set -eux; \
    echo "date.timezone = \${TZ}" > /usr/local/etc/php/conf.d/timezone.ini; \
    echo "memory_limit = 512M" > /usr/local/etc/php/conf.d/memory.ini; \
    echo "max_execution_time = 120" > /usr/local/etc/php/conf.d/execution_time.ini; \
    echo "max_input_time = 120" > /usr/local/etc/php/conf.d/input_time.ini; \
    echo "upload_max_filesize = 64M" > /usr/local/etc/php/conf.d/upload.ini; \
    echo "post_max_size = 64M" > /usr/local/etc/php/conf.d/post.ini

# Copy Cacti source code
WORKDIR /var/www/html
COPY . /var/www/html/

# Remove unnecessary files from the image
RUN set -eux; \
    rm -rf /var/www/html/.git \
        /var/www/html/.gitignore \
        /var/www/html/.github \
        /var/www/html/.gitignore.BACKUP \
        /var/www/html/.htaccess.dist \
        /var/www/html/tests \
        /var/www/html/cache/boost/* \
        /var/www/html/cache/mibcache/* \
        /var/www/html/cache/realtime/* \
        /var/www/html/cache/spikekill/* \
        /var/www/html/composer.lock \
        /var/www/html/CHANGELOG

# Create required directories with proper permissions
RUN set -eux; \
    mkdir -p /var/www/html/log; \
    mkdir -p /var/www/html/rra; \
    mkdir -p /var/www/html/cache/boost; \
    mkdir -p /var/www/html/cache/mibcache; \
    mkdir -p /var/www/html/cache/realtime; \
    mkdir -p /var/www/html/cache/spikekill; \
    chown -R www-data:www-data /var/www/html/log; \
    chown -R www-data:www-data /var/www/html/rra; \
    chown -R www-data:www-data /var/www/html/cache; \
    chmod -R 755 /var/www/html/log; \
    chmod -R 755 /var/www/html/rra; \
    chmod -R 755 /var/www/html/cache

# Apache configuration
COPY docker/apache-cacti.conf /etc/apache2/sites-available/000-default.conf

# Supervisor configuration
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Crontab for poller
COPY docker/crontab /etc/cron.d/cacti
RUN chmod 0644 /etc/cron.d/cacti; \
    crontab /etc/cron.d/cacti

# Entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

VOLUME ["/var/www/html/rra", "/var/lib/mysql-files"]

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
