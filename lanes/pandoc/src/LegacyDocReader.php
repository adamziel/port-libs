<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class LegacyDocReader
{
    private const SUMMARY_INFORMATION = "\x05SummaryInformation";
    private const DOCUMENT_SUMMARY_INFORMATION = "\x05DocumentSummaryInformation";
    private const FMTID_USER_DEFINED_PROPERTIES = '05d5cdd59c2e1b10939708002b2cf9ae';

    /**
     * @return array{document:AstNode, metadata:array<string,mixed>, streams:list<string>, fib:array<string,mixed>, embeddedObjects:list<array<string,mixed>>}
     */
    public function readBytes(string $bytes): array
    {
        return $this->readCompoundFile(CompoundFileBinary::fromBytes($bytes));
    }

    /**
     * @return array{document:AstNode, metadata:array<string,mixed>, streams:list<string>, fib:array<string,mixed>, embeddedObjects:list<array<string,mixed>>}
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
        $embeddedObjects = $this->embeddedObjectReport($compoundFile);
        if ($embeddedObjects !== []) {
            $metadata['embeddedObjectCount'] = count($embeddedObjects);
        }

        $attrs = [
            'sourceFormat' => 'doc',
            'cfbStreams' => $compoundFile->streamNames(),
            'textSource' => $textResult['source'],
            'tableStream' => $tableStreamName,
            'meta' => $metadata,
            'embeddedObjects' => $embeddedObjects,
        ];

        return [
            'document' => new AstNode('document', $attrs, $this->paragraphNodes($textResult['text'])),
            'metadata' => $metadata,
            'streams' => $compoundFile->streamNames(),
            'fib' => $fib + ['textSource' => $textResult['source']],
            'embeddedObjects' => $embeddedObjects,
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

            $pcdFlags = self::u16($plcPcd, $pcdOffset + ($index * 8));
            if (($pcdFlags & 0x0004) !== 0) {
                throw new \RuntimeException('Legacy DOC piece table contains a dirty Pcd flag');
            }

            $fcCompressed = self::u32($plcPcd, $pcdOffset + ($index * 8) + 2);
            $compressed = ($fcCompressed & 0x40000000) !== 0;
            $fc = $fcCompressed & 0x3fffffff;
            if ($compressed) {
                $start = intdiv($fc, 2);
                if ($start + $characters > strlen($wordDocument)) {
                    throw new \RuntimeException('Legacy DOC compressed text piece points outside WordDocument');
                }
                $pieceText = $this->decodeCompressedPiece(substr($wordDocument, $start, $characters));
                $this->assertNoParagraphLastPieceIsValid($pcdFlags, $pieceText);
                $text .= $pieceText;
                continue;
            }

            $byteLength = $characters * 2;
            if ($fc + $byteLength > strlen($wordDocument)) {
                throw new \RuntimeException('Legacy DOC Unicode text piece points outside WordDocument');
            }
            $pieceText = $this->decodeUtf16Le(substr($wordDocument, $fc, $byteLength));
            $this->assertNoParagraphLastPieceIsValid($pcdFlags, $pieceText);
            $text .= $pieceText;
        }

        return $text;
    }

    private function assertNoParagraphLastPieceIsValid(int $pcdFlags, string $pieceText): void
    {
        if (($pcdFlags & 0x0001) !== 0 && str_contains($pieceText, "\r")) {
            throw new \RuntimeException('Legacy DOC piece table marks a piece as paragraph-free but contains a paragraph mark');
        }
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
        if (strpbrk($text, "\x13\x14\x15") !== false) {
            return $this->fieldAwareInlineNodes($text);
        }

        return $this->plainInlineNodes($text);
    }

    /**
     * @return list<AstNode>
     */
    private function fieldAwareInlineNodes(string $text): array
    {
        $parts = preg_split('/([\x13\x14\x15])/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts)) {
            return $this->plainInlineNodes($text);
        }

        $nodes = [];
        $field = null;
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if ($part === "\x13") {
                if ($field !== null) {
                    throw new \RuntimeException('Legacy DOC nested field codes are not supported by the native reader');
                }
                $field = [
                    'instruction' => '',
                    'result' => '',
                    'collectingResult' => false,
                ];
                continue;
            }

            if ($part === "\x14") {
                if ($field === null) {
                    throw new \RuntimeException('Legacy DOC field separator appears outside a field');
                }
                if ($field['collectingResult'] === true) {
                    throw new \RuntimeException('Legacy DOC field contains duplicate separators');
                }
                $field['collectingResult'] = true;
                continue;
            }

            if ($part === "\x15") {
                if ($field === null) {
                    throw new \RuntimeException('Legacy DOC field end appears outside a field');
                }
                array_push($nodes, ...$this->fieldResultNodes($field));
                $field = null;
                continue;
            }

            if ($field === null) {
                array_push($nodes, ...$this->plainInlineNodes($part));
            } elseif ($field['collectingResult'] === true) {
                $field['result'] .= $part;
            } else {
                $field['instruction'] .= $part;
            }
        }

        if ($field !== null) {
            throw new \RuntimeException('Legacy DOC field code is not terminated');
        }

        return $nodes;
    }

    /**
     * @param array{instruction:string,result:string,collectingResult:bool} $field
     * @return list<AstNode>
     */
    private function fieldResultNodes(array $field): array
    {
        $resultNodes = $this->plainInlineNodes($field['result']);
        if ($resultNodes === []) {
            return [];
        }

        $attrs = $this->hyperlinkFieldAttrs($field['instruction']);
        if ($attrs !== null) {
            return [new AstNode('link', $attrs, $resultNodes)];
        }

        $attrs = $this->fieldSpanAttrs($field['instruction']);
        if ($attrs !== null) {
            return [new AstNode('span', $attrs, $resultNodes)];
        }

        return $resultNodes;
    }

    /**
     * @return array{url:string,title?:string}|null
     */
    private function hyperlinkFieldAttrs(string $instruction): ?array
    {
        $tokens = $this->fieldInstructionTokens($instruction);
        if ($tokens === [] || strtoupper(array_shift($tokens)) !== 'HYPERLINK') {
            return null;
        }

        $url = null;
        $anchor = null;
        $title = null;
        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if ($token === '') {
                continue;
            }

            if (str_starts_with($token, '\\')) {
                $switch = strtolower(substr($token, 1));
                if (($switch === 'l' || $switch === 'o') && isset($tokens[$index + 1])) {
                    $index++;
                    if ($switch === 'l') {
                        $anchor = $tokens[$index];
                    } else {
                        $title = $tokens[$index];
                    }
                }
                continue;
            }

            $url ??= $token;
        }

        if ($url === null || $url === '') {
            $url = $anchor === null || $anchor === '' ? '' : '#' . $anchor;
        } elseif ($anchor !== null && $anchor !== '') {
            $url .= '#' . $anchor;
        }
        if ($url === '') {
            return null;
        }

        $attrs = ['url' => $url];
        if ($title !== null && $title !== '') {
            $attrs['title'] = $title;
        }

        return $attrs;
    }

    /**
     * @return array{classes:list<string>,attributes:array<string,string>}|null
     */
    private function fieldSpanAttrs(string $instruction): ?array
    {
        $tokens = $this->fieldInstructionTokens($instruction);
        if ($tokens === []) {
            return null;
        }

        $fieldNames = [
            'PAGE' => 'page',
            'NUMPAGES' => 'numpages',
            'SECTIONPAGES' => 'sectionpages',
            'DATE' => 'date',
            'TIME' => 'time',
            'CREATEDATE' => 'createdate',
            'SAVEDATE' => 'savedate',
            'PRINTDATE' => 'printdate',
        ];

        $fieldName = strtoupper(array_shift($tokens));
        if (!isset($fieldNames[$fieldName])) {
            return null;
        }

        $fieldKey = $fieldNames[$fieldName];
        $attributes = [
            'data-legacy-doc-field' => $fieldKey,
            'data-legacy-doc-field-instruction' => $this->normalizeFieldInstruction($instruction),
        ];

        $format = $this->fieldFormatSwitchValue($tokens);
        if ($format !== null && $format !== '') {
            $attributes['data-legacy-doc-field-format'] = $format;
        }

        return [
            'classes' => ['legacy-doc-field', 'legacy-doc-field-' . $fieldKey],
            'attributes' => $attributes,
        ];
    }

    /**
     * @param list<string> $tokens
     */
    private function fieldFormatSwitchValue(array $tokens): ?string
    {
        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            if (!str_starts_with($token, '\\')) {
                continue;
            }

            $switch = strtolower(substr($token, 1));
            if (($switch === '*' || $switch === '@') && isset($tokens[$index + 1]) && !str_starts_with($tokens[$index + 1], '\\')) {
                return $tokens[$index + 1];
            }
        }

        return null;
    }

    private function normalizeFieldInstruction(string $instruction): string
    {
        return preg_replace('/\s+/u', ' ', trim($instruction)) ?? trim($instruction);
    }

    /**
     * @return list<string>
     */
    private function fieldInstructionTokens(string $instruction): array
    {
        $tokens = [];
        $length = strlen($instruction);
        for ($index = 0; $index < $length;) {
            while ($index < $length && ctype_space($instruction[$index])) {
                $index++;
            }
            if ($index >= $length) {
                break;
            }

            if ($instruction[$index] === '"') {
                $index++;
                $token = '';
                while ($index < $length) {
                    $char = $instruction[$index];
                    if ($char === '"' && ($index === 0 || $instruction[$index - 1] !== '\\')) {
                        $index++;
                        break;
                    }
                    if ($char === '\\' && isset($instruction[$index + 1]) && $instruction[$index + 1] === '"') {
                        $token .= '"';
                        $index += 2;
                        continue;
                    }

                    $token .= $char;
                    $index++;
                }
                $tokens[] = $token;
                continue;
            }

            $start = $index;
            while ($index < $length && !ctype_space($instruction[$index])) {
                $index++;
            }
            $tokens[] = substr($instruction, $start, $index - $start);
        }

        return $tokens;
    }

    /**
     * @return list<AstNode>
     */
    private function plainInlineNodes(string $text): array
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
     * @return list<array<string,mixed>>
     */
    private function embeddedObjectReport(CompoundFileBinary $compoundFile): array
    {
        $objects = [];
        foreach ($compoundFile->entries() as $entry) {
            if ($entry['type'] !== 2) {
                continue;
            }

            $path = (string) $entry['path'];
            $segments = explode('/', $path);
            if (count($segments) < 3 || strtolower($segments[0]) !== 'objectpool') {
                continue;
            }

            $objectId = $segments[1];
            if ($objectId === '') {
                continue;
            }

            $storagePath = $segments[0] . '/' . $objectId;
            $streamName = implode('/', array_slice($segments, 2));
            $role = $this->embeddedObjectStreamRole($streamName);
            $stream = [
                'path' => $path,
                'name' => $streamName,
                'role' => $role,
                'bytes' => (int) $entry['size'],
                'canExposeBytes' => false,
            ];

            $objects[$storagePath] ??= [
                'storagePath' => $storagePath,
                'objectId' => $objectId,
                'streamCount' => 0,
                'totalBytes' => 0,
                'hasNativeData' => false,
                'hasPresentationData' => false,
                'canExposeBytes' => false,
                'streams' => [],
            ];
            $objects[$storagePath]['streamCount']++;
            $objects[$storagePath]['totalBytes'] += (int) $entry['size'];
            $objects[$storagePath]['hasNativeData'] = $objects[$storagePath]['hasNativeData'] || $role === 'native-data';
            $objects[$storagePath]['hasPresentationData'] = $objects[$storagePath]['hasPresentationData'] || $role === 'presentation-data';
            $objects[$storagePath]['streams'][] = $stream;

            if ($role === 'object-info') {
                $format = $this->embeddedObjectInfoFormat($compoundFile->readStream($path));
                if ($format !== null) {
                    $objects[$storagePath]['transmissionFormat'] = $format;
                }
            }
        }

        $result = array_values($objects);
        foreach ($result as &$object) {
            usort(
                $object['streams'],
                static fn (array $left, array $right): int => strcmp((string) $left['path'], (string) $right['path'])
            );
        }
        unset($object);
        usort(
            $result,
            static fn (array $left, array $right): int => strcmp((string) $left['storagePath'], (string) $right['storagePath'])
        );

        return $result;
    }

    private function embeddedObjectStreamRole(string $streamName): string
    {
        return match (true) {
            $streamName === "\x01Ole10Native" => 'native-data',
            $streamName === "\x01CompObj" => 'compound-object',
            $streamName === "\x03ObjInfo" => 'object-info',
            $streamName === "\x03EPRINT" => 'print-presentation',
            str_starts_with($streamName, "\x02OlePres") => 'presentation-data',
            default => 'private-data',
        };
    }

    /**
     * @return array{code:int,name:string}|null
     */
    private function embeddedObjectInfoFormat(string $bytes): ?array
    {
        if (strlen($bytes) < 4) {
            return null;
        }

        $code = self::u16($bytes, 2);

        return [
            'code' => $code,
            'name' => match ($code) {
                0x0001 => 'rtf',
                0x0002 => 'text',
                0x0003 => 'metafile',
                0x0004 => 'bitmap',
                0x0005 => 'dib',
                0x000a => 'html',
                0x0014 => 'unicode-text',
                default => 'unknown',
            },
        ];
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
            $fmtid = bin2hex(substr($bytes, $descriptorOffset, 16));
            $propertySetOffset = self::u32($bytes, $descriptorOffset + 16);
            if ($propertySetOffset >= strlen($bytes)) {
                continue;
            }

            $propertySet = $this->readPropertySet($bytes, $propertySetOffset);
            if ($kind === 'document-summary' && $fmtid === self::FMTID_USER_DEFINED_PROPERTIES) {
                $customProperties = $this->customDocumentProperties($propertySet['properties'], $propertySet['dictionary']);
                if ($customProperties !== []) {
                    $metadata['customProperties'] = array_replace(
                        $metadata['customProperties'] ?? [],
                        $customProperties
                    );
                }
                continue;
            }

            $metadata = array_replace($metadata, $this->mapPropertySetValues($propertySet['properties'], $kind));
        }

        return $metadata;
    }

    /**
     * @return array{properties:array<int,mixed>,dictionary:array<int,string>}
     */
    private function readPropertySet(string $bytes, int $offset): array
    {
        if ($offset + 8 > strlen($bytes)) {
            return [
                'properties' => [],
                'dictionary' => [],
            ];
        }

        $propertySetSize = self::u32($bytes, $offset);
        $propertyCount = self::u32($bytes, $offset + 4);
        if ($propertySetSize < 8 || $offset + $propertySetSize > strlen($bytes)) {
            return [
                'properties' => [],
                'dictionary' => [],
            ];
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
                $codepage = $codepageValue < 0 ? $codepageValue + 0x10000 : $codepageValue;
                $values[1] = $codepage;
            }
        }

        $dictionary = [];
        if (isset($locations[0])) {
            $dictionary = $this->readDictionary($bytes, $locations[0], $codepage, $offset + $propertySetSize);
        }

        foreach ($locations as $propertyId => $valueOffset) {
            if ($propertyId === 0 || $propertyId === 1) {
                continue;
            }
            $values[$propertyId] = $this->readTypedPropertyValue($bytes, $valueOffset, $codepage);
        }

        return [
            'properties' => $values,
            'dictionary' => $dictionary,
        ];
    }

    private function readTypedPropertyValue(string $bytes, int $offset, int $codepage): mixed
    {
        $typedValue = $this->readTypedPropertyValueWithSize($bytes, $offset, $codepage);

        return $typedValue['value'] ?? null;
    }

    /**
     * @return array{value:mixed,bytes:int}|null
     */
    private function readTypedPropertyValueWithSize(string $bytes, int $offset, int $codepage): ?array
    {
        if ($offset + 4 > strlen($bytes)) {
            return null;
        }

        $type = self::u16($bytes, $offset);
        $valueOffset = $offset + 4;

        return match ($type) {
            0x0002 => $valueOffset + 2 <= strlen($bytes)
                ? ['value' => self::signed16(self::u16($bytes, $valueOffset)), 'bytes' => 8]
                : null,
            0x0003 => $valueOffset + 4 <= strlen($bytes)
                ? ['value' => self::signed32(self::u32($bytes, $valueOffset)), 'bytes' => 8]
                : null,
            0x000b => $valueOffset + 2 <= strlen($bytes)
                ? ['value' => $this->readVariantBool($bytes, $valueOffset), 'bytes' => 8]
                : null,
            0x001e => $this->typedSizedValue($this->readLpstrWithSize($bytes, $valueOffset, $codepage)),
            0x001f => $this->typedSizedValue($this->readLpwstrWithSize($bytes, $valueOffset)),
            0x0040 => $valueOffset + 8 <= strlen($bytes)
                ? ['value' => $this->readFiletime($bytes, $valueOffset), 'bytes' => 12]
                : null,
            0x100c => $this->typedSizedValue($this->readVariantVectorWithSize($bytes, $valueOffset, $codepage)),
            0x101e => $this->typedSizedValue($this->readLpstrVectorWithSize($bytes, $valueOffset, $codepage)),
            0x101f => $this->typedSizedValue($this->readLpwstrVectorWithSize($bytes, $valueOffset)),
            default => null,
        };
    }

    /**
     * @param array{value:mixed,bytes:int}|null $sizedValue
     * @return array{value:mixed,bytes:int}|null
     */
    private function typedSizedValue(?array $sizedValue): ?array
    {
        if ($sizedValue === null) {
            return null;
        }

        return [
            'value' => $sizedValue['value'],
            'bytes' => 4 + $sizedValue['bytes'],
        ];
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
        if ($kind === 'document-summary') {
            $documentParts = $this->stringList($properties[13] ?? null);
            if ($documentParts !== []) {
                $metadata['documentParts'] = $documentParts;
            }

            $headingPairs = $this->documentHeadingPairs($properties[12] ?? null, $documentParts);
            if ($headingPairs !== []) {
                $metadata['headingPairs'] = $headingPairs;
            }
        }

        return $metadata;
    }

    /**
     * @param array<int,mixed> $properties
     * @param array<int,string> $dictionary
     * @return array<string,mixed>
     */
    private function customDocumentProperties(array $properties, array $dictionary): array
    {
        $customProperties = [];
        foreach ($dictionary as $propertyId => $name) {
            $value = $properties[$propertyId] ?? null;
            if ($name === '' || $value === null || $value === '') {
                continue;
            }

            $customProperties[$name] = $value;
        }

        return $customProperties;
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

    private function decodeCodePageString(string $bytes, int $codepage): string
    {
        if ($codepage === 1200) {
            while (strlen($bytes) >= 2 && substr($bytes, -2) === "\0\0") {
                $bytes = substr($bytes, 0, -2);
            }

            return $this->decodeUtf16Le($bytes);
        }

        $bytes = rtrim($bytes, "\0");
        if ($codepage === 65001) {
            if (preg_match('//u', $bytes) === 1) {
                return $bytes;
            }
            $decoded = @iconv('UTF-8', 'UTF-8//IGNORE', $bytes);

            return is_string($decoded) ? $decoded : '';
        }
        if ($codepage === 1251) {
            return $this->decodeWindows1251($bytes);
        }

        $encoding = $this->oleCodepageEncoding($codepage);
        if ($encoding !== null) {
            $decoded = @iconv($encoding, 'UTF-8//IGNORE', $bytes);
            if (is_string($decoded)) {
                return $decoded;
            }
        }

        return $this->decodeWindows1252($bytes);
    }

    private function oleCodepageEncoding(int $codepage): ?string
    {
        return match ($codepage) {
            874 => 'CP874',
            932 => 'CP932',
            936 => 'CP936',
            949 => 'CP949',
            950 => 'CP950',
            1250 => 'Windows-1250',
            1252 => 'Windows-1252',
            1253 => 'Windows-1253',
            1254 => 'Windows-1254',
            1255 => 'Windows-1255',
            1256 => 'Windows-1256',
            1257 => 'Windows-1257',
            1258 => 'Windows-1258',
            default => null,
        };
    }

    private function decodeWindows1251(string $bytes): string
    {
        $map = [
            0x80 => 0x0402,
            0x81 => 0x0403,
            0x82 => 0x201a,
            0x83 => 0x0453,
            0x84 => 0x201e,
            0x85 => 0x2026,
            0x86 => 0x2020,
            0x87 => 0x2021,
            0x88 => 0x20ac,
            0x89 => 0x2030,
            0x8a => 0x0409,
            0x8b => 0x2039,
            0x8c => 0x040a,
            0x8d => 0x040c,
            0x8e => 0x040b,
            0x8f => 0x040f,
            0x90 => 0x0452,
            0x91 => 0x2018,
            0x92 => 0x2019,
            0x93 => 0x201c,
            0x94 => 0x201d,
            0x95 => 0x2022,
            0x96 => 0x2013,
            0x97 => 0x2014,
            0x99 => 0x2122,
            0x9a => 0x0459,
            0x9b => 0x203a,
            0x9c => 0x045a,
            0x9d => 0x045c,
            0x9e => 0x045b,
            0x9f => 0x045f,
            0xa0 => 0x00a0,
            0xa1 => 0x040e,
            0xa2 => 0x045e,
            0xa3 => 0x0408,
            0xa4 => 0x00a4,
            0xa5 => 0x0490,
            0xa6 => 0x00a6,
            0xa7 => 0x00a7,
            0xa8 => 0x0401,
            0xa9 => 0x00a9,
            0xaa => 0x0404,
            0xab => 0x00ab,
            0xac => 0x00ac,
            0xad => 0x00ad,
            0xae => 0x00ae,
            0xaf => 0x0407,
            0xb0 => 0x00b0,
            0xb1 => 0x00b1,
            0xb2 => 0x0406,
            0xb3 => 0x0456,
            0xb4 => 0x0491,
            0xb5 => 0x00b5,
            0xb6 => 0x00b6,
            0xb7 => 0x00b7,
            0xb8 => 0x0451,
            0xb9 => 0x2116,
            0xba => 0x0454,
            0xbb => 0x00bb,
            0xbc => 0x0458,
            0xbd => 0x0405,
            0xbe => 0x0455,
            0xbf => 0x0457,
        ];

        $text = '';
        for ($index = 0, $length = strlen($bytes); $index < $length; $index++) {
            $byte = ord($bytes[$index]);
            if ($byte < 0x80) {
                $text .= chr($byte);
            } elseif ($byte >= 0xc0) {
                $text .= self::codepointToUtf8(0x0410 + ($byte - 0xc0));
            } elseif (isset($map[$byte])) {
                $text .= self::codepointToUtf8($map[$byte]);
            } else {
                $text .= self::codepointToUtf8($byte);
            }
        }

        return $text;
    }

    private function decodeUtf16Le(string $bytes): string
    {
        $decoded = iconv('UTF-16LE', 'UTF-8//IGNORE', $bytes);

        return is_string($decoded) ? $decoded : '';
    }

    private function readLpstr(string $bytes, int $offset, int $codepage): ?string
    {
        $value = $this->readLpstrWithSize($bytes, $offset, $codepage);

        return $value['value'] ?? null;
    }

    /**
     * @return array{value:string,bytes:int}|null
     */
    private function readLpstrWithSize(string $bytes, int $offset, int $codepage): ?array
    {
        if ($offset + 4 > strlen($bytes)) {
            return null;
        }
        $length = self::u32($bytes, $offset);
        if ($length === 0 || $offset + 4 + $length > strlen($bytes)) {
            return null;
        }

        return [
            'value' => $this->decodeCodePageString(substr($bytes, $offset + 4, $length), $codepage),
            'bytes' => 4 + $length,
        ];
    }

    private function readLpwstr(string $bytes, int $offset): ?string
    {
        $value = $this->readLpwstrWithSize($bytes, $offset);

        return $value['value'] ?? null;
    }

    /**
     * @return array{value:string,bytes:int}|null
     */
    private function readLpwstrWithSize(string $bytes, int $offset): ?array
    {
        if ($offset + 4 > strlen($bytes)) {
            return null;
        }
        $characters = self::u32($bytes, $offset);
        $byteLength = $characters * 2;
        if ($characters === 0 || $offset + 4 + $byteLength > strlen($bytes)) {
            return null;
        }

        return [
            'value' => rtrim($this->decodeUtf16Le(substr($bytes, $offset + 4, $byteLength)), "\0"),
            'bytes' => 4 + $byteLength,
        ];
    }

    /**
     * @return array{value:list<string>,bytes:int}|null
     */
    private function readLpstrVectorWithSize(string $bytes, int $offset, int $codepage): ?array
    {
        if ($offset + 4 > strlen($bytes)) {
            return null;
        }

        $count = self::u32($bytes, $offset);
        $cursor = $offset + 4;
        $values = [];
        for ($index = 0; $index < $count; $index++) {
            $value = $this->readLpstrWithSize($bytes, $cursor, $codepage);
            if ($value === null) {
                return null;
            }
            $values[] = $value['value'];
            $cursor += $value['bytes'];
        }

        return [
            'value' => $values,
            'bytes' => $cursor - $offset,
        ];
    }

    /**
     * @return array{value:list<string>,bytes:int}|null
     */
    private function readLpwstrVectorWithSize(string $bytes, int $offset): ?array
    {
        if ($offset + 4 > strlen($bytes)) {
            return null;
        }

        $count = self::u32($bytes, $offset);
        $cursor = $offset + 4;
        $values = [];
        for ($index = 0; $index < $count; $index++) {
            $value = $this->readLpwstrWithSize($bytes, $cursor);
            if ($value === null) {
                return null;
            }
            $values[] = $value['value'];
            $cursor += $value['bytes'];
        }

        return [
            'value' => $values,
            'bytes' => $cursor - $offset,
        ];
    }

    /**
     * @return array<int,string>
     */
    private function readDictionary(string $bytes, int $offset, int $codepage, int $limit): array
    {
        if ($offset + 4 > $limit || $limit > strlen($bytes)) {
            return [];
        }

        $count = self::u32($bytes, $offset);
        $cursor = $offset + 4;
        $dictionary = [];
        $seenNames = [];
        for ($index = 0; $index < $count; $index++) {
            if ($cursor + 8 > $limit) {
                return [];
            }

            $propertyId = self::u32($bytes, $cursor);
            $nameLength = self::u32($bytes, $cursor + 4);
            $cursor += 8;
            if ($propertyId < 2 || $propertyId > 0x7fffffff || $nameLength === 0) {
                return [];
            }

            if ($codepage === 1200) {
                $byteLength = $nameLength * 2;
                if ($cursor + $byteLength > $limit) {
                    return [];
                }

                $name = rtrim($this->decodeUtf16Le(substr($bytes, $cursor, $byteLength)), "\0");
                $cursor += $byteLength;
                $padding = (4 - ($byteLength % 4)) % 4;
                if ($cursor + $padding > $limit) {
                    return [];
                }
                $cursor += $padding;
            } else {
                if ($cursor + $nameLength > $limit) {
                    return [];
                }

                $name = $this->decodeCodePageString(substr($bytes, $cursor, $nameLength), $codepage);
                $cursor += $nameLength;
            }

            $normalizedName = strtolower($name);
            if (isset($dictionary[$propertyId]) || isset($seenNames[$normalizedName])) {
                throw new \RuntimeException('Legacy DOC custom property dictionary contains duplicate entries');
            }

            $dictionary[$propertyId] = $name;
            $seenNames[$normalizedName] = true;
        }

        return $dictionary;
    }

    /**
     * @return array{value:list<mixed>,bytes:int}|null
     */
    private function readVariantVectorWithSize(string $bytes, int $offset, int $codepage): ?array
    {
        if ($offset + 4 > strlen($bytes)) {
            return null;
        }

        $count = self::u32($bytes, $offset);
        $cursor = $offset + 4;
        $values = [];
        for ($index = 0; $index < $count; $index++) {
            $value = $this->readTypedPropertyValueWithSize($bytes, $cursor, $codepage);
            if ($value === null) {
                return null;
            }
            $values[] = $value['value'];
            $cursor += $value['bytes'];
        }

        return [
            'value' => $values,
            'bytes' => $cursor - $offset,
        ];
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $strings = [];
        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $strings[] = $item;
            }
        }

        return $strings;
    }

    /**
     * @param list<string> $documentParts
     * @return list<array{heading:string,count:int,parts:list<string>}>
     */
    private function documentHeadingPairs(mixed $value, array $documentParts): array
    {
        if (!is_array($value)) {
            return [];
        }

        $pairs = [];
        $partOffset = 0;
        for ($index = 0, $count = count($value); $index + 1 < $count; $index += 2) {
            $heading = $value[$index];
            $partCount = $value[$index + 1];
            if (!is_string($heading) || $heading === '' || !is_int($partCount) || $partCount < 0) {
                continue;
            }

            $parts = array_slice($documentParts, $partOffset, $partCount);
            $partOffset += $partCount;
            $pairs[] = [
                'heading' => $heading,
                'count' => $partCount,
                'parts' => $parts,
            ];
        }

        return $pairs;
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
