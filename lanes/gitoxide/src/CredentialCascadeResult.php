<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class CredentialCascadeResult
{
    public function __construct(
        public readonly string $username,
        public readonly string $password,
        public readonly ?string $oauthRefreshToken,
        public readonly bool $quit,
        public readonly CredentialContext $context,
        private readonly string $nextActionBytes,
    ) {
    }

    /**
     * @return array{username: string, password: string, oauthRefreshToken: ?string}
     */
    public function identity(): array
    {
        return [
            'username' => $this->username,
            'password' => $this->password,
            'oauthRefreshToken' => $this->oauthRefreshToken,
        ];
    }

    public function nextActionBytes(): string
    {
        return $this->nextActionBytes;
    }

    public function nextActionContext(): CredentialContext
    {
        return CredentialContext::fromBytes($this->nextActionBytes);
    }
}
