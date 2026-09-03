# ============================================================
# Stage 1: Install PHP dependencies with Composer
# ============================================================
FROM composer:2 AS composer

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction \
    --no-scripts


# ============================================================
# Stage 2: Production Laravel application
# ============================================================
FROM php:8.3-fpm-bookworm

ENV APP_ENV=production
ENV APP_DEBUG=false

WORKDIR /var/www/html


# ============================================================
# Install system dependencies
# ============================================================
RUN apt-get update && apt-get install -y \
    nginx \
    supervisor \
    curl \
    git \
    unzip \
    libpq-dev \
    libicu-dev \
    libzip-dev \
    libonig-dev \
    && docker-php-ext-install \
        pdo_pgsql \
        mbstring \
        bcmath \
        intl \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*


# ============================================================
# PHP production configuration
# ============================================================
RUN cp /usr/local/etc/php/php.ini-production \
    /usr/local/etc/php/php.ini

RUN { \
    echo "opcache.enable=1"; \
    echo "opcache.validate_timestamps=0"; \
    echo "opcache.memory_consumption=128"; \
    echo "opcache.interned_strings_buffer=16"; \
    echo "opcache.max_accelerated_files=20000"; \
} > /usr/local/etc/php/conf.d/opcache.ini


# ============================================================
# Copy Laravel application
# ============================================================
COPY . .

COPY --from=composer /app/vendor ./vendor

# Verify Laravel entry point exists
RUN test -f /var/www/html/public/index.php || \
    (echo "ERROR: Laravel public/index.php is missing!" && exit 1)



# ============================================================
# Laravel permissions
# ============================================================
RUN mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data \
        storage \
        bootstrap/cache \
    && chmod -R 775 \
        storage \
        bootstrap/cache


# ============================================================
# Nginx configuration
# ============================================================
RUN rm -f /etc/nginx/sites-enabled/default

RUN cat > /etc/nginx/conf.d/default.conf <<'EOF'
server {
    listen 0.0.0.0:8080;

    server_name _;

    root /var/www/html/public;

    index index.php index.html;

    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    location ~ \.php$ {
        include fastcgi_params;
    
        fastcgi_pass 127.0.0.1:9000;
    
        fastcgi_param SCRIPT_FILENAME /var/www/html/public$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT /var/www/html/public;
    
        fastcgi_param HTTP_PROXY "";
    
        fastcgi_read_timeout 60;
    }


    location ~ /\. {
        deny all;
    }
}
EOF


# ============================================================
# Supervisor configuration
# ============================================================
RUN cat > /etc/supervisor/conf.d/supervisord.conf <<'EOF'
[supervisord]
nodaemon=true
user=root
logfile=/dev/null
pidfile=/run/supervisord.pid

[program:php-fpm]
command=/usr/local/sbin/php-fpm --nodaemonize
priority=10
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:nginx]
command=/usr/sbin/nginx -g "daemon off;"
priority=20
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
EOF


# ============================================================
# Render startup script
# ============================================================
RUN cat > /usr/local/bin/start-render.sh <<'EOF'
#!/bin/sh

set -e

# Render provides PORT at runtime.
# Use 8080 locally if PORT is not provided.
PORT="${PORT:-8080}"

echo "Starting Laravel application on port ${PORT}"

# Change Nginx from 8080 to Render's PORT
sed -i \
    "s/listen 0.0.0.0:8080;/listen 0.0.0.0:${PORT};/" \
    /etc/nginx/conf.d/default.conf

# Verify Nginx configuration
nginx -t

# Start Supervisor
exec /usr/bin/supervisord \
    -c /etc/supervisor/conf.d/supervisord.conf
EOF

RUN chmod +x /usr/local/bin/start-render.sh


# ============================================================
# Expose default HTTP port
# ============================================================
EXPOSE 8080


# ============================================================
# Start application
# ============================================================
CMD ["/usr/local/bin/start-render.sh"]
