FROM php:8-apache

# enable mod rewrite
RUN a2enmod rewrite headers

# install gd + wget + advpng
RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    wget \
    advancecomp \
    pngcrush \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j$(nproc) gd \
  && rm -rf /var/lib/apt/lists/*

# download pngout (http://www.jonof.id.au/kenutils.html)
RUN wget http://static.jonof.id.au/dl/kenutils/pngout-20200115-linux.tar.gz \
  && tar -xf pngout-20200115-linux.tar.gz \
  && rm pngout-20200115-linux.tar.gz \
  && cp pngout-20200115-linux/amd64/pngout /bin/pngout \
  && rm -rf pngout-20200115-linux

RUN mkdir -p /tmp/icons/ && chown www-data /tmp/icons \
  && mkdir -p /var/www/html/cache \
  && chown -R www-data /var/www/html/cache

COPY src/.htaccess /var/www/html/.htaccess
COPY src/icon.php /var/www/html/icon.php
