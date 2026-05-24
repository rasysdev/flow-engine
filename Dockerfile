# syntax=docker/dockerfile:1

FROM composer:2 AS deps
WORKDIR /app

COPY composer.json ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress

COPY bin/ bin/
COPY src/ src/
COPY schema/ schema/

RUN composer dump-autoload --no-dev --optimize

FROM php:8.2-cli
WORKDIR /flow-engine

# Store cache/events/sessions outside the analyzed project so `/workspace` can be mounted read-only.
ENV FLOW_ENGINE_STATE_DIR=/state
RUN mkdir -p /state

COPY --from=deps /app /flow-engine

ENTRYPOINT ["php", "bin/engine.php"]
