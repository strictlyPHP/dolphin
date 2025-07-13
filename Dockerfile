FROM ubuntu:24.04

RUN apt-get update && \
    DEBIAN_FRONTEND=noninteractive apt-get install -y \
      software-properties-common

RUN add-apt-repository -y ppa:ondrej/php

RUN apt-get update && \
    DEBIAN_FRONTEND=noninteractive apt-get install -y \
        git \
        unzip \
        php8.2-cli \
        php8.2-intl \
        php8.2-xdebug \
        php8.2-bcmath \
        php8.2-xml \
        curl \
        php8.2-curl \
        php8.2-mbstring \
        php8.2-mysql \
        php8.2-pgsql \
        php8.2-sqlite3

COPY --from=composer /usr/bin/composer /usr/bin/composer