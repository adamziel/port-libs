<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class LegacyDocReader
{
    private const SUMMARY_INFORMATION = "\x05SummaryInformation";
    private const DOCUMENT_SUMMARY_INFORMATION = "\x05DocumentSummaryInformation";
    private const FMTID_USER_DEFINED_PROPERTIES = '05d5cdd59c2e1b10939708002b2cf9ae';
    private const FIB_FC_PLCFFND_REF = 0x00aa;
    private const FIB_LCB_PLCFFND_REF = 0x00ae;
    private const FIB_FC_PLCFFND_TXT = 0x00b2;
    private const FIB_LCB_PLCFFND_TXT = 0x00b6;
    private const FIB_FC_PLCF_SED = 0x00ca;
    private const FIB_LCB_PLCF_SED = 0x00ce;
    private const FIB_FC_STTBF_BKMK = 0x0142;
    private const FIB_LCB_STTBF_BKMK = 0x0146;
    private const FIB_FC_PLCF_BKF = 0x014a;
    private const FIB_LCB_PLCF_BKF = 0x014e;
    private const FIB_FC_PLCF_BKL = 0x0152;
    private const FIB_LCB_PLCF_BKL = 0x0156;
    private const FIB_FC_PLCFEND_REF = 0x020a;
    private const FIB_LCB_PLCFEND_REF = 0x020e;
    private const FIB_FC_PLCFEND_TXT = 0x0212;
    private const FIB_LCB_PLCFEND_TXT = 0x0216;

    /**
     * @return array{document:AstNode, metadata:array<string,mixed>, streams:list<string>, streamDirectory:list<array<string,mixed>>, directoryEntries:list<array<string,mixed>>, fib:array<string,mixed>, sections:list<array<string,mixed>>, bookmarks:list<array<string,mixed>>, footnotes:list<array<string,mixed>>, endnotes:list<array<string,mixed>>, embeddedObjects:list<array<string,mixed>>, macroProjects:list<array<string,mixed>>}
     */
    public function readBytes(string $bytes): array
    {
        return $this->readCompoundFile(CompoundFileBinary::fromBytes($bytes));
    }

    /**
     * @return array{document:AstNode, metadata:array<string,mixed>, streams:list<string>, streamDirectory:list<array<string,mixed>>, directoryEntries:list<array<string,mixed>>, fib:array<string,mixed>, sections:list<array<string,mixed>>, bookmarks:list<array<string,mixed>>, footnotes:list<array<string,mixed>>, endnotes:list<array<string,mixed>>, embeddedObjects:list<array<string,mixed>>, macroProjects:list<array<string,mixed>>}
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
        $streamDirectory = $this->streamDirectoryReport($compoundFile);
        $directoryEntries = $this->directoryEntryReport($compoundFile);
        $metadata = $this->readMetadata($compoundFile);
        if ($streamDirectory !== []) {
            $metadata['cfbStreamCount'] = count($streamDirectory);
        }
        $timestampedDirectoryEntryCount = 0;
        foreach ($directoryEntries as $directoryEntry) {
            if (isset($directoryEntry['createdAt']) || isset($directoryEntry['modifiedAt'])) {
                $timestampedDirectoryEntryCount++;
            }
        }
        if ($timestampedDirectoryEntryCount > 0) {
            $metadata['cfbTimestampedDirectoryEntryCount'] = $timestampedDirectoryEntryCount;
        }
        $sections = $this->sectionReport($wordDocument, $tableStream, $textResult['text']);
        if ($sections !== []) {
            $metadata['sectionCount'] = count($sections);
            $metadata['sections'] = $sections;
        }
        $bookmarks = $this->standardBookmarkReport($wordDocument, $tableStream, $textResult['text']);
        if ($bookmarks !== []) {
            $metadata['bookmarkCount'] = count($bookmarks);
            $metadata['bookmarks'] = $bookmarks;
        }
        $footnotes = $this->noteReferenceReport('footnote', $wordDocument, $tableStream, $textResult['text']);
        if ($footnotes !== []) {
            $metadata['footnoteReferenceCount'] = count($footnotes);
            $metadata['footnotes'] = $footnotes;
        }
        $endnotes = $this->noteReferenceReport('endnote', $wordDocument, $tableStream, $textResult['text']);
        if ($endnotes !== []) {
            $metadata['endnoteReferenceCount'] = count($endnotes);
            $metadata['endnotes'] = $endnotes;
        }
        $embeddedObjects = $this->embeddedObjectReport($compoundFile);
        if ($embeddedObjects !== []) {
            $metadata['embeddedObjectCount'] = count($embeddedObjects);
        }
        $macroProjects = $this->macroProjectReport($compoundFile);
        if ($macroProjects !== []) {
            $metadata['containsMacros'] = true;
            $metadata['macroProjectCount'] = count($macroProjects);
            $metadata['macroPolicy'] = 'disabled-native-review';
        }

        $attrs = [
            'sourceFormat' => 'doc',
            'cfbStreams' => $compoundFile->streamNames(),
            'cfbStreamDirectory' => $streamDirectory,
            'cfbDirectoryEntries' => $directoryEntries,
            'textSource' => $textResult['source'],
            'tableStream' => $tableStreamName,
            'meta' => $metadata,
            'sections' => $sections,
            'bookmarks' => $bookmarks,
            'footnotes' => $footnotes,
            'endnotes' => $endnotes,
            'embeddedObjects' => $embeddedObjects,
            'macroProjects' => $macroProjects,
        ];

        return [
            'document' => new AstNode('document', $attrs, $this->paragraphNodes(
                $textResult['text'],
                $bookmarks,
                array_merge($footnotes, $endnotes)
            )),
            'metadata' => $metadata,
            'streams' => $compoundFile->streamNames(),
            'streamDirectory' => $streamDirectory,
            'directoryEntries' => $directoryEntries,
            'fib' => $fib + ['textSource' => $textResult['source']],
            'sections' => $sections,
            'bookmarks' => $bookmarks,
            'footnotes' => $footnotes,
            'endnotes' => $endnotes,
            'embeddedObjects' => $embeddedObjects,
            'macroProjects' => $macroProjects,
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
     * @param list<array<string,mixed>> $bookmarks
     * @param list<array<string,mixed>> $noteReferences
     * @return list<AstNode>
     */
    private function paragraphNodes(string $text, array $bookmarks = [], array $noteReferences = []): array
    {
        $normalized = str_replace(["\r\n", "\n"], "\r", $text);
        $paragraphs = explode("\r", $normalized);
        $nodes = [];
        $paragraphStartCp = 0;
        foreach ($paragraphs as $paragraph) {
            $paragraphLength = $this->textCharacterLength($paragraph);
            $paragraph = str_replace("\0", '', $paragraph);
            if (trim($paragraph) === '') {
                $paragraphStartCp += $paragraphLength + 1;
                continue;
            }
            $nodes[] = new AstNode('paragraph', [], $this->inlineNodesWithBookmarks(
                $paragraph,
                $paragraphStartCp,
                $bookmarks,
                $noteReferences
            ));
            $paragraphStartCp += $paragraphLength + 1;
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
     * @param list<array<string,mixed>> $bookmarks
     * @param list<array<string,mixed>> $noteReferences
     * @return list<AstNode>
     */
    private function inlineNodesWithBookmarks(
        string $text,
        int $paragraphStartCp,
        array $bookmarks,
        array $noteReferences = []
    ): array {
        if ($bookmarks === []) {
            return $this->inlineNodesWithNoteReferences($text, $paragraphStartCp, $noteReferences);
        }

        $chars = $this->unicodeCharacters($text);
        $paragraphLength = count($chars);
        $paragraphEndCp = $paragraphStartCp + $paragraphLength;
        $candidates = [];
        foreach ($bookmarks as $bookmark) {
            if (($bookmark['canAnchor'] ?? false) !== true) {
                continue;
            }

            $startCp = (int) ($bookmark['startCp'] ?? -1);
            $endCp = (int) ($bookmark['endCp'] ?? -1);
            if ($startCp < $paragraphStartCp || $endCp > $paragraphEndCp || $startCp > $endCp) {
                continue;
            }

            $candidates[] = $bookmark;
        }
        if ($candidates === []) {
            return $this->inlineNodesWithNoteReferences($text, $paragraphStartCp, $noteReferences);
        }

        usort(
            $candidates,
            static function (array $left, array $right): int {
                $start = ((int) $left['startCp']) <=> ((int) $right['startCp']);
                if ($start !== 0) {
                    return $start;
                }

                return ((int) $right['endCp']) <=> ((int) $left['endCp']);
            }
        );

        $nodes = [];
        $cursor = 0;
        foreach ($candidates as $bookmark) {
            $start = (int) $bookmark['startCp'] - $paragraphStartCp;
            $end = (int) $bookmark['endCp'] - $paragraphStartCp;
            if ($start < $cursor || $start > $paragraphLength || $end > $paragraphLength) {
                continue;
            }
            if ($start > $cursor) {
                array_push(
                    $nodes,
                    ...$this->inlineNodesWithNoteReferences(
                        $this->charactersToString(array_slice($chars, $cursor, $start - $cursor)),
                        $paragraphStartCp + $cursor,
                        $noteReferences
                    )
                );
            }

            $bookmarkText = $this->charactersToString(array_slice($chars, $start, $end - $start));
            $bookmarkNodes = $bookmarkText === ''
                ? []
                : $this->inlineNodesWithNoteReferences($bookmarkText, $paragraphStartCp + $start, $noteReferences);
            $nodes[] = new AstNode('span', $this->bookmarkSpanAttrs($bookmark), $bookmarkNodes);
            $cursor = $end;
        }

        if ($cursor < $paragraphLength) {
            array_push(
                $nodes,
                ...$this->inlineNodesWithNoteReferences(
                    $this->charactersToString(array_slice($chars, $cursor)),
                    $paragraphStartCp + $cursor,
                    $noteReferences
                )
            );
        }

        return $nodes === [] ? $this->inlineNodesWithNoteReferences($text, $paragraphStartCp, $noteReferences) : $nodes;
    }

    /**
     * @param list<array<string,mixed>> $noteReferences
     * @return list<AstNode>
     */
    private function inlineNodesWithNoteReferences(string $text, int $segmentStartCp, array $noteReferences): array
    {
        if ($noteReferences === []) {
            return $this->inlineNodes($text);
        }

        $chars = $this->unicodeCharacters($text);
        $segmentLength = count($chars);
        $segmentEndCp = $segmentStartCp + $segmentLength;
        $candidates = [];
        foreach ($noteReferences as $noteReference) {
            if (($noteReference['canAnchor'] ?? false) !== true) {
                continue;
            }

            $referenceCp = (int) ($noteReference['referenceCp'] ?? -1);
            if ($referenceCp < $segmentStartCp || $referenceCp >= $segmentEndCp) {
                continue;
            }

            $candidates[] = $noteReference;
        }
        if ($candidates === []) {
            return $this->inlineNodes($text);
        }

        usort(
            $candidates,
            static fn (array $left, array $right): int => ((int) $left['referenceCp']) <=> ((int) $right['referenceCp'])
        );

        $nodes = [];
        $cursor = 0;
        foreach ($candidates as $noteReference) {
            $localCp = (int) $noteReference['referenceCp'] - $segmentStartCp;
            if ($localCp < $cursor || $localCp >= $segmentLength) {
                continue;
            }

            if ($localCp > $cursor) {
                array_push(
                    $nodes,
                    ...$this->inlineNodes($this->charactersToString(array_slice($chars, $cursor, $localCp - $cursor)))
                );
            }

            $nodes[] = new AstNode('span', $this->noteReferenceSpanAttrs($noteReference), [
                new AstNode('superscript', [], [
                    new AstNode('text', ['text' => (string) ($noteReference['marker'] ?? '')]),
                ]),
            ]);
            $cursor = $localCp + 1;
        }

        if ($cursor < $segmentLength) {
            array_push($nodes, ...$this->inlineNodes($this->charactersToString(array_slice($chars, $cursor))));
        }

        return $nodes === [] ? $this->inlineNodes($text) : $nodes;
    }

    /**
     * @param array<string,mixed> $bookmark
     * @return array{id:string,classes:list<string>,attributes:array<string,string>}
     */
    private function bookmarkSpanAttrs(array $bookmark): array
    {
        $classes = ['legacy-doc-bookmark'];
        if (($bookmark['hidden'] ?? false) === true) {
            $classes[] = 'legacy-doc-bookmark-hidden';
        }

        return [
            'id' => (string) ($bookmark['anchorId'] ?? $bookmark['name'] ?? ''),
            'classes' => $classes,
            'attributes' => [
                'data-legacy-doc-bookmark' => (string) ($bookmark['name'] ?? ''),
                'data-legacy-doc-bookmark-start-cp' => (string) ((int) ($bookmark['startCp'] ?? 0)),
                'data-legacy-doc-bookmark-end-cp' => (string) ((int) ($bookmark['endCp'] ?? 0)),
            ],
        ];
    }

    /**
     * @param array<string,mixed> $noteReference
     * @return array{classes:list<string>,attributes:array<string,string>}
     */
    private function noteReferenceSpanAttrs(array $noteReference): array
    {
        $type = (string) ($noteReference['type'] ?? 'footnote');

        return [
            'classes' => ['legacy-doc-note-ref', 'legacy-doc-' . $type . '-ref'],
            'attributes' => [
                'data-legacy-doc-note-type' => $type,
                'data-legacy-doc-note-index' => (string) ((int) ($noteReference['referenceIndex'] ?? 0)),
                'data-legacy-doc-note-reference-cp' => (string) ((int) ($noteReference['referenceCp'] ?? 0)),
                'data-legacy-doc-note-text-start-cp' => (string) ((int) ($noteReference['textStartCp'] ?? 0)),
                'data-legacy-doc-note-text-end-cp' => (string) ((int) ($noteReference['textEndCp'] ?? 0)),
                'data-legacy-doc-note-auto-numbered' => ($noteReference['autoNumbered'] ?? false) === true ? 'true' : 'false',
            ],
        ];
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
    private function streamDirectoryReport(CompoundFileBinary $compoundFile): array
    {
        $streams = [];
        foreach ($compoundFile->entries() as $entry) {
            if (($entry['type'] ?? null) !== 2) {
                continue;
            }

            $path = (string) ($entry['path'] ?? '');
            $slash = strrpos($path, '/');
            $stream = [
                'path' => $path,
                'name' => (string) ($entry['name'] ?? ''),
                'storagePath' => $slash === false ? '' : substr($path, 0, $slash),
                'bytes' => (int) ($entry['size'] ?? 0),
                'directoryId' => (int) ($entry['directoryId'] ?? 0),
            ];

            if (($entry['createdAt'] ?? null) !== null) {
                $stream['createdAt'] = (string) $entry['createdAt'];
            }
            if (($entry['modifiedAt'] ?? null) !== null) {
                $stream['modifiedAt'] = (string) $entry['modifiedAt'];
            }

            $streams[] = $stream;
        }

        return $streams;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function directoryEntryReport(CompoundFileBinary $compoundFile): array
    {
        $entries = [];
        foreach ($compoundFile->entries() as $entry) {
            $path = (string) ($entry['path'] ?? '');
            $typeId = (int) ($entry['type'] ?? 0);
            $record = [
                'path' => $path,
                'name' => (string) ($entry['name'] ?? ''),
                'type' => match ($typeId) {
                    1 => 'storage',
                    2 => 'stream',
                    5 => 'root',
                    default => 'unknown',
                },
                'bytes' => (int) ($entry['size'] ?? 0),
                'directoryId' => (int) ($entry['directoryId'] ?? 0),
            ];

            if (($entry['createdAt'] ?? null) !== null) {
                $record['createdAt'] = (string) $entry['createdAt'];
            }
            if (($entry['modifiedAt'] ?? null) !== null) {
                $record['modifiedAt'] = (string) $entry['modifiedAt'];
            }

            $entries[] = $record;
        }

        return $entries;
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
     * @return list<array<string,mixed>>
     */
    private function macroProjectReport(CompoundFileBinary $compoundFile): array
    {
        $projects = [];
        foreach ($compoundFile->entries() as $entry) {
            if ($entry['type'] !== 2) {
                continue;
            }

            $path = (string) $entry['path'];
            $segments = explode('/', $path);
            $root = $this->macroProjectRoot($segments);
            if ($root === null) {
                continue;
            }

            $streamName = implode('/', array_slice($segments, 1));
            if ($streamName === '') {
                continue;
            }

            $role = $this->macroProjectStreamRole($streamName);
            $stream = [
                'path' => $path,
                'name' => $streamName,
                'role' => $role,
                'bytes' => (int) $entry['size'],
                'canExposeBytes' => false,
            ];

            $projects[$root] ??= [
                'storagePath' => $root,
                'policy' => 'macro-execution-disabled',
                'canExecute' => false,
                'canExposeBytes' => false,
                'streamCount' => 0,
                'totalBytes' => 0,
                'hasVbaStorage' => false,
                'hasDirStream' => false,
                'hasProjectStream' => false,
                'hasProjectWmStream' => false,
                'hasPerformanceCache' => false,
                'moduleStreams' => [],
                'streams' => [],
            ];

            $projects[$root]['streamCount']++;
            $projects[$root]['totalBytes'] += (int) $entry['size'];
            $projects[$root]['hasVbaStorage'] = $projects[$root]['hasVbaStorage']
                || str_starts_with(strtolower($streamName), 'vba/');
            $projects[$root]['hasDirStream'] = $projects[$root]['hasDirStream']
                || $role === 'vba-dir-compressed';
            $projects[$root]['hasProjectStream'] = $projects[$root]['hasProjectStream']
                || $role === 'project-properties';
            $projects[$root]['hasProjectWmStream'] = $projects[$root]['hasProjectWmStream']
                || $role === 'project-codepage';
            $projects[$root]['hasPerformanceCache'] = $projects[$root]['hasPerformanceCache']
                || $role === 'vba-performance-cache';
            if ($role === 'module-stream') {
                $projects[$root]['moduleStreams'][] = basename($streamName);
            }
            $projects[$root]['streams'][] = $stream;
        }

        $result = array_values($projects);
        foreach ($result as &$project) {
            $moduleStreams = array_values(array_unique(array_map(
                static fn (mixed $name): string => (string) $name,
                $project['moduleStreams']
            )));
            sort($moduleStreams, SORT_STRING);
            $project['moduleStreams'] = $moduleStreams;

            usort(
                $project['streams'],
                static fn (array $left, array $right): int => strcmp((string) $left['path'], (string) $right['path'])
            );

            $diagnostics = [];
            if ($project['hasVbaStorage'] === true && $project['hasDirStream'] !== true) {
                $diagnostics[] = [
                    'code' => 'missing-vba-dir-stream',
                    'message' => 'VBA storage is present but the compressed dir stream was not found',
                ];
            }
            if ($project['hasDirStream'] === true) {
                $diagnostics[] = [
                    'code' => 'compressed-vba-dir-not-expanded',
                    'message' => 'VBA dir stream bytes are retained for reviewer policy only and are not decompressed or executed',
                ];
            }
            $project['diagnostics'] = $diagnostics;
        }
        unset($project);

        usort(
            $result,
            static fn (array $left, array $right): int => strcmp((string) $left['storagePath'], (string) $right['storagePath'])
        );

        return $result;
    }

    /**
     * @param list<string> $segments
     */
    private function macroProjectRoot(array $segments): ?string
    {
        if ($segments === []) {
            return null;
        }

        $root = (string) $segments[0];

        return in_array(strtolower($root), ['macros', '_vba_project_cur'], true) ? $root : null;
    }

    private function macroProjectStreamRole(string $streamName): string
    {
        $normalized = strtolower(str_replace('\\', '/', $streamName));
        $baseName = basename($streamName);
        $lowerBaseName = strtolower($baseName);

        if ($normalized === 'vba/dir') {
            return 'vba-dir-compressed';
        }
        if ($normalized === 'vba/_vba_project') {
            return 'vba-performance-cache';
        }
        if ($streamName === 'PROJECT') {
            return 'project-properties';
        }
        if ($streamName === 'PROJECTwm') {
            return 'project-codepage';
        }
        if (str_starts_with($normalized, 'vba/') && !in_array($lowerBaseName, ['dir', '_vba_project'], true)) {
            return 'module-stream';
        }

        return 'private-macro-data';
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

    /**
     * @return list<array<string,mixed>>
     */
    private function noteReferenceReport(string $type, string $wordDocument, ?string $tableStream, string $text): array
    {
        if ($tableStream === null) {
            return [];
        }

        if ($type === 'footnote') {
            $fcRefOffset = self::FIB_FC_PLCFFND_REF;
            $lcbRefOffset = self::FIB_LCB_PLCFFND_REF;
            $fcTxtOffset = self::FIB_FC_PLCFFND_TXT;
            $lcbTxtOffset = self::FIB_LCB_PLCFFND_TXT;
        } else {
            $fcRefOffset = self::FIB_FC_PLCFEND_REF;
            $lcbRefOffset = self::FIB_LCB_PLCFEND_REF;
            $fcTxtOffset = self::FIB_FC_PLCFEND_TXT;
            $lcbTxtOffset = self::FIB_LCB_PLCFEND_TXT;
        }
        if (strlen($wordDocument) < $lcbTxtOffset + 4) {
            return [];
        }

        $fib = $this->readFib($wordDocument);
        if ((int) $fib['fcMin'] > 0 && $lcbTxtOffset + 4 > (int) $fib['fcMin']) {
            return [];
        }

        $fcRef = self::u32($wordDocument, $fcRefOffset);
        $lcbRef = self::u32($wordDocument, $lcbRefOffset);
        $fcTxt = self::u32($wordDocument, $fcTxtOffset);
        $lcbTxt = self::u32($wordDocument, $lcbTxtOffset);
        if ($lcbRef === 0 && $lcbTxt === 0) {
            return [];
        }
        if ($lcbRef === 0 || $lcbTxt === 0) {
            throw new \RuntimeException('Legacy DOC ' . $type . ' reference PLC is present without matching text-range PLC');
        }

        $references = $this->parseNoteReferencePlc(
            $this->tableStreamSlice($tableStream, $fcRef, $lcbRef, $type . ' reference PLC'),
            $type
        );
        $ranges = $this->parseNoteTextPlc(
            $this->tableStreamSlice($tableStream, $fcTxt, $lcbTxt, $type . ' text PLC'),
            $type
        );
        if (count($references) !== count($ranges)) {
            throw new \RuntimeException('Legacy DOC ' . $type . ' reference and text PLCs do not contain parallel counts');
        }

        $characters = $this->unicodeCharacters($text);
        $textLength = count($characters);
        $result = [];
        foreach ($references as $index => $reference) {
            $referenceCp = (int) $reference['referenceCp'];
            if ($referenceCp < 0 || $referenceCp >= $textLength) {
                throw new \RuntimeException('Legacy DOC ' . $type . ' reference CP points outside the extracted main text');
            }

            $referenceIndex = (int) $reference['referenceIndex'];
            $autoNumbered = $referenceIndex !== 0;
            $referenceCharacter = $characters[$referenceCp] ?? '';
            if ($autoNumbered && $referenceCharacter !== "\x02") {
                throw new \RuntimeException('Legacy DOC auto-numbered ' . $type . ' reference is missing the special reference character');
            }

            $range = $ranges[$index];
            $result[] = [
                'type' => $type,
                'index' => $index + 1,
                'referenceCp' => $referenceCp,
                'referenceIndex' => $referenceIndex,
                'autoNumbered' => $autoNumbered,
                'marker' => $this->noteReferenceMarker($autoNumbered, $index, $referenceCharacter),
                'textStartCp' => (int) $range['startCp'],
                'textEndCp' => (int) $range['endCp'],
                'canAnchor' => true,
            ];
        }

        return $result;
    }

    /**
     * @return list<array{referenceCp:int,referenceIndex:int}>
     */
    private function parseNoteReferencePlc(string $bytes, string $type): array
    {
        $length = strlen($bytes);
        if ($length < 10 || (($length - 4) % 6) !== 0) {
            throw new \RuntimeException('Legacy DOC ' . $type . ' reference PLC has an invalid length');
        }

        $count = intdiv($length - 4, 6);
        $dataOffset = ($count + 1) * 4;
        $entries = [];
        $previousCp = null;
        $seenCps = [];
        for ($index = 0; $index < $count; $index++) {
            $referenceCp = self::u32($bytes, $index * 4);
            if ($previousCp !== null && $referenceCp <= $previousCp) {
                throw new \RuntimeException('Legacy DOC ' . $type . ' reference PLC contains duplicate or unsorted CPs');
            }
            if (isset($seenCps[$referenceCp])) {
                throw new \RuntimeException('Legacy DOC ' . $type . ' reference PLC contains duplicate CPs');
            }
            $previousCp = $referenceCp;
            $seenCps[$referenceCp] = true;
            $entries[] = [
                'referenceCp' => $referenceCp,
                'referenceIndex' => self::u16($bytes, $dataOffset + ($index * 2)),
            ];
        }

        $ignoredCp = self::u32($bytes, $count * 4);
        if ($previousCp !== null && $ignoredCp <= $previousCp) {
            throw new \RuntimeException('Legacy DOC ' . $type . ' reference PLC final CP is not after the last reference CP');
        }

        return $entries;
    }

    /**
     * @return list<array{startCp:int,endCp:int}>
     */
    private function parseNoteTextPlc(string $bytes, string $type): array
    {
        $length = strlen($bytes);
        if ($length < 12 || ($length % 4) !== 0) {
            throw new \RuntimeException('Legacy DOC ' . $type . ' text PLC has an invalid length');
        }

        $cpCount = intdiv($length, 4);
        $noteCount = $cpCount - 2;
        $cps = [];
        $previousCp = null;
        for ($index = 0; $index < $cpCount; $index++) {
            $cp = self::u32($bytes, $index * 4);
            if ($previousCp !== null && $cp <= $previousCp) {
                throw new \RuntimeException('Legacy DOC ' . $type . ' text PLC contains duplicate or unsorted CPs');
            }
            $previousCp = $cp;
            $cps[] = $cp;
        }

        $ranges = [];
        for ($index = 0; $index < $noteCount; $index++) {
            $startCp = $cps[$index];
            $endCp = $cps[$index + 1];
            if ($startCp >= $endCp) {
                throw new \RuntimeException('Legacy DOC ' . $type . ' text PLC contains an empty note range');
            }
            $ranges[] = [
                'startCp' => $startCp,
                'endCp' => $endCp,
            ];
        }

        return $ranges;
    }

    private function noteReferenceMarker(bool $autoNumbered, int $index, string $referenceCharacter): string
    {
        if ($autoNumbered) {
            return (string) ($index + 1);
        }
        if ($referenceCharacter !== '' && preg_match('/[\x00-\x1f]/u', $referenceCharacter) !== 1) {
            return $referenceCharacter;
        }

        return '*';
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function sectionReport(string $wordDocument, ?string $tableStream, string $text): array
    {
        if ($tableStream === null || strlen($wordDocument) < self::FIB_LCB_PLCF_SED + 4) {
            return [];
        }

        $fcPlcfSed = self::u32($wordDocument, self::FIB_FC_PLCF_SED);
        $lcbPlcfSed = self::u32($wordDocument, self::FIB_LCB_PLCF_SED);
        if ($lcbPlcfSed === 0) {
            return [];
        }

        return $this->parsePlcfSed(
            $this->tableStreamSlice($tableStream, $fcPlcfSed, $lcbPlcfSed, 'PlcfSed'),
            $wordDocument,
            $text
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function parsePlcfSed(string $bytes, string $wordDocument, string $text): array
    {
        $length = strlen($bytes);
        if ($length < 20 || (($length - 4) % 16) !== 0) {
            throw new \RuntimeException('Legacy DOC section descriptor PLC has an invalid length');
        }

        $sectionCount = intdiv($length - 4, 16);
        $cpCount = $sectionCount + 1;
        $cps = [];
        $previousCp = null;
        for ($index = 0; $index < $cpCount; $index++) {
            $cp = self::u32($bytes, $index * 4);
            if ($previousCp !== null && $cp <= $previousCp) {
                throw new \RuntimeException('Legacy DOC section descriptor PLC contains duplicate or unsorted CPs');
            }
            if ($index === 0 && $cp !== 0) {
                throw new \RuntimeException('Legacy DOC section descriptor PLC must start at main-text CP 0');
            }

            $previousCp = $cp;
            $cps[] = $cp;
        }

        $characters = $this->unicodeCharacters($text);
        $textLength = count($characters);
        if ($cps[$sectionCount] < $textLength) {
            throw new \RuntimeException('Legacy DOC section descriptor PLC final CP is before the extracted main text ends');
        }

        $sections = [];
        $sedOffset = $cpCount * 4;
        for ($index = 0; $index < $sectionCount; $index++) {
            $startCp = $cps[$index];
            $endCp = $cps[$index + 1];
            $hasSectionBreak = $index < $sectionCount - 1;
            if ($startCp > $textLength || ($hasSectionBreak && $endCp > $textLength)) {
                throw new \RuntimeException('Legacy DOC section descriptor PLC points outside the extracted main text');
            }
            if ($hasSectionBreak) {
                $breakCharacter = $endCp > 0 ? ($characters[$endCp - 1] ?? '') : '';
                if ($breakCharacter !== "\f") {
                    throw new \RuntimeException('Legacy DOC section descriptor PLC is missing the required section-break character');
                }
            }

            $offset = $sedOffset + ($index * 12);
            $fcSepx = self::signed32(self::u32($bytes, $offset + 2));
            $section = [
                'index' => $index + 1,
                'startCp' => $startCp,
                'endCp' => min($endCp, $textLength),
                'contentEndCp' => $hasSectionBreak ? $endCp - 1 : min($endCp, $textLength),
                'hasSectionBreak' => $hasSectionBreak,
                'hasSepx' => $fcSepx !== -1,
            ];

            if ($fcSepx !== -1) {
                $section += $this->readSepxProvenance($wordDocument, $fcSepx);
            }

            $sections[] = $section;
        }

        return $sections;
    }

    /**
     * @return array{sepxFc:int,sepxByteCount:int,sprmByteCount:int}
     */
    private function readSepxProvenance(string $wordDocument, int $fcSepx): array
    {
        if ($fcSepx < 0 || $fcSepx + 2 > strlen($wordDocument)) {
            throw new \RuntimeException('Legacy DOC section descriptor SEPX pointer points outside WordDocument');
        }

        $byteCount = self::signed16(self::u16($wordDocument, $fcSepx));
        if ($byteCount < 0 || $fcSepx + 2 + $byteCount > strlen($wordDocument)) {
            throw new \RuntimeException('Legacy DOC section descriptor SEPX byte count points outside WordDocument');
        }

        return [
            'sepxFc' => $fcSepx,
            'sepxByteCount' => $byteCount + 2,
            'sprmByteCount' => $byteCount,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function standardBookmarkReport(string $wordDocument, ?string $tableStream, string $text): array
    {
        if ($tableStream === null || strlen($wordDocument) < self::FIB_LCB_PLCF_BKL + 4) {
            return [];
        }

        $fcSttbfBkmk = self::u32($wordDocument, self::FIB_FC_STTBF_BKMK);
        $lcbSttbfBkmk = self::u32($wordDocument, self::FIB_LCB_STTBF_BKMK);
        $fcPlcfBkf = self::u32($wordDocument, self::FIB_FC_PLCF_BKF);
        $lcbPlcfBkf = self::u32($wordDocument, self::FIB_LCB_PLCF_BKF);
        $fcPlcfBkl = self::u32($wordDocument, self::FIB_FC_PLCF_BKL);
        $lcbPlcfBkl = self::u32($wordDocument, self::FIB_LCB_PLCF_BKL);
        if ($lcbSttbfBkmk === 0) {
            return [];
        }
        if ($lcbPlcfBkf === 0 || $lcbPlcfBkl === 0) {
            throw new \RuntimeException('Legacy DOC bookmark names are present without matching bookmark range PLCs');
        }

        $names = $this->parseSttbfBkmk($this->tableStreamSlice($tableStream, $fcSttbfBkmk, $lcbSttbfBkmk, 'SttbfBkmk'));
        $starts = $this->parsePlcfBkf($this->tableStreamSlice($tableStream, $fcPlcfBkf, $lcbPlcfBkf, 'PlcfBkf'));
        $endCps = $this->parsePlcfBkl($this->tableStreamSlice($tableStream, $fcPlcfBkl, $lcbPlcfBkl, 'PlcfBkl'));
        if (count($names) !== count($starts) || count($starts) !== count($endCps)) {
            throw new \RuntimeException('Legacy DOC bookmark tables do not contain parallel element counts');
        }

        $textLength = $this->textCharacterLength($text);
        $bookmarks = [];
        $seenIbkl = [];
        foreach ($starts as $index => $start) {
            $ibkl = (int) $start['ibkl'];
            if ($ibkl < 0 || $ibkl >= count($endCps)) {
                throw new \RuntimeException('Legacy DOC bookmark start points outside the bookmark end PLC');
            }
            if (isset($seenIbkl[$ibkl])) {
                throw new \RuntimeException('Legacy DOC bookmark start records reuse a bookmark end index');
            }
            $seenIbkl[$ibkl] = true;

            $startCp = (int) $start['startCp'];
            $endCp = (int) $endCps[$ibkl];
            if ($startCp > $endCp) {
                throw new \RuntimeException('Legacy DOC bookmark start CP is after its end CP');
            }

            $name = $names[$index];
            $bookmarks[] = [
                'name' => $name,
                'anchorId' => $name,
                'startCp' => $startCp,
                'endCp' => $endCp,
                'hidden' => str_starts_with($name, '_'),
                'bkc' => (int) $start['bkc'],
                'canAnchor' => $startCp >= 0 && $endCp <= $textLength,
            ];
        }

        return $bookmarks;
    }

    private function tableStreamSlice(string $tableStream, int $offset, int $length, string $label): string
    {
        if ($length <= 0 || $offset < 0 || $offset + $length > strlen($tableStream)) {
            throw new \RuntimeException('Legacy DOC ' . $label . ' points outside the table stream');
        }

        return substr($tableStream, $offset, $length);
    }

    /**
     * @return list<string>
     */
    private function parseSttbfBkmk(string $bytes): array
    {
        if (strlen($bytes) < 6) {
            throw new \RuntimeException('Legacy DOC bookmark name table is truncated');
        }
        if (self::u16($bytes, 0) !== 0xffff) {
            throw new \RuntimeException('Legacy DOC bookmark name table must use extended strings');
        }
        $count = self::u16($bytes, 2);
        if ($count > 0x3ffb) {
            throw new \RuntimeException('Legacy DOC bookmark name table exceeds the standard bookmark limit');
        }
        if (self::u16($bytes, 4) !== 0) {
            throw new \RuntimeException('Legacy DOC bookmark name table must not contain extra data');
        }

        $cursor = 6;
        $names = [];
        $seen = [];
        for ($index = 0; $index < $count; $index++) {
            if ($cursor + 2 > strlen($bytes)) {
                throw new \RuntimeException('Legacy DOC bookmark name table is truncated');
            }

            $characters = self::u16($bytes, $cursor);
            $cursor += 2;
            if ($characters <= 0 || $characters >= 40) {
                throw new \RuntimeException('Legacy DOC bookmark name length is outside the supported range');
            }

            $byteLength = $characters * 2;
            if ($cursor + $byteLength > strlen($bytes)) {
                throw new \RuntimeException('Legacy DOC bookmark name table points outside its string data');
            }

            $name = $this->decodeUtf16Le(substr($bytes, $cursor, $byteLength));
            $cursor += $byteLength;
            if ($name === '') {
                throw new \RuntimeException('Legacy DOC bookmark name table contains an empty decoded name');
            }

            $key = $this->normalizedBookmarkName($name);
            if (isset($seen[$key])) {
                throw new \RuntimeException('Legacy DOC bookmark name table contains duplicate names');
            }
            $seen[$key] = true;
            $names[] = $name;
        }

        return $names;
    }

    /**
     * @return list<array{startCp:int,ibkl:int,bkc:int}>
     */
    private function parsePlcfBkf(string $bytes): array
    {
        $length = strlen($bytes);
        if ($length < 4 || (($length - 4) % 8) !== 0) {
            throw new \RuntimeException('Legacy DOC bookmark start PLC has an invalid length');
        }

        $count = intdiv($length - 4, 8);
        $dataOffset = ($count + 1) * 4;
        $entries = [];
        for ($index = 0; $index < $count; $index++) {
            $fbkfOffset = $dataOffset + ($index * 4);
            $entries[] = [
                'startCp' => self::u32($bytes, $index * 4),
                'ibkl' => self::u16($bytes, $fbkfOffset),
                'bkc' => self::u16($bytes, $fbkfOffset + 2),
            ];
        }

        return $entries;
    }

    /**
     * @return list<int>
     */
    private function parsePlcfBkl(string $bytes): array
    {
        $length = strlen($bytes);
        if ($length < 4 || ($length % 4) !== 0) {
            throw new \RuntimeException('Legacy DOC bookmark end PLC has an invalid length');
        }

        $count = intdiv($length, 4) - 1;
        $cps = [];
        for ($index = 0; $index < $count; $index++) {
            $cps[] = self::u32($bytes, $index * 4);
        }

        return $cps;
    }

    private function normalizedBookmarkName(string $name): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
    }

    private function textCharacterLength(string $text): int
    {
        return count($this->unicodeCharacters($text));
    }

    /**
     * @return list<string>
     */
    private function unicodeCharacters(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (is_array($characters)) {
            return array_values($characters);
        }

        return str_split($text);
    }

    /**
     * @param list<string> $characters
     */
    private function charactersToString(array $characters): string
    {
        return implode('', $characters);
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
