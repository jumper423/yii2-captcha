Yii2 Captcha
================
Распознавание капч для всех популярных сервисов rucaptcha.com, 2captcha.com, pixodrom.com, captcha24.com, socialink.ru, anti-captcha.com

Особенности
------------
* Подходит для всех сервисов по распознаванию капч работающие по общим стандартам
* Лёгкость настроки
* Распозвание как по пути до файла, так и по ссылки

Установка
------------
Предпочтительный способ установить это расширение через [composer](http://getcomposer.org/download/).

Либо запустить

```
php composer.phar require --prefer-dist jumper423/yii2-captcha "*"
```

или добавить

```
"jumper423/yii2-captchar": "*"
```

в файл `composer.json`.

Конфигурация
------------

```php
'components' => [
    'captcha' => [
        'class' => 'jumper423\Captcha',
        'pathTmp' => '@app/captcha',
        'apiKey' => '42eab4119020dbc729f657fef270r546',
    ],
],
```

Использование
------------
Простой пример использования:

```php
$path = 'path/to/captcha.png';
if (\Yii::$app->captcha->run($path)) {
    $captcha = \Yii::$app->captcha->result()
} else {
    throw new Exception(\Yii::$app->captcha->error());
}
```

Так же можно применять если у Вас есть только ссылка на капчу, но для этого метода Вам следует прописать путь в конфигурации для сохранения капч (pathTmp):

```php
$url = 'https://vk.com/captcha.php?sid=698254154192&s=1';
if (\Yii::$app->captcha->run($url)) {
    $captcha = \Yii::$app->captcha->result()
} else {
    throw new Exception(\Yii::$app->captcha->error());
}
```