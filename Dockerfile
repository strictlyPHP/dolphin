FROM ubuntu:20.04

RUN apt-get update && \
    DEBIAN_FRONTEND=noninteractive apt-get install -y \
      software-properties-common

RUN add-apt-repository --remove ppa:vikoadi/ppa
RUN add-apt-repository -y ppa:ondrej/php

RUN apt-get update && \
    DEBIAN_FRONTEND=noninteractive apt-get install -y \
        git \
        unzip \
        php8.0-cli \
        php8.0-intl \
        php8.0-xdebug \
        php8.0-bcmath \
        php8.0-xml \
        curl \
        php8.0-curl \
        php8.0-mbstring

COPY --from=composer /usr/bin/composer /usr/bin/composer