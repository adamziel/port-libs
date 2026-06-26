<?php

declare(strict_types=1);

use PortLibs\Pandoc\ZipOpcPackage;

function pandoc_zipopc_test_u16(string $bytes, int $offset): int
{
    $value = unpack('v', substr($bytes, $offset, 2));
    if (!is_array($value)) {
        throw new RuntimeException('Unable to read ZIP uint16 field');
    }

    return (int) $value[1];
}

function pandoc_zipopc_test_u32(string $bytes, int $offset): int
{
    $value = unpack('V', substr($bytes, $offset, 4));
    if (!is_array($value)) {
        throw new RuntimeException('Unable to read ZIP uint32 field');
    }

    return (int) $value[1];
}

function pandoc_zipopc_test_put_u16(string &$bytes, int $offset, int $value): void
{
    $bytes = substr_replace($bytes, pack('v', $value), $offset, 2);
}

function pandoc_zipopc_test_put_u32(string &$bytes, int $offset, int $value): void
{
    $bytes = substr_replace($bytes, pack('V', $value), $offset, 4);
}

function pandoc_zipopc_test_add_first_local_extra_field(string $path, string $extra): void
{
    $bytes = file_get_contents($path);
    if (!is_string($bytes) || substr($bytes, 0, 4) !== "PK\x03\x04") {
        throw new RuntimeException('Unable to patch ZIP local header');
    }

    $nameLength = pandoc_zipopc_test_u16($bytes, 26);
    $extraLength = pandoc_zipopc_test_u16($bytes, 28);
    $insertOffset = 30 + $nameLength + $extraLength;
    $extraBytes = strlen($extra);
    pandoc_zipopc_test_put_u16($bytes, 28, $extraLength + $extraBytes);

    $eocdOffset = strrpos($bytes, "PK\x05\x06");
    if (!is_int($eocdOffset)) {
        throw new RuntimeException('Unable to locate ZIP EOCD while patching fixture');
    }

    $centralDirectorySize = pandoc_zipopc_test_u32($bytes, $eocdOffset + 12);
    $centralDirectoryOffset = pandoc_zipopc_test_u32($bytes, $eocdOffset + 16);
    $centralDirectory = substr($bytes, $centralDirectoryOffset, $centralDirectorySize);
    $offset = 0;
    $centralLength = strlen($centralDirectory);
    while ($offset + 46 <= $centralLength && substr($centralDirectory, $offset, 4) === "PK\x01\x02") {
        $localOffset = pandoc_zipopc_test_u32($centralDirectory, $offset + 42);
        if ($localOffset >= $insertOffset) {
            pandoc_zipopc_test_put_u32($centralDirectory, $offset + 42, $localOffset + $extraBytes);
        }

        $fileNameLength = pandoc_zipopc_test_u16($centralDirectory, $offset + 28);
        $centralExtraLength = pandoc_zipopc_test_u16($centralDirectory, $offset + 30);
        $commentLength = pandoc_zipopc_test_u16($centralDirectory, $offset + 32);
        $offset += 46 + $fileNameLength + $centralExtraLength + $commentLength;
    }

    $tail = substr($bytes, $eocdOffset);
    pandoc_zipopc_test_put_u32($tail, 16, $centralDirectoryOffset + $extraBytes);
    $patched = substr($bytes, 0, $insertOffset)
        . $extra
        . substr($bytes, $insertOffset, $centralDirectoryOffset - $insertOffset)
        . $centralDirectory
        . $tail;

    if (file_put_contents($path, $patched) === false) {
        throw new RuntimeException('Unable to write patched ZIP local header');
    }
}

return [
    'normalizes shared zip opc package paths and directories' => static function (TestRunner $t): void {
        $t->same('OPS/chapter.xhtml', ZipOpcPackage::normalizePath('/OPS\\text/../chapter.xhtml'));
        $t->same('META-INF/container.xml', ZipOpcPackage::normalizePath('../META-INF/./container.xml'));
        $t->same('OPS/chapter.xhtml', ZipOpcPackage::normalizePathStrict('/OPS\\text/../chapter.xhtml'));
        $t->throws(InvalidArgumentException::class, static fn (): string => ZipOpcPackage::normalizePathStrict('../META-INF/./container.xml'));
        $t->same('word/_rels', ZipOpcPackage::dirname('word/_rels/document.xml.rels'));
        $t->same('', ZipOpcPackage::dirname('mimetype'));
    },
    'opens lists reads and probes native zip opc package entries' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-zipopc-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary ZIP package path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary ZIP package');
        }
        $zip->addFromString('[Content_Types].xml', '<Types/>');
        $zip->addFromString('word/document.xml', '<w:document/>');
        $zip->addFromString('word/media/image.png', 'image-bytes');
        $zip->close();

        try {
            $package = ZipOpcPackage::open($path, 'TEST');
            try {
                $t->same('[Content_Types].xml', $package->firstEntryName());
                $t->same(['[Content_Types].xml', 'word/document.xml', 'word/media/image.png'], $package->entryNames());
                $t->same('<w:document/>', $package->read('word\\document.xml'));
                $t->same('image-bytes', $package->requireRead('word/media/image.png', 'missing image'));
                $t->same(true, $package->exists('./word/media/image.png'));
                $t->same(false, $package->exists('../missing.bin'));
                $stat = $package->stat('[Content_Types].xml');
                $t->true(is_array($stat));
            } finally {
                $package->close();
            }
        } finally {
            @unlink($path);
        }
    },
    'reads first local zip header metadata from package bytes' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-zipopc-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary ZIP package path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary ZIP package');
        }
        $zip->addFromString('mimetype', 'application/epub+zip');
        $zip->addFromString('META-INF/container.xml', '<container/>');
        $zip->close();
        pandoc_zipopc_test_add_first_local_extra_field($path, 'ABCD');

        try {
            $header = ZipOpcPackage::firstLocalFileHeaderFromPath($path);
            $t->same('mimetype', $header['name'] ?? null);
            $t->same(4, $header['extraBytes'] ?? null);

            $package = ZipOpcPackage::open($path, 'TEST');
            try {
                $packageHeader = $package->firstLocalFileHeader();
                $t->same('mimetype', $packageHeader['name'] ?? null);
                $t->same(4, $packageHeader['extraBytes'] ?? null);
            } finally {
                $package->close();
            }
        } finally {
            @unlink($path);
        }
    },
    'enforces zip opc entry count and read size budgets' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-zipopc-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary ZIP package path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary ZIP package');
        }
        $zip->addFromString('one.xml', str_repeat('a', 8));
        $zip->addFromString('two.xml', 'bb');
        $zip->close();

        try {
            $package = ZipOpcPackage::open($path, 'TEST');
            try {
                $t->same('aaaaaaaa', $package->read('one.xml', 8));
                $t->throws(RuntimeException::class, static fn (): array => $package->entryNames(1));
                $t->throws(RuntimeException::class, static fn (): ?string => $package->read('one.xml', 4));
                $t->throws(RuntimeException::class, static fn (): string => $package->requireRead('one.xml', 'missing one', 4));
            } finally {
                $package->close();
            }
        } finally {
            @unlink($path);
        }
    },
];
