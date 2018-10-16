<!--
  Title: FIFA 19 WebApp API
  Description: A simply way to manage your FIFA 19 Ultimate Team with a PHP framework..
  Author: Curtis Crewe
  Website: curtiscrewe.co.uk
  -->

FIFA 19 WebApp API
=============

Manage your FIFA 19 Ultimate Team using this FIFA 19 Ultimate Team API.
Written solely in PHP

FIFA 19 Projects
=============

If you require any projects/systems to be developed alongside the FIFA 19 WebApp API then be sure to contact me using one of the methods below.

Skype: <strong>bws-curtis</strong><br/>
Email: <strong>wscrewey@hotmail.com</strong><br/>
Website: <strong>https://curtiscrewe.co.uk</strong>

Installing FUTApi
=======

The recommended way to install FIFA 19 WebApp API is through
[Composer](http://getcomposer.org).

```bash
# Install Composer
curl -sS https://getcomposer.org/installer | php
```

Next, run the Composer command to install the latest stable version of Guzzle:

```bash
composer require inkedcurtis/fut-api
```

After installing, you need to require Composer's autoloader:

```php
require 'vendor/autoload.php';
```

Documentation
=============

Python source provided by: https://github.com/futapi/fut/

Contact
=======

Skype: bws-curtis<br/>
Email: wscrewey@hotmail.com

Usage
=====

Login
-----

Login parameters:

- email: [string] email used for logging into the FIFA 19 WebApp
- password: [string] password used for logging into the FIFA 19 WebApp
- platform: [string] pc/ps4/ps4/xbox/xbox360
- code: [string] email/sms code for two-step verification (make sure to use string if your code starts with 0).
- emulate: [string] currently DISABLED.
- cookies: [string] path to cookies file, if not provided it'll be created in your temp system directory.

```php
use FUTApi\Core;
use FUTApi\FutError;
try {
    $fut = new Core('email', 'password', 'platform', 'backup_code');
} catch(FutError $e) {
    $error = $e->GetOptions();
    die("We have an error logging in: ".$error['reason']);
}
$login = $fut->login();
```

After you have initiated your first session, you can then use the API wthout logging in again using the session info from your original login array:

```php
use FUTApi\Core;
use FUTApi\FutError;
$fut = new Core('email', 'password', 'platform', 'backup_code');
$fut->setSession($persona, $nucleus, $phishing, $session, $dob);
```

    
Search
------

Optional parameters:

- min_price: [int] Minimal price.
- max_price: [int] Maximum price.
- min_buy: [int] Minimal buy now price.
- max_buy: [int] Maximum buy now price.
- level: ['bronze'/'silver'/gold'] Card level.
- start: [int] Start page number.
- category: ['fitness'/'?'] Card category.
- assetId: [int] assetId.
- defId: [int] defId.
- league: [int] League id.
- club: [int] Club id.
- position: [int?/str?] Position.
- zone: ['attacker'/'?'] zone.
- nationality: [int] Nation id.
- rare: [boolean] True for searching special cards.
- playStyle: [str?] playStyle.
- page_size: [int] Amount of cards on single page (changing this might be risky).

```php
$items = $fut->searchAuctions('player');
```
    
Logout
------

Replicates clicking the Logout button.

    $fut->logout();


License
-------

GNU GPLv3
