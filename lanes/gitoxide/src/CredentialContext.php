<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class CredentialContext
{
    public function __construct(
        public readonly ?string $protocol = null,
        public readonly ?string $host = null,
        public readonly ?string $path = null,
        public readonly ?string $username = null,
        public readonly ?string $password = null,
        public readonly ?string $oauthRefreshToken = null,
        public readonly ?int $passwordExpiryUtc = null,
        public readonly ?string $url = null,
        public readonly ?bool $quit = null,
    ) {
    }

    public static function fromBytes(string $input): self
    {
        $fields = [];
        foreach (self::protocolLines($input) as $line) {
            if ($line === '') {
                break;
            }

            $equals = strpos($line, '=');
            if ($equals === false) {
                throw new \InvalidArgumentException("Credential context line must use key=value syntax: {$line}");
            }

            $key = substr($line, 0, $equals);
            $value = substr($line, $equals + 1);
            self::validateField($key, $value);
            if (self::isUtf8ValueField($key) && preg_match('//u', $value) !== 1) {
                throw new \InvalidArgumentException("Credential context field {$key} must be valid UTF-8");
            }
            $fields[$key] = $value;
        }

        return new self(
            protocol: self::utf8Field($fields, 'protocol'),
            host: self::utf8Field($fields, 'host'),
            path: $fields['path'] ?? null,
            username: self::utf8Field($fields, 'username'),
            password: self::utf8Field($fields, 'password'),
            oauthRefreshToken: self::utf8Field($fields, 'oauth_refresh_token'),
            passwordExpiryUtc: self::integerField($fields, 'password_expiry_utc'),
            url: $fields['url'] ?? null,
            quit: self::boolField($fields, 'quit'),
        );
    }

    public function storageBytes(): string
    {
        $bytes = '';
        foreach ([
            'url' => $this->url,
            'path' => $this->path,
            'protocol' => $this->protocol,
            'host' => $this->host,
            'username' => $this->username,
            'password' => $this->password,
            'oauth_refresh_token' => $this->oauthRefreshToken,
        ] as $key => $value) {
            if ($value === null) {
                continue;
            }
            self::validateField($key, $value, !in_array($key, ['url', 'path'], true));
            $bytes .= "{$key}={$value}\n";
        }

        if ($this->passwordExpiryUtc !== null) {
            $key = 'password_expiry_utc';
            $value = (string) $this->passwordExpiryUtc;
            self::validateField($key, $value);
            $bytes .= "{$key}={$value}\n";
        }

        return $bytes;
    }

    public function clearSecrets(): self
    {
        return new self(
            protocol: $this->protocol,
            host: $this->host,
            path: $this->path,
            username: $this->username,
            password: null,
            oauthRefreshToken: null,
            passwordExpiryUtc: $this->passwordExpiryUtc,
            url: $this->url,
            quit: $this->quit,
        );
    }

    public function redacted(): self
    {
        return new self(
            protocol: $this->protocol,
            host: $this->host,
            path: $this->path,
            username: $this->username,
            password: $this->password === null ? null : '<redacted>',
            oauthRefreshToken: $this->oauthRefreshToken === null ? null : '<redacted>',
            passwordExpiryUtc: $this->passwordExpiryUtc,
            url: $this->url,
            quit: $this->quit,
        );
    }

    public function toUrl(): ?string
    {
        if ($this->protocol === null) {
            return null;
        }

        $url = $this->protocol . '://';
        if ($this->username !== null) {
            $url .= $this->username . '@';
        }
        if ($this->host !== null) {
            $url .= $this->host;
        }
        if ($this->path !== null) {
            $url .= str_starts_with($this->path, '/') ? $this->path : '/' . $this->path;
        }

        return $url;
    }

    public function toPrompt(string $field): string
    {
        $url = $this->toUrl();

        return $url === null ? "{$field}: " : "{$field} for {$url}: ";
    }

    public function destructureUrl(bool $useHttpPath = false): self
    {
        $url = $this->url ?? $this->toUrl();
        if ($url === null) {
            throw new \InvalidArgumentException("Either 'url' field or both 'protocol' and 'host' fields must be provided");
        }

        try {
            $parsed = GitUrl::parse($url);
        } catch (\InvalidArgumentException $error) {
            throw new \InvalidArgumentException('Credential context URL could not be parsed', 0, $error);
        }

        $protocol = $parsed->scheme();
        $host = null;
        if ($parsed->host() !== null && $parsed->host() !== '') {
            $host = $parsed->host();
            $port = $parsed->port();
            if ($port !== null && self::defaultPort($protocol) !== $port) {
                $host .= ':' . $port;
            }
        }
        if (($host === null || $host === '') && in_array($protocol, ['http', 'https', 'ssh', 'git'], true)) {
            throw new \InvalidArgumentException("Either 'url' field or both 'protocol' and 'host' fields must be provided");
        }

        $path = $this->path;
        if (($protocol !== 'http' && $protocol !== 'https') || $useHttpPath) {
            $trimmedPath = trim($parsed->path(), '/');
            $path = $trimmedPath === '' ? null : $trimmedPath;
        }

        return new self(
            protocol: $protocol,
            host: $host,
            path: $path,
            username: $parsed->user(),
            password: $parsed->password(),
            oauthRefreshToken: $this->oauthRefreshToken,
            passwordExpiryUtc: $this->passwordExpiryUtc,
            url: $url,
            quit: $this->quit,
        );
    }

    /**
     * @param array<string, string> $fields
     */
    private static function utf8Field(array $fields, string $key): ?string
    {
        if (!array_key_exists($key, $fields)) {
            return null;
        }
        $value = $fields[$key];
        if (preg_match('//u', $value) !== 1) {
            throw new \InvalidArgumentException("Credential context field {$key} must be valid UTF-8");
        }

        return $value;
    }

    /**
     * @param array<string, string> $fields
     */
    private static function integerField(array $fields, string $key): ?int
    {
        if (!array_key_exists($key, $fields)) {
            return null;
        }

        return self::parseSignedI64($fields[$key]);
    }

    /**
     * @param array<string, string> $fields
     */
    private static function boolField(array $fields, string $key): ?bool
    {
        if (!array_key_exists($key, $fields)) {
            return null;
        }

        $value = strtolower($fields[$key]);
        if ($value === '') {
            return false;
        }
        if (in_array($value, ['true', 'on', 'yes'], true)) {
            return true;
        }
        if (in_array($value, ['false', 'off', 'no'], true)) {
            return false;
        }
        $integer = self::parseSignedI64($value);
        if ($integer !== null) {
            return $integer !== 0;
        }

        return null;
    }

    private static function parseSignedI64(string $value): ?int
    {
        if (preg_match('/^[+-]?\d+$/', $value) !== 1) {
            return null;
        }

        $negative = str_starts_with($value, '-');
        $digits = $negative || str_starts_with($value, '+') ? substr($value, 1) : $value;
        $digits = ltrim($digits, '0');
        if ($digits === '') {
            return 0;
        }

        $limit = $negative ? '9223372036854775808' : '9223372036854775807';
        if (strlen($digits) > strlen($limit) || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) > 0)) {
            return null;
        }

        return (int) ($negative ? '-' . $digits : $digits);
    }

    private static function validateField(string $key, string $value, bool $valueMustBeUtf8 = false): void
    {
        if (str_contains($key, "\0")
            || str_contains($key, "\n")
            || str_contains($value, "\0")
            || str_contains($value, "\n")
        ) {
            throw new \InvalidArgumentException('Credential context keys and values must not contain NUL bytes or newlines');
        }
        if (preg_match('//u', $key) !== 1) {
            throw new \InvalidArgumentException('Credential context keys must be valid UTF-8');
        }
        if ($valueMustBeUtf8 && preg_match('//u', $value) !== 1) {
            throw new \InvalidArgumentException("Credential context field {$key} must be valid UTF-8");
        }
    }

    /**
     * @return \Generator<int, string>
     */
    private static function protocolLines(string $input): \Generator
    {
        $offset = 0;
        $length = strlen($input);
        while ($offset < $length) {
            $newline = strpos($input, "\n", $offset);
            if ($newline === false) {
                yield substr($input, $offset);

                return;
            }

            $line = substr($input, $offset, $newline - $offset);
            yield str_ends_with($line, "\r") ? substr($line, 0, -1) : $line;
            $offset = $newline + 1;
        }
    }

    private static function isUtf8ValueField(string $key): bool
    {
        return in_array($key, ['protocol', 'host', 'username', 'password', 'oauth_refresh_token'], true);
    }

    private static function defaultPort(string $scheme): ?int
    {
        return match ($scheme) {
            'http' => 80,
            'https' => 443,
            'ssh' => 22,
            'git' => 9418,
            default => null,
        };
    }
}
