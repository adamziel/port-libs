<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class SmartHttpReceivePackTransport implements ReceivePackTransport
{
    private const MAX_INITIAL_REDIRECTS = 50;
    private const DEFAULT_USER_AGENT = 'git/oxide-port-libs';

    private bool $advertisementRead = false;
    private bool $requestWritten = false;
    private bool $responseRead = false;
    private ?string $requestBytes = null;
    private readonly string $repositoryUrl;
    private ?string $effectiveRepositoryUrl = null;
    private readonly ?string $authorizationHeader;
    private readonly mixed $requester;
    /** @var array<string, array{name: string, value: string, domain: string, path: string, secure: bool, hostOnly: bool}> */
    private array $cookies = [];
    /** @var array<string, string> */
    private readonly array $extraHeaders;
    /** @var array{proxyConfigured: bool, proxy: ?array{type: string, stream: string, url: string, credentialUrl: string, authorization: ?string, username: ?string}, httpsProxyConfigured: bool, httpsProxy: ?array{type: string, stream: string, url: string, credentialUrl: string, authorization: ?string, username: ?string}, allProxyConfigured: bool, allProxy: ?array{type: string, stream: string, url: string, credentialUrl: string, authorization: ?string, username: ?string}, noProxy: list<string>, proxyAuthorization: ?string, proxyAuthMethod: string, proxyCredentialHelper: ?callable, proxyCredentialStore: ?callable, proxyCredentialErase: ?callable, sslCaInfo: ?string, sslVerify: bool, followRedirects: string} */
    private readonly array $httpOptions;

    /**
     * @param null|callable(string, string, array<string, string>, ?string, float, array<string, mixed>): array{status: int, headers: array<string, string|list<string>>, body: string} $requester
     * @param list<string> $extraParameters
     * @param array<string, string> $extraHeaders
     * @param array<string, mixed> $httpOptions
     */
    public function __construct(
        string $repositoryUrl,
        ?callable $requester = null,
        private readonly array $extraParameters = [],
        private readonly float $timeout = 30.0,
        array $extraHeaders = [],
        array $httpOptions = [],
    ) {
        if ($timeout <= 0.0) {
            throw new \InvalidArgumentException('smart HTTP receive-pack transport timeout must be greater than zero');
        }
        self::validateExtraParameters($extraParameters);
        $this->extraHeaders = self::normalizeExtraHeaders($extraHeaders);
        $this->httpOptions = self::normalizeHttpOptions($httpOptions);

        $target = self::normalizeRepositoryUrl($repositoryUrl);
        $this->repositoryUrl = $target['url'];
        $this->authorizationHeader = $target['authorization'];
        $this->requester = $requester ?? static fn (
            string $method,
            string $url,
            array $headers,
            ?string $body,
            float $timeout,
            array $requestHttpOptions,
        ): array => self::performHttpRequest($method, $url, $headers, $body, $timeout, $requestHttpOptions);
    }

    public static function infoRefsUrl(string $repositoryUrl): string
    {
        $target = self::normalizeRepositoryUrl($repositoryUrl);
        $separator = str_contains($target['url'], '?') ? '&' : '?';

        return $target['url'] . '/info/refs' . $separator . 'service=git-receive-pack';
    }

    public static function receivePackUrl(string $repositoryUrl): string
    {
        $target = self::normalizeRepositoryUrl($repositoryUrl);

        return $target['url'] . '/git-receive-pack';
    }

    public function readAdvertisement(): string
    {
        if ($this->advertisementRead) {
            throw new \LogicException('smart HTTP receive-pack advertisement was already read');
        }

        $this->advertisementRead = true;
        $response = $this->request('GET', self::infoRefsUrl($this->repositoryUrl), $this->advertisementHeaders(), null, true, [200, 304]);
        self::assertStatus($response, [200, 304], 'smart HTTP receive-pack advertisement');
        self::assertContentType($response, 'application/x-git-receive-pack-advertisement', 'smart HTTP receive-pack advertisement');
        $this->rememberCookies(
            $response['headers'],
            self::swapBaseUrl($this->effectiveRepositoryUrl, $this->repositoryUrl, self::infoRefsUrl($this->repositoryUrl)),
            true,
        );

        return self::stripServiceAdvertisement($response['body']);
    }

    public function writeRequest(string $requestBytes): void
    {
        if (!$this->advertisementRead) {
            throw new \LogicException('smart HTTP receive-pack request cannot be written before advertisement');
        }
        if ($this->requestWritten) {
            throw new \LogicException('smart HTTP receive-pack request was already written');
        }

        $this->requestBytes = $requestBytes;
        $this->requestWritten = true;
    }

    public function readResponse(): string
    {
        if (!$this->requestWritten || $this->requestBytes === null) {
            throw new \LogicException('smart HTTP receive-pack response cannot be read before request');
        }
        if ($this->responseRead) {
            throw new \LogicException('smart HTTP receive-pack response was already read');
        }

        $this->responseRead = true;
        $response = $this->request(
            'POST',
            self::receivePackUrl($this->repositoryUrl),
            $this->requestHeaders(strlen($this->requestBytes)),
            $this->requestBytes,
            false,
            [200],
        );
        self::assertStatus($response, [200], 'smart HTTP receive-pack result');
        self::assertContentType($response, 'application/x-git-receive-pack-result', 'smart HTTP receive-pack result');
        $this->rememberCookies(
            $response['headers'],
            self::swapBaseUrl($this->effectiveRepositoryUrl, $this->repositoryUrl, self::receivePackUrl($this->repositoryUrl)),
            true,
        );

        return $response['body'];
    }

    /**
     * @return array<string, string>
     */
    private function advertisementHeaders(): array
    {
        $headers = $this->withExtraHeaders([
            'Accept' => 'application/x-git-receive-pack-advertisement',
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
            'User-Agent' => self::DEFAULT_USER_AGENT,
        ]);
        $authorizationHeader = $this->authorizationHeaderForRequest();
        if ($authorizationHeader !== null) {
            self::setHeader($headers, 'Authorization', $authorizationHeader);
        }
        if ($this->extraParameters !== []) {
            self::setHeader($headers, 'Git-Protocol', implode(':', $this->extraParameters));
        }
        $cookieHeader = self::cookieHeader($this->cookies, self::infoRefsUrl($this->repositoryUrl), self::headerValue($headers, 'cookie'));
        if ($cookieHeader !== null) {
            self::setHeader($headers, 'Cookie', $cookieHeader);
        }

        return $headers;
    }

    /**
     * @return array<string, string>
     */
    private function requestHeaders(int $bodyLength): array
    {
        $headers = $this->withExtraHeaders([
            'Accept' => 'application/x-git-receive-pack-result',
            'Content-Type' => 'application/x-git-receive-pack-request',
            'Content-Length' => (string) $bodyLength,
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
            'User-Agent' => self::DEFAULT_USER_AGENT,
        ]);
        self::setHeader($headers, 'Expect', '');
        $authorizationHeader = $this->authorizationHeaderForRequest();
        if ($authorizationHeader !== null) {
            self::setHeader($headers, 'Authorization', $authorizationHeader);
        }
        if ($this->extraParameters !== []) {
            self::setHeader($headers, 'Git-Protocol', implode(':', $this->extraParameters));
        }
        $cookieUrl = self::swapBaseUrl($this->effectiveRepositoryUrl, $this->repositoryUrl, self::receivePackUrl($this->repositoryUrl));
        $cookieHeader = self::cookieHeader($this->cookies, $cookieUrl, self::headerValue($headers, 'cookie'));
        if ($cookieHeader !== null) {
            self::setHeader($headers, 'Cookie', $cookieHeader);
        }

        return $headers;
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function withExtraHeaders(array $headers): array
    {
        foreach ($this->extraHeaders as $name => $value) {
            self::setHeader($headers, $name, $value);
        }

        return $headers;
    }

    private function authorizationHeaderForRequest(): ?string
    {
        if ($this->authorizationHeader === null) {
            return null;
        }
        if (str_starts_with($this->repositoryUrl, 'http://')) {
            throw new \RuntimeException('smart HTTP receive-pack will not send URL credentials over cleartext HTTP');
        }

        return $this->authorizationHeader;
    }

    /**
     * @param array<string, string> $headers
     * @param list<int> $acceptedCredentialStatuses
     * @return array{status: int, headers: array<string, string|list<string>>, body: string}
     */
    private function request(string $method, string $url, array $headers, ?string $body, bool $followInitialRedirects, array $acceptedCredentialStatuses): array
    {
        $redirectsRemaining = $this->redirectLimit($followInitialRedirects);
        $callerCookieHeader = self::headerValue($this->extraHeaders, 'cookie');
        $proxyCredentialAction = null;
        $proxyCredentialAuthorization = null;

        while (true) {
            $effectiveUrl = self::swapBaseUrl($this->effectiveRepositoryUrl, $this->repositoryUrl, $url);
            [$requestHttpOptions, $proxyCredentialAction] = $this->httpOptionsForUrl(
                $effectiveUrl,
                $proxyCredentialAction,
                $proxyCredentialAuthorization,
                $url,
            );
            try {
                $response = ($this->requester)(
                    $method,
                    $effectiveUrl,
                    $headers,
                    $body,
                    $this->timeout,
                    $requestHttpOptions,
                );
                if (!is_array($response)
                    || !isset($response['status'], $response['headers'], $response['body'])
                    || !is_int($response['status'])
                    || !is_array($response['headers'])
                    || !is_string($response['body'])
                ) {
                    throw new \RuntimeException('smart HTTP receive-pack requester returned an invalid response shape');
                }
                $this->completeProxyCredentialAction($proxyCredentialAction, $response['status'], $acceptedCredentialStatuses);
            } catch (\Throwable $throwable) {
                $this->eraseProxyCredentialAction($proxyCredentialAction);

                throw $throwable;
            }

            if (!self::isRedirectStatus($response['status'])) {
                return $response;
            }

            try {
                if ($redirectsRemaining <= 0) {
                    throw new \RuntimeException("smart HTTP receive-pack {$method} request returned an unexpected redirect status {$response['status']}");
                }
                if ($method === 'POST' && !in_array($response['status'], [307, 308], true)) {
                    throw new \RuntimeException("smart HTTP receive-pack POST redirect status {$response['status']} would not preserve the generated pack request");
                }

                $location = self::headerValue($response['headers'], 'location');
                if ($location === null || $location === '') {
                    throw new \RuntimeException("smart HTTP receive-pack {$method} redirect missing Location header");
                }

                $redirectUrl = self::resolveRedirectUrl($location, $effectiveUrl);
                $redirectedBaseUrl = self::redirectedBaseUrl($redirectUrl, $this->repositoryUrl, $url);
                $this->rememberCookies($response['headers'], $effectiveUrl, true);
                $cookieHeader = self::cookieHeader($this->cookies, $redirectUrl, $callerCookieHeader);
                if ($cookieHeader !== null) {
                    self::setHeader($headers, 'Cookie', $cookieHeader);
                } else {
                    self::removeHeader($headers, 'Cookie');
                }
                $this->effectiveRepositoryUrl = $redirectedBaseUrl;
                $redirectsRemaining--;
            } catch (\Throwable $throwable) {
                $this->eraseProxyCredentialAction($proxyCredentialAction);

                throw $throwable;
            }
        }
    }

    private function redirectLimit(bool $initialRequest): int
    {
        return match ($this->httpOptions['followRedirects']) {
            'all' => self::MAX_INITIAL_REDIRECTS,
            'initial' => $initialRequest ? self::MAX_INITIAL_REDIRECTS : 0,
            default => 0,
        };
    }

    /**
     * @return array{url: string, authorization: ?string}
     */
    private static function normalizeRepositoryUrl(string $repositoryUrl): array
    {
        if ($repositoryUrl === '' || self::containsControlByte($repositoryUrl)) {
            throw new \InvalidArgumentException('smart HTTP receive-pack URL must be non-empty and must not contain control bytes');
        }

        try {
            $parts = parse_url($repositoryUrl);
        } catch (\ValueError $error) {
            throw new \InvalidArgumentException('smart HTTP receive-pack transport could not parse repository URL', 0, $error);
        }

        if (!is_array($parts) || !isset($parts['scheme']) || !is_string($parts['scheme'])) {
            throw new \InvalidArgumentException('smart HTTP receive-pack URL must include an http or https scheme');
        }

        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new \InvalidArgumentException('smart HTTP receive-pack URL must use http or https');
        }
        if (!isset($parts['host']) || !is_string($parts['host']) || $parts['host'] === '') {
            throw new \InvalidArgumentException('smart HTTP receive-pack URL must include a host');
        }
        if (isset($parts['fragment'])) {
            throw new \InvalidArgumentException('smart HTTP receive-pack URL must not include a fragment');
        }

        $host = self::validateAuthorityHost($parts['host'], 'smart HTTP receive-pack URL host');
        if (isset($parts['path'])) {
            self::validateDecodedUrlComponent((string) $parts['path'], 'smart HTTP receive-pack URL path');
        }
        if (isset($parts['query'])) {
            self::validateDecodedUrlComponent((string) $parts['query'], 'smart HTTP receive-pack URL query');
        }
        $authority = self::authority($host, isset($parts['port']) ? (int) $parts['port'] : null);
        $url = $scheme . '://' . $authority . ($parts['path'] ?? '');
        if (isset($parts['query'])) {
            $url .= '?' . $parts['query'];
        }

        $authorization = null;
        if (isset($parts['user'])) {
            $user = rawurldecode((string) $parts['user']);
            $pass = rawurldecode((string) ($parts['pass'] ?? ''));
            $authorization = self::basicAuthorization($user, $pass, 'smart HTTP receive-pack URL credentials');
        }

        return [
            'url' => rtrim($url, '/'),
            'authorization' => $authorization,
        ];
    }

    private static function authority(string $host, ?int $port): string
    {
        $authority = str_contains($host, ':') && !str_starts_with($host, '[') ? "[{$host}]" : $host;
        if ($port !== null) {
            if ($port < 1 || $port > 65535) {
                throw new \InvalidArgumentException('smart HTTP receive-pack URL port must be between 1 and 65535');
            }
            $authority .= ':' . $port;
        }

        return $authority;
    }

    private static function validateAuthorityHost(string $host, string $label): string
    {
        $decoded = rawurldecode($host);
        if ($decoded === '' || self::containsControlByte($decoded)) {
            throw new \InvalidArgumentException("{$label} must be non-empty and must not contain control bytes");
        }
        if (preg_match('/[\s\/\\\\]/', $decoded) === 1) {
            throw new \InvalidArgumentException("{$label} must not contain whitespace, slash, or backslash delimiters");
        }

        return $decoded;
    }

    private static function validateDecodedUrlComponent(string $value, string $label): void
    {
        if (self::containsControlByte(rawurldecode($value))) {
            throw new \InvalidArgumentException("{$label} must not contain decoded control bytes");
        }
    }

    private static function isRedirectStatus(int $status): bool
    {
        return in_array($status, [301, 302, 303, 307, 308], true);
    }

    private static function swapBaseUrl(?string $effectiveBaseUrl, string $baseUrl, string $url): string
    {
        if ($effectiveBaseUrl === null) {
            return $url;
        }
        if (!str_starts_with($url, $baseUrl)) {
            throw new \LogicException('smart HTTP receive-pack redirect base does not match request URL');
        }

        return $effectiveBaseUrl . substr($url, strlen($baseUrl));
    }

    private static function redirectedBaseUrl(string $redirectUrl, string $baseUrl, string $url): string
    {
        if (!str_starts_with($url, $baseUrl)) {
            throw new \LogicException('smart HTTP receive-pack redirect base does not match request URL');
        }
        if (!self::sharesAuthorityOrUpgradesScheme($redirectUrl, $baseUrl)) {
            throw new \RuntimeException("smart HTTP receive-pack redirect URL {$redirectUrl} does not share authority with {$baseUrl}");
        }

        $tail = substr($url, strlen($baseUrl));
        if ($tail === '' || !str_ends_with($redirectUrl, $tail)) {
            throw new \RuntimeException("smart HTTP receive-pack redirect URL {$redirectUrl} does not preserve request path suffix");
        }

        return substr($redirectUrl, 0, -strlen($tail));
    }

    private static function sharesAuthorityOrUpgradesScheme(string $redirectUrl, string $baseUrl): bool
    {
        $redirect = self::httpUrlParts($redirectUrl, 'smart HTTP receive-pack redirect URL');
        $base = self::httpUrlParts($baseUrl, 'smart HTTP receive-pack base URL');
        if (strtolower($redirect['host']) !== strtolower($base['host'])) {
            return false;
        }

        if ($redirect['scheme'] === $base['scheme']) {
            return self::portOrDefault($redirect) === self::portOrDefault($base);
        }

        if ($base['scheme'] === 'http' && $redirect['scheme'] === 'https') {
            $basePort = self::portOrDefault($base);
            $redirectPort = self::portOrDefault($redirect);

            return $basePort === $redirectPort || ($basePort === 80 && $redirectPort === 443);
        }

        return false;
    }

    private static function resolveRedirectUrl(string $location, string $currentUrl): string
    {
        if (self::containsControlByte($location)) {
            throw new \RuntimeException('smart HTTP receive-pack redirect Location contains control bytes');
        }

        try {
            $locationParts = parse_url($location);
        } catch (\ValueError $error) {
            throw new \RuntimeException('smart HTTP receive-pack redirect Location could not be parsed', 0, $error);
        }

        if (is_array($locationParts) && isset($locationParts['scheme'])) {
            return self::normalizeRedirectUrl($location);
        }

        $current = self::httpUrlParts($currentUrl, 'smart HTTP receive-pack current URL');
        $authority = self::authority($current['host'], $current['port']);
        if (str_starts_with($location, '/')) {
            return self::normalizeRedirectUrl($current['scheme'] . '://' . $authority . $location);
        }

        $basePath = $current['path'] ?? '/';
        $directory = str_ends_with($basePath, '/') ? $basePath : substr($basePath, 0, strrpos($basePath, '/') + 1);

        return self::normalizeRedirectUrl($current['scheme'] . '://' . $authority . $directory . $location);
    }

    private static function normalizeRedirectUrl(string $url): string
    {
        $parts = self::httpUrlParts($url, 'smart HTTP receive-pack redirect URL');
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new \RuntimeException('smart HTTP receive-pack redirect URL must not contain credentials');
        }
        if (isset($parts['fragment'])) {
            throw new \RuntimeException('smart HTTP receive-pack redirect URL must not contain a fragment');
        }
        if (isset($parts['path'])) {
            self::validateDecodedUrlComponent($parts['path'], 'smart HTTP receive-pack redirect URL path');
        }
        if (isset($parts['query'])) {
            self::validateDecodedUrlComponent($parts['query'], 'smart HTTP receive-pack redirect URL query');
        }

        $path = isset($parts['path']) ? self::normalizeRedirectPath($parts['path']) : '';
        $normalized = $parts['scheme'] . '://' . self::authority($parts['host'], $parts['port']) . $path;
        if (isset($parts['query'])) {
            $normalized .= '?' . $parts['query'];
        }

        return $normalized;
    }

    /**
     * @return array{scheme: string, host: string, port: ?int, path?: string, query?: string, user?: string, pass?: string, fragment?: string}
     */
    private static function httpUrlParts(string $url, string $label): array
    {
        try {
            $parts = parse_url($url);
        } catch (\ValueError $error) {
            throw new \RuntimeException("{$label} could not be parsed", 0, $error);
        }

        if (!is_array($parts)
            || !isset($parts['scheme'], $parts['host'])
            || !is_string($parts['scheme'])
            || !is_string($parts['host'])
            || $parts['host'] === ''
        ) {
            throw new \RuntimeException("{$label} must include an http or https scheme and host");
        }

        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new \RuntimeException("{$label} must use http or https");
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        if ($port !== null && ($port < 1 || $port > 65535)) {
            throw new \RuntimeException("{$label} port must be between 1 and 65535");
        }

        $host = self::validateAuthorityHost($parts['host'], "{$label} host");
        $result = [
            'scheme' => $scheme,
            'host' => $host,
            'port' => $port,
        ];
        foreach (['path', 'query', 'user', 'pass', 'fragment'] as $key) {
            if (isset($parts[$key]) && is_string($parts[$key])) {
                $result[$key] = $parts[$key];
            }
        }

        return $result;
    }

    /**
     * @param array{scheme: string, port: ?int} $parts
     */
    private static function portOrDefault(array $parts): int
    {
        if ($parts['port'] !== null) {
            return $parts['port'];
        }

        return $parts['scheme'] === 'https' ? 443 : 80;
    }

    private static function normalizeRedirectPath(string $path): string
    {
        if ($path === '') {
            return '';
        }

        $input = $path;
        $output = '';
        while ($input !== '') {
            if (str_starts_with($input, '../')) {
                $input = substr($input, 3);
                continue;
            }
            if (str_starts_with($input, './')) {
                $input = substr($input, 2);
                continue;
            }
            if (str_starts_with($input, '/./')) {
                $input = '/' . substr($input, 3);
                continue;
            }
            if ($input === '/.') {
                $input = '/';
                continue;
            }
            if (str_starts_with($input, '/../')) {
                $input = '/' . substr($input, 4);
                $output = self::removeLastRedirectPathSegment($output);
                continue;
            }
            if ($input === '/..') {
                $input = '/';
                $output = self::removeLastRedirectPathSegment($output);
                continue;
            }
            if ($input === '.' || $input === '..') {
                $input = '';
                continue;
            }

            $nextSlash = str_starts_with($input, '/')
                ? strpos($input, '/', 1)
                : strpos($input, '/');
            $segmentLength = $nextSlash === false ? strlen($input) : $nextSlash;
            $output .= substr($input, 0, $segmentLength);
            $input = substr($input, $segmentLength);
        }

        return $output;
    }

    private static function removeLastRedirectPathSegment(string $path): string
    {
        if ($path === '') {
            return '';
        }

        $position = strrpos($path, '/');
        if ($position === false) {
            return '';
        }

        return substr($path, 0, $position);
    }

    /**
     * @param ?array{proxyUrl: string, requestHost: string, credentials: array{username: string, password: string}} $proxyCredentialAction
     * @return array{0: array<string, mixed>, 1: ?array{proxyUrl: string, requestHost: string, credentials: array{username: string, password: string}}}
     */
    private function httpOptionsForUrl(
        string $url,
        ?array &$proxyCredentialAction = null,
        ?string &$proxyCredentialAuthorization = null,
        ?string $proxySelectionUrl = null,
    ): array {
        $options = [];
        if ($this->httpOptions['sslCaInfo'] !== null) {
            $options['sslCaInfo'] = $this->httpOptions['sslCaInfo'];
        }
        if (!$this->httpOptions['sslVerify']) {
            $options['sslVerify'] = false;
        }

        $request = self::httpUrlParts($url, 'smart HTTP receive-pack request URL');
        $proxySelection = $proxySelectionUrl === null
            ? $request
            : self::httpUrlParts($proxySelectionUrl, 'smart HTTP receive-pack proxy selection URL');
        $proxy = $this->proxyForScheme($proxySelection['scheme']);
        if ($proxy === null) {
            return [$options, null];
        }

        if (self::matchesNoProxy($request['host'], $this->httpOptions['noProxy'])) {
            return [$options, null];
        }

        $options += [
            'proxyType' => $proxy['type'],
            'proxy' => $proxy['stream'],
            'proxyUrl' => $proxy['url'],
            'requestFullUri' => $proxy['type'] === 'http' || $proxy['type'] === 'https',
            'proxyAuthMethod' => $this->httpOptions['proxyAuthMethod'],
        ];
        $authorization = $this->proxyAuthorization(
            $proxy,
            $request['host'],
            $proxyCredentialAction,
            $proxyCredentialAuthorization,
        );
        if ($authorization['authorization'] !== null) {
            $options['proxyAuthorization'] = $authorization['authorization'];
        }

        return [$options, $authorization['credentialAction']];
    }

    /**
     * @return ?array{type: string, stream: string, url: string, credentialUrl: string, authorization: ?string, username: ?string}
     */
    private function proxyForScheme(string $scheme): ?array
    {
        if ($this->httpOptions['proxyConfigured']) {
            return $this->httpOptions['proxy'];
        }
        if ($scheme === 'https' && $this->httpOptions['httpsProxyConfigured']) {
            return $this->httpOptions['httpsProxy'];
        }
        if ($this->httpOptions['allProxyConfigured']) {
            return $this->httpOptions['allProxy'];
        }

        return null;
    }

    /**
     * @param array{type: string, stream: string, url: string, credentialUrl: string, authorization: ?string, username: ?string} $proxy
     * @param ?array{proxyUrl: string, requestHost: string, credentials: array{username: string, password: string}} $proxyCredentialAction
     * @return array{authorization: ?string, credentialAction: ?array{proxyUrl: string, requestHost: string, credentials: array{username: string, password: string}}}
     */
    private function proxyAuthorization(
        array $proxy,
        string $requestHost,
        ?array &$proxyCredentialAction = null,
        ?string &$proxyCredentialAuthorization = null,
    ): array {
        if ($this->httpOptions['proxyAuthorization'] !== null) {
            return ['authorization' => $this->httpOptions['proxyAuthorization'], 'credentialAction' => null];
        }
        $helper = $this->httpOptions['proxyCredentialHelper'];
        if ($helper === null) {
            if ($proxy['authorization'] !== null) {
                return ['authorization' => $proxy['authorization'], 'credentialAction' => null];
            }
            if ($proxy['username'] !== null && in_array($proxy['type'], ['socks4', 'socks4a', 'socks5', 'socks5h'], true)) {
                return [
                    'authorization' => self::basicAuthorization(
                        $proxy['username'],
                        '',
                        'smart HTTP receive-pack proxy credentials',
                    ),
                    'credentialAction' => null,
                ];
            }

            return ['authorization' => null, 'credentialAction' => null];
        }
        $helperProxyUrl = $proxy['credentialUrl'];
        if ($proxyCredentialAction !== null
            && $proxyCredentialAction['proxyUrl'] === $helperProxyUrl
            && strcasecmp($proxyCredentialAction['requestHost'], $requestHost) === 0
        ) {
            $proxyCredentialAuthorization ??= self::basicAuthorization(
                $proxyCredentialAction['credentials']['username'],
                $proxyCredentialAction['credentials']['password'],
                'smart HTTP receive-pack proxy credentials',
            );

            return ['authorization' => $proxyCredentialAuthorization, 'credentialAction' => $proxyCredentialAction];
        }

        $credentials = $helper($helperProxyUrl, $requestHost);
        if ($credentials === null) {
            return ['authorization' => null, 'credentialAction' => null];
        }
        if (!is_array($credentials)
            || !isset($credentials['username'], $credentials['password'])
            || !is_string($credentials['username'])
            || !is_string($credentials['password'])
        ) {
            throw new \RuntimeException('smart HTTP receive-pack proxy credential helper returned invalid credentials');
        }

        $proxyCredentialAuthorization = self::basicAuthorization(
            $credentials['username'],
            $credentials['password'],
            'smart HTTP receive-pack proxy credentials',
        );
        $proxyCredentialAction = [
            'proxyUrl' => $helperProxyUrl,
            'requestHost' => $requestHost,
            'credentials' => [
                'username' => $credentials['username'],
                'password' => $credentials['password'],
            ],
        ];

        return [
            'authorization' => $proxyCredentialAuthorization,
            'credentialAction' => $proxyCredentialAction,
        ];
    }

    /**
     * @param ?array{proxyUrl: string, requestHost: string, credentials: array{username: string, password: string}} $credentialAction
     * @param list<int> $acceptedStatuses
     */
    private function completeProxyCredentialAction(?array $credentialAction, int $status, array $acceptedStatuses): void
    {
        if ($credentialAction === null || self::isRedirectStatus($status)) {
            return;
        }

        $callback = in_array($status, $acceptedStatuses, true)
            ? $this->httpOptions['proxyCredentialStore']
            : $this->httpOptions['proxyCredentialErase'];
        if ($callback === null) {
            return;
        }

        $callback($credentialAction['proxyUrl'], $credentialAction['requestHost'], $credentialAction['credentials']);
    }

    /**
     * @param ?array{proxyUrl: string, requestHost: string, credentials: array{username: string, password: string}} $credentialAction
     */
    private function eraseProxyCredentialAction(?array $credentialAction): void
    {
        if ($credentialAction === null || $this->httpOptions['proxyCredentialErase'] === null) {
            return;
        }

        ($this->httpOptions['proxyCredentialErase'])(
            $credentialAction['proxyUrl'],
            $credentialAction['requestHost'],
            $credentialAction['credentials'],
        );
    }

    /**
     * @param list<string> $patterns
     */
    private static function matchesNoProxy(string $host, array $patterns): bool
    {
        $host = self::normalizeNoProxyHost($host);
        foreach ($patterns as $pattern) {
            if ($pattern === '*') {
                return true;
            }

            $pattern = strtolower(trim($pattern));
            if ($pattern === '') {
                continue;
            }
            if (str_contains($pattern, '/') && self::cidrMatches($host, $pattern)) {
                return true;
            }
            $pattern = self::normalizeNoProxyHost($pattern);
            if ($pattern === '') {
                continue;
            }
            if (str_starts_with($pattern, '.')) {
                if (str_ends_with($host, $pattern) || $host === substr($pattern, 1)) {
                    return true;
                }
                continue;
            }
            if ($host === $pattern || str_ends_with($host, '.' . $pattern)) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeNoProxyHost(string $host): string
    {
        return rtrim(strtolower(trim($host, '[]')), '.');
    }

    private static function cidrMatches(string $host, string $pattern): bool
    {
        $cidr = self::parseCidrPattern($pattern);
        if ($cidr === null) {
            return false;
        }

        $hostBytes = @inet_pton(trim($host, '[]'));
        if ($hostBytes === false || strlen($hostBytes) !== strlen($cidr['network'])) {
            return false;
        }

        $fullBytes = intdiv($cidr['prefix'], 8);
        $remainingBits = $cidr['prefix'] % 8;
        if ($fullBytes > 0 && substr($hostBytes, 0, $fullBytes) !== substr($cidr['network'], 0, $fullBytes)) {
            return false;
        }
        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xff << (8 - $remainingBits)) & 0xff;

        return (ord($hostBytes[$fullBytes]) & $mask) === (ord($cidr['network'][$fullBytes]) & $mask);
    }

    /**
     * @return null|array{network: string, prefix: int}
     */
    private static function parseCidrPattern(string $pattern): ?array
    {
        $pattern = trim($pattern);
        if (preg_match('/^\[([^\]]+)\]\/(\d+)$/', $pattern, $matches) === 1) {
            $address = $matches[1];
            $prefix = (int) $matches[2];
        } else {
            [$address, $prefixString] = array_pad(explode('/', $pattern, 2), 2, null);
            if ($prefixString === null || preg_match('/^\d+$/', $prefixString) !== 1) {
                return null;
            }
            $prefix = (int) $prefixString;
        }

        $network = @inet_pton($address);
        if ($network === false) {
            return null;
        }
        $maxPrefix = strlen($network) * 8;
        if ($prefix < 0 || $prefix > $maxPrefix) {
            return null;
        }

        return ['network' => $network, 'prefix' => $prefix];
    }

    /**
     * @param list<string> $extraParameters
     */
    private static function validateExtraParameters(array $extraParameters): void
    {
        foreach ($extraParameters as $extraParameter) {
            if (!is_string($extraParameter)
                || $extraParameter === ''
                || self::containsControlByte($extraParameter)
                || str_contains($extraParameter, ':')
            ) {
                throw new \InvalidArgumentException('smart HTTP receive-pack extra parameters must be non-empty colon-free strings without control bytes');
            }
        }
    }

    /**
     * @param array<string, string> $extraHeaders
     * @return array<string, string>
     */
    private static function normalizeExtraHeaders(array $extraHeaders): array
    {
        $normalized = [];
        foreach ($extraHeaders as $name => $value) {
            if (!is_string($name) || !is_string($value)) {
                throw new \InvalidArgumentException('smart HTTP receive-pack extra headers must be string name/value pairs');
            }
            self::validateHeader($name, $value);

            $lowerName = strtolower($name);
            if (in_array($lowerName, ['accept', 'content-type', 'content-length', 'cache-control', 'pragma', 'git-protocol'], true)) {
                throw new \InvalidArgumentException("smart HTTP receive-pack extra header {$name} is managed by the transport");
            }

            foreach (array_keys($normalized) as $existingName) {
                if (strtolower($existingName) === $lowerName) {
                    unset($normalized[$existingName]);
                }
            }
            $normalized[$name] = $value;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $httpOptions
     * @return array{proxyConfigured: bool, proxy: ?array{type: string, stream: string, url: string, credentialUrl: string, authorization: ?string, username: ?string}, httpsProxyConfigured: bool, httpsProxy: ?array{type: string, stream: string, url: string, credentialUrl: string, authorization: ?string, username: ?string}, allProxyConfigured: bool, allProxy: ?array{type: string, stream: string, url: string, credentialUrl: string, authorization: ?string, username: ?string}, noProxy: list<string>, proxyAuthorization: ?string, proxyAuthMethod: string, proxyCredentialHelper: ?callable, proxyCredentialStore: ?callable, proxyCredentialErase: ?callable, sslCaInfo: ?string, sslVerify: bool, followRedirects: string}
     */
    private static function normalizeHttpOptions(array $httpOptions): array
    {
        $allowed = ['proxy', 'httpsProxy', 'allProxy', 'noProxy', 'proxyAuthorization', 'proxyAuthMethod', 'proxyCredentials', 'proxyCredentialHelper', 'proxyCredentialStore', 'proxyCredentialErase', 'sslCaInfo', 'sslVerify', 'followRedirects'];
        foreach (array_keys($httpOptions) as $name) {
            if (!is_string($name) || !in_array($name, $allowed, true)) {
                throw new \InvalidArgumentException('smart HTTP receive-pack HTTP option is not supported');
            }
        }

        [$proxyConfigured, $proxy] = self::normalizeConfiguredProxy($httpOptions, 'proxy');
        [$httpsProxyConfigured, $httpsProxy] = self::normalizeConfiguredProxy($httpOptions, 'httpsProxy');
        [$allProxyConfigured, $allProxy] = self::normalizeConfiguredProxy($httpOptions, 'allProxy');

        $proxyAuthorization = null;
        if (array_key_exists('proxyCredentials', $httpOptions)) {
            $credentials = $httpOptions['proxyCredentials'];
            if (!is_array($credentials)
                || !isset($credentials['username'], $credentials['password'])
                || !is_string($credentials['username'])
                || !is_string($credentials['password'])
            ) {
                throw new \InvalidArgumentException('smart HTTP receive-pack proxy credentials must include string username and password');
            }
            $proxyAuthorization = self::basicAuthorization($credentials['username'], $credentials['password'], 'smart HTTP receive-pack proxy credentials');
        }
        if (array_key_exists('proxyAuthorization', $httpOptions) && $httpOptions['proxyAuthorization'] !== null) {
            if (!is_string($httpOptions['proxyAuthorization'])) {
                throw new \InvalidArgumentException('smart HTTP receive-pack proxy authorization must be a string');
            }
            self::validateHeader('Proxy-Authorization', $httpOptions['proxyAuthorization']);
            $proxyAuthorization = $httpOptions['proxyAuthorization'];
        }

        $proxyAuthMethod = self::normalizeProxyAuthMethod($httpOptions['proxyAuthMethod'] ?? null);

        $helper = null;
        if (array_key_exists('proxyCredentialHelper', $httpOptions) && $httpOptions['proxyCredentialHelper'] !== null) {
            if (!is_callable($httpOptions['proxyCredentialHelper'])) {
                throw new \InvalidArgumentException('smart HTTP receive-pack proxy credential helper must be callable');
            }
            $helper = $httpOptions['proxyCredentialHelper'];
        }
        $store = null;
        if (array_key_exists('proxyCredentialStore', $httpOptions) && $httpOptions['proxyCredentialStore'] !== null) {
            if (!is_callable($httpOptions['proxyCredentialStore'])) {
                throw new \InvalidArgumentException('smart HTTP receive-pack proxy credential store must be callable');
            }
            $store = $httpOptions['proxyCredentialStore'];
        }
        $erase = null;
        if (array_key_exists('proxyCredentialErase', $httpOptions) && $httpOptions['proxyCredentialErase'] !== null) {
            if (!is_callable($httpOptions['proxyCredentialErase'])) {
                throw new \InvalidArgumentException('smart HTTP receive-pack proxy credential erase must be callable');
            }
            $erase = $httpOptions['proxyCredentialErase'];
        }

        $sslCaInfo = null;
        if (array_key_exists('sslCaInfo', $httpOptions) && $httpOptions['sslCaInfo'] !== null && $httpOptions['sslCaInfo'] !== '') {
            if (!is_string($httpOptions['sslCaInfo'])
                || self::containsControlByte($httpOptions['sslCaInfo'])
            ) {
                throw new \InvalidArgumentException('smart HTTP receive-pack sslCaInfo must be a path string without control bytes');
            }
            if (!is_file($httpOptions['sslCaInfo']) || !is_readable($httpOptions['sslCaInfo'])) {
                throw new \InvalidArgumentException('smart HTTP receive-pack sslCaInfo must point to a readable CA file');
            }
            $sslCaInfo = $httpOptions['sslCaInfo'];
        }

        $sslVerify = true;
        if (array_key_exists('sslVerify', $httpOptions)) {
            if (!is_bool($httpOptions['sslVerify'])) {
                throw new \InvalidArgumentException('smart HTTP receive-pack sslVerify must be a boolean');
            }
            $sslVerify = $httpOptions['sslVerify'];
        }

        return [
            'proxyConfigured' => $proxyConfigured,
            'proxy' => $proxy,
            'httpsProxyConfigured' => $httpsProxyConfigured,
            'httpsProxy' => $httpsProxy,
            'allProxyConfigured' => $allProxyConfigured,
            'allProxy' => $allProxy,
            'noProxy' => self::normalizeNoProxy($httpOptions['noProxy'] ?? null),
            'proxyAuthorization' => $proxyAuthorization,
            'proxyAuthMethod' => $proxyAuthMethod,
            'proxyCredentialHelper' => $helper,
            'proxyCredentialStore' => $store,
            'proxyCredentialErase' => $erase,
            'sslCaInfo' => $sslCaInfo,
            'sslVerify' => $sslVerify,
            'followRedirects' => self::normalizeFollowRedirects($httpOptions['followRedirects'] ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $httpOptions
     * @return array{0: bool, 1: ?array{type: string, stream: string, url: string, credentialUrl: string, authorization: ?string, username: ?string}}
     */
    private static function normalizeConfiguredProxy(array $httpOptions, string $name): array
    {
        if (!array_key_exists($name, $httpOptions)) {
            return [false, null];
        }

        $proxy = $httpOptions[$name];
        if ($proxy === null || $proxy === '') {
            return [true, null];
        }
        if (!is_string($proxy)) {
            throw new \InvalidArgumentException("smart HTTP receive-pack {$name} must be a string");
        }

        return [true, self::normalizeProxy($proxy)];
    }

    private static function normalizeFollowRedirects(mixed $followRedirects): string
    {
        if ($followRedirects === null || $followRedirects === '') {
            return 'initial';
        }
        if (is_bool($followRedirects)) {
            return $followRedirects ? 'all' : 'none';
        }
        if (!is_string($followRedirects)) {
            throw new \InvalidArgumentException('smart HTTP receive-pack followRedirects must be initial, true, or false');
        }

        $normalized = strtolower(trim($followRedirects));
        return match ($normalized) {
            'initial' => 'initial',
            'true', '1', 'yes', 'on', 'all' => 'all',
            'false', '0', 'no', 'off', 'none' => 'none',
            default => throw new \InvalidArgumentException('smart HTTP receive-pack followRedirects must be initial, true, or false'),
        };
    }

    /**
     * @return array{type: string, stream: string, url: string, credentialUrl: string, authorization: ?string, username: ?string}
     */
    private static function normalizeProxy(string $proxy): array
    {
        if ($proxy === '' || self::containsControlByte($proxy)) {
            throw new \InvalidArgumentException('smart HTTP receive-pack proxy must be non-empty and must not contain control bytes');
        }

        $parseTarget = str_contains($proxy, '://') ? $proxy : '//' . $proxy;
        try {
            $parts = parse_url($parseTarget);
        } catch (\ValueError $error) {
            throw new \InvalidArgumentException('smart HTTP receive-pack proxy could not be parsed', 0, $error);
        }
        if (!is_array($parts)) {
            throw new \InvalidArgumentException('smart HTTP receive-pack proxy could not be parsed');
        }

        $scheme = strtolower(str_contains($proxy, '://') ? (string) ($parts['scheme'] ?? '') : 'http');
        $type = match (true) {
            $scheme === 'http', $scheme === 'https' => $scheme,
            $scheme === 'socks' => 'socks4',
            in_array($scheme, ['socks4', 'socks4a', 'socks5', 'socks5h'], true) => $scheme,
            default => null,
        };
        if ($type === null) {
            throw new \InvalidArgumentException('smart HTTP receive-pack proxy must use http, https, socks4, socks4a, socks5, or socks5h');
        }
        if (!isset($parts['host']) || !is_string($parts['host']) || $parts['host'] === '') {
            throw new \InvalidArgumentException('smart HTTP receive-pack proxy must include a host');
        }
        if (isset($parts['path']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new \InvalidArgumentException('smart HTTP receive-pack proxy must not include a path, query, or fragment');
        }

        $host = self::validateAuthorityHost($parts['host'], 'smart HTTP receive-pack proxy host');
        $explicitPort = isset($parts['port']);
        $port = $explicitPort ? (int) $parts['port'] : self::defaultProxyPort($type);
        $streamAuthority = self::authority($host, $port);
        $urlAuthority = self::authority($host, $explicitPort ? $port : null);
        $credentialPort = $explicitPort && $port !== self::defaultProxyPort($type) ? $port : null;
        $credentialUrlAuthority = self::authority($host, $credentialPort);
        $authorization = null;
        $username = null;
        if (isset($parts['user'])) {
            $username = rawurldecode((string) $parts['user']);
            if (self::containsControlByte($username)) {
                throw new \InvalidArgumentException('smart HTTP receive-pack proxy credentials must not contain control bytes');
            }
            $encodedUsername = rawurlencode($username);
            if (array_key_exists('pass', $parts)) {
                $password = rawurldecode((string) $parts['pass']);
                $authorization = self::basicAuthorization(
                    $username,
                    $password,
                    'smart HTTP receive-pack proxy credentials',
                );
                $credentialUrlAuthority = $encodedUsername . ':' . rawurlencode($password) . '@' . $credentialUrlAuthority;
            } else {
                $credentialUrlAuthority = $encodedUsername . '@' . $credentialUrlAuthority;
                $urlAuthority = $encodedUsername . '@' . $urlAuthority;
            }
        }

        return [
            'type' => $type,
            'stream' => 'tcp://' . $streamAuthority,
            'url' => $type . '://' . $urlAuthority,
            'credentialUrl' => $type . '://' . $credentialUrlAuthority,
            'authorization' => $authorization,
            'username' => $username,
        ];
    }

    private static function defaultProxyPort(string $type): int
    {
        return match ($type) {
            'https' => 443,
            'socks4', 'socks4a', 'socks5', 'socks5h' => 1080,
            default => 80,
        };
    }

    private static function normalizeProxyAuthMethod(mixed $method): string
    {
        if ($method === null || $method === '') {
            return 'anyauth';
        }
        if (!is_string($method)) {
            throw new \InvalidArgumentException('smart HTTP receive-pack proxy auth method must be a string');
        }
        $normalized = strtolower(str_replace(['-', '_'], '', $method));

        return match ($normalized) {
            'any', 'anyauth' => 'anyauth',
            'basic' => 'basic',
            'digest' => 'digest',
            'negotiate', 'gssnegotiate', 'gssapi' => 'negotiate',
            'ntlm' => 'ntlm',
            default => throw new \InvalidArgumentException('smart HTTP receive-pack proxy auth method is not supported'),
        };
    }

    /**
     * @return list<string>
     */
    private static function normalizeNoProxy(mixed $noProxy): array
    {
        if ($noProxy === null || $noProxy === '') {
            return [];
        }

        $items = is_array($noProxy) ? $noProxy : explode(',', (string) $noProxy);
        $patterns = [];
        foreach ($items as $item) {
            if (!is_string($item)) {
                throw new \InvalidArgumentException('smart HTTP receive-pack noProxy entries must be strings');
            }
            $pattern = trim($item);
            if ($pattern === '') {
                continue;
            }
            if (self::containsControlByte($pattern)) {
                throw new \InvalidArgumentException('smart HTTP receive-pack noProxy entries must not contain control bytes');
            }
            if ($pattern !== '*' && preg_match('/[\s\\\\]/', $pattern) === 1) {
                throw new \InvalidArgumentException('smart HTTP receive-pack noProxy entries must not contain whitespace or backslash delimiters');
            }
            if (str_contains($pattern, '/') && self::parseCidrPattern($pattern) === null) {
                throw new \InvalidArgumentException('smart HTTP receive-pack noProxy CIDR entries must include an IP address and valid prefix length');
            }
            $patterns[] = $pattern;
        }

        return $patterns;
    }

    /**
     * @param array{status: int, headers: array<string, string|list<string>>, body: string} $response
     * @param list<int> $allowedStatuses
     */
    private static function assertStatus(array $response, array $allowedStatuses, string $label): void
    {
        if (!in_array($response['status'], $allowedStatuses, true)) {
            throw new \RuntimeException("{$label} returned HTTP status {$response['status']}");
        }
    }

    /**
     * @param array{status: int, headers: array<string, string|list<string>>, body: string} $response
     */
    private static function assertContentType(array $response, string $expected, string $label): void
    {
        $actualValues = self::headerValues($response['headers'], 'content-type');
        foreach ($actualValues as $actual) {
            $actualType = strtolower(trim(explode(';', $actual, 2)[0]));
            if ($actualType === $expected) {
                return;
            }
        }

        $actual = $actualValues === [] ? null : $actualValues[count($actualValues) - 1];
        throw new \RuntimeException("{$label} returned unexpected content type " . ($actual ?? 'missing'));
    }

    /**
     * @param array<string, string|list<string>> $headers
     */
    private static function headerValue(array $headers, string $name): ?string
    {
        foreach ($headers as $headerName => $value) {
            if (strtolower((string) $headerName) !== $name) {
                continue;
            }

            if (is_array($value)) {
                return (string) end($value);
            }

            return (string) $value;
        }

        return null;
    }

    /**
     * @param array<string, string> $headers
     */
    private static function setHeader(array &$headers, string $name, string $value): void
    {
        $lowerName = strtolower($name);
        foreach (array_keys($headers) as $existingName) {
            if (strtolower((string) $existingName) === $lowerName) {
                unset($headers[$existingName]);
            }
        }

        $headers[$name] = $value;
    }

    /**
     * @param array<string, string> $headers
     */
    private static function removeHeader(array &$headers, string $name): void
    {
        $lowerName = strtolower($name);
        foreach (array_keys($headers) as $existingName) {
            if (strtolower((string) $existingName) === $lowerName) {
                unset($headers[$existingName]);
            }
        }
    }

    /**
     * @param array<string, string|list<string>> $headers
     */
    private function rememberCookies(array $headers, string $url, bool $useDefaultPath = false): void
    {
        foreach (self::headerValues($headers, 'set-cookie') as $setCookie) {
            $this->rememberCookie($setCookie, $url, $useDefaultPath);
        }
    }

    private function rememberCookie(string $setCookie, string $url, bool $useDefaultPath): void
    {
        [$pair, $attributes] = array_pad(explode(';', $setCookie, 2), 2, '');
        [$name, $value] = array_pad(explode('=', trim($pair), 2), 2, null);
        if ($value === null || !self::isCookieName($name) || !self::isCookieValue($value)) {
            return;
        }

        $scope = self::cookieScope($attributes, $url, $useDefaultPath);
        if ($scope === null) {
            return;
        }

        $key = self::cookieKey($name, $scope);
        if (self::expiresCookie($attributes)) {
            unset($this->cookies[$key]);

            return;
        }

        $this->cookies[$key] = [
            'name' => $name,
            'value' => $value,
            'domain' => $scope['domain'],
            'path' => $scope['path'],
            'secure' => $scope['secure'],
            'hostOnly' => $scope['hostOnly'],
        ];
    }

    /**
     * @return null|array{domain: string, path: string, secure: bool, hostOnly: bool}
     */
    private static function cookieScope(string $attributes, string $url, bool $useDefaultPath): ?array
    {
        $request = self::httpUrlParts($url, 'smart HTTP receive-pack cookie URL');
        $domain = self::normalizeCookieHost($request['host']);
        $path = $useDefaultPath ? self::defaultCookiePath($request['path'] ?? '/') : '/';
        $secure = false;
        $hostOnly = true;

        foreach (explode(';', $attributes) as $attribute) {
            if (trim($attribute) === '') {
                continue;
            }

            [$name, $rawValue] = array_pad(explode('=', $attribute, 2), 2, '');
            $name = strtolower(trim($name));
            $value = trim($rawValue);
            if ($name === 'secure') {
                $secure = true;
                continue;
            }
            if ($name === 'domain') {
                $candidate = strtolower(ltrim($value, '.'));
                if ($candidate === '' || self::containsControlByte($candidate) || preg_match('/[\s\/\\\\]/', $candidate) === 1) {
                    return null;
                }
                if (!self::domainMatches($domain, $candidate)) {
                    return null;
                }
                $domain = $candidate;
                $hostOnly = false;
                continue;
            }
            if ($name === 'path') {
                $pathValue = trim($rawValue, " \t");
                if ($pathValue === '' || !str_starts_with($pathValue, '/') || self::containsControlByte($pathValue)) {
                    return null;
                }
                $path = $pathValue;
            }
        }

        return [
            'domain' => $domain,
            'path' => $path,
            'secure' => $secure,
            'hostOnly' => $hostOnly,
        ];
    }

    private static function domainMatches(string $host, string $domain): bool
    {
        return $host === $domain || str_ends_with($host, '.' . $domain);
    }

    private static function normalizeCookieHost(string $host): string
    {
        return rtrim(strtolower(trim($host, '[]')), '.');
    }

    private static function defaultCookiePath(string $requestPath): string
    {
        if ($requestPath === '' || $requestPath[0] !== '/') {
            return '/';
        }

        $lastSlash = strrpos($requestPath, '/');
        if ($lastSlash === false || $lastSlash === 0) {
            return '/';
        }

        return substr($requestPath, 0, $lastSlash);
    }

    private static function pathMatches(string $requestPath, string $cookiePath): bool
    {
        return $requestPath === $cookiePath
            || str_starts_with($requestPath, rtrim($cookiePath, '/') . '/');
    }

    /**
     * @param array{domain: string, path: string, secure: bool, hostOnly: bool} $scope
     */
    private static function cookieKey(string $name, array $scope): string
    {
        return ($scope['hostOnly'] ? 'host' : 'domain')
            . "\0" . $scope['domain']
            . "\0" . $scope['path']
            . "\0" . $name;
    }

    private static function expiresCookie(string $attributes): bool
    {
        $expiresAt = null;
        foreach (explode(';', $attributes) as $attribute) {
            $attribute = trim($attribute);
            if ($attribute === '') {
                continue;
            }

            [$name, $value] = array_pad(explode('=', $attribute, 2), 2, '');
            $name = strtolower(trim($name));
            $value = trim($value);
            if ($name === 'max-age' && preg_match('/^-?\d+$/', $value) === 1 && (int) $value <= 0) {
                return true;
            }
            if ($name === 'max-age' && preg_match('/^-?\d+$/', $value) === 1) {
                return false;
            }
            if ($name === 'expires') {
                $timestamp = strtotime($value);
                if ($timestamp !== false) {
                    $expiresAt = $timestamp;
                }
            }
        }

        return $expiresAt !== null && $expiresAt <= time();
    }

    /**
     * @param array<string, string|list<string>> $headers
     * @return list<string>
     */
    private static function headerValues(array $headers, string $name): array
    {
        $values = [];
        foreach ($headers as $headerName => $value) {
            if (strtolower((string) $headerName) !== $name) {
                continue;
            }

            foreach ((array) $value as $item) {
                $values[] = (string) $item;
            }
        }

        return $values;
    }

    /**
     * @param array<string, array{name: string, value: string, domain: string, path: string, secure: bool, hostOnly: bool}> $cookies
     */
    private static function cookieHeader(array $cookies, string $url, ?string $base = null): ?string
    {
        $parts = [];
        if ($base !== null && $base !== '') {
            $parts[] = $base;
        }
        $request = self::httpUrlParts($url, 'smart HTTP receive-pack cookie request URL');
        $requestHost = self::normalizeCookieHost($request['host']);
        $requestPath = $request['path'] ?? '/';
        $matchingCookies = [];
        $position = 0;
        foreach ($cookies as $cookie) {
            $domainMatch = $cookie['hostOnly']
                ? $requestHost === $cookie['domain']
                : self::domainMatches($requestHost, $cookie['domain']);
            if (!$domainMatch || !self::pathMatches($requestPath, $cookie['path'])) {
                $position++;
                continue;
            }
            if ($cookie['secure'] && $request['scheme'] !== 'https') {
                $position++;
                continue;
            }
            $matchingCookies[] = [$cookie, $position++];
        }
        usort(
            $matchingCookies,
            static fn (array $left, array $right): int => strlen($right[0]['path']) <=> strlen($left[0]['path'])
                ?: $left[1] <=> $right[1]
        );
        foreach ($matchingCookies as [$cookie]) {
            $parts[] = "{$cookie['name']}={$cookie['value']}";
        }

        return $parts === [] ? null : implode('; ', $parts);
    }

    private static function isCookieName(string $name): bool
    {
        return $name !== '' && preg_match('/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/', $name) === 1;
    }

    private static function isCookieValue(string $value): bool
    {
        return preg_match('/^[^\x00-\x1f\x7f;]*$/', $value) === 1;
    }

    private static function stripServiceAdvertisement(string $body): string
    {
        $length = self::packetLengthAt($body, 0, 'smart HTTP receive-pack service header');

        $serviceLine = substr($body, 4, $length - 4);
        if (!str_starts_with($serviceLine, '# service=')) {
            return $body;
        }

        if (rtrim($serviceLine, "\n") !== '# service=git-receive-pack') {
            throw new \RuntimeException('smart HTTP receive-pack advertisement service header did not match git-receive-pack');
        }
        if (strlen($body) < $length + 4) {
            throw new \RuntimeException('smart HTTP receive-pack advertisement ended before service header flush');
        }
        if (substr($body, $length, 4) !== '0000') {
            throw new \RuntimeException('smart HTTP receive-pack advertisement missing service header flush');
        }

        return substr($body, $length + 4);
    }

    private static function packetLengthAt(string $bytes, int $offset, string $label): int
    {
        if (strlen($bytes) < $offset + 4) {
            throw new \RuntimeException("{$label} is missing a pkt-line length");
        }

        $header = substr($bytes, $offset, 4);
        if (preg_match('/^[0-9a-f]{4}$/', $header) !== 1) {
            throw new \RuntimeException("{$label} has an invalid pkt-line length {$header}");
        }

        $length = hexdec($header);
        if ($length < 4) {
            throw new \RuntimeException("{$label} has an invalid pkt-line length {$header}");
        }
        if (strlen($bytes) < $offset + $length) {
            throw new \RuntimeException("{$label} has a truncated pkt-line payload");
        }

        return $length;
    }

    /**
     * @param array<string, string> $headers
     * @return array{status: int, headers: array<string, list<string>>, body: string}
     */
    private static function performHttpRequest(string $method, string $url, array $headers, ?string $body, float $timeout, array $httpOptions = []): array
    {
        if (isset($httpOptions['proxy'])) {
            $proxyType = strtolower((string) ($httpOptions['proxyType'] ?? 'http'));
            if ($proxyType !== 'http' && $proxyType !== 'https') {
                return self::performSocksHttpRequest($method, $url, $headers, $body, $timeout, $httpOptions);
            }
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            self::validateHeader($name, $value);
            $headerLines[] = $name . ': ' . $value;
        }
        if (isset($httpOptions['proxyAuthorization'])) {
            $proxyAuthorization = (string) $httpOptions['proxyAuthorization'];
            self::validateHeader('Proxy-Authorization', $proxyAuthorization);
            $headerLines[] = 'Proxy-Authorization: ' . $proxyAuthorization;
        }

        $options = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headerLines),
                'follow_location' => 0,
                'ignore_errors' => true,
                'timeout' => $timeout,
            ],
        ];
        if ($body !== null) {
            $options['http']['content'] = $body;
        }
        if (isset($httpOptions['proxy'])) {
            $options['http']['proxy'] = (string) $httpOptions['proxy'];
            $options['http']['request_fulluri'] = !empty($httpOptions['requestFullUri']);
        }
        $sslOptions = self::sslStreamContextOptions(self::httpUrlParts($url, 'smart HTTP receive-pack request URL'), $httpOptions);
        if ($sslOptions !== []) {
            $options['ssl'] = $sslOptions;
        }

        $context = stream_context_create($options);
        $responseBody = @file_get_contents($url, false, $context);
        if ($responseBody === false) {
            throw new \RuntimeException("smart HTTP receive-pack {$method} request failed for {$url}");
        }

        $responseHeaderLines = $http_response_header ?? [];

        return [
            'status' => self::statusFromHeaderLines($responseHeaderLines),
            'headers' => self::headersFromHeaderLines($responseHeaderLines),
            'body' => $responseBody,
        ];
    }

    /**
     * @param array<string, string> $headers
     * @return array{status: int, headers: array<string, list<string>>, body: string}
     */
    private static function performSocksHttpRequest(string $method, string $url, array $headers, ?string $body, float $timeout, array $httpOptions): array
    {
        $target = self::httpUrlParts($url, 'smart HTTP receive-pack SOCKS target URL');
        $stream = self::openSocksTunnel($target, $timeout, $httpOptions);
        try {
            $requestBytes = self::httpRequestBytes($method, $url, $headers, $body);
            self::writeAll($stream, $requestBytes, 'smart HTTP receive-pack SOCKS HTTP request');
            $responseBytes = stream_get_contents($stream);
            if ($responseBytes === false) {
                throw new \RuntimeException("smart HTTP receive-pack {$method} SOCKS request failed for {$url}");
            }
        } finally {
            fclose($stream);
        }

        return self::parseHttpResponseBytes($responseBytes, "smart HTTP receive-pack {$method} SOCKS response");
    }

    /**
     * @param array{scheme: string, host: string, port: ?int, path?: string, query?: string, user?: string, pass?: string, fragment?: string} $target
     * @param array<string, mixed> $httpOptions
     * @return resource
     */
    private static function openSocksTunnel(array $target, float $timeout, array $httpOptions): mixed
    {
        $proxy = (string) ($httpOptions['proxy'] ?? '');
        $proxyType = strtolower((string) ($httpOptions['proxyType'] ?? ''));
        if ($proxy === '' || !in_array($proxyType, ['socks4', 'socks4a', 'socks5', 'socks5h'], true)) {
            throw new \RuntimeException('smart HTTP receive-pack SOCKS proxy options are incomplete');
        }

        $context = stream_context_create(['ssl' => self::sslStreamContextOptions($target, $httpOptions)]);
        $stream = @stream_socket_client($proxy, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        if (!is_resource($stream)) {
            throw new \RuntimeException("smart HTTP receive-pack SOCKS proxy connection failed for {$proxy}: {$errstr}", $errno);
        }

        stream_set_timeout($stream, max(1, (int) ceil($timeout)));
        try {
            $port = self::portOrDefault($target);
            if ($proxyType === 'socks4' || $proxyType === 'socks4a') {
                self::performSocks4Handshake($stream, $proxyType, $target['host'], $port, $httpOptions);
            } else {
                self::performSocks5Handshake($stream, $proxyType, $target['host'], $port, $httpOptions);
            }

            if ($target['scheme'] === 'https') {
                $enabled = @stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if ($enabled !== true) {
                    throw new \RuntimeException('smart HTTP receive-pack SOCKS TLS negotiation failed');
                }
            }
        } catch (\Throwable $throwable) {
            fclose($stream);

            throw $throwable;
        }

        return $stream;
    }

    /**
     * @param array{scheme: string, host: string, port: ?int, path?: string, query?: string, user?: string, pass?: string, fragment?: string} $target
     * @param array<string, mixed> $httpOptions
     * @return array<string, mixed>
     */
    private static function sslStreamContextOptions(array $target, array $httpOptions): array
    {
        if ($target['scheme'] !== 'https') {
            return [];
        }

        $verify = !array_key_exists('sslVerify', $httpOptions) || $httpOptions['sslVerify'] !== false;
        $options = [
            'peer_name' => trim($target['host'], '[]'),
            'verify_peer' => $verify,
            'verify_peer_name' => $verify,
            'allow_self_signed' => !$verify,
        ];
        if (isset($httpOptions['sslCaInfo'])) {
            $options['cafile'] = (string) $httpOptions['sslCaInfo'];
        }

        return $options;
    }

    /**
     * @param resource $stream
     * @param array<string, mixed> $httpOptions
     */
    private static function performSocks4Handshake(mixed $stream, string $proxyType, string $host, int $port, array $httpOptions): void
    {
        $credentials = self::basicCredentialsFromAuthorization($httpOptions['proxyAuthorization'] ?? null);
        $userId = $credentials['username'] ?? '';
        if (str_contains($userId, "\0")) {
            throw new \RuntimeException('smart HTTP receive-pack SOCKS4 username must not contain NUL bytes');
        }

        $host = trim($host, '[]');
        $address = @inet_pton($host);
        $remoteName = '';
        if ($address === false || strlen($address) !== 4) {
            if ($proxyType === 'socks4a') {
                $address = "\x00\x00\x00\x01";
                $remoteName = self::socksDomainName($host, 'smart HTTP receive-pack SOCKS4a host');
            } else {
                $resolved = gethostbyname($host);
                $address = @inet_pton($resolved);
                if ($address === false || strlen($address) !== 4) {
                    throw new \RuntimeException("smart HTTP receive-pack SOCKS4 could not resolve {$host} to an IPv4 address");
                }
            }
        }

        self::writeAll(
            $stream,
            "\x04\x01" . pack('n', $port) . $address . $userId . "\x00" . ($remoteName === '' ? '' : $remoteName . "\x00"),
            'smart HTTP receive-pack SOCKS4 handshake',
        );
        $reply = self::readExact($stream, 8, 'smart HTTP receive-pack SOCKS4 reply');
        if (ord($reply[1]) !== 0x5a) {
            throw new \RuntimeException('smart HTTP receive-pack SOCKS4 proxy rejected CONNECT request with status 0x' . bin2hex($reply[1]));
        }
    }

    /**
     * @param resource $stream
     * @param array<string, mixed> $httpOptions
     */
    private static function performSocks5Handshake(mixed $stream, string $proxyType, string $host, int $port, array $httpOptions): void
    {
        $credentials = self::basicCredentialsFromAuthorization($httpOptions['proxyAuthorization'] ?? null);
        $methods = $credentials === null ? "\x00" : "\x00\x02";
        self::writeAll(
            $stream,
            "\x05" . chr(strlen($methods)) . $methods,
            'smart HTTP receive-pack SOCKS5 greeting',
        );

        $selection = self::readExact($stream, 2, 'smart HTTP receive-pack SOCKS5 method selection');
        if ($selection[0] !== "\x05") {
            throw new \RuntimeException('smart HTTP receive-pack SOCKS5 proxy returned an invalid method selection');
        }
        $method = ord($selection[1]);
        if ($method === 0x02) {
            if ($credentials === null) {
                throw new \RuntimeException('smart HTTP receive-pack SOCKS5 proxy requested credentials but none were available');
            }
            self::performSocks5UsernamePasswordAuth($stream, $credentials['username'], $credentials['password']);
        } elseif ($method !== 0x00) {
            throw new \RuntimeException('smart HTTP receive-pack SOCKS5 proxy did not accept supported authentication methods');
        }

        $remoteDns = $proxyType === 'socks5h';
        self::writeAll(
            $stream,
            "\x05\x01\x00" . self::socks5AddressBytes($host, $remoteDns) . pack('n', $port),
            'smart HTTP receive-pack SOCKS5 CONNECT request',
        );

        $reply = self::readExact($stream, 4, 'smart HTTP receive-pack SOCKS5 CONNECT reply');
        if ($reply[0] !== "\x05") {
            throw new \RuntimeException('smart HTTP receive-pack SOCKS5 proxy returned an invalid CONNECT reply');
        }
        $status = ord($reply[1]);
        if ($status !== 0x00) {
            throw new \RuntimeException('smart HTTP receive-pack SOCKS5 proxy rejected CONNECT request with status 0x' . bin2hex($reply[1]));
        }

        $addressLength = match (ord($reply[3])) {
            0x01 => 4,
            0x03 => ord(self::readExact($stream, 1, 'smart HTTP receive-pack SOCKS5 bound host length')),
            0x04 => 16,
            default => throw new \RuntimeException('smart HTTP receive-pack SOCKS5 proxy returned an invalid bound address type'),
        };
        self::readExact($stream, $addressLength + 2, 'smart HTTP receive-pack SOCKS5 bound address');
    }

    /**
     * @param resource $stream
     */
    private static function performSocks5UsernamePasswordAuth(mixed $stream, string $username, string $password): void
    {
        if (strlen($username) > 255 || strlen($password) > 255) {
            throw new \RuntimeException('smart HTTP receive-pack SOCKS5 credentials must fit in one byte length fields');
        }
        self::writeAll(
            $stream,
            "\x01" . chr(strlen($username)) . $username . chr(strlen($password)) . $password,
            'smart HTTP receive-pack SOCKS5 username/password authentication',
        );
        $reply = self::readExact($stream, 2, 'smart HTTP receive-pack SOCKS5 username/password authentication reply');
        if ($reply !== "\x01\x00") {
            throw new \RuntimeException('smart HTTP receive-pack SOCKS5 proxy rejected username/password credentials');
        }
    }

    private static function socks5AddressBytes(string $host, bool $remoteDns): string
    {
        $host = trim($host, '[]');
        $address = @inet_pton($host);
        if ($address !== false) {
            return strlen($address) === 4
                ? "\x01" . $address
                : "\x04" . $address;
        }

        if (!$remoteDns) {
            $resolved = gethostbyname($host);
            $address = @inet_pton($resolved);
            if ($address !== false && strlen($address) === 4) {
                return "\x01" . $address;
            }

            throw new \RuntimeException("smart HTTP receive-pack SOCKS5 could not resolve {$host} locally");
        }

        $domain = self::socksDomainName($host, 'smart HTTP receive-pack SOCKS5 host');

        return "\x03" . chr(strlen($domain)) . $domain;
    }

    private static function socksDomainName(string $host, string $label): string
    {
        if ($host === '' || strlen($host) > 255 || str_contains($host, "\0") || str_contains($host, "\r") || str_contains($host, "\n")) {
            throw new \RuntimeException("{$label} must be 1 to 255 bytes without control bytes");
        }

        return $host;
    }

    /**
     * @return ?array{username: string, password: string}
     */
    private static function basicCredentialsFromAuthorization(mixed $authorization): ?array
    {
        if (!is_string($authorization) || stripos($authorization, 'Basic ') !== 0) {
            return null;
        }

        $decoded = base64_decode(substr($authorization, 6), true);
        if (!is_string($decoded) || !str_contains($decoded, ':')) {
            return null;
        }

        [$username, $password] = explode(':', $decoded, 2);

        return ['username' => $username, 'password' => $password];
    }

    /**
     * @param resource $stream
     */
    private static function writeAll(mixed $stream, string $bytes, string $label): void
    {
        $offset = 0;
        $length = strlen($bytes);
        while ($offset < $length) {
            $written = fwrite($stream, substr($bytes, $offset));
            if ($written === false || $written === 0) {
                throw new \RuntimeException("{$label} could not be written");
            }
            $offset += $written;
        }
    }

    /**
     * @param resource $stream
     */
    private static function readExact(mixed $stream, int $length, string $label): string
    {
        $bytes = '';
        while (strlen($bytes) < $length && !feof($stream)) {
            $chunk = fread($stream, $length - strlen($bytes));
            if ($chunk === false) {
                throw new \RuntimeException("{$label} could not be read");
            }
            if ($chunk === '') {
                $meta = stream_get_meta_data($stream);
                if (!empty($meta['timed_out'])) {
                    throw new \RuntimeException("{$label} timed out");
                }
                continue;
            }
            $bytes .= $chunk;
        }
        if (strlen($bytes) !== $length) {
            throw new \RuntimeException("{$label} ended before {$length} bytes were read");
        }

        return $bytes;
    }

    /**
     * @param array<string, string> $headers
     */
    private static function httpRequestBytes(string $method, string $url, array $headers, ?string $body): string
    {
        if (preg_match('/^[A-Z]+$/', $method) !== 1) {
            throw new \RuntimeException('smart HTTP receive-pack HTTP method is invalid');
        }

        $target = self::httpUrlParts($url, 'smart HTTP receive-pack HTTP request URL');
        if (self::headerValue($headers, 'host') === null) {
            self::setHeader($headers, 'Host', self::authority($target['host'], $target['port']));
        }
        if (self::headerValue($headers, 'connection') === null) {
            self::setHeader($headers, 'Connection', 'close');
        }

        $path = $target['path'] ?? '/';
        if ($path === '') {
            $path = '/';
        }
        if (isset($target['query'])) {
            $path .= '?' . $target['query'];
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            self::validateHeader($name, $value);
            $headerLines[] = $name . ': ' . $value;
        }

        return $method . ' ' . $path . " HTTP/1.1\r\n"
            . implode("\r\n", $headerLines)
            . "\r\n\r\n"
            . ($body ?? '');
    }

    /**
     * @return array{status: int, headers: array<string, list<string>>, body: string}
     */
    private static function parseHttpResponseBytes(string $responseBytes, string $label): array
    {
        $headerEnd = strpos($responseBytes, "\r\n\r\n");
        $separatorLength = 4;
        if ($headerEnd === false) {
            $headerEnd = strpos($responseBytes, "\n\n");
            $separatorLength = 2;
        }
        if ($headerEnd === false) {
            throw new \RuntimeException("{$label} did not contain an HTTP header terminator");
        }

        $headerBlock = substr($responseBytes, 0, $headerEnd);
        $body = substr($responseBytes, $headerEnd + $separatorLength);
        $lines = preg_split('/\r\n|\n|\r/', $headerBlock) ?: [];
        $status = self::statusFromHeaderLines($lines);
        if ($status === 0) {
            throw new \RuntimeException("{$label} did not contain an HTTP status line");
        }
        $headers = self::headersFromHeaderLines($lines);
        $transferEncoding = self::headerValue($headers, 'transfer-encoding');
        if ($transferEncoding !== null && strtolower(trim($transferEncoding)) === 'chunked') {
            $body = self::decodeChunkedBody($body, $label);
        }

        return [
            'status' => $status,
            'headers' => $headers,
            'body' => $body,
        ];
    }

    private static function decodeChunkedBody(string $body, string $label): string
    {
        $decoded = '';
        $offset = 0;
        while (true) {
            $lineEnd = strpos($body, "\r\n", $offset);
            if ($lineEnd === false) {
                throw new \RuntimeException("{$label} has a truncated chunked body");
            }
            $line = substr($body, $offset, $lineEnd - $offset);
            $chunkSizeHex = trim(explode(';', $line, 2)[0]);
            if ($chunkSizeHex === '' || preg_match('/^[0-9a-fA-F]+$/', $chunkSizeHex) !== 1) {
                throw new \RuntimeException("{$label} has an invalid chunk size");
            }
            $chunkSize = hexdec($chunkSizeHex);
            $offset = $lineEnd + 2;
            if ($chunkSize === 0) {
                return $decoded;
            }
            if (strlen($body) < $offset + $chunkSize + 2) {
                throw new \RuntimeException("{$label} has a truncated chunk payload");
            }
            $decoded .= substr($body, $offset, $chunkSize);
            $offset += $chunkSize;
            if (substr($body, $offset, 2) !== "\r\n") {
                throw new \RuntimeException("{$label} chunk payload is missing its terminator");
            }
            $offset += 2;
        }
    }

    private static function validateHeader(string $name, string $value): void
    {
        if (preg_match('/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/', $name) !== 1) {
            throw new \InvalidArgumentException('smart HTTP receive-pack header name is invalid');
        }
        if (self::containsControlByte($value)) {
            throw new \InvalidArgumentException('smart HTTP receive-pack header value is invalid');
        }
    }

    private static function basicAuthorization(string $username, string $password, string $label): string
    {
        if (self::containsControlByte($username) || self::containsControlByte($password)) {
            throw new \InvalidArgumentException("{$label} must not contain control bytes");
        }

        return 'Basic ' . base64_encode($username . ':' . $password);
    }

    private static function containsControlByte(string $value): bool
    {
        return preg_match('/[\x00-\x1f\x7f]/', $value) === 1;
    }

    /**
     * @param list<string> $headerLines
     */
    private static function statusFromHeaderLines(array $headerLines): int
    {
        $status = 0;
        foreach ($headerLines as $line) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $line, $matches) === 1) {
                $status = (int) $matches[1];
            }
        }

        return $status;
    }

    /**
     * @param list<string> $headerLines
     * @return array<string, list<string>>
     */
    private static function headersFromHeaderLines(array $headerLines): array
    {
        $headers = [];
        foreach ($headerLines as $line) {
            if (str_starts_with($line, 'HTTP/')) {
                $headers = [];
                continue;
            }

            [$name, $value] = array_pad(explode(':', $line, 2), 2, null);
            if ($value === null) {
                continue;
            }

            $headers[strtolower(trim($name))][] = trim($value);
        }

        return $headers;
    }
}
