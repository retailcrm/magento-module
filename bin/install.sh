#!/usr/bin/env bash

if [ -z $ROOT_DIR ]; then
	exit 0;
fi

MAGE_ROOT=$ROOT_DIR/../magento2

create_db() {
	mysqladmin create "magento2_test" --user="root" --password="root"
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

	composer install --no-interaction --prefer-dist
	composer require retailcrm/api-client-php
}

magento_install() {
	cd $MAGE_ROOT
	php bin/magento setup:install \
		--db-host="localhost" \
		--db-name="magento2_test" \
		--db-user="root" \
		--db-password="root" \
		--admin-firstname="admin_firstname" \
		--admin-lastname="admin_lastname" \
		--admin-email="example@email.com" \
		--admin-user="admin" \
		--admin-password="admin123" \
		--language="en_US" \
		--currency="USD" \
		--timezone="Europe/Moscow"
}

module_install() {
	cd $MAGE_ROOT
	mkdir -p app/code/Retailcrm/Retailcrm
	cp -R $ROOT_DIR/src/* app/code/Retailcrm/Retailcrm

	php bin/magento module:enable Retailcrm_Retailcrm
	php bin/magento setup:upgrade
	php bin/magento setup:di:compile
}

create_db
magento_clone
magento_install
module_install
