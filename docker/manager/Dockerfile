# As of 2021-05-06, underlying alpine 3.13 arm image throws ssl verify error on Raspberry Pi
# https://raspberrypi.stackexchange.com/questions/123675/certificate-problems-on-docker-image-for-rpi-alpine
#FROM php:7.4-cli-alpine
FROM php:7.4-cli-alpine3.12

RUN apk update \
    && apk upgrade --available \
    && apk add --virtual build-deps \
    docker

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY bin /app/bin
COPY src /app/src
COPY resources /app/resources
COPY composer.json /app/composer.json
COPY composer.lock /app/composer.lock

WORKDIR /app

RUN composer install --prefer-dist --no-dev --no-scripts --no-progress; \
    composer clear-cache

RUN set -eux; \
    composer dump-autoload --classmap-authoritative --no-dev; \
    chmod +x bin/manager

RUN mkdir /tmp/manager

ENTRYPOINT ["/app/bin/manager"]
