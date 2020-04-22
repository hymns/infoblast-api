# Infoblast API library

# Installation

```
composer require hymns/infoblast-api
```

# Usage 
**Sending**


```php
<?php 
use hymns\infoblast\OpenAPI;

$status = OpenAPI::send('phone-number', 'your-messages');

var_dump($status);
```

**Get Sending Status**

```php
<?php 
use hymns\infoblast\OpenAPI;

$status = OpenAPI::status('messageID', true | false);

var_dump($status);
```

For second parameter, set to *true* for detail sent status. By default value is *false*.

**Pull all message**

```php
<?php 
use hymns\infoblast\OpenAPI;

$message = OpenAPI::pull('new', true | false);

var_dump($message);

```
First parameter is optional, set to *all* to get all message, by default value is *new*.
For second parameter value also optional, set to *true* for auto delete after pull, default value is *false*


> P/S: Please set INFOBLAST_USERNAME and INFOBLAST_PASSWORD to your server enviroment
