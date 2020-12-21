#!/usr/bin/env bash

if [ -z $TRAVIS_BUILD_DIR ]; then
	exit 0;
fi

MAGE_ROOT=$TRAVIS_BUILD_DIR/../magento2

create_db() {
	mysqladmin create $DB_NAME --user="$DB_USER" --password="$DB_PASS"
}

magento_clone() {
	cd ..
	git clone https://github.com/magento/magento2
	cd magento2
	git checkout $BRANCH

	touch auth.json
	echo '
      {
         "http-basic": {
            "repo.magento.com": {
               "username": "<public-key>",
               "password": "<private-key>"
            }
         }
      }
	' > auth.json

    sudo sed -e "s?<public-key>?$PUBLIC_KEY?g" --in-place auth.json
    sudo sed -e "s?<private-key>?$PRIVATE_KEY?g" --in-place auth.json

    php -r "copy('https://getcomposer.org/download/1.10.17/composer.phar', 'composer.phar');"

    php composer.phar install --ignore-platform-reqs --no-interaction --prefer-dist
    php composer.phar require retailcrm/api-client-php
}

magento_install() {
	cd $MAGE_ROOT

	php bin/magento setup:install \
		--db-host="$DB_HOST" \
		--db-name="$DB_NAME" \
		--db-user="$DB_USER" \
		--admin-firstname="$ADMIN_FIRSTNAME" \
		--admin-lastname="$ADMIN_LASTNAME" \
		--admin-email="$ADMIN_EMAIL" \
		--admin-user="$ADMIN_USER" \
		--admin-password="$ADMIN_PASS" \
		--language="en_US" \
		--currency="USD" \
		--timezone="Europe/Moscow"
}

module_install() {
	cd $MAGE_ROOT
	mkdir -p app/code/Retailcrm/Retailcrm
	cp -R $TRAVIS_BUILD_DIR/src/* app/code/Retailcrm/Retailcrm

	php bin/magento module:enable Retailcrm_Retailcrm
	php bin/magento setup:upgrade
	php bin/magento setup:di:compile
}

create_db
magento_clone
magento_install
module_install
