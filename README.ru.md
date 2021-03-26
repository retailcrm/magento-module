DEPRECATED Magento module

**Модуль устарел и больше не поддерживается**

Модуль Magento 2 для интеграции с [RetailCRM](http://www.retailcrm.ru) ([Документация](https://docs.retailcrm.ru/Users/Integration/SiteModules/Magento))

Модуль позволяет:

* Производить обмен заказами с RetailCRM
* Настроить соответствие справочников RetailCRM и Magento (статусы, оплаты, типы доставки и т.д.)
* Создать [ICML](http://www.retailcrm.ru/docs/Developers/ICML) (Intaro Markup Language) для загрузки каталога товаров в RetailCRM

## ICML

По умолчанию ICML-файл генерируется модулем каждые 4 часа. Вы можете найти файл в корневой папке с именем «retailcrm_{{shop_code}}.xml". Например, http://example.ru/retailcrm_default.xml

## Ручная установка

1) Распакуйте архив с модулем в директорию "app/code/Retailcrm/Retailcrm". В файле "app/etc/config.php" в массив `modules` добавьте элемент `'Retailcrm_Retailcrm' => 1`

2) Выполните в папке проекта:

```bash
composer require retailcrm/api-client-php ~5.0
```

В конфигурационный файл `composer.json` вашего проекта будет добавлена библиотека [retailcrm/api-client-php](https://github.com/retailcrm/api-client-php), которая будет установлена в директорию `vendor/`.


Этот модуль совместим с Magento 2 до версии 2.2.8
