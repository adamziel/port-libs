<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class EncryptionConsistencyDecision
{
    public function __construct(
        public readonly ?string $errorCode = null,
        public readonly string $message = '',
        public readonly string $acceptedFolderTokenHex = '',
        public readonly bool $clusterConfigResendNeeded = false,
    ) {
        if ($this->acceptedFolderTokenHex !== '' && !preg_match('/^(?:[0-9a-f]{2})+$/', $this->acceptedFolderTokenHex)) {
            throw new \InvalidArgumentException('Expected lowercase hexadecimal bytes for accepted folder token');
        }
    }

    public function ok(): bool
    {
        return $this->errorCode === null;
    }
}
