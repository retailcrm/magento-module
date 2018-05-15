#!/usr/bin/env bash

if [ -z $TRAVIS_BUILD_DIR ]; then
	exit 0;
fi

MAGE_ROOT=$TRAVIS_BUILD_DIR/../magento2
cd $MAGE_ROOT
php vendor/phpunit/phpunit/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Retailcrm/Retailcrm/Test/Unit
