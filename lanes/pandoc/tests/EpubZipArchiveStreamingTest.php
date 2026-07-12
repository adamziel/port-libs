<?php

declare(strict_types=1);

use PortLibs\Pandoc\EpubZipArchive;

return [
    'reads bounded epub entries and hashes them without materializing a package model' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-zip-stream-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }

        $payload = str_repeat("streamed image payload\n", 1024);
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($path);
            throw new RuntimeException('Unable to create temporary EPUB ZIP archive');
        }
        $zip->addFromString('mimetype', 'application/epub+zip');
        $zip->addFromString('OPS/images/cover.bin', $payload);
        $zip->close();

        try {
            $bytes = file_get_contents($path);
            if (!is_string($bytes)) {
                throw new RuntimeException('Unable to read temporary EPUB package');
            }
            $archive = EpubZipArchive::fromString($bytes);
            $t->same(['mimetype', 'OPS/images/cover.bin'], $archive->names());
            $t->same($payload, $archive->readBounded('OPS/images/cover.bin', strlen($payload)));
            $t->same([
                'byteLength' => strlen($payload),
                'sha1' => sha1($payload),
            ], $archive->entryDigest('OPS/images/cover.bin'));

            $threw = false;
            try {
                $archive->readBounded('OPS/images/cover.bin', strlen($payload) - 1);
            } catch (RuntimeException) {
                $threw = true;
            }
            $t->true($threw);
            unset($archive);
        } finally {
            @unlink($path);
        }
    },
];
