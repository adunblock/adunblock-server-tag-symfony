# Server Tag Symfony Bundle

A Symfony bundle to fetch and render scripts from a remote URL with Twig integration and caching support. Perfect for server-side script loading and integration with third-party services.

## Features

- **Twig Integration**: Easy-to-use Twig function for templates
- **HTTP Client**: Uses Symfony's HTTP client for reliable requests
- **Caching**: Built-in caching support with configurable TTL
- **Error Handling**: Graceful error handling with fallback to empty arrays
- **Security**: XSS protection with proper HTML escaping
- **Bundle Structure**: Proper Symfony bundle with dependency injection
- **Wide Compatibility**: Support for Symfony 5, 6, 7, and 8

## Requirements

- PHP 7.4 or higher
- Symfony 5.x, 6.x, 7.x, or 8.x
- Twig 2.x or 3.x

## Installation

Install the bundle via Composer:

```bash
composer require adunblock/server-tag-symfony:^1.0
```

Enable the bundle in your `config/bundles.php`:

```php
<?php

return [
    // ... other bundles
    AdUnblock\ServerTag\Symfony\AdunblockServerTagBundle::class => ['all' => true],
];
```

## Usage

### Basic Usage

In your Twig template, use the `server_tag()` function to fetch and render scripts from a remote URL:

```twig
<!DOCTYPE html>
<html>
<head>
  <title>My Page</title>
  {{ server_tag('https://your-remote-url.com/scripts') }}
</head>
<body>
  <h1>My Page</h1>
</body>
</html>
```

### With Caching

Specify a cache duration (in seconds) as the second parameter:

```twig
{{ server_tag('https://your-remote-url.com/scripts', 300) }}
```

This will cache the fetched scripts for 5 minutes (300 seconds), reducing external requests.

### Script Attributes

Pass a map as the fourth argument, or by name, to add attributes to the tag. Your account ID goes in `data-code`, which our dashboard looks for to verify the site:

```twig
{{ server_tag('https://public.adunblocker.com/api/vendor_scripts', 300, null, {'data-code': 'ACCOUNT_ID'}) }}
{# or #}
{{ server_tag('https://public.adunblocker.com/api/vendor_scripts', scriptAttributes={'data-code': 'ACCOUNT_ID'}) }}
```

Rendering rules: `true` → bare attribute, `false` / `null` → omitted, any other value → `key="value"` (HTML-escaped).

### The tag it prints

By default `server_tag()` prints one tag, for the first URL in the response (the preferred one):

```html
<script data-cfasync="false" nowprocket src="SCRIPT_URL" async data-code="ACCOUNT_ID"></script>
```

- `data-cfasync="false"` and `nowprocket` come **before** `src`: Cloudflare Rocket Loader skips a script only when `data-cfasync="false"` precedes `src`, and `nowprocket` keeps WP Rocket's Delay JavaScript Execution off it. Pass either as `false` to drop it.
- Your attributes come after `src` and `async` (`'async': false` drops `async`).
- Only the first URL is rendered: the client must load once per page, since each copy runs its own adblock detection and asks for its own proxy session. A custom renderer still receives every URL.

### The site's domain

`server_tag()` sends your site's domain to the sources endpoint as `?domain=`, taken from the current request's host (through the `request_stack` service), or from the `domain` argument: `{{ server_tag(url, domain='example.com') }}`. When an ad blocker rule targets one site, we publish a new script host for that site alone, and only a request naming the site receives it. A `domain` parameter already in your URL is kept as it is.

### Custom Rendering

You can provide a custom callable as the third parameter to control how script tags are rendered:

```twig
{{ server_tag('https://your-remote-url.com/scripts', 300, custom_renderer) }}
```

Create a custom renderer service in your Symfony application:

```php
namespace App\Service;

class CustomScriptRenderer
{
    public function __invoke(array $scripts): string
    {
        $scriptTags = array_map(function ($src) {
            return sprintf('<script src="%s" defer></script>', htmlspecialchars($src, ENT_QUOTES, 'UTF-8'));
        }, $scripts);

        return implode("\n", $scriptTags);
    }
}
```

### Function Signature

```php
server_tag(string $remoteUrl, int $cacheInterval = 300, ?callable $renderScript = null, ?array $scriptAttributes = null, ?string $domain = null): string
```

**Parameters:**
- `$remoteUrl` (string): The remote URL to fetch scripts from
- `$cacheInterval` (int, optional): Cache duration in seconds. Default is 300
- `$renderScript` (callable, optional): Custom rendering function. Receives an array of script URLs and returns an HTML string
- `$scriptAttributes` (array, optional): Attributes to render on the `<script>` tag (e.g. `{'data-code': 'ACCOUNT_ID'}`), after `src` and `async`
- `$domain` (string, optional): Your site's domain, sent as `?domain=`. Defaults to the current request's host

## How It Works

1. The Twig function fetches content from the specified remote URL using Symfony's HTTP client
2. If caching is enabled, the response is stored in Symfony's cache for the specified duration
3. The first script URL is rendered as a `<script>` tag (or every URL is passed to your custom renderer)
4. The HTML is returned with proper XSS protection and can be used directly in your Twig templates

## Development

```bash
composer install
composer test
```

## License

ISC

## Support

- Issues: [GitHub Issues](https://github.com/adunblock/adunblock-server-tag/issues)
- Homepage: [https://github.com/adunblock/adunblock-server-tag](https://github.com/adunblock/adunblock-server-tag)

## Author

AdUnblock - [support@ad-unblock.com](mailto:support@ad-unblock.com)