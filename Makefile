FILE = $(TRAVIS_BUILD_DIR)/VERSION
VERSION = `cat $(FILE)`
ARCHIVE_NAME = '/tmp/retailcrm-retailcrm-'$(VERSION)'.zip'
MAGE_ROOT = $(TRAVIS_BUILD_DIR)/../magento2

.PHONY: build_archive delete_archive

build_archive:
	cd src; zip -r $(ARCHIVE_NAME) ./*

delete_archive:
	rm -f $(ARCHIVE_NAME)

ci:
	php $(MAGE_ROOT)/vendor/phpunit/phpunit/phpunit -c $(MAGE_ROOT)/dev/tests/unit/phpunit.xml.dist $(MAGE_ROOT)/app/code/Retailcrm/Retailcrm/Test/Unit
