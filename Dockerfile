FROM ubuntu:22.04

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
        php8.2-mbstring

COPY --from=composer /usr/bin/composer /usr/bin/composer