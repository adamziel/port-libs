<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class SmartHttpReceivePackTransport implements ReceivePackTransport
{
    private bool $advertisementRead = false;
    private bool $requestWritten = false;
    private bool $responseRead = false;
    private ?string $requestBytes = null;
    private readonly string $repositoryUrl;
    private readonly ?string $authorizationHeader;
    private readonly mixed $requester;
    /** @var array<string, string> */
    private array $cookies = [];
    /** @var array<string, string> */
    private readonly array $extraHeaders;

    /**
     * @param null|callable(string, string, array<string, string>, ?string, float): array{status: int, headers: array<string, string|list<string>>, body: string} $requester
     * @param list<string> $extraParameters
     * @param array<string, string> $extraHeaders
     */
    public function __construct(
        string $repositoryUrl,
        ?callable $requester = null,
        private readonly array $extraParameters = [],
        private readonly float $timeout = 30.0,
        array $extraHeaders = [],
    ) {
        if ($timeout <= 0.0) {
            throw new \InvalidArgumentException('smart HTTP receive-pack transport timeout must be greater than zero');
        }
        self::validateExtraParameters($extraParameters);
        $this->extraHeaders = self::normalizeExtraHeaders($extraHeaders);

        $target = self::normalizeRepositoryUrl($repositoryUrl);
        $this->repositoryUrl = $target['url'];
        $this->authorizationHeader = $target['authorization'];
        $this->requester = $requester ?? static fn (
            string $method,
            string $url,
            array $headers,
            ?string $body,
            float $timeout,
        ): array => self::performHttpRequest($method, $url, $headers, $body, $timeout);
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
        $response = $this->request('GET', self::infoRefsUrl($this->repositoryUrl), $this->advertisementHeaders(), null);
        self::assertStatus($response, [200, 304], 'smart HTTP receive-pack advertisement');
        self::assertContentType($response, 'application/x-git-receive-pack-advertisement', 'smart HTTP receive-pack advertisement');
        $this->rememberCookies($response['headers']);

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
        );
        self::assertStatus($response, [200], 'smart HTTP receive-pack result');
        self::assertContentType($response, 'application/x-git-receive-pack-result', 'smart HTTP receive-pack result');
        $this->rememberCookies($response['headers']);

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
        ]);
        if ($this->authorizationHeader !== null) {
            self::setHeader($headers, 'Authorization', $this->authorizationHeader);
        }
        if ($this->extraParameters !== []) {
            self::setHeader($headers, 'Git-Protocol', implode(':', $this->extraParameters));
        }
        $cookieHeader = self::cookieHeader($this->cookies, self::headerValue($headers, 'cookie'));
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
        ]);
        if ($this->authorizationHeader !== null) {
            self::setHeader($headers, 'Authorization', $this->authorizationHeader);
        }
        if ($this->extraParameters !== []) {
            self::setHeader($headers, 'Git-Protocol', implode(':', $this->extraParameters));
        }
        $cookieHeader = self::cookieHeader($this->cookies, self::headerValue($headers, 'cookie'));
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

    /**
     * @param array<string, string> $headers
     * @return array{status: int, headers: array<string, string|list<string>>, body: string}
     */
    private function request(string $method, string $url, array $headers, ?string $body): array
    {
        $response = ($this->requester)($method, $url, $headers, $body, $this->timeout);
        if (!is_array($response)
            || !isset($response['status'], $response['headers'], $response['body'])
            || !is_int($response['status'])
            || !is_array($response['headers'])
            || !is_string($response['body'])
        ) {
            throw new \RuntimeException('smart HTTP receive-pack requester returned an invalid response shape');
        }

        return $response;
    }

    /**
     * @return array{url: string, authorization: ?string}
     */
    private static function normalizeRepositoryUrl(string $repositoryUrl): array
    {
        if ($repositoryUrl === '' || str_contains($repositoryUrl, "\0") || str_contains($repositoryUrl, "\r") || str_contains($repositoryUrl, "\n")) {
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

        $authority = self::authority($parts['host'], isset($parts['port']) ? (int) $parts['port'] : null);
        $url = $scheme . '://' . $authority . ($parts['path'] ?? '');
        if (isset($parts['query'])) {
            $url .= '?' . $parts['query'];
        }

        $authorization = null;
        if (isset($parts['user'])) {
            $user = rawurldecode((string) $parts['user']);
            $pass = rawurldecode((string) ($parts['pass'] ?? ''));
            $authorization = 'Basic ' . base64_encode($user . ':' . $pass);
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

    /**
     * @param list<string> $extraParameters
     */
    private static function validateExtraParameters(array $extraParameters): void
    {
        foreach ($extraParameters as $extraParameter) {
            if (!is_string($extraParameter)
                || $extraParameter === ''
                || str_contains($extraParameter, "\0")
                || str_contains($extraParameter, "\r")
                || str_contains($extraParameter, "\n")
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
        $actual = self::headerValue($response['headers'], 'content-type');
        $actualType = strtolower(trim(explode(';', $actual ?? '', 2)[0]));
        if ($actualType !== $expected) {
            throw new \RuntimeException("{$label} returned unexpected content type " . ($actual ?? 'missing'));
        }
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
     * @param array<string, string|list<string>> $headers
     */
    private function rememberCookies(array $headers): void
    {
        foreach (self::headerValues($headers, 'set-cookie') as $setCookie) {
            $this->rememberCookie($setCookie);
        }
    }

    private function rememberCookie(string $setCookie): void
    {
        [$pair] = explode(';', $setCookie, 2);
        [$name, $value] = array_pad(explode('=', trim($pair), 2), 2, null);
        if ($value === null || !self::isCookieName($name) || !self::isCookieValue($value)) {
            return;
        }

        $this->cookies[$name] = $value;
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
     * @param array<string, string> $cookies
     */
    private static function cookieHeader(array $cookies, ?string $base = null): ?string
    {
        $parts = [];
        if ($base !== null && $base !== '') {
            $parts[] = $base;
        }
        foreach ($cookies as $name => $value) {
            $parts[] = "{$name}={$value}";
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
        if ($length < 4 || strlen($body) < $length + 4) {
            throw new \RuntimeException('smart HTTP receive-pack advertisement ended before service header flush');
        }

        $serviceLine = substr($body, 4, $length - 4);
        if (rtrim($serviceLine, "\n") !== '# service=git-receive-pack') {
            throw new \RuntimeException('smart HTTP receive-pack advertisement service header did not match git-receive-pack');
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
    private static function performHttpRequest(string $method, string $url, array $headers, ?string $body, float $timeout): array
    {
        $headerLines = [];
        foreach ($headers as $name => $value) {
            self::validateHeader($name, $value);
            $headerLines[] = $name . ': ' . $value;
        }

        $options = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headerLines),
                'ignore_errors' => true,
                'timeout' => $timeout,
            ],
        ];
        if ($body !== null) {
            $options['http']['content'] = $body;
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

    private static function validateHeader(string $name, string $value): void
    {
        if (preg_match('/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/', $name) !== 1) {
            throw new \InvalidArgumentException('smart HTTP receive-pack header name is invalid');
        }
        if (str_contains($value, "\r") || str_contains($value, "\n") || str_contains($value, "\0")) {
            throw new \InvalidArgumentException('smart HTTP receive-pack header value is invalid');
        }
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
