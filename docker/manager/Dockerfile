FROM php:7.4-cli-alpine

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

RUN composer install --prefer-dist --no-dev --no-scripts --no-progress --no-suggest; \
    composer clear-cache

RUN set -eux; \
    composer dump-autoload --classmap-authoritative --no-dev; \
    chmod +x bin/manager

RUN mkdir /tmp/manager

ENTRYPOINT ["/app/bin/manager"]
