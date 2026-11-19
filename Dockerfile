# SPARTA deployment image: nginx + PHP-FPM on Alpine Linux.
# Alpine v3.24 ships PHP 8.5.12-r0, the newest release in that branch and the
# first with the php2shell fixes (CVE-2026-109618 / CVE-2026-109619).
# The interpreter is pinned so a rebuild cannot silently roll it; bump
# PHP_APK_VERSION when Alpine publishes a newer patch release.
FROM alpine:3.24

ARG PHP_APK_VERSION=8.5.12-r0

RUN apk upgrade --no-cache \
    && apk add --no-cache \
        nginx \
        php85=${PHP_APK_VERSION} \
        php85-fpm=${PHP_APK_VERSION} \
        php85-ctype=${PHP_APK_VERSION} \
        php85-fileinfo=${PHP_APK_VERSION} \
        php85-mbstring=${PHP_APK_VERSION} \
        php85-openssl=${PHP_APK_VERSION} \
        php85-pdo=${PHP_APK_VERSION} \
        php85-pdo_mysql=${PHP_APK_VERSION} \
        php85-session=${PHP_APK_VERSION} \
    && ln -sf /usr/bin/php85 /usr/bin/php

WORKDIR /var/www/html

# Application source (public/ is the web root).
COPY . /var/www/html

# Runtime configuration.
RUN mkdir -p /run/nginx /run/php /var/lib/php/sessions \
    && cp docker/nginx/default.conf /etc/nginx/http.d/default.conf \
    && cp docker/php/php.ini /etc/php85/conf.d/99-sparta.ini \
    && cp docker/php/www.conf /etc/php85/php-fpm.d/www.conf \
    && cp docker/entrypoint.sh /entrypoint.sh \
    && chmod +x /entrypoint.sh \
    && mkdir -p public/uploads/alat \
    && chown -R nobody:nobody public/uploads/alat /var/lib/php/sessions

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
