<?php

declare(strict_types=1);

use PortLibs\Pandoc\ZipPackage;
use PortLibs\Pandoc\ZipPackageEntry;

$crc32 = static fn (string $bytes): int => (int) sprintf('%u', crc32($bytes));

/**
 * @param list<array{
 *     name:string,
 *     data?:string,
 *     method?:int,
 *     flags?:int,
 *     descriptor?:bool,
 *     centralCrc?:int,
 *     localName?:string,
 *     diskStart?:int,
 *     comment?:string
 * }> $entries
 */
$buildZipPackage = static function (array $entries, string $comment = '') use ($crc32): string {
    $body = '';
    $central = '';

    foreach ($entries as $entry) {
        $name = $entry['name'];
        $localName = $entry['localName'] ?? $name;
        $data = $entry['data'] ?? '';
        $method = $entry['method'] ?? 0;
        $flags = $entry['flags'] ?? 0x0800;
        $descriptor = (bool) ($entry['descriptor'] ?? false);
        if ($descriptor) {
            $flags |= 0x0008;
        }

        $compressed = $method === 8 ? gzdeflate($data) : $data;
        $compressedSize = strlen($compressed);
        $uncompressedSize = strlen($data);
        $actualCrc = $crc32($data);
        $centralCrc = $entry['centralCrc'] ?? $actualCrc;
        $offset = strlen($body);
        $localCrc = $descriptor ? 0 : $actualCrc;
        $localCompressedSize = $descriptor ? 0 : $compressedSize;
        $localUncompressedSize = $descriptor ? 0 : $uncompressedSize;

        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            $flags,
            $method,
            0,
            0,
            $localCrc,
            $localCompressedSize,
            $localUncompressedSize,
            strlen($localName),
            0
        );
        $body .= $localName . $compressed;
        if ($descriptor) {
            $body .= "PK\x07\x08" . pack('VVV', $actualCrc, $compressedSize, $uncompressedSize);
        }

        $entryComment = $entry['comment'] ?? '';
        $central .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            0x0314,
            20,
            $flags,
            $method,
            0,
            0,
            $centralCrc,
            $compressedSize,
            $uncompressedSize,
            strlen($name),
            0,
            strlen($entryComment),
            $entry['diskStart'] ?? 0,
            0,
            0,
            $offset
        );
        $central .= $name . $entryComment;
    }

    $centralOffset = strlen($body);
    $centralSize = strlen($central);

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, count($entries), count($entries), $centralSize, $centralOffset, strlen($comment))
        . $comment;
};

