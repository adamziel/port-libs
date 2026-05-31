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
        foreach (explode("\n", $input) as $line) {
            $line = str_ends_with($line, "\r") ? substr($line, 0, -1) : $line;
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
            self::validateField($key, $value);
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
            $parts = parse_url($url);
        } catch (\ValueError $error) {
            throw new \InvalidArgumentException('Credential context URL could not be parsed', 0, $error);
        }
        if (!is_array($parts) || !isset($parts['scheme']) || !is_string($parts['scheme'])) {
            throw new \InvalidArgumentException("Either 'url' field or both 'protocol' and 'host' fields must be provided");
        }

        $protocol = strtolower($parts['scheme']);
        $host = $this->host;
        if (isset($parts['host']) && is_string($parts['host']) && $parts['host'] !== '') {
            $host = $parts['host'];
            $port = isset($parts['port']) ? (int) $parts['port'] : null;
            if ($port !== null && self::defaultPort($protocol) !== $port) {
                $host .= ':' . $port;
            }
        }
        if ($host === null || $host === '') {
            throw new \InvalidArgumentException("Either 'url' field or both 'protocol' and 'host' fields must be provided");
        }

        $path = $this->path;
        if (($protocol !== 'http' && $protocol !== 'https') || $useHttpPath) {
            $trimmedPath = isset($parts['path']) && is_string($parts['path']) ? trim($parts['path'], '/') : '';
            if ($trimmedPath !== '') {
                $path = $trimmedPath;
            }
        }

        return new self(
            protocol: $protocol,
            host: $host,
            path: $path,
            username: isset($parts['user']) ? rawurldecode((string) $parts['user']) : null,
            password: isset($parts['pass']) ? rawurldecode((string) $parts['pass']) : null,
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
        if (!array_key_exists($key, $fields) || preg_match('/^[+-]?\d+$/', $fields[$key]) !== 1) {
            return null;
        }

        return (int) $fields[$key];
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
        if (preg_match('/^[+-]?\d+$/', $value) === 1) {
            return (int) $value !== 0;
        }

        return null;
    }

    private static function validateField(string $key, string $value): void
    {
        if (str_contains($key, "\0")
            || str_contains($key, "\n")
            || str_contains($value, "\0")
            || str_contains($value, "\n")
        ) {
            throw new \InvalidArgumentException('Credential context keys and values must not contain NUL bytes or newlines');
        }
    }

    private static function defaultPort(string $scheme): ?int
    {
        return match ($scheme) {
            'http' => 80,
            'https' => 443,
            'ssh' => 22,
            default => null,
        };
    }
}
