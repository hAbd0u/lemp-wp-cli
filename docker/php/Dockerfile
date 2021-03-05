# https://github.com/adhocore/docker-phpfpm/tree/7.4
FROM adhocore/phpfpm:7.4
MAINTAINER Ilyes Abdelrazak <ia.beladel@gmail.com>

RUN docker-php-ext-disable \
	ds hrtime igbinary imap \
	ldap lua lzf mongodb msgpack oauth pcov pdo_pgsql \
	pdo_sqlite pgsql phalcon redis reflection soap \
	sqlite3 swoole swoole_async sysvmsg sysvsem sysvshm \
	tideways_xhprof tidy xlswriter yaf yaml zephir_parser xdebug

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Add user 
#RUN groupadd -g 1000 www
#RUN useradd -u 1000 -ms /bin/bash -g www www

# Change current user to www
#USER www

# Expose port 9000 and start php-fpm server
EXPOSE 9000
CMD ["php-fpm", "-F"]