return [
    'reads current zip package central directory and stored deflated parts' => static function (TestRunner $t) use ($buildZipPackage, $crc32): void {
        $zip = $buildZipPackage([
            [
                'name' => '[Content_Types].xml',
                'data' => '<Types><Default Extension="xml" ContentType="application/xml"/></Types>',
                'method' => 0,
            ],
            [
                'name' => '_rels/.rels',
                'data' => '<Relationships><Relationship Target="word/document.xml"/></Relationships>',
                'method' => 8,
                'comment' => 'root rels',
            ],
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:body><w:p>Imported post</w:p></w:body></w:document>',
                'method' => 8,
            ],
            [
                'name' => 'word/media/',
                'data' => '',
                'method' => 0,
            ],
        ], 'package comment');

        $package = ZipPackage::fromString($zip);
        $entries = $package->entries();

        $t->same([
            '[Content_Types].xml',
            '_rels/.rels',
            'word/document.xml',
            'word/media/',
        ], $package->names());
        $t->same(4, count($entries));
        $t->true($entries[0] instanceof ZipPackageEntry);
        $t->same('[Content_Types].xml', $entries[0]->name);
        $t->same(0, $entries[0]->compressionMethod);
        $t->same($crc32('<Types><Default Extension="xml" ContentType="application/xml"/></Types>'), $entries[0]->crc32);
        $t->same(sprintf('%08x', $entries[0]->crc32), $entries[0]->crc32Hex());
        $t->same('_rels/.rels', $entries[1]->name);
        $t->same(8, $entries[1]->compressionMethod);
        $t->same('root rels', $entries[1]->comment);
        $t->true($entries[3]->isDirectory());
        $t->true($package->has('/word/document.xml'));
        $t->same('<Types><Default Extension="xml" ContentType="application/xml"/></Types>', $package->read('[Content_Types].xml'));
        $t->same('<Relationships><Relationship Target="word/document.xml"/></Relationships>', $package->read('_rels/.rels'));
        $t->same('<w:document><w:body><w:p>Imported post</w:p></w:body></w:document>', $package->read('/word/document.xml'));
        $t->same('', $package->read('word/media/'));
        $t->true($package->centralDirectoryOffset() > 0);
    },

    'reads package entries whose local header uses a data descriptor' => static function (TestRunner $t) use ($buildZipPackage): void {
        $zip = $buildZipPackage([
            [
                'name' => 'word/comments.xml',
                'data' => str_repeat('<w:comment>review</w:comment>', 8),
                'method' => 8,
                'descriptor' => true,
            ],
        ]);

        $package = ZipPackage::fromString($zip);
        $entry = $package->entry('word/comments.xml');

        $t->same(8, $entry->compressionMethod);
        $t->same(0x0808, $entry->generalPurposeFlags);
        $t->same(str_repeat('<w:comment>review</w:comment>', 8), $package->read('word/comments.xml'));
    },

    'rejects unsafe package part names before exposing entries' => static function (TestRunner $t) use ($buildZipPackage): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            ['name' => '/word/document.xml', 'data' => 'absolute'],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            ['name' => 'word/../document.xml', 'data' => 'traversal'],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            ['name' => 'word\\document.xml', 'data' => 'backslash'],
        ])));
        $t->throws(\RuntimeException::class, static fn (): bool => ZipPackage::fromString($buildZipPackage([
            ['name' => 'word/document.xml', 'data' => 'ok'],
        ]))->has('../word/document.xml'));
    },

    'rejects duplicate encrypted and split zip package entries' => static function (TestRunner $t) use ($buildZipPackage): void {
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            ['name' => 'word/document.xml', 'data' => 'first'],
            ['name' => 'word/document.xml', 'data' => 'second'],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            ['name' => 'word/encrypted.xml', 'data' => 'secret', 'flags' => 0x0801],
        ])));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($buildZipPackage([
            ['name' => 'word/split.xml', 'data' => 'split', 'diskStart' => 1],
        ])));
    },

    'verifies crc and local file header names before returning part bytes' => static function (TestRunner $t) use ($buildZipPackage): void {
        $crcMismatch = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'data' => '<w:document>changed</w:document>',
                'method' => 8,
                'centralCrc' => 0,
            ],
        ]));
        $localNameMismatch = ZipPackage::fromString($buildZipPackage([
            [
                'name' => 'word/document.xml',
                'localName' => 'word/other.xml',
                'data' => '<w:document/>',
                'method' => 0,
            ],
        ]));

        $t->throws(\RuntimeException::class, static fn (): string => $crcMismatch->read('word/document.xml'));
        $t->throws(\RuntimeException::class, static fn (): string => $localNameMismatch->read('word/document.xml'));
    },

    'rejects unsupported compression methods and malformed package endings' => static function (TestRunner $t) use ($buildZipPackage): void {
        $unsupported = ZipPackage::fromString($buildZipPackage([
            ['name' => 'word/document.xml', 'data' => '<w:document/>', 'method' => 12],
        ]));

        $t->same(['word/document.xml'], $unsupported->names());
        $t->throws(\RuntimeException::class, static fn (): string => $unsupported->read('word/document.xml'));
        $t->throws(\RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString('not a zip package'));
    },
];
