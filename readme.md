# Infoblast API library

# Installation

```
composer require hymns/infoblast-api
```

# Usage 
**Sending**


```php
<?php 
use Hymns\Infoblast\OpenAPI;

$response = (new OpenAPI())->send('phone-number', 'your-messages');

var_dump($response);
```

**Get Sending Status**

```php
<?php 
use Hymns\Infoblast\OpenAPI;

$response = (new OpenAPI())->status('messageID', true | false);

var_dump($response);
```

For second parameter, set to *true* for detail sent status. By default value is *false*.

**Pull all message**

```php
<?php 
use Hymns\Infoblast\OpenAPI;

$response = (new OpenAPI())->pull('new', true | false);

var_dump($response);

```
First parameter is optional, set to *all* to get all message, by default value is *new*.
For second parameter value also optional, set to *true* for auto delete after pull, default value is *false*


> Note: Please set INFOBLAST_USERNAME and INFOBLAST_PASSWORD to your server enviroment
