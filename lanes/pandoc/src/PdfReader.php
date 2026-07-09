<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

use PortLibs\MarkerPDF\PdfTextExtractor;

final class PdfReader
{
    private const DEFAULT_MAX_TEXT_BYTES = 120000;
    private const DEFAULT_FAST_MODE_BYTES = 5_000_000;
    private int $lowConfidenceGeometryTableCandidates = 0;

    /**
     * @param array{maxTextBytes?: int, maxPages?: int, pdfMaxPages?: int, max_pages?: int, password?: string, pdfPassword?: string, geometryTables?: bool, pdfGeometryTables?: bool, extractGeometryTables?: bool, pdfRepairProseText?: bool, repairProseText?: bool, pdfFastTextOnly?: bool, fastTextOnly?: bool, pdfFastModeBytes?: int} $options
     */
    public function __construct(private readonly array $options = [])
    {
    }

    public function read(string $pdfBytes): AstNode
    {
        $this->lowConfidenceGeometryTableCandidates = 0;

        if (!class_exists(PdfTextExtractor::class)) {
            throw new \RuntimeException('PDF reading needs PortLibs\\MarkerPDF\\PdfTextExtractor.');
        }

        $structuralMetadata = $this->structuralMetadata($pdfBytes);
        $fastTextOnly = $this->fastTextOnlyMode($pdfBytes, $structuralMetadata);
        $extractorOptions = $this->options;
        if ($fastTextOnly && $this->pdfMaxPages() === null) {
            $extractorOptions['pdfMaxPages'] = (int) ($this->options['pdfFastMaxPages'] ?? 2);
        }
        $extractor = new PdfTextExtractor($extractorOptions);
        $lines = $this->normalizeLines($extractor->extractTextLines($pdfBytes));
        $geometryTablesEnabled = !$fastTextOnly && $this->geometryTablesEnabled();
        $proseRepairEnabled = !$fastTextOnly && $this->proseTextRepairEnabled();
        $runs = $fastTextOnly ? [] : $extractor->extractTextRuns($pdfBytes);
        $positionedRuns = (!$fastTextOnly && ($geometryTablesEnabled || $proseRepairEnabled)) ? $extractor->extractPositionedTextRuns($pdfBytes) : [];
        $filledRectangles = $geometryTablesEnabled ? $extractor->extractFilledRectangles($pdfBytes) : [];
        $diagnostics = $fastTextOnly ? $this->fastTextOnlyDiagnostics() : $extractor->diagnostics($pdfBytes);
        $plainText = implode("\n", $lines);
        $maxTextBytes = max(0, (int) ($this->options['maxTextBytes'] ?? self::DEFAULT_MAX_TEXT_BYTES));
        $limitedLines = $this->limitLines($lines, $maxTextBytes);
        $limitedPositionedRuns = $this->limitPositionedTextRuns($positionedRuns, $maxTextBytes);
        $insertedText = implode("\n", $limitedLines);
        $linkAnnotations = is_array($diagnostics['linkAnnotations'] ?? null) ? $diagnostics['linkAnnotations'] : [];
        $textAnnotations = is_array($diagnostics['textAnnotations'] ?? null) ? $diagnostics['textAnnotations'] : [];
        $fileAttachmentAnnotations = is_array($diagnostics['fileAttachmentAnnotations'] ?? null) ? $diagnostics['fileAttachmentAnnotations'] : [];
        $popupAnnotations = is_array($diagnostics['popupAnnotations'] ?? null) ? $diagnostics['popupAnnotations'] : [];
        $appearanceAnnotations = is_array($diagnostics['appearanceAnnotations'] ?? null) ? $diagnostics['appearanceAnnotations'] : [];
        $appliedLinkAnnotations = $this->unambiguousLinkAnnotations($linkAnnotations, $limitedLines);

        $taggedStructureBlocks = $this->blocksFromTaggedStructureBlocks(
            is_array($diagnostics['taggedStructureBlocks'] ?? null) ? $diagnostics['taggedStructureBlocks'] : [],
            $limitedLines
        );
        $taggedTableBlocks = $taggedStructureBlocks !== [] ? $taggedStructureBlocks : $this->blocksFromTaggedTables(
            is_array($diagnostics['taggedTables'] ?? null) ? $diagnostics['taggedTables'] : [],
            $limitedLines
        );
        $taggedBlocks = $taggedTableBlocks !== [] ? $taggedTableBlocks : $this->blocksFromTaggedStructureItems(
            is_array($diagnostics['taggedStructureItems'] ?? null) ? $diagnostics['taggedStructureItems'] : [],
            $limitedLines
        );
        $geometryTableBlocks = $geometryTablesEnabled && $taggedBlocks === [] ? $this->blocksFromPositionedTables($limitedPositionedRuns, $filledRectangles) : [];
        $geometryTableCount = $this->countNodesOfType($geometryTableBlocks, 'table');
        $geometryTableFallback = false;
        if ($geometryTableBlocks !== [] && $this->blocksHaveSuspiciousPdfTableText($geometryTableBlocks)) {
            $textTableBlocks = $this->blocksFromLines($limitedLines);
            if ($this->countNodesOfType($textTableBlocks, 'table') === 0 || $this->blocksHaveSuspiciousPdfTableText($textTableBlocks)) {
                $textTableBlocks = $this->blocksFromCurrencyRecordLines($limitedLines);
            }
            if ($this->countNodesOfType($textTableBlocks, 'table') > 0 && !$this->blocksHaveSuspiciousPdfTableText($textTableBlocks)) {
                $geometryTableBlocks = $textTableBlocks;
                $geometryTableFallback = true;
            }
        }
        $repairSourceLines = $limitedLines;
        $repairSourceLayouts = [];
        $repairSource = 'text';
        $repairSplitWordHints = [];
        if ($taggedBlocks === [] && $geometryTableBlocks === [] && $proseRepairEnabled) {
            $positionedLineItems = $this->positionedProseLineItemsFromTextRuns($limitedPositionedRuns);
            $positionedLines = $this->positionedLineItemTexts($positionedLineItems);
            if ($this->positionedProseLinesLookUsable($positionedLines, $limitedLines)) {
                $repairSourceLines = $positionedLines;
                $repairSourceLayouts = $positionedLineItems;
                $repairSource = 'positioned';
            } else {
                $repairSplitWordHints = array_replace(
                    $this->pdfTextRunSplitWordHints($runs),
                    $this->pdfPositionedRunSpacingHints($limitedPositionedRuns)
                );
            }
        }
        $repairedLines = $taggedBlocks === [] && $geometryTableBlocks === [] && $proseRepairEnabled
            ? $this->repairProseTextLines($repairSourceLines, $this->looksLikeProseRepairCandidate($repairSourceLines), $repairSourceLayouts, $repairSplitWordHints)
            : $limitedLines;
        $blocks = $taggedBlocks !== [] ? $taggedBlocks : ($geometryTableBlocks !== [] ? $geometryTableBlocks : $this->blocksFromLines($repairedLines));
        $blocks = $appliedLinkAnnotations === [] ? $blocks : $this->applyLinkAnnotationsToBlocks($blocks, $appliedLinkAnnotations);
        $pdfWarnings = is_array($diagnostics['warnings'] ?? null) ? array_values(array_map(static fn (mixed $warning): string => (string) $warning, $diagnostics['warnings'])) : [];
        if ($this->lowConfidenceGeometryTableCandidates > 0 && $geometryTableBlocks === []) {
            $pdfWarnings[] = 'PDF table-like geometry was preserved as text because native table confidence was low.';
        }
        $metadata = array_replace($structuralMetadata, [
            'pdfExtractor' => PdfTextExtractor::class,
            'pdfFastTextOnly' => $fastTextOnly,
            'pdfTextLines' => count($lines),
            'pdfTextRuns' => count($runs),
            'pdfPositionedTextRuns' => count($positionedRuns),
            'pdfPositionedTextInsertedRuns' => count($limitedPositionedRuns),
            'pdfFilledRectangles' => count($filledRectangles),
            'pdfTextBytes' => strlen($plainText),
            'pdfTextInsertedBytes' => strlen($insertedText),
            'pdfTextLimited' => strlen($insertedText) < strlen($plainText),
            'pdfMaxPages' => $this->pdfMaxPages(),
            'pdfTextRepair' => $repairedLines !== $limitedLines,
            'pdfTextRepairSource' => $repairedLines !== $limitedLines ? $repairSource : null,
            'pdfDetectedTables' => $this->countNodesOfType($blocks, 'table'),
            'pdfGeometryTables' => $geometryTableCount,
            'pdfGeometryTablesEnabled' => $geometryTablesEnabled,
            'pdfGeometryTableLowConfidenceCandidates' => $this->lowConfidenceGeometryTableCandidates,
            'pdfTableReconstruction' => $taggedBlocks !== [] ? 'tagged' : ($geometryTableBlocks !== [] ? ($geometryTableFallback ? 'text-fallback' : 'geometry') : 'text'),
            'pdfDiagnostics' => $diagnostics,
            'pdfWarnings' => $pdfWarnings,
            'pdfEncryptionDecrypted' => $diagnostics['encryptionDecrypted'] ?? false,
            'pdfEncryptionHandler' => $diagnostics['encryptionHandler'] ?? null,
            'pdfEncryptionPasswordType' => $diagnostics['encryptionPasswordType'] ?? null,
            'pdfEncryptionPermissions' => $diagnostics['encryptionPermissions'] ?? null,
            'pdfEncryptionAllowsContentExtraction' => $diagnostics['encryptionAllowsContentExtraction'] ?? null,
            'pdfUnsupportedFilters' => $diagnostics['unsupportedFilters'],
            'pdfFailedStreams' => $diagnostics['failedStreams'],
            'pdfMalformedXrefOffsets' => $diagnostics['malformedXrefOffsets'],
            'pdfMalformedXrefStreams' => $diagnostics['malformedXrefStreams'],
            'pdfMalformedObjectStreams' => $diagnostics['malformedObjectStreams'],
            'pdfMissingUnicodeFonts' => $diagnostics['missingUnicodeFonts'],
            'pdfMissingUnicodeFontEncodings' => $diagnostics['missingUnicodeFontEncodings'],
            'pdfSuppressedGlyphRuns' => $diagnostics['suppressedGlyphRuns'],
            'pdfIgnoredXObjectSubtypes' => $diagnostics['ignoredXObjectSubtypes'],
            'pdfIgnoredXObjectCount' => $diagnostics['ignoredXObjectCount'],
            'pdfTaggedRoleMap' => $diagnostics['taggedRoleMap'] ?? [],
            'pdfTaggedStructureRoles' => $diagnostics['taggedStructureRoles'] ?? [],
            'pdfTaggedStructureLanguages' => $diagnostics['taggedStructureLanguages'] ?? [],
            'pdfTaggedClassMap' => $diagnostics['taggedClassMap'] ?? [],
            'pdfTaggedStructureAttributes' => $diagnostics['taggedStructureAttributes'] ?? [],
            'pdfTaggedStructureItems' => $diagnostics['taggedStructureItems'] ?? [],
            'pdfTaggedStructureBlocks' => $diagnostics['taggedStructureBlocks'] ?? [],
            'pdfTaggedTables' => $diagnostics['taggedTables'] ?? [],
            'pdfTaggedAttributeOwners' => $diagnostics['taggedAttributeOwners'] ?? [],
            'pdfTaggedStructElementCount' => $diagnostics['taggedStructElementCount'] ?? 0,
            'pdfLinkAnnotations' => $linkAnnotations,
            'pdfTextAnnotations' => $textAnnotations,
            'pdfFileAttachmentAnnotations' => $fileAttachmentAnnotations,
            'pdfPopupAnnotations' => $popupAnnotations,
            'pdfAppearanceAnnotations' => $appearanceAnnotations,
            'pdfAppliedLinkAnnotations' => $appliedLinkAnnotations,
            'pdfPageExtractionIssues' => $diagnostics['pageExtractionIssues'],
            'pdfPagesWithExtractionIssues' => $diagnostics['pagesWithExtractionIssues'],
        ]);

        return new AstNode('document', ['meta' => $metadata], $blocks);
    }

    private function geometryTablesEnabled(): bool
    {
        foreach (['pdfGeometryTables', 'geometryTables', 'extractGeometryTables'] as $key) {
            if (array_key_exists($key, $this->options)) {
                return (bool) $this->options[$key];
            }
        }

        return true;
    }

    private function proseTextRepairEnabled(): bool
    {
        foreach (['pdfRepairProseText', 'repairProseText'] as $key) {
            if (array_key_exists($key, $this->options)) {
                return (bool) $this->options[$key];
            }
        }

        return false;
    }

