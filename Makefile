FILE = $(TRAVIS_BUILD_DIR)/VERSION
VERSION = `cat $(FILE)`
ARCHIVE_NAME = '/tmp/retailcrm-retailcrm-'$(VERSION)'.zip'
MAGE_ROOT = $(TRAVIS_BUILD_DIR)/../magento2

all: build_archive send_to_ftp delete_archive

build_archive:
	cd src; zip -r $(ARCHIVE_NAME) ./*

send_to_ftp:
	curl -T $(ARCHIVE_NAME) -u $(FTP_USER):$(FTP_PASSWORD) ftp://$(FTP_HOST)

delete_archive:
	rm -f $(ARCHIVE_NAME)

ci:
ifeq ($(USE_COMPOSER),1)
	php $(MAGE_ROOT)/vendor/phpunit/phpunit/phpunit -c $(MAGE_ROOT)/dev/tests/unit/phpunit.xml.dist $(MAGE_ROOT)/app/code/Retailcrm/Retailcrm/Test/Unit
else
	phpunit -c $(MAGE_ROOT)/dev/tests/unit/phpunit.xml.dist $(MAGE_ROOT)/app/code/Retailcrm/Retailcrm/Test/Unit
endif
