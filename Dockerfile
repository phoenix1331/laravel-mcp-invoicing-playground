FROM dunglas/frankenphp:php8.4

RUN install-php-extensions \
    pdo_mysql \
    pdo_sqlite \
    mysqli \
    redis \
    zip \
    intl \
    opcache \
    gd

RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . /app

ENV SERVER_NAME=:8000
