FROM php:8.2-apache

#Update apt
RUN apt-get update -y

#Install necessary program
RUN apt-get -y install \
    screen \
    git \
    vim \
    zip

#Install necessary libs
RUN apt-get -y install \
    tcpdump \
    zlib1g-dev \
    libwebp-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libxpm-dev \
    libfreetype6-dev \
    libicu-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    libonig-dev \
    librabbitmq-dev \
    libssh-dev \
    libsodium-dev \
    zlib1g-dev \
    libzip-dev \
    g++ \
    procps

#Configure GD
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j$(nproc) gd \
  && docker-php-source delete

RUN docker-php-ext-install opcache

#Install and configure other php extensions
RUN docker-php-ext-configure intl
RUN docker-php-ext-install intl \
    bcmath \
    xml \
    curl \
    mbstring \
    pcntl \
    sodium \
    zip

RUN pecl install amqp apcu igbinary xdebug \
    && docker-php-ext-enable amqp \
    xdebug \
    apcu \
    igbinary \
    zip \
    sodium

# Update packages list
RUN apt-get install -y wget

# Install wget
RUN apt-get update

RUN docker-php-ext-install mysqli
