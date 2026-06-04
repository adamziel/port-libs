<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class LegacyDocReader
{
    private const SUMMARY_INFORMATION = "\x05SummaryInformation";
    private const DOCUMENT_SUMMARY_INFORMATION = "\x05DocumentSummaryInformation";

    /**
     * @return array{document:AstNode, metadata:array<string,mixed>, streams:list<string>, fib:array<string,mixed>}
     */
    public function readBytes(string $bytes): array
    {
        return $this->readCompoundFile(CompoundFileBinary::fromBytes($bytes));
    }

    /**
     * @return array{document:AstNode, metadata:array<string,mixed>, streams:list<string>, fib:array<string,mixed>}
     */
    public function readCompoundFile(CompoundFileBinary $compoundFile): array
    {
        if (!$compoundFile->hasStream('WordDocument')) {
            throw new \RuntimeException('Legacy DOC CFB package is missing the WordDocument stream');
        }

        $wordDocument = $compoundFile->readStream('WordDocument');
        $fib = $this->readFib($wordDocument);
        if ($fib['encrypted'] === true) {
            throw new \RuntimeException('Legacy DOC encrypted streams are not supported by the native reader');
        }

        $tableStreamName = (string) $fib['tableStream'];
        $tableStream = null;
        if ($compoundFile->hasStream($tableStreamName)) {
            $tableStream = $compoundFile->readStream($tableStreamName);
        } elseif ($compoundFile->hasStream('0Table')) {
            $tableStreamName = '0Table';
            $tableStream = $compoundFile->readStream('0Table');
        } elseif ($compoundFile->hasStream('1Table')) {
            $tableStreamName = '1Table';
            $tableStream = $compoundFile->readStream('1Table');
        }

        $textResult = $this->extractText($wordDocument, $tableStream);
        $metadata = $this->readMetadata($compoundFile);
        $attrs = [
            'sourceFormat' => 'doc',
            'cfbStreams' => $compoundFile->streamNames(),
            'textSource' => $textResult['source'],
            'tableStream' => $tableStreamName,
            'meta' => $metadata,
        ];

        return [
            'document' => new AstNode('document', $attrs, $this->paragraphNodes($textResult['text'])),
            'metadata' => $metadata,
            'streams' => $compoundFile->streamNames(),
            'fib' => $fib + ['textSource' => $textResult['source']],
        ];
    }

    public function readDocument(string $bytes): AstNode
    {
        return $this->readBytes($bytes)['document'];
    }

    /**
     * @return array<string,mixed>
     */
    private function readFib(string $wordDocument): array
    {
        if (strlen($wordDocument) < 32) {
            throw new \InvalidArgumentException('WordDocument stream is too short to contain a FIB');
        }
        $wIdent = self::u16($wordDocument, 0);
        if ($wIdent !== 0xa5ec) {
            throw new \InvalidArgumentException('WordDocument stream has an invalid FIB signature');
        }

        $flags = self::u16($wordDocument, 10);
        $fcMin = self::u32($wordDocument, 24);
        $fcMac = self::u32($wordDocument, 28);

        return [
            'wIdent' => $wIdent,
            'nFib' => self::u16($wordDocument, 2),
            'flags' => $flags,
            'fcMin' => $fcMin,
            'fcMac' => $fcMac,
            'tableStream' => ($flags & 0x0200) !== 0 ? '1Table' : '0Table',
            'complex' => ($flags & 0x0004) !== 0,
            'encrypted' => ($flags & 0x0100) !== 0,
            'extendedCharacters' => ($flags & 0x1000) !== 0,
        ];
    }

    /**
     * @return array{text:string,source:string}
     */
    private function extractText(string $wordDocument, ?string $tableStream): array
    {
        if ($tableStream !== null) {
            $pieceText = $this->extractPieceTableText($wordDocument, $tableStream);
            if ($pieceText !== null) {
                return [
                    'text' => $pieceText,
                    'source' => 'piece-table',
                ];
            }
        }

        $fib = $this->readFib($wordDocument);
        $fcMin = (int) $fib['fcMin'];
        $fcMac = (int) $fib['fcMac'];
        if ($fcMin > $fcMac || $fcMac > strlen($wordDocument)) {
            throw new \RuntimeException('WordDocument FIB text range points outside the stream');
        }

        $bytes = substr($wordDocument, $fcMin, $fcMac - $fcMin);
        if ($fib['extendedCharacters'] === true) {
            if (strlen($bytes) % 2 !== 0) {
                throw new \RuntimeException('Legacy DOC Unicode text range has an odd byte length');
            }

            return [
                'text' => $this->decodeUtf16Le($bytes),
                'source' => 'fib-text-range',
            ];
        }

        return [
            'text' => $this->looksLikeUtf16Le($bytes) ? $this->decodeUtf16Le($bytes) : $this->decodeWindows1252($bytes),
            'source' => 'fib-text-range',
        ];
    }

    private function extractPieceTableText(string $wordDocument, string $tableStream): ?string
    {
        foreach ([0x01a2, 0x010c] as $offset) {
            if (strlen($wordDocument) < $offset + 8) {
                continue;
            }

            $fcClx = self::u32($wordDocument, $offset);
            $lcbClx = self::u32($wordDocument, $offset + 4);
            if ($lcbClx === 0) {
                continue;
            }
            if ($fcClx > strlen($tableStream) || $fcClx + $lcbClx > strlen($tableStream)) {
                continue;
            }

            return $this->parseClx(substr($tableStream, $fcClx, $lcbClx), $wordDocument);
        }

        return null;
    }

    private function parseClx(string $clx, string $wordDocument): string
    {
        $cursor = 0;
        $length = strlen($clx);
        while ($cursor < $length) {
            $marker = ord($clx[$cursor]);
            $cursor++;
            if ($marker === 0x01) {
                if ($cursor + 2 > $length) {
                    throw new \RuntimeException('Legacy DOC Clx contains a truncated PChgTabs block');
                }
                $skip = self::u16($clx, $cursor);
                $cursor += 2 + $skip;
                continue;
            }

            if ($marker !== 0x02) {
                throw new \RuntimeException('Legacy DOC Clx does not contain a piece table');
            }
            if ($cursor + 4 > $length) {
                throw new \RuntimeException('Legacy DOC piece table length is truncated');
            }

            $pieceTableLength = self::u32($clx, $cursor);
            $cursor += 4;
            if ($cursor + $pieceTableLength > $length) {
                throw new \RuntimeException('Legacy DOC piece table points outside the Clx');
            }

            return $this->parsePlcPcd(substr($clx, $cursor, $pieceTableLength), $wordDocument);
        }

        throw new \RuntimeException('Legacy DOC Clx does not contain a piece table');
    }

    private function parsePlcPcd(string $plcPcd, string $wordDocument): string
    {
        $length = strlen($plcPcd);
        if ($length < 4 || ($length - 4) % 12 !== 0) {
            throw new \RuntimeException('Legacy DOC piece table has an invalid PlcPcd length');
        }

        $pieceCount = intdiv($length - 4, 12);
        $cpOffsets = [];
        for ($index = 0; $index <= $pieceCount; $index++) {
            $cpOffsets[] = self::u32($plcPcd, $index * 4);
        }

        $pcdOffset = ($pieceCount + 1) * 4;
        $text = '';
        for ($index = 0; $index < $pieceCount; $index++) {
            $characters = $cpOffsets[$index + 1] - $cpOffsets[$index];
            if ($characters <= 0) {
                continue;
            }

            $fcCompressed = self::u32($plcPcd, $pcdOffset + ($index * 8) + 2);
            $compressed = ($fcCompressed & 0x40000000) !== 0;
            $fc = $fcCompressed & 0x3fffffff;
            if ($compressed) {
                $start = intdiv($fc, 2);
                if ($start + $characters > strlen($wordDocument)) {
                    throw new \RuntimeException('Legacy DOC compressed text piece points outside WordDocument');
                }
                $text .= $this->decodeCompressedPiece(substr($wordDocument, $start, $characters));
                continue;
            }

            $byteLength = $characters * 2;
            if ($fc + $byteLength > strlen($wordDocument)) {
                throw new \RuntimeException('Legacy DOC Unicode text piece points outside WordDocument');
            }
            $text .= $this->decodeUtf16Le(substr($wordDocument, $fc, $byteLength));
        }

        return $text;
    }

    /**
     * @return list<AstNode>
     */
    private function paragraphNodes(string $text): array
    {
        $normalized = str_replace(["\r\n", "\n"], "\r", $text);
        $paragraphs = explode("\r", $normalized);
        $nodes = [];
        foreach ($paragraphs as $paragraph) {
            $paragraph = str_replace("\0", '', $paragraph);
            if (trim($paragraph) === '') {
                continue;
            }
            $nodes[] = new AstNode('paragraph', [], $this->inlineNodes($paragraph));
        }

        return $nodes;
    }

    /**
     * @return list<AstNode>
     */
    private function inlineNodes(string $text): array
    {
        $parts = preg_split('/(\x0b|\x0c)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts)) {
            $parts = [$text];
        }

        $nodes = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if ($part === "\v" || $part === "\f") {
                $nodes[] = new AstNode('linebreak');
                continue;
            }

            $clean = preg_replace('/[\x00-\x08\x0e-\x1f]/u', '', $part);
            if ($clean !== null && $clean !== '') {
                $nodes[] = new AstNode('text', ['text' => $clean]);
            }
        }

        return $nodes;
    }

    /**
     * @return array<string,mixed>
     */
    private function readMetadata(CompoundFileBinary $compoundFile): array
    {
        $metadata = [];
        if ($compoundFile->hasStream(self::SUMMARY_INFORMATION)) {
            $metadata = array_replace(
                $metadata,
                $this->propertySetMetadata($compoundFile->readStream(self::SUMMARY_INFORMATION), 'summary')
            );
        }
        if ($compoundFile->hasStream(self::DOCUMENT_SUMMARY_INFORMATION)) {
            $metadata = array_replace(
                $metadata,
                $this->propertySetMetadata($compoundFile->readStream(self::DOCUMENT_SUMMARY_INFORMATION), 'document-summary')
            );
        }

        return $metadata;
    }

    /**
     * @return array<string,mixed>
     */
    private function propertySetMetadata(string $bytes, string $kind): array
    {
        if (strlen($bytes) < 48 || self::u16($bytes, 0) !== 0xfffe) {
            return [];
        }

        $setCount = self::u32($bytes, 24);
        $metadata = [];
        for ($index = 0; $index < $setCount; $index++) {
            $descriptorOffset = 28 + ($index * 20);
            if ($descriptorOffset + 20 > strlen($bytes)) {
                break;
            }
            $propertySetOffset = self::u32($bytes, $descriptorOffset + 16);
            if ($propertySetOffset >= strlen($bytes)) {
                continue;
            }

            $properties = $this->readPropertySet($bytes, $propertySetOffset);
            $metadata = array_replace($metadata, $this->mapPropertySetValues($properties, $kind));
        }

        return $metadata;
    }

    /**
     * @return array<int,mixed>
     */
    private function readPropertySet(string $bytes, int $offset): array
    {
        if ($offset + 8 > strlen($bytes)) {
            return [];
        }

        $propertySetSize = self::u32($bytes, $offset);
        $propertyCount = self::u32($bytes, $offset + 4);
        if ($propertySetSize < 8 || $offset + $propertySetSize > strlen($bytes)) {
            return [];
        }

        $entriesOffset = $offset + 8;
        $values = [];
        $codepage = 1252;
        $locations = [];
        for ($index = 0; $index < $propertyCount; $index++) {
            $entryOffset = $entriesOffset + ($index * 8);
            if ($entryOffset + 8 > $offset + $propertySetSize) {
                break;
            }
            $propertyId = self::u32($bytes, $entryOffset);
            $valueOffset = self::u32($bytes, $entryOffset + 4);
            $locations[$propertyId] = $offset + $valueOffset;
        }

        if (isset($locations[1])) {
            $codepageValue = $this->readTypedPropertyValue($bytes, $locations[1], $codepage);
            if (is_int($codepageValue)) {
                $codepage = $codepageValue;
                $values[1] = $codepageValue;
            }
        }

        foreach ($locations as $propertyId => $valueOffset) {
            if ($propertyId === 1) {
                continue;
            }
            $values[$propertyId] = $this->readTypedPropertyValue($bytes, $valueOffset, $codepage);
        }

        return $values;
    }

    private function readTypedPropertyValue(string $bytes, int $offset, int $codepage): mixed
    {
        if ($offset + 4 > strlen($bytes)) {
            return null;
        }

        $type = self::u16($bytes, $offset);
        $valueOffset = $offset + 4;

        return match ($type) {
            0x0002 => self::signed16(self::u16($bytes, $valueOffset)),
            0x0003 => self::signed32(self::u32($bytes, $valueOffset)),
            0x000b => $this->readVariantBool($bytes, $valueOffset),
            0x001e => $this->readLpstr($bytes, $valueOffset, $codepage),
            0x001f => $this->readLpwstr($bytes, $valueOffset),
            0x0040 => $this->readFiletime($bytes, $valueOffset),
            default => null,
        };
    }

    /**
     * @param array<int,mixed> $properties
     * @return array<string,mixed>
     */
    private function mapPropertySetValues(array $properties, string $kind): array
    {
        $map = $kind === 'summary'
            ? [
                2 => 'title',
                3 => 'subject',
                4 => 'creator',
                5 => 'keywords',
                6 => 'description',
                7 => 'template',
                8 => 'lastModifiedBy',
                9 => 'revision',
                11 => 'lastPrinted',
                12 => 'created',
                13 => 'modified',
                14 => 'pageCount',
                15 => 'wordCount',
                16 => 'characterCount',
                18 => 'application',
                19 => 'documentSecurity',
            ]
            : [
                2 => 'category',
                3 => 'presentationFormat',
                4 => 'byteCount',
                5 => 'lineCount',
                6 => 'paragraphCount',
                7 => 'slideCount',
                8 => 'noteCount',
                9 => 'hiddenSlideCount',
                10 => 'multimediaClipCount',
                11 => 'scale',
                14 => 'manager',
                15 => 'company',
                16 => 'linksDirty',
                17 => 'charactersWithSpaces',
                19 => 'sharedDocument',
                22 => 'hyperlinksChanged',
                23 => 'applicationVersion',
                26 => 'contentType',
                27 => 'contentStatus',
                28 => 'language',
                29 => 'documentVersion',
            ];

        $metadata = [];
        foreach ($map as $propertyId => $name) {
            $value = $properties[$propertyId] ?? null;
            if ($value !== null && $value !== '') {
                $metadata[$name] = $value;
            }
        }
        if ($kind === 'summary' && isset($metadata['documentSecurity']) && is_int($metadata['documentSecurity'])) {
            $metadata['documentSecurityFlags'] = $this->documentSecurityFlags($metadata['documentSecurity']);
        }

        return $metadata;
    }

    /**
     * @return list<string>
     */
    private function documentSecurityFlags(int $flags): array
    {
        $map = [
            0x00000001 => 'passwordProtected',
            0x00000002 => 'readOnlyRecommended',
            0x00000004 => 'readOnlyEnforced',
            0x00000008 => 'lockedForAnnotations',
        ];

        $names = [];
        foreach ($map as $bit => $name) {
            if (($flags & $bit) !== 0) {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function looksLikeUtf16Le(string $bytes): bool
    {
        $length = strlen($bytes);
        if ($length < 4 || $length % 2 !== 0) {
            return false;
        }

        $zeroOddBytes = 0;
        for ($index = 1; $index < $length; $index += 2) {
            if ($bytes[$index] === "\0") {
                $zeroOddBytes++;
            }
        }

        return $zeroOddBytes >= intdiv($length, 4);
    }

    private function decodeCompressedPiece(string $bytes): string
    {
        $map = [
            0x82 => "\u{201a}",
            0x83 => "\u{0192}",
            0x84 => "\u{201e}",
            0x85 => "\u{2026}",
            0x86 => "\u{2020}",
            0x87 => "\u{2021}",
            0x88 => "\u{02c6}",
            0x89 => "\u{2030}",
            0x8a => "\u{0160}",
            0x8b => "\u{2039}",
            0x8c => "\u{0152}",
            0x91 => "\u{2018}",
            0x92 => "\u{2019}",
            0x93 => "\u{201c}",
            0x94 => "\u{201d}",
            0x95 => "\u{2022}",
            0x96 => "\u{2013}",
            0x97 => "\u{2014}",
            0x98 => "\u{02dc}",
            0x99 => "\u{2122}",
            0x9a => "\u{0161}",
            0x9b => "\u{203a}",
            0x9c => "\u{0153}",
            0x9f => "\u{0178}",
        ];

        $text = '';
        for ($index = 0, $length = strlen($bytes); $index < $length; $index++) {
            $byte = ord($bytes[$index]);
            $text .= $map[$byte] ?? self::codepointToUtf8($byte);
        }

        return $text;
    }

    private function decodeWindows1252(string $bytes): string
    {
        $decoded = iconv('Windows-1252', 'UTF-8//IGNORE', $bytes);

        return is_string($decoded) ? $decoded : $bytes;
    }

    private function decodeUtf16Le(string $bytes): string
    {
        $decoded = iconv('UTF-16LE', 'UTF-8//IGNORE', $bytes);

        return is_string($decoded) ? $decoded : '';
    }

    private function readLpstr(string $bytes, int $offset, int $codepage): ?string
    {
        if ($offset + 4 > strlen($bytes)) {
            return null;
        }
        $length = self::u32($bytes, $offset);
        if ($length === 0 || $offset + 4 + $length > strlen($bytes)) {
            return null;
        }

        $raw = rtrim(substr($bytes, $offset + 4, $length), "\0");
        if ($codepage === 65001) {
            return $raw;
        }
        if ($codepage === 1200) {
            return $this->decodeUtf16Le($raw);
        }

        return $this->decodeWindows1252($raw);
    }

    private function readLpwstr(string $bytes, int $offset): ?string
    {
        if ($offset + 4 > strlen($bytes)) {
            return null;
        }
        $characters = self::u32($bytes, $offset);
        $byteLength = $characters * 2;
        if ($characters === 0 || $offset + 4 + $byteLength > strlen($bytes)) {
            return null;
        }

        return rtrim($this->decodeUtf16Le(substr($bytes, $offset + 4, $byteLength)), "\0");
    }

    private function readVariantBool(string $bytes, int $offset): ?bool
    {
        if ($offset + 2 > strlen($bytes)) {
            return null;
        }

        return self::u16($bytes, $offset) !== 0;
    }

    private function readFiletime(string $bytes, int $offset): ?string
    {
        if ($offset + 8 > strlen($bytes)) {
            return null;
        }

        $ticks = self::u64($bytes, $offset);
        if ($ticks === 0) {
            return null;
        }

        $seconds = intdiv($ticks, 10000000) - 11644473600;
        if ($seconds < 0) {
            return null;
        }

        return gmdate('Y-m-d\TH:i:s\Z', $seconds);
    }

    private static function codepointToUtf8(int $codepoint): string
    {
        return html_entity_decode('&#x' . dechex($codepoint) . ';', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private static function signed16(int $value): int
    {
        return $value >= 0x8000 ? $value - 0x10000 : $value;
    }

    private static function signed32(int $value): int
    {
        return $value >= 0x80000000 ? $value - 0x100000000 : $value;
    }

    private static function u16(string $bytes, int $offset): int
    {
        if ($offset < 0 || $offset + 2 > strlen($bytes)) {
            throw new \RuntimeException('Unexpected end of legacy DOC data');
        }
        $values = unpack('vvalue', substr($bytes, $offset, 2));

        return (int) $values['value'];
    }

    private static function u32(string $bytes, int $offset): int
    {
        if ($offset < 0 || $offset + 4 > strlen($bytes)) {
            throw new \RuntimeException('Unexpected end of legacy DOC data');
        }
        $values = unpack('Vvalue', substr($bytes, $offset, 4));

        return (int) $values['value'];
    }

    private static function u64(string $bytes, int $offset): int
    {
        $low = self::u32($bytes, $offset);
        $high = self::u32($bytes, $offset + 4);
        if ($high > intdiv(PHP_INT_MAX - $low, 4294967296)) {
            throw new \RuntimeException('Legacy DOC FILETIME exceeds PHP integer range');
        }

        return ($high * 4294967296) + $low;
    }
}
