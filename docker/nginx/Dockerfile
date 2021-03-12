FROM nginx
MAINTAINER Ilyes Abdelrazak <ia.beladel@gmail.com>

# Add user 
#RUN useradd -u 1000 -ms /bin/bash -g nginx nginx

RUN chown -R nginx:nginx /usr/share/nginx/html 
RUN chmod -R 755 /usr/share/nginx/html

# Copy the nginx config file
COPY ./site.conf /etc/nginx/conf.d/

# forward request and error logs to docker log collector
RUN ln -sf /dev/stdout /var/log/nginx/access.log
RUN ln -sf /dev/stderr /var/log/nginx/error.log

EXPOSE 80 443

CMD ["nginx", "-g", "daemon off;"]