    private function pdfMaxPages(): ?int
    {
        foreach (['pdfMaxPages', 'maxPages', 'max_pages'] as $key) {
            if (!array_key_exists($key, $this->options) || $this->options[$key] === null || $this->options[$key] === '') {
                continue;
            }
            $value = (int) $this->options[$key];

            return $value > 0 ? $value : null;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $structuralMetadata
     */
    private function fastTextOnlyMode(string $pdfBytes, array $structuralMetadata): bool
    {
        foreach (['pdfFastTextOnly', 'fastTextOnly'] as $key) {
            if (array_key_exists($key, $this->options)) {
                return (bool) $this->options[$key];
            }
        }

        $threshold = (int) ($this->options['pdfFastModeBytes'] ?? self::DEFAULT_FAST_MODE_BYTES);
        if ($threshold > 0 && strlen($pdfBytes) >= $threshold) {
            return true;
        }

        $maxPages = $this->pdfMaxPages();
        $estimatedPages = (int) ($structuralMetadata['pdfEstimatedPages'] ?? 0);

        return $maxPages !== null && $estimatedPages > $maxPages * 2;
    }

    /**
     * @return array<string, mixed>
     */
    private function fastTextOnlyDiagnostics(): array
    {
        return [
            'warnings' => ['Large PDF imported in bounded text-only mode; expensive geometry, annotation, and tagged-structure diagnostics were skipped.'],
            'unsupportedFilters' => [],
            'failedStreams' => 0,
            'malformedXrefOffsets' => [],
            'malformedXrefStreams' => 0,
            'malformedObjectStreams' => 0,
            'missingUnicodeFonts' => [],
            'missingUnicodeFontEncodings' => [],
            'suppressedGlyphRuns' => 0,
            'ignoredXObjectSubtypes' => [],
            'ignoredXObjectCount' => 0,
            'taggedRoleMap' => [],
            'taggedStructureRoles' => [],
            'taggedStructureLanguages' => [],
            'taggedClassMap' => [],
            'taggedStructureAttributes' => [],
            'taggedStructureItems' => [],
            'taggedStructureBlocks' => [],
            'taggedTables' => [],
            'taggedAttributeOwners' => [],
            'taggedStructElementCount' => 0,
            'linkAnnotations' => [],
            'textAnnotations' => [],
            'fileAttachmentAnnotations' => [],
            'popupAnnotations' => [],
            'appearanceAnnotations' => [],
            'pageExtractionIssues' => [],
            'pagesWithExtractionIssues' => [],
            'encryptionDecrypted' => false,
            'encryptionHandler' => null,
            'encryptionPasswordType' => null,
            'encryptionPermissions' => null,
            'encryptionAllowsContentExtraction' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function structuralMetadata(string $pdfBytes): array
    {
        $metadata = [
            'pdfHeader' => preg_match('/%PDF-\d\.\d/', substr($pdfBytes, 0, 64), $match) === 1 ? $match[0] : 'unknown',
            'pdfEstimatedPages' => preg_match_all('/\/Type\s*\/Page\b/', $pdfBytes),
            'pdfObjectCount' => preg_match_all('/\b\d+\s+\d+\s+obj\b/', $pdfBytes),
            'pdfStreamCount' => preg_match_all('/\bstream\r?\n/', $pdfBytes),
            'pdfEncrypted' => str_contains($pdfBytes, '/Encrypt'),
        ];

        $title = $this->pdfInfoValue($pdfBytes, 'Title');
        $xmpTitle = $this->xmpTitle($pdfBytes);
        if ($title === '' && $xmpTitle !== '') {
            $title = $xmpTitle;
        }
        if ($title !== '') {
            $metadata['title'] = $title;
            $metadata['titleInlines'] = [new AstNode('text', ['text' => $title])];
        }

        $author = $this->pdfInfoValue($pdfBytes, 'Author');
        if ($author !== '') {
            $metadata['author'] = $author;
        }

        foreach ([
            'Creator' => 'creator',
            'Producer' => 'producer',
            'CreationDate' => 'created',
        ] as $key => $metadataKey) {
            $value = $this->pdfInfoValue($pdfBytes, $key);
            if ($value !== '') {
                $metadata[$metadataKey] = $value;
            }
        }

        return $metadata;
    }

    /**
     * @param list<string> $lines
     * @return list<string>
     */
    private function normalizeLines(array $lines): array
    {
        $normalized = [];
        foreach ($lines as $line) {
            $line = $this->normalizePdfTextEncoding($line);
            $line = str_replace("\0", '', $line);
            $line = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $line) ?? $line;
            $line = trim($line);
            if ($line !== '') {
                $normalized[] = $line;
            }
        }

        return $normalized;
    }

    private function normalizePdfTextEncoding(string $text): string
    {
        if ($text === '' || preg_match('//u', $text) === 1) {
            return $text;
        }

        $decoded = @iconv('Windows-1252', 'UTF-8//IGNORE', $text);
        if (is_string($decoded) && $decoded !== '') {
            return $decoded;
        }

        $decoded = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
        return is_string($decoded) ? $decoded : $text;
    }

    /**
     * @param list<string> $lines
     * @return list<string>
     */
    private function limitLines(array $lines, int $maxBytes): array
    {
        if ($maxBytes <= 0) {
            return [];
        }

        $limited = [];
        $bytes = 0;
        foreach ($lines as $line) {
            $nextBytes = strlen($line) + ($limited === [] ? 0 : 1);
            if ($bytes + $nextBytes > $maxBytes) {
                $remaining = $maxBytes - $bytes - ($limited === [] ? 0 : 1);
                if ($remaining > 0) {
                    $limited[] = trim(substr($line, 0, $remaining));
                }
                break;
            }
            $limited[] = $line;
            $bytes += $nextBytes;
        }

        return array_values(array_filter($limited, static fn (string $line): bool => $line !== ''));
    }

    /**
     * @param list<array<string, mixed>> $runs
     * @return list<array<string, mixed>>
     */
    private function limitPositionedTextRuns(array $runs, int $maxBytes): array
    {
        if ($maxBytes <= 0) {
            return [];
        }

        $limited = [];
        $bytes = 0;
        foreach ($runs as $run) {
            $text = isset($run['text']) ? (string) $run['text'] : '';
            if ($text === '') {
                continue;
            }

            $nextBytes = strlen($text) + ($limited === [] ? 0 : 1);
            if ($bytes + $nextBytes > $maxBytes) {
                break;
            }

            $limited[] = $run;
            $bytes += $nextBytes;
        }

        return $limited;
    }

    /**
     * @param list<array<string, mixed>> $runs
     * @return list<string>
     */
    private function linesFromPositionedTextRuns(array $runs): array
    {
        return $this->positionedLineItemTexts($this->positionedProseLineItemsFromTextRuns($runs));
    }

    /**
     * @param list<array{text: string}> $items
     * @return list<string>
     */
    private function positionedLineItemTexts(array $items): array
    {
        return array_map(static fn (array $item): string => $item['text'], $items);
    }

    /**
     * @param list<array<string, mixed>> $runs
     * @return list<array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float}>
     */
    private function positionedProseLineItemsFromTextRuns(array $runs): array
    {
        $runsByPage = [];
        foreach ($runs as $index => $run) {
            $run['_order'] = $index;
            $normalized = $this->positionedRun($run);
            if ($normalized === null) {
                continue;
            }
            $runsByPage[$normalized['page']][] = $normalized;
        }
        if ($runsByPage === []) {
            return [];
        }

        ksort($runsByPage);
        $items = [];
        foreach ($runsByPage as $pageRuns) {
            foreach ($this->positionedProseLineItemsForPage($pageRuns) as $item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}> $runs
     * @return list<string>
     */
    private function positionedProseLinesForPage(array $runs): array
    {
        return $this->positionedLineItemTexts($this->positionedProseLineItemsForPage($runs));
    }

    /**
     * @param list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}> $runs
     * @return list<array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float}>
     */
    private function positionedProseLineItemsForPage(array $runs): array
    {
        if ($runs === []) {
            return [];
        }

        $fontSizes = array_map(static fn (array $run): float => $run['fontSize'], $runs);
        $medianFontSize = max(1.0, $this->median($fontSizes));
        $rowTolerance = max(3.0, $medianFontSize * 0.55);
        $rows = $this->clusterPositionedRows($runs, $rowTolerance);
        $rows = $this->mergePositionedProseRowFragments($rows, $this->positionedRowsBounds($rows));
        $rows = $this->splitPositionedRowsIntoProseFragments($rows);
        $rows = $this->orderPositionedProseRows($rows, $medianFontSize);

        $items = [];
        foreach ($rows as $row) {
            $line = $this->positionedRowText($row);
            if ($line !== '' && !$this->lineIsOnlyPdfNoise($line)) {
                $bounds = $this->positionedProseRowBounds($row);
                $items[] = [
                    'text' => $line,
                    'page' => (int) $row['runs'][0]['page'],
                    'x1' => $bounds['x1'],
                    'y1' => $bounds['y1'],
                    'x2' => $bounds['x2'],
                    'y2' => $bounds['y2'],
                    'fontSize' => $this->positionedRowMaxFontSize($row),
                ];
            }
        }

        return $items;
    }

    /**
     * @param list<array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>}> $rows
     * @param array{x1: float, y1: float, x2: float, y2: float} $pageBounds
     * @return list<array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>}>
     */
    private function mergePositionedProseRowFragments(array $rows, array $pageBounds): array
    {
        $pageWidth = max(0.0, $pageBounds['x2'] - $pageBounds['x1']);
        foreach ($rows as &$row) {
            $merged = [];
            foreach ($row['runs'] as $run) {
                $lastIndex = array_key_last($merged);
                if ($lastIndex === null) {
                    $merged[] = $run;
                    continue;
                }

                $last = $merged[$lastIndex];
                $gap = $run['textX1'] - $last['textX2'];
                $mergeGap = max(4.0, max($run['fontSize'], $last['fontSize']) * 1.25);
                $combinedWidth = max($last['x2'], $last['textX2'], $run['x2'], $run['textX2']) - min($last['x1'], $last['textX1'], $run['x1'], $run['textX1']);
                $normalProseMerge = $gap <= $mergeGap && ($pageWidth <= 0.0 || $combinedWidth <= $pageWidth * 0.72);
                $looksLikeLineContinuation = $this->positionedProseRunsLookLikeLineContinuation($last, $run, $gap, $pageWidth);
                if ($normalProseMerge || $looksLikeLineContinuation) {
                    $merged[$lastIndex] = [
                        'page' => $last['page'],
                        'text' => $looksLikeLineContinuation && !$normalProseMerge
                            ? $this->positionedCellText($last['text'] . $run['text'])
                            : $this->joinPositionedCellText(
                                $last['text'],
                                $run['text'],
                                $gap,
                                max($run['fontSize'], $last['fontSize']),
                                (bool) ($last['endsWithWhitespace'] ?? false),
                                (bool) ($run['startsWithWhitespace'] ?? false)
                            ),
                        'x1' => min($last['x1'], $run['x1']),
                        'y1' => min($last['y1'], $run['y1']),
                        'x2' => max($last['x2'], $run['x2']),
                        'y2' => max($last['y2'], $run['y2']),
                        'textX1' => min($last['textX1'], $run['textX1']),
                        'textY1' => min($last['textY1'], $run['textY1']),
                        'textX2' => max($last['textX2'], $run['textX2']),
                        'textY2' => max($last['textY2'], $run['textY2']),
                        'fontSize' => max($run['fontSize'], $last['fontSize']),
                        'startsWithWhitespace' => (bool) ($last['startsWithWhitespace'] ?? false),
                        'endsWithWhitespace' => (bool) ($run['endsWithWhitespace'] ?? false),
                    ];
                    continue;
                }

                $merged[] = $run;
            }
            $row['runs'] = $merged;
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float} $left
     * @param array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float} $right
     */
    private function positionedProseRunsLookLikeLineContinuation(array $left, array $right, float $gap, float $pageWidth): bool
    {
        if ($pageWidth > 0.0 && (abs($gap) > $pageWidth * 0.45)) {
            return false;
        }
        if (($left['endsWithWhitespace'] ?? false) || ($right['startsWithWhitespace'] ?? false)) {
            return false;
        }
        if (preg_match('/[-\p{L}\p{N}]$/u', rtrim($left['text'])) !== 1) {
            return false;
        }
        if (preg_match('/^[\p{Ll}]/u', ltrim($right['text'])) !== 1) {
            return false;
        }

        $leftWord = $this->lastWordToken($left['text']);
        $rightWord = $this->firstWordToken($right['text']);
        if ($leftWord === '' || $rightWord === '' || preg_match('/^\p{L}+$/u', $leftWord . $rightWord) !== 1) {
            return false;
        }

        $leftLength = $this->length($leftWord);
        $rightLength = $this->length($rightWord);
        if ($leftLength < 2 || $rightLength < 1) {
            return false;
        }

        if ($rightLength > 4 && preg_match('/-\s*$/u', $left['text']) !== 1) {
            return false;
        }

        $fontSize = max($left['fontSize'], $right['fontSize'], 1.0);
        $maxContinuationGap = max($fontSize * 3.0, $pageWidth > 0.0 ? $pageWidth * 0.45 : 24.0);

        return $gap <= $maxContinuationGap;
    }

    /**
     * @param list<array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>}> $rows
     * @return list<array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>}>
     */
    private function splitPositionedRowsIntoProseFragments(array $rows): array
    {
        $fragments = [];
        foreach ($rows as $row) {
            if (count($row['runs']) <= 1) {
                $fragments[] = $row;
                continue;
            }

            foreach ($row['runs'] as $run) {
                $fragments[] = [
                    'center' => $row['center'],
                    'runs' => [$run],
                ];
            }
        }

        usort($fragments, static fn (array $left, array $right): int => ($right['center'] <=> $left['center']) ?: ($left['runs'][0]['x1'] <=> $right['runs'][0]['x1']));

        return $fragments;
    }

    /**
     * @param list<array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>}> $rows
     * @return list<array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>}>
     */
    private function orderPositionedProseRows(array $rows, float $medianFontSize): array
    {
        if (count($rows) < 4) {
            return $rows;
        }

        $pageBounds = $this->positionedRowsBounds($rows);
        $pageWidth = max(0.0, $pageBounds['x2'] - $pageBounds['x1']);
        if ($pageWidth < max(120.0, $medianFontSize * 12.0)) {
            return $rows;
        }

        $ordered = [];
        $band = [];
        $flushBand = function () use (&$ordered, &$band, $pageBounds, $medianFontSize): void {
            if ($band === []) {
                return;
            }
            foreach ($this->orderPositionedProseBand($band, $pageBounds, $medianFontSize) as $row) {
                $ordered[] = $row;
            }
            $band = [];
        };

        foreach ($rows as $row) {
            if ($this->positionedProseRowIsFullWidth($row, $pageBounds, $medianFontSize)) {
                $flushBand();
                $ordered[] = $row;
                continue;
            }
            $band[] = $row;
        }
        $flushBand();

        return $ordered;
    }

    /**
     * @param list<array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>}> $rows
     * @param array{x1: float, y1: float, x2: float, y2: float} $pageBounds
     * @return list<array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>}>
     */
    private function orderPositionedProseBand(array $rows, array $pageBounds, float $medianFontSize): array
    {
        $columns = $this->splitPositionedProseBandIntoColumns($rows, $pageBounds, $medianFontSize);
        if ($columns === null) {
            return $rows;
        }

        return array_merge($columns['left'], $columns['right']);
    }

    /**
     * @param list<array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>}> $rows
     * @param array{x1: float, y1: float, x2: float, y2: float} $pageBounds
     * @return array{left: list<array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>}>, right: list<array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>}>}|null
     */
    private function splitPositionedProseBandIntoColumns(array $rows, array $pageBounds, float $medianFontSize): ?array
    {
        if (count($rows) < 4) {
            return null;
        }

        $midpoint = ($pageBounds['x1'] + $pageBounds['x2']) / 2.0;
        $left = [];
        $right = [];
        $leftStarts = [];
        $rightStarts = [];
        $leftEnds = [];
        $rightStartsForGap = [];
        foreach ($rows as $row) {
            $bounds = $this->positionedProseRowBounds($row);
            $centerX = ($bounds['x1'] + $bounds['x2']) / 2.0;
            if ($centerX <= $midpoint) {
                $left[] = $row;
                $leftStarts[] = $bounds['x1'];
                $leftEnds[] = $bounds['x2'];
                continue;
            }

            $right[] = $row;
            $rightStarts[] = $bounds['x1'];
            $rightStartsForGap[] = $bounds['x1'];
        }

        if (count($left) < 2 || count($right) < 2) {
            return null;
        }

        $startGap = $this->median($rightStarts) - $this->median($leftStarts);
        if ($startGap < max(80.0, $medianFontSize * 8.0)) {
            return null;
        }

        if ($leftEnds !== [] && $rightStartsForGap !== []) {
            $gutter = $this->median($rightStartsForGap) - $this->median($leftEnds);
            if ($gutter < -max(24.0, $medianFontSize * 2.0)) {
                return null;
            }
        }

        return ['left' => $left, 'right' => $right];
    }

    /**
     * @param array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>} $row
     * @param array{x1: float, y1: float, x2: float, y2: float} $pageBounds
     */
    private function positionedProseRowIsFullWidth(array $row, array $pageBounds, float $medianFontSize): bool
    {
        $bounds = $this->positionedProseRowBounds($row);
        $pageWidth = max(0.0, $pageBounds['x2'] - $pageBounds['x1']);
        if ($pageWidth <= 0.0) {
            return false;
        }

        $rowWidth = max(0.0, $bounds['x2'] - $bounds['x1']);
        if ($rowWidth >= $pageWidth * 0.72) {
            return true;
        }

        $pageCenter = ($pageBounds['x1'] + $pageBounds['x2']) / 2.0;
        $rowCenter = ($bounds['x1'] + $bounds['x2']) / 2.0;
        $text = $this->positionedRowText($row);

        return $this->positionedRowLooksStandaloneHeading($row, $medianFontSize)
            && abs($rowCenter - $pageCenter) <= max(36.0, $medianFontSize * 4.0)
            && $this->length($text) <= 100;
    }

    /**
     * @param array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>} $row
     * @return array{x1: float, y1: float, x2: float, y2: float}
     */
    private function positionedProseRowBounds(array $row): array
    {
        $bounds = ['x1' => INF, 'y1' => INF, 'x2' => -INF, 'y2' => -INF];
        foreach ($row['runs'] as $run) {
            $bounds['x1'] = min($bounds['x1'], $run['x1'], $run['textX1']);
            $bounds['y1'] = min($bounds['y1'], $run['y1'], $run['textY1']);
            $bounds['x2'] = max($bounds['x2'], $run['x2'], $run['textX2']);
            $bounds['y2'] = max($bounds['y2'], $run['y2'], $run['textY2']);
        }

        return is_finite($bounds['x1']) && is_finite($bounds['x2']) && is_finite($bounds['y1']) && is_finite($bounds['y2'])
            ? $bounds
            : ['x1' => 0.0, 'y1' => $row['center'], 'x2' => 0.0, 'y2' => $row['center']];
    }

    /**
     * @param list<string> $positionedLines
     * @param list<string> $textLines
     */
    private function positionedProseLinesLookUsable(array $positionedLines, array $textLines): bool
    {
        if (count($positionedLines) < 2) {
            return false;
        }
        if ($textLines === []) {
            return true;
        }

        $textTokens = $this->significantTextTokens(implode(' ', array_slice($textLines, 0, 500)));
        if (count($textTokens) < 4) {
            return true;
        }

        $textSpacingDamage = $this->genericSpacingDamageScore($textLines);
        $positionedSpacingDamage = $this->genericSpacingDamageScore($positionedLines);
        if ($positionedSpacingDamage >= $textSpacingDamage + 2) {
            return false;
        }

        $positionedTokens = array_flip($this->significantTextTokens(implode(' ', array_slice($positionedLines, 0, 500))));
        if ($positionedTokens === []) {
            return false;
        }

        $matched = 0;
        foreach ($textTokens as $token) {
            if (isset($positionedTokens[$token])) {
                $matched++;
            }
        }

        return $matched / count($textTokens) >= 0.55;
    }

    /**
     * @param list<string> $lines
     */
    private function genericSpacingDamageScore(array $lines): int
    {
        $score = 0;
        foreach (array_slice($lines, 0, 500) as $line) {
            if (preg_match_all('/\p{Ll}\p{Lu}|\p{L}\d|\d\p{L}|[,:;!?]\S|(?<!\d)\.[\p{Lu}]/u', $line, $matches) !== false) {
                $score += count($matches[0]);
            }
            if (preg_match_all('/\b[\p{L}]{28,}\b/u', $line, $matches) !== false) {
                $score += count($matches[0]);
            }
        }

        return $score;
    }

    /**
     * @param list<string> $lines
     * @param list<array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float}> $lineLayouts
     * @param array<string, true|string> $splitWordHints
     * @return list<string>
     */
    private function repairProseTextLines(array $lines, bool $repairGluedText = true, array $lineLayouts = [], array $splitWordHints = []): array
    {
        $cleaned = [];
        $pendingListMarker = null;
        foreach ($lines as $index => $line) {
            $layout = $lineLayouts[$index] ?? null;
            $chunks = $this->splitPdfTextLineVisualChunks($line);
            $chunkLayout = count($chunks) === 1 ? $layout : null;
            foreach ($chunks as $chunk) {
                if ($this->lineIsStandalonePdfListMarker($chunk)) {
                    $pendingListMarker = trim($chunk);
                    continue;
                }
                if ($pendingListMarker !== null) {
                    $chunk = $pendingListMarker . ' ' . ltrim($chunk);
                    $pendingListMarker = null;
                }
                if ($this->lineIsOnlyPdfNoise($chunk)) {
                    continue;
                }
                $cleaned[] = [
                    'text' => $chunk,
                    'layout' => $chunkLayout,
                ];
            }
        }
        $cleaned = $this->removeLowCoherencePdfMapRegions($cleaned);

        $merged = $this->pdfLinesLookLikeDenseListLayout($cleaned) || $this->pdfLinesLookLikeSparseLongTextChunks($cleaned)
            ? array_map(static fn (array $record): string => $record['text'], $cleaned)
            : $this->mergeRepairedProseLines($cleaned, $splitWordHints);
        $repaired = [];
        foreach ($merged as $line) {
            $line = $repairGluedText ? $this->repairGluedProseLine($line, $splitWordHints) : trim($line);
            if ($line !== '') {
                $repaired[] = $line;
            }
        }

        return $repaired;
    }

    /**
     * @return list<string>
     */
    private function splitPdfTextLineVisualChunks(string $line): array
    {
        if (!str_contains($line, "\t")) {
            return [$line];
        }

        $chunks = [];
        foreach (preg_split('/\t+/u', $line) ?: [] as $chunk) {
            $chunk = trim($chunk);
            if ($chunk !== '') {
                $chunks[] = $chunk;
            }
        }

        return $chunks === [] ? [$line] : $chunks;
    }

    private function lineIsStandalonePdfListMarker(string $line): bool
    {
        return preg_match('/^\s*(?:[-*]|\x{2022}|\d{1,2}[.)])\s*$/u', $line) === 1;
    }

    /**
     * @param list<array{text: string, layout: array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float}|null}> $records
     */
    private function pdfLinesLookLikeDenseListLayout(array $records): bool
    {
        $count = count($records);
        if ($count < 20) {
            return false;
        }

        $listItems = 0;
        $shortLines = 0;
        $totalLength = 0;
        foreach ($records as $record) {
            $line = trim($record['text']);
            $length = $this->length($line);
            $totalLength += $length;
            if ($length <= 42) {
                $shortLines++;
            }
            if ($this->lineHasPdfListBlockEvidence($line)) {
                $listItems++;
            }
        }

        $listRatio = $listItems / $count;
        $shortRatio = $shortLines / $count;
        $averageLength = $totalLength / $count;

        return $listItems >= 6
            && $listRatio >= 0.08
            && $shortRatio >= 0.45
            && $averageLength <= 48.0;
    }

    /**
     * @param list<array{text: string, layout: array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float}|null}> $records
     */
    private function pdfLinesLookLikeSparseLongTextChunks(array $records): bool
    {
        $count = count($records);
        if ($count < 8 || $count > 60) {
            return false;
        }

        $longLines = 0;
        $shortLines = 0;
        $listItems = 0;
        $totalLength = 0;
        foreach ($records as $record) {
            $line = trim($record['text']);
            $length = $this->length($line);
            $totalLength += $length;
            if ($length >= 70) {
                $longLines++;
            }
            if ($length <= 42) {
                $shortLines++;
            }
            if ($this->lineHasPdfListBlockEvidence($line)) {
                $listItems++;
            }
        }

        return $longLines / $count >= 0.60
            && $shortLines / $count <= 0.25
            && $totalLength / $count >= 80.0
            && $listItems <= 2;
    }

    /**
     * @param list<array{text: string, layout: array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float}|null}> $records
     * @return list<array{text: string, layout: array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float}|null}>
     */
    private function removeLowCoherencePdfMapRegions(array $records): array
    {
        $filtered = [];
        $candidate = [];
        $flushCandidate = function () use (&$filtered, &$candidate): void {
            if ($candidate === []) {
                return;
            }
            if (!$this->pdfMapNoiseClusterShouldBeDropped($candidate)) {
                foreach ($candidate as $record) {
                    $filtered[] = $record;
                }
            }
            $candidate = [];
        };

        foreach ($records as $record) {
            $line = $record['text'];
            if ($this->lineLooksLikePdfMapLabelNoise($line, $record['layout'])) {
                $candidate[] = $record;
                continue;
            }

            $flushCandidate();
            $filtered[] = $record;
        }
        $flushCandidate();

        return $filtered;
    }

    /**
     * @param list<array{text: string, layout: array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float}|null}> $records
     */
    private function pdfMapNoiseClusterShouldBeDropped(array $records): bool
    {
        $count = count($records);
        if ($count < 8) {
            return false;
        }

        $shortLabels = 0;
        $letterSpacedLabels = 0;
        $edgeFragments = 0;
        $hasLayout = false;
        foreach ($records as $record) {
            $line = $record['text'];
            if ($record['layout'] !== null) {
                $hasLayout = true;
            }
            if ($this->lineLooksLikeShortPdfMapLabel($line)) {
                $shortLabels++;
            }
            if ($this->lineLooksLikeLetterSpacedPdfMapLabel($line)) {
                $letterSpacedLabels++;
            }
            if ($this->lineLooksLikePdfMapEdgeFragment($line, $record['layout'])) {
                $edgeFragments++;
            }
        }
        if (!$hasLayout) {
            return $count >= 18 && ($shortLabels + $letterSpacedLabels) / $count >= 0.72;
        }

        if ($shortLabels >= 6 && $edgeFragments > 0) {
            return true;
        }

        return $count >= 18 && $shortLabels / $count >= 0.72;
    }

    /**
     * @param array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float}|null $layout
     */
    private function lineLooksLikePdfMapLabelNoise(string $line, ?array $layout): bool
    {
        $line = trim($line);
        if ($line === '') {
            return false;
        }
        if ($this->lineLooksLikePdfListItem($line) || $this->lineLooksLikeUrlOnly($line)) {
            return false;
        }

        return $this->lineLooksLikeShortPdfMapLabel($line)
            || $this->lineLooksLikeLetterSpacedPdfMapLabel($line)
            || $this->lineLooksLikePdfMapEdgeFragment($line, $layout);
    }

    /**
     * @param array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float}|null $layout
     */
    private function lineLooksLikePdfMapEdgeFragment(string $line, ?array $layout): bool
    {
        if ($layout === null || $this->lineLooksLikePdfListItem($line) || $this->lineLooksLikeUrlOnly($line)) {
            return false;
        }

        $compact = preg_replace('/\s+/u', '', trim($line)) ?? '';
        if ($this->length($compact) < 80 || count($this->pdfLineWordTokens($line)) < 6) {
            return false;
        }

        return $layout['y1'] < 24.0 && $layout['x1'] < 48.0;
    }

    private function lineLooksLikeShortPdfMapLabel(string $line): bool
    {
        $line = trim($line);
        if ($line === '' || $this->lineLooksLikePdfListItem($line) || $this->lineLooksLikeUrlOnly($line)) {
            return false;
        }

        $compact = preg_replace('/[^\p{L}\p{N}]+/u', '', $line) ?? '';
        $length = $this->length($compact);
        if ($length <= 3) {
            return true;
        }
        if ($length > 35 || preg_match('/[.!?;:]$/u', $line) === 1) {
            return false;
        }

        $words = $this->pdfLineWordTokens($line);
        if (count($words) > 4) {
            return false;
        }

        return preg_match('/^[\p{L}\p{N},&()\/ .-]+$/u', $line) === 1;
    }

    private function lineLooksLikeLetterSpacedPdfMapLabel(string $line): bool
    {
        $line = trim($line);
        if ($line === '' || $this->lineLooksLikePdfListItem($line) || $this->lineLooksLikeUrlOnly($line)) {
            return false;
        }

        $tokens = preg_split('/\s+/u', $line) ?: [];
        if (count($tokens) < 4 || count($tokens) > 24) {
            return false;
        }

        $singleGlyphTokens = 0;
        foreach ($tokens as $token) {
            $token = trim($token, " \t\n\r\0\x0B,.;:()[]{}'\"’‘“”-");
            if ($this->length($token) === 1 && preg_match('/^[\p{L}\p{N}]$/u', $token) === 1) {
                $singleGlyphTokens++;
            }
        }

        return $singleGlyphTokens >= 4 && $singleGlyphTokens / count($tokens) >= 0.75;
    }

    /**
     * @return list<string>
     */
    private function pdfLineWordTokens(string $line): array
    {
        if (preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}.-]*/u', $line, $matches) < 1) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (string $word): string => trim($word, ".-"), $matches[0]),
            static fn (string $word): bool => $word !== ''
        ));
    }

    private function lineLooksLikePdfListItem(string $line): bool
    {
        return $this->listItem($line) !== null;
    }

    private function lineLooksLikeUrlOnly(string $line): bool
    {
        return preg_match('/^(?:https?:\/\/|www\.)\S+$/i', trim($line)) === 1;
    }

    /**
     * @param list<mixed> $runs
     * @return array<string, true|string>
     */
    private function pdfTextRunSplitWordHints(array $runs): array
    {
        $hints = [];
        $count = count($runs);
        for ($index = 0; $index < $count - 2; $index++) {
            $prefix = trim($this->pdfTextRunString($runs[$index]));
            $continuation = trim($this->pdfTextRunString($runs[$index + 1]));
            $after = trim($this->pdfTextRunString($runs[$index + 2]));
            if ($after !== '' || preg_match('/^\p{Lu}$/u', $prefix) !== 1 || preg_match('/^\p{Ll}{2,}\b/u', $continuation) !== 1) {
                continue;
            }

            $hints[$this->splitWordHintKey($prefix, $continuation)] = true;
        }
        for ($index = 0; $index < $count - 1; $index++) {
            $text = $this->pdfTextRunString($runs[$index]);
            $prefix = $this->pdfTextRunTrailingSplitFragment($text);
            if ($prefix === '') {
                continue;
            }

            $continuation = $this->pdfTextRunFollowingSplitFragment($runs, $index + 1);
            if ($continuation === '' || !$this->pdfTextRunSplitFragmentLooksUsable($text, $prefix, $continuation)) {
                continue;
            }

            $spaced = $prefix . ' ' . $continuation;
            $hints[$this->splitFragmentHintKey($spaced)] = $prefix . $continuation;
        }

        return $hints;
    }

    private function pdfTextRunString(mixed $run): string
    {
        if (is_array($run)) {
            return isset($run['text']) ? $this->normalizePdfTextEncoding((string) $run['text']) : '';
        }

        return $this->normalizePdfTextEncoding((string) $run);
    }

    private function splitWordHintKey(string $prefix, string $continuation): string
    {
        return trim($prefix) . "\n" . ltrim($continuation);
    }

    private function splitFragmentHintKey(string $spacedFragment): string
    {
        return "fragment\0" . $spacedFragment;
    }

    private function spacingHintKey(string $gluedText): string
    {
        return "spacing\0" . $gluedText;
    }

    /**
     * @param list<array<string, mixed>> $runs
     * @return array<string, string>
     */
    private function pdfPositionedRunSpacingHints(array $runs): array
    {
        $runsByPage = [];
        foreach ($runs as $index => $run) {
            $run['_order'] = $index;
            $normalized = $this->positionedRun($run);
            if ($normalized === null) {
                continue;
            }
            $runsByPage[$normalized['page']][] = $normalized;
        }
        if ($runsByPage === []) {
            return [];
        }

        ksort($runsByPage);
        $hints = [];
        foreach ($runsByPage as $pageRuns) {
            $fontSizes = array_map(static fn (array $run): float => $run['fontSize'], $pageRuns);
            $medianFontSize = max(1.0, $this->median($fontSizes));
            $rowTolerance = max(3.0, $medianFontSize * 0.55);
            $rows = $this->clusterPositionedRows($pageRuns, $rowTolerance);
            foreach ($rows as $row) {
                foreach ($this->positionedSpacingHintTokenSegments($row['runs']) as $tokens) {
                    $tokenCount = count($tokens);
                    for ($start = 0; $start < $tokenCount - 1; $start++) {
                        $glued = '';
                        $spaced = '';
                        for ($end = $start; $end < $tokenCount && $end < $start + 8; $end++) {
                            $token = $tokens[$end];
                            $glued .= $token;
                            $spaced .= ($spaced === '' ? '' : ' ') . $token;
                            if ($end === $start) {
                                continue;
                            }
                            if (!$this->spacingHintTokenSequenceLooksUsable(array_slice($tokens, $start, $end - $start + 1))) {
                                continue;
                            }
                            if ($glued !== '' && $glued !== $spaced) {
                                $hints[$this->spacingHintKey($glued)] = $spaced;
                            }
                        }
                    }
                }
            }
            foreach ($this->pdfPositionedRowBoundarySpacingHints($rows, $medianFontSize) as $key => $replacement) {
                $hints[$key] = $replacement;
            }
        }

        return $hints;
    }

    /**
     * @param list<array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>}> $rows
     * @return array<string, string>
     */
    private function pdfPositionedRowBoundarySpacingHints(array $rows, float $medianFontSize): array
    {
        if (count($rows) < 2) {
            return [];
        }

        $rows = $this->mergePositionedProseRowFragments($rows, $this->positionedRowsBounds($rows));
        $rows = $this->splitPositionedRowsIntoProseFragments($rows);
        $rows = $this->orderPositionedProseRows($rows, $medianFontSize);

        $hints = [];
        $count = count($rows);
        for ($index = 0; $index < $count - 1; $index++) {
            $current = $rows[$index];
            $next = $rows[$index + 1];
            if (!$this->positionedRowsLookLikeSpacingHintBoundary($current, $next, $medianFontSize)) {
                continue;
            }

            $left = $this->lastWordToken($this->positionedRowText($current));
            $right = $this->firstWordToken($this->positionedRowText($next));
            if (!$this->spacingHintTokenSequenceLooksUsable([$left, $right])) {
                continue;
            }

            $hints[$this->spacingHintKey($left . $right)] = $left . ' ' . $right;
        }

        return $hints;
    }

    /**
     * @param array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>} $current
     * @param array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>} $next
     */
    private function positionedRowsLookLikeSpacingHintBoundary(array $current, array $next, float $medianFontSize): bool
    {
        if ($current['runs'] === [] || $next['runs'] === []) {
            return false;
        }
        if ((int) $current['runs'][0]['page'] !== (int) $next['runs'][0]['page']) {
            return false;
        }
        if ($this->positionedRowLooksStandaloneHeading($current, $medianFontSize)
            || $this->positionedRowLooksStandaloneHeading($next, $medianFontSize)) {
            return false;
        }

        $currentText = rtrim($this->positionedRowText($current));
        $nextText = ltrim($this->positionedRowText($next));
        if ($currentText === '' || $nextText === '') {
            return false;
        }
        if (preg_match('/[-\x{2010}-\x{2015}\/\\\\]$/u', $currentText) === 1) {
            return false;
        }
        if (preg_match('/^[^\p{L}\p{N}]*[\p{Lu}]/u', $nextText) === 1) {
            return false;
        }

        $currentBounds = $this->positionedProseRowBounds($current);
        $nextBounds = $this->positionedProseRowBounds($next);
        $fontSize = max(
            $this->positionedRowMaxFontSize($current),
            $this->positionedRowMaxFontSize($next),
            $medianFontSize,
            1.0
        );
        $verticalGap = $currentBounds['y1'] - $nextBounds['y2'];
        if ($verticalGap < -$fontSize * 0.4 || $verticalGap > $fontSize * 1.8) {
            return false;
        }

        return abs($currentBounds['x1'] - $nextBounds['x1']) <= max(8.0, $fontSize * 1.25);
    }

    /**
     * @param list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float, startsWithWhitespace?: bool, endsWithWhitespace?: bool}> $runs
     * @return list<list<string>>
     */
    private function positionedSpacingHintTokenSegments(array $runs): array
    {
        usort($runs, static fn (array $left, array $right): int => ($left['textX1'] <=> $right['textX1']) ?: (($left['order'] ?? 0) <=> ($right['order'] ?? 0)));
        $segments = [];
        $current = [];
        $previous = null;
        $flush = static function () use (&$segments, &$current): void {
            if (count($current) >= 2) {
                $segments[] = $current;
            }
            $current = [];
        };
        foreach ($runs as $run) {
            $token = $this->spacingHintTokenFromPositionedRun((string) $run['text']);
            if ($token === '') {
                $flush();
                $previous = null;
                continue;
            }
            if ($previous !== null && !$this->positionedRunsHaveSpacingHintBoundary($previous, $run)) {
                $flush();
            }
            $current[] = $token;
            $previous = $run;
        }
        $flush();

        return $segments;
    }

    private function spacingHintTokenFromPositionedRun(string $text): string
    {
        $text = trim($text);
        if ($text === '' || preg_match('/-\s*$/u', $text) === 1) {
            return '';
        }
        if (preg_match('/^[^\p{L}\p{N}]*([\p{L}\p{N}]{1,24})[^\p{L}\p{N}]*$/u', $text, $matches) !== 1) {
            return '';
        }

        return $matches[1];
    }

    /**
     * @param array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float, endsWithWhitespace?: bool} $left
     * @param array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float, startsWithWhitespace?: bool} $right
     */
    private function positionedRunsHaveSpacingHintBoundary(array $left, array $right): bool
    {
        if (($left['endsWithWhitespace'] ?? false) || ($right['startsWithWhitespace'] ?? false)) {
            return true;
        }

        $fontSize = max($left['fontSize'], $right['fontSize'], 1.0);
        $gap = $right['textX1'] - $left['textX2'];

        return $gap >= -max(1.5, $fontSize * 0.15)
            && $gap <= max(18.0, $fontSize * 2.6);
    }

    /**
     * @param list<string> $tokens
     */
    private function spacingHintTokenSequenceLooksUsable(array $tokens): bool
    {
        if (count($tokens) < 2) {
            return false;
        }
        $letters = 0;
        $singleGlyphTokens = 0;
        $shortTokens = 0;
        $maxTokenLength = 0;
        foreach ($tokens as $token) {
            $length = $this->length($token);
            $maxTokenLength = max($maxTokenLength, $length);
            if ($length > 24 || preg_match('/^[\p{L}\p{N}]+$/u', $token) !== 1) {
                return false;
            }
            if ($length === 1) {
                $singleGlyphTokens++;
            }
            if ($length <= 2) {
                $shortTokens++;
            }
            if (preg_match('/\p{L}/u', $token) === 1) {
                $letters++;
            }
        }
        if ($letters === 0) {
            return false;
        }
        if ($singleGlyphTokens > 1) {
            return false;
        }
        if ($singleGlyphTokens === 1) {
            foreach ($tokens as $index => $token) {
                if ($this->length($token) === 1 && $index !== 0) {
                    return false;
                }
            }
        }
        if (count($tokens) > 2) {
            if ($shortTokens > 0 && ($maxTokenLength < 4 || $shortTokens / count($tokens) > 0.5)) {
                return false;
            }
            foreach ($tokens as $index => $token) {
                if ($index > 0 && $this->length($token) === 1) {
                    return false;
                }
            }
        }

        if (count($tokens) === 2) {
            [$left, $right] = $tokens;
            $leftLength = $this->length($left);
            $rightLength = $this->length($right);
            if ($leftLength < 2 || $rightLength < 2) {
                return false;
            }
        }

        return true;
    }

    private function pdfTextRunTrailingSplitFragment(string $text): string
    {
        if ($text === '' || preg_match('/\s$/u', $text) === 1) {
            return '';
        }
        if (preg_match('/(^|[^\p{L}])(\p{Ll}{1,8})$/u', $text, $matches) !== 1) {
            return '';
        }

        return $matches[2];
    }

    /**
     * @param list<mixed> $runs
     */
    private function pdfTextRunFollowingSplitFragment(array $runs, int $start): string
    {
        $fragment = '';
        $count = count($runs);
        for ($index = $start; $index < $count && $index < $start + 4; $index++) {
            $text = $this->pdfTextRunString($runs[$index]);
            if ($text === '' || preg_match('/^\s/u', $text) === 1) {
                break;
            }
            if (preg_match('/^(\p{Ll}{1,12})/u', $text, $matches) !== 1) {
                break;
            }

            $fragment .= $matches[1];
            if ($this->length($fragment) > 12) {
                return '';
            }
            if ($matches[1] !== $text) {
                break;
            }
        }

        return $fragment;
    }

    private function pdfTextRunSplitFragmentLooksUsable(string $text, string $prefix, string $continuation): bool
    {
        $prefixLength = $this->length($prefix);
        $continuationLength = $this->length($continuation);
        $wordLength = $prefixLength + $continuationLength;
        if ($wordLength < 2 || $wordLength > 20) {
            return false;
        }

        $hasLeftContext = preg_match('/\p{Ll}\s+\p{Ll}{1,8}$/u', $text) === 1;
        $isStandaloneRun = preg_match('/^\p{Ll}{1,8}$/u', $text) === 1;
        if (!$hasLeftContext && !$isStandaloneRun) {
            return false;
        }

        if ($prefixLength === 1) {
            return $hasLeftContext || ($isStandaloneRun && $continuationLength >= 2 && $continuationLength <= 5);
        }

        return $hasLeftContext || $continuationLength >= 2;
    }

    /**
     * @param array<string, true|string> $splitWordHints
     */
    private function repairGluedProseLine(string $line, array $splitWordHints = []): string
    {
        $line = trim($line);
        if ($line === '') {
            return '';
        }

        $line = $this->removeStandaloneBraceArtifacts($line);
        $line = $this->repairSplitUrlWhitespace($line);
        $line = $this->repairSplitFragmentWhitespace($line, $splitWordHints);
        $line = $this->repairPositionedSpacingWhitespace($line, $splitWordHints);
        $lineHasWordSpacing = preg_match('/\p{L}\s+\p{L}/u', $line) === 1;
        $line = preg_replace('/([,;:!?])(?=\S)/u', '$1 ', $line) ?? $line;
        $line = preg_replace('/(?<!\d)\.(?=\p{Lu})/u', '. ', $line) ?? $line;
        if ($lineHasWordSpacing) {
            $line = preg_replace('/([\p{Ll}])(\p{Lu}{2,})(?=\p{Ll})/u', '$1 $2', $line) ?? $line;
            $line = preg_replace('/(\p{Lu}{2,})(\p{Lu}\p{Ll})/u', '$1 $2', $line) ?? $line;
            $line = preg_replace('/(\p{Lu}{2,})(\p{Ll})/u', '$1 $2', $line) ?? $line;
            $line = preg_replace('/([\p{Ll}])([\p{Lu}][\p{Ll}])/u', '$1 $2', $line) ?? $line;
        }
        $line = preg_replace('/([\p{L}])(\d{2,})/u', '$1 $2', $line) ?? $line;
        $line = preg_replace('/(\d)([\p{L}]{2,})/u', '$1 $2', $line) ?? $line;
        $line = preg_replace('/\/\/(?=[A-Za-z])/', '// ', $line) ?? $line;
        $line = $this->repairSplitUrlWhitespace($line);
        $line = preg_replace('/([\x{2019}\']t)(?=[A-Za-z])/u', '$1 ', $line) ?? $line;

        $line = preg_replace('/\s+/u', ' ', $line) ?? $line;

        return trim($line);
    }

    /**
     * @param array<string, true|string> $splitWordHints
     */
    private function repairSplitFragmentWhitespace(string $line, array $splitWordHints): string
    {
        foreach ($splitWordHints as $key => $replacement) {
            if (!is_string($replacement) || !str_starts_with($key, "fragment\0")) {
                continue;
            }

            $spaced = substr($key, strlen("fragment\0"));
            if ($spaced === '' || !str_contains($line, $spaced)) {
                continue;
            }

            $pattern = '/(?<!\p{L})' . preg_quote($spaced, '/') . '(?!\p{L})/u';
            $line = preg_replace($pattern, $replacement, $line) ?? $line;
        }

        return $line;
    }

    /**
     * @param array<string, true|string> $splitWordHints
     */
    private function repairPositionedSpacingWhitespace(string $line, array $splitWordHints): string
    {
        $hints = [];
        foreach ($splitWordHints as $key => $replacement) {
            if (!is_string($replacement) || !str_starts_with($key, "spacing\0")) {
                continue;
            }

            $glued = substr($key, strlen("spacing\0"));
            if ($glued !== '' && str_contains($line, $glued)) {
                $hints[$glued] = $replacement;
            }
        }
        if ($hints === []) {
            return $line;
        }

        uksort($hints, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));
        foreach ($hints as $glued => $replacement) {
            if ($this->spacingReplacementNeedsLetterBoundary($replacement)) {
                $pattern = '/(?<!\p{L})' . preg_quote($glued, '/') . '(?!\p{L})/u';
                $line = preg_replace($pattern, $replacement, $line) ?? $line;
                continue;
            }
            $line = str_replace($glued, $replacement, $line);
        }

        return $line;
    }

    private function spacingReplacementNeedsLetterBoundary(string $replacement): bool
    {
        $tokens = array_values(array_filter(preg_split('/\s+/u', trim($replacement)) ?: []));
        if (count($tokens) === 2) {
            return true;
        }
        if (count($tokens) > 2) {
            $shortTokens = 0;
            $maxTokenLength = 0;
            foreach ($tokens as $token) {
                $length = $this->length($token);
                $maxTokenLength = max($maxTokenLength, $length);
                if ($length <= 2) {
                    $shortTokens++;
                }
            }
            if ($maxTokenLength >= 4 && $shortTokens / count($tokens) <= 0.5) {
                return false;
            }
        }

        foreach ($tokens as $token) {
            if ($this->length($token) <= 2) {
                return true;
            }
        }

        return false;
    }

    private function repairSplitUrlWhitespace(string $line): string
    {
        $line = preg_replace('/\b(https?):\s*\/\/\s*/iu', '$1://', $line) ?? $line;
        $line = preg_replace('/\b(www)\s+\.(?=[A-Za-z0-9-])/iu', '$1.', $line) ?? $line;

        return preg_replace('/\b((?:https?:\/\/|www\.)[A-Za-z0-9.-]*[A-Za-z0-9])\s+((?!www(?=\.))[A-Za-z0-9-]{1,24})(?=\.)/iu', '$1$2', $line) ?? $line;
    }

    private function removeStandaloneBraceArtifacts(string $line): string
    {
        $line = preg_replace('/(^|\s)\{\s*\}(?=\s|$)/u', '$1', $line) ?? $line;

        return preg_replace('/\s+/u', ' ', $line) ?? $line;
    }

    /**
     * @param list<string> $lines
     */
    private function looksLikeProseRepairCandidate(array $lines): bool
    {
        foreach (array_slice($lines, 0, 200) as $line) {
            if ($this->genericSpacingDamageScore([$line]) > 0) {
                return true;
            }
            if (preg_match('/(^|\s)\{\s*\}(?=\s|$)/u', $line) === 1) {
                return true;
            }
        }

        return false;
    }

    private function lineIsOnlyPdfNoise(string $line): bool
    {
        $line = trim($line);
        if ($line === '') {
            return false;
        }

        return preg_match('/^[,.;:()\[\]{}|_~`\'"’‘“”\-]+$/u', $line) === 1;
    }

    /**
     * @param list<array{text: string, layout: array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float}|null}> $records
     * @param array<string, true|string> $splitWordHints
     * @return list<string>
     */
    private function mergeRepairedProseLines(array $records, array $splitWordHints = []): array
    {
        $merged = [];
        $pending = '';
        $pendingLayout = null;
        foreach ($records as $record) {
            $line = $record['text'];
            $layout = $record['layout'];
            if ($line === '') {
                continue;
            }
            if ($pending === '') {
                $pending = $line;
                $pendingLayout = $layout;
                continue;
            }
            if ($this->repairedLineLooksLikeSplitWordPrefix($pending, $line, $splitWordHints)) {
                $pending .= ltrim($line);
                $pendingLayout = $layout;
                continue;
            }
            if (preg_match('/-\s*$/', $pending) === 1 && preg_match('/^\p{Ll}/u', $line) === 1) {
                $pending = $this->repairedLineShouldRemoveHyphenatedBreak($pending, $line, $pendingLayout, $layout)
                    ? (preg_replace('/-\s*$/u', '', $pending) ?? rtrim(substr($pending, 0, -1))) . ltrim($line)
                    : rtrim($pending) . ltrim($line);
                $pendingLayout = $layout;
                continue;
            }
            if ($this->repairedLineShouldStartNewBlock($pending, $line, $pendingLayout, $layout)) {
                $merged[] = $pending;
                $pending = $line;
                $pendingLayout = $layout;
                continue;
            }
            $pending .= ' ' . $line;
            $pendingLayout = $layout;
        }
        if ($pending !== '') {
            $merged[] = $pending;
        }

        return $merged;
    }

    /**
     * @param array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float}|null $previousLayout
     * @param array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float}|null $lineLayout
     */
    private function repairedLineShouldRemoveHyphenatedBreak(string $previous, string $line, ?array $previousLayout, ?array $lineLayout): bool
    {
        if ($previousLayout === null || $lineLayout === null) {
            $prefix = $this->hyphenatedLineBreakPrefix($previous);
            $continuation = $this->firstWordToken($line);

            return $prefix !== ''
                && $continuation !== ''
                && ($this->length($prefix) <= 3 || $this->length($prefix) > 8)
                && $this->length($continuation) >= 3;
        }
        if (preg_match('/^\p{Ll}{1,3}-/u', ltrim($line)) === 1) {
            return false;
        }

        return $this->repairedPdfLayoutContinuesWrappedLine($previousLayout, $lineLayout);
    }

    private function hyphenatedLineBreakPrefix(string $line): string
    {
        if (preg_match('/([\p{L}]+)-\s*$/u', $line, $matches) !== 1) {
            return '';
        }

        return $matches[1];
    }

    /**
     * @param array<string, true|string> $splitWordHints
     */
    private function repairedLineLooksLikeSplitWordPrefix(string $previous, string $line, array $splitWordHints = []): bool
    {
        $previous = trim($previous);
        $line = ltrim($line);
        if ($previous === '' || $line === '') {
            return false;
        }

        $continuation = $this->firstWordToken($line);
        $previousLength = $this->length($previous);
        $continuationLength = $this->length($continuation);

        if (preg_match('/^\p{L}+$/u', $previous) !== 1 || preg_match('/^\p{Ll}/u', $line) !== 1 || $continuationLength < 1) {
            return false;
        }

        if ($previousLength === 1) {
            return isset($splitWordHints[$this->splitWordHintKey($previous, $line)]);
        }

        return $previousLength <= 3 && $continuationLength <= 3;
    }

    /**
     * @param array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float}|null $previousLayout
     * @param array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float}|null $lineLayout
     */
    private function repairedLineShouldStartNewBlock(string $previous, string $line, ?array $previousLayout = null, ?array $lineLayout = null): bool
    {
        if ($this->lineHasPdfListBlockEvidence($line)) {
            return true;
        }
        if ($this->repairedLineLooksLikeSectionLabel($previous) || $this->repairedLineLooksLikeSectionLabel($line)) {
            return true;
        }
        if ($this->repairedPdfLayoutStartsNewBlock($previousLayout, $lineLayout)) {
            return true;
        }
        if ($this->lineHasPdfListBlockEvidence($previous) && !$this->lineHasPdfListBlockEvidence($line)) {
            if (preg_match('/[.!?]\s*$/u', $previous) !== 1 && preg_match('/^\p{Ll}/u', $line) === 1) {
                return false;
            }
            if ($previousLayout === null && $lineLayout === null && preg_match('/[.!?]\s*$/u', $previous) === 1 && preg_match('/^\p{Lu}/u', $line) === 1) {
                return true;
            }
            if ($this->repairedPdfLayoutLeavesListItem($previousLayout, $lineLayout)) {
                return true;
            }
        }
        if ($this->repairedPdfLayoutContinuesWrappedLine($previousLayout, $lineLayout)) {
            return false;
        }
        if ($this->looksLikeRepairedPdfTitle($this->repairGluedProseLine($previous))) {
            return true;
        }
        if ($previousLayout === null && $lineLayout === null && $this->lineEndsWithUrl($previous)) {
            return true;
        }

        $wordCount = str_word_count($line);
        if ($wordCount <= 6 && preg_match('/^[A-Z0-9][A-Za-z0-9,;:() \-]+$/', $line) === 1 && preg_match('/[.!?]$/', $previous) === 1) {
            return true;
        }
        if (preg_match('/[.!?]$/', $previous) === 1 && preg_match('/^[A-Z]/', $line) === 1 && $wordCount <= 10) {
            return true;
        }

        return false;
    }

    /**
     * @param array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float}|null $previousLayout
     * @param array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float}|null $lineLayout
     */
    private function repairedPdfLayoutStartsNewBlock(?array $previousLayout, ?array $lineLayout): bool
    {
        if ($previousLayout === null || $lineLayout === null) {
            return false;
        }
        if ($previousLayout['page'] !== $lineLayout['page']) {
            return true;
        }

        $previousHeight = max(1.0, $previousLayout['y2'] - $previousLayout['y1']);
        $lineHeight = max(1.0, $lineLayout['y2'] - $lineLayout['y1']);
        $referenceHeight = max($previousHeight, $lineHeight, $previousLayout['fontSize'], $lineLayout['fontSize'], 1.0);
        $verticalGap = $previousLayout['y1'] - $lineLayout['y2'];
        if ($verticalGap > $referenceHeight * 1.85) {
            return true;
        }

        $leftDelta = abs($previousLayout['x1'] - $lineLayout['x1']);
        if ($leftDelta > max(30.0, $referenceHeight * 3.0) && $verticalGap > $referenceHeight * 0.75) {
            return true;
        }

        return false;
    }

    /**
     * @param array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float}|null $previousLayout
     * @param array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float}|null $lineLayout
     */
    private function repairedPdfLayoutContinuesWrappedLine(?array $previousLayout, ?array $lineLayout): bool
    {
        if ($previousLayout === null || $lineLayout === null || $previousLayout['page'] !== $lineLayout['page']) {
            return false;
        }

        $previousHeight = max(1.0, $previousLayout['y2'] - $previousLayout['y1']);
        $lineHeight = max(1.0, $lineLayout['y2'] - $lineLayout['y1']);
        $referenceHeight = max($previousHeight, $lineHeight, $previousLayout['fontSize'], $lineLayout['fontSize'], 1.0);
        $verticalGap = $previousLayout['y1'] - $lineLayout['y2'];
        if ($verticalGap < -$referenceHeight * 0.4 || $verticalGap > $referenceHeight * 1.45) {
            return false;
        }

        return abs($previousLayout['x1'] - $lineLayout['x1']) <= max(8.0, $referenceHeight * 0.75);
    }

    /**
     * @param array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float}|null $previousLayout
     * @param array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float}|null $lineLayout
     */
    private function repairedPdfLayoutLeavesListItem(?array $previousLayout, ?array $lineLayout): bool
    {
        if ($previousLayout === null || $lineLayout === null) {
            return false;
        }
        if ($previousLayout['page'] !== $lineLayout['page']) {
            return true;
        }

        $previousHeight = max(1.0, $previousLayout['y2'] - $previousLayout['y1']);
        $lineHeight = max(1.0, $lineLayout['y2'] - $lineLayout['y1']);
        $referenceHeight = max($previousHeight, $lineHeight, $previousLayout['fontSize'], $lineLayout['fontSize'], 1.0);
        $verticalGap = $previousLayout['y1'] - $lineLayout['y2'];
        if ($verticalGap < -$referenceHeight * 0.4 || $verticalGap > $referenceHeight * 1.45) {
            return false;
        }

        return $lineLayout['x1'] <= $previousLayout['x1'] + max(8.0, $referenceHeight * 0.75);
    }

    private function lineEndsWithUrl(string $line): bool
    {
        return preg_match('/(?:https?:\/\/|www\.)\S+$/i', trim($line)) === 1;
    }

    private function repairedLineLooksLikeSectionLabel(string $line): bool
    {
        $line = trim($line);
        if ($line === '' || preg_match('/[.!?]$/u', $line) === 1) {
            return false;
        }
        $words = $this->pdfLineWordTokens($line);
        if (count($words) === 0 || count($words) > 6) {
            return false;
        }
        if (preg_match('/[:：]$/u', $line) === 1) {
            return true;
        }
        if (preg_match('/^\p{Lu}[\p{Lu}\p{N},;:() \-]+$/u', $line) === 1) {
            return true;
        }
        if (count($words) <= 3 && preg_match('/^\p{Lu}[\p{L}\p{N}&()\/ .-]*$/u', $line) === 1) {
            return true;
        }

        return $this->looksLikeRepairedPdfTitle($line);
    }

    private function looksLikeRepairedPdfTitle(string $line): bool
    {
        $wordCount = str_word_count($line);
        if ($wordCount < 3 || $wordCount > 12 || preg_match('/[.!?]$/', $line) === 1) {
            return false;
        }

        if (preg_match('/^[A-Z][A-Za-z0-9,;:() \-]+$/', $line) !== 1) {
            return false;
        }

        $words = preg_split('/\s+/', $line) ?: [];
        $titleLike = 0;
        $significant = 0;
        foreach ($words as $word) {
            $word = trim($word, " \t\n\r\0\x0B,;:()");
            if ($word === '') {
                continue;
            }
            $significant++;
            if (preg_match('/^[\p{Lu}\p{N}]/u', $word) === 1) {
                $titleLike++;
            }
        }

        return $significant > 0 && $titleLike / $significant >= 0.6;
    }

    /**
     * @param list<string> $lines
     * @return list<AstNode>
     */
    private function blocksFromLines(array $lines): array
    {
        $blocks = [];
        $pendingItems = [];
        $pendingOrdered = false;

        $flushList = function () use (&$blocks, &$pendingItems, &$pendingOrdered): void {
            if ($pendingItems === []) {
                return;
            }
            $blocks[] = new AstNode($pendingOrdered ? 'ordered_list' : 'bullet_list', [], $pendingItems);
            $pendingItems = [];
            $pendingOrdered = false;
        };

        $index = 0;
        $lineCount = count($lines);
        while ($index < $lineCount) {
            $line = $lines[$index];
            $tableRun = $this->tableRowsAt($lines, $index);
            if ($tableRun['rows'] !== []) {
                $flushList();
                $blocks[] = $this->table($tableRun['rows']);
                $index += $tableRun['consumed'];
                continue;
            }

            $listItem = $this->listItem($line);
            if ($listItem !== null) {
                [$ordered, $text] = $listItem;
                if ($pendingItems !== [] && $pendingOrdered !== $ordered) {
                    $flushList();
                }
                $pendingOrdered = $ordered;
                $pendingItems[] = new AstNode('list_item', [], [$this->paragraph($text)]);
                $index++;
                continue;
            }

            $flushList();
            $embeddedListBlocks = $this->blocksFromLineWithEmbeddedLists($line);
            if ($embeddedListBlocks !== null) {
                foreach ($embeddedListBlocks as $block) {
                    $blocks[] = $block;
                }
                $index++;
                continue;
            }

            if ($this->looksLikeHeading($line, $index, count($lines))) {
                $blocks[] = new AstNode('heading', ['level' => $index === 0 ? 1 : 2, 'text' => $line], $this->inlines($line));
                $index++;
                continue;
            }
            $blocks[] = $this->paragraph($line);
            $index++;
        }
        $flushList();

        return $blocks;
    }

    /**
     * @param list<array<string, mixed>> $runs
     * @param list<array<string, mixed>> $filledRectangles
     * @return list<AstNode>
     */
    private function blocksFromPositionedTables(array $runs, array $filledRectangles = []): array
    {
        if ($runs === []) {
            return [];
        }

        $runsByPage = [];
        foreach ($runs as $index => $run) {
            $run['_order'] = $index;
            $normalized = $this->positionedRun($run);
            if ($normalized === null) {
                continue;
            }
            $runsByPage[$normalized['page']][] = $normalized;
        }
        if ($runsByPage === []) {
            return [];
        }

        $filledRectanglesByPage = [];
        foreach ($filledRectangles as $rectangle) {
            $normalized = $this->positionedFillRectangle($rectangle);
            if ($normalized !== null) {
                $filledRectanglesByPage[$normalized['page']][] = $normalized;
            }
        }

        ksort($runsByPage);
        $blocksByPage = [];
        $hasTable = false;
        foreach ($runsByPage as $page => $pageRuns) {
            $pageBlocks = $this->blocksFromPositionedPageTables($pageRuns, $filledRectanglesByPage[$page] ?? []);
            if ($this->countNodesOfType($pageBlocks, 'table') > 0) {
                $hasTable = true;
            }
            $blocksByPage[$page] = $pageBlocks;
        }
        if (!$hasTable) {
            return [];
        }

        $blocks = [];
        foreach ($runsByPage as $page => $pageRuns) {
            $pageBlocks = $blocksByPage[$page] ?? [];
            if ($pageBlocks === []) {
                $pageBlocks = $this->blocksFromPositionedPageProse($pageRuns);
            }
            foreach ($pageBlocks as $block) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    /**
     * @param array<string, mixed> $run
     * @return array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float, startsWithWhitespace: bool, endsWithWhitespace: bool}|null
     */
    private function positionedRun(array $run): ?array
    {
        $rawText = $this->normalizePdfTextEncoding((string) ($run['text'] ?? ''));
        $text = $this->positionedCellText($rawText);
        $x1 = $this->numericValue($run['x1'] ?? null);
        $y1 = $this->numericValue($run['y1'] ?? null);
        $x2 = $this->numericValue($run['x2'] ?? null);
        $y2 = $this->numericValue($run['y2'] ?? null);
        $textX1 = $this->numericValue($run['textX1'] ?? null) ?? $x1;
        $textY1 = $this->numericValue($run['textY1'] ?? null) ?? $y1;
        $textX2 = $this->numericValue($run['textX2'] ?? null) ?? $x2;
        $textY2 = $this->numericValue($run['textY2'] ?? null) ?? $y2;
        if ($text === '' || $x1 === null || $y1 === null || $x2 === null || $y2 === null) {
            return null;
        }

        $fontSize = $this->numericValue($run['fontSize'] ?? null);

        return [
            'page' => max(1, (int) ($run['page'] ?? 1)),
            'text' => $text,
            'x1' => min($x1, $x2),
            'y1' => min($y1, $y2),
            'x2' => max($x1, $x2),
            'y2' => max($y1, $y2),
            'textX1' => min($textX1, $textX2),
            'textY1' => min($textY1, $textY2),
            'textX2' => max($textX1, $textX2),
            'textY2' => max($textY1, $textY2),
            'fontSize' => max(1.0, $fontSize ?? max(1.0, abs($y2 - $y1))),
            'startsWithWhitespace' => preg_match('/^\s/u', $rawText) === 1,
            'endsWithWhitespace' => preg_match('/\s$/u', $rawText) === 1,
            'order' => max(0, (int) ($run['_order'] ?? 0)),
        ];
    }

    /**
     * @param array<string, mixed> $rectangle
     * @return array{page: int, x1: float, y1: float, x2: float, y2: float, fillColor: string}|null
     */
    private function positionedFillRectangle(array $rectangle): ?array
    {
        $x1 = $this->numericValue($rectangle['x1'] ?? null);
        $y1 = $this->numericValue($rectangle['y1'] ?? null);
        $x2 = $this->numericValue($rectangle['x2'] ?? null);
        $y2 = $this->numericValue($rectangle['y2'] ?? null);
        $fillColor = $rectangle['fillColor'] ?? null;
        if ($x1 === null || $y1 === null || $x2 === null || $y2 === null || !is_string($fillColor) || preg_match('/^#[0-9a-f]{6}$/i', $fillColor) !== 1) {
            return null;
        }

        return [
            'page' => max(1, (int) ($rectangle['page'] ?? 1)),
            'x1' => min($x1, $x2),
            'y1' => min($y1, $y2),
            'x2' => max($x1, $x2),
            'y2' => max($y1, $y2),
            'fillColor' => strtolower($fillColor),
        ];
    }

    /**
     * @param list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}> $runs
     * @return list<list<string>>
     */
    private function positionedTableRowsForPage(array $runs, array $filledRectangles = []): array
    {
        if (count($runs) < 4) {
            return [];
        }

        $fontSizes = array_map(static fn (array $run): float => $run['fontSize'], $runs);
        $medianFontSize = $this->median($fontSizes);
        $rowTolerance = max(3.0, $medianFontSize * 0.55);
        $columnTolerance = max(8.0, $medianFontSize * 1.10);
        $rows = $this->mergePositionedRowFragments($this->clusterPositionedRows($runs, $rowTolerance));
        if (count($rows) < 2) {
            return [];
        }

        $columns = $this->positionedTableColumns($rows, $columnTolerance);
        if (count($columns) < 2) {
            return [];
        }

        $physicalRows = $this->positionedRowsWithCells($rows, $columns);
        $logicalRows = $this->trimSparsePositionedRows($this->compactSparsePositionedColumns($this->mergePositionedContinuationRows($physicalRows)));

        if (!$this->isPositionedTableCandidate($logicalRows, count($logicalRows[0] ?? [])) || $this->positionedRenderedRowsLookLikeFragmentGrid($logicalRows)) {
            return [];
        }

        return $this->withPositionedCellBackgrounds($logicalRows, $filledRectangles);
    }

    /**
     * @param list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}> $runs
     * @return list<AstNode>
     */
    private function blocksFromPositionedPageTables(array $runs, array $filledRectangles = []): array
    {
        if (count($runs) < 4) {
            return [];
        }

        $fontSizes = array_map(static fn (array $run): float => $run['fontSize'], $runs);
        $medianFontSize = $this->median($fontSizes);
        $rowTolerance = max(3.0, $medianFontSize * 0.55);
        $columnTolerance = max(8.0, $medianFontSize * 1.10);
        $rows = $this->mergePositionedRowFragments($this->clusterPositionedRows($runs, $rowTolerance));
        $segments = $this->positionedTableSegments($rows, $columnTolerance, $filledRectangles, $medianFontSize);
        if ($segments === []) {
            $pageRows = $this->positionedTableRowsForPage($runs, $filledRectangles);

            return $pageRows === [] ? [] : [$this->table($pageRows)];
        }

        $blocks = [];
        $pendingLines = [];
        $rowIndex = 0;
        $flushPendingLines = function () use (&$blocks, &$pendingLines): void {
            if ($pendingLines === []) {
                return;
            }
            $lines = $this->proseTextRepairEnabled()
                ? $this->repairProseTextLines($pendingLines, $this->looksLikeProseRepairCandidate($pendingLines))
                : $pendingLines;
            foreach ($this->blocksFromLines($lines) as $block) {
                $blocks[] = $block;
            }
            $pendingLines = [];
        };

        foreach ($segments as $segment) {
            while ($rowIndex < $segment['start']) {
                $line = $this->positionedRowText($rows[$rowIndex]);
                if ($line !== '') {
                    $pendingLines[] = $line;
                }
                $rowIndex++;
            }

            $flushPendingLines();
            $blocks[] = $this->table($segment['rows']);
            $rowIndex = $segment['end'] + 1;
        }

        while ($rowIndex < count($rows)) {
            $line = $this->positionedRowText($rows[$rowIndex]);
            if ($line !== '') {
                $pendingLines[] = $line;
            }
            $rowIndex++;
        }
        $flushPendingLines();

        return $blocks;
    }

    /**
     * @param list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}> $runs
     * @return list<AstNode>
     */
    private function blocksFromPositionedPageProse(array $runs): array
    {
        $lines = $this->positionedProseLinesForPage($runs);
        if ($lines === []) {
            return [];
        }
        if ($this->proseTextRepairEnabled()) {
            $lines = $this->repairProseTextLines($lines, $this->looksLikeProseRepairCandidate($lines));
        }

        return $this->blocksFromLines($lines);
    }

    /**
     * @param list<array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>}> $rows
     * @return list<array{start: int, end: int, rows: list<list<string>>}>
     */
    private function positionedTableSegments(array $rows, float $columnTolerance, array $filledRectangles = [], ?float $medianFontSize = null): array
    {
        $segments = [];
        $rowCount = count($rows);
        $index = 0;
        $medianFontSize ??= 10.0;
        while ($index < $rowCount) {
            if (!$this->positionedRowLooksMultiColumn($rows[$index]) || $this->positionedRowLooksStandaloneHeading($rows[$index], $medianFontSize)) {
                $index++;
                continue;
            }

            $end = $index;
            while ($end + 1 < $rowCount && !$this->positionedRowsHaveTableSegmentBreak($rows[$end], $rows[$end + 1], $medianFontSize)) {
                $end++;
            }

            if ($end === $index) {
                $index++;
                continue;
            }

            $logicalRows = $this->positionedTableRowsFromClusteredRows(
                array_slice($rows, $index, $end - $index + 1),
                $columnTolerance,
                $filledRectangles
            );
            if ($logicalRows === []) {
                $index++;
                continue;
            }

            $segments[] = [
                'start' => $index,
                'end' => $end,
                'rows' => $logicalRows,
            ];
            $index = $end + 1;
        }

        return $segments;
    }

    /**
     * @param list<array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>}> $rows
     * @return list<list<string>>
     */
    private function positionedTableRowsFromClusteredRows(array $rows, float $columnTolerance, array $filledRectangles = []): array
    {
        $columns = $this->positionedTableColumns($rows, $columnTolerance);
        if (count($columns) < 2) {
            return [];
        }

        $logicalRows = $this->compactSparsePositionedColumns($this->mergePositionedContinuationRows($this->positionedRowsWithCells($rows, $columns)));

        if (!$this->isPositionedTableCandidate($logicalRows, count($logicalRows[0] ?? [])) || $this->positionedRenderedRowsLookLikeFragmentGrid($logicalRows)) {
            return [];
        }

        return $this->withPositionedCellBackgrounds($logicalRows, $filledRectangles);
    }

    /**
     * @param array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>} $row
     */
    private function positionedRowLooksMultiColumn(array $row): bool
    {
        return count($row['runs']) >= 2;
    }

    /**
     * @param array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>} $current
     * @param array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>} $next
     */
    private function positionedRowsHaveTableSegmentBreak(array $current, array $next, float $medianFontSize): bool
    {
        $gap = $this->positionedRowGap($current, $next);
        $currentRuns = count($current['runs']);
        $nextRuns = count($next['runs']);

        if ($this->positionedRowLooksStandaloneHeading($current, $medianFontSize) || $this->positionedRowLooksStandaloneHeading($next, $medianFontSize)) {
            return true;
        }

        if ($gap >= max(24.0, $medianFontSize * 3.0)) {
            return true;
        }

        if ($currentRuns <= 1 && $nextRuns >= 3 && $gap >= max(12.0, $medianFontSize * 1.7)) {
            return true;
        }

        if ($currentRuns >= 3 && $nextRuns <= 1 && $gap >= max(20.0, $medianFontSize * 2.6)) {
            return true;
        }

        return false;
    }

    /**
     * @param array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>} $upper
     * @param array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>} $lower
     */
    private function positionedRowGap(array $upper, array $lower): float
    {
        return max(0.0, $upper['center'] - $lower['center']);
    }

    /**
     * @param array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>} $row
     */
    private function positionedRowMaxFontSize(array $row): float
    {
        $fontSize = 0.0;
        foreach ($row['runs'] as $run) {
            $fontSize = max($fontSize, (float) $run['fontSize']);
        }

        return $fontSize;
    }

    /**
     * @param array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>} $row
     */
    private function positionedRowLooksStandaloneHeading(array $row, float $medianFontSize): bool
    {
        if (count($row['runs']) !== 1) {
            return false;
        }

        $text = trim($this->positionedRowText($row));
        if ($text === '' || $this->length($text) > 80) {
            return false;
        }

        return $this->positionedRowMaxFontSize($row) >= max(9.0, $medianFontSize * 1.25);
    }

    /**
     * @param array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>} $row
     */
    private function positionedRowText(array $row): string
    {
        $text = '';
        foreach ($row['runs'] as $run) {
            $text = $this->appendCellText($text, $run['text']);
        }

        return $text;
    }

    /**
     * @param list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}> $runs
     * @return list<array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>}>
     */
    private function clusterPositionedRows(array $runs, float $tolerance): array
    {
        usort($runs, static function (array $left, array $right): int {
            $leftCenter = self::positionedRunRowCenter($left);
            $rightCenter = self::positionedRunRowCenter($right);

            return ($rightCenter <=> $leftCenter) ?: ($left['x1'] <=> $right['x1']);
        });

        $rows = [];
        foreach ($runs as $run) {
            $center = self::positionedRunRowCenter($run);
            $matchedIndex = null;
            foreach ($rows as $index => $row) {
                $minCenter = min((float) ($row['minCenter'] ?? $row['center']), $center);
                $maxCenter = max((float) ($row['maxCenter'] ?? $row['center']), $center);
                if (abs($center - $row['center']) <= $tolerance && ($maxCenter - $minCenter) <= $tolerance) {
                    $matchedIndex = $index;
                    break;
                }
            }

            if ($matchedIndex === null) {
                $rows[] = [
                    'center' => $center,
                    'minCenter' => $center,
                    'maxCenter' => $center,
                    'runs' => [$run],
                ];
                continue;
            }

            $count = count($rows[$matchedIndex]['runs']);
            $rows[$matchedIndex]['center'] = (($rows[$matchedIndex]['center'] * $count) + $center) / ($count + 1);
            $rows[$matchedIndex]['minCenter'] = min((float) ($rows[$matchedIndex]['minCenter'] ?? $center), $center);
            $rows[$matchedIndex]['maxCenter'] = max((float) ($rows[$matchedIndex]['maxCenter'] ?? $center), $center);
            $rows[$matchedIndex]['runs'][] = $run;
        }

        usort($rows, static fn (array $left, array $right): int => $right['center'] <=> $left['center']);
        foreach ($rows as &$row) {
            $row['runs'] = $this->removeOverprintedPositionedRuns($row['runs']);
            usort($row['runs'], static fn (array $left, array $right): int => $left['x1'] <=> $right['x1']);
            unset($row['minCenter'], $row['maxCenter']);
        }
        unset($row);

        return $rows;
    }

    /**
     * @param list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float, order?: int}> $runs
     * @return list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float, order?: int}>
     */
    private function removeOverprintedPositionedRuns(array $runs): array
    {
        if (count($runs) < 4) {
            return $runs;
        }

        $ordered = $runs;
        usort($ordered, static fn (array $left, array $right): int => ((int) ($left['order'] ?? 0)) <=> ((int) ($right['order'] ?? 0)));

        $layers = [];
        $current = [];
        $currentRight = null;
        foreach ($ordered as $run) {
            if ($current !== [] && $currentRight !== null && $run['x1'] < $currentRight - max(6.0, $run['fontSize'] * 2.0)) {
                $layers[] = $current;
                $current = [];
                $currentRight = null;
            }
            $current[] = $run;
            $currentRight = max($currentRight ?? $run['x2'], $run['x2']);
        }
        if ($current !== []) {
            $layers[] = $current;
        }

        if (count($layers) < 2) {
            return $runs;
        }

        $kept = [];
        foreach ($layers as $layer) {
            $duplicateIndex = null;
            foreach ($kept as $index => $keptLayer) {
                if ($this->positionedRunLayersLookOverprinted($keptLayer, $layer)) {
                    $duplicateIndex = $index;
                    break;
                }
            }
            if ($duplicateIndex === null) {
                $kept[] = $layer;
                continue;
            }
            $keptText = $this->normalizedPositionedLayerText($kept[$duplicateIndex]);
            $layerText = $this->normalizedPositionedLayerText($layer);
            if ($keptText !== '' && $layerText !== '' && str_ends_with($keptText, $layerText)) {
                continue;
            }
            if (($keptText === '' || !str_ends_with($layerText, $keptText))
                && $this->positionedRunLayerArea($layer) >= $this->positionedRunLayerArea($kept[$duplicateIndex])) {
                continue;
            }
            if ($layerText !== '' && ($keptText === '' || str_ends_with($layerText, $keptText) || $this->positionedRunLayerArea($layer) < $this->positionedRunLayerArea($kept[$duplicateIndex]))) {
                $kept[$duplicateIndex] = $layer;
            }
        }

        if (count($kept) === count($layers)) {
            return $runs;
        }

        return array_merge(...$kept);
    }

    /**
     * @param list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}> $left
     * @param list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}> $right
     */
    private function positionedRunLayersLookOverprinted(array $left, array $right): bool
    {
        $leftText = $this->normalizedPositionedLayerText($left);
        $rightText = $this->normalizedPositionedLayerText($right);
        if ($leftText === '' || $rightText === '') {
            return false;
        }
        if ($leftText !== $rightText && !str_ends_with($leftText, $rightText) && !str_ends_with($rightText, $leftText)) {
            return false;
        }

        $leftBounds = $this->positionedRunsBounds($left);
        $rightBounds = $this->positionedRunsBounds($right);
        $overlapWidth = min($leftBounds['x2'], $rightBounds['x2']) - max($leftBounds['x1'], $rightBounds['x1']);
        $overlapHeight = min($leftBounds['y2'], $rightBounds['y2']) - max($leftBounds['y1'], $rightBounds['y1']);
        if ($overlapWidth <= 0.0 || $overlapHeight < -2.0) {
            return false;
        }

        $leftWidth = max(1.0, $leftBounds['x2'] - $leftBounds['x1']);
        $rightWidth = max(1.0, $rightBounds['x2'] - $rightBounds['x1']);

        return $overlapWidth / min($leftWidth, $rightWidth) >= 0.82;
    }

    /**
     * @param list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}> $runs
     */
    private function positionedRunLayerArea(array $runs): float
    {
        $bounds = $this->positionedRunsBounds($runs);

        return max(1.0, $bounds['x2'] - $bounds['x1']) * max(1.0, $bounds['y2'] - $bounds['y1']);
    }

    /**
     * @param list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}> $runs
     * @return array{x1: float, y1: float, x2: float, y2: float}
     */
    private function positionedRunsBounds(array $runs): array
    {
        $bounds = ['x1' => INF, 'y1' => INF, 'x2' => -INF, 'y2' => -INF];
        foreach ($runs as $run) {
            $bounds['x1'] = min($bounds['x1'], $run['x1'], $run['textX1']);
            $bounds['y1'] = min($bounds['y1'], $run['y1'], $run['textY1']);
            $bounds['x2'] = max($bounds['x2'], $run['x2'], $run['textX2']);
            $bounds['y2'] = max($bounds['y2'], $run['y2'], $run['textY2']);
        }

        return is_finite($bounds['x1']) && is_finite($bounds['x2']) && is_finite($bounds['y1']) && is_finite($bounds['y2'])
            ? $bounds
            : ['x1' => 0.0, 'y1' => 0.0, 'x2' => 0.0, 'y2' => 0.0];
    }

    /**
     * @param list<array{text: string, x1: float}> $runs
     */
    private function positionedRunsText(array $runs): string
    {
        usort($runs, static fn (array $left, array $right): int => $left['x1'] <=> $right['x1']);
        $text = '';
        foreach ($runs as $run) {
            $text = $this->appendCellText($text, $run['text']);
        }

        return $text;
    }

    /**
     * @param list<array{text: string, x1: float}> $runs
     */
    private function normalizedPositionedLayerText(array $runs): string
    {
        return preg_replace('/\s+/u', '', $this->positionedRunsText($runs)) ?? '';
    }

    /**
     * @param array{textY1: float, textY2: float, y1: float, y2: float} $run
     */
    private static function positionedRunRowCenter(array $run): float
    {
        $textY1 = (float) $run['textY1'];
        $textY2 = (float) $run['textY2'];
        if (abs($textY2 - $textY1) <= max(0.5, abs($run['y2'] - $run['y1']) * 0.35)) {
            return ($textY1 + $textY2) / 2.0;
        }

        return ((float) $run['y1'] + (float) $run['y2']) / 2.0;
    }

    /**
     * @param list<array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>}> $rows
     * @return list<array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>}>
     */
    private function mergePositionedRowFragments(array $rows): array
    {
        foreach ($rows as &$row) {
            $merged = [];
            foreach ($row['runs'] as $run) {
                $lastIndex = array_key_last($merged);
                if ($lastIndex === null) {
                    $merged[] = $run;
                    continue;
                }

                $last = $merged[$lastIndex];
                $gap = $run['textX1'] - $last['textX2'];
                $mergeGap = max(4.0, max($run['fontSize'], $last['fontSize']) * 1.25);
                if ($gap <= $mergeGap) {
                    $merged[$lastIndex] = [
                        'page' => $last['page'],
                        'text' => $this->joinPositionedCellText(
                            $last['text'],
                            $run['text'],
                            $gap,
                            max($run['fontSize'], $last['fontSize']),
                            (bool) ($last['endsWithWhitespace'] ?? false),
                            (bool) ($run['startsWithWhitespace'] ?? false)
                        ),
                        'x1' => min($last['x1'], $run['x1']),
                        'y1' => min($last['y1'], $run['y1']),
                        'x2' => max($last['x2'], $run['x2']),
                        'y2' => max($last['y2'], $run['y2']),
                        'textX1' => min($last['textX1'], $run['textX1']),
                        'textY1' => min($last['textY1'], $run['textY1']),
                        'textX2' => max($last['textX2'], $run['textX2']),
                        'textY2' => max($last['textY2'], $run['textY2']),
                        'fontSize' => max($run['fontSize'], $last['fontSize']),
                        'startsWithWhitespace' => (bool) ($last['startsWithWhitespace'] ?? false),
                        'endsWithWhitespace' => (bool) ($run['endsWithWhitespace'] ?? false),
                    ];
                    continue;
                }

                $merged[] = $run;
            }
            $row['runs'] = $merged;
        }
        unset($row);

        return $rows;
    }

    /**
     * @param list<array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>}> $rows
     * @return list<float>
     */
    private function positionedTableColumns(array $rows, float $tolerance): array
    {
        $positions = [];
        foreach ($rows as $row) {
            foreach ($row['runs'] as $run) {
                $positions[] = $run['textX1'];
            }
        }
        sort($positions, SORT_NUMERIC);

        $clusters = [];
        foreach ($positions as $x) {
            $matchedIndex = null;
            foreach ($clusters as $index => $cluster) {
                if (abs($x - $cluster['center']) <= $tolerance) {
                    $matchedIndex = $index;
                    break;
                }
            }
            if ($matchedIndex === null) {
                $clusters[] = ['center' => $x, 'count' => 1];
                continue;
            }

            $count = $clusters[$matchedIndex]['count'];
            $clusters[$matchedIndex]['center'] = (($clusters[$matchedIndex]['center'] * $count) + $x) / ($count + 1);
            $clusters[$matchedIndex]['count'] = $count + 1;
        }

        $columns = array_map(static fn (array $cluster): float => $cluster['center'], $clusters);
        sort($columns, SORT_NUMERIC);

        return $columns;
    }

    /**
     * @param list<array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>}> $rows
     * @param list<float> $columns
     * @return list<list<string>>
     */
    private function positionedRowsWithCells(array $rows, array $columns): array
    {
        $tableBounds = $this->positionedRowsBounds($rows);
        $columnBounds = $this->positionedColumnBounds($columns, $tableBounds['x1'], $tableBounds['x2']);
        $cellRows = [];
        foreach ($rows as $row) {
            $rowBounds = $this->positionedRowBounds($row);
            $cells = [];
            foreach ($columns as $index => $_column) {
                $cells[$index] = $this->emptyPositionedCell($rowBounds, $columnBounds[$index] ?? null);
            }
            foreach ($row['runs'] as $run) {
                $columnIndex = $this->nearestPositionedColumn($columns, $run['textX1']);
                $cells[$columnIndex] = $this->mergePositionedCells($cells[$columnIndex], $this->positionedCellFromRun($run));
            }
            $cellRows[] = array_map(fn (mixed $cell): array => is_array($cell) ? $cell : $this->emptyPositionedCell(), $cells);
        }

        return $cellRows;
    }

    /**
     * @param list<array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>}> $rows
     * @return array{x1: float, y1: float, x2: float, y2: float}
     */
    private function positionedRowsBounds(array $rows): array
    {
        $bounds = ['x1' => INF, 'y1' => INF, 'x2' => -INF, 'y2' => -INF];
        foreach ($rows as $row) {
            foreach ($row['runs'] as $run) {
                $bounds['x1'] = min($bounds['x1'], $run['x1'], $run['textX1']);
                $bounds['y1'] = min($bounds['y1'], $run['y1'], $run['textY1']);
                $bounds['x2'] = max($bounds['x2'], $run['x2'], $run['textX2']);
                $bounds['y2'] = max($bounds['y2'], $run['y2'], $run['textY2']);
            }
        }

        return is_finite($bounds['x1']) && is_finite($bounds['x2']) && is_finite($bounds['y1']) && is_finite($bounds['y2'])
            ? $bounds
            : ['x1' => 0.0, 'y1' => 0.0, 'x2' => 0.0, 'y2' => 0.0];
    }

    /**
     * @param array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>} $row
     * @return array{y1: float, y2: float}
     */
    private function positionedRowBounds(array $row): array
    {
        $y1 = INF;
        $y2 = -INF;
        foreach ($row['runs'] as $run) {
            $y1 = min($y1, $run['y1'], $run['textY1']);
            $y2 = max($y2, $run['y2'], $run['textY2']);
        }

        return is_finite($y1) && is_finite($y2)
            ? ['y1' => $y1, 'y2' => $y2]
            : ['y1' => $row['center'], 'y2' => $row['center']];
    }

    /**
     * @param list<float> $columns
     * @return list<array{x1: float, x2: float}>
     */
    private function positionedColumnBounds(array $columns, float $tableX1, float $tableX2): array
    {
        $bounds = [];
        $count = count($columns);
        for ($index = 0; $index < $count; $index++) {
            $left = $index === 0
                ? min($tableX1, $columns[$index])
                : ($columns[$index - 1] + $columns[$index]) / 2.0;
            $right = $index + 1 < $count
                ? ($columns[$index] + $columns[$index + 1]) / 2.0
                : max($tableX2, $columns[$index]);

            if ($right <= $left) {
                $left = $columns[$index] - 4.0;
                $right = $columns[$index] + 4.0;
            }

            $bounds[] = ['x1' => $left, 'x2' => $right];
        }

        return $bounds;
    }

    /**
     * @param array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float} $run
     * @return array{text: string, x1: float, y1: float, x2: float, y2: float, contentX1: float, contentX2: float}
     */
    private function positionedCellFromRun(array $run): array
    {
        return [
            'text' => $run['text'],
            'x1' => $run['textX1'],
            'y1' => $run['y1'],
            'x2' => $run['textX2'],
            'y2' => $run['y2'],
            'contentX1' => $run['textX1'],
            'contentX2' => $run['textX2'],
        ];
    }

    /**
     * @param array{y1: float, y2: float}|null $rowBounds
     * @param array{x1: float, x2: float}|null $columnBounds
     * @return array<string, mixed>
     */
    private function emptyPositionedCell(?array $rowBounds = null, ?array $columnBounds = null): array
    {
        $cell = ['text' => ''];
        if ($rowBounds !== null && $columnBounds !== null) {
            $cell['x1'] = $columnBounds['x1'];
            $cell['y1'] = $rowBounds['y1'];
            $cell['x2'] = $columnBounds['x2'];
            $cell['y2'] = $rowBounds['y2'];
        }

        return $cell;
    }

    /**
     * @param array<string, mixed>|null $left
     * @param array<string, mixed> $right
     * @return array<string, mixed>
     */
    private function mergePositionedCells(?array $left, array $right): array
    {
        if ($left === null) {
            return $right;
        }

        $leftText = $this->cellTextValue($left);
        $rightText = $this->cellTextValue($right);
        $merged = array_replace($left, array_diff_key($right, array_flip(['text', 'x1', 'y1', 'x2', 'y2', 'contentX1', 'contentX2'])));
        $merged['text'] = $leftText === ''
            ? $rightText
            : ($rightText === '' ? $leftText : $this->appendCellText($leftText, $rightText));

        foreach (['x1', 'y1', 'x2', 'y2', 'contentX1', 'contentX2'] as $key) {
            $leftValue = $this->numericValue($left[$key] ?? null);
            $rightValue = $this->numericValue($right[$key] ?? null);
            if ($leftValue === null && $rightValue === null) {
                continue;
            }
            if ($leftValue === null) {
                $merged[$key] = $rightValue;
                continue;
            }
            if ($rightValue === null) {
                $merged[$key] = $leftValue;
                continue;
            }
            $merged[$key] = in_array($key, ['x1', 'y1', 'contentX1'], true)
                ? min($leftValue, $rightValue)
                : max($leftValue, $rightValue);
        }

        return $merged;
    }

    /**
     * @param list<list<mixed>> $rows
     * @param list<array{page: int, x1: float, y1: float, x2: float, y2: float, fillColor: string}> $filledRectangles
     * @return list<list<mixed>>
     */
    private function withPositionedCellBackgrounds(array $rows, array $filledRectangles): array
    {
        if ($filledRectangles === []) {
            return $rows;
        }

        foreach ($rows as &$row) {
            foreach ($row as &$cell) {
                if (!is_array($cell)) {
                    continue;
                }

                $fillColor = $this->positionedCellBackgroundColor($cell, $filledRectangles);
                if ($fillColor === null) {
                    continue;
                }

                $htmlAttributes = is_array($cell['htmlAttributes'] ?? null) ? $cell['htmlAttributes'] : [];
                $style = trim((string) ($htmlAttributes['style'] ?? ''));
                if (preg_match('/(?:^|;)\s*background(?:-color)?\s*:/i', $style) !== 1) {
                    $style = trim($style === '' ? 'background-color:' . $fillColor : rtrim($style, ';') . '; background-color:' . $fillColor);
                    $htmlAttributes['style'] = $style;
                }
                $htmlAttributes['data-pdf-fill-color'] = $fillColor;
                $cell['htmlAttributes'] = $htmlAttributes;
            }
            unset($cell);
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<string, mixed> $cell
     * @param list<array{page: int, x1: float, y1: float, x2: float, y2: float, fillColor: string}> $filledRectangles
     */
    private function positionedCellBackgroundColor(array $cell, array $filledRectangles): ?string
    {
        $x1 = $this->numericValue($cell['x1'] ?? null);
        $y1 = $this->numericValue($cell['y1'] ?? null);
        $x2 = $this->numericValue($cell['x2'] ?? null);
        $y2 = $this->numericValue($cell['y2'] ?? null);
        if ($x1 === null || $y1 === null || $x2 === null || $y2 === null) {
            return null;
        }

        $centerX = ($x1 + $x2) / 2.0;
        $centerY = ($y1 + $y2) / 2.0;
        $bestColor = null;
        $bestArea = INF;
        foreach ($filledRectangles as $rectangle) {
            if (!$this->pointInsideRectangle($centerX, $centerY, $rectangle, 2.0)) {
                continue;
            }

            $area = max(0.0, ($rectangle['x2'] - $rectangle['x1']) * ($rectangle['y2'] - $rectangle['y1']));
            if ($area < $bestArea) {
                $bestArea = $area;
                $bestColor = $rectangle['fillColor'];
            }
        }

        return $bestColor;
    }

    /**
     * @param array{x1: float, y1: float, x2: float, y2: float} $rectangle
     */
    private function pointInsideRectangle(float $x, float $y, array $rectangle, float $tolerance): bool
    {
        return $x >= $rectangle['x1'] - $tolerance
            && $x <= $rectangle['x2'] + $tolerance
            && $y >= $rectangle['y1'] - $tolerance
            && $y <= $rectangle['y2'] + $tolerance;
    }

    /**
     * @param list<float> $columns
     */
    private function nearestPositionedColumn(array $columns, float $x): int
    {
        $nearestIndex = 0;
        $nearestDistance = INF;
        foreach ($columns as $index => $columnX) {
            $distance = abs($x - $columnX);
            if ($distance < $nearestDistance) {
                $nearestDistance = $distance;
                $nearestIndex = $index;
            }
        }

        return $nearestIndex;
    }

    /**
     * @param list<list<string>> $rows
     * @return list<list<string>>
     */
    private function mergePositionedContinuationRows(array $rows): array
    {
        $logicalRows = [];
        foreach ($rows as $row) {
            $populated = $this->populatedCellIndexes($row);
            if ($populated === []) {
                continue;
            }

            if (count($populated) === 1 && $logicalRows !== []) {
                $lastIndex = array_key_last($logicalRows);
                $columnIndex = $populated[0];
                $logicalRows[$lastIndex][$columnIndex] = $this->mergePositionedCells(
                    is_array($logicalRows[$lastIndex][$columnIndex]) ? $logicalRows[$lastIndex][$columnIndex] : ['text' => (string) $logicalRows[$lastIndex][$columnIndex]],
                    is_array($row[$columnIndex]) ? $row[$columnIndex] : ['text' => (string) $row[$columnIndex]]
                );
                continue;
            }

            $logicalRows[] = $row;
        }

        return $logicalRows;
    }

    /**
     * @param list<list<mixed>> $rows
     * @return list<list<mixed>>
     */
    private function compactSparsePositionedColumns(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $columnCount = 0;
        foreach ($rows as $row) {
            $columnCount = max($columnCount, count($row));
        }
        if ($columnCount < 2) {
            return $rows;
        }

        foreach ($rows as &$row) {
            while (count($row) < $columnCount) {
                $row[] = $this->emptyPositionedCell();
            }
        }
        unset($row);

        $columnIndex = 0;
        while ($columnIndex < $columnCount - 1) {
            if (!$this->canMergeSparsePositionedColumns($rows, $columnIndex, $columnIndex + 1)) {
                $columnIndex++;
                continue;
            }

            foreach ($rows as &$row) {
                $left = is_array($row[$columnIndex]) ? $row[$columnIndex] : ['text' => (string) $row[$columnIndex]];
                $right = is_array($row[$columnIndex + 1]) ? $row[$columnIndex + 1] : ['text' => (string) $row[$columnIndex + 1]];
                $row[$columnIndex] = $this->mergePositionedCells($left, $right);
                array_splice($row, $columnIndex + 1, 1);
            }
            unset($row);
            $columnCount--;
        }

        return $rows;
    }

    /**
     * @param list<list<mixed>> $rows
     */
    private function canMergeSparsePositionedColumns(array $rows, int $leftColumn, int $rightColumn): bool
    {
        $leftHasContent = false;
        $rightHasContent = false;

        foreach ($rows as $row) {
            $leftText = $this->cellTextValue($row[$leftColumn] ?? '');
            $rightText = $this->cellTextValue($row[$rightColumn] ?? '');
            if ($leftText !== '' && $rightText !== '') {
                return false;
            }
            $leftHasContent = $leftHasContent || $leftText !== '';
            $rightHasContent = $rightHasContent || $rightText !== '';
        }

        return $leftHasContent && $rightHasContent;
    }

    /**
     * @param list<list<string>> $rows
     * @return list<list<string>>
     */
    private function trimSparsePositionedRows(array $rows): array
    {
        while ($rows !== [] && count($this->populatedCellIndexes($rows[0])) < 2) {
            array_shift($rows);
        }
        while ($rows !== [] && count($this->populatedCellIndexes($rows[array_key_last($rows)])) < 2) {
            array_pop($rows);
        }

        return array_values($rows);
    }

    /**
     * @param list<list<string>> $rows
     */
    private function isPositionedTableCandidate(array $rows, int $columnCount): bool
    {
        if ($columnCount < 2 || count($rows) < 2) {
            return false;
        }
        if (count($rows) === 2 && !$this->positionedTableHasHorizontalCellText($rows)) {
            return false;
        }
        if ($this->positionedRowsLookLikeSparseProseGrid($rows, $columnCount)) {
            return false;
        }

        $multiCellRows = 0;
        $columnOccupancy = array_fill(0, $columnCount, 0);
        foreach ($rows as $row) {
            $populated = $this->populatedCellIndexes($row);
            if (count($populated) >= 2) {
                $multiCellRows++;
            }
            foreach ($populated as $columnIndex) {
                $columnOccupancy[$columnIndex]++;
            }
        }

        $recurringColumns = 0;
        foreach ($columnOccupancy as $count) {
            if ($count >= 2) {
                $recurringColumns++;
            }
        }

        if ($multiCellRows < 2 || $recurringColumns < 2) {
            return false;
        }

        $confidence = $this->positionedTableConfidence($rows, $columnCount, $multiCellRows, $recurringColumns);
        if ($confidence < 0.72) {
            $this->lowConfidenceGeometryTableCandidates++;

            return false;
        }

        return true;
    }

    /**
     * @param list<list<mixed>> $rows
     */
    private function positionedTableConfidence(array $rows, int $columnCount, int $multiCellRows, int $recurringColumns): float
    {
        $rowCount = count($rows);
        if ($rowCount === 0 || $columnCount === 0) {
            return 0.0;
        }

        $populatedCells = 0;
        $numericAnchors = 0;
        $wideCells = 0;
        foreach ($rows as $row) {
            foreach ($row as $cell) {
                $text = trim($this->cellTextValue($cell));
                if ($text === '') {
                    continue;
                }
                $populatedCells++;
                if ($this->positionedCellLooksNumericAnchor($text)) {
                    $numericAnchors++;
                }
                if (is_array($cell)) {
                    $x1 = $this->numericValue($cell['contentX1'] ?? $cell['x1'] ?? null);
                    $x2 = $this->numericValue($cell['contentX2'] ?? $cell['x2'] ?? null);
                    if ($x1 !== null && $x2 !== null && abs($x2 - $x1) > 1.0) {
                        $wideCells++;
                    }
                }
            }
        }

        $totalCells = max(1, $rowCount * $columnCount);
        $fillRatio = $populatedCells / $totalCells;
        $multiCellRowRatio = $multiCellRows / max(1, $rowCount);
        $recurringColumnRatio = $recurringColumns / max(1, $columnCount);
        $numericRatio = $numericAnchors / max(1, $populatedCells);
        $wideCellRatio = $wideCells / max(1, $populatedCells);

        $score = 0.0;
        $score += $rowCount >= 3 ? 0.18 : 0.08;
        $score += $columnCount >= 3 ? 0.18 : 0.08;
        $score += 0.20 * min(1.0, $multiCellRowRatio);
        $score += 0.20 * min(1.0, $recurringColumnRatio);
        $score += $fillRatio >= 0.70 ? 0.16 : ($fillRatio >= 0.50 ? 0.08 : 0.0);
        $score += $numericRatio >= 0.20 ? 0.14 : ($numericAnchors >= 2 ? 0.10 : 0.0);
        $score += $wideCellRatio >= 0.75 ? 0.08 : ($wideCellRatio >= 0.50 ? 0.04 : 0.0);

        if ($rowCount <= 2 && $columnCount <= 2 && $numericAnchors < 2) {
            $score = min($score, 0.55);
        }
        if ($columnCount === 2 && $rowCount < 4 && $numericAnchors < 2) {
            $score = min($score, 0.65);
        }

        return round(min(1.0, $score), 4);
    }

    /**
     * @param list<list<mixed>> $rows
     */
    private function positionedRowsLookLikeSparseProseGrid(array $rows, int $columnCount): bool
    {
        if ($columnCount < 5 || count($rows) < 2) {
            return false;
        }

        $totalCells = 0;
        $populatedCells = 0;
        $shortWordCells = 0;
        $numericCells = 0;
        $numericRows = 0;
        foreach ($rows as $row) {
            $rowNumericCells = 0;
            for ($index = 0; $index < $columnCount; $index++) {
                $totalCells++;
                $text = trim($this->cellTextValue($row[$index] ?? ''));
                if ($text === '') {
                    continue;
                }
                $populatedCells++;
                if ($this->positionedCellLooksNumericAnchor($text)) {
                    $numericCells++;
                    $rowNumericCells++;
                }
                if ($this->positionedCellWordCount($text) <= 2 && !$this->positionedCellLooksNumericAnchor($text)) {
                    $shortWordCells++;
                }
            }
            if ($rowNumericCells >= 2) {
                $numericRows++;
            }
        }

        if ($populatedCells < 4 || $totalCells === 0) {
            return true;
        }

        $emptyRatio = 1.0 - ($populatedCells / $totalCells);
        $shortWordRatio = $shortWordCells / max(1, $populatedCells);
        $numericRatio = $numericCells / max(1, $populatedCells);

        if ($emptyRatio >= 0.35 && $shortWordRatio >= 0.45 && $numericRatio < 0.18 && $numericRows < 2) {
            return true;
        }

        if ($columnCount >= 5 && $shortWordRatio >= 0.65 && $numericRows < 2) {
            return true;
        }

        if ($columnCount >= 4 && $shortWordRatio >= 0.75 && $numericCells === 0) {
            return true;
        }

        return $columnCount >= 8
            && $emptyRatio >= 0.20
            && $numericRatio < 0.12
            && $numericRows < 2;
    }

    private function positionedCellLooksNumericAnchor(string $text): bool
    {
        return preg_match('/(?:[$€£¥]\s*\d|\d[\d,]*(?:\.\d+)?\s*(?:%|[$€£¥])?|\b\d{1,2}[\/.-]\d{1,2}(?:[\/.-]\d{2,4})?\b)/u', $text) === 1;
    }

    private function positionedCellWordCount(string $text): int
    {
        preg_match_all('/[\pL\pN]+/u', $text, $matches);

        return count($matches[0] ?? []);
    }

    /**
     * @param list<list<mixed>> $rows
     */
    private function positionedRenderedRowsLookLikeFragmentGrid(array $rows): bool
    {
        $columnCount = 0;
        foreach ($rows as $row) {
            $columnCount = max($columnCount, count($row));
        }
        if ($columnCount < 4) {
            return false;
        }

        $wordCounts = [];
        $numericAnchors = 0;
        $oneWordCells = 0;
        foreach ($rows as $row) {
            foreach ($row as $cell) {
                $text = trim($this->cellTextValue($cell));
                if ($text === '') {
                    continue;
                }
                if ($this->positionedCellLooksNumericAnchor($text)) {
                    $numericAnchors++;
                }
                $wordCount = $this->positionedCellWordCount($text);
                if ($wordCount <= 1) {
                    $oneWordCells++;
                }
                $wordCounts[] = $wordCount;
            }
        }
        if (count($wordCounts) < 4) {
            return false;
        }

        sort($wordCounts, SORT_NUMERIC);
        $median = $wordCounts[intdiv(count($wordCounts), 2)] ?? 0;
        if ($numericAnchors > 0 && $columnCount < 18) {
            return false;
        }
        if ($numericAnchors > 0) {
            $oneWordRatio = $oneWordCells / max(1, count($wordCounts));
            $numericRatio = $numericAnchors / max(1, count($wordCounts));
            if ($median <= 1 && $oneWordRatio >= 0.80 && $numericRatio < 0.55) {
                return true;
            }

            return false;
        }

        return $median <= 2;
    }

    /**
     * @param list<list<mixed>> $rows
     */
    private function positionedTableHasHorizontalCellText(array $rows): bool
    {
        $wideCells = 0;
        foreach ($rows as $row) {
            foreach ($row as $cell) {
                if (!is_array($cell) || $this->cellTextValue($cell) === '') {
                    continue;
                }
                $x1 = $this->numericValue($cell['contentX1'] ?? $cell['x1'] ?? null);
                $x2 = $this->numericValue($cell['contentX2'] ?? $cell['x2'] ?? null);
                if ($x1 !== null && $x2 !== null && abs($x2 - $x1) > 1.0) {
                    $wideCells++;
                }
            }
        }

        return $wideCells >= 2;
    }

    /**
     * @param list<list<list<string>>> $tables
     * @param list<string> $limitedLines
     */
    private function positionedTablesLikelyCoverText(array $tables, array $limitedLines): bool
    {
        if ($limitedLines === []) {
            return true;
        }

        $tableText = '';
        foreach ($tables as $table) {
            foreach ($table as $row) {
                $tableText .= ' ' . implode(' ', $row);
            }
        }

        $lineTokens = $this->significantTextTokens(implode(' ', $limitedLines));
        if (count($lineTokens) < 4) {
            return true;
        }

        $tableTokens = array_flip($this->significantTextTokens($tableText));
        $matched = 0;
        foreach ($lineTokens as $token) {
            if (isset($tableTokens[$token])) {
                $matched++;
            }
        }

        return $matched / count($lineTokens) >= 0.60;
    }

    /**
     * @return list<string>
     */
    private function significantTextTokens(string $text): array
    {
        if (preg_match('//u', $text) !== 1) {
            $scrubbed = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
            $text = is_string($scrubbed) ? $scrubbed : (preg_replace('/[^\x20-\x7E]+/', ' ', $text) ?? $text);
        }

        $normalized = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
        if (preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}._-]*/u', $normalized, $matches) === false) {
            if (preg_match_all('/[A-Za-z0-9][A-Za-z0-9._-]*/', strtolower($text), $fallbackMatches) === false) {
                return [];
            }

            $matches = $fallbackMatches;
        }

        return array_values(array_filter($matches[0], static fn (string $token): bool => strlen($token) >= 2));
    }

    private function positionedCellText(string $text): string
    {
        $text = str_replace("\0", '', $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function joinPositionedCellText(
        string $left,
        string $right,
        float $gap,
        float $fontSize,
        bool $leftEndsWithWhitespace = false,
        bool $rightStartsWithWhitespace = false
    ): string
    {
        $separator = ($leftEndsWithWhitespace
            || $rightStartsWithWhitespace
            || $gap > max(1.0, $fontSize * 0.35)
            || $this->positionedGapLooksLikeWordBoundary($gap, $fontSize, $left, $right)) ? ' ' : '';

        return $this->positionedCellText($left . $separator . $right);
    }

    private function positionedGapLooksLikeWordBoundary(float $gap, float $fontSize, string $leftText, string $rightText): bool
    {
        if ($gap < -1.5) {
            return false;
        }

        $rightWord = $this->firstWordToken($rightText);
        if ($rightWord === '') {
            return false;
        }

        if ($gap >= max(1.5, $fontSize * 0.22)) {
            $leftWord = $this->lastWordToken($leftText);
            if ($leftWord !== ''
                && $this->length($leftWord) >= 2
                && $this->length($rightWord) >= 2
                && preg_match('/^\p{L}+$/u', $leftWord . $rightWord) === 1
            ) {
                return true;
            }
        }

        if ($gap >= max(1.0, $fontSize * 0.35)) {
            $leftWord = $this->lastWordToken($leftText);
            if (preg_match('/[:,;]$/u', rtrim($leftText)) === 1) {
                return true;
            }
            if ($leftWord !== '' && $this->length($rightWord) === 1) {
                return in_array(strtolower($rightWord), ['a', 'i', 'o', 'u', 'w', 'z'], true);
            }
            if ($leftWord !== '' && $this->length($leftWord) === 1) {
                return in_array(strtolower($leftWord), ['a', 'i', 'o', 'u', 'w', 'z'], true);
            }
            if (preg_match('/^\p{Lu}{2,}$/u', $rightWord) === 1) {
                return true;
            }
            if (preg_match('/^\p{Lu}\p{Ll}+$/u', $rightWord) === 1) {
                return false;
            }
            if (preg_match('/^\p{Ll}/u', $rightWord) === 1 && $this->length($rightWord) > 2) {
                return true;
            }
        }

        return false;
    }

    private function lastWordToken(string $text): string
    {
        if (preg_match('/[\p{L}\p{N}]+$/u', rtrim($text), $match) !== 1) {
            return '';
        }

        return $match[0];
    }

    private function firstWordToken(string $text): string
    {
        if (preg_match('/^[^\p{L}\p{N}]*([\p{L}\p{N}]+)/u', ltrim($text), $match) !== 1) {
            return '';
        }

        return $match[1];
    }

    private function appendCellText(string $left, string $right): string
    {
        $left = trim($left);
        $right = trim($right);
        if ($left === '') {
            return $right;
        }
        if ($right === '') {
            return $left;
        }

        return $this->positionedCellText($left . ' ' . $right);
    }

    /**
     * @param list<string> $row
     * @return list<int>
     */
    private function populatedCellIndexes(array $row): array
    {
        $indexes = [];
        foreach ($row as $index => $cell) {
            if ($this->cellTextValue($cell) !== '') {
                $indexes[] = $index;
            }
        }

        return $indexes;
    }

    private function cellTextValue(mixed $cell): string
    {
        if (is_array($cell)) {
            return $this->positionedCellText((string) ($cell['text'] ?? ''));
        }

        return $this->positionedCellText((string) $cell);
    }

    private function numericValue(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    /**
     * @param list<float> $values
     */
    private function median(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }

        sort($values, SORT_NUMERIC);
        $middle = intdiv(count($values), 2);
        if (count($values) % 2 === 1) {
            return (float) $values[$middle];
        }

        return ((float) $values[$middle - 1] + (float) $values[$middle]) / 2.0;
    }

    private function length(string $text): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($text, 'UTF-8');
        }

        if (preg_match_all('/./us', $text, $matches) === 1) {
            return count($matches[0]);
        }

        return strlen($text);
    }

    /**
     * @param list<array<string, mixed>> $structureBlocks
     * @param list<string> $limitedLines
     * @return list<AstNode>
     */
    private function blocksFromTaggedStructureBlocks(array $structureBlocks, array $limitedLines): array
    {
        if ($structureBlocks === [] || $limitedLines === []) {
            return [];
        }

        $structureBlockGroups = $this->taggedStructureBlockLineGroups($structureBlocks);
        if ($structureBlockGroups === []) {
            return [];
        }

        $blocks = [];
        $pendingItems = [];
        $pendingOrdered = false;
        $lineIndex = 0;
        $exactCoverage = $this->taggedStructureBlockLines($structureBlocks) === $limitedLines;

        $flushList = function () use (&$blocks, &$pendingItems, &$pendingOrdered): void {
            if ($pendingItems === []) {
                return;
            }
            $blocks[] = new AstNode($pendingOrdered ? 'ordered_list' : 'bullet_list', [], $pendingItems);
            $pendingItems = [];
            $pendingOrdered = false;
        };

        foreach ($structureBlockGroups as $group) {
            $structureBlock = $group['block'];
            $structureLines = $group['lines'];

            if ($exactCoverage) {
                $matchIndex = $lineIndex;
            } else {
                $matches = $this->lineSequenceIndexes($limitedLines, $structureLines, $lineIndex);
                if (count($matches) !== 1) {
                    return [];
                }
                $matchIndex = $matches[0];
            }

            if ($matchIndex > $lineIndex) {
                $flushList();
                foreach ($this->blocksFromLines(array_slice($limitedLines, $lineIndex, $matchIndex - $lineIndex)) as $fallbackBlock) {
                    $blocks[] = $fallbackBlock;
                }
            }

            if (($structureBlock['kind'] ?? '') === 'table') {
                $rows = $this->taggedTableRows($structureBlock);
                if ($rows !== []) {
                    $flushList();
                    $blocks[] = $this->tableFromTaggedTable($structureBlock, $this->taggedAstAttrs($structureBlock));
                }
                $lineIndex = $matchIndex + count($structureLines);
                continue;
            }

            $text = $this->taggedStructureBlockText($structureBlock);
            if ($text === '') {
                $lineIndex = $matchIndex + count($structureLines);
                continue;
            }

            $role = $this->taggedStructureItemRole($structureBlock);
            $headingLevel = $this->headingLevelFromTaggedRole($role);
            if ($headingLevel !== null) {
                $flushList();
                $blocks[] = new AstNode(
                    'heading',
                    array_replace($this->taggedAstAttrs($structureBlock), ['level' => $headingLevel, 'text' => $text]),
                    $this->inlines($text)
                );
                $lineIndex = $matchIndex + count($structureLines);
                continue;
            }

            if ($this->isTaggedListItemRole($role)) {
                $ordered = $this->taggedListItemIsOrdered($structureBlock);
                if ($pendingItems !== [] && $pendingOrdered !== $ordered) {
                    $flushList();
                }
                $pendingOrdered = $ordered;
                $pendingItems[] = new AstNode('list_item', $this->taggedAstAttrs($structureBlock), [$this->paragraph($text)]);
                $lineIndex = $matchIndex + count($structureLines);
                continue;
            }

            $flushList();
            $blocks[] = $this->paragraph($text, $this->taggedAstAttrs($structureBlock));
            $lineIndex = $matchIndex + count($structureLines);
        }

        if ($lineIndex < count($limitedLines)) {
            $flushList();
            foreach ($this->blocksFromLines(array_slice($limitedLines, $lineIndex)) as $fallbackBlock) {
                $blocks[] = $fallbackBlock;
            }
        }
        $flushList();

        return $blocks;
    }

    /**
     * @param list<array<string, mixed>> $structureBlocks
     * @return list<array{block: array<string, mixed>, lines: list<string>}>
     */
    private function taggedStructureBlockLineGroups(array $structureBlocks): array
    {
        $groups = [];
        foreach ($structureBlocks as $structureBlock) {
            $lines = [];
            if (($structureBlock['kind'] ?? '') === 'table') {
                foreach ($this->taggedTableRows($structureBlock) as $row) {
                    foreach ($row as $cell) {
                        $text = $this->taggedTableCellText($cell);
                        if ($text !== '') {
                            $lines[] = $text;
                        }
                    }
                }
            } else {
                $text = $this->taggedStructureBlockText($structureBlock);
                if ($text !== '') {
                    $lines[] = $text;
                }
            }

            if ($lines !== []) {
                $groups[] = [
                    'block' => $structureBlock,
                    'lines' => $lines,
                ];
            }
        }

        return $groups;
    }

    /**
     * @param list<string> $lines
     * @param list<string> $sequence
     * @return list<int>
     */
    private function lineSequenceIndexes(array $lines, array $sequence, int $startIndex): array
    {
        if ($sequence === []) {
            return [];
        }

        $matches = [];
        $lineCount = count($lines);
        $sequenceCount = count($sequence);
        $lastStart = $lineCount - $sequenceCount;
        for ($index = max(0, $startIndex); $index <= $lastStart; $index++) {
            for ($offset = 0; $offset < $sequenceCount; $offset++) {
                if ($lines[$index + $offset] !== $sequence[$offset]) {
                    continue 2;
                }
            }
            $matches[] = $index;
        }

        return $matches;
    }

    /**
     * @param list<array<string, mixed>> $structureBlocks
     * @return list<string>
     */
    private function taggedStructureBlockLines(array $structureBlocks): array
    {
        $lines = [];
        foreach ($structureBlocks as $structureBlock) {
            if (($structureBlock['kind'] ?? '') === 'table') {
                foreach ($this->taggedTableRows($structureBlock) as $row) {
                    foreach ($row as $cell) {
                        $text = $this->taggedTableCellText($cell);
                        if ($text !== '') {
                            $lines[] = $text;
                        }
                    }
                }
                continue;
            }

            $text = $this->taggedStructureBlockText($structureBlock);
            if ($text !== '') {
                $lines[] = $text;
            }
        }

        return $lines;
    }

    /**
     * @param list<array<string, mixed>> $tables
     * @param list<string> $limitedLines
     * @return list<AstNode>
     */
    private function blocksFromTaggedTables(array $tables, array $limitedLines): array
    {
        if ($tables === [] || $this->taggedTableLines($tables) !== $limitedLines) {
            return [];
        }

        $blocks = [];
        foreach ($tables as $table) {
            $rows = $this->taggedTableRows($table);
            if ($rows !== []) {
                $blocks[] = $this->tableFromTaggedTable($table, $this->taggedAstAttrs($table));
            }
        }

        return $blocks;
    }

    /**
     * @param list<array<string, mixed>> $tables
     * @return list<string>
     */
    private function taggedTableLines(array $tables): array
    {
        $lines = [];
        foreach ($tables as $table) {
            foreach ($this->taggedTableRows($table) as $row) {
                foreach ($row as $cell) {
                    $text = $this->taggedTableCellText($cell);
                    if ($text !== '') {
                        $lines[] = $text;
                    }
                }
            }
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $table
     * @return list<list<array<string, mixed>>>
     */
    private function taggedTableRows(array $table): array
    {
        $rawRows = $table['rows'] ?? [];
        if (!is_array($rawRows)) {
            return [];
        }

        $rows = [];
        foreach ($rawRows as $rawRow) {
            if (!is_array($rawRow)) {
                continue;
            }

            $row = [];
            foreach ($rawRow as $rawCell) {
                if (is_array($rawCell)) {
                    $row[] = $rawCell;
                }
            }
            if ($row !== []) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $table
     * @return list<array<string, mixed>>
     */
    private function taggedTableSections(array $table): array
    {
        $rawSections = $table['sections'] ?? [];
        if (!is_array($rawSections)) {
            return [];
        }

        $sections = [];
        foreach ($rawSections as $rawSection) {
            if (!is_array($rawSection) || $this->taggedTableRows($rawSection) === []) {
                continue;
            }

            $sections[] = $rawSection;
        }

        return $sections;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<string> $limitedLines
     * @return list<AstNode>
     */
    private function blocksFromTaggedStructureItems(array $items, array $limitedLines): array
    {
        if ($items === [] || $this->taggedStructureItemLines($items) !== $limitedLines) {
            return [];
        }

        $blocks = [];
        $pendingItems = [];
        $pendingOrdered = false;

        $flushList = function () use (&$blocks, &$pendingItems, &$pendingOrdered): void {
            if ($pendingItems === []) {
                return;
            }
            $blocks[] = new AstNode($pendingOrdered ? 'ordered_list' : 'bullet_list', [], $pendingItems);
            $pendingItems = [];
            $pendingOrdered = false;
        };

        foreach ($items as $item) {
            $text = $this->taggedStructureItemText($item);
            if ($text === '') {
                continue;
            }

            $role = $this->taggedStructureItemRole($item);
            $headingLevel = $this->headingLevelFromTaggedRole($role);
            if ($headingLevel !== null) {
                $flushList();
                $blocks[] = new AstNode(
                    'heading',
                    array_replace($this->taggedAstAttrs($item), ['level' => $headingLevel, 'text' => $text]),
                    $this->inlines($text)
                );
                continue;
            }

            if ($this->isTaggedListItemRole($role)) {
                $ordered = $this->taggedListItemIsOrdered($item);
                if ($pendingItems !== [] && $pendingOrdered !== $ordered) {
                    $flushList();
                }
                $pendingOrdered = $ordered;
                $pendingItems[] = new AstNode('list_item', $this->taggedAstAttrs($item), [$this->paragraph($text)]);
                continue;
            }

            $flushList();
            $blocks[] = $this->paragraph($text, $this->taggedAstAttrs($item));
        }
        $flushList();

        return $blocks;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<string>
     */
    private function taggedStructureItemLines(array $items): array
    {
        $lines = [];
        foreach ($items as $item) {
            $text = $this->taggedStructureItemText($item);
            if ($text !== '') {
                $lines[] = $text;
            }
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function taggedStructureItemText(array $item): string
    {
        $text = $item['text'] ?? '';
        if (!is_string($text)) {
            return '';
        }

        $text = str_replace("\0", '', $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * @param array<string, mixed> $structureBlock
     */
    private function taggedStructureBlockText(array $structureBlock): string
    {
        return $this->taggedStructureItemText($structureBlock);
    }

    /**
     * @param array<string, mixed> $cell
     */
    private function taggedTableCellText(array $cell): string
    {
        $text = $cell['text'] ?? '';
        if (!is_string($text)) {
            return '';
        }

        $text = str_replace("\0", '', $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function taggedStructureItemRole(array $item): string
    {
        foreach (['resolvedRole', 'role'] as $key) {
            $role = $item[$key] ?? '';
            if (is_string($role) && $role !== '') {
                return strtoupper($role);
            }
        }

        return '';
    }

    private function headingLevelFromTaggedRole(string $role): ?int
    {
        if (preg_match('/^H([1-6])$/', $role, $match) === 1) {
            return (int) $match[1];
        }

        return $role === 'H' ? 1 : null;
    }

    private function isTaggedListItemRole(string $role): bool
    {
        return in_array($role, ['LI', 'LBODY'], true);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function taggedListItemIsOrdered(array $item): bool
    {
        $attributes = $item['attributes'] ?? [];
        if (!is_array($attributes)) {
            return false;
        }

        foreach ($attributes as $attributeDictionary) {
            if (!is_array($attributeDictionary)) {
                continue;
            }

            $numbering = $attributeDictionary['ListNumbering'] ?? null;
            if (!is_string($numbering)) {
                continue;
            }

            return !in_array($numbering, ['None', 'Disc', 'Circle', 'Square'], true);
        }

        return false;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function taggedAstAttrs(array $item): array
    {
        $classes = [];
        foreach ($this->taggedClassNames($item) as $className) {
            $classToken = $this->taggedHtmlToken($className);
            if ($classToken !== '') {
                $classes[] = 'pdf-class-' . $classToken;
            }
        }
        $classes = array_values(array_unique($classes));

        $attributes = [];
        $language = $item['language'] ?? null;
        if (is_string($language) && $this->isSafeLanguageTag($language)) {
            $attributes['lang'] = $language;
        }

        foreach (['role' => 'data-pdf-role', 'resolvedRole' => 'data-pdf-resolved-role'] as $itemKey => $attrName) {
            $value = $item[$itemKey] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $attributes[$attrName] = trim($value);
            }
        }

        $objectNumber = $item['objectNumber'] ?? null;
        if (is_int($objectNumber) || is_float($objectNumber) || (is_string($objectNumber) && ctype_digit($objectNumber))) {
            $attributes['data-pdf-struct-object'] = (string) (int) $objectNumber;
        }

        $identifier = $item['id'] ?? null;
        if (is_string($identifier) && trim($identifier) !== '') {
            $attributes['data-pdf-struct-id'] = trim($identifier);
        }

        $classNames = $this->taggedClassNames($item);
        if ($classNames !== []) {
            $attributes['data-pdf-classes'] = implode(' ', $classNames);
        }

        foreach ($this->taggedAttributeDictionaries($item) as $attributeDictionary) {
            $owner = $this->taggedHtmlToken((string) ($attributeDictionary['O'] ?? 'attribute'));
            if ($owner === '') {
                $owner = 'attribute';
            }
            foreach ($attributeDictionary as $key => $value) {
                if ($key === 'O') {
                    continue;
                }
                $name = $this->taggedHtmlToken((string) $key);
                $htmlValue = $this->taggedAttributeHtmlValue($value);
                if ($name === '' || $htmlValue === '') {
                    continue;
                }
                $htmlName = 'data-pdf-' . $owner . '-' . $name;
                if (isset($attributes[$htmlName]) && $attributes[$htmlName] !== $htmlValue) {
                    $attributes[$htmlName] .= ' ' . $htmlValue;
                } else {
                    $attributes[$htmlName] = $htmlValue;
                }
            }
        }

        $attrs = [];
        if ($classes !== []) {
            $attrs['classes'] = $classes;
        }
        if ($attributes !== []) {
            $attrs['attributes'] = $attributes;
        }

        $htmlAttributes = $attributes;
        if ($classes !== []) {
            $htmlAttributes['class'] = implode(' ', $classes);
        }
        if ($htmlAttributes !== []) {
            $attrs['htmlAttributes'] = $htmlAttributes;
        }

        return $attrs;
    }

    /**
     * @param array<string, mixed> $item
     * @return list<string>
     */
    private function taggedClassNames(array $item): array
    {
        $classes = $item['classes'] ?? [];
        if (!is_array($classes)) {
            return [];
        }

        $normalized = [];
        foreach ($classes as $className) {
            if (!is_scalar($className)) {
                continue;
            }
            $className = trim((string) $className);
            if ($className !== '') {
                $normalized[] = $className;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param array<string, mixed> $item
     * @return list<array<string, mixed>>
     */
    private function taggedAttributeDictionaries(array $item): array
    {
        $attributes = $item['attributes'] ?? [];
        if (!is_array($attributes)) {
            return [];
        }

        $dictionaries = [];
        foreach ($attributes as $attributeDictionary) {
            if (is_array($attributeDictionary)) {
                $dictionaries[] = $attributeDictionary;
            }
        }

        return $dictionaries;
    }

    private function taggedHtmlToken(string $value): string
    {
        $value = preg_replace('/([a-z])([A-Z])/', '$1-$2', $value) ?? $value;
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_.:-]+/', '-', $value) ?? $value;
        $value = trim($value, '-');

        return $value;
    }

    private function isSafeLanguageTag(string $language): bool
    {
        return preg_match('/^[A-Za-z]{2,8}(?:-[A-Za-z0-9]{1,8})*$/', $language) === 1;
    }

    private function taggedAttributeHtmlValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value) || is_string($value)) {
            return trim((string) $value);
        }
        if (!is_array($value)) {
            return '';
        }

        $parts = [];
        foreach ($value as $item) {
            if (is_bool($item)) {
                $parts[] = $item ? 'true' : 'false';
                continue;
            }
            if (is_int($item) || is_float($item) || is_string($item)) {
                $part = trim((string) $item);
                if ($part !== '') {
                    $parts[] = $part;
                }
            }
        }

        return implode(' ', $parts);
    }

    /**
     * @param list<string> $lines
     * @return array{rows: list<list<string>>, consumed: int}
     */
    private function tableRowsAt(array $lines, int $start): array
    {
        $rows = [];
        $columnCount = null;
        $consumed = 0;
        for ($index = $start, $count = count($lines); $index < $count; $index++) {
            $cells = $this->tableCells($lines[$index]);
            if ($cells === null) {
                break;
            }
            if ($this->isTableSeparatorRow($cells)) {
                $consumed++;
                continue;
            }
            $rowColumnCount = count($cells);
            if ($columnCount === null) {
                $columnCount = $rowColumnCount;
            } elseif ($rowColumnCount !== $columnCount) {
                break;
            }
            $rows[] = $cells;
            $consumed++;
        }

        return count($rows) >= 2 ? ['rows' => $rows, 'consumed' => $consumed] : ['rows' => [], 'consumed' => 0];
    }

    /**
     * @param list<string> $lines
     * @return list<AstNode>
     */
    private function blocksFromCurrencyRecordLines(array $lines): array
    {
        $rows = [];
        $recordRows = 0;
        $pending = [];
        foreach ($lines as $line) {
            $line = trim(preg_replace('/\s+/u', ' ', $line) ?? $line);
            if ($line === '') {
                continue;
            }

            $totalRow = $this->currencyRecordTotalRow($line);
            if ($totalRow !== null) {
                $rows[] = $totalRow;
                $pending = [];
                continue;
            }

            $amountOnlyRow = $this->currencyRecordAmountOnlyRow($line);
            if ($amountOnlyRow !== null) {
                $rows[] = $amountOnlyRow;
                $pending = [];
                continue;
            }

            $pending[] = $line;
            $candidate = implode(' ', $pending);
            $recordRow = $this->currencyRecordDataRow($candidate);
            if ($recordRow === null) {
                if (count($pending) > 5 || strlen($candidate) > 260) {
                    $pending = [];
                }
                continue;
            }

            $rows[] = $recordRow;
            $recordRows++;
            $pending = [];
        }

        if ($recordRows < 4 || count($rows) < 6) {
            return [];
        }

        array_unshift($rows, ['Name', 'Location', 'Category', 'Amount']);

        return [$this->table($rows)];
    }

    /**
     * @return list<string>|null
     */
    private function currencyRecordDataRow(string $line): ?array
    {
        if (preg_match('/^(?<beforeAmount>.+?)\s*(?<amount>[$€£¥]\s*\d[\d,]*(?:\.\d{2})?)$/u', $line, $amountMatch) !== 1) {
            return null;
        }

        $beforeAmount = trim($amountMatch['beforeAmount']);
        if (preg_match('/^(?<beforeCategory>.+,\s*[A-Z]{2})(?<category>[A-Z][A-Z &\/-]*)$/u', $beforeAmount, $categoryMatch) !== 1) {
            return null;
        }

        $nameAndLocation = $this->splitCurrencyRecordNameAndLocation(trim($categoryMatch['beforeCategory']));
        if ($nameAndLocation === null) {
            return null;
        }
        [$name, $location] = $nameAndLocation;
        $category = trim($categoryMatch['category']);
        $amount = trim($amountMatch['amount']);
        if ($name === '' || $location === '' || $category === '' || $amount === '' || preg_match('/[A-Z]{3,}/', $category) !== 1) {
            return null;
        }

        return [
            $this->repairCurrencyRecordName($name),
            $this->repairCurrencyRecordLocation($location),
            $this->positionedCellText($category),
            preg_replace('/\s+/', '', $amount) ?? $amount,
        ];
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function splitCurrencyRecordNameAndLocation(string $text): ?array
    {
        if (preg_match('/^(?<prefix>.+),\s*(?<state>[A-Z]{2})$/u', $text, $stateMatch) !== 1) {
            return null;
        }

        $prefix = trim($stateMatch['prefix']);
        $state = $stateMatch['state'];
        $tokens = preg_split('/\s+/u', $prefix) ?: [];
        if (count($tokens) < 3) {
            return null;
        }

        $best = null;
        $bestScore = -INF;
        $tokenCount = count($tokens);
        for ($split = 1; $split < $tokenCount; $split++) {
            $name = trim(implode(' ', array_slice($tokens, 0, $split)));
            $cityTokens = array_slice($tokens, $split);
            $city = trim(implode(' ', $cityTokens));
            if ($name === '' || $city === '' || !str_contains($name, ',') || preg_match('/^[A-Z]{2,}/u', $cityTokens[0] ?? '') !== 1) {
                continue;
            }

            $score = $this->currencyRecordNameLocationSplitScore($name, $cityTokens);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = [$name, $city . ', ' . $state];
            }
        }

        return $bestScore > 0 && $best !== null ? $best : null;
    }

    /**
     * @param list<string> $cityTokens
     */
    private function currencyRecordNameLocationSplitScore(string $name, array $cityTokens): int
    {
        $score = 0;
        $commaCount = substr_count($name, ',');
        $score += $commaCount >= 2 ? 4 : 2;

        $afterLastComma = trim(substr($name, strrpos($name, ',') + 1));
        $afterCommaTokens = $afterLastComma === '' ? [] : (preg_split('/\s+/u', $afterLastComma) ?: []);
        $afterCommaCount = count($afterCommaTokens);
        if ($afterCommaCount === 1) {
            $score += 3;
        } elseif ($afterCommaCount === 2) {
            $score += 2;
        } elseif ($afterCommaCount > 3) {
            $score -= 3;
        }

        if ($commaCount >= 2 && $afterCommaCount > 0 && preg_match('/^[A-Z]$/', $afterCommaTokens[0]) === 1) {
            $score += 2;
        }

        $cityTokenCount = count($cityTokens);
        if ($cityTokenCount === 2) {
            $score += 2;
        } elseif ($cityTokenCount === 3) {
            $score += 1;
        } elseif ($cityTokenCount > 4) {
            $score -= 6;
        }

        $city = implode(' ', $cityTokens);
        if (str_contains($city, '&') || preg_match('/\b(?:MEDICINE|PULMONARY|REGIONAL|SLEEP|CENTER|ASSOCIATES|GROUP)\b/u', $city) === 1) {
            $score -= 4;
        }

        return $score;
    }

    /**
     * @return list<string>|null
     */
    private function currencyRecordTotalRow(string $line): ?array
    {
        if (preg_match('/^TOTAL\s+(?<amount>[$€£¥]\s*\d[\d,]*(?:\.\d{2})?)$/u', $line, $match) !== 1) {
            return null;
        }

        return ['TOTAL', '', '', preg_replace('/\s+/', '', trim($match['amount'])) ?? trim($match['amount'])];
    }

    /**
     * @return list<string>|null
     */
    private function currencyRecordAmountOnlyRow(string $line): ?array
    {
        if (preg_match('/^(?<amount>[$€£¥]\s*\d[\d,]*(?:\.\d{2})?)$/u', $line, $match) !== 1) {
            return null;
        }

        return ['TOTAL', '', '', preg_replace('/\s+/', '', trim($match['amount'])) ?? trim($match['amount'])];
    }

    private function repairCurrencyRecordName(string $name): string
    {
        return $this->positionedCellText(preg_replace('/\s*,\s*/u', ', ', $name) ?? $name);
    }

    private function repairCurrencyRecordLocation(string $location): string
    {
        return $this->positionedCellText(preg_replace('/\s*,\s*/u', ', ', $location) ?? $location);
    }

    /**
     * @return list<string>|null
     */
    private function tableCells(string $line): ?array
    {
        if (str_contains($line, '|')) {
            $cells = array_map('trim', explode('|', trim($line)));
            if (($cells[0] ?? '') === '') {
                array_shift($cells);
            }
            if (($cells[array_key_last($cells)] ?? '') === '') {
                array_pop($cells);
            }
        } elseif (str_contains($line, "\t")) {
            $cells = preg_split('/\t+/u', $line) ?: [];
            $cells = array_map('trim', $cells);
        } elseif (preg_match('/ {2,}/u', $line) === 1) {
            $cells = preg_split('/ {2,}/u', $line) ?: [];
            $cells = array_map('trim', $cells);
        } else {
            return null;
        }

        $cells = array_values(array_filter($cells, static fn (string $cell): bool => $cell !== ''));

        return count($cells) >= 2 ? $cells : null;
    }

    /**
     * @param list<string> $cells
     */
    private function isTableSeparatorRow(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (preg_match('/^:?-{2,}:?$/', $cell) !== 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<list<mixed>> $rows
     */
    private function table(array $rows, array $attrs = []): AstNode
    {
        $header = array_shift($rows) ?? [];

        return new AstNode('table', $attrs, [
            new AstNode('table_head', [], [new AstNode('table_row', [], array_map(fn (mixed $cell): AstNode => $this->tableCellFromValue($cell), $header))]),
            new AstNode('table_body', [], array_map(fn (array $row): AstNode => new AstNode('table_row', [], array_map(fn (mixed $cell): AstNode => $this->tableCellFromValue($cell), $row)), $rows)),
        ]);
    }

    /**
     * @param array<string, mixed> $table
     */
    private function tableFromTaggedTable(array $table, array $attrs = []): AstNode
    {
        $sections = $this->taggedTableSections($table);
        if ($sections === []) {
            return $this->table($this->taggedTableRows($table), $attrs);
        }

        $headRows = [];
        $headAttrs = [];
        $bodyNodes = [];
        $footRows = [];
        $footAttrs = [];

        foreach ($sections as $section) {
            $rows = $this->taggedTableRows($section);
            if ($rows === []) {
                continue;
            }

            $rowNodes = array_map(
                fn (array $row): AstNode => new AstNode('table_row', [], array_map(fn (mixed $cell): AstNode => $this->tableCellFromValue($cell), $row)),
                $rows
            );
            $role = strtoupper($this->taggedStructureItemRole($section));
            if ($role === 'THEAD') {
                if ($headRows === []) {
                    $headAttrs = $this->taggedAstAttrs($section);
                }
                foreach ($rowNodes as $rowNode) {
                    $headRows[] = $rowNode;
                }
                continue;
            }
            if ($role === 'TFOOT') {
                if ($footRows === []) {
                    $footAttrs = $this->taggedAstAttrs($section);
                }
                foreach ($rowNodes as $rowNode) {
                    $footRows[] = $rowNode;
                }
                continue;
            }

            $bodyNodes[] = new AstNode('table_body', $this->taggedAstAttrs($section), $rowNodes);
        }

        $children = [];
        if ($headRows !== []) {
            $children[] = new AstNode('table_head', $headAttrs, $headRows);
        }
        foreach ($bodyNodes as $bodyNode) {
            $children[] = $bodyNode;
        }
        if ($footRows !== []) {
            $children[] = new AstNode('table_foot', $footAttrs, $footRows);
        }

        return $children === [] ? $this->table($this->taggedTableRows($table), $attrs) : new AstNode('table', $attrs, $children);
    }

    private function tableCellFromValue(mixed $cell): AstNode
    {
        if (is_array($cell)) {
            $attrs = $this->taggedAstAttrs($cell);
            foreach (['classes', 'attributes', 'htmlAttributes'] as $attrKey) {
                if (isset($cell[$attrKey]) && is_array($cell[$attrKey])) {
                    $attrs[$attrKey] = array_replace(is_array($attrs[$attrKey] ?? null) ? $attrs[$attrKey] : [], $cell[$attrKey]);
                }
            }
            if (strtoupper($this->taggedStructureItemRole($cell)) === 'TH') {
                $attrs['header'] = true;
                $headerId = $this->taggedTableHeaderHtmlId($cell['id'] ?? null);
                if ($headerId !== '') {
                    $attrs = $this->withTableCellHtmlAttribute($attrs, 'id', $headerId);
                }
                $scope = $this->taggedTableHeaderScope($cell);
                if ($scope !== '') {
                    $attrs = $this->withTableCellHtmlAttribute($attrs, 'scope', $scope);
                }
            }
            $headers = $this->taggedTableHeadersAttribute($cell);
            if ($headers !== '') {
                $attrs = $this->withTableCellHtmlAttribute($attrs, 'headers', $headers);
            }

            return $this->tableCell(
                $this->taggedTableCellText($cell),
                $this->positiveSpan($cell['rowSpan'] ?? 1),
                $this->positiveSpan($cell['colSpan'] ?? 1),
                $attrs
            );
        }

        return $this->tableCell((string) $cell);
    }

    private function withTableCellHtmlAttribute(array $attrs, string $name, string $value): array
    {
        if (!isset($attrs['attributes']) || !is_array($attrs['attributes'])) {
            $attrs['attributes'] = [];
        }
        if (!isset($attrs['htmlAttributes']) || !is_array($attrs['htmlAttributes'])) {
            $attrs['htmlAttributes'] = [];
        }

        $attrs['attributes'][$name] = $value;
        $attrs['htmlAttributes'][$name] = $value;

        return $attrs;
    }

    private function taggedTableHeaderHtmlId(mixed $identifier): string
    {
        if (!is_scalar($identifier)) {
            return '';
        }

        $token = $this->taggedHtmlToken((string) $identifier);
        if ($token === '') {
            return '';
        }

        return 'pdf-' . $token;
    }

    /**
     * @param array<string, mixed> $cell
     */
    private function taggedTableHeadersAttribute(array $cell): string
    {
        $headers = [];
        foreach ($this->taggedAttributeDictionaries($cell) as $attributeDictionary) {
            $owner = $attributeDictionary['O'] ?? null;
            if (is_string($owner) && strcasecmp($owner, 'Table') !== 0) {
                continue;
            }

            foreach ($this->taggedTableHeaderReferences($attributeDictionary['Headers'] ?? null) as $headerReference) {
                $headerId = $this->taggedTableHeaderHtmlId($headerReference);
                if ($headerId !== '') {
                    $headers[$headerId] = $headerId;
                }
            }
        }

        return implode(' ', array_values($headers));
    }

    /**
     * @return list<string>
     */
    private function taggedTableHeaderReferences(mixed $value): array
    {
        if (is_string($value) || is_int($value) || is_float($value)) {
            $value = trim((string) $value);
            return $value === '' ? [] : [$value];
        }

        if (!is_array($value)) {
            return [];
        }

        $references = [];
        foreach ($value as $item) {
            foreach ($this->taggedTableHeaderReferences($item) as $reference) {
                $references[] = $reference;
            }
        }

        return $references;
    }

    /**
     * @param array<string, mixed> $cell
     */
    private function taggedTableHeaderScope(array $cell): string
    {
        foreach ($this->taggedAttributeDictionaries($cell) as $attributeDictionary) {
            $owner = $attributeDictionary['O'] ?? null;
            if (is_string($owner) && strcasecmp($owner, 'Table') !== 0) {
                continue;
            }

            $scope = $attributeDictionary['Scope'] ?? null;
            if (!is_string($scope)) {
                continue;
            }

            $normalized = strtolower(trim($scope));
            if ($normalized === 'column') {
                return 'col';
            }
            if ($normalized === 'row') {
                return 'row';
            }
        }

        return '';
    }

    private function tableCell(string $text, int $rowspan = 1, int $colspan = 1, array $attrs = []): AstNode
    {
        $text = $this->repairPdfTableCellText($text);
        $attrs = array_replace($attrs, ['text' => $text]);
        if ($rowspan > 1) {
            $attrs['rowspan'] = $rowspan;
        }
        if ($colspan > 1) {
            $attrs['colspan'] = $colspan;
        }

        return new AstNode('table_cell', $attrs, [
            new AstNode('plain', ['text' => $text], $this->inlines($text)),
        ]);
    }

    private function repairPdfTableCellText(string $text): string
    {
        $text = trim($this->removeStandaloneBraceArtifacts($text));
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/(?<=[\p{L}])([:;])(?=[\p{L}\d])/u', '$1 ', $text) ?? $text;
        $text = preg_replace('/(?<!\d)\.(?=[A-Z][a-z])/u', '. ', $text) ?? $text;
        $text = preg_replace('/(?<=[\p{Ll}])(\d+)(?=[:;])/u', ' $1', $text) ?? $text;
        $text = preg_replace('/(?<=\d)\s+,(?=\d)/u', ',', $text) ?? $text;
        $text = preg_replace('/(?<=\d,)\s+(?=\d{3}(?:\.\d{2})?\b)/u', '', $text) ?? $text;
        $text = preg_replace('/(?<=\d)(?=[\p{L}]{2,}\s+\d)/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        if ($this->looksLikePdfProseTableCell($text)) {
            $text = preg_replace('/(?<=[\p{Ll}])(?=\d{1,4}[\p{L}])/u', ' ', $text) ?? $text;
            $text = preg_replace('/(?<=\d)(?=[\p{L}]{3,})/u', ' ', $text) ?? $text;
            $text = $this->repairGluedProseLine($text);
        }

        return trim($text);
    }

    private function looksLikePdfProseTableCell(string $text): bool
    {
        if (strlen($text) < 28) {
            return false;
        }

        if (preg_match_all('/\p{L}/u', $text, $letters) === false) {
            return false;
        }
        $letterCount = count($letters[0]);
        $nonSpaceCount = strlen(preg_replace('/\s+/u', '', $text) ?? $text);
        if ($nonSpaceCount === 0 || $letterCount / $nonSpaceCount < 0.55) {
            return false;
        }

        if (preg_match_all('/[\p{L}]{3,}/u', $text, $words) === false) {
            return false;
        }
        $longAlphaRun = false;
        foreach ($words[0] as $word) {
            if (strlen($word) >= 18) {
                $longAlphaRun = true;
                break;
            }
        }
        if (count($words[0]) < 3 && !$longAlphaRun) {
            return false;
        }

        return preg_match('/(?:[a-z]{2,}(?:of|in|to|as|and|the|with|from|for|by)[a-z]{2,}|[a-z]\d+[a-z]|[a-z]{12,})/iu', $text) === 1;
    }

    private function positiveSpan(mixed $value): int
    {
        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            return max(1, (int) $value);
        }

        return 1;
    }

    /**
     * @return array{bool, string}|null
     */
    private function listItem(string $line): ?array
    {
        if (preg_match('/^\s*(?:[-*]|\x{2022})\s+(.+)$/u', $line, $match)) {
            return [false, trim($match[1])];
        }
        if (preg_match('/^\s*\d+[.)]\s+(.+)$/u', $line, $match)) {
            return [true, trim($match[1])];
        }

        return null;
    }

    /**
     * @return list<AstNode>|null
     */
    private function blocksFromLineWithEmbeddedLists(string $line): ?array
    {
        $markers = $this->embeddedListMarkers($line);
        [$allowBullets, $allowOrdered] = $this->embeddedListMarkerPermissions($markers);
        if (!$allowBullets && !$allowOrdered) {
            return null;
        }

        $markers = array_values(array_filter(
            $markers,
            static fn (array $marker): bool => $marker['type'] === 'bullet' ? $allowBullets : $allowOrdered
        ));
        if (count($markers) < 2) {
            return null;
        }

        $blocks = [];
        $prefix = trim(substr($line, 0, $markers[0]['offset']));
        if ($prefix !== '') {
            $blocks[] = $this->paragraph($this->repairGluedProseLine($prefix));
        }

        $index = 0;
        $markerCount = count($markers);
        while ($index < $markerCount) {
            $type = $markers[$index]['type'];
            $ordered = $type === 'ordered';
            $items = [];
            $start = $ordered ? $markers[$index]['number'] : null;
            while ($index < $markerCount && $markers[$index]['type'] === $type) {
                $nextOffset = $markers[$index + 1]['offset'] ?? strlen($line);
                $text = trim(substr($line, $markers[$index]['contentOffset'], $nextOffset - $markers[$index]['contentOffset']));
                if ($text !== '') {
                    $items[] = new AstNode('list_item', [], [$this->paragraph($this->repairGluedProseLine($text))]);
                }
                $index++;
            }
            if ($items === []) {
                continue;
            }

            $attrs = [];
            if ($ordered && $start !== null && $start !== 1) {
                $attrs['start'] = $start;
            }
            $blocks[] = new AstNode($ordered ? 'ordered_list' : 'bullet_list', $attrs, $items);
        }

        return count($blocks) >= 2 ? $blocks : null;
    }

    private function lineHasPdfListBlockEvidence(string $line): bool
    {
        if ($this->listItem($line) !== null) {
            return true;
        }

        [$allowBullets, $allowOrdered] = $this->embeddedListMarkerPermissions($this->embeddedListMarkers($line));

        return $allowBullets || $allowOrdered;
    }

    /**
     * @param list<array{type: string, number: ?int, offset: int, contentOffset: int}> $markers
     * @return array{bool, bool}
     */
    private function embeddedListMarkerPermissions(array $markers): array
    {
        if ($markers === []) {
            return [false, false];
        }

        $bulletCount = 0;
        $orderedNumbers = [];
        foreach ($markers as $marker) {
            if ($marker['type'] === 'bullet') {
                $bulletCount++;
                continue;
            }
            $orderedNumbers[] = $marker['number'];
        }

        return [$bulletCount >= 2, $this->embeddedOrderedMarkersLookSequential($orderedNumbers)];
    }

    /**
     * @return list<array{type: string, number: ?int, offset: int, contentOffset: int}>
     */
    private function embeddedListMarkers(string $line): array
    {
        $markers = [];
        if (preg_match_all('/(?:^|(?<=\s))\x{2022}\s+/u', $line, $matches, PREG_OFFSET_CAPTURE) !== false) {
            foreach ($matches[0] as $match) {
                $markers[] = [
                    'type' => 'bullet',
                    'number' => null,
                    'offset' => $match[1],
                    'contentOffset' => $match[1] + strlen($match[0]),
                ];
            }
        }
        if (preg_match_all('/(?:^|(?<![\p{L}\p{N}]))(\d{1,2})[.)]\s+(?=\S)/u', $line, $matches, PREG_OFFSET_CAPTURE) !== false) {
            foreach ($matches[0] as $index => $match) {
                $markers[] = [
                    'type' => 'ordered',
                    'number' => (int) $matches[1][$index][0],
                    'offset' => $match[1],
                    'contentOffset' => $match[1] + strlen($match[0]),
                ];
            }
        }

        usort($markers, static fn (array $left, array $right): int => $left['offset'] <=> $right['offset']);

        return $markers;
    }

    /**
     * @param list<int|null> $numbers
     */
    private function embeddedOrderedMarkersLookSequential(array $numbers): bool
    {
        if (count($numbers) < 2) {
            return false;
        }

        $previous = null;
        $hasProgression = false;
        foreach ($numbers as $number) {
            if (!is_int($number) || $number < 1) {
                return false;
            }
            if ($previous !== null) {
                if ($number === 1) {
                    $previous = $number;
                    continue;
                }
                if ($number !== $previous + 1) {
                    return false;
                }
                $hasProgression = true;
            }
            $previous = $number;
        }

        return $hasProgression;
    }

    private function looksLikeHeading(string $line, int $index, int $lineCount): bool
    {
        if ($lineCount < 2 || strlen($line) > 96) {
            return false;
        }
        if (preg_match('/[.!?,;]\s*$/u', $line)) {
            return false;
        }
        if ($index === 0) {
            return true;
        }

        return (bool) preg_match('/^[\p{Lu}\d][\p{L}\p{N} ,:;\'"()\/&-]{2,}$/u', $line);
    }

    private function paragraph(string $text, array $attrs = []): AstNode
    {
        return new AstNode('paragraph', array_replace($attrs, ['text' => $text]), $this->inlines($text));
    }

    /**
     * @param list<array<string, mixed>> $annotations
     * @param list<string> $limitedLines
     * @return list<array<string, mixed>>
     */
    private function unambiguousLinkAnnotations(array $annotations, array $limitedLines): array
    {
        $documentText = implode("\n", $limitedLines);
        $normalized = [];
        foreach ($annotations as $annotation) {
            if (!is_array($annotation)) {
                continue;
            }
            $text = trim(preg_replace('/\s+/u', ' ', (string) ($annotation['text'] ?? '')) ?? (string) ($annotation['text'] ?? ''));
            $uri = trim((string) ($annotation['uri'] ?? ''));
            if ($text === '' || $uri === '') {
                continue;
            }
            if ($this->substringOccurrenceCount($documentText, $text) !== 1) {
                continue;
            }

            $normalizedAnnotation = $annotation;
            $normalizedAnnotation['text'] = $text;
            $normalizedAnnotation['uri'] = $uri;
            $normalized[] = $normalizedAnnotation + [
                'text' => $text,
                'uri' => $uri,
            ];
        }

        return $normalized;
    }

    private function substringOccurrenceCount(string $haystack, string $needle): int
    {
        if ($needle === '') {
            return 0;
        }

        $count = 0;
        $offset = 0;
        while (($position = strpos($haystack, $needle, $offset)) !== false) {
            $count++;
            $offset = $position + strlen($needle);
        }

        return $count;
    }

    /**
     * @param list<AstNode> $blocks
     * @param list<array<string, mixed>> $annotations
     * @return list<AstNode>
     */
    private function applyLinkAnnotationsToBlocks(array $blocks, array $annotations): array
    {
        return array_map(fn (AstNode $block): AstNode => $this->applyLinkAnnotationsToNode($block, $annotations), $blocks);
    }

    /**
     * @param list<array<string, mixed>> $annotations
     */
    private function applyLinkAnnotationsToNode(AstNode $node, array $annotations): AstNode
    {
        $children = array_map(fn (AstNode $child): AstNode => $this->applyLinkAnnotationsToNode($child, $annotations), $node->children);
        $text = $node->attr('text');
        if (is_string($text) && in_array($node->type, ['paragraph', 'heading', 'plain'], true)) {
            return new AstNode($node->type, $node->attrs, $this->inlinesWithLinkAnnotations($text, $annotations));
        }

        return $children === $node->children ? $node : new AstNode($node->type, $node->attrs, $children);
    }

    /**
     * @param list<array<string, mixed>> $annotations
     * @return list<AstNode>
     */
    private function inlinesWithLinkAnnotations(string $text, array $annotations): array
    {
        $matches = [];
        foreach ($annotations as $annotation) {
            $label = (string) ($annotation['text'] ?? '');
            $uri = (string) ($annotation['uri'] ?? '');
            if ($label === '' || $uri === '') {
                continue;
            }
            $position = strpos($text, $label);
            if ($position === false) {
                continue;
            }
            $matches[] = [
                'start' => $position,
                'end' => $position + strlen($label),
                'text' => $label,
                'uri' => $uri,
            ];
        }
        if ($matches === []) {
            return $this->inlines($text);
        }

        usort($matches, static fn (array $a, array $b): int => $a['start'] <=> $b['start']);
        $nodes = [];
        $offset = 0;
        foreach ($matches as $match) {
            if ($match['start'] < $offset) {
                continue;
            }
            $before = substr($text, $offset, $match['start'] - $offset);
            if ($before !== '') {
                array_push($nodes, ...$this->inlines($before));
            }

            $nodes[] = new AstNode('link', ['url' => $match['uri'], 'title' => ''], [
                new AstNode('text', ['text' => $match['text']]),
            ]);
            $offset = $match['end'];
        }

        $after = substr($text, $offset);
        if ($after !== '') {
            array_push($nodes, ...$this->inlines($after));
        }

        return $nodes === [] ? $this->inlines($text) : $nodes;
    }

    /**
     * @return list<AstNode>
     */
    private function inlines(string $text): array
    {
        if (!preg_match_all('/https?:\/\/[^\s<>"\']+/i', $text, $matches, PREG_OFFSET_CAPTURE)) {
            return [new AstNode('text', ['text' => $text])];
        }

        $nodes = [];
        $offset = 0;
        foreach ($matches[0] as [$url, $position]) {
            $before = substr($text, $offset, $position - $offset);
            if ($before !== '') {
                $nodes[] = new AstNode('text', ['text' => $before]);
            }

            $trimmedUrl = rtrim($url, '.,);]');
            $trailing = substr($url, strlen($trimmedUrl));
            $nodes[] = new AstNode('link', ['url' => $trimmedUrl, 'title' => ''], [
                new AstNode('text', ['text' => $trimmedUrl]),
            ]);
            if ($trailing !== '') {
                $nodes[] = new AstNode('text', ['text' => $trailing]);
            }
            $offset = $position + strlen($url);
        }

        $after = substr($text, $offset);
        if ($after !== '') {
            $nodes[] = new AstNode('text', ['text' => $after]);
        }

        return $nodes;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function countNodesOfType(array $nodes, string $type): int
    {
        $count = 0;
        foreach ($nodes as $node) {
            if ($node->type === $type) {
                $count++;
            }
            $count += $this->countNodesOfType($node->children, $type);
        }

        return $count;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function blocksHaveSuspiciousPdfTableText(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if ($node->type === 'table_cell') {
                $text = trim((string) ($node->attrs['text'] ?? ''));
                if ($text !== '' && preg_match('/\b[A-Za-z]{24,}\b/u', $text) === 1) {
                    return true;
                }
            }
            if ($node->children !== [] && $this->blocksHaveSuspiciousPdfTableText($node->children)) {
                return true;
            }
        }

        return false;
    }

    private function pdfInfoValue(string $pdfBytes, string $key): string
    {
        if (preg_match('/\/' . preg_quote($key, '/') . '\s*\((.*?)\)/s', $pdfBytes, $match) !== 1) {
            return '';
        }

        return $this->clipMetadataValue($this->decodePdfLiteralString($match[1]));
    }

    private function decodePdfLiteralString(string $value): string
    {
        $value = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $value);
        $value = preg_replace('/\\\\[nrtbf]/', ' ', $value) ?? $value;
        $value = preg_replace_callback('/\\\\([0-7]{1,3})/', static fn (array $match): string => chr(octdec($match[1])), $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function xmpTitle(string $pdfBytes): string
    {
        if (preg_match('/<dc:title\b[^>]*>(.*?)<\/dc:title>/is', $pdfBytes, $outer) !== 1) {
            return '';
        }
        if (preg_match('/<rdf:li\b[^>]*>(.*?)<\/rdf:li>/is', $outer[1], $inner) === 1) {
            return $this->clipMetadataValue(html_entity_decode(strip_tags($inner[1]), ENT_QUOTES | ENT_XML1, 'UTF-8'));
        }

        return $this->clipMetadataValue(html_entity_decode(strip_tags($outer[1]), ENT_QUOTES | ENT_XML1, 'UTF-8'));
    }

    private function clipMetadataValue(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
        if (strlen($value) <= 240) {
            return $value;
        }

        return rtrim(substr($value, 0, 237)) . '...';
    }
}
