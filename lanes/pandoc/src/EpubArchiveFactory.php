<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class EpubArchiveFactory
{
    public static function fromString(string $bytes): EpubArchive
    {
        if (class_exists(\ZipArchive::class)) {
            try {
                return EpubZipArchive::fromString($bytes);
            } catch (\RuntimeException) {
                // The bounded pure-PHP package reader remains the fallback
                // for ZIP variants the extension cannot open.
            }
        }

        return ZipPackage::fromString($bytes);
    }
}
