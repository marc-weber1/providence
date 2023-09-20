FROM ubuntu:22.04

RUN apt update
RUN apt install -y software-properties-common
RUN add-apt-repository -y ppa:ondrej/apache2
RUN add-apt-repository -y ppa:ondrej/php
RUN apt update
RUN apt install -y apache2
RUN DEBIAN_FRONTEND=noninteractive apt install -y --no-install-recommends php7.4 libapache2-mod-php7.4 php7.4-mbstring php7.4-xmlrpc php7.4-gd php7.4-xml php7.4-intl php7.4-mysql php7.4-cli php7.4-zip php7.4-curl php7.4-posix php7.4-dev php7.4-redis php7.4-gmagick php7.4-gmp

RUN apt clean

WORKDIR /var/www/html
RUN rm *
COPY . .

EXPOSE 80
CMD ln -sf /proc/1/fd/1 /var/log/apache2/access.log \
    && ln -sf /proc/1/fd/2 /var/log/apache2/error.log \
    && apachectl -D FOREGROUND