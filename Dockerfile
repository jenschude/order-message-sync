FROM jaysde/php-test-base

WORKDIR /app
ADD . /app

CMD php bin/console.php order:message-sync
