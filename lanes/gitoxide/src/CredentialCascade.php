<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class CredentialCascade
{
    /** @var list<callable(string, string, ?CredentialContext):(CredentialContext|string|null)> */
    private array $helpers;

    /**
     * @param list<callable(string, string, ?CredentialContext):(CredentialContext|string|null)> $helpers
     */
    public function __construct(
        array $helpers,
        private readonly bool $useHttpPath = false,
        private readonly bool $queryUserOnly = false,
        private readonly ?int $nowUtc = null,
        private readonly mixed $usernamePrompt = null,
        private readonly mixed $passwordPrompt = null,
    ) {
        foreach ($helpers as $helper) {
            if (!is_callable($helper)) {
                throw new \InvalidArgumentException('Credential cascade helpers must be callable');
            }
        }
        foreach ([
            'usernamePrompt' => $this->usernamePrompt,
            'passwordPrompt' => $this->passwordPrompt,
        ] as $name => $prompt) {
            if ($prompt !== null && !is_callable($prompt)) {
                throw new \InvalidArgumentException("Credential cascade {$name} must be callable when provided");
            }
        }

        $this->helpers = array_values($helpers);
    }

    /**
     * @param list<callable(string, string, ?CredentialContext):(CredentialContext|string|null)> $helpers
     */
    public static function getForUrl(
        string $url,
        array $helpers,
        bool $useHttpPath = false,
        bool $queryUserOnly = false,
        ?int $nowUtc = null,
        mixed $usernamePrompt = null,
        mixed $passwordPrompt = null,
    ): CredentialCascadeResult {
        return (new self(
            $helpers,
            $useHttpPath,
            $queryUserOnly,
            $nowUtc,
            $usernamePrompt,
            $passwordPrompt,
        ))->get(
            new CredentialContext(url: $url),
        );
    }

    public function get(CredentialContext $context): CredentialCascadeResult
    {
        $context = $context->destructureUrl($this->useHttpPath);
        $url = $context->url;
        $context = self::contextWith($context, ['url' => null]);

        if ($this->queryUserOnly && $context->password === null) {
            $context = self::contextWith($context, ['password' => '']);
        }

        foreach ($this->helpers as $helper) {
            try {
                $response = $helper('get', $context->storageBytes(), $context);
            } catch (\Throwable) {
                continue;
            }

            $helperContext = $this->responseToContext($response);
            if ($helperContext === null) {
                continue;
            }

            $helperQuit = $helperContext->quit === true;
            $context = $this->mergeContext($context, $helperContext, $url);
            if ($context->username !== null && $context->password !== null) {
                break;
            }
            if ($helperQuit) {
                $context = self::contextWith($context, ['quit' => true]);
                break;
            }
        }

        if ($this->usernamePrompt !== null || $this->passwordPrompt !== null) {
            $context = self::contextWith($context, ['url' => $url]);
            if ($context->username === null) {
                $context = self::contextWith($context, [
                    'username' => $this->promptFor($context, 'Username', 'visible', $this->usernamePrompt),
                ]);
            }
            if ($context->password === null) {
                $context = self::contextWith($context, [
                    'password' => $this->promptFor($context, 'Password', 'hidden', $this->passwordPrompt),
                ]);
            }
        }

        if ($context->username === null || $context->password === null) {
            if ($context->quit === true) {
                throw new \RuntimeException('Credential helper asked to stop trying to obtain credentials');
            }

            throw new \RuntimeException(
                "Could not obtain identity for context: {$context->redacted()->diagnosticBytes()}",
            );
        }

        return new CredentialCascadeResult(
            username: $context->username,
            password: $context->password,
            oauthRefreshToken: $context->oauthRefreshToken,
            quit: $context->quit ?? false,
            context: $context,
            nextActionBytes: $context->storageBytes(),
        );
    }

    public function store(CredentialCascadeResult|CredentialContext|string $target): void
    {
        $this->invokeAll('store', $this->payloadFor($target) . "\n");
    }

    public function erase(CredentialCascadeResult|CredentialContext|string $target): void
    {
        $this->invokeAll('erase', $this->payloadFor($target) . "\n");
    }

    private function responseToContext(mixed $response): ?CredentialContext
    {
        if ($response === null) {
            return null;
        }
        if ($response instanceof CredentialContext) {
            return $response;
        }
        if (is_string($response)) {
            return CredentialContext::fromBytes($response);
        }

        throw new \InvalidArgumentException('Credential helper responses must be CredentialContext, string, or null');
    }

    private function mergeContext(CredentialContext $context, CredentialContext $source, ?string &$url): CredentialContext
    {
        $changes = [];
        foreach ([
            'protocol' => $source->protocol,
            'host' => $source->host,
            'path' => $source->path,
            'username' => $source->username,
            'password' => $source->password,
            'oauthRefreshToken' => $source->oauthRefreshToken,
            'passwordExpiryUtc' => $source->passwordExpiryUtc,
        ] as $key => $value) {
            if ($value !== null) {
                $changes[$key] = $value;
            }
        }

        $context = self::contextWith($context, $changes);
        if ($source->url !== null) {
            $context = self::contextWith($context, ['url' => $source->url])->destructureUrl($this->useHttpPath);
            $url = $context->url;
            $context = self::contextWith($context, ['url' => null]);
        }

        if ($context->passwordExpiryUtc !== null && $context->passwordExpiryUtc < $this->now()) {
            $context = self::contextWith($context->clearSecrets(), ['passwordExpiryUtc' => null]);
        }

        return $context;
    }

    private function promptFor(CredentialContext $context, string $field, string $mode, mixed $prompt): string
    {
        if (!is_callable($prompt)) {
            throw new \RuntimeException("Credential prompt for {$field} is disabled");
        }

        $message = $context->toPrompt($field);
        $value = $prompt($context, $message, $mode);
        if (!is_string($value)) {
            throw new \RuntimeException("Credential prompt for {$field} must return a string");
        }

        return $value;
    }

    private function payloadFor(CredentialCascadeResult|CredentialContext|string $target): string
    {
        if ($target instanceof CredentialCascadeResult) {
            return $target->nextActionBytes();
        }
        if ($target instanceof CredentialContext) {
            return $target->storageBytes();
        }

        return $target;
    }

    private function invokeAll(string $action, string $payload): void
    {
        foreach ($this->helpers as $helper) {
            try {
                $helper($action, $payload, null);
            } catch (\Throwable) {
                continue;
            }
        }
    }

    private function now(): int
    {
        return $this->nowUtc ?? time();
    }

    /**
     * @param array<string, mixed> $changes
     */
    private static function contextWith(CredentialContext $context, array $changes): CredentialContext
    {
        return new CredentialContext(
            protocol: self::changed($changes, 'protocol', $context->protocol),
            host: self::changed($changes, 'host', $context->host),
            path: self::changed($changes, 'path', $context->path),
            username: self::changed($changes, 'username', $context->username),
            password: self::changed($changes, 'password', $context->password),
            oauthRefreshToken: self::changed($changes, 'oauthRefreshToken', $context->oauthRefreshToken),
            passwordExpiryUtc: self::changed($changes, 'passwordExpiryUtc', $context->passwordExpiryUtc),
            url: self::changed($changes, 'url', $context->url),
            quit: self::changed($changes, 'quit', $context->quit),
        );
    }

    /**
     * @param array<string, mixed> $changes
     */
    private static function changed(array $changes, string $key, mixed $fallback): mixed
    {
        return array_key_exists($key, $changes) ? $changes[$key] : $fallback;
    }
}
