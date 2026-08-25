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

RUN apt-get update \
    && apt-get install -y chromium \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN groupadd --gid 1000 app \
    && useradd --uid 1000 --gid app --shell /bin/sh --create-home app \
    && mkdir -p /data /config \
    && chown -R app:app /data /config

WORKDIR /app

COPY --chown=app:app . /app

USER app

ENV SERVER_NAME="http://localhost:8000"
