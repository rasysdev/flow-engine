# syntax=docker/dockerfile:1

FROM composer:2.9.5 AS deps
WORKDIR /app

COPY composer.json ./
RUN composer config platform.php 8.3.31 \
    && composer install --no-dev --prefer-dist --no-interaction --no-progress \
    && composer config --unset platform.php

COPY bin/ bin/
COPY src/ src/
COPY schema/ schema/

RUN composer dump-autoload --no-dev --optimize

FROM php:8.3.31-cli
WORKDIR /flow-engine

# Store cache/events/sessions outside the analyzed project so `/workspace` can be mounted read-only.
ENV FLOW_ENGINE_STATE_DIR=/state
RUN mkdir -p /state

COPY --from=deps /app /flow-engine

ENTRYPOINT ["php", "bin/engine.php"]
