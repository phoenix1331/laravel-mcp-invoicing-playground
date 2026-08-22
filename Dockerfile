FROM dunglas/frankenphp:php8.4

RUN install-php-extensions \
    pdo_mysql \
    mysqli \
    redis \
    zip \
    intl \
    opcache \
    gd

WORKDIR /app

COPY . /app

ENV SERVER_NAME=:8000
