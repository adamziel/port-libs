<?php

declare(strict_types=1);

namespace PortLibs\LightningCSS;

final class CssBundleException extends \RuntimeException
{
    public function __construct(
        public readonly string $kind,
        string $message,
        public readonly ?string $sourceFile = null,
        public readonly ?int $sourceLine = null,
        public readonly ?int $sourceColumn = null,
    ) {
        parent::__construct($message);
    }
}
