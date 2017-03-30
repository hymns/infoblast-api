# Infoblast API library

# Installation

```
composer require hymns/infoblast-api
```

# Usage 
**Sending**


```php
<?php 

require 'vendor/autoload.php';

use Infoblast\OpenAPI;

$status = OpenAPI::send('phone-number', 'your-messages');

```

**Get Sending Status**

```php
<?php 

require 'vendor/autoload.php';

use Infoblast\OpenAPI;

$status = OpenAPI::getStatus('messageID', true | false);

```

For second parameter, set to *true* for detail sent status. By default value is *false*.

** Pull all message **

```php
<?php 

require 'vendor/autoload.php';

use Infoblast\OpenAPI;

$status = OpenAPI::pull('new', true | false);

```
First parameter is optional, set to *all* to get all message, by default value is *new*.
For second parameter value also optional, set to *true* for auto delete after pull, default value is *false*


> P/S: Please set INFOBLAST_USERNAME and INFOBLAST_PASSWORD to your server enviroment