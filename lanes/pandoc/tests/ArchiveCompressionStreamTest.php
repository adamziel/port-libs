<?php

declare(strict_types=1);

use PortLibs\Pandoc\GzipStream;
use PortLibs\Pandoc\TarArchive;
use PortLibs\Pandoc\TarArchiveEntry;

$rawTarHeader = static function (string $name, string $typeFlag, string $data = '', int $modifiedAt = 0): string {
    $octal = static function (int $value, int $length): string {
        return str_pad(decoct($value), $length - 1, '0', STR_PAD_LEFT) . "\0";
    };
    $field = static fn (string $value, int $length): string => str_pad($value, $length, "\0");

    $header = $field($name, 100)
        . $octal(0644, 8)
        . $octal(0, 8)
        . $octal(0, 8)
        . $octal(strlen($data), 12)
        . $octal($modifiedAt, 12)
        . str_repeat(' ', 8)
        . $typeFlag
        . $field('', 100)
        . "ustar\0"
        . '00'
        . $field('', 32)
        . $field('', 32)
        . $octal(0, 8)
        . $octal(0, 8)
        . $field('', 155)
        . str_repeat("\0", 12);

    $checksum = 0;
    for ($index = 0; $index < strlen($header); $index++) {
        $checksum += ord($header[$index]);
    }

    $header = substr_replace($header, sprintf('%06o', $checksum) . "\0 ", 148, 8);
    $padding = strlen($data) % 512 === 0 ? '' : str_repeat("\0", 512 - (strlen($data) % 512));

    return $header . $data . $padding . str_repeat("\0", 1024);
};

return [
    'builds and reads bounded tar package fixture entries' => static function (TestRunner $t): void {
        $archive = TarArchive::fromEntries([
            [
                'name' => 'packet/',
                'type' => TarArchiveEntry::TYPE_DIRECTORY,
                'modifiedAt' => 1780479016,
                'mode' => 0755,
            ],
            [
                'name' => 'packet/manifest.json',
                'data' => '{"source":"wordpress-import","format":"docx"}',
                'modifiedAt' => 1780479017,
                'mode' => 0640,
                'userName' => 'wp',
                'groupName' => 'import',
            ],
            [
                'name' => 'packet/word/document.xml',
                'data' => '<w:document><w:p>Tar-backed import source</w:p></w:document>',
                'modifiedAt' => 1780479018,
            ],
        ]);

        $roundTrip = TarArchive::fromString($archive->bytes());
        $manifest = $roundTrip->entry('packet/manifest.json');

        $t->same([
            'packet/',
            'packet/manifest.json',
            'packet/word/document.xml',
        ], $roundTrip->names());
        $t->true($roundTrip->entry('packet/')->isDirectory());
        $t->same('', $roundTrip->read('packet/'));
        $t->true($manifest->isRegularFile());
        $t->same(0640, $manifest->mode);
        $t->same(1780479017, $manifest->modifiedAt);
        $t->same('wp', $manifest->userName);
        $t->same('import', $manifest->groupName);
        $t->same('{"source":"wordpress-import","format":"docx"}', $roundTrip->read('/packet/manifest.json'));
        $t->same('<w:document><w:p>Tar-backed import source</w:p></w:document>', $roundTrip->read('packet/word/document.xml'));
        $t->same($archive->bytes(), $roundTrip->bytes());
    },

    'preserves ustar prefix and pax long path metadata' => static function (TestRunner $t): void {
        $ustarName = 'packet/' . str_repeat('nested/', 12) . 'document.xml';
        $paxName = 'packet/' . str_repeat('review-', 18) . 'document.xml';
        $archive = TarArchive::fromEntries([
            [
                'name' => $ustarName,
                'data' => '<w:document><w:p>ustar prefix path</w:p></w:document>',
                'modifiedAt' => 1780479019,
            ],
            [
                'name' => $paxName,
                'data' => '<w:document><w:p>pax path metadata</w:p></w:document>',
                'modifiedAt' => 1780479020,
            ],
        ]);

        $roundTrip = TarArchive::fromString($archive->bytes());
        $paxEntry = $roundTrip->entry($paxName);

        $t->true(strlen($ustarName) > 100);
        $t->true(strlen($paxName) > 100);
        $t->same($ustarName, $roundTrip->entry($ustarName)->name);
        $t->same($paxName, $paxEntry->name);
        $t->same($paxName, $paxEntry->paxHeaders['path'] ?? null);
        $t->same(1780479020, $paxEntry->modifiedAt);
        $t->same('<w:document><w:p>ustar prefix path</w:p></w:document>', $roundTrip->read($ustarName));
        $t->same('<w:document><w:p>pax path metadata</w:p></w:document>', $roundTrip->read('/' . $paxName));
    },

    'rejects unsafe or unsupported tar archive entries' => static function (TestRunner $t) use ($rawTarHeader): void {
        $valid = TarArchive::build([
            ['name' => 'packet/document.xml', 'data' => '<w:document/>'],
        ]);
        $badChecksum = substr_replace($valid, '000000', 148, 6);
        $linkArchive = $rawTarHeader('packet/link', '2', 'packet/document.xml');
        $deviceArchive = $rawTarHeader('packet/device', '3');
        $directoryWithPayload = $rawTarHeader('packet/dir/', '5', 'payload');

        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromEntries([
            ['name' => '../packet/document.xml', 'data' => 'unsafe'],
        ]));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString(substr($valid, 0, -1)));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($badChecksum));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($linkArchive));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($deviceArchive));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($directoryWithPayload));
        $t->throws(\RuntimeException::class, static fn (): TarArchive => TarArchive::fromString($valid, 1));
    },

    'reads gzip wrapped tar streams for package handoff fixtures' => static function (TestRunner $t): void {
        $archive = TarArchive::fromEntries([
            [
                'name' => 'packet/manifest.json',
                'data' => '{"source":"gzip-tar","target":"wordpress"}',
                'modifiedAt' => 1780479021,
            ],
            [
                'name' => 'packet/content.md',
                'data' => "# Imported archive\n\nReady for block review.\n",
                'modifiedAt' => 1780479022,
            ],
        ]);
        $gzip = GzipStream::build($archive->bytes(), [
            'filename' => 'wordpress-import-packet.tar',
            'comment' => 'tar package fixture',
            'modifiedAt' => 1780479023,
            'headerCrc' => true,
        ]);
        $members = GzipStream::members($gzip);
        $roundTrip = TarArchive::fromString(GzipStream::decode($gzip));

        $t->same(1, count($members));
        $t->same('wordpress-import-packet.tar', $members[0]['filename']);
        $t->same('tar package fixture', $members[0]['comment']);
        $t->same(1780479023, $members[0]['modifiedAt']);
        $t->same(['packet/manifest.json', 'packet/content.md'], $roundTrip->names());
        $t->same('{"source":"gzip-tar","target":"wordpress"}', $roundTrip->read('packet/manifest.json'));
        $t->same("# Imported archive\n\nReady for block review.\n", $roundTrip->read('/packet/content.md'));
    },
];
