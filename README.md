# Live Game Server Status (LGSL)
This package is edited version of LGSL 6.1.1 by tltneon.

[![Latest Version on Packagist](https://img.shields.io/github/v/release/grinjackal/PHP-LGSL?display_name=tag&style=for-the-badge)](https://packagist.org/packages/grinjackal/lgsl)
[![Total Downloads](https://img.shields.io/packagist/dt/grinjackal/lgsl.svg?style=for-the-badge)](https://packagist.org/packages/grinjackal/lgsl)
[![License](https://img.shields.io/github/license/grinjackal/PHP-LGSL?style=for-the-badge)](https://github.com/grinjackal/PHP-LGSL/blob/master/LICENSE)

## Require
```bash
PHP 8.0 or higher
```
Tested on PHP 8.1.

## Installation

```bash
composer require grinjackal/lgsl
```

## Usage
After generated autoload files:

## Example #1
```php
require_once('./vendor/autoload.php');

use GrinJackal\LGSL\LGSL;

$result = LGSL::Query('urbanterror', '176.9.28.206', 27971, 27971, 0, "sep");

echo 'Status: '.($result['basic']['status'] == 1 ? 'ONLINE' : 'OFFLINE').'<br />';
echo 'Name: '.$result['server']['name'].'<br />';
echo 'Map: '.$result['server']['map'] . '<br />';
echo 'Players: '.$result['server']['players'].'/'.$result['server']['playersmax'].'<br />';
```

## Example #2
```php
require_once('./vendor/autoload.php');

use GrinJackal\LGSL\LGSL;

$result = LGSL::Query('discord', 'nDuNTC6', 1, 1, 0, "sep");

echo 'Status: '.($result['basic']['status'] == 1 ? 'ONLINE' : 'OFFLINE').'<br />';
echo 'Name: '.$result['server']['name'].'<br />';
echo 'Map: '.$result['server']['map'].'<br />';
echo 'Players: '.$result['server']['players'].'/'.$result['server']['playersmax'].'<br />';
```

### Security
If you discover any security related issues, please email grinjackal@gmail.com instead of using the issue tracker.

## Credits

-   [Richard Perry](http://www.greycube.com)
-   [Neon](https://github.com/tltneon/lgsl)
-   [Grin Jackal](https://github.com/grinjackal)

## License

The AGPL. Please see [License File](LICENSE) for more information.