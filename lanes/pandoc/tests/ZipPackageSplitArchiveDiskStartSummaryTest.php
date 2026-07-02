<?php

declare(strict_types=1);

use PortLibs\Pandoc\ZipPackage;

$rewriteCentralDirectoryDiskStarts = static function (
    string $zip,
    array $diskStartsByName,
    array $eocdFields = []
): string {
    $eocdOffset = strrpos($zip, "PK\x05\x06");
    if ($eocdOffset === false) {
        throw new RuntimeException('EOCD fixture not found');
    }

    $totalEntryCount = unpack('vvalue', substr($zip, $eocdOffset + 10, 2))['value'];
    $centralDirectoryOffset = unpack('Vvalue', substr($zip, $eocdOffset + 16, 4))['value'];
    $cursor = $centralDirectoryOffset;
    for ($index = 0; $index < $totalEntryCount; $index++) {
        if (substr($zip, $cursor, 4) !== "PK\x01\x02") {
            throw new RuntimeException("Central directory fixture entry {$index} not found");
        }

        $nameLength = unpack('vvalue', substr($zip, $cursor + 28, 2))['value'];
        $extraLength = unpack('vvalue', substr($zip, $cursor + 30, 2))['value'];
        $commentLength = unpack('vvalue', substr($zip, $cursor + 32, 2))['value'];
        $name = substr($zip, $cursor + 46, $nameLength);
        if (array_key_exists($name, $diskStartsByName)) {
            $diskStart = $diskStartsByName[$name];
            if (!is_int($diskStart) || $diskStart < 0 || $diskStart > 0xffff) {
                throw new RuntimeException("Invalid disk-start fixture value for {$name}");
            }
            $zip = substr_replace($zip, pack('v', $diskStart), $cursor + 34, 2);
        }

        $cursor += 46 + $nameLength + $extraLength + $commentLength;
    }

    $rewriteUInt16 = static function (string $zip, int $offset, int $value): string {
        if ($value < 0 || $value > 0xffff) {
            throw new RuntimeException('EOCD fixture uint16 value out of range');
        }

        return substr_replace($zip, pack('v', $value), $offset, 2);
    };

    if (array_key_exists('diskNumber', $eocdFields)) {
        $zip = $rewriteUInt16($zip, $eocdOffset + 4, $eocdFields['diskNumber']);
    }
    if (array_key_exists('centralDirectoryDisk', $eocdFields)) {
        $zip = $rewriteUInt16($zip, $eocdOffset + 6, $eocdFields['centralDirectoryDisk']);
    }
    if (array_key_exists('diskEntryCount', $eocdFields)) {
        $zip = $rewriteUInt16($zip, $eocdOffset + 8, $eocdFields['diskEntryCount']);
    }
    if (array_key_exists('totalEntryCount', $eocdFields)) {
        $zip = $rewriteUInt16($zip, $eocdOffset + 10, $eocdFields['totalEntryCount']);
    }

    return $zip;
};

return [
    'summarizes raw split archive disk-start buckets before package import' => static function (TestRunner $t) use ($rewriteCentralDirectoryDiskStarts): void {
        $zip = ZipPackage::build([
            ['name' => 'word/document.xml', 'data' => '<w:document>disk start root</w:document>', 'compressionMethod' => 8],
            ['name' => 'word/media/a.png', 'data' => 'A-PNG', 'compressionMethod' => 0],
            ['name' => 'word/media/b.png', 'data' => 'B-PNG', 'compressionMethod' => 0],
            ['name' => 'customXml/item1.xml', 'data' => '<item>split</item>', 'compressionMethod' => 8],
        ]);
        $zip = $rewriteCentralDirectoryDiskStarts(
            $zip,
            [
                'word/media/a.png' => 2,
                'word/media/b.png' => 2,
                'customXml/item1.xml' => 7,
            ],
            [
                'diskNumber' => 2,
                'centralDirectoryDisk' => 2,
                'diskEntryCount' => 2,
            ]
        );

        $summary = ZipPackage::splitArchivePreflight($zip);
        $summariesByDisk = [];
        foreach ($summary['diskStartSummaries'] as $diskSummary) {
            $summariesByDisk[$diskSummary['diskStart']] = $diskSummary;
        }
        $splitSummariesByDisk = [];
        foreach ($summary['splitArchiveDiskStartSummaries'] as $diskSummary) {
            $splitSummariesByDisk[$diskSummary['diskStart']] = $diskSummary;
        }

        $t->same(4, $summary['entryCount']);
        $t->same(false, $summary['isSingleDisk']);
        $t->same(true, $summary['hasSplitArchiveMarkers']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same(['split-archive-eocd', 'split-entry-disk-start'], $summary['issues']);
        $t->same(3, $summary['splitArchiveEntryCount']);
        $t->same(3, $summary['diskStartSummaryCount']);
        $t->same(2, $summary['splitArchiveDiskStartSummaryCount']);
        $t->same([0, 2, 7], $summary['diskStartValues']);
        $t->same([2, 7], $summary['splitArchiveDiskStartValues']);
        $t->same([0 => 1, 2 => 2, 7 => 1], $summary['diskStartEntryCounts']);
        $t->same([2 => 2, 7 => 1], $summary['splitArchiveDiskStartEntryCounts']);
        $t->same(['word/document.xml'], $summariesByDisk[0]['entryNames']);
        $t->same(['word/media/a.png', 'word/media/b.png'], $summariesByDisk[2]['entryNames']);
        $t->same(['customXml/item1.xml'], $summariesByDisk[7]['entryNames']);
        $t->same([1, 2], $summariesByDisk[2]['centralDirectoryIndexes']);
        $t->same(2, $summariesByDisk[2]['entryCount']);
        $t->same(2, $summariesByDisk[2]['splitArchiveEntryCount']);
        $t->same($summariesByDisk[2], $splitSummariesByDisk[2]);
        $t->same($summariesByDisk[7], $splitSummariesByDisk[7]);
        $t->same([0, 2, 2, 7], array_column($summary['entries'], 'diskStart'));
        $t->same(['word/media/a.png', 'word/media/b.png', 'customXml/item1.xml'], array_column($summary['splitArchiveEntries'], 'name'));

        $rawStrict = ZipPackage::rawStrictImportPreflight($zip);
        $t->same(false, $rawStrict['canInstantiate']);
        $t->contains('split-archive-eocd', implode(',', $rawStrict['diagnostics']));
        $t->contains('split-entry-disk-start', implode(',', $rawStrict['diagnostics']));
        $t->same($summary['diskStartEntryCounts'], $rawStrict['splitArchive']['diskStartEntryCounts']);
        $t->same($summary['splitArchiveDiskStartSummaries'], $rawStrict['splitArchive']['splitArchiveDiskStartSummaries']);
    },
];
