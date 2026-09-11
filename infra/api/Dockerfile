# Laravel API 映像
# 基底：serversideup/php（Nginx + PHP-FPM，Laravel 友善，內建 healthcheck 與 autorun）
# 兩個 target：
#   dev  ：不複製程式碼，由 compose bind mount 掛入，方便熱改
#   prod ：多階段建置，composer install --no-dev，複製程式碼進映像

ARG PHP_VERSION=8.4

# ---------- 共用基底：加裝 Phase 1 需要的擴充 ----------
FROM serversideup/php:${PHP_VERSION}-fpm-nginx-alpine AS base

USER root
# pdo_pgsql：Postgres；redis：queue/cache；imagick：縮圖與浮水印；intl/bcmath：Laravel 常用
RUN install-php-extensions pdo_pgsql redis imagick intl bcmath exif gd

# Nginx 監聯 8080（映像預設），Caddy 反向代理到這裡
ENV NGINX_WEBROOT=/var/www/html/public \
    PHP_MEMORY_LIMIT=256M \
    PHP_UPLOAD_MAX_FILE_SIZE=25M \
    PHP_POST_MAX_SIZE=25M \
    SSL_MODE=off

USER www-data

# ---------- dev ----------
FROM base AS dev
# 程式碼由 compose 掛入 /var/www/html；這裡不做任何複製。
# Windows 的 bind mount 在容器內固定為 root:root 0755 且無法 chown，
# 所以 storage/ 與 bootstrap/cache 改由 compose 掛 named volume；
# 這裡先建好目錄並交給 www-data，volume 初始化時會沿用這個擁有者。
USER root
RUN mkdir -p /var/www/html/storage/app/public \
             /var/www/html/storage/framework/cache/data \
             /var/www/html/storage/framework/sessions \
             /var/www/html/storage/framework/testing \
             /var/www/html/storage/framework/views \
             /var/www/html/storage/logs \
             /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html
USER www-data
ENV PHP_OPCACHE_ENABLE=0

# ---------- prod：先在 composer 階段裝依賴 ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

FROM base AS prod
COPY --chown=www-data:www-data --from=vendor /app /var/www/html
ENV PHP_OPCACHE_ENABLE=1 \
    AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_MIGRATION=true \
    AUTORUN_LARAVEL_CONFIG_CACHE=true \
    AUTORUN_LARAVEL_ROUTE_CACHE=true \
    AUTORUN_LARAVEL_VIEW_CACHE=true
