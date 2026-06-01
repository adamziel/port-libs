<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class SshReceivePackTransport implements ReceivePackTransport
{
    private const REMOTE_SERVICE = 'git-receive-pack';

    private const ENVIRONMENT_VARIABLES_TO_REMOVE = [
        'GIT_ALTERNATE_OBJECT_DIRECTORIES',
        'GIT_CONFIG',
        'GIT_CONFIG_PARAMETERS',
        'GIT_OBJECT_DIRECTORY',
        'GIT_DIR',
        'GIT_WORK_TREE',
        'GIT_IMPLICIT_WORK_TREE',
        'GIT_GRAFT_FILE',
        'GIT_INDEX_FILE',
        'GIT_NO_REPLACE_OBJECTS',
        'GIT_REPLACE_REF_BASE',
        'GIT_PREFIX',
        'GIT_INTERNAL_SUPER_PREFIX',
        'GIT_SHALLOW_FILE',
        'GIT_COMMON_DIR',
        'GIT_CONFIG_COUNT',
    ];

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
     * @param array{protocolVersion?: int, programKind?: string, sshCommand?: string, disallowShell?: bool} $options
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
     * @param array{protocolVersion?: int, programKind?: string, sshCommand?: string, disallowShell?: bool} $options
     * @return array{
     *     host: string,
     *     user: ?string,
     *     port: ?int,
     *     path: string,
     *     command: string,
     *     remoteService: string,
     *     remotePathArgument: string,
     *     sshInvocationArguments: list<string>,
     *     protocolVersion: int,
     *     programKind: string,
     *     sshCommand: string,
     *     disallowShell: bool,
     *     useShell: bool,
     *     sshFeatureProbe: array{command: string, arguments: list<string>, useShell: bool}|null,
     *     environment: array<string, string>,
     *     environmentRemovals: list<string>,
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

        if (str_contains($url, '://')) {
            return self::parseSshUrl($url);
        }

        return self::parseScpLikeUrl($url);
    }

    public static function receivePackCommand(string $repositoryPath): string
    {
        self::validateRepositoryPath($repositoryPath);

        return self::REMOTE_SERVICE . ' ' . self::shellQuote($repositoryPath);
    }

    /**
     * @return array{kind: string, message: string}|null
     */
    public static function classifyErrorLine(string $line, string $programKind = 'ssh'): ?array
    {
        $kind = self::normalizeProgramKind($programKind);
        $message = self::trimOneTrailingNewline($line);
        if ($message === '') {
            return null;
        }

        if ($kind === 'ssh' || $kind === 'simple') {
            if (str_contains($message, 'Permission denied') || str_contains($message, 'permission denied')) {
                return self::sshError('permission_denied', $message);
            }
            if (str_contains($message, 'resolve hostname')) {
                return self::sshError('connection_refused', $message);
            }
            if (
                str_contains($message, 'connect to host')
                || str_contains($message, 'Connection to ')
                || str_contains($message, 'Connection closed by ')
            ) {
                return self::sshError('not_found', $message);
            }

            return null;
        }

        if (str_contains($message, 'publickey')) {
            return self::sshError('permission_denied', $message);
        }

        return null;
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
        if (!is_array($parts)) {
            $parts = self::parseSshUrlWithNonNumericPort($url);
        }

        if (!is_array($parts) || !self::isSshScheme((string) ($parts['scheme'] ?? ''))) {
            throw new \InvalidArgumentException('SSH receive-pack transport expects an ssh://, ssh+git://, git+ssh://, or scp-like SSH URL');
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

        self::validateUser($user);
        self::validateHost($host, $user);
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
        $colon = self::scpLikePathDelimiterPosition($url);
        if ($colon === null) {
            throw new \InvalidArgumentException('SSH receive-pack transport expects an ssh:// URL or scp-like SSH URL');
        }

        $authority = substr($url, 0, $colon);
        $pathPart = substr($url, $colon + 1);
        $hostPart = $authority;
        $userPart = null;
        if (!str_starts_with($authority, '[')) {
            $at = strrpos($authority, '@');
            if ($at !== false) {
                $userPart = substr($authority, 0, $at);
                $hostPart = substr($authority, $at + 1);
            }
        }

        if ($userPart !== null && str_starts_with($hostPart, '[')) {
            throw new \InvalidArgumentException('SCP-like SSH receive-pack URL does not support bracketed IPv6 hosts with a user');
        }

        $host = self::normalizeHost(self::decodeComponent($hostPart, 'host'));

        $user = $userPart !== null
            ? self::decodeComponent($userPart, 'user')
            : null;
        $path = self::normalizeSshPath(self::decodeComponent($pathPart, 'repository path'));

        self::validateUser($user, true);
        self::validateHost($host, $user);
        self::validateRepositoryPath($path);

        return [
            'host' => $host,
            'user' => $user,
            'port' => null,
            'path' => $path,
        ];
    }

    private static function scpLikePathDelimiterPosition(string $url): ?int
    {
        if (str_starts_with($url, '[')) {
            $bracketEnd = strpos($url, ']');
            $colon = $bracketEnd === false
                ? strpos($url, ':')
                : strpos($url, ':', $bracketEnd + 1);
        } else {
            $colon = strpos($url, ':');
        }

        if ($colon === false) {
            return null;
        }

        return str_contains(substr($url, 0, $colon), '/') ? null : $colon;
    }

    private static function normalizeSshPath(string $path): string
    {
        if (preg_match('#^/(~[^/]*/?.*)$#', $path, $matches) === 1) {
            return $matches[1];
        }

        return $path;
    }

    /**
     * PHP's URL parser rejects authorities such as "host.xz:abc" before a
     * path, while gix-url treats the non-numeric port-looking suffix as part
     * of the SSH host. Keep this fallback scoped to that upstream boundary so
     * numeric overflow ports still fail.
     *
     * @return array{scheme: string, host: string, path: string, user?: string, pass?: string, query?: string, fragment?: string}|null
     */
    private static function parseSshUrlWithNonNumericPort(string $url): ?array
    {
        $schemeSeparator = strpos($url, '://');
        if ($schemeSeparator === false) {
            return null;
        }

        $scheme = substr($url, 0, $schemeSeparator);
        if (!self::isSshScheme($scheme)) {
            return null;
        }

        $remainder = substr($url, $schemeSeparator + 3);
        $fragment = null;
        $fragmentPosition = strpos($remainder, '#');
        if ($fragmentPosition !== false) {
            $fragment = substr($remainder, $fragmentPosition + 1);
            $remainder = substr($remainder, 0, $fragmentPosition);
        }

        $query = null;
        $queryPosition = strpos($remainder, '?');
        if ($queryPosition !== false) {
            $query = substr($remainder, $queryPosition + 1);
            $remainder = substr($remainder, 0, $queryPosition);
        }

        $pathPosition = strpos($remainder, '/');
        if ($pathPosition === false) {
            return null;
        }

        $authority = substr($remainder, 0, $pathPosition);
        $path = substr($remainder, $pathPosition);
        if ($authority === '' || str_starts_with($authority, '[')) {
            return null;
        }

        $hostPort = $authority;
        $user = null;
        $pass = null;
        $userPosition = strrpos($authority, '@');
        if ($userPosition !== false) {
            $userInfo = substr($authority, 0, $userPosition);
            $hostPort = substr($authority, $userPosition + 1);
            if ($userInfo === '' || $hostPort === '') {
                return null;
            }
            if (str_contains($userInfo, ':')) {
                [$user, $pass] = explode(':', $userInfo, 2);
            } else {
                $user = $userInfo;
            }
        }

        $colonPosition = strrpos($hostPort, ':');
        if ($colonPosition === false) {
            return null;
        }

        $afterColon = substr($hostPort, $colonPosition + 1);
        if ($afterColon === '' || ctype_digit($afterColon)) {
            return null;
        }

        $parts = [
            'scheme' => $scheme,
            'host' => $hostPort,
            'path' => $path,
        ];
        if ($user !== null) {
            $parts['user'] = $user;
        }
        if ($pass !== null) {
            $parts['pass'] = $pass;
        }
        if ($query !== null) {
            $parts['query'] = $query;
        }
        if ($fragment !== null) {
            $parts['fragment'] = $fragment;
        }

        return $parts;
    }

    private static function isSshScheme(string $scheme): bool
    {
        return in_array(strtolower($scheme), ['ssh', 'ssh+git', 'git+ssh'], true);
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

    private static function validateHost(string $host, ?string $user): void
    {
        if ($host === '' || self::hasControlBytes($host)) {
            throw new \InvalidArgumentException('SSH receive-pack host must be non-empty and must not contain control bytes');
        }
        if (preg_match('/[\s\/\\\\]/', $host) === 1) {
            throw new \InvalidArgumentException('SSH receive-pack host must not contain whitespace, slash, or backslash delimiters');
        }
        if ($user === null && str_starts_with($host, '-')) {
            throw new \InvalidArgumentException('SSH receive-pack host is ambiguous as an SSH command argument');
        }
    }

    private static function validateUser(?string $user, bool $allowAtSign = false): void
    {
        if ($user !== null && ($user === '' || self::hasControlBytes($user))) {
            throw new \InvalidArgumentException('SSH receive-pack user must be non-empty and must not contain control bytes');
        }
        $delimiterPattern = $allowAtSign ? '/[\s\/\\\\:]/' : '/[\s\/\\\\@:]/';
        if ($user !== null && preg_match($delimiterPattern, $user) === 1) {
            $message = $allowAtSign
                ? 'SSH receive-pack user must not contain whitespace, slash, backslash, or colon delimiters'
                : 'SSH receive-pack user must not contain whitespace, slash, backslash, at-sign, or colon delimiters';
            throw new \InvalidArgumentException($message);
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
     * @param array{protocolVersion?: int, programKind?: string, sshCommand?: string, disallowShell?: bool} $options
     * @return array{protocolVersion: int, programKind: string, sshCommand: string, disallowShell: bool, useShell: bool, featureProbeRequired: bool}
     */
    private static function normalizeConnectOptions(array $options): array
    {
        $protocolVersion = $options['protocolVersion'] ?? 1;
        if (!is_int($protocolVersion) || !in_array($protocolVersion, [1, 2], true)) {
            throw new \InvalidArgumentException('SSH receive-pack protocol version must be 1 or 2');
        }

        $sshCommand = $options['sshCommand'] ?? null;
        if ($sshCommand !== null && (!is_string($sshCommand) || $sshCommand === '' || self::hasControlBytes($sshCommand))) {
            throw new \InvalidArgumentException('SSH receive-pack sshCommand must be a non-empty string without control bytes');
        }

        $programKind = $options['programKind'] ?? null;
        if ($programKind !== null && !is_string($programKind)) {
            throw new \InvalidArgumentException('SSH receive-pack programKind must be ssh, plink, putty, tortoiseplink, or simple');
        }

        $normalizedKind = $programKind === null
            ? self::programKindFromCommand($sshCommand ?? 'ssh')
            : self::normalizeProgramKind($programKind);
        $featureProbeRequired = $programKind === null && $normalizedKind === 'simple';

        if ($sshCommand === null) {
            $sshCommand = self::defaultCommandForProgramKind($normalizedKind);
            $featureProbeRequired = false;
        }

        $disallowShell = $options['disallowShell'] ?? false;
        if (!is_bool($disallowShell)) {
            throw new \InvalidArgumentException('SSH receive-pack disallowShell must be a boolean');
        }

        return [
            'protocolVersion' => $protocolVersion,
            'programKind' => $normalizedKind,
            'sshCommand' => $sshCommand,
            'disallowShell' => $disallowShell,
            'useShell' => self::sshCommandUsesShell($sshCommand, $disallowShell),
            'featureProbeRequired' => $featureProbeRequired,
        ];
    }

    /**
     * @param array{host: string, user: ?string, port: ?int, path: string} $target
     * @param array{protocolVersion: int, programKind: string, sshCommand: string, disallowShell: bool, useShell: bool} $options
     * @return array{
     *     host: string,
     *     user: ?string,
     *     port: ?int,
     *     path: string,
     *     command: string,
     *     remoteService: string,
     *     remotePathArgument: string,
     *     sshInvocationArguments: list<string>,
     *     protocolVersion: int,
     *     programKind: string,
     *     sshCommand: string,
     *     disallowShell: bool,
     *     useShell: bool,
     *     sshFeatureProbe: array{command: string, arguments: list<string>, useShell: bool}|null,
     *     environment: array<string, string>,
     *     environmentRemovals: list<string>,
     *     sshArguments: list<string>,
     *     credentialContext: CredentialContext,
     *     redactedCredentialContext: string,
     *     authenticationBoundary: string
     * }
     */
    private static function connectorContextForTarget(array $target, array $options): array
    {
        $featureProbe = self::sshFeatureProbeForTarget($target, $options);
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
        $sshArguments = self::sshArgumentsForTarget($target, $options);
        $remotePathArgument = self::shellQuote($target['path']);
        if ($options['programKind'] === 'ssh' && $options['protocolVersion'] === 2) {
            $environment = ['GIT_PROTOCOL' => 'version=2'] + $environment;
        }

        return [
            'host' => $target['host'],
            'user' => $target['user'],
            'port' => $target['port'],
            'path' => $target['path'],
            'command' => self::REMOTE_SERVICE . ' ' . $remotePathArgument,
            'remoteService' => self::REMOTE_SERVICE,
            'remotePathArgument' => $remotePathArgument,
            'sshInvocationArguments' => array_merge($sshArguments, [self::REMOTE_SERVICE, $remotePathArgument]),
            'protocolVersion' => $options['protocolVersion'],
            'programKind' => $options['programKind'],
            'sshCommand' => $options['sshCommand'],
            'disallowShell' => $options['disallowShell'],
            'useShell' => $options['useShell'],
            'sshFeatureProbe' => $featureProbe,
            'environment' => $environment,
            'environmentRemovals' => self::ENVIRONMENT_VARIABLES_TO_REMOVE,
            'sshArguments' => $sshArguments,
            'credentialContext' => $credentialContext,
            'redactedCredentialContext' => $credentialContext->redacted()->storageBytes(),
            'authenticationBoundary' => 'caller-provided-ssh-connector',
        ];
    }

    /**
     * @param array{host: string, user: ?string, port: ?int, path: string} $target
     * @param array{protocolVersion: int, programKind: string, sshCommand: string, disallowShell: bool, useShell: bool, featureProbeRequired: bool} $options
     * @return array{command: string, arguments: list<string>, useShell: bool}|null
     */
    private static function sshFeatureProbeForTarget(array $target, array $options): ?array
    {
        if (!$options['featureProbeRequired']) {
            return null;
        }
        if (str_starts_with($target['host'], '-')) {
            throw new \InvalidArgumentException('SSH receive-pack host is ambiguous for SSH feature detection');
        }

        return [
            'command' => $options['sshCommand'],
            'arguments' => ['-G', $target['host']],
            'useShell' => $options['useShell'],
        ];
    }

    /**
     * @param array{host: string, user: ?string, port: ?int, path: string} $target
     * @param array{protocolVersion: int, programKind: string, sshCommand: string, disallowShell: bool, useShell: bool, featureProbeRequired: bool} $options
     * @return list<string>
     */
    private static function sshArgumentsForTarget(array $target, array $options): array
    {
        $arguments = [];
        if ($options['programKind'] === 'ssh' && $options['protocolVersion'] === 2) {
            $arguments[] = '-o';
            $arguments[] = 'SendEnv=GIT_PROTOCOL';
        }

        if ($target['port'] !== null) {
            if ($options['programKind'] === 'simple') {
                throw new \InvalidArgumentException('SSH receive-pack simple programKind does not support setting the port');
            }
            if ($options['programKind'] === 'ssh') {
                $arguments[] = '-p' . $target['port'];
            } else {
                $arguments[] = '-P';
                $arguments[] = (string) $target['port'];
            }
        }

        if ($options['programKind'] === 'tortoiseplink') {
            array_unshift($arguments, '-batch');
        }

        $arguments[] = $target['user'] === null
            ? self::authority($target['host'], null)
            : $target['user'] . '@' . self::authority($target['host'], null);

        return $arguments;
    }

    private static function normalizeProgramKind(string $programKind): string
    {
        $normalized = strtolower(str_replace(['_', '-'], '', $programKind));

        return match ($normalized) {
            'ssh' => 'ssh',
            'plink' => 'plink',
            'putty' => 'putty',
            'tortoiseplink', 'tortoiseplinkexe' => 'tortoiseplink',
            'simple' => 'simple',
            default => throw new \InvalidArgumentException('SSH receive-pack programKind must be ssh, plink, putty, tortoiseplink, or simple'),
        };
    }

    private static function programKindFromCommand(string $sshCommand): string
    {
        $command = str_replace('\\', '/', $sshCommand);
        $basename = basename($command);
        $stem = strtolower($basename);
        if (str_ends_with($stem, '.exe')) {
            $stem = substr($stem, 0, -4);
        }

        return match ($stem) {
            'ssh' => 'ssh',
            'plink' => 'plink',
            'putty' => 'putty',
            'tortoiseplink' => 'tortoiseplink',
            default => 'simple',
        };
    }

    private static function defaultCommandForProgramKind(string $programKind): string
    {
        return match ($programKind) {
            'tortoiseplink' => 'tortoiseplink.exe',
            'simple' => 'ssh',
            default => $programKind,
        };
    }

    private static function sshCommandUsesShell(string $sshCommand, bool $disallowShell): bool
    {
        if ($disallowShell) {
            return false;
        }

        foreach (['|', '&', ';', '<', '>', '(', ')', '$', '`', '\\', '"', "'", ' ', "\t", "\n", '*', '?', '[', '#', '~', '=', '%'] as $byte) {
            if (str_contains($sshCommand, $byte)) {
                return true;
            }
        }

        return false;
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
        $authority = self::isIpv6Literal($host) ? "[{$host}]" : $host;
        if ($port !== null) {
            $authority .= ':' . $port;
        }

        return $authority;
    }

    private static function isIpv6Literal(string $host): bool
    {
        $packed = @inet_pton($host);

        return $packed !== false && strlen($packed) === 16;
    }

    private static function shellQuote(string $value): string
    {
        return "'" . str_replace(["'", '!'], ["'\\''", "'\\!'"], $value) . "'";
    }

    /**
     * @return array{kind: string, message: string}
     */
    private static function sshError(string $kind, string $message): array
    {
        return [
            'kind' => $kind,
            'message' => $message,
        ];
    }

    private static function trimOneTrailingNewline(string $line): string
    {
        if (str_ends_with($line, "\r\n")) {
            return substr($line, 0, -2);
        }
        if (str_ends_with($line, "\n") || str_ends_with($line, "\r")) {
            return substr($line, 0, -1);
        }

        return $line;
    }
}
