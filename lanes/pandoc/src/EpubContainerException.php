<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class EpubContainerException extends \InvalidArgumentException
{
    /**
     * @param list<array<string, mixed>> $diagnostics
     */
    public function __construct(string $message, private readonly array $diagnostics = [])
    {
        parent::__construct($message);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }
}
