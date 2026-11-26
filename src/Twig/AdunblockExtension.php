<?php

namespace AdUnblock\ServerTag\Symfony\Twig;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AdunblockExtension extends AbstractExtension
{
    /**
     * Printed ahead of src, with their default values. Cloudflare Rocket
     * Loader honours data-cfasync="false" only when it comes before src;
     * nowprocket keeps WP Rocket's Delay JavaScript Execution off the tag.
     * A caller can override either, or drop it with false.
     */
    private const LEADING_ATTRIBUTES = ['data-cfasync' => 'false', 'nowprocket' => true];

    private $httpClient;
    private $cache;
    private $requestStack;

    public function __construct(HttpClientInterface $httpClient, CacheItemPoolInterface $cache, ?RequestStack $requestStack = null)
    {
        $this->httpClient = $httpClient;
        $this->cache = $cache;
        $this->requestStack = $requestStack;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('server_tag', [$this, 'serverTag'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Renders the AdUnblock script tag.
     *
     * The default output is one tag, for the first URL (the preferred one):
     * <script data-cfasync="false" nowprocket src="URL" async ...$scriptAttributes></script>
     * The client must load once per page, since each copy runs its own adblock
     * detection and asks for its own proxy session. $renderScript still
     * receives every URL.
     *
     * $domain is sent as ?domain= and defaults to the current request's host.
     */
    public function serverTag(
        string $remoteUrl,
        int $cacheInterval = 300,
        ?callable $renderScript = null,
        ?array $scriptAttributes = null,
        ?string $domain = null
    ): string {
        $remoteUrl = self::withDomain($remoteUrl, $domain !== null ? $domain : $this->requestHost());
        $cacheItem = $this->cache->getItem(md5($remoteUrl));

        if (!$cacheItem->isHit()) {
            try {
                $response = $this->httpClient->request('GET', $remoteUrl);
                $data = $response->toArray();
                // Ensure we have an array
                $jsFiles = is_array($data) ? $data : [];
                $cacheItem->set($jsFiles);
                $cacheItem->expiresAfter($cacheInterval);
                $this->cache->save($cacheItem);
            } catch (\Exception $e) {
                // Log the error or handle it as needed
                $jsFiles = [];
            }
        } else {
            $jsFiles = $cacheItem->get();
        }

        if (is_callable($renderScript)) {
            return $renderScript($jsFiles);
        }

        $src = $jsFiles[0] ?? null;
        if (!is_string($src) || $src === '') {
            return '';
        }

        $leading = self::LEADING_ATTRIBUTES;
        $trailing = ['async' => true];
        foreach ($scriptAttributes ?? [] as $key => $value) {
            if (array_key_exists($key, self::LEADING_ATTRIBUTES)) {
                $leading[$key] = $value;
            } else {
                $trailing[$key] = $value;
            }
        }

        $before = self::renderAttributes($leading);
        $after = self::renderAttributes($trailing);

        return sprintf(
            '<script%s src="%s"%s></script>',
            $before === '' ? '' : ' ' . $before,
            htmlspecialchars($src, ENT_QUOTES, 'UTF-8'),
            $after === '' ? '' : ' ' . $after
        );
    }

    /**
     * Adds ?domain=<site> to the sources URL. When a filter rule blocks our
     * script host on one site only, that site alone gets a new host, and only
     * a request naming it receives it. The URL is left as it is without a
     * domain, or when it already has a domain parameter.
     */
    public static function withDomain(string $remoteUrl, ?string $domain): string
    {
        if ($domain === null || $domain === '') {
            return $remoteUrl;
        }
        $query = (string) parse_url($remoteUrl, PHP_URL_QUERY);
        parse_str($query, $params);
        if (array_key_exists('domain', $params)) {
            return $remoteUrl;
        }
        $fragment = '';
        $hashPos = strpos($remoteUrl, '#');
        if ($hashPos !== false) {
            $fragment = substr($remoteUrl, $hashPos);
            $remoteUrl = substr($remoteUrl, 0, $hashPos);
        }
        $separator = $query === '' ? (substr($remoteUrl, -1) === '?' ? '' : '?') : '&';

        return $remoteUrl . $separator . 'domain=' . rawurlencode($domain) . $fragment;
    }

    private function requestHost(): ?string
    {
        if ($this->requestStack === null) {
            return null;
        }
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return null;
        }
        try {
            $host = $request->getHost();
        } catch (\Exception $e) {
            // A host outside framework.trusted_hosts: send no domain.
            return null;
        }

        return $host === '' ? null : $host;
    }

    private static function renderAttributes(array $attrs): string
    {
        $parts = [];
        foreach ($attrs as $key => $value) {
            if ($value === true) {
                $parts[] = htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8');
            } elseif ($value !== false && $value !== null) {
                $parts[] = sprintf(
                    '%s="%s"',
                    htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8')
                );
            }
        }

        return implode(' ', $parts);
    }
}
