# Instagram PHP API

A simple PHP wrapper for the Instagram API. Based on the original [Instagram-PHP-API](https://github.com/cosenary/Instagram-PHP-API) by [Christian Metz](http://metzweb.net)

[![Latest Stable Version](http://img.shields.io/packagist/v/espresso-dev/instagram-php.svg?style=flat)](https://packagist.org/packages/espresso-dev/instagram-php)
[![License](https://img.shields.io/packagist/l/espresso-dev/instagram-php.svg?style=flat)](https://packagist.org/packages/espresso-dev/instagram-php)
[![Total Downloads](http://img.shields.io/packagist/dt/espresso-dev/instagram-php.svg?style=flat)](https://packagist.org/packages/espresso-dev/instagram-php)

> [Composer](#installation) package available.

## Requirements

- PHP 5.6 or higher
- cURL
- Facebook Developer Account
- Facebook App

## Get started

To use the Instagram API, you will need to register a Facebook app and configure Instagram Basic Display. Follow the [getting started guide](https://developers.facebook.com/docs/instagram-basic-display-api/getting-started).

### Installation

I strongly advise using [Composer](https://getcomposer.org) to keep updates as smooth as possible.

```bash
$ composer require espresso-dev/instagram-php
```

### Initialize the class

```php
use EspressoDev\Instagram\Instagram;

$instagram = new Instagram([
    'appId' => 'YOUR_APP_ID',
    'appSecret' => 'YOUR_APP_SECRET',
    'redirectUri' => 'YOUR_APP_REDIRECT_URI'
]);

echo "<a href='{$instagram->getLoginUrl()}'>Login with Instagram</a>";
```

### Authenticate user (OAuth2)

```php
// Get the OAuth callback code
$code = $GET['code'];

// Get the short lived access token (valid for 1 hour)
$token = $instagram->getOAuthToken($code, true);

// Exchange this token for a long lived token (valid for 60 days)
$token = $instagram->getLongLivedToken($token, true);

echo 'Your token is: ' . $token;
```

### Get user profile

```php
// Set user access token
$instagram->setAccessToken($token);
// Get the users profile
$profile = $instagram->getUserProfile();

echo '<pre>';
print_r($profile);
echo '</pre>';
```

### Get user media

```php
// Set user access token
$instagram->setAccessToken($token);
// Get the users media
$media = $instagram->getUserMedia();

echo '<pre>';
print_r($media);
echo '</pre>';
```
