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
server_tag(string $url, int $cache_seconds = 0, ?callable $render_script = null): string
```

**Parameters:**
- `$url` (string): The remote URL to fetch scripts from
- `$cache_seconds` (int, optional): Cache duration in seconds. Default is 0 (no caching)
- `$render_script` (callable, optional): Custom rendering function. Receives an array of script URLs and returns an HTML string

## How It Works

1. The Twig function fetches content from the specified remote URL using Symfony's HTTP client
2. If caching is enabled, the response is stored in Symfony's cache for the specified duration
3. The fetched scripts are rendered as `<script>` tags (or using your custom renderer)
4. The HTML is returned with proper XSS protection and can be used directly in your Twig templates

## License

ISC

## Support

- Issues: [GitHub Issues](https://github.com/adunblock/adunblock-server-tag/issues)
- Homepage: [https://github.com/adunblock/adunblock-server-tag](https://github.com/adunblock/adunblock-server-tag)

## Author

AdUnblock - [support@ad-unblock.com](mailto:support@ad-unblock.com)