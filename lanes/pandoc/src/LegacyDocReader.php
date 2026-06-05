<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class LegacyDocReader
{
    private const SUMMARY_INFORMATION = "\x05SummaryInformation";
    private const DOCUMENT_SUMMARY_INFORMATION = "\x05DocumentSummaryInformation";
    private const FMTID_USER_DEFINED_PROPERTIES = '05d5cdd59c2e1b10939708002b2cf9ae';
    private const FIB_FC_STSHF = 0x00a2;
    private const FIB_LCB_STSHF = 0x00a6;
    private const FIB_FC_PLCFFND_REF = 0x00aa;
    private const FIB_LCB_PLCFFND_REF = 0x00ae;
    private const FIB_FC_PLCFFND_TXT = 0x00b2;
    private const FIB_LCB_PLCFFND_TXT = 0x00b6;
    private const FIB_FC_PLCFAND_REF = 0x00ba;
    private const FIB_LCB_PLCFAND_REF = 0x00be;
    private const FIB_FC_PLCFAND_TXT = 0x00c2;
    private const FIB_LCB_PLCFAND_TXT = 0x00c6;
    private const FIB_FC_PLCF_SED = 0x00ca;
    private const FIB_LCB_PLCF_SED = 0x00ce;
    private const FIB_FC_PLCF_BTE_CHPX = 0x00fa;
    private const FIB_LCB_PLCF_BTE_CHPX = 0x00fe;
    private const FIB_FC_PLCF_BTE_PAPX = 0x0102;
    private const FIB_LCB_PLCF_BTE_PAPX = 0x0106;
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
    private const FIB_RGLW97_CB_MAC = 0x0040;
    private const FIB_RGLW97_RESERVED3 = 0x0058;
    private const FIB_RGLW97_CCP_FIELDS = [
        'ccpText' => 0x004c,
        'ccpFtn' => 0x0050,
        'ccpHdd' => 0x0054,
        'ccpAtn' => 0x005c,
        'ccpEdn' => 0x0060,
        'ccpTxbx' => 0x0064,
        'ccpHdrTxbx' => 0x0068,
    ];
    private const FIB_RGLW97_SUBDOCUMENT_TYPES = [
        'ccpFtn' => 'footnote',
        'ccpHdd' => 'header',
        'ccpAtn' => 'comment',
        'ccpEdn' => 'endnote',
        'ccpTxbx' => 'textbox',
        'ccpHdrTxbx' => 'header-textbox',
    ];

    /**
     * @return array{document:AstNode, metadata:array<string,mixed>, streams:list<string>, streamDirectory:list<array<string,mixed>>, directoryEntries:list<array<string,mixed>>, fib:array<string,mixed>, styles:list<array<string,mixed>>, formattingRuns:list<array<string,mixed>>, sections:list<array<string,mixed>>, bookmarks:list<array<string,mixed>>, footnotes:list<array<string,mixed>>, endnotes:list<array<string,mixed>>, comments:list<array<string,mixed>>, embeddedObjects:list<array<string,mixed>>, macroProjects:list<array<string,mixed>>}
     */
    public function readBytes(string $bytes): array
    {
        return $this->readCompoundFile(CompoundFileBinary::fromBytes($bytes));
    }

    /**
     * @return array{document:AstNode, metadata:array<string,mixed>, streams:list<string>, streamDirectory:list<array<string,mixed>>, directoryEntries:list<array<string,mixed>>, fib:array<string,mixed>, styles:list<array<string,mixed>>, formattingRuns:list<array<string,mixed>>, sections:list<array<string,mixed>>, bookmarks:list<array<string,mixed>>, footnotes:list<array<string,mixed>>, endnotes:list<array<string,mixed>>, comments:list<array<string,mixed>>, embeddedObjects:list<array<string,mixed>>, macroProjects:list<array<string,mixed>>}
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
        if ((int) $fib['lKey'] !== 0) {
            throw new \RuntimeException('Legacy DOC unencrypted FIB contains a nonzero lKey encryption verifier');
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

        $textResult = $this->extractText($wordDocument, $tableStream, $fib);
        $streamDirectory = $this->streamDirectoryReport($compoundFile);
        $directoryEntries = $this->directoryEntryReport($compoundFile);
        $metadata = $this->readMetadata($compoundFile);
        $metadata['fibBase'] = $this->fibBaseReviewMetadata($fib);
        if (isset($fib['fibRgLw97']) && is_array($fib['fibRgLw97'])) {
            $metadata['fibRgLw97'] = $fib['fibRgLw97'];
        }
        if ($streamDirectory !== []) {
            $metadata['cfbStreamCount'] = count($streamDirectory);
        }
        $timestampedDirectoryEntryCount = 0;
        $classIdDirectoryEntryCount = 0;
        $stateBitsDirectoryEntryCount = 0;
        foreach ($directoryEntries as $directoryEntry) {
            if (isset($directoryEntry['createdAt']) || isset($directoryEntry['modifiedAt'])) {
                $timestampedDirectoryEntryCount++;
            }
            if (isset($directoryEntry['clsid'])) {
                $classIdDirectoryEntryCount++;
            }
            if (isset($directoryEntry['stateBits'])) {
                $stateBitsDirectoryEntryCount++;
            }
        }
        if ($timestampedDirectoryEntryCount > 0) {
            $metadata['cfbTimestampedDirectoryEntryCount'] = $timestampedDirectoryEntryCount;
        }
        if ($classIdDirectoryEntryCount > 0) {
            $metadata['cfbClassIdDirectoryEntryCount'] = $classIdDirectoryEntryCount;
        }
        if ($stateBitsDirectoryEntryCount > 0) {
            $metadata['cfbStateBitsDirectoryEntryCount'] = $stateBitsDirectoryEntryCount;
        }
        $styles = $this->styleSheetReport($wordDocument, $tableStream);
        if ($styles !== []) {
            $metadata['styleCount'] = count($styles);
            $metadata['styles'] = $styles;
        }
        $formattingRuns = $this->formattingRunReport($wordDocument, $tableStream);
        if ($formattingRuns !== []) {
            $metadata['formattingRunCount'] = count($formattingRuns);
            $metadata['paragraphFormattingRunCount'] = count(array_filter(
                $formattingRuns,
                static fn (array $run): bool => ($run['kind'] ?? null) === 'paragraph'
            ));
            $metadata['characterFormattingRunCount'] = count(array_filter(
                $formattingRuns,
                static fn (array $run): bool => ($run['kind'] ?? null) === 'character'
            ));
            $metadata['formattingRuns'] = $formattingRuns;
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
        $comments = $this->commentReferenceReport($wordDocument, $tableStream, $textResult['text']);
        if ($comments !== []) {
            $metadata['commentReferenceCount'] = count($comments);
            $metadata['comments'] = $comments;
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
            'styles' => $styles,
            'formattingRuns' => $formattingRuns,
            'sections' => $sections,
            'bookmarks' => $bookmarks,
            'footnotes' => $footnotes,
            'endnotes' => $endnotes,
            'comments' => $comments,
            'embeddedObjects' => $embeddedObjects,
            'macroProjects' => $macroProjects,
        ];

        return [
            'document' => new AstNode('document', $attrs, $this->paragraphNodes(
                $textResult['text'],
                $bookmarks,
                array_merge($footnotes, $endnotes, $comments)
            )),
            'metadata' => $metadata,
            'streams' => $compoundFile->streamNames(),
            'streamDirectory' => $streamDirectory,
            'directoryEntries' => $directoryEntries,
            'fib' => $fib + ['textSource' => $textResult['source']],
            'styles' => $styles,
            'formattingRuns' => $formattingRuns,
            'sections' => $sections,
            'bookmarks' => $bookmarks,
            'footnotes' => $footnotes,
            'endnotes' => $endnotes,
            'comments' => $comments,
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
        $languageId = self::u16($wordDocument, 6);
        $pnNext = self::u16($wordDocument, 8);
        $nFibBack = self::u16($wordDocument, 12);
        $lKey = self::u32($wordDocument, 14);
        $fcMin = self::u32($wordDocument, 24);
        $fcMac = self::u32($wordDocument, 28);
        $fibRgLw97 = $this->fibRgLw97ReviewMetadata($wordDocument);

        $fib = [
            'wIdent' => $wIdent,
            'nFib' => self::u16($wordDocument, 2),
            'nFibBack' => $nFibBack,
            'languageId' => $languageId,
            'languageTag' => $this->legacyLanguageTag($languageId),
            'pnNext' => $pnNext,
            'lKey' => $lKey,
            'flags' => $flags,
            'flagNames' => $this->fibFlagNames($flags),
            'quickSaveCount' => ($flags >> 4) & 0x0f,
            'fcMin' => $fcMin,
            'fcMac' => $fcMac,
            'tableStream' => ($flags & 0x0200) !== 0 ? '1Table' : '0Table',
            'template' => ($flags & 0x0001) !== 0,
            'glossary' => ($flags & 0x0002) !== 0,
            'complex' => ($flags & 0x0004) !== 0,
            'hasPictures' => ($flags & 0x0008) !== 0,
            'encrypted' => ($flags & 0x0100) !== 0,
            'readOnlyRecommended' => ($flags & 0x0400) !== 0,
            'writeReservation' => ($flags & 0x0800) !== 0,
            'extendedCharacters' => ($flags & 0x1000) !== 0,
            'loadOverride' => ($flags & 0x2000) !== 0,
            'farEast' => ($flags & 0x4000) !== 0,
            'obfuscated' => ($flags & 0x8000) !== 0,
        ];
        if ($fibRgLw97 !== []) {
            $fib['fibRgLw97'] = $fibRgLw97;
        }

        return $fib;
    }

    /**
     * @param array<string,mixed> $fib
     * @return array<string,mixed>
     */
    private function fibBaseReviewMetadata(array $fib): array
    {
        $metadata = [
            'nFib' => (int) ($fib['nFib'] ?? 0),
            'nFibBack' => (int) ($fib['nFibBack'] ?? 0),
            'languageId' => (int) ($fib['languageId'] ?? 0),
            'pnNext' => (int) ($fib['pnNext'] ?? 0),
            'tableStream' => (string) ($fib['tableStream'] ?? ''),
            'quickSaveCount' => (int) ($fib['quickSaveCount'] ?? 0),
            'flags' => is_array($fib['flagNames'] ?? null) ? $fib['flagNames'] : [],
            'template' => ($fib['template'] ?? false) === true,
            'glossary' => ($fib['glossary'] ?? false) === true,
            'complex' => ($fib['complex'] ?? false) === true,
            'hasPictures' => ($fib['hasPictures'] ?? false) === true,
            'readOnlyRecommended' => ($fib['readOnlyRecommended'] ?? false) === true,
            'writeReservation' => ($fib['writeReservation'] ?? false) === true,
            'extendedCharacters' => ($fib['extendedCharacters'] ?? false) === true,
            'loadOverride' => ($fib['loadOverride'] ?? false) === true,
            'farEast' => ($fib['farEast'] ?? false) === true,
        ];

        if (($fib['languageTag'] ?? null) !== null) {
            $metadata['languageTag'] = (string) $fib['languageTag'];
        }
        if (isset($fib['fibRgLw97']) && is_array($fib['fibRgLw97'])) {
            $metadata['fibRgLw97'] = $fib['fibRgLw97'];
        }

        return $metadata;
    }

    /**
     * @return array<string,mixed>
     */
    private function fibRgLw97ReviewMetadata(string $wordDocument): array
    {
        if (strlen($wordDocument) < 0x006c) {
            return [];
        }

        $reserved3 = self::signed32(self::u32($wordDocument, self::FIB_RGLW97_RESERVED3));
        if ($reserved3 !== 0) {
            throw new \RuntimeException('Legacy DOC FibRgLw97 reserved3 must be zero');
        }

        $record = [
            'cbMac' => self::u32($wordDocument, self::FIB_RGLW97_CB_MAC),
        ];
        if ($record['cbMac'] > strlen($wordDocument)) {
            throw new \RuntimeException('Legacy DOC FibRgLw97 cbMac points outside WordDocument');
        }

        $supplementalCharacters = 0;
        foreach (self::FIB_RGLW97_CCP_FIELDS as $field => $offset) {
            $value = self::signed32(self::u32($wordDocument, $offset));
            if ($value < 0) {
                throw new \RuntimeException('Legacy DOC FibRgLw97 ' . $field . ' must not be negative');
            }
            $record[$field] = $value;
            if ($field !== 'ccpText') {
                $supplementalCharacters += $value;
            }
        }

        $declaresCpCounts = (int) $record['ccpText'] > 0 || $supplementalCharacters > 0;
        if (!$declaresCpCounts && (int) $record['cbMac'] === 0) {
            return [];
        }

        $record['supplementalSubdocumentCharacters'] = $supplementalCharacters;
        $record['hasSupplementalSubdocuments'] = $supplementalCharacters > 0;
        if ($declaresCpCounts) {
            $record['pieceTableExpectedLastCp'] = (int) $record['ccpText']
                + ($supplementalCharacters > 0 ? 1 : 0)
                + $supplementalCharacters;
            $record['subdocuments'] = $this->fibRgLw97SubdocumentRanges($record);
        }

        return $record;
    }

    /**
     * @param array<string,mixed> $fibRgLw97
     * @return list<array{type:string,startCp:int,endCp:int,characterCount:int}>
     */
    private function fibRgLw97SubdocumentRanges(array $fibRgLw97): array
    {
        $mainCount = (int) ($fibRgLw97['ccpText'] ?? 0);
        $ranges = [[
            'type' => 'main',
            'startCp' => 0,
            'endCp' => $mainCount,
            'characterCount' => $mainCount,
        ]];

        $cursor = $mainCount;
        $hasSupplemental = ($fibRgLw97['hasSupplementalSubdocuments'] ?? false) === true;
        if ($hasSupplemental) {
            $cursor++;
        }
        foreach (self::FIB_RGLW97_SUBDOCUMENT_TYPES as $field => $type) {
            $count = (int) ($fibRgLw97[$field] ?? 0);
            if ($count === 0) {
                continue;
            }

            $ranges[] = [
                'type' => $type,
                'startCp' => $cursor,
                'endCp' => $cursor + $count,
                'characterCount' => $count,
            ];
            $cursor += $count;
        }

        return $ranges;
    }

    /**
     * @return list<string>
     */
    private function fibFlagNames(int $flags): array
    {
        $map = [
            0x0001 => 'template',
            0x0002 => 'glossary',
            0x0004 => 'complex',
            0x0008 => 'hasPictures',
            0x0100 => 'encrypted',
            0x0200 => 'tableStream1',
            0x0400 => 'readOnlyRecommended',
            0x0800 => 'writeReservation',
            0x1000 => 'extendedCharacters',
            0x2000 => 'loadOverride',
            0x4000 => 'farEast',
            0x8000 => 'obfuscated',
        ];

        $names = [];
        foreach ($map as $bit => $name) {
            if (($flags & $bit) !== 0) {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function legacyLanguageTag(int $languageId): ?string
    {
        return match ($languageId) {
            0x0404 => 'zh-TW',
            0x0407 => 'de-DE',
            0x0409 => 'en-US',
            0x040c => 'fr-FR',
            0x0410 => 'it-IT',
            0x0411 => 'ja-JP',
            0x0412 => 'ko-KR',
            0x0413 => 'nl-NL',
            0x0416 => 'pt-BR',
            0x0419 => 'ru-RU',
            0x0804 => 'zh-CN',
            0x0809 => 'en-GB',
            0x0816 => 'pt-PT',
            0x0c0a => 'es-ES',
            default => null,
        };
    }

    /**
     * @return array{text:string,source:string}
     */
    private function extractText(string $wordDocument, ?string $tableStream, ?array $fib = null): array
    {
        $fib ??= $this->readFib($wordDocument);
        if ($tableStream !== null) {
            $pieceText = $this->extractPieceTableText(
                $wordDocument,
                $tableStream,
                is_array($fib['fibRgLw97'] ?? null) ? $fib['fibRgLw97'] : []
            );
            if ($pieceText !== null) {
                return [
                    'text' => $pieceText,
                    'source' => 'piece-table',
                ];
            }
        }

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

    /**
     * @param array<string,mixed> $fibRgLw97
     */
    private function extractPieceTableText(string $wordDocument, string $tableStream, array $fibRgLw97): ?string
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

            return $this->parseClx(substr($tableStream, $fcClx, $lcbClx), $wordDocument, $fibRgLw97);
        }

        return null;
    }

    /**
     * @param array<string,mixed> $fibRgLw97
     */
    private function parseClx(string $clx, string $wordDocument, array $fibRgLw97): string
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

            return $this->parsePlcPcd(substr($clx, $cursor, $pieceTableLength), $wordDocument, $fibRgLw97);
        }

        throw new \RuntimeException('Legacy DOC Clx does not contain a piece table');
    }

    /**
     * @param array<string,mixed> $fibRgLw97
     */
    private function parsePlcPcd(string $plcPcd, string $wordDocument, array $fibRgLw97): string
    {
        $length = strlen($plcPcd);
        if ($length < 4 || ($length - 4) % 12 !== 0) {
            throw new \RuntimeException('Legacy DOC piece table has an invalid PlcPcd length');
        }

        $pieceCount = intdiv($length - 4, 12);
        $cpOffsets = [];
        $previousCp = null;
        for ($index = 0; $index <= $pieceCount; $index++) {
            $cp = self::u32($plcPcd, $index * 4);
            if ($previousCp !== null && $cp <= $previousCp) {
                throw new \RuntimeException('Legacy DOC piece table contains duplicate or unsorted CPs');
            }
            $previousCp = $cp;
            $cpOffsets[] = $cp;
        }

        $expectedLastCp = isset($fibRgLw97['pieceTableExpectedLastCp'])
            ? (int) $fibRgLw97['pieceTableExpectedLastCp']
            : null;
        if ($expectedLastCp !== null && $cpOffsets[$pieceCount] !== $expectedLastCp) {
            throw new \RuntimeException('Legacy DOC piece table final CP does not match FibRgLw97 subdocument counts');
        }

        $mainTextCpLimit = isset($fibRgLw97['ccpText']) && (
            (int) $fibRgLw97['ccpText'] > 0
            || ($fibRgLw97['hasSupplementalSubdocuments'] ?? false) === true
        )
            ? (int) $fibRgLw97['ccpText']
            : null;
        if ($mainTextCpLimit !== null && $mainTextCpLimit > $cpOffsets[$pieceCount]) {
            throw new \RuntimeException('Legacy DOC FibRgLw97 main-text CP count exceeds the piece table');
        }

        $pcdOffset = ($pieceCount + 1) * 4;
        $text = '';
        for ($index = 0; $index < $pieceCount; $index++) {
            $characters = $cpOffsets[$index + 1] - $cpOffsets[$index];
            if ($characters <= 0) {
                continue;
            }
            if ($mainTextCpLimit !== null) {
                if ($cpOffsets[$index] >= $mainTextCpLimit) {
                    break;
                }
                $characters = min($characters, $mainTextCpLimit - $cpOffsets[$index]);
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
        if ($type === 'comment') {
            $attributes = [
                'data-legacy-doc-comment-index' => (string) ((int) ($noteReference['index'] ?? 0)),
                'data-legacy-doc-comment-reference-cp' => (string) ((int) ($noteReference['referenceCp'] ?? 0)),
                'data-legacy-doc-comment-text-start-cp' => (string) ((int) ($noteReference['textStartCp'] ?? 0)),
                'data-legacy-doc-comment-text-end-cp' => (string) ((int) ($noteReference['textEndCp'] ?? 0)),
                'data-legacy-doc-comment-author-index' => (string) ((int) ($noteReference['authorIndex'] ?? 0)),
            ];
            if (($noteReference['authorInitials'] ?? '') !== '') {
                $attributes['data-legacy-doc-comment-author-initials'] = (string) $noteReference['authorInitials'];
            }
            if (isset($noteReference['bookmarkTag'])) {
                $attributes['data-legacy-doc-comment-bookmark-tag'] = (string) ((int) $noteReference['bookmarkTag']);
            }

            return [
                'classes' => ['legacy-doc-comment-ref'],
                'attributes' => $attributes,
            ];
        }

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
            if (($entry['clsid'] ?? null) !== null) {
                $record['clsid'] = (string) $entry['clsid'];
            }
            if (($entry['stateBits'] ?? null) !== null) {
                $record['stateBits'] = (int) $entry['stateBits'];
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

            if ($role === 'object-info') {
                $format = $this->embeddedObjectInfoFormat($compoundFile->readStream($path));
                if ($format !== null) {
                    $objects[$storagePath]['transmissionFormat'] = $format;
                }
            }
            if ($role === 'compound-object') {
                $compoundObject = $this->embeddedCompoundObjectMetadata($compoundFile->readStream($path));
                $stream['compoundObject'] = $compoundObject;
                if (($compoundObject['displayName'] ?? '') !== '') {
                    $objects[$storagePath]['compoundObjectDisplayNames'][] = (string) $compoundObject['displayName'];
                }
                if (is_array($compoundObject['clipboardFormat'] ?? null)) {
                    $clipboardFormat = $compoundObject['clipboardFormat'];
                    if (($clipboardFormat['name'] ?? '') !== '') {
                        $objects[$storagePath]['compoundObjectClipboardFormats'][] = (string) $clipboardFormat['name'];
                    } elseif (isset($clipboardFormat['code'])) {
                        $objects[$storagePath]['compoundObjectClipboardFormats'][] = 'standard:' . (string) $clipboardFormat['code'];
                    }
                }
                foreach (($compoundObject['diagnostics'] ?? []) as $diagnostic) {
                    if (!is_array($diagnostic)) {
                        continue;
                    }
                    $objects[$storagePath]['diagnostics'][] = ['stream' => $path] + $diagnostic;
                }
            }
            if ($role === 'native-data') {
                $oleNative = $this->embeddedOleNativeMetadata($compoundFile->readStream($path));
                $stream['oleNative'] = $oleNative;
                if (($oleNative['label'] ?? '') !== '') {
                    $objects[$storagePath]['nativeLabels'][] = (string) $oleNative['label'];
                }
                if (($oleNative['sourcePath'] ?? '') !== '') {
                    $objects[$storagePath]['nativeSourcePaths'][] = (string) $oleNative['sourcePath'];
                }
                if (($oleNative['temporaryPath'] ?? '') !== '') {
                    $objects[$storagePath]['nativeTemporaryPaths'][] = (string) $oleNative['temporaryPath'];
                }
                if (isset($oleNative['nativeDataBytes']) && is_int($oleNative['nativeDataBytes'])) {
                    $objects[$storagePath]['nativeDataBytes'] = (int) ($objects[$storagePath]['nativeDataBytes'] ?? 0)
                        + $oleNative['nativeDataBytes'];
                }
                foreach (($oleNative['diagnostics'] ?? []) as $diagnostic) {
                    if (!is_array($diagnostic)) {
                        continue;
                    }
                    $objects[$storagePath]['diagnostics'][] = ['stream' => $path] + $diagnostic;
                }
            }

            $objects[$storagePath]['streams'][] = $stream;
        }

        $result = array_values($objects);
        foreach ($result as &$object) {
            foreach (['compoundObjectDisplayNames', 'compoundObjectClipboardFormats', 'nativeLabels', 'nativeSourcePaths', 'nativeTemporaryPaths'] as $field) {
                if (!isset($object[$field]) || !is_array($object[$field])) {
                    continue;
                }
                $object[$field] = array_values(array_unique(array_map(
                    static fn (mixed $value): string => (string) $value,
                    $object[$field]
                )));
            }
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
    private function embeddedCompoundObjectMetadata(string $bytes): array
    {
        $metadata = ['canExposeBytes' => false];
        $diagnostics = [];
        $length = strlen($bytes);
        if ($length < 28) {
            $metadata['diagnostics'] = [[
                'code' => 'truncated-compobj-header',
                'message' => 'CompObj stream is too short to contain the 28-byte header',
            ]];

            return $metadata;
        }

        $cursor = 28;
        $ansiUserType = $this->readLengthPrefixedAnsiString($bytes, $cursor);
        if ($ansiUserType === null) {
            $metadata['diagnostics'] = [[
                'code' => 'truncated-compobj-ansi-user-type',
                'message' => 'CompObj stream is missing the ANSI display name',
            ]];

            return $metadata;
        }
        if ($ansiUserType !== '') {
            $metadata['ansiUserType'] = $ansiUserType;
            $metadata['displayName'] = $ansiUserType;
        }

        $ansiClipboard = $this->readClipboardFormatOrAnsiString($bytes, $cursor, $diagnostics);
        if ($ansiClipboard !== null) {
            $metadata['ansiClipboardFormat'] = $ansiClipboard;
            $metadata['clipboardFormat'] = $ansiClipboard;
        } elseif ($diagnostics !== []) {
            $metadata['diagnostics'] = $diagnostics;

            return $metadata;
        }

        if ($cursor >= $length) {
            if ($diagnostics !== []) {
                $metadata['diagnostics'] = $diagnostics;
            }

            return $metadata;
        }

        $reserved = $this->readLengthPrefixedAnsiStringWithSize($bytes, $cursor);
        if ($reserved === null) {
            $diagnostics[] = [
                'code' => 'truncated-compobj-reserved-ansi',
                'message' => 'CompObj stream has a truncated reserved ANSI string',
            ];
            $metadata['diagnostics'] = $diagnostics;

            return $metadata;
        }
        $cursor += $reserved['bytes'];
        if ($reserved['declaredCharacters'] === 0 || $reserved['declaredCharacters'] > 0x28 || $cursor + 4 > $length) {
            if ($diagnostics !== []) {
                $metadata['diagnostics'] = $diagnostics;
            }

            return $metadata;
        }

        $unicodeMarker = self::u32($bytes, $cursor);
        $cursor += 4;
        if ($unicodeMarker !== 0x71b239f4) {
            $metadata['unicodeMarker'] = $unicodeMarker;
            if ($diagnostics !== []) {
                $metadata['diagnostics'] = $diagnostics;
            }

            return $metadata;
        }
        $metadata['unicodeMarker'] = $unicodeMarker;

        $unicodeUserType = $this->readLengthPrefixedUnicodeString($bytes, $cursor);
        if ($unicodeUserType === null) {
            $diagnostics[] = [
                'code' => 'truncated-compobj-unicode-user-type',
                'message' => 'CompObj stream has a truncated Unicode display name',
            ];
            $metadata['diagnostics'] = $diagnostics;

            return $metadata;
        }
        if ($unicodeUserType !== '') {
            $metadata['unicodeUserType'] = $unicodeUserType;
            $metadata['displayName'] = $unicodeUserType;
        }

        $unicodeClipboard = $this->readClipboardFormatOrUnicodeString($bytes, $cursor, $diagnostics);
        if ($unicodeClipboard !== null) {
            $metadata['unicodeClipboardFormat'] = $unicodeClipboard;
            $metadata['clipboardFormat'] = $unicodeClipboard;
        }

        if ($diagnostics !== []) {
            $metadata['diagnostics'] = $diagnostics;
        }

        return $metadata;
    }

    private function readLengthPrefixedAnsiString(string $bytes, int &$cursor): ?string
    {
        $value = $this->readLengthPrefixedAnsiStringWithSize($bytes, $cursor);
        if ($value === null) {
            return null;
        }

        $cursor += $value['bytes'];

        return $value['value'];
    }

    /**
     * @return array{value:string,bytes:int,declaredCharacters:int}|null
     */
    private function readLengthPrefixedAnsiStringWithSize(string $bytes, int $offset): ?array
    {
        if ($offset + 4 > strlen($bytes)) {
            return null;
        }

        $characters = self::u32($bytes, $offset);
        if ($characters === 0) {
            return [
                'value' => '',
                'bytes' => 4,
                'declaredCharacters' => 0,
            ];
        }
        if ($characters > 0x100000 || $offset + 4 + $characters > strlen($bytes)) {
            return null;
        }

        $raw = substr($bytes, $offset + 4, $characters);
        $value = $this->decodeCodePageString($raw, 1252);
        $clean = preg_replace('/[\x00-\x08\x0e-\x1f]/u', '', $value);

        return [
            'value' => is_string($clean) ? $clean : $value,
            'bytes' => 4 + $characters,
            'declaredCharacters' => $characters,
        ];
    }

    private function readLengthPrefixedUnicodeString(string $bytes, int &$cursor): ?string
    {
        if ($cursor + 4 > strlen($bytes)) {
            return null;
        }

        $byteLength = self::u32($bytes, $cursor);
        $cursor += 4;
        if ($byteLength === 0) {
            return '';
        }
        if (($byteLength % 2) !== 0 || $byteLength > 0x200000 || $cursor + $byteLength > strlen($bytes)) {
            return null;
        }

        $value = rtrim($this->decodeUtf16Le(substr($bytes, $cursor, $byteLength)), "\0");
        $cursor += $byteLength;

        return $value;
    }

    /**
     * @param list<array<string,mixed>> $diagnostics
     * @return array<string,mixed>|null
     */
    private function readClipboardFormatOrAnsiString(string $bytes, int &$cursor, array &$diagnostics): ?array
    {
        if ($cursor + 4 > strlen($bytes)) {
            $diagnostics[] = [
                'code' => 'truncated-compobj-ansi-clipboard-format',
                'message' => 'CompObj stream is missing the ANSI clipboard format marker',
            ];

            return null;
        }

        $marker = self::u32($bytes, $cursor);
        $cursor += 4;
        if ($marker === 0) {
            return [
                'kind' => 'none',
            ];
        }
        if ($marker === 0xffffffff || $marker === 0xfffffffe) {
            if ($cursor + 4 > strlen($bytes)) {
                $diagnostics[] = [
                    'code' => 'truncated-compobj-ansi-standard-clipboard-format',
                    'message' => 'CompObj stream has a truncated ANSI standard clipboard format identifier',
                ];

                return null;
            }

            $code = self::u32($bytes, $cursor);
            $cursor += 4;

            return $this->standardClipboardFormat($code);
        }
        if ($marker > 0x190 || $cursor + $marker > strlen($bytes)) {
            $diagnostics[] = [
                'code' => 'invalid-compobj-ansi-clipboard-format',
                'message' => 'CompObj ANSI registered clipboard format length is invalid',
                'declaredCharacters' => $marker,
            ];

            return null;
        }

        $value = $this->decodeCodePageString(substr($bytes, $cursor, $marker), 1252);
        $cursor += $marker;

        return [
            'kind' => 'registered',
            'name' => $value,
        ];
    }

    /**
     * @param list<array<string,mixed>> $diagnostics
     * @return array<string,mixed>|null
     */
    private function readClipboardFormatOrUnicodeString(string $bytes, int &$cursor, array &$diagnostics): ?array
    {
        if ($cursor + 4 > strlen($bytes)) {
            $diagnostics[] = [
                'code' => 'truncated-compobj-unicode-clipboard-format',
                'message' => 'CompObj stream is missing the Unicode clipboard format marker',
            ];

            return null;
        }

        $marker = self::u32($bytes, $cursor);
        $cursor += 4;
        if ($marker === 0) {
            return [
                'kind' => 'none',
            ];
        }
        if ($marker === 0xffffffff || $marker === 0xfffffffe) {
            if ($cursor + 4 > strlen($bytes)) {
                $diagnostics[] = [
                    'code' => 'truncated-compobj-unicode-standard-clipboard-format',
                    'message' => 'CompObj stream has a truncated Unicode standard clipboard format identifier',
                ];

                return null;
            }

            $code = self::u32($bytes, $cursor);
            $cursor += 4;

            return $this->standardClipboardFormat($code);
        }
        if ($marker > 0x190) {
            $diagnostics[] = [
                'code' => 'invalid-compobj-unicode-clipboard-format',
                'message' => 'CompObj Unicode registered clipboard format length is invalid',
                'declaredCharacters' => $marker,
            ];

            return null;
        }

        $byteLength = $marker * 2;
        if ($cursor + $byteLength > strlen($bytes)) {
            $diagnostics[] = [
                'code' => 'truncated-compobj-unicode-clipboard-format-name',
                'message' => 'CompObj Unicode registered clipboard format name is truncated',
                'declaredCharacters' => $marker,
            ];

            return null;
        }

        $value = rtrim($this->decodeUtf16Le(substr($bytes, $cursor, $byteLength)), "\0");
        $cursor += $byteLength;

        return [
            'kind' => 'registered',
            'name' => $value,
        ];
    }

    /**
     * @return array{kind:string,code:int,name:string}
     */
    private function standardClipboardFormat(int $code): array
    {
        return [
            'kind' => 'standard',
            'code' => $code,
            'name' => match ($code) {
                1 => 'CF_TEXT',
                2 => 'CF_BITMAP',
                3 => 'CF_METAFILEPICT',
                4 => 'CF_SYLK',
                5 => 'CF_DIF',
                6 => 'CF_TIFF',
                7 => 'CF_OEMTEXT',
                8 => 'CF_DIB',
                9 => 'CF_PALETTE',
                10 => 'CF_PENDATA',
                11 => 'CF_RIFF',
                12 => 'CF_WAVE',
                13 => 'CF_UNICODETEXT',
                14 => 'CF_ENHMETAFILE',
                15 => 'CF_HDROP',
                16 => 'CF_LOCALE',
                17 => 'CF_DIBV5',
                default => 'standard:' . (string) $code,
            },
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function embeddedOleNativeMetadata(string $bytes): array
    {
        $metadata = ['canExposeBytes' => false];
        $diagnostics = [];
        $length = strlen($bytes);
        if ($length < 4) {
            $metadata['diagnostics'] = [[
                'code' => 'truncated-ole-native-size',
                'message' => 'Ole10Native stream is too short to contain the declared payload size',
            ]];

            return $metadata;
        }

        $declaredPayloadBytes = self::u32($bytes, 0);
        $metadata['declaredPayloadBytes'] = $declaredPayloadBytes;
        if ($declaredPayloadBytes > $length - 4) {
            $diagnostics[] = [
                'code' => 'ole-native-declared-size-exceeds-stream',
                'message' => 'Ole10Native declared payload size exceeds the CFB stream length',
                'declaredPayloadBytes' => $declaredPayloadBytes,
                'availablePayloadBytes' => $length - 4,
            ];
        }

        $cursor = 4;
        if ($cursor + 2 > $length) {
            $diagnostics[] = [
                'code' => 'truncated-ole-native-flags',
                'message' => 'Ole10Native stream is missing the native flags field',
            ];
            $metadata['diagnostics'] = $diagnostics;

            return $metadata;
        }

        $metadata['flags'] = self::u16($bytes, $cursor);
        $cursor += 2;

        $label = $this->readOleNativeAnsiString($bytes, $cursor);
        if ($label === null) {
            $diagnostics[] = [
                'code' => 'truncated-ole-native-label',
                'message' => 'Ole10Native stream is missing a null-terminated display label',
            ];
            $metadata['diagnostics'] = $diagnostics;

            return $metadata;
        }
        if ($label !== '') {
            $metadata['label'] = $label;
        }

        $sourcePath = $this->readOleNativeAnsiString($bytes, $cursor);
        if ($sourcePath === null) {
            $diagnostics[] = [
                'code' => 'truncated-ole-native-source-path',
                'message' => 'Ole10Native stream is missing a null-terminated source path',
            ];
            $metadata['diagnostics'] = $diagnostics;

            return $metadata;
        }
        if ($sourcePath !== '') {
            $metadata['sourcePath'] = $sourcePath;
        }

        if ($cursor + 4 > $length) {
            $diagnostics[] = [
                'code' => 'truncated-ole-native-secondary-flags',
                'message' => 'Ole10Native stream is missing secondary flags before the command path',
            ];
            $metadata['diagnostics'] = $diagnostics;

            return $metadata;
        }

        $secondaryFlags = self::u16($bytes, $cursor);
        $unknownFlags = self::u16($bytes, $cursor + 2);
        if ($secondaryFlags !== 0) {
            $metadata['secondaryFlags'] = $secondaryFlags;
        }
        if ($unknownFlags !== 0) {
            $metadata['unknownFlags'] = $unknownFlags;
        }
        $cursor += 4;

        $temporaryPath = $this->readOleNativeAnsiString($bytes, $cursor);
        if ($temporaryPath === null) {
            $diagnostics[] = [
                'code' => 'truncated-ole-native-temporary-path',
                'message' => 'Ole10Native stream is missing a null-terminated temporary path',
            ];
            $metadata['diagnostics'] = $diagnostics;

            return $metadata;
        }
        if ($temporaryPath !== '') {
            $metadata['temporaryPath'] = $temporaryPath;
        }

        if ($cursor + 4 > $length) {
            $diagnostics[] = [
                'code' => 'truncated-ole-native-data-size',
                'message' => 'Ole10Native stream is missing the embedded native-data size',
            ];
            $metadata['diagnostics'] = $diagnostics;

            return $metadata;
        }

        $nativeDataBytes = self::u32($bytes, $cursor);
        $cursor += 4;
        $metadata['nativeDataBytes'] = $nativeDataBytes;
        $availableNativeDataBytes = max(0, $length - $cursor);
        if ($nativeDataBytes > $availableNativeDataBytes) {
            $metadata['availableNativeDataBytes'] = $availableNativeDataBytes;
            $diagnostics[] = [
                'code' => 'ole-native-data-size-exceeds-stream',
                'message' => 'Ole10Native embedded native-data size exceeds the remaining stream bytes',
                'nativeDataBytes' => $nativeDataBytes,
                'availableNativeDataBytes' => $availableNativeDataBytes,
            ];
        }

        if ($diagnostics !== []) {
            $metadata['diagnostics'] = $diagnostics;
        }

        return $metadata;
    }

    private function readOleNativeAnsiString(string $bytes, int &$cursor): ?string
    {
        $end = strpos($bytes, "\0", $cursor);
        if ($end === false) {
            return null;
        }

        $value = $this->decodeCodePageString(substr($bytes, $cursor, $end - $cursor), 1252);
        $cursor = $end + 1;
        $clean = preg_replace('/[\x00-\x08\x0e-\x1f]/u', '', $value);

        return is_string($clean) ? $clean : $value;
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
    private function commentReferenceReport(string $wordDocument, ?string $tableStream, string $text): array
    {
        if ($tableStream === null || strlen($wordDocument) < self::FIB_LCB_PLCFAND_TXT + 4) {
            return [];
        }

        $fib = $this->readFib($wordDocument);
        if ((int) $fib['fcMin'] > 0 && self::FIB_LCB_PLCFAND_TXT + 4 > (int) $fib['fcMin']) {
            return [];
        }

        $fcRef = self::u32($wordDocument, self::FIB_FC_PLCFAND_REF);
        $lcbRef = self::u32($wordDocument, self::FIB_LCB_PLCFAND_REF);
        $fcTxt = self::u32($wordDocument, self::FIB_FC_PLCFAND_TXT);
        $lcbTxt = self::u32($wordDocument, self::FIB_LCB_PLCFAND_TXT);
        if ($lcbRef === 0 && $lcbTxt === 0) {
            return [];
        }
        if ($lcbRef === 0 || $lcbTxt === 0) {
            throw new \RuntimeException('Legacy DOC comment reference PLC is present without matching text-range PLC');
        }

        $references = $this->parseCommentReferencePlc(
            $this->tableStreamSlice($tableStream, $fcRef, $lcbRef, 'comment reference PLC')
        );
        $ranges = $this->parseNoteTextPlc(
            $this->tableStreamSlice($tableStream, $fcTxt, $lcbTxt, 'comment text PLC'),
            'comment'
        );
        if (count($references) !== count($ranges)) {
            throw new \RuntimeException('Legacy DOC comment reference and text PLCs do not contain parallel counts');
        }

        $characters = $this->unicodeCharacters($text);
        $textLength = count($characters);
        $comments = [];
        foreach ($references as $index => $reference) {
            $referenceCp = (int) $reference['referenceCp'];
            if ($referenceCp < 0 || $referenceCp >= $textLength) {
                throw new \RuntimeException('Legacy DOC comment reference CP points outside the extracted main text');
            }
            if (($characters[$referenceCp] ?? '') !== "\x05") {
                throw new \RuntimeException('Legacy DOC comment reference is missing the special annotation reference character');
            }

            $range = $ranges[$index];
            $authorInitials = (string) ($reference['authorInitials'] ?? '');
            $comments[] = [
                'type' => 'comment',
                'index' => $index + 1,
                'referenceCp' => $referenceCp,
                'authorInitials' => $authorInitials,
                'authorIndex' => (int) $reference['authorIndex'],
                'bookmarkTag' => (int) $reference['bookmarkTag'],
                'lengthZeroRange' => (int) $reference['bookmarkTag'] === -1,
                'marker' => $authorInitials !== '' ? $authorInitials : (string) ($index + 1),
                'textStartCp' => (int) $range['startCp'],
                'textEndCp' => (int) $range['endCp'],
                'canAnchor' => true,
            ];
        }

        return $comments;
    }

    /**
     * @return list<array{referenceCp:int,authorInitials:string,authorIndex:int,bookmarkTag:int}>
     */
    private function parseCommentReferencePlc(string $bytes): array
    {
        $length = strlen($bytes);
        if ($length < 38 || (($length - 4) % 34) !== 0) {
            throw new \RuntimeException('Legacy DOC comment reference PLC has an invalid length');
        }

        $count = intdiv($length - 4, 34);
        $dataOffset = ($count + 1) * 4;
        $entries = [];
        $previousCp = null;
        $seenCps = [];
        for ($index = 0; $index < $count; $index++) {
            $referenceCp = self::u32($bytes, $index * 4);
            if ($previousCp !== null && $referenceCp <= $previousCp) {
                throw new \RuntimeException('Legacy DOC comment reference PLC contains duplicate or unsorted CPs');
            }
            if (isset($seenCps[$referenceCp])) {
                throw new \RuntimeException('Legacy DOC comment reference PLC contains duplicate CPs');
            }
            $previousCp = $referenceCp;
            $seenCps[$referenceCp] = true;

            $recordOffset = $dataOffset + ($index * 30);
            $unusedBits = self::u16($bytes, $recordOffset + 22);
            $unusedFlags = self::u16($bytes, $recordOffset + 24);
            if ($unusedBits !== 0 || $unusedFlags !== 0) {
                throw new \RuntimeException('Legacy DOC comment reference descriptor contains nonzero reserved fields');
            }

            $bookmarkTag = self::signed32(self::u32($bytes, $recordOffset + 26));
            $entries[] = [
                'referenceCp' => $referenceCp,
                'authorInitials' => $this->readLpxCharBuffer9(substr($bytes, $recordOffset, 20)),
                'authorIndex' => self::u16($bytes, $recordOffset + 20),
                'bookmarkTag' => $bookmarkTag,
            ];
        }

        $ignoredCp = self::u32($bytes, $count * 4);
        if ($previousCp !== null && $ignoredCp <= $previousCp) {
            throw new \RuntimeException('Legacy DOC comment reference PLC final CP is not after the last reference CP');
        }

        return $entries;
    }

    private function readLpxCharBuffer9(string $bytes): string
    {
        if (strlen($bytes) !== 20) {
            throw new \RuntimeException('Legacy DOC LPXCharBuffer9 is truncated');
        }

        $characters = self::u16($bytes, 0);
        if ($characters > 9) {
            throw new \RuntimeException('Legacy DOC LPXCharBuffer9 declares too many characters');
        }

        return $characters === 0 ? '' : $this->decodeUtf16Le(substr($bytes, 2, $characters * 2));
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function styleSheetReport(string $wordDocument, ?string $tableStream): array
    {
        if ($tableStream === null || strlen($wordDocument) < self::FIB_LCB_STSHF + 4) {
            return [];
        }

        $fcStshf = self::u32($wordDocument, self::FIB_FC_STSHF);
        $lcbStshf = self::u32($wordDocument, self::FIB_LCB_STSHF);
        if ($lcbStshf === 0) {
            return [];
        }

        return $this->parseStyleSheet($this->tableStreamSlice($tableStream, $fcStshf, $lcbStshf, 'STSH stylesheet'));
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function parseStyleSheet(string $bytes): array
    {
        $length = strlen($bytes);
        if ($length < 20) {
            throw new \RuntimeException('Legacy DOC stylesheet is too short to contain an LPStshi header');
        }

        $cbStshi = self::u16($bytes, 0);
        $stylesOffset = 2 + $cbStshi;
        if ($cbStshi < 18 || $stylesOffset > $length) {
            throw new \RuntimeException('Legacy DOC stylesheet LPStshi header length is invalid');
        }
        if (($stylesOffset % 2) !== 0) {
            $stylesOffset++;
        }

        $styleCount = self::u16($bytes, 2);
        $stdfBaseBytes = self::u16($bytes, 4);
        $styleFlags = self::u16($bytes, 6);
        $fixedStyleCount = self::u16($bytes, 10);
        if ($styleCount < 0x000f || $styleCount >= 0x0ffe) {
            throw new \RuntimeException('Legacy DOC stylesheet contains an invalid style count');
        }
        if (!in_array($stdfBaseBytes, [0x000a, 0x0012], true)) {
            throw new \RuntimeException('Legacy DOC stylesheet uses an unsupported StdfBase length');
        }
        if (($styleFlags & 0x0001) === 0 || ($styleFlags & 0xfffe) !== 0) {
            throw new \RuntimeException('Legacy DOC stylesheet contains reserved Stshif flags');
        }
        if ($fixedStyleCount !== 0x000f) {
            throw new \RuntimeException('Legacy DOC stylesheet fixed-style count is invalid');
        }

        $styles = [];
        $nonEmptyByIstd = [];
        $seenNames = [];
        $cursor = $stylesOffset;
        for ($istd = 0; $istd < $styleCount; $istd++) {
            if ($cursor + 2 > $length) {
                throw new \RuntimeException('Legacy DOC stylesheet is truncated before all LPStd records');
            }

            $cbStd = self::u16($bytes, $cursor);
            $cursor += 2;
            if (($cbStd & 0x8000) !== 0) {
                throw new \RuntimeException('Legacy DOC stylesheet contains a negative LPStd length');
            }
            if ($cbStd === 0) {
                if (($cursor % 2) !== 0) {
                    $cursor++;
                }
                continue;
            }
            if ($cursor + $cbStd > $length) {
                throw new \RuntimeException('Legacy DOC stylesheet LPStd points outside the STSH bytes');
            }

            $style = $this->parseStyleDefinition(substr($bytes, $cursor, $cbStd), $istd, $cbStd, $stdfBaseBytes, $seenNames);
            $styles[] = $style;
            $nonEmptyByIstd[$istd] = true;
            $cursor += $cbStd;
            if (($cursor % 2) !== 0) {
                $cursor++;
            }
        }

        foreach ($styles as $style) {
            if (isset($style['basedOnIstd'])) {
                $base = (int) $style['basedOnIstd'];
                if ($base < 0 || $base >= $styleCount || !isset($nonEmptyByIstd[$base]) || $base === (int) $style['istd']) {
                    throw new \RuntimeException('Legacy DOC stylesheet contains an invalid based-on style reference');
                }
            }
        }
        $this->assertStyleInheritanceIsAcyclic($styles);

        return $styles;
    }

    /**
     * @param array<string, true> $seenNames
     * @return array<string,mixed>
     */
    private function parseStyleDefinition(string $bytes, int $istd, int $cbStd, int $stdfBaseBytes, array &$seenNames): array
    {
        if (strlen($bytes) < $stdfBaseBytes + 4 || $stdfBaseBytes < 10) {
            throw new \RuntimeException('Legacy DOC stylesheet style definition is missing its Xstz name');
        }

        $word0 = self::u16($bytes, 0);
        $word1 = self::u16($bytes, 2);
        $word2 = self::u16($bytes, 4);
        $bchUpe = self::u16($bytes, 6);
        $grfstd = self::u16($bytes, 8);
        if ($bchUpe !== $cbStd) {
            throw new \RuntimeException('Legacy DOC stylesheet style definition length mirror is invalid');
        }

        $stk = $word1 & 0x000f;
        $style = [
            'istd' => $istd,
            'type' => $this->styleTypeName($stk),
            'name' => '',
            'sti' => $word0 & 0x0fff,
            'builtIn' => ($word0 & 0x0fff) !== 0x0ffe,
            'cupx' => $word2 & 0x000f,
            'nextIstd' => $word2 >> 4,
            'cbStd' => $cbStd,
            'bchUpe' => $bchUpe,
            'grfstd' => $grfstd,
        ];

        $basedOnIstd = $word1 >> 4;
        if ($basedOnIstd !== 0x0fff) {
            $style['basedOnIstd'] = $basedOnIstd;
        }

        [$name, $bytesRead] = $this->readXstzName($bytes, $stdfBaseBytes);
        if ($name === '') {
            throw new \RuntimeException('Legacy DOC stylesheet style definition contains an empty name');
        }
        if ($stdfBaseBytes + $bytesRead > strlen($bytes)) {
            throw new \RuntimeException('Legacy DOC stylesheet style name points outside its LPStd record');
        }

        $nameParts = array_map('trim', explode(',', $name));
        if ($nameParts === [] || in_array('', $nameParts, true)) {
            throw new \RuntimeException('Legacy DOC stylesheet style definition contains an empty alias');
        }
        foreach ($nameParts as $styleName) {
            $normalized = $this->normalizedStyleName($styleName);
            if (isset($seenNames[$normalized])) {
                throw new \RuntimeException('Legacy DOC stylesheet contains duplicate style names');
            }
            $seenNames[$normalized] = true;
        }

        $style['name'] = $nameParts[0];
        if (count($nameParts) > 1) {
            $style['aliases'] = array_slice($nameParts, 1);
        }

        return $style;
    }

    private function styleTypeName(int $stk): string
    {
        return match ($stk) {
            1 => 'paragraph',
            2 => 'character',
            3 => 'table',
            4 => 'numbering',
            default => throw new \RuntimeException('Legacy DOC stylesheet contains an unsupported style type'),
        };
    }

    /**
     * @return array{0:string,1:int}
     */
    private function readXstzName(string $bytes, int $offset): array
    {
        if ($offset + 4 > strlen($bytes)) {
            throw new \RuntimeException('Legacy DOC stylesheet style name is truncated');
        }

        $characters = self::u16($bytes, $offset);
        if ($characters === 0 || $characters > 255) {
            throw new \RuntimeException('Legacy DOC stylesheet style name length is outside the supported range');
        }

        $textOffset = $offset + 2;
        $byteLength = $characters * 2;
        $terminatorOffset = $textOffset + $byteLength;
        if ($terminatorOffset + 2 > strlen($bytes)) {
            throw new \RuntimeException('Legacy DOC stylesheet style name points outside its string data');
        }
        if (self::u16($bytes, $terminatorOffset) !== 0) {
            throw new \RuntimeException('Legacy DOC stylesheet style name is missing its null terminator');
        }

        return [
            $this->decodeUtf16Le(substr($bytes, $textOffset, $byteLength)),
            2 + $byteLength + 2,
        ];
    }

    /**
     * @param list<array<string,mixed>> $styles
     */
    private function assertStyleInheritanceIsAcyclic(array $styles): void
    {
        $baseByIstd = [];
        foreach ($styles as $style) {
            if (isset($style['basedOnIstd'])) {
                $baseByIstd[(int) $style['istd']] = (int) $style['basedOnIstd'];
            }
        }

        foreach (array_keys($baseByIstd) as $istd) {
            $seen = [];
            $cursor = $istd;
            while (isset($baseByIstd[$cursor])) {
                if (isset($seen[$cursor])) {
                    throw new \RuntimeException('Legacy DOC stylesheet based-on styles form an inheritance loop');
                }
                $seen[$cursor] = true;
                $cursor = $baseByIstd[$cursor];
            }
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function formattingRunReport(string $wordDocument, ?string $tableStream): array
    {
        if ($tableStream === null || strlen($wordDocument) < self::FIB_LCB_PLCF_BTE_PAPX + 4) {
            return [];
        }

        return array_merge(
            $this->formattingTableReport(
                'paragraph',
                'PlcBtePapx',
                $wordDocument,
                $tableStream,
                self::FIB_FC_PLCF_BTE_PAPX,
                self::FIB_LCB_PLCF_BTE_PAPX
            ),
            $this->formattingTableReport(
                'character',
                'PlcBteChpx',
                $wordDocument,
                $tableStream,
                self::FIB_FC_PLCF_BTE_CHPX,
                self::FIB_LCB_PLCF_BTE_CHPX
            )
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function formattingTableReport(
        string $kind,
        string $label,
        string $wordDocument,
        string $tableStream,
        int $fcOffset,
        int $lcbOffset
    ): array {
        $fc = self::u32($wordDocument, $fcOffset);
        $lcb = self::u32($wordDocument, $lcbOffset);
        if ($lcb === 0) {
            return [];
        }
        return $this->parsePlcBte(
            $this->tableStreamSlice($tableStream, $fc, $lcb, $label),
            $kind,
            $label,
            $wordDocument
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function parsePlcBte(string $bytes, string $kind, string $label, string $wordDocument): array
    {
        $length = strlen($bytes);
        if ($length < 12 || (($length - 4) % 8) !== 0) {
            throw new \RuntimeException('Legacy DOC ' . $label . ' formatting table has an invalid length');
        }

        $runCount = intdiv($length - 4, 8);
        $fcCount = $runCount + 1;
        $fcs = [];
        $previousFc = null;
        for ($index = 0; $index < $fcCount; $index++) {
            $fc = self::u32($bytes, $index * 4);
            if ($previousFc !== null && $fc <= $previousFc) {
                throw new \RuntimeException('Legacy DOC ' . $label . ' formatting table contains duplicate or unsorted file offsets');
            }
            if ($fc > strlen($wordDocument)) {
                throw new \RuntimeException('Legacy DOC ' . $label . ' formatting table points outside WordDocument');
            }

            $previousFc = $fc;
            $fcs[] = $fc;
        }

        $pnOffset = $fcCount * 4;
        $runs = [];
        for ($index = 0; $index < $runCount; $index++) {
            $pnFkp = self::u32($bytes, $pnOffset + ($index * 4));
            $fkpPage = $pnFkp & 0x003fffff;
            $unusedBits = $pnFkp >> 22;
            $fkpByteOffset = $fkpPage * 512;
            if ($fkpByteOffset < 0 || $fkpByteOffset + 512 > strlen($wordDocument)) {
                throw new \RuntimeException('Legacy DOC ' . $label . ' formatting table points to an FKP page outside WordDocument');
            }

            $run = [
                'kind' => $kind,
                'table' => $label,
                'index' => $index + 1,
                'startFc' => $fcs[$index],
                'endFc' => $fcs[$index + 1],
                'byteLength' => $fcs[$index + 1] - $fcs[$index],
                'fkpPage' => $fkpPage,
                'fkpByteOffset' => $fkpByteOffset,
                'fkpByteCount' => 512,
                'fkpRunCount' => ord($wordDocument[$fkpByteOffset + 511]),
                'canApplyFormatting' => false,
            ];
            if ($unusedBits !== 0) {
                $run['unusedPnFkpBits'] = $unusedBits;
            }

            $runs[] = $run;
        }

        return $runs;
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

    private function normalizedStyleName(string $name): string
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
