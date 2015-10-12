# WebIcqPro
![](https://img.shields.io/packagist/v/arsen-bespalov/webicqpro.svg)
![](https://img.shields.io/packagist/dm/arsen-bespalov/webicqpro.svg)
![](https://img.shields.io/packagist/l/arsen-bespalov/webicqpro.svg)

Неофициальный ICQ API с которым можно многое. Например создать даже бота, не говоря уже о рассылке информативных сообщений.

## Установка
Скачайте сам пакет:
```
composer require arsen-bespalov/webicqpro
```

Подгрузите и инициализируйте класс:
```php
define('UIN', '881129');
define('PASSWORD', '*************');

require_once __DIR__.'/vendor/autoload.php';

$icq = new WebIcqPro();
$icq->connect(UIN, PASSWORD)
```

## Документация
Подробная документация по всем свойствам и методам в Вики разделе:  
<https://github.com/ArsenBespalov/WebIcqPro/wiki>
