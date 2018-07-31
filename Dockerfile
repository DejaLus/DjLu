FROM php:5.6

ADD . / /djlu/
CMD php -S 0.0.0.0:8080 -t /djlu
