#!/bin/sh
set -ev
if [ "$TRAVIS_PHP_VERSION" != "7.1" ]
then
    vendor/bin/phpunit
else
    vendor/bin/phpunit --coverage-clover ./clover.xml
    wget https://scrutinizer-ci.com/ocular.phar
    php ocular.phar code-coverage:upload --format=php-clover ./clover.xml
    phpenv config-rm xdebug.ini || return 0
    vendor/bin/php-cs-fixer --diff --dry-run --verbose fix
fi
