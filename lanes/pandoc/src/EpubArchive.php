<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * The narrow package surface the EPUB content reader needs. The full
 * ZipPackage model remains available for rich package inspection elsewhere.
 */
interface EpubArchive
{
    public function has(string $partName): bool;

    public function read(string $partName): string;
}
