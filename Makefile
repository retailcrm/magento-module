FILE = $(TRAVIS_BUILD_DIR)/VERSION
VERSION = `cat $(FILE)`
ARCHIVE_NAME = '/tmp/retailcrm-retailcrm-'$(VERSION)'.zip'

all: build_archive send_to_ftp delete_archive

build_archive:
	cd src; zip -r $(ARCHIVE_NAME) ./*

send_to_ftp:
	curl -T $(ARCHIVE_NAME) -u $(FTP_USER):$(FTP_PASSWORD) ftp://$(FTP_HOST)/public_html/

delete_archive:
	rm -f $(ARCHIVE_NAME)
