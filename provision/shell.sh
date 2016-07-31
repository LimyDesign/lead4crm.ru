#!/usr/bin/env bash

# Variables
PGSQL_USER=vagrant
PGSQL_PASSWORD=p@s$w0rd
PGSQL_DB=vagrant

PROJECT_ROOT=/var/www/lead4crm
LOG=$PROJECT_ROOT/provision/provision.log

# include log functions
. `dirname $0`/log.lib.sh

# LOG HEADER
h1 "PROVISION AT `date`"

h2 "Configure a PPA"
lec apt-get install -y language-pack-en-base
lec LC_ALL=en_US.UTF-8 add-apt-repository ppa:ondrej/php

h2 "Update apt-get"
lec apt-get update

h2 "Install autoremove"
lec apt-get -y autoremove

h2 "Install PHP 7.0"
lec apt-get -y php7.0-cli php7.0-pgsql php7.0-fpm php7.0-dom

h2 "Install Nginx"
lec add-apt-repository -y ppa:nginx/stable
lec apt-get update
lec apt-get -y install nginx-full

h2 "Install PosgreSQL"
lec apt-get -y install postgresql postgresql-contrib
sudo -i -u postgres
createuser -s $PGSQL_USER
createdb $PGSQL_DB


h2 "Install Ruby"
lec apt-get -y install ruby ruby-dev

h2 "Install Composer"
lec php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
lec php -r "if (hash_file('SHA384', 'composer-setup.php') == 'e115a8dc7871f15d853148a7fbac7da27d6c0030b848d9b3dc09e2a0388afed865e6a3d6b3c0fad45c48e2b5fc1196ae') { echo 'Installer verified' . PHP_EOL; } else { unlink('composer-setup.php'); echo 'Installer corrupt' . PHP_EOL; exit(1); }"
lec php composer-setup.php --install-dir=/usr/local/bin --filename=composer

h2 "Update Composer"
lec composer self-update

h2 "Install compass"
lec gem update
lec gem install compass

h2 "Change user and group for php-fpm"
lec sed -i 's/www\-data/vagrant/g' /etc/php/7.0/fpm/pool.d/www.conf

h2 "Change main settins on Nginx"
lec sed -i 's/www\-data/vagrant vagrant/g' /etc/nginx/nginx.conf
lec sed -i 's/http {/http \{\n\tproxy_cache_path \/var\/www\/lead4crm\/var\/nginx levels=1:2 keys_zone=lead4crm:10m max_size=10g inactive=60m use_temp_path=off;\n/g' /etc/nginx/nginx.conf

h2 "Link nginx config into nginx sites-available dir"
lec ln -s $PROJECT_ROOT/provision/lead4crm.dev.conf /etc/nginx/site-available/

h2 'Link config into "enabled" dir'
lec ln -s /etc/nginx/site-available/lead4crm.dev.conf /etc/nginx/site-enabled/

h2 "Link config with Nginx Cache"
lec ln -s $PROJECT_ROOT/provision/nginx_cache.conf /etc/nginx/conf.d/

h2 "Reloading nginx"
lec nginx -s reload

h2 "Restore privileges"
lec chown -R vagrant:vagrant $PROJECT_ROOT