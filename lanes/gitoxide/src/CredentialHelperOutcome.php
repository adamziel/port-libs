<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class CredentialHelperOutcome
{
    public function __construct(
        public readonly ?string $username,
        public readonly ?string $password,
        public readonly ?string $oauthRefreshToken,
        public readonly bool $quit,
        private readonly string $nextActionBytes,
    ) {
    }

    /**
     * @return array{username: string, password: string, oauthRefreshToken: ?string}|null
     */
    public function identity(): ?array
    {
        if ($this->username === null || $this->password === null) {
            return null;
        }

        return [
            'username' => $this->username,
            'password' => $this->password,
            'oauthRefreshToken' => $this->oauthRefreshToken,
        ];
    }

    /**
     * @return array{username: string, password: string, oauthRefreshToken: ?string}
     */
    public static function requireIdentity(?self $outcome, CredentialContext $requestContext): array
    {
        if ($outcome === null) {
            throw self::identityMissing($requestContext);
        }

        $identity = $outcome->identity();
        if ($identity !== null) {
            return $identity;
        }

        if ($outcome->quit) {
            throw new \RuntimeException('Credential helper asked to stop trying to obtain credentials');
        }

        throw self::identityMissing($requestContext);
    }

    public function nextActionBytes(): string
    {
        return $this->nextActionBytes;
    }

    public function nextActionContext(): CredentialContext
    {
        return CredentialContext::fromBytes($this->nextActionBytes);
    }

    private static function identityMissing(CredentialContext $requestContext): \RuntimeException
    {
        return new \RuntimeException(
            "Could not obtain identity for context: {$requestContext->redacted()->diagnosticBytes()}",
        );
    }
}
