Yii2 cloudshop api client
===================================

https://web.cloudshop.ru/


Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist skeeks/yii2-cloudshop-api-client "*"
```

or add

```
"skeeks/yii2-cloudshop-api-client": "*"
```


Configure your application
----------

```php
//App config
[
    'components'    =>
    [
    //....
        'cloudshopApiClient' =>
        [
            'class'         => 'skeeks\yii2\cloudshopApiClient\CloudshopApiClient',
            'email'         => '',
            'password'      => '',
        ],
    //....
    ]
]

```
How to use
----------

```php
\Yii::$app->cloudshopApiClient->getProfileApiMethod();
\Yii::$app->cloudshopApiClient->getStoresApiMethod();
\Yii::$app->cloudshopApiClient->getAccountsApiMethod();
\Yii::$app->cloudshopApiClient->getCatalogApiMethod();
```

___

> [![skeeks!](https://skeeks.com/img/logo/logo-no-title-80px.png)](https://skeeks.com)  
<i>SkeekS CMS (Yii2) — fast, simple, effective!</i>  
[skeeks.com](https://skeeks.com) | [cms.skeeks.com](https://cms.skeeks.com)

