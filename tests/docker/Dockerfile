# The library's tests: PHP as the services run it, with what CodeIgniter and MySQL need.
FROM php:8.5-cli
RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip libicu-dev \
 && docker-php-ext-install intl mysqli \
 && rm -rf /var/lib/apt/lists/*
# PHP 8.5.11's PCRE JIT segfaults on arm64 (a Mac) in the realistic suite, after some thousand
# includes of tens of thousands of rows; on amd64, and with the JIT off, it passes.
RUN echo 'pcre.jit=0' > /usr/local/etc/php/conf.d/pcre.ini
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /src
