# The library's tests: PHP as the services run it, with what CodeIgniter and MySQL need.
FROM php:8.5-cli
RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip libicu-dev \
 && docker-php-ext-install intl mysqli \
 && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /src
