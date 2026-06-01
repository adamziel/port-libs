<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class GitUrl
{
    private const MAX_URL_PRE_PATH_BYTES = 1024;

    public const SCHEME_FILE = 'file';
    public const SCHEME_GIT = 'git';
    public const SCHEME_SSH = 'ssh';
    public const SCHEME_HTTP = 'http';
    public const SCHEME_HTTPS = 'https';

    public const ARGUMENT_ABSENT = 'absent';
    public const ARGUMENT_USABLE = 'usable';
    public const ARGUMENT_DANGEROUS = 'dangerous';

    private function __construct(
        private readonly string $scheme,
        private readonly ?string $user,
        private readonly ?string $password,
        private readonly ?string $host,
        private readonly ?int $port,
        private readonly string $path,
        private readonly bool $alternativeForm,
    ) {
    }

    public static function parse(string $input): self
    {
        if ($input === '') {
            throw new \InvalidArgumentException('Git URL does not specify a repository path');
        }

        $protocolEnd = strpos($input, '://');
        if ($protocolEnd !== false) {
            $scheme = strtolower(substr($input, 0, $protocolEnd));
            if ($scheme === self::SCHEME_FILE) {
                return self::parseFileUrl($input, $protocolEnd);
            }

            return self::parseUrlForm($input, $protocolEnd);
        }

        $colon = self::scpColonPosition($input);
        if ($colon !== null) {
            return self::parseScpForm($input, $colon);
        }

        return new self(self::SCHEME_FILE, null, null, null, null, $input, true);
    }

    public static function fromBytes(string $bytes): self
    {
        return self::parse($bytes);
    }

    public static function fromParts(
        string $scheme,
        ?string $user,
        ?string $password,
        ?string $host,
        ?int $port,
        string $path,
        bool $alternativeForm = false
    ): self {
        $scheme = self::normalizeScheme($scheme);
        self::assertScheme($scheme);
        if ($user !== null) {
            self::assertValidUtf8($user, 'Git URL username');
        }
        if ($password !== null) {
            self::assertValidUtf8($password, 'Git URL password');
        }
        if ($host !== null) {
            self::assertValidUtf8($host, 'Git URL host');
        }
        if ($user !== null && $host === null) {
            throw new \InvalidArgumentException('Git URL user info requires a host');
        }
        if ($port !== null) {
            self::parsePort((string) $port);
        }

        return self::parse((new self(
            $scheme,
            $user,
            $password,
            $host,
            $port,
            $path,
            $alternativeForm
        ))->toBytes());
    }

    /**
     * @return array{currentUser: bool, user: ?string, path: string}
     */
    public static function parseHomePath(string $path): array
    {
        if ($path === '' || $path[0] !== '/') {
            return ['currentUser' => false, 'user' => null, 'path' => $path];
        }

        $nextSlash = strpos($path, '/', 1);
        $segment = $nextSlash === false ? substr($path, 1) : substr($path, 1, $nextSlash - 1);
        if ($segment === '' || $segment[0] !== '~') {
            return ['currentUser' => false, 'user' => null, 'path' => $path];
        }

        $tail = $nextSlash === false ? '/' : substr($path, $nextSlash);
        if ($segment === '~') {
            return ['currentUser' => true, 'user' => null, 'path' => $tail];
        }

        return ['currentUser' => false, 'user' => substr($segment, 1), 'path' => $tail];
    }

    public static function forShellPath(string $path): string
    {
        $parsed = self::parseHomePath($path);
        if ($parsed['currentUser']) {
            return '~' . $parsed['path'];
        }
        if ($parsed['user'] !== null) {
            return '~' . $parsed['user'] . $parsed['path'];
        }

        return $parsed['path'];
    }

    /**
     * @param callable(?string): ?string $homeForUser Receives null for the current user, or a user name.
     */
    public static function expandHomePath(string $path, callable $homeForUser): string
    {
        $parsed = self::parseHomePath($path);
        if (!$parsed['currentUser'] && $parsed['user'] === null) {
            return $parsed['path'];
        }

        $home = $homeForUser($parsed['currentUser'] ? null : $parsed['user']);
        if ($home === null) {
            $label = $parsed['currentUser'] ? 'current user' : "user '{$parsed['user']}'";
            throw new \InvalidArgumentException("Home directory could not be obtained for {$label}");
        }

        $relative = ltrim($parsed['path'], '/');
        $base = rtrim($home, '/');
        if ($base === '') {
            return $relative === '' ? '/' : '/' . $relative;
        }

        return $relative === '' ? $base : $base . '/' . $relative;
    }

    public function scheme(): string
    {
        return $this->scheme;
    }

    public function user(): ?string
    {
        return $this->user;
    }

    public function password(): ?string
    {
        return $this->password;
    }

    public function withUser(?string $user): self
    {
        if ($user !== null) {
            self::assertValidUtf8($user, 'Git URL username');
        }

        return new self(
            $this->scheme,
            $user,
            $this->password,
            $this->host,
            $this->port,
            $this->path,
            $this->alternativeForm
        );
    }

    public function withPassword(?string $password): self
    {
        if ($password !== null) {
            self::assertValidUtf8($password, 'Git URL password');
        }

        return new self(
            $this->scheme,
            $this->user,
            $password,
            $this->host,
            $this->port,
            $this->path,
            $this->alternativeForm
        );
    }

    public function host(): ?string
    {
        return $this->host;
    }

    public function port(): ?int
    {
        return $this->port;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function usesAlternativeForm(): bool
    {
        return $this->alternativeForm;
    }

    public function withAlternativeForm(bool $alternativeForm): self
    {
        return new self(
            $this->scheme,
            $this->user,
            $this->password,
            $this->host,
            $this->port,
            $this->path,
            $alternativeForm
        );
    }

    public function portOrDefault(): ?int
    {
        return $this->port ?? match ($this->scheme) {
            self::SCHEME_HTTP => 80,
            self::SCHEME_HTTPS => 443,
            self::SCHEME_SSH => 22,
            self::SCHEME_GIT => 9418,
            default => null,
        };
    }

    public function canonicalized(string $currentDirectory): self
    {
        if ($this->scheme !== self::SCHEME_FILE || self::isAbsoluteFilePath($this->path)) {
            return $this;
        }

        $base = $currentDirectory === '' ? '.' : $currentDirectory;
        if (!self::isAbsoluteFilePath($base)) {
            $cwd = getcwd();
            if ($cwd !== false && $cwd !== '') {
                $base = rtrim($cwd, '/') . '/' . $base;
            }
        }

        return new self(
            $this->scheme,
            $this->user,
            $this->password,
            $this->host,
            $this->port,
            self::normalizeFilePath(rtrim($base, '/') . '/' . $this->path),
            $this->alternativeForm
        );
    }

    /**
     * @return array{status: string, value: ?string}
     */
    public function userArgumentSafety(): array
    {
        return self::classifyArgument($this->user);
    }

    public function userArgumentSafe(): ?string
    {
        return $this->argumentSafe($this->user);
    }

    /**
     * @return array{status: string, value: ?string}
     */
    public function hostArgumentSafety(): array
    {
        return self::classifyArgument($this->host);
    }

    public function hostArgumentSafe(): ?string
    {
        return $this->argumentSafe($this->host);
    }

    /**
     * @return array{status: string, value: ?string}
     */
    public function pathArgumentSafety(): array
    {
        if ($this->path === '') {
            return ['status' => self::ARGUMENT_ABSENT, 'value' => null];
        }

        $comparison = str_starts_with($this->path, '/') ? substr($this->path, 1) : $this->path;
        if (str_starts_with($comparison, '-')) {
            return ['status' => self::ARGUMENT_DANGEROUS, 'value' => $this->path];
        }

        return ['status' => self::ARGUMENT_USABLE, 'value' => $this->path];
    }

    public function pathArgumentSafe(): ?string
    {
        $safety = $this->pathArgumentSafety();

        return $safety['status'] === self::ARGUMENT_USABLE ? $safety['value'] : null;
    }

    public function pathIsRoot(): bool
    {
        return $this->path === '/';
    }

    /**
     * @return array{scheme: string, user: ?string, password: ?string, host: ?string, port: ?int, path: string, alternativeForm: bool, normalized: string, display: string, defaultPort: ?int}
     */
    public function toArray(): array
    {
        return [
            'scheme' => $this->scheme,
            'user' => $this->user,
            'password' => $this->password,
            'host' => $this->host,
            'port' => $this->port,
            'path' => $this->path,
            'alternativeForm' => $this->alternativeForm,
            'normalized' => $this->toBytes(),
            'display' => $this->display(),
            'defaultPort' => $this->portOrDefault(),
        ];
    }

    public function toBytes(): string
    {
        if (
            $this->alternativeForm
            && ($this->scheme === self::SCHEME_FILE || $this->scheme === self::SCHEME_SSH)
            && $this->password === null
            && $this->port === null
        ) {
            return $this->alternativeBytes();
        }

        return $this->canonicalBytes($this->password);
    }

    public function display(): string
    {
        return $this->password === null ? $this->toBytes() : $this->canonicalBytes('redacted');
    }

    public function __toString(): string
    {
        return $this->display();
    }

    private static function parseUrlForm(string $input, int $protocolEnd): self
    {
        self::assertUrlPrePathWithinLimit($input, $protocolEnd);
        self::assertValidUtf8($input, 'Git URL');

        if (preg_match('/\s/', $input) === 1) {
            throw new \InvalidArgumentException('Git URL contains invalid whitespace');
        }

        $scheme = self::normalizeScheme(substr($input, 0, $protocolEnd));
        self::assertScheme($scheme);
        $afterScheme = substr($input, $protocolEnd + 3);
        $pathStart = strpos($afterScheme, '/');
        $authority = $pathStart === false ? $afterScheme : substr($afterScheme, 0, $pathStart);
        $path = $pathStart === false ? '' : self::percentDecodeUtf8(substr($afterScheme, $pathStart), 'path');

        [$user, $password, $host, $port] = self::parseAuthority($authority, true);
        if (self::schemeRequiresHost($scheme) && $host === null) {
            throw new \InvalidArgumentException('Git URL scheme requires a host');
        }

        if (($scheme === self::SCHEME_SSH || $scheme === self::SCHEME_GIT) && $path === '') {
            throw new \InvalidArgumentException('Git URL does not specify a repository path');
        }

        if (($scheme === self::SCHEME_SSH || $scheme === self::SCHEME_GIT) && str_starts_with($path, '/~')) {
            $path = substr($path, 1);
        } elseif (($scheme === self::SCHEME_HTTP || $scheme === self::SCHEME_HTTPS) && $path === '') {
            $path = '/';
        }

        if ($scheme === self::SCHEME_SSH && $host !== null) {
            $host = self::normalizeSshUrlHost($host);
        }

        return new self($scheme, $user, $password, $host, $port, $path, false);
    }

    private static function assertUrlPrePathWithinLimit(string $input, int $protocolEnd): void
    {
        if ($protocolEnd > self::MAX_URL_PRE_PATH_BYTES) {
            throw new \InvalidArgumentException('Git URL scheme is too long');
        }

        $afterScheme = substr($input, $protocolEnd + 3);
        $length = strlen($afterScheme);
        $offset = 0;
        while ($offset < $length) {
            $byte = $afterScheme[$offset];
            if ($byte !== '/' && $byte !== '\\' && preg_match('/\s/', $byte) !== 1) {
                break;
            }
            $offset++;
        }

        $pathStart = strpos($afterScheme, '/', $offset);
        $authorityLength = $pathStart === false ? $length - $offset : $pathStart - $offset;
        if ($authorityLength > self::MAX_URL_PRE_PATH_BYTES) {
            throw new \InvalidArgumentException('Git URL host portion is too long');
        }
    }

    private static function parseScpForm(string $input, int $colon): self
    {
        self::assertValidUtf8($input, 'SCP-like Git URL');

        $hostAndUser = substr($input, 0, $colon);
        $path = substr($input, $colon + 1);
        if ($path === '') {
            throw new \InvalidArgumentException('SCP-like Git URL does not specify a repository path');
        }

        [$user, , $host, $port] = self::parseAuthority($hostAndUser, true);
        if ($host === null || $port !== null) {
            throw new \InvalidArgumentException('SCP-like Git URL requires a host without a port');
        }
        if ($user !== null && str_starts_with($host, '[')) {
            throw new \InvalidArgumentException('SCP-like Git URL does not support bracketed IPv6 hosts with a user');
        }

        if (str_starts_with($path, '/~')) {
            $path = substr($path, 1);
        }

        return new self(self::SCHEME_SSH, $user, null, self::stripIpv6Brackets($host), null, $path, true);
    }

    private static function parseFileUrl(string $input, int $protocolEnd): self
    {
        self::assertValidUtf8($input, 'File Git URL');

        $afterScheme = substr($input, $protocolEnd + 3);
        $firstSlash = strpos($afterScheme, '/');
        if ($firstSlash === false) {
            throw new \InvalidArgumentException('File Git URL does not specify a repository path');
        }

        $authority = $firstSlash === 0 ? '' : substr($afterScheme, 0, $firstSlash);
        $path = substr($afterScheme, $firstSlash);
        [$user, $password, $host, $port] = self::parseAuthority($authority);

        return new self(self::SCHEME_FILE, $user, $password, $host, $port, $path, false);
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: ?string, 3: ?int}
     */
    private static function parseAuthority(string $authority, bool $strictPercentUtf8 = false): array
    {
        if ($authority === '') {
            return [null, null, null, null];
        }

        $user = null;
        $password = null;
        $hostPort = $authority;
        $at = strrpos($authority, '@');
        if ($at !== false) {
            $userinfo = substr($authority, 0, $at);
            $hostPort = substr($authority, $at + 1);
            [$rawUser, $rawPassword] = array_pad(explode(':', $userinfo, 2), 2, null);
            $user = self::decodeAuthorityComponent($rawUser, $strictPercentUtf8, 'username');
            $password = $rawPassword === null || $rawPassword === ''
                ? null
                : self::decodeAuthorityComponent($rawPassword, $strictPercentUtf8, 'password');
        }

        [$host, $port] = self::parseHostPort($hostPort);
        if ($host === null && $user !== null) {
            throw new \InvalidArgumentException('Git URL user info requires a host');
        }

        if ($user === '' && $password === null) {
            $user = null;
        }

        return [$user, $password, $host, $port];
    }

    private static function decodeAuthorityComponent(string $value, bool $strictPercentUtf8, string $component): string
    {
        if ($strictPercentUtf8) {
            return self::percentDecodeUtf8($value, $component);
        }

        return rawurldecode($value);
    }

    private static function percentDecodeUtf8(string $value, string $component): string
    {
        $decoded = rawurldecode($value);
        self::assertValidUtf8($decoded, 'Git URL ' . $component);

        return $decoded;
    }

    private static function assertValidUtf8(string $value, string $subject): void
    {
        if (preg_match('//u', $value) !== 1) {
            throw new \InvalidArgumentException($subject . ' is not valid UTF-8');
        }
    }

    /**
     * @return array{0: ?string, 1: ?int}
     */
    private static function parseHostPort(string $hostPort): array
    {
        if ($hostPort === '') {
            return [null, null];
        }

        if ($hostPort[0] === '[') {
            $bracketEnd = strpos($hostPort, ']');
            if ($bracketEnd === false) {
                throw new \InvalidArgumentException('Git URL IPv6 host is missing closing bracket');
            }
            $remaining = substr($hostPort, $bracketEnd + 1);
            $host = strtolower(substr($hostPort, 0, $bracketEnd + 1));
            if ($remaining === '') {
                return [$host, null];
            }
            if (!str_starts_with($remaining, ':')) {
                throw new \InvalidArgumentException('Git URL host contains invalid IPv6 suffix');
            }
            $portText = substr($remaining, 1);
            if ($portText === '') {
                return [strtolower($hostPort), null];
            }

            return [$host, self::parsePort($portText)];
        }

        $colon = strrpos($hostPort, ':');
        if ($colon !== false && substr_count(substr($hostPort, 0, $colon), ':') === 0) {
            $portText = substr($hostPort, $colon + 1);
            if ($portText === '') {
                return [self::normalizeHost($hostPort), null];
            }
            if (ctype_digit($portText)) {
                return [self::normalizeHost(substr($hostPort, 0, $colon)), self::parsePort($portText)];
            }
        }

        return [self::normalizeHost($hostPort), null];
    }

    private static function parsePort(string $port): int
    {
        if (!ctype_digit($port)) {
            throw new \InvalidArgumentException('Git URL port must be numeric');
        }

        $value = (int) $port;
        if ($value < 1 || $value > 65535) {
            throw new \InvalidArgumentException('Git URL port must be between 1 and 65535');
        }

        return $value;
    }

    private static function normalizeHost(string $host): string
    {
        if ($host === '' || preg_match('/[\s?]/', $host) === 1) {
            throw new \InvalidArgumentException('Git URL host contains invalid characters');
        }

        return preg_match('/^[A-Za-z0-9._*-]+$/', $host) === 1 ? strtolower($host) : $host;
    }

    private static function normalizeScheme(string $scheme): string
    {
        $scheme = strtolower($scheme);
        return match ($scheme) {
            'ssh+git', 'git+ssh' => self::SCHEME_SSH,
            default => $scheme,
        };
    }

    private static function schemeRequiresHost(string $scheme): bool
    {
        return in_array($scheme, [
            self::SCHEME_HTTP,
            self::SCHEME_HTTPS,
            self::SCHEME_GIT,
            self::SCHEME_SSH,
            'ftp',
            'ftps',
        ], true);
    }

    private static function assertScheme(string $scheme): void
    {
        if (preg_match('/^[a-z][a-z0-9+.-]*$/', $scheme) !== 1) {
            throw new \InvalidArgumentException('Git URL scheme is invalid');
        }
    }

    private static function stripIpv6Brackets(string $host): string
    {
        $inner = str_starts_with($host, '[') && str_ends_with($host, ']') ? substr($host, 1, -1) : null;
        return $inner ?? $host;
    }

    private static function normalizeSshUrlHost(string $host): string
    {
        if (str_starts_with($host, '[')) {
            if (str_ends_with($host, ']:')) {
                return substr($host, 1, -2);
            }

            return self::stripIpv6Brackets($host);
        }

        if (str_ends_with($host, ':') && substr_count($host, ':') === 1) {
            return substr($host, 0, -1);
        }

        return $host;
    }

    private static function isAbsoluteFilePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1;
    }

    private static function normalizeFilePath(string $path): string
    {
        $prefix = '';
        $rest = $path;
        if (preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1) {
            $prefix = substr($path, 0, 2);
            $rest = ltrim(substr($path, 2), '/\\');
        } elseif (str_starts_with($path, '/')) {
            $prefix = '/';
            $rest = ltrim($path, '/');
        }

        $segments = [];
        foreach (explode('/', $rest) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                if ($segments !== [] && end($segments) !== '..') {
                    array_pop($segments);
                    continue;
                }
                if ($prefix !== '') {
                    continue;
                }
            }
            $segments[] = $segment;
        }

        if ($prefix === '/') {
            return '/' . implode('/', $segments);
        }
        if ($prefix !== '') {
            return $prefix . '/' . implode('/', $segments);
        }

        return $segments === [] ? '.' : implode('/', $segments);
    }

    private static function scpColonPosition(string $input): ?int
    {
        if (str_starts_with($input, '[')) {
            $bracketEnd = strpos($input, ']');
            $colon = $bracketEnd === false
                ? strpos($input, ':')
                : strpos($input, ':', $bracketEnd + 1);
        } else {
            $colon = strpos($input, ':');
        }

        if ($colon === false) {
            return null;
        }

        $prefix = substr($input, 0, $colon);
        if (str_contains($prefix, '/')) {
            return null;
        }

        return $colon;
    }

    private function canonicalBytes(?string $password): string
    {
        $out = $this->scheme . '://';
        if ($this->host !== null) {
            if ($this->user !== null) {
                $out .= self::encodeUserInfo($this->user);
                if ($password !== null) {
                    $out .= ':' . self::encodeUserInfo($password);
                }
                $out .= '@';
            }
            $out .= $this->hostForSerialization($this->host, $this->port !== null);
        }
        if ($this->port !== null) {
            $out .= ':' . $this->port;
        }
        if (($this->scheme === self::SCHEME_SSH || $this->scheme === self::SCHEME_GIT) && !str_starts_with($this->path, '/')) {
            $out .= '/';
        }

        return $out . $this->encodedPath();
    }

    private function alternativeBytes(): string
    {
        $out = '';
        if ($this->user !== null) {
            $out .= $this->user . '@';
        }
        if ($this->host !== null) {
            $out .= $this->hostForSerialization($this->host, false);
        }
        if ($this->scheme === self::SCHEME_SSH) {
            $out .= ':';
        }

        return $out . $this->path;
    }

    private function encodedPath(): string
    {
        if ($this->scheme !== self::SCHEME_HTTP && $this->scheme !== self::SCHEME_HTTPS) {
            return $this->path;
        }

        return self::percentEncode($this->path, " \"`{}<>%");
    }

    private function hostForSerialization(string $host, bool $portIsPresent): string
    {
        if ($portIsPresent && str_contains($host, ':') && !str_starts_with($host, '[')) {
            return '[' . $host . ']';
        }
        if ($this->alternativeForm && str_contains($host, ':') && !str_starts_with($host, '[')) {
            return '[' . $host . ']';
        }

        return $host;
    }

    private function argumentSafe(?string $value): ?string
    {
        $safety = self::classifyArgument($value);

        return $safety['status'] === self::ARGUMENT_USABLE ? $safety['value'] : null;
    }

    /**
     * @return array{status: string, value: ?string}
     */
    private static function classifyArgument(?string $value): array
    {
        if ($value === null) {
            return ['status' => self::ARGUMENT_ABSENT, 'value' => null];
        }

        if (str_starts_with($value, '-')) {
            return ['status' => self::ARGUMENT_DANGEROUS, 'value' => $value];
        }

        return ['status' => self::ARGUMENT_USABLE, 'value' => $value];
    }

    private static function encodeUserInfo(string $input): string
    {
        return self::percentEncode($input, " \"#%/<>?@[\\]^`{|}");
    }

    private static function percentEncode(string $input, string $encodeAscii): string
    {
        $out = '';
        $length = strlen($input);
        for ($index = 0; $index < $length; $index++) {
            $byte = $input[$index];
            $ord = ord($byte);
            if ($ord < 0x20 || $ord >= 0x7f || str_contains($encodeAscii, $byte)) {
                $out .= sprintf('%%%02X', $ord);
            } else {
                $out .= $byte;
            }
        }

        return $out;
    }
}
