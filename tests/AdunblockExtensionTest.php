<?php

namespace AdUnblock\ServerTag\Symfony\Tests;

use AdUnblock\ServerTag\Symfony\DependencyInjection\AdunblockServerTagExtension;
use AdUnblock\ServerTag\Symfony\Twig\AdunblockExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class AdunblockExtensionTest extends TestCase
{
    private const SOURCES = 'https://sources.example/api/vendor_scripts';
    private const SCRIPT_1 = 'https://one.example/a.js';
    private const SCRIPT_2 = 'https://two.example/b.js';

    /** @var string[] URLs the sources endpoint was asked for */
    private $requested = [];
    /** @var RequestStack */
    private $requestStack;
    /** @var AdunblockExtension */
    private $extension;

    protected function setUp(): void
    {
        $this->requested = [];
        $this->requestStack = new RequestStack();
        $this->extension = new AdunblockExtension($this->sourcesClient(), new ArrayAdapter(), $this->requestStack);
    }

    private function sourcesClient(): MockHttpClient
    {
        return new MockHttpClient(function ($method, $url) {
            $this->requested[] = $url;
            $body = strpos($url, '/empty') !== false ? [] : [self::SCRIPT_1, self::SCRIPT_2];
            return new MockResponse(json_encode($body), ['response_headers' => ['content-type' => 'application/json']]);
        });
    }

    private function visit(string $url): void
    {
        $this->requestStack->push(Request::create($url));
    }

    /** @return array<int, string|null> the domain each sources request named */
    private function requestedDomains(): array
    {
        return array_map(function ($url) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            return $query['domain'] ?? null;
        }, $this->requested);
    }

    private function renderTwig(string $template, array $context = []): string
    {
        $twig = new Environment(new ArrayLoader(['page' => $template]));
        $twig->addExtension($this->extension);
        return $twig->render('page', $context);
    }

    public function testDefaultTagOrderAndFirstUrlOnly(): void
    {
        $this->assertSame(
            '<script data-cfasync="false" nowprocket src="' . self::SCRIPT_1 . '" async></script>',
            $this->extension->serverTag(self::SOURCES)
        );
    }

    public function testScriptAttributesArgumentRendersDataCodeAfterSrc(): void
    {
        $this->assertSame(
            '<script data-cfasync="false" nowprocket src="' . self::SCRIPT_1 . '" async data-code="12345678"></script>',
            $this->extension->serverTag(self::SOURCES, 300, null, ['data-code' => '12345678'])
        );
    }

    public function testTwigPositionalAndNamedScriptAttributes(): void
    {
        $expected = '<script data-cfasync="false" nowprocket src="' . self::SCRIPT_1 . '" async data-code="12345678"></script>';
        $this->assertSame($expected, $this->renderTwig("{{ server_tag(url, 300, null, {'data-code': '12345678'}) }}", ['url' => self::SOURCES]));
        $this->assertSame($expected, $this->renderTwig("{{ server_tag(url, scriptAttributes={'data-code': '12345678'}) }}", ['url' => self::SOURCES]));
    }

    public function testExistingTwigCallsStillWork(): void
    {
        $this->assertSame(
            '<script data-cfasync="false" nowprocket src="' . self::SCRIPT_1 . '" async></script>',
            $this->renderTwig('{{ server_tag(url, 60) }}', ['url' => self::SOURCES])
        );
    }

    public function testCallerDataCfasyncStaysAheadOnceAndDefaultsCanBeDropped(): void
    {
        $this->assertSame(
            '<script data-cfasync="false" nowprocket src="' . self::SCRIPT_1 . '" async data-code="1"></script>',
            $this->extension->serverTag(self::SOURCES, 300, null, ['data-cfasync' => 'false', 'data-code' => '1'])
        );
        $this->assertSame(
            '<script src="' . self::SCRIPT_1 . '"></script>',
            $this->extension->serverTag(self::SOURCES, 300, null, ['data-cfasync' => false, 'nowprocket' => false, 'async' => false])
        );
    }

    public function testRenderScriptStillGetsEveryUrl(): void
    {
        $seen = null;
        $html = $this->extension->serverTag(self::SOURCES, 300, function (array $scripts) use (&$seen) {
            $seen = $scripts;
            return 'custom';
        });
        $this->assertSame([self::SCRIPT_1, self::SCRIPT_2], $seen);
        $this->assertSame('custom', $html);
    }

    public function testEmptyListRendersNothing(): void
    {
        $this->assertSame('', $this->extension->serverTag(self::SOURCES . '/empty'));
    }

    public function testDomainDefaultsToTheRequestHost(): void
    {
        $this->visit('http://www.publisher.com:8080/page');
        $this->extension->serverTag(self::SOURCES);
        $this->assertSame(['www.publisher.com'], $this->requestedDomains());
    }

    public function testWithoutARequestStackNoDomainIsSent(): void
    {
        // The two-argument constructor existing code may call directly.
        $extension = new AdunblockExtension($this->sourcesClient(), new ArrayAdapter());
        $extension->serverTag(self::SOURCES);
        $this->assertSame([null], $this->requestedDomains());
    }

    public function testDomainArgumentWins(): void
    {
        $this->visit('http://other.com/');
        $this->renderTwig("{{ server_tag(url, domain='chosen.com') }}", ['url' => self::SOURCES]);
        $this->assertSame(['chosen.com'], $this->requestedDomains());
    }

    public function testDomainAlreadyInUrlIsKept(): void
    {
        $this->visit('http://other.com/');
        $this->extension->serverTag(self::SOURCES . '?domain=fixed.com');
        $this->assertSame(['fixed.com'], $this->requestedDomains());
    }

    public function testCacheIsPerDomain(): void
    {
        $this->extension->serverTag(self::SOURCES, 300, null, null, 'a.com');
        $this->extension->serverTag(self::SOURCES, 300, null, null, 'a.com');
        $this->extension->serverTag(self::SOURCES, 300, null, null, 'b.com');
        $this->assertSame(['a.com', 'b.com'], $this->requestedDomains());
    }

    public function testWithDomainEncodesAndKeepsTheQuery(): void
    {
        $this->assertSame(
            self::SOURCES . '/v1?x=1&domain=my%20site.com',
            AdunblockExtension::withDomain(self::SOURCES . '/v1?x=1', 'my site.com')
        );
        $this->assertSame(self::SOURCES, AdunblockExtension::withDomain(self::SOURCES, null));
    }

    public function testBundleWiresTheRequestStack(): void
    {
        $container = new ContainerBuilder();
        (new AdunblockServerTagExtension())->load([], $container);
        $arguments = $container->getDefinition('adunblock.server_tag.twig_extension')->getArguments();
        $this->assertSame('request_stack', (string) $arguments[2]);
        $this->assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $arguments[2]->getInvalidBehavior());
    }
}
