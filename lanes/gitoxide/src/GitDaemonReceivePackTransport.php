<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class GitDaemonReceivePackTransport implements ReceivePackTransport
{
    private readonly StreamReceivePackTransport $streamTransport;

    /**
     * @param resource $readStream
     * @param resource $writeStream
     * @param list<string> $extraParameters
     */
    public function __construct(
        mixed $readStream,
        mixed $writeStream,
        string $repositoryPath,
        string $host,
        ?int $port = null,
        array $extraParameters = [],
        int $protocolVersion = 1,
        ?string $virtualHost = null,
        ?int $virtualPort = null,
    ) {
        if (!is_resource($readStream) || !is_resource($writeStream)) {
            throw new \InvalidArgumentException('git-daemon receive-pack transport expects readable and writable stream resources');
        }

        self::writeAll($writeStream, self::serviceRequestBytes(
            $repositoryPath,
            $host,
            $port,
            $extraParameters,
            $protocolVersion,
            $virtualHost,
            $virtualPort,
        ));
        if (!fflush($writeStream)) {
            throw new \RuntimeException('git-daemon receive-pack transport failed to flush service request');
        }

        $this->streamTransport = new StreamReceivePackTransport($readStream, $writeStream);
    }

    /**
     * @param list<string> $extraParameters
     */
    public static function connect(
        string $url,
        float $timeout = 30.0,
        array $extraParameters = [],
        int $protocolVersion = 1,
        ?string $virtualHost = null,
        ?int $virtualPort = null,
    ): self {
        if ($timeout <= 0.0) {
            throw new \InvalidArgumentException('git-daemon receive-pack transport timeout must be greater than zero');
        }

        $target = self::parseGitUrl($url);
        self::serviceRequestBytes(
            $target['path'],
            $target['host'],
            $target['port'],
            $extraParameters,
            $protocolVersion,
            $virtualHost,
            $virtualPort,
        );

        $connectPort = $target['port'] ?? 9418;
        $address = self::tcpAddress($target['host']);
        $errno = 0;
        $errstr = '';
        $stream = @stream_socket_client("tcp://{$address}:{$connectPort}", $errno, $errstr, $timeout);
        if ($stream === false) {
            $reason = $errstr !== '' ? "{$errstr} ({$errno})" : "error {$errno}";
            throw new \RuntimeException("git-daemon receive-pack transport failed to connect to {$target['host']}:{$connectPort}: {$reason}");
        }

        $seconds = (int) floor($timeout);
        $microseconds = (int) (($timeout - $seconds) * 1_000_000);
        stream_set_timeout($stream, $seconds, $microseconds);

        return new self(
            $stream,
            $stream,
            $target['path'],
            $target['host'],
            $target['port'],
            $extraParameters,
            $protocolVersion,
            $virtualHost,
            $virtualPort,
        );
    }

    /**
     * @param list<string> $extraParameters
     */
    public static function serviceRequestBytes(
        string $repositoryPath,
        string $host,
        ?int $port = null,
        array $extraParameters = [],
        int $protocolVersion = 1,
        ?string $virtualHost = null,
        ?int $virtualPort = null,
    ): string {
        self::validateRepositoryPath($repositoryPath);
        self::validateHost($host);
        self::validatePort($port);
        self::validateVirtualHostOverride($virtualHost, $virtualPort);
        self::validateExtraParameters($extraParameters);
        self::validateProtocolVersion($protocolVersion);

        $hostParameterHost = $virtualHost ?? $host;
        $hostParameterPort = $virtualHost === null ? $port : $virtualPort;
        $hostParameter = 'host=' . self::hostParameterValue($hostParameterHost, $hostParameterPort) . "\0";
        $payload = 'git-receive-pack ' . self::shellRepositoryPath($repositoryPath) . "\0{$hostParameter}";
        $extraParamsNeedNullPrefix = true;
        if ($protocolVersion !== 1) {
            $payload .= "\0version={$protocolVersion}\0";
            $extraParamsNeedNullPrefix = false;
        }
        if ($extraParameters !== []) {
            $payload .= ($extraParamsNeedNullPrefix ? "\0" : '') . implode("\0", $extraParameters) . "\0";
        }

        $length = strlen($payload) + 4;
        if ($length > 0xffff) {
            throw new \InvalidArgumentException('git-daemon receive-pack service request is too large for one pkt-line');
        }

        return sprintf('%04x', $length) . $payload;
    }

    /**
     * @param list<string> $extraParameters
     */
    public static function serviceRequestBytesForUrl(
        string $url,
        array $extraParameters = [],
        int $protocolVersion = 1,
        ?string $virtualHost = null,
        ?int $virtualPort = null,
    ): string {
        $target = self::parseGitUrl($url);

        return self::serviceRequestBytes(
            $target['path'],
            $target['host'],
            $target['port'],
            $extraParameters,
            $protocolVersion,
            $virtualHost,
            $virtualPort,
        );
    }

    public function readAdvertisement(): string
    {
        return $this->streamTransport->readAdvertisement();
    }

    public function writeRequest(string $requestBytes): void
    {
        $this->streamTransport->writeRequest($requestBytes);
    }

    public function readResponse(): string
    {
        return $this->streamTransport->readResponse();
    }

    /**
     * @return array{host: string, path: string, port: ?int}
     */
    private static function parseGitUrl(string $url): array
    {
        try {
            $parts = parse_url($url);
        } catch (\ValueError $error) {
            throw new \InvalidArgumentException('git-daemon receive-pack transport could not parse git URL', 0, $error);
        }

        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'git') {
            throw new \InvalidArgumentException('git-daemon receive-pack transport expects a git:// URL');
        }
        if (!isset($parts['host']) || !is_string($parts['host']) || $parts['host'] === '') {
            throw new \InvalidArgumentException('git-daemon receive-pack URL must include a host');
        }
        if (!isset($parts['path']) || !is_string($parts['path']) || $parts['path'] === '' || $parts['path'] === '/') {
            throw new \InvalidArgumentException('git-daemon receive-pack URL must include a repository path');
        }
        if (isset($parts['query']) || isset($parts['fragment']) || isset($parts['user']) || isset($parts['pass'])) {
            throw new \InvalidArgumentException('git-daemon receive-pack URL does not support user info, query, or fragment components');
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : null;

        return [
            'host' => self::decodeUrlComponent($parts['host'], 'host'),
            'path' => self::decodeUrlComponent($parts['path'], 'repository path'),
            'port' => $port,
        ];
    }

    private static function decodeUrlComponent(string $value, string $label): string
    {
        $decoded = rawurldecode($value);
        if ($decoded === '' || self::containsControlByte($decoded)) {
            throw new \InvalidArgumentException("git-daemon receive-pack {$label} must be non-empty and must not contain control bytes");
        }

        return $decoded;
    }

    private static function validateRepositoryPath(string $repositoryPath): void
    {
        if ($repositoryPath === '' || self::containsControlByte($repositoryPath)) {
            throw new \InvalidArgumentException('git-daemon receive-pack repository path must be non-empty and must not contain control bytes');
        }
        if (!str_starts_with($repositoryPath, '/')) {
            throw new \InvalidArgumentException('git-daemon receive-pack repository path must be an absolute URL path');
        }
    }

    private static function validateHost(string $host): void
    {
        if ($host === '' || self::containsControlByte($host)) {
            throw new \InvalidArgumentException('git-daemon receive-pack host must be non-empty and must not contain control bytes');
        }
        if (preg_match('/[\s\/\\\\]/', $host) === 1) {
            throw new \InvalidArgumentException('git-daemon receive-pack host must not contain whitespace, slash, or backslash delimiters');
        }
    }

    private static function validatePort(?int $port): void
    {
        if ($port !== null && ($port < 1 || $port > 65535)) {
            throw new \InvalidArgumentException('git-daemon receive-pack port must be between 1 and 65535');
        }
    }

    private static function validateVirtualHostOverride(?string $virtualHost, ?int $virtualPort): void
    {
        if ($virtualHost === null) {
            if ($virtualPort !== null) {
                throw new \InvalidArgumentException('git-daemon receive-pack virtual port requires a virtual host');
            }

            return;
        }

        self::validateHost($virtualHost);
        self::validatePort($virtualPort);
    }

    private static function validateProtocolVersion(int $protocolVersion): void
    {
        if ($protocolVersion !== 1 && $protocolVersion !== 2) {
            throw new \InvalidArgumentException('git-daemon receive-pack protocol version must be 1 or 2');
        }
    }

    /**
     * @param list<string> $extraParameters
     */
    private static function validateExtraParameters(array $extraParameters): void
    {
        foreach ($extraParameters as $extraParameter) {
            if (!is_string($extraParameter) || $extraParameter === '' || self::containsControlByte($extraParameter)) {
                throw new \InvalidArgumentException('git-daemon receive-pack extra parameters must be non-empty strings without control bytes');
            }
            if (preg_match('/^[A-Za-z][A-Za-z0-9-]*(?:=.+)?$/', $extraParameter) !== 1) {
                throw new \InvalidArgumentException('git-daemon receive-pack extra parameters must be protocol keys or key=value pairs with non-empty keys and values');
            }
        }
    }

    private static function containsControlByte(string $value): bool
    {
        return preg_match('/[\x00-\x1f\x7f]/', $value) === 1;
    }

    private static function hostParameterValue(string $host, ?int $port): string
    {
        $host = self::bracketIpv6Host($host);
        if ($port === null) {
            return $host;
        }

        return "{$host}:{$port}";
    }

    private static function tcpAddress(string $host): string
    {
        if (str_contains($host, ':') && !str_starts_with($host, '[')) {
            return "[{$host}]";
        }

        return $host;
    }

    private static function shellRepositoryPath(string $repositoryPath): string
    {
        if (preg_match('#^/(~[^/]*)((?:/.*)?)$#', $repositoryPath, $matches) !== 1) {
            return $repositoryPath;
        }

        $tail = $matches[2] === '' ? '/' : $matches[2];

        return $matches[1] . $tail;
    }

    private static function bracketIpv6Host(string $host): string
    {
        if (str_contains($host, ':') && !str_starts_with($host, '[')) {
            return "[{$host}]";
        }

        return $host;
    }

    /**
     * @param resource $stream
     */
    private static function writeAll(mixed $stream, string $bytes): void
    {
        $offset = 0;
        while ($offset < strlen($bytes)) {
            $written = fwrite($stream, substr($bytes, $offset));
            if ($written === false || $written === 0) {
                throw new \RuntimeException('git-daemon receive-pack transport failed while writing service request');
            }
            $offset += $written;
        }
    }
}
