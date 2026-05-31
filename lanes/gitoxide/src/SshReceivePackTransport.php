<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class SshReceivePackTransport implements ReceivePackTransport
{
    private readonly StreamReceivePackTransport $streamTransport;

    /**
     * @param resource $readStream
     * @param resource $writeStream
     */
    public function __construct(mixed $readStream, mixed $writeStream)
    {
        if (!is_resource($readStream) || !is_resource($writeStream)) {
            throw new \InvalidArgumentException('SSH receive-pack transport expects readable and writable stream resources');
        }

        $this->streamTransport = new StreamReceivePackTransport($readStream, $writeStream);
    }

    /**
     * @param callable(string, ?string, ?int, string, float): array{read: resource, write: resource} $connector
     * @param array{protocolVersion?: int} $options
     */
    public static function connect(string $url, callable $connector, float $timeout = 30.0, array $options = []): self
    {
        if ($timeout <= 0.0) {
            throw new \InvalidArgumentException('SSH receive-pack transport timeout must be greater than zero');
        }

        $target = self::parseRepositoryUrl($url);
        $connectorContext = self::connectorContextForTarget($target, self::normalizeConnectOptions($options));
        $streams = self::connectorAcceptsContext($connector)
            ? $connector(
                $target['host'],
                $target['user'],
                $target['port'],
                $connectorContext['command'],
                $timeout,
                $connectorContext,
            )
            : $connector(
                $target['host'],
                $target['user'],
                $target['port'],
                $connectorContext['command'],
                $timeout,
            );

        if (!is_array($streams)
            || !isset($streams['read'], $streams['write'])
            || !is_resource($streams['read'])
            || !is_resource($streams['write'])
        ) {
            throw new \RuntimeException('SSH receive-pack connector returned an invalid stream pair');
        }

        return new self($streams['read'], $streams['write']);
    }

    /**
     * @param array{protocolVersion?: int} $options
     * @return array{
     *     host: string,
     *     user: ?string,
     *     port: ?int,
     *     path: string,
     *     command: string,
     *     protocolVersion: int,
     *     environment: array<string, string>,
     *     sshArguments: list<string>,
     *     credentialContext: CredentialContext,
     *     redactedCredentialContext: string,
     *     authenticationBoundary: string
     * }
     */
    public static function connectorContext(string $url, array $options = []): array
    {
        return self::connectorContextForTarget(
            self::parseRepositoryUrl($url),
            self::normalizeConnectOptions($options),
        );
    }

    /**
     * @return array{host: string, user: ?string, port: ?int, path: string}
     */
    public static function parseRepositoryUrl(string $url): array
    {
        if ($url === '' || self::hasControlBytes($url)) {
            throw new \InvalidArgumentException('SSH receive-pack URL must be non-empty and must not contain control bytes');
        }

        if (str_starts_with(strtolower($url), 'ssh://')) {
            return self::parseSshUrl($url);
        }
        if (str_contains($url, '://')) {
            throw new \InvalidArgumentException('SSH receive-pack transport expects an ssh:// URL or scp-like SSH URL');
        }

        return self::parseScpLikeUrl($url);
    }

    public static function receivePackCommand(string $repositoryPath): string
    {
        self::validateRepositoryPath($repositoryPath);

        return 'git-receive-pack ' . self::shellQuote($repositoryPath);
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
     * @return array{host: string, user: ?string, port: ?int, path: string}
     */
    private static function parseSshUrl(string $url): array
    {
        try {
            $parts = parse_url($url);
        } catch (\ValueError $error) {
            throw new \InvalidArgumentException('SSH receive-pack transport could not parse repository URL', 0, $error);
        }

        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'ssh') {
            throw new \InvalidArgumentException('SSH receive-pack transport expects an ssh:// URL or scp-like SSH URL');
        }
        if (!isset($parts['host']) || !is_string($parts['host']) || $parts['host'] === '') {
            throw new \InvalidArgumentException('SSH receive-pack URL must include a host');
        }
        if (!isset($parts['path']) || !is_string($parts['path']) || $parts['path'] === '' || $parts['path'] === '/') {
            throw new \InvalidArgumentException('SSH receive-pack URL must include a repository path');
        }
        if (isset($parts['query']) || isset($parts['fragment']) || isset($parts['pass'])) {
            throw new \InvalidArgumentException('SSH receive-pack URL does not support password, query, or fragment components');
        }

        $host = self::normalizeHost(self::decodeComponent($parts['host'], 'host'));
        $user = isset($parts['user']) ? self::decodeComponent((string) $parts['user'], 'user') : null;
        $path = self::normalizeSshPath(self::decodeComponent($parts['path'], 'repository path'));
        $port = isset($parts['port']) ? (int) $parts['port'] : null;

        self::validateHost($host);
        self::validateUser($user);
        self::validatePort($port);
        self::validateRepositoryPath($path);

        return [
            'host' => $host,
            'user' => $user,
            'port' => $port,
            'path' => $path,
        ];
    }

    /**
     * @return array{host: string, user: ?string, port: ?int, path: string}
     */
    private static function parseScpLikeUrl(string $url): array
    {
        if (preg_match('/^(?:(?<user>[^@\/:]+)@)?(?<host>\[[^\]]+\]|[^\/:]+):(?<path>.+)$/', $url, $matches) !== 1) {
            throw new \InvalidArgumentException('SSH receive-pack transport expects an ssh:// URL or scp-like SSH URL');
        }

        $host = self::normalizeHost(self::decodeComponent($matches['host'], 'host'));

        $user = isset($matches['user']) && $matches['user'] !== ''
            ? self::decodeComponent($matches['user'], 'user')
            : null;
        $path = self::decodeComponent($matches['path'], 'repository path');

        self::validateHost($host);
        self::validateUser($user);
        self::validateRepositoryPath($path);

        return [
            'host' => $host,
            'user' => $user,
            'port' => null,
            'path' => $path,
        ];
    }

    private static function normalizeSshPath(string $path): string
    {
        if (preg_match('#^/(~[^/]*/?.*)$#', $path, $matches) === 1) {
            return $matches[1];
        }

        return $path;
    }

    private static function normalizeHost(string $host): string
    {
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            return substr($host, 1, -1);
        }

        return $host;
    }

    private static function decodeComponent(string $value, string $label): string
    {
        $decoded = rawurldecode($value);
        if ($decoded === '' || self::hasControlBytes($decoded)) {
            throw new \InvalidArgumentException("SSH receive-pack {$label} must be non-empty and must not contain control bytes");
        }

        return $decoded;
    }

    private static function validateHost(string $host): void
    {
        if ($host === '' || self::hasControlBytes($host)) {
            throw new \InvalidArgumentException('SSH receive-pack host must be non-empty and must not contain control bytes');
        }
        if (preg_match('/[\s\/\\\\]/', $host) === 1) {
            throw new \InvalidArgumentException('SSH receive-pack host must not contain whitespace, slash, or backslash delimiters');
        }
        if (str_starts_with($host, '-')) {
            throw new \InvalidArgumentException('SSH receive-pack host is ambiguous as an SSH command argument');
        }
    }

    private static function validateUser(?string $user): void
    {
        if ($user !== null && ($user === '' || self::hasControlBytes($user))) {
            throw new \InvalidArgumentException('SSH receive-pack user must be non-empty and must not contain control bytes');
        }
        if ($user !== null && preg_match('/[\s\/\\\\@:]/', $user) === 1) {
            throw new \InvalidArgumentException('SSH receive-pack user must not contain whitespace, slash, backslash, at-sign, or colon delimiters');
        }
        if ($user !== null && str_starts_with($user, '-')) {
            throw new \InvalidArgumentException('SSH receive-pack user is ambiguous as an SSH command argument');
        }
    }

    private static function validatePort(?int $port): void
    {
        if ($port !== null && ($port < 1 || $port > 65535)) {
            throw new \InvalidArgumentException('SSH receive-pack URL port must be between 1 and 65535');
        }
    }

    private static function validateRepositoryPath(string $repositoryPath): void
    {
        if ($repositoryPath === '' || self::hasControlBytes($repositoryPath)) {
            throw new \InvalidArgumentException('SSH receive-pack repository path must be non-empty and must not contain control bytes');
        }
        if (str_starts_with(ltrim($repositoryPath), '-')) {
            throw new \InvalidArgumentException('SSH receive-pack repository path is ambiguous as a git-receive-pack argument');
        }
    }

    private static function hasControlBytes(string $value): bool
    {
        return preg_match('/[\x00-\x1f\x7f]/', $value) === 1;
    }

    /**
     * @param array{protocolVersion?: int} $options
     * @return array{protocolVersion: int}
     */
    private static function normalizeConnectOptions(array $options): array
    {
        $protocolVersion = $options['protocolVersion'] ?? 1;
        if (!is_int($protocolVersion) || !in_array($protocolVersion, [1, 2], true)) {
            throw new \InvalidArgumentException('SSH receive-pack protocol version must be 1 or 2');
        }

        return ['protocolVersion' => $protocolVersion];
    }

    /**
     * @param array{host: string, user: ?string, port: ?int, path: string} $target
     * @param array{protocolVersion: int} $options
     * @return array{
     *     host: string,
     *     user: ?string,
     *     port: ?int,
     *     path: string,
     *     command: string,
     *     protocolVersion: int,
     *     environment: array<string, string>,
     *     sshArguments: list<string>,
     *     credentialContext: CredentialContext,
     *     redactedCredentialContext: string,
     *     authenticationBoundary: string
     * }
     */
    private static function connectorContextForTarget(array $target, array $options): array
    {
        $credentialContext = new CredentialContext(
            protocol: 'ssh',
            host: self::authority($target['host'], $target['port']),
            path: ltrim($target['path'], '/'),
            username: $target['user'],
        );
        $environment = [
            'LANG' => 'C',
            'LC_ALL' => 'C',
        ];
        $sshArguments = [];
        if ($options['protocolVersion'] === 2) {
            $environment = ['GIT_PROTOCOL' => 'version=2'] + $environment;
            $sshArguments[] = '-o';
            $sshArguments[] = 'SendEnv=GIT_PROTOCOL';
        }
        if ($target['port'] !== null) {
            $sshArguments[] = '-p' . $target['port'];
        }
        $sshArguments[] = $target['user'] === null
            ? self::authority($target['host'], null)
            : $target['user'] . '@' . self::authority($target['host'], null);

        return [
            'host' => $target['host'],
            'user' => $target['user'],
            'port' => $target['port'],
            'path' => $target['path'],
            'command' => self::receivePackCommand($target['path']),
            'protocolVersion' => $options['protocolVersion'],
            'environment' => $environment,
            'sshArguments' => $sshArguments,
            'credentialContext' => $credentialContext,
            'redactedCredentialContext' => $credentialContext->redacted()->storageBytes(),
            'authenticationBoundary' => 'caller-provided-ssh-connector',
        ];
    }

    private static function connectorAcceptsContext(callable $connector): bool
    {
        try {
            $reflection = new \ReflectionFunction(\Closure::fromCallable($connector));
        } catch (\ReflectionException) {
            return false;
        }

        return $reflection->isVariadic() || $reflection->getNumberOfParameters() >= 6;
    }

    private static function authority(string $host, ?int $port): string
    {
        $authority = str_contains($host, ':') && !str_starts_with($host, '[') ? "[{$host}]" : $host;
        if ($port !== null) {
            $authority .= ':' . $port;
        }

        return $authority;
    }

    private static function shellQuote(string $value): string
    {
        return "'" . str_replace("'", "'\\''", $value) . "'";
    }
}
