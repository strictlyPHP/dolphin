#!/bin/bash
set -ex
DIRNAME=$(/usr/bin/dirname $0)
DIR=$(/bin/bash -c "cd $DIRNAME/..; /bin/pwd")

cd $DIR

rm -fr ~/var/cache/test/*
rm -fr ~/var/cache/prod/*

XDEBUG_MODE=coverage ./vendor/bin/phpunit tests/ --coverage-php=/tmp/coverage.cov --coverage-html=/tmp/coverage.html
XDEBUG_MODE=coverage ./vendor/bin/phpcov patch-coverage --path-prefix /usr/src/myapp/ /tmp/coverage.cov ./diff.txt | php ./build/check-coverage.php