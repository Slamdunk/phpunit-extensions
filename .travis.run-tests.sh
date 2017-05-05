#!/bin/sh
set -ev
if [ "$PHP_LATEST_VERSION" = 1 ]
then
    vendor/bin/phpunit --coverage-clover ./clover.xml
    wget https://scrutinizer-ci.com/ocular.phar
    php ocular.phar code-coverage:upload --format=php-clover ./clover.xml
    phpenv config-rm xdebug.ini || return 0
    vendor/bin/php-cs-fixer --diff --dry-run --verbose fix
else
    vendor/bin/phpunit
fi
