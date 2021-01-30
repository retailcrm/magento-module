export ROOT_DIR=$(shell dirname $(realpath $(lastword $(MAKEFILE_LIST))))

VERSION = `cat $(FILE)`
ARCHIVE_NAME = '/tmp/retailcrm-retailcrm-'$(VERSION)'.zip'
MAGE_ROOT=$(shell dirname $(realpath $(lastword $(MAKEFILE_LIST))))/../magento2

.PHONY: build_archive delete_archive

build_archive:
	cd src; zip -r $(ARCHIVE_NAME) ./*

delete_archive:
	rm -f $(ARCHIVE_NAME)

test:
	mkdir coverage

	php $(MAGE_ROOT)/vendor/phpunit/phpunit/phpunit -c $(MAGE_ROOT)/dev/tests/unit/phpunit.xml.dist $(MAGE_ROOT)/app/code/Retailcrm/Retailcrm/Test/Unit

before_script:
	bash bin/install.sh

coverage:
	wget https://phar.phpunit.de/phpcov-2.0.2.phar && php phpcov-2.0.2.phar merge coverage/ --clover coverage.xml