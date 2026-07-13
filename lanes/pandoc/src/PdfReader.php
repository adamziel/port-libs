<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

final class PdfReader
{
    private const DEFAULT_MAX_TEXT_BYTES = 120000;
    private const DEFAULT_FAST_MODE_BYTES = 5_000_000;
    private const PDF_CODE_BLOCK_PREFIX = "\x1EPDF-CODE\x1F";
    private const PDF_MAP_LABEL_PREFIX = "\x1EPDF-MAP-LABEL\x1F";
    private const PDF_NUMBERED_HEADING_PREFIX = "\x1EPDF-NUMBERED-HEADING\x1F";
    private const PDF_DISPLAY_HEADING_PREFIX = "\x1EPDF-DISPLAY-HEADING\x1F";
    // A temporary word joiner preserves a compact source/geometry identifier
    // through the later, deliberately conservative prose-spacing pass.
    private const PDF_SOURCE_COMPACT_IDENTIFIER_BOUNDARY = "\u{2060}";
    private int $lowConfidenceGeometryTableCandidates = 0;

    /**
     * @param array{maxTextBytes?: int, maxPages?: int, pdfMaxPages?: int, max_pages?: int, password?: string, pdfPassword?: string, geometryTables?: bool, pdfGeometryTables?: bool, extractGeometryTables?: bool, pdfRepairProseText?: bool, repairProseText?: bool, pdfFastTextOnly?: bool, fastTextOnly?: bool, pdfFastModeBytes?: int, maxPositionedTextRuns?: int, pdfMaxPositionedTextRuns?: int} $options
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
        if (!$fastTextOnly) {
            $structuralMetadata = $this->withReaderMetadata($structuralMetadata, $pdfBytes);
        }
        $maxTextBytes = max(0, (int) ($this->options['maxTextBytes'] ?? self::DEFAULT_MAX_TEXT_BYTES));
        $extractorOptions = $this->options;
        if ($fastTextOnly && $this->pdfMaxPages() === null) {
            $extractorOptions['pdfMaxPages'] = (int) ($this->options['pdfFastMaxPages'] ?? 2);
        }
        if (!$fastTextOnly && $this->needsPositionedPdfText() && !$this->hasExplicitPositionedTextRunLimit()) {
            $extractorOptions['pdfMaxPositionedTextRuns'] = $this->automaticPositionedTextRunLimit($maxTextBytes);
        }
        $extractor = new PdfTextExtractor($extractorOptions);
        $geometryTablesEnabled = !$fastTextOnly && $this->geometryTablesEnabled();
        $proseRepairEnabled = !$fastTextOnly && $this->proseTextRepairEnabled();
        // Complete object-graph-heavy diagnostics before retaining import
        // facts, so its allocator pages do not overlap geometry records.
        $diagnostics = $fastTextOnly ? $this->fastTextOnlyDiagnostics() : $extractor->diagnostics($pdfBytes);
        $this->releaseTransientPdfMemory();
        $importFacts = $this->collectPdfImportFacts(
            $extractor,
            $pdfBytes,
            $maxTextBytes,
            !$fastTextOnly,
            !$fastTextOnly && ($geometryTablesEnabled || $proseRepairEnabled),
            $geometryTablesEnabled,
            $proseRepairEnabled
        );
        $limitedTextLineItems = $importFacts['limitedTextLineItems'];
        $limitedLines = array_column($limitedTextLineItems, 'text');
        $pdfTextLineCount = $importFacts['textLineCount'];
        $pdfTextBytes = $importFacts['textBytes'];
        $pdfTextInsertedBytes = $this->pdfTextLineItemsByteLength($limitedTextLineItems);
        $runs = $importFacts['rawRuns'];
        $pdfTextRunCount = $importFacts['textRunCount'];
        unset($runs);
        $limitedPositionedRuns = $importFacts['limitedPositionedTextRuns'];
        $pdfPositionedTextRunCount = $importFacts['positionedTextRunCount'];
        $pdfPositionedTextInsertedRunCount = count($limitedPositionedRuns);
        $filledRectangles = $importFacts['filledRectangles'];
        $pdfFilledRectangleCount = count($filledRectangles);
        unset($importFacts);
        $this->releaseTransientPdfMemory();
        $linkAnnotations = is_array($diagnostics['linkAnnotations'] ?? null) ? $diagnostics['linkAnnotations'] : [];
        $textAnnotations = is_array($diagnostics['textAnnotations'] ?? null) ? $diagnostics['textAnnotations'] : [];
        $fileAttachmentAnnotations = is_array($diagnostics['fileAttachmentAnnotations'] ?? null) ? $diagnostics['fileAttachmentAnnotations'] : [];
        $popupAnnotations = is_array($diagnostics['popupAnnotations'] ?? null) ? $diagnostics['popupAnnotations'] : [];
        $appearanceAnnotations = is_array($diagnostics['appearanceAnnotations'] ?? null) ? $diagnostics['appearanceAnnotations'] : [];

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
        $geometryTableBlocksByPage = [];
        $geometryTableBlocks = $geometryTablesEnabled && $taggedBlocks === []
            ? $this->blocksFromPositionedTables($limitedPositionedRuns, $filledRectangles, $geometryTableBlocksByPage)
            : [];
        unset($filledRectangles);
        $geometryTableCount = $this->countNodesOfType($geometryTableBlocks, 'table');
        $positionedLineItems = [];
        $positionedLines = [];
        $positionedRunsAreGlyphFragments = false;
        if ($taggedBlocks === [] && $proseRepairEnabled && $limitedPositionedRuns !== []) {
            $positionedRunsAreGlyphFragments = $this->positionedRunsArePredominantlyGlyphFragments(
                $limitedPositionedRuns,
                count($limitedLines)
            );
            $positionedLineItems = $this->positionedProseLineItemsFromTextRuns($limitedPositionedRuns);
            $positionedLines = $this->positionedLineItemTexts($positionedLineItems);
        }
        // Geometry tables are now materialized and prose uses compact visual
        // lines. Retaining every glyph-level record through matching and
        // repair needlessly dominates the peak for dense technical PDFs.
        unset($limitedPositionedRuns);
        $this->releaseTransientPdfMemory();
        $geometryTableFallback = false;
        if ($geometryTableBlocks !== [] && (
            $this->blocksHaveSuspiciousPdfTableText($geometryTableBlocks)
            || $this->geometryPdfTableBlocksLookOversegmented($geometryTableBlocks)
        )) {
            $fallbackLines = $limitedLines;
            $fallbackLayouts = [];
            if ($proseRepairEnabled && $positionedLineItems !== []) {
                $fallbackSourceOrder = $this->sourceTextLineItemsInVisualOrder(
                    $limitedTextLineItems,
                    $positionedLineItems
                );
                if ($fallbackSourceOrder['geometryPages'] > 0) {
                    $fallbackLines = $this->positionedLineItemTexts($fallbackSourceOrder['items']);
                    $fallbackLayouts = $fallbackSourceOrder['items'];
                }
            }
            $textFallbackLines = $proseRepairEnabled
                ? $this->repairProseTextLines(
                    $fallbackLines,
                    $this->looksLikeProseRepairCandidate($fallbackLines),
                    $fallbackLayouts
                )
                : $limitedLines;
            $textTableBlocks = $this->blocksFromLines($textFallbackLines);
            if ($this->countNodesOfType($textTableBlocks, 'table') === 0 || $this->blocksHaveSuspiciousPdfTableText($textTableBlocks)) {
                $textTableBlocks = $this->blocksFromCurrencyRecordLines($limitedLines);
            }
            if ($this->countNodesOfType($textTableBlocks, 'table') > 0 && !$this->blocksHaveSuspiciousPdfTableText($textTableBlocks)) {
                $geometryTableBlocks = $textTableBlocks;
                $geometryTableBlocksByPage = [];
                $geometryTableFallback = true;
            }
        }
        $repairSourceLines = $limitedLines;
        $repairSourceLayouts = $limitedTextLineItems;
        $repairSource = 'text';
        $positionedCodeBlocks = [];
        if ($taggedBlocks === [] && $proseRepairEnabled) {
            $positionedCodeBlocks = $this->positionedCodeBlocksFromLineItems($positionedLineItems);
            $sourceOrderedItems = $this->sourceTextLineItemsInVisualOrder(
                $limitedTextLineItems,
                $positionedLineItems
            );
            if ($sourceOrderedItems['geometryPages'] > 0) {
                $repairSourceLines = $this->positionedLineItemTexts($sourceOrderedItems['items']);
                $repairSourceLayouts = $sourceOrderedItems['items'];
                $repairSource = 'text-geometry';
            } elseif ($this->positionedProseLinesLookUsable(
                $positionedLines,
                $limitedLines,
                [],
                $positionedRunsAreGlyphFragments
            )) {
                $repairSourceLines = $positionedLines;
                $repairSourceLayouts = $positionedLineItems;
                $repairSource = 'positioned';
            } elseif ($sourceOrderedItems['items'] !== []) {
                $repairSourceLines = $this->positionedLineItemTexts($sourceOrderedItems['items']);
                $repairSourceLayouts = $sourceOrderedItems['items'];
                $repairSource = 'text';
            }
        }
        unset($positionedLineItems, $positionedLines, $sourceOrderedItems, $fallbackSourceOrder);
        if ($repairSource !== 'positioned' && $positionedCodeBlocks !== []) {
            $codeInjection = $this->injectPositionedCodeBlocksIntoRepairSource(
                $repairSourceLines,
                $repairSourceLayouts,
                $positionedCodeBlocks
            );
            $repairSourceLines = $codeInjection['lines'];
            $repairSourceLayouts = $codeInjection['layouts'];
            $positionedCodeBlocks = $codeInjection['remainingCodeBlocks'];
        }
        $repairedLines = $taggedBlocks === [] && $proseRepairEnabled
            ? $this->repairProseTextLines($repairSourceLines, $this->looksLikeProseRepairCandidate($repairSourceLines), $repairSourceLayouts)
            : $limitedLines;
        if ($repairSource !== 'positioned' && $positionedCodeBlocks !== []) {
            $repairedLines = $this->injectPositionedCodeBlocks($repairedLines, $positionedCodeBlocks);
        }
        if ($taggedBlocks !== []) {
            $blocks = $taggedBlocks;
        } elseif ($geometryTableBlocks !== []) {
            if ($geometryTableFallback) {
                // The fallback table was selected before later prose repair
                // protects code listings and resolves source/geometry joins.
                // Prefer that final stream whenever it preserves the same
                // table coverage.
                $repairedBlocks = $this->blocksFromLines($repairedLines);
                $blocks = $this->countNodesOfType($repairedBlocks, 'table')
                    >= $this->countNodesOfType($geometryTableBlocks, 'table')
                    ? $repairedBlocks
                    : $geometryTableBlocks;
            } else {
                $blocks = $geometryTableBlocksByPage !== [] && $positionedCodeBlocks === []
                    ? $this->blocksWithPositionedPdfTablePages(
                        $repairSourceLines,
                        $repairSourceLayouts,
                        $geometryTableBlocksByPage
                    )
                    : $geometryTableBlocks;
            }
        } else {
            $blocks = $this->blocksFromLines($repairedLines);
        }
        // Link labels are recovered from positioned annotation geometry while
        // body text can still be reordered or repaired below. Select a label
        // only after the final block text exists, otherwise a harmless
        // whitespace repair can make a valid annotation disappear.
        $appliedLinkAnnotations = $this->unambiguousLinkAnnotations($linkAnnotations, $blocks);
        $blocks = $appliedLinkAnnotations === [] ? $blocks : $this->applyLinkAnnotationsToBlocks($blocks, $appliedLinkAnnotations);
        $pdfWarnings = is_array($diagnostics['warnings'] ?? null) ? array_values(array_map(static fn (mixed $warning): string => (string) $warning, $diagnostics['warnings'])) : [];
        if ($this->lowConfidenceGeometryTableCandidates > 0 && $geometryTableBlocks === []) {
            $pdfWarnings[] = 'PDF table-like geometry was preserved as text because native table confidence was low.';
        }
        $metadata = array_replace($structuralMetadata, [
            'pdfExtractor' => PdfTextExtractor::class,
            'pdfFastTextOnly' => $fastTextOnly,
            'pdfTextLines' => $pdfTextLineCount,
            'pdfTextRuns' => $pdfTextRunCount,
            'pdfPositionedTextRuns' => $pdfPositionedTextRunCount,
            'pdfPositionedTextInsertedRuns' => $pdfPositionedTextInsertedRunCount,
            'pdfFilledRectangles' => $pdfFilledRectangleCount,
            'pdfTextBytes' => $pdfTextBytes,
            'pdfTextInsertedBytes' => $pdfTextInsertedBytes,
            'pdfTextLimited' => $pdfTextInsertedBytes < $pdfTextBytes,
            'pdfMaxPages' => $this->pdfMaxPages(),
            'pdfTextRepair' => $repairedLines !== $limitedLines,
            'pdfTextRepairSource' => $repairedLines !== $limitedLines ? $repairSource : null,
            'pdfDetectedTables' => $this->countNodesOfType($blocks, 'table'),
            'pdfDetectedCodeBlocks' => $this->countNodesOfType($blocks, 'code_block'),
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

    /**
     * PHP retains allocator pages after large extraction buffers are unset.
     * Releasing those pages between independent PDF passes keeps bounded
     * imports within the memory limits common on shared hosting.
     */
    private function releaseTransientPdfMemory(): void
    {
        if (function_exists('gc_mem_caches')) {
            gc_mem_caches();
        }
    }

    /**
     * Consume shared PDF facts as they are decoded. The reader ultimately
     * needs only a bounded prefix of text and positioned runs, while raw text
     * is retained only when prose repair needs cross-run evidence.
     *
     * @return array{
     *     limitedTextLineItems: list<array{page: int, stream: int, text: string}>,
     *     textLineCount: int,
     *     textBytes: int,
     *     rawRuns: list<string>,
     *     textRunCount: int,
     *     limitedPositionedTextRuns: list<array<string, mixed>>,
     *     positionedTextRunCount: int,
     *     filledRectangles: list<array<string, mixed>>
     * }
     */
    private function collectPdfImportFacts(
        PdfTextExtractor $extractor,
        string $pdfBytes,
        int $maxTextBytes,
        bool $includeTextRuns,
        bool $includePositionedTextRuns,
        bool $includeFilledRectangles,
        bool $retainRawRuns
    ): array {
        $limitedTextLineItems = [];
        $textLineCount = 0;
        $textBytes = 0;
        $textSourceIndex = 0;
        $limitedTextBytes = 0;
        $textLimitReached = $maxTextBytes <= 0;
        $rawRuns = [];
        $textRunCount = 0;
        $limitedPositionedTextRuns = [];
        $positionedTextRunCount = 0;
        $positionedBytes = 0;
        $positionedLimitReached = $maxTextBytes <= 0;
        $filledRectangles = [];

        foreach ($extractor->streamImportFacts(
            $pdfBytes,
            $includeTextRuns,
            $includePositionedTextRuns,
            $includeFilledRectangles
        ) as $facts) {
            foreach ($facts['textLineItems'] as $item) {
                $normalizedItem = $this->normalizePdfTextLineItem($item, $textSourceIndex);
                $textSourceIndex++;
                if ($normalizedItem === null) {
                    continue;
                }

                $textBytes += strlen($normalizedItem['text']) + ($textLineCount === 0 ? 0 : 1);
                $textLineCount++;
                if (!$textLimitReached && !$this->appendLimitedPdfTextLineItem(
                    $limitedTextLineItems,
                    $limitedTextBytes,
                    $normalizedItem,
                    $maxTextBytes
                )) {
                    $textLimitReached = true;
                }
            }

            foreach ($facts['textRuns'] as $run) {
                $textRunCount++;
                if ($retainRawRuns) {
                    $rawRuns[] = $run;
                }
            }

            foreach ($facts['positionedTextRuns'] as $run) {
                $positionedTextRunCount++;
                if (!$positionedLimitReached && !$this->appendLimitedPositionedTextRun(
                    $limitedPositionedTextRuns,
                    $positionedBytes,
                    $run,
                    $maxTextBytes
                )) {
                    $positionedLimitReached = true;
                }
            }

            foreach ($facts['filledRectangles'] as $rectangle) {
                $filledRectangles[] = $rectangle;
            }
        }

        return [
            'limitedTextLineItems' => $limitedTextLineItems,
            'textLineCount' => $textLineCount,
            'textBytes' => $textBytes,
            'rawRuns' => $rawRuns,
            'textRunCount' => $textRunCount,
            'limitedPositionedTextRuns' => $limitedPositionedTextRuns,
            'positionedTextRunCount' => $positionedTextRunCount,
            'filledRectangles' => $filledRectangles,
        ];
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

    private function needsPositionedPdfText(): bool
    {
        return $this->geometryTablesEnabled() || $this->proseTextRepairEnabled();
    }

    private function hasExplicitPositionedTextRunLimit(): bool
    {
        foreach (['pdfMaxPositionedTextRuns', 'maxPositionedTextRuns'] as $key) {
            if (array_key_exists($key, $this->options) && $this->options[$key] !== null && $this->options[$key] !== '') {
                return true;
            }
        }

        return false;
    }

    private function automaticPositionedTextRunLimit(int $maxTextBytes): int
    {
        // Geometry is only retained for the bounded text payload. The cap keeps
        // glyph-heavy PDFs from turning a prose-order repair into an unbounded
        // memory allocation while still covering normal multi-page documents.
        return min(20_000, max(5_000, intdiv(max(1, $maxTextBytes), 4)));
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

        return $maxPages !== null
            && (($structuralMetadata['pdfPageCountLimited'] ?? false) === true || $estimatedPages > $maxPages * 2);
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
        ];

        $documentMetadata = $this->documentStructuralMetadata($pdfBytes);
        $metadata['pdfEstimatedPages'] = max(0, (int) ($documentMetadata['page_count'] ?? 0));
        $metadata['pdfObjectCount'] = max(0, (int) ($documentMetadata['object_count'] ?? 0));
        $metadata['pdfStreamCount'] = max(0, (int) ($documentMetadata['stream_count'] ?? 0));
        $metadata['pdfEncrypted'] = (($documentMetadata['encryption']['is_encrypted'] ?? false) === true);
        if (($documentMetadata['page_count_limited'] ?? false) === true) {
            $metadata['pdfPageCountLimited'] = true;
        }

        return $metadata;
    }

    /**
     * Add small, bounded document metadata only after fast-mode selection.
     * PDF Info retains the long-standing precedence over XMP for this reader.
     *
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function withReaderMetadata(array $metadata, string $pdfBytes): array
    {
        $documentMetadata = $this->documentMetadata($pdfBytes);
        $info = is_array($documentMetadata['info'] ?? null) ? $documentMetadata['info'] : [];

        $title = $this->metadataString($info, 'Title');
        if ($title === '') {
            $title = $this->metadataString($documentMetadata, 'title');
        }
        if ($title !== '') {
            $metadata['title'] = $title;
            $metadata['titleInlines'] = [new AstNode('text', ['text' => $title])];
        }

        $infoAuthor = $this->metadataString($info, 'Author');
        $author = $infoAuthor === ''
            ? ''
            : $this->firstMetadataString(preg_split('/\s*;\s*/', $infoAuthor, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        if ($author === '') {
            $author = $this->firstMetadataString($documentMetadata['authors'] ?? null);
        }
        if ($author !== '') {
            $metadata['author'] = $author;
        }

        foreach ([
            'Creator' => ['creator_tool', 'creator'],
            'Producer' => ['producer', 'producer'],
            'CreationDate' => ['created_at', 'created'],
        ] as $infoKey => [$documentKey, $metadataKey]) {
            $value = $this->metadataString($info, $infoKey);
            if ($value === '') {
                $value = $this->metadataString($documentMetadata, $documentKey);
            }
            if ($value !== '') {
                $metadata[$metadataKey] = $value;
            }
        }

        return $metadata;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array{page: int, stream: int, text: string}>
     */
    private function normalizePdfTextLineItems(array $items): array
    {
        $normalized = [];
        foreach ($items as $index => $item) {
            $normalizedItem = $this->normalizePdfTextLineItem($item, $index);
            if ($normalizedItem !== null) {
                $normalized[] = $normalizedItem;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $item
     * @return array{page: int, stream: int, text: string}|null
     */
    private function normalizePdfTextLineItem(array $item, int $index): ?array
    {
        $line = isset($item['text']) ? (string) $item['text'] : '';
        $line = $this->normalizePdfTextEncoding($line);
        $line = str_replace("\0", '', $line);
        $line = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $line) ?? $line;
        $line = trim($line);
        if ($line === '') {
            return null;
        }

        return [
            'page' => max(1, (int) ($item['page'] ?? $index + 1)),
            'stream' => max(1, (int) ($item['stream'] ?? $index + 1)),
            'text' => $line,
        ];
    }

    /**
     * @param list<array{page: int, stream: int, text: string}> $items
     */
    private function pdfTextLineItemsByteLength(array $items): int
    {
        $bytes = 0;
        foreach ($items as $index => $item) {
            $bytes += strlen($item['text']);
            if ($index > 0) {
                ++$bytes;
            }
        }

        return $bytes;
    }

    /**
     * Compatibility helper for internal callers that only need normalized
     * strings rather than source page and stream provenance.
     *
     * @param list<string> $lines
     * @return list<string>
     */
    private function normalizeLines(array $lines): array
    {
        $items = [];
        foreach ($lines as $index => $line) {
            $items[] = ['page' => 1, 'stream' => $index + 1, 'text' => $line];
        }

        return array_column($this->normalizePdfTextLineItems($items), 'text');
    }

    private function normalizePdfTextEncoding(string $text): string
    {
        $text = $this->repairPdfControlLigatures($text);
        if ($text === '' || preg_match('//u', $text) === 1) {
            return $text;
        }

        $decoded = @iconv('Windows-1252', 'UTF-8//IGNORE', $text);
        if (is_string($decoded) && $decoded !== '') {
            return $this->repairPdfControlLigatures($decoded);
        }

        $decoded = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
        return is_string($decoded) ? $this->repairPdfControlLigatures($decoded) : $text;
    }

    private function repairPdfControlLigatures(string $text): string
    {
        if (!str_contains($text, "\x02")) {
            return $text;
        }

        return preg_replace('/((?<=\p{L})\x02(?=\p{Ll})|\x02(?=\p{Ll}))/u', 'fi', $text) ?? $text;
    }

    /**
     * @param list<array{page: int, stream: int, text: string}> $items
     * @return list<array{page: int, stream: int, text: string}>
     */
    private function limitPdfTextLineItems(array $items, int $maxBytes): array
    {
        $limited = [];
        $bytes = 0;
        foreach ($items as $item) {
            if (!$this->appendLimitedPdfTextLineItem($limited, $bytes, $item, $maxBytes)) {
                break;
            }
        }

        return $limited;
    }

    /**
     * @param list<array{page: int, stream: int, text: string}> $items
     * @param array{page: int, stream: int, text: string} $item
     */
    private function appendLimitedPdfTextLineItem(array &$items, int &$bytes, array $item, int $maxBytes): bool
    {
        if ($maxBytes <= 0) {
            return false;
        }

        $line = $item['text'];
        $nextBytes = strlen($line) + ($items === [] ? 0 : 1);
        if ($bytes + $nextBytes > $maxBytes) {
            $remaining = $maxBytes - $bytes - ($items === [] ? 0 : 1);
            $line = $remaining > 0 ? trim(substr($line, 0, $remaining)) : '';
            if ($line !== '') {
                $items[] = array_replace($item, ['text' => $line]);
            }

            return false;
        }

        $items[] = $item;
        $bytes += $nextBytes;

        return true;
    }

    /**
     * @param list<array<string, mixed>> $runs
     * @return list<array<string, mixed>>
     */
    private function limitPositionedTextRuns(array $runs, int $maxBytes): array
    {
        $limited = [];
        $bytes = 0;
        foreach ($runs as $run) {
            if (!$this->appendLimitedPositionedTextRun($limited, $bytes, $run, $maxBytes)) {
                break;
            }
        }

        return $limited;
    }

    /**
     * @param list<array<string, mixed>> $runs
     * @param array<string, mixed> $run
     */
    private function appendLimitedPositionedTextRun(array &$runs, int &$bytes, array $run, int $maxBytes): bool
    {
        if ($maxBytes <= 0) {
            return false;
        }

        $text = isset($run['text']) ? (string) $run['text'] : '';
        if ($text === '') {
            return true;
        }

        $nextBytes = strlen($text) + ($runs === [] ? 0 : 1);
        if ($bytes + $nextBytes > $maxBytes) {
            return false;
        }

        $runs[] = $run;
        $bytes += $nextBytes;

        return true;
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
     * The ordinary text layer generally preserves words and whitespace better
     * than glyph-level positioning. When positioning can independently match
     * those lines, retain the text layer and borrow only its visual order.
     *
     * @param list<array{page: int, stream: int, text: string}> $sourceItems
     * @param list<array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float, code?: bool, codeText?: string}> $positionedItems
     * @return array{items: list<array<string, mixed>>, geometryPages: int}
     */
    private function sourceTextLineItemsInVisualOrder(
        array $sourceItems,
        array $positionedItems
    ): array
    {
        if ($sourceItems === []) {
            return ['items' => [], 'geometryPages' => 0];
        }

        $sourceByPage = [];
        foreach ($sourceItems as $item) {
            $sourceByPage[$item['page']][] = $item;
        }
        $positionedByPage = [];
        foreach ($positionedItems as $item) {
            $positionedByPage[$item['page']][] = $item;
        }

        $ordered = [];
        $geometryPages = 0;
        foreach ($sourceByPage as $page => $pageSourceItems) {
            $pagePositionedItems = $positionedByPage[$page] ?? [];
            $positionedBodyColumns = $this->sourcePdfStableTextColumns($pagePositionedItems);
            $hasStablePositionedBodyColumns = $this->sourcePdfColumnsLookLikeParallelBodyColumns(
                $positionedBodyColumns
            );
            if ($hasStablePositionedBodyColumns) {
                // Match source lines only after geometry has reassembled
                // independently positioned fragments on the same baseline.
                // Otherwise a formula suffix can make its surrounding body
                // line look like an unrelated interrupted flow.
                $pagePositionedItems = $this->composePositionedPdfInlineFragmentsWithinStableColumns(
                    $pagePositionedItems,
                    $positionedBodyColumns
                );
            }
            if ($this->sourcePdfPageContainsReferenceList($pageSourceItems)) {
                foreach ($this->sourcePdfReferenceItemsInSourceOrder($pageSourceItems, $pagePositionedItems) as $item) {
                    $ordered[] = $item;
                }
                continue;
            }
            $match = $this->matchSourcePdfLinesToPositionedItems(
                $pageSourceItems,
                $pagePositionedItems
            );
            $hasCode = $this->positionedPdfPageContainsCode($pagePositionedItems);
            $hasTextTable = $this->sourcePdfPageContainsTextTable($pageSourceItems);
            $geometryLooksUsable = $this->positionedPdfPageGeometryLooksUsable($pagePositionedItems);
            if ($geometryLooksUsable) {
                $geometryMatchIsReliable = $this->sourcePdfLineGeometryMatchIsReliable($pageSourceItems, $match['sourceIndexes']);
                $geometryItems = $this->sourcePdfItemsInVisualOrder(
                    $pageSourceItems,
                    $match,
                    !$geometryMatchIsReliable
                );
                $hasStableBodyColumns = $this->sourcePdfGeometryOrderHasStableBodyColumns($geometryItems);
                $preservesTextTable = !$hasTextTable || $this->sourcePdfGeometryOrderPreservesTextTable($geometryItems);
                $preservesCode = !$hasCode || $this->sourcePdfGeometryOrderPreservesPositionedCode(
                    $geometryItems,
                    $pagePositionedItems
                );
                if (!$hasCode
                    && !$hasTextTable
                    && !$geometryMatchIsReliable
                    && ($this->sourcePdfGeometryBodyColumnCount($geometryItems) >= 3
                        || $hasStablePositionedBodyColumns)
                    && $this->positionedPdfPageTextLooksUsable($pageSourceItems, $pagePositionedItems)) {
                    $geometryPages++;
                    $positionedBodyItems = $this->orderSourcePdfItemsWithinStableColumns($pagePositionedItems);
                    foreach ($this->markPositionedPdfParagraphBoundaries($positionedBodyItems) as $item) {
                        $ordered[] = $item;
                    }
                    continue;
                }
                if (($geometryMatchIsReliable || $hasStableBodyColumns) && $preservesTextTable && $preservesCode) {
                    $geometryPages++;
                    foreach ($this->markPositionedPdfParagraphBoundaries($geometryItems) as $item) {
                        $ordered[] = $item;
                    }
                    continue;
                }
            }

            if (!$hasCode && !$hasTextTable && $geometryLooksUsable
                && $this->positionedPdfPageTextLooksUsable($pageSourceItems, $pagePositionedItems)) {
                $geometryPages++;
                foreach ($pagePositionedItems as $item) {
                    $ordered[] = $item;
                }
                continue;
            }

            foreach ($pageSourceItems as $sourceItem) {
                $ordered[] = $this->sourcePdfLineItem($sourceItem);
            }
        }

        $ordered = $this->prioritizeSourcePdfVerifiedCrossColumnContinuationPages($ordered);
        // A terminal hard hyphen is ambiguous in isolation. Mark only the
        // source/layout pair that proves a conventional wrapped continuation;
        // the repair stage will consume this occurrence-local marker rather
        // than turning a word shape into a document-wide replacement rule.
        $ordered = $this->markSourcePdfLocalWrappedHyphenJoins($ordered, $sourceItems);

        return [
            'items' => $ordered,
            'geometryPages' => $geometryPages,
        ];
    }

    /**
     * Identify hard-hyphen joins at the exact adjacent source/layout pair
     * where they occur. A corpus token or character-transition count is only
     * evidence for that pair; it is never retained as a reusable text hint.
     *
     * @param list<array<string, mixed>> $items
     * @param list<array{page: int, stream: int, text: string}> $sourceItems
     * @return list<array<string, mixed>>
     */
    private function markSourcePdfLocalWrappedHyphenJoins(array $items, array $sourceItems): array
    {
        if (count($items) < 2 || $sourceItems === []) {
            return $items;
        }

        $sourceByPage = [];
        $documentText = '';
        foreach ($sourceItems as $sourceItem) {
            $page = (int) ($sourceItem['page'] ?? 0);
            $sourceByPage[$page][] = $sourceItem;
            $documentText .= "\n" . $this->normalizePdfTextEncoding((string) ($sourceItem['text'] ?? ''));
        }

        $knownTokens = [];
        $tokenList = [];
        if (preg_match_all('/(?<![\p{L}\p{M}\p{N}])([\p{L}][\p{L}\p{M}\p{N}]*)(?![\p{L}\p{M}\p{N}])/u', $documentText, $matches) !== false) {
            foreach ($matches[1] as $token) {
                $normalized = $this->pdfLowercaseToken($token);
                $knownTokens[$normalized] = true;
                $tokenList[] = $normalized;
            }
        }
        $knownHyphenatedTokens = [];
        if (preg_match_all('/(?<![\p{L}\p{M}\p{N}])([\p{L}\p{M}\p{N}]+(?:[-\x{2010}\x{2011}][\p{L}\p{M}\p{N}]+)+)(?![\p{L}\p{M}\p{N}])/u', $documentText, $compoundMatches) !== false) {
            foreach ($compoundMatches[1] as $token) {
                $knownHyphenatedTokens[$this->pdfLowercaseToken($token)] = true;
            }
        }
        $trigramCounts = $this->pdfLocalHyphenTrigramCounts($tokenList);

        for ($index = 0, $count = count($items) - 1; $index < $count; $index++) {
            $previous = &$items[$index];
            $following = &$items[$index + 1];
            $page = (int) ($previous['page'] ?? 0);
            $previousSourceIndex = $previous['sourcePdfSourceIndexEnd'] ?? $previous['sourcePdfSourceIndex'] ?? null;
            $followingSourceIndex = $following['sourcePdfSourceIndex'] ?? null;
            if (!is_int($previousSourceIndex)
                || !is_int($followingSourceIndex)
                || $followingSourceIndex !== $previousSourceIndex + 1
                || $page !== (int) ($following['page'] ?? 0)
                || ($previous['sourceStream'] ?? null) !== ($following['sourceStream'] ?? null)
                || ($following['forceBlockBreakBefore'] ?? false) === true
                || (!$this->sourcePdfItemHasExactPositionedText($previous)
                    && ($previous['sourceFootnotePrefixedGeometry'] ?? false) !== true)
                || !$this->sourcePdfItemHasExactPositionedText($following)
                || !$this->repairedPdfLayoutContinuesWrappedLine($previous, $following)) {
                unset($previous, $following);
                continue;
            }

            $pageSourceItems = $sourceByPage[$page] ?? [];
            $sourcePrevious = (string) ($pageSourceItems[$previousSourceIndex]['text'] ?? '');
            $sourceFollowing = (string) ($pageSourceItems[$followingSourceIndex]['text'] ?? '');
            if (preg_match('/^\s*([\p{L}\p{M}]+)(?![\p{L}\p{M}\p{N}])/u', $sourceFollowing, $continuationMatch) !== 1) {
                unset($previous, $following);
                continue;
            }

            $pair = $page . "\0" . (string) ($previous['sourceStream'] ?? '') . "\0" . $previousSourceIndex . "\0" . $followingSourceIndex;
            // Whitespace before a terminal dash proves punctuation rather
            // than a word fragment. Preserve that separator only at this
            // source/layout occurrence (for example, a bibliography lead
            // followed by a URL on the next visual line).
            if (preg_match('/\s[-\x{2010}\x{2011}]\s*$/u', $sourcePrevious) === 1) {
                $previous['sourcePdfTerminalDashSeparatorPairAfter'] = $pair;
                $following['sourcePdfTerminalDashSeparatorPairBefore'] = $pair;
                unset($previous, $following);
                continue;
            }
            if (preg_match('/([\p{L}\p{M}]+)(\x{00AD}|[-\x{2010}\x{2011}])\s*$/u', rtrim($sourcePrevious), $prefixMatch) !== 1) {
                unset($previous, $following);
                continue;
            }

            $prefix = $prefixMatch[1];
            $hyphen = $prefixMatch[2];
            $continuation = $continuationMatch[1];
            // A discretionary hyphen names a break at this source pair. Its
            // geometry already proved that the adjacent visual lines are one
            // flow, so no token spelling or capitalization rule is needed.
            if ($hyphen === "\u{00AD}") {
                $previous['sourcePdfWrappedHyphenPairAfter'] = $pair;
                $following['sourcePdfWrappedHyphenPairBefore'] = $pair;
                unset($previous, $following);
                continue;
            }
            $joined = $this->pdfLowercaseToken($prefix . $continuation);
            $hyphenated = $this->pdfLowercaseToken($prefix . '-' . $continuation);
            if (isset($knownHyphenatedTokens[$hyphenated])
                || (!isset($knownTokens[$joined])
                    && !$this->pdfLocalCorpusCharacterEvidenceSupportsHyphenJoin($prefix, $continuation, $trigramCounts))) {
                unset($previous, $following);
                continue;
            }

            $previous['sourcePdfWrappedHyphenPairAfter'] = $pair;
            $following['sourcePdfWrappedHyphenPairBefore'] = $pair;
            unset($previous, $following);
        }

        return $items;
    }

    private function pdfLowercaseToken(string $token): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($token, 'UTF-8') : strtolower($token);
    }

    /**
     * @param list<string> $tokens
     * @return array<string, int>
     */
    private function pdfLocalHyphenTrigramCounts(array $tokens): array
    {
        $counts = [];
        foreach ($tokens as $token) {
            if (preg_match_all('/(?=(\p{L}{3}))/u', $token, $matches) === false) {
                continue;
            }
            foreach ($matches[1] as $trigram) {
                $counts[$trigram] = ($counts[$trigram] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * @param array<string, int> $trigramCounts
     */
    private function pdfLocalCorpusCharacterEvidenceSupportsHyphenJoin(
        string $prefix,
        string $continuation,
        array $trigramCounts
    ): bool {
        $prefix = $this->pdfLowercaseToken($prefix);
        $continuation = $this->pdfLowercaseToken($continuation);
        if (preg_match('/(\p{L}{2})$/u', $prefix, $leftMatch) !== 1
            || preg_match('/^(\p{L}{2})/u', $continuation, $rightMatch) !== 1
            || preg_match('/(\p{L})$/u', $leftMatch[1], $leftTail) !== 1
            || preg_match('/^(\p{L})/u', $rightMatch[1], $rightHead) !== 1) {
            return false;
        }

        return ($trigramCounts[$leftMatch[1] . $rightHead[1]] ?? 0) >= 3
            && ($trigramCounts[$leftTail[1] . $rightMatch[1]] ?? 0) >= 3;
    }

    /**
     * A bibliography's source sequence is the most reliable way to keep an
     * entry together across columns. Retain that sequence, but borrow matched
     * positions for later confidence checks: a source-only fragment that is
     * visibly stranded far inside a reference line is not safe prose.
     *
     * @param list<array{page: int, stream: int, text: string}> $sourceItems
     * @param list<array<string, mixed>> $positionedItems
     * @return list<array<string, mixed>>
     */
    private function sourcePdfReferenceItemsInSourceOrder(array $sourceItems, array $positionedItems): array
    {
        $match = $this->matchSourcePdfLinesToPositionedItems($sourceItems, $positionedItems);
        $items = [];
        $referenceStarted = false;
        foreach ($sourceItems as $index => $sourceItem) {
            $referenceStarted = $referenceStarted || $this->lineLooksLikePdfReferenceEntry($sourceItem['text']);
            $matchedItem = $match['itemsBySourceIndex'][$index] ?? null;
            // Directly matched lines may already contain a local, evidence-
            // backed separator repair. A reference page keeps source order,
            // not source spelling, so retain that exact matched occurrence.
            // Fragment composites still expand through the individual source
            // records below to avoid duplicating their shared source line.
            $item = is_array($matchedItem) && !isset($matchedItem['sourcePdfSourceIndexes'])
                ? $matchedItem
                : $this->sourcePdfLineItem($sourceItem, $matchedItem);
            if ($referenceStarted) {
                $item['sourcePdfReferenceEntry'] = true;
                if (is_array($matchedItem)) {
                    $item = $this->markSourcePdfVerifiedGeometryText($item, $matchedItem);
                    $item['text'] = $this->sourcePdfReferenceTextWithLocalPunctuationSeparators(
                        (string) ($item['text'] ?? ''),
                        (string) ($matchedItem['text'] ?? '')
                    );
                }
            }

            $previousItemIndex = array_key_last($items);
            $previousSourceItem = $index > 0 ? ($sourceItems[$index - 1] ?? null) : null;
            if ($previousItemIndex !== null
                && is_array($previousSourceItem)
                && ($items[$previousItemIndex]['sourcePdfReferenceEntry'] ?? false) === true
                && ($item['sourcePdfReferenceEntry'] ?? false) === true
                && ($previousSourceItem['page'] ?? null) === ($sourceItem['page'] ?? null)
                && ($previousSourceItem['stream'] ?? null) === ($sourceItem['stream'] ?? null)
                && preg_match('/[-\x{2010}\x{2011}]\s*$/u', (string) ($previousSourceItem['text'] ?? '')) === 1
                && $this->lineLooksLikeUrlOnly((string) ($sourceItem['text'] ?? ''))
                && $this->repairedPdfLayoutContinuesWrappedLine($items[$previousItemIndex], $item)) {
                $pair = (string) ($sourceItem['page'] ?? '') . "\0" . (string) ($sourceItem['stream'] ?? '') . "\0reference\0" . ($index - 1) . "\0" . $index;
                $items[$previousItemIndex]['sourcePdfTerminalDashSeparatorPairAfter'] = $pair;
                $item['sourcePdfTerminalDashSeparatorPairBefore'] = $pair;
            }
            $items[] = $item;
        }

        return $items;
    }

    /**
     * Reference entries sometimes omit spaces around parenthetical labels in
     * their source stream. Apply only structural punctuation separators and
     * only after the exact source record matched one positioned visual line;
     * no vocabulary or title-shaped text is involved.
     */
    private function sourcePdfReferenceTextWithLocalPunctuationSeparators(string $sourceText, string $positionedText): string
    {
        if ($this->pdfComparableLineText($sourceText) === ''
            || $this->pdfComparableLineText($sourceText) !== $this->pdfComparableLineText($positionedText)) {
            return $sourceText;
        }

        $text = preg_replace('/([\p{L}\p{N}])(?=\()/u', '$1 ', $sourceText) ?? $sourceText;

        return preg_replace('/(\))(?=[\p{L}\p{N}])/u', '$1 ', $text) ?? $text;
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function sourcePdfGeometryOrderHasStableBodyColumns(array $items): bool
    {
        $counts = [];
        foreach ($items as $item) {
            if (($item['sourceStructuredGeometry'] ?? false) !== true
                || !isset($item['sourceGeometryColumn'])) {
                continue;
            }
            $column = (int) $item['sourceGeometryColumn'];
            $counts[$column] = ($counts[$column] ?? 0) + 1;
        }
        if (count($counts) < 2 || count($counts) > 4) {
            return false;
        }

        foreach ($counts as $count) {
            if ($count < 8) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function sourcePdfGeometryBodyColumnCount(array $items): int
    {
        $columns = [];
        foreach ($items as $item) {
            if (($item['sourceStructuredGeometry'] ?? false) !== true
                || !isset($item['sourceGeometryColumn'])) {
                continue;
            }
            $columns[(int) $item['sourceGeometryColumn']] = true;
        }

        return count($columns);
    }

    /**
     * A bibliography is an ordered sequence even when its visual layout uses
     * two columns. Source order keeps each numbered entry and its wrapped
     * continuation together, whereas column reconstruction can otherwise
     * separate their titles from publication details.
     *
     * @param list<array{page: int, stream: int, text: string}> $sourceItems
     */
    private function sourcePdfPageContainsReferenceList(array $sourceItems): bool
    {
        $entries = 0;
        foreach ($sourceItems as $item) {
            if ($this->lineLooksLikePdfReferenceEntry($item['text'])) {
                $entries++;
            }
        }

        return $entries >= 3;
    }

    /**
     * Keep a source-ordered page when the existing text-table recognizer can
     * reconstruct a real table there. Reordering every visual line first
     * destroys the stacked-cell sequence that makes table recovery possible.
     *
     * @param list<array{page: int, stream: int, text: string}> $sourceItems
     */
    private function sourcePdfPageContainsTextTable(array $sourceItems): bool
    {
        $lines = array_column($sourceItems, 'text');
        if ($lines === []) {
            return false;
        }
        $repaired = $this->repairProseTextLines(
            $lines,
            $this->looksLikeProseRepairCandidate($lines),
            []
        );

        return $this->countNodesOfType($this->blocksFromLines($repaired), 'table') > 0;
    }

    /**
     * A page can contain a real table beside ordinary prose. Preserve the
     * source table recognizer only when visual ordering would lose that table;
     * otherwise geometry can still keep the prose columns independent.
     *
     * @param list<array<string, mixed>> $items
     */
    private function sourcePdfGeometryOrderPreservesTextTable(array $items): bool
    {
        $items = $this->markPositionedPdfParagraphBoundaries($items);
        $lines = $this->positionedLineItemTexts($items);
        $repaired = $this->repairProseTextLines(
            $lines,
            $this->looksLikeProseRepairCandidate($lines),
            $items
        );

        return $this->countNodesOfType($this->blocksFromLines($repaired), 'table') > 0;
    }

    /**
     * Geometry must not reorder a code-bearing page unless the later code
     * injector can still recover every sustained monospaced listing.
     *
     * @param list<array<string, mixed>> $items
     * @param list<array<string, mixed>> $positionedItems
     */
    private function sourcePdfGeometryOrderPreservesPositionedCode(
        array $items,
        array $positionedItems
    ): bool {
        $codeBlocks = $this->positionedCodeBlocksFromLineItems($positionedItems);
        if ($codeBlocks === []) {
            return true;
        }

        $items = $this->markPositionedPdfParagraphBoundaries($items);
        $lines = $this->positionedLineItemTexts($items);
        $repaired = $this->repairProseTextLines(
            $lines,
            $this->looksLikeProseRepairCandidate($lines),
            $items
        );
        $injected = $this->injectPositionedCodeBlocks($repaired, $codeBlocks);

        return $this->countNodesOfType($this->blocksFromLines($injected), 'code_block') >= count($codeBlocks);
    }

    /**
     * Geometry-derived code blocks carry their own column alignment. Retain
     * ordinary source order on those pages so the existing code-span injector
     * can replace the complete listing atomically.
     *
     * @param list<array<string, mixed>> $positionedItems
     */
    private function positionedPdfPageContainsCode(array $positionedItems): bool
    {
        foreach ($positionedItems as $item) {
            if (($item['code'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{page: int, stream: int, text: string}> $sourceItems
     * @param list<array<string, mixed>> $positionedItems
     */
    private function positionedPdfPageTextLooksUsable(array $sourceItems, array $positionedItems): bool
    {
        if ($positionedItems === [] || !$this->positionedPdfPageGeometryLooksUsable($positionedItems)) {
            return false;
        }

        return $this->positionedProseLinesLookUsable(
            $this->positionedLineItemTexts($positionedItems),
            array_column($sourceItems, 'text')
        );
    }

    /**
     * Form XObjects can occasionally expose readable text with a broken
     * coordinate transform. In that case several independent visual lines
     * collapse to the same baseline and start position. Treat that geometry as
     * untrustworthy rather than using it to reorder otherwise usable text.
     *
     * @param list<array<string, mixed>> $items
     */
    private function positionedPdfPageGeometryLooksUsable(array $items): bool
    {
        if (count($items) < 3) {
            return true;
        }

        $fontSizes = [];
        foreach ($items as $item) {
            if (!$this->pdfLayoutHasGeometry($item)) {
                return false;
            }
            $fontSizes[] = max(1.0, (float) $item['fontSize']);
        }
        $fontSize = $this->median($fontSizes);
        $baselineTolerance = max(0.35, min(2.0, $fontSize * 0.10));
        $startTolerance = max(3.0, $fontSize * 0.50);
        $baselineGroups = [];
        foreach ($items as $item) {
            $baseline = (float) $item['y1'];
            $groupIndex = null;
            foreach ($baselineGroups as $index => $group) {
                if (abs($baseline - $group['baseline']) <= $baselineTolerance) {
                    $groupIndex = $index;
                    break;
                }
            }
            if ($groupIndex === null) {
                $baselineGroups[] = ['baseline' => $baseline, 'items' => [$item]];
                continue;
            }
            $baselineGroups[$groupIndex]['items'][] = $item;
        }

        foreach ($baselineGroups as $group) {
            if (count($group['items']) < 3) {
                continue;
            }
            $startGroups = [];
            foreach ($group['items'] as $item) {
                $start = (float) $item['x1'];
                $groupIndex = null;
                foreach ($startGroups as $index => $startGroup) {
                    if (abs($start - $startGroup['start']) <= $startTolerance) {
                        $groupIndex = $index;
                        break;
                    }
                }
                if ($groupIndex === null) {
                    $startGroups[] = ['start' => $start, 'items' => [$item]];
                    continue;
                }
                $startGroups[$groupIndex]['items'][] = $item;
            }
            foreach ($startGroups as $startGroup) {
                if (count($startGroup['items']) >= 3) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param list<array{page: int, stream: int, text: string}> $sourceItems
     * @param list<array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float, code?: bool, codeText?: string}> $positionedItems
     * @return array{items: list<array<string, mixed>>, sourceIndexes: array<int, true>, itemsBySourceIndex: array<int, array<string, mixed>>, visualEntries: list<array{item: array<string, mixed>, sourceIndex: int|null}>}
     */
    private function matchSourcePdfLinesToPositionedItems(
        array $sourceItems,
        array $positionedItems
    ): array
    {
        $sourceIndexesByKey = [];
        $sourceIndexesByComparablePrefix = [];
        $sourceIndexesByCompactPrefix = [];
        foreach ($sourceItems as $index => $item) {
            $key = $this->pdfComparableLineText($item['text']);
            if ($this->length($key) >= 3) {
                $sourceIndexesByKey[$key][] = $index;
            }
            if ($this->length($key) >= 8) {
                $sourceIndexesByComparablePrefix[substr($key, 0, 8)][] = $index;
            }
            if ($this->length($key) >= 1 && $this->length($key) <= 3) {
                $sourceIndexesByCompactPrefix[substr($key, 0, 1)][] = $index;
            }
        }

        $positionedItems = $this->combineAdjacentPositionedPdfFragmentsMatchingSourceLines(
            $positionedItems,
            $sourceItems,
            $sourceIndexesByKey,
            $sourceIndexesByComparablePrefix,
            $sourceIndexesByCompactPrefix
        );

        $nextSourceIndexByKey = [];
        $matchedItems = [];
        $matchedSourceIndexes = [];
        $matchedItemsBySourceIndex = [];
        $visualEntries = [];
        $shortSupplementalCandidates = [];
        $previousWasSupplementalPositionedItem = false;
        $consumedPositionedIndexes = [];
        foreach ($positionedItems as $positionedIndex => $positionedItem) {
            if (isset($consumedPositionedIndexes[$positionedIndex])) {
                continue;
            }
            $key = $this->pdfComparableLineText($positionedItem['text']);
            $sourceIndex = null;
            if ($this->length($key) >= 3 && isset($sourceIndexesByKey[$key])) {
                $offset = $nextSourceIndexByKey[$key] ?? 0;
                $sourceIndexes = $sourceIndexesByKey[$key];
                while (isset($sourceIndexes[$offset]) && isset($matchedSourceIndexes[$sourceIndexes[$offset]])) {
                    $offset++;
                }
                if (isset($sourceIndexes[$offset])) {
                    $sourceIndex = $sourceIndexes[$offset];
                    $nextSourceIndexByKey[$key] = $offset + 1;
                }
            }

            $footnotePrefixedSourceMatch = false;
            if ($sourceIndex === null) {
                $sourceIndex = $this->sourcePdfFootnotePrefixedSourceIndexMatchingPositionedLine(
                    $sourceItems,
                    $matchedSourceIndexes,
                    $positionedItem
                );
                $footnotePrefixedSourceMatch = $sourceIndex !== null;
            }

            $fragmentIndexes = $sourceIndex === null
                ? $this->sourcePdfFragmentIndexesMatchingPositionedLine(
                    $sourceItems,
                    $matchedSourceIndexes,
                    $key,
                    $sourceIndexesByComparablePrefix,
                    $sourceIndexesByCompactPrefix
                )
                : [];
            if ($fragmentIndexes !== []) {
                $firstSourceIndex = $fragmentIndexes[0];
                $sourceItem = $this->sourcePdfJoinedFragmentLineItem(
                    $sourceItems,
                    $fragmentIndexes,
                    $positionedItem
                );
                if ($sourceItem === null) {
                    continue;
                }
                $item = $this->sourcePdfLineItem($sourceItem, $positionedItem);
                $item = $this->markSourcePdfVerifiedGeometryText($item, $positionedItem);
                $item['sourcePdfSourceIndexes'] = $fragmentIndexes;
                $item['sourcePdfSourceIndexEnd'] = $fragmentIndexes[array_key_last($fragmentIndexes)];
                foreach ($fragmentIndexes as $fragmentIndex) {
                    $matchedSourceIndexes[$fragmentIndex] = true;
                }
                $matchedItems[] = $item;
                $matchedItemsBySourceIndex[$firstSourceIndex] = $item;
                $visualEntries[] = ['item' => $item, 'sourceIndex' => $firstSourceIndex];
                $previousWasSupplementalPositionedItem = false;
                continue;
            }

            $inlineSiblingIndexes = $this->positionedPdfInlineContinuationSiblingIndexes(
                $positionedItems,
                $positionedIndex
            );
            $inlineText = $inlineSiblingIndexes === []
                ? null
                : $this->positionedPdfInlineContinuationText(
                    $positionedItem,
                    $positionedItems,
                    $inlineSiblingIndexes
                );
            if ($inlineText !== null) {
                $inlineKey = $this->pdfComparableLineText($inlineText);
                $inlineFragmentIndexes = $inlineKey === $key
                    ? []
                    : $this->sourcePdfFragmentIndexesMatchingPositionedLine(
                        $sourceItems,
                        $matchedSourceIndexes,
                        $inlineKey,
                        $sourceIndexesByComparablePrefix,
                        $sourceIndexesByCompactPrefix
                    );
                if ($inlineFragmentIndexes !== []) {
                    $firstSourceIndex = $inlineFragmentIndexes[0];
                    $positionedLayout = $this->positionedPdfCompositeInlineContinuationLayout(
                        $positionedItem,
                        $positionedItems,
                        $inlineSiblingIndexes
                    );
                    $positionedLayout['text'] = $inlineText;
                    $sourceItem = $this->sourcePdfJoinedFragmentLineItem(
                        $sourceItems,
                        $inlineFragmentIndexes,
                        $positionedLayout
                    );
                    if ($sourceItem === null) {
                        continue;
                    }
                    $item = $this->sourcePdfLineItem($sourceItem, $positionedLayout);
                    $item = $this->markSourcePdfVerifiedGeometryText($item, $positionedLayout);
                    $item['sourcePdfSourceIndexes'] = $inlineFragmentIndexes;
                    $item['sourcePdfSourceIndexEnd'] = $inlineFragmentIndexes[array_key_last($inlineFragmentIndexes)];
                    foreach ($inlineFragmentIndexes as $fragmentIndex) {
                        $matchedSourceIndexes[$fragmentIndex] = true;
                    }
                    foreach ($inlineSiblingIndexes as $inlineSiblingIndex) {
                        $consumedPositionedIndexes[$inlineSiblingIndex] = true;
                    }
                    $matchedItems[] = $item;
                    $matchedItemsBySourceIndex[$firstSourceIndex] = $item;
                    $visualEntries[] = ['item' => $item, 'sourceIndex' => $firstSourceIndex];
                    $previousWasSupplementalPositionedItem = false;
                    continue;
                }
            }

            if ($sourceIndex !== null) {
                $matchedSourceIndexes[$sourceIndex] = true;
                // Preserve the source line's own separators here. The
                // positioned layer is authoritative for visual order, but a
                // line whose font advances are only estimated can omit normal
                // spaces. Exact source-fragment reconciliation below handles
                // proven intra-word boundaries occurrence by occurrence.
                $item = $this->sourcePdfLineItem($sourceItems[$sourceIndex], $positionedItem);
                $boundarySeparators = $this->positionedSourceVerifiedBoundarySeparators($positionedItem);
                if ($this->sourcePdfCanApplyProvenPositionedSeparators(
                    $sourceItems,
                    $sourceIndex,
                    $positionedItem,
                    $matchedItemsBySourceIndex
                )) {
                    $item['text'] = $this->sourcePdfLineTextWithProvenPositionedSeparators(
                        (string) $item['text'],
                        (string) ($positionedItem['text'] ?? ''),
                        $boundarySeparators
                    );
                }
                $item = $this->markSourcePdfVerifiedGeometryText($item, $positionedItem);
                if ($footnotePrefixedSourceMatch) {
                    $item['sourceFootnotePrefixedGeometry'] = true;
                }
                $matchedItems[] = $item;
                $matchedItemsBySourceIndex[$sourceIndex] = $item;
                $visualEntries[] = ['item' => $item, 'sourceIndex' => $sourceIndex];
                $previousWasSupplementalPositionedItem = false;
                continue;
            }

            $partialFragmentIndexes = $this->sourcePdfFragmentIndexesExtendingPositionedLine(
                $sourceItems,
                $matchedSourceIndexes,
                $key,
                $sourceIndexesByComparablePrefix
            );
            if ($partialFragmentIndexes !== [] && $inlineText !== null) {
                $firstSourceIndex = $partialFragmentIndexes[0];
                $sourceItem = $this->sourcePdfJoinedFragmentLineItem(
                    $sourceItems,
                    $partialFragmentIndexes,
                    array_replace(
                        $this->positionedPdfCompositeInlineContinuationLayout(
                            $positionedItem,
                            $positionedItems,
                            $inlineSiblingIndexes
                        ),
                        ['text' => $inlineText]
                    )
                );
                if ($sourceItem === null) {
                    continue;
                }
                $positionedLayout = $this->positionedPdfCompositeInlineContinuationLayout(
                    $positionedItem,
                    $positionedItems,
                    $inlineSiblingIndexes
                );
                $item = $this->sourcePdfLineItem($sourceItem, $positionedLayout);
                $item['sourceVerifiedGeometryText'] = true;
                $item['sourceVerifiedPartialInlineGeometry'] = true;
                $item['sourcePdfSourceIndexes'] = $partialFragmentIndexes;
                $item['sourcePdfSourceIndexEnd'] = $partialFragmentIndexes[array_key_last($partialFragmentIndexes)];
                foreach ($partialFragmentIndexes as $fragmentIndex) {
                    $matchedSourceIndexes[$fragmentIndex] = true;
                }
                foreach ($inlineSiblingIndexes as $inlineSiblingIndex) {
                    $consumedPositionedIndexes[$inlineSiblingIndex] = true;
                }
                $matchedItems[] = $item;
                $matchedItemsBySourceIndex[$firstSourceIndex] = $item;
                $visualEntries[] = ['item' => $item, 'sourceIndex' => $firstSourceIndex];
                $previousWasSupplementalPositionedItem = false;
                continue;
            }

            if ($this->positionedPdfLineCanSupplementSource($positionedItem)) {
                $item = $positionedItem;
                $item['sourceSupplementalPositioned'] = true;
                if ($this->positionedPdfLineHasRecoverableSentenceSuffix($positionedItem)) {
                    // The leading visual run is damaged, but punctuation
                    // exposes a complete body sentence after it.
                    $item['sourceSupplementalRecoverableSentenceSuffix'] = true;
                    $item['sourceInterruptedColumnRegion'] = true;
                    $item['forceBlockBreakBefore'] = true;
                }
                if ($this->positionedPdfLineSubstantiallyOverlapsSource($positionedItem, $sourceItems)) {
                    if ($this->lineLooksLikeUrlOnly((string) ($positionedItem['text'] ?? ''))) {
                        $item['sourceSupplementalUrlOverlap'] = true;
                    } else {
                        $item['sourceSupplementalSourceOverlap'] = true;
                    }
                }
                if (!$previousWasSupplementalPositionedItem) {
                    $item['forceBlockBreakBefore'] = true;
                }
                $visualEntries[] = ['item' => $item, 'sourceIndex' => null];
                $previousWasSupplementalPositionedItem = true;
                continue;
            }
            $shortSupplementalCandidates[] = $positionedItem;
            $previousWasSupplementalPositionedItem = false;
        }

        foreach ($shortSupplementalCandidates as $positionedItem) {
            $item = $positionedItem;
            $item['sourceSupplementalPositioned'] = true;
            $item['sourceShortSupplementalCandidate'] = true;
            $visualEntries[] = ['item' => $item, 'sourceIndex' => null];
        }

        return [
            'items' => $matchedItems,
            'sourceIndexes' => $matchedSourceIndexes,
            'itemsBySourceIndex' => $matchedItemsBySourceIndex,
            'visualEntries' => $visualEntries,
        ];
    }

    /**
     * A footnote marker can be emitted as its own source-text item while the
     * positioned layer keeps it immediately before the surrounding prose on a
     * shared baseline. Match the prose suffix only when both layers expose the
     * same adjacent marker. This is source/geometry provenance, not a guess
     * based on the words in the sentence.
     *
     * @param list<array{page: int, stream: int, text: string}> $sourceItems
     * @param array<int, true> $matchedSourceIndexes
     * @param array<string, mixed> $positionedItem
     */
    private function sourcePdfFootnotePrefixedSourceIndexMatchingPositionedLine(
        array $sourceItems,
        array $matchedSourceIndexes,
        array $positionedItem
    ): ?int {
        $positionedText = trim((string) ($positionedItem['text'] ?? ''));
        if (preg_match('/^([0-9]{1,3})\s+(.+)$/u', $positionedText, $matches) !== 1) {
            return null;
        }

        $marker = $this->pdfComparableLineText($matches[1]);
        $suffix = $this->pdfComparableLineText($matches[2]);
        if ($marker === '' || $suffix === '' || $this->length($suffix) < 4) {
            return null;
        }

        foreach ($sourceItems as $index => $sourceItem) {
            if (isset($matchedSourceIndexes[$index])
                || $this->pdfComparableLineText($sourceItem['text']) !== $suffix
                || !isset($sourceItems[$index - 1])) {
                continue;
            }

            $markerItem = $sourceItems[$index - 1];
            if ($this->pdfComparableLineText($markerItem['text']) !== $marker
                || !$this->sourcePdfItemsShareStream($markerItem, $sourceItem)) {
                continue;
            }

            return $index;
        }

        return null;
    }

    /**
     * A text stream can split one visual line at an inline style boundary.
     * The individual positioned fragments then cannot match the source line
     * that contains both fragments, so its fallback geometry is borrowed from
     * a neighbor and a genuine paragraph indent is lost. Recombine only a
     * locally contiguous, same-baseline sequence whose combined text exactly
     * matches a source line; this is layout and provenance evidence rather
     * than a guess based on vocabulary.
     *
     * @param list<array<string, mixed>> $positionedItems
     * @param list<array{page: int, stream: int, text: string}> $sourceItems
     * @param array<string, list<int>> $sourceIndexesByKey
     * @param array<string, list<int>> $sourceIndexesByComparablePrefix
     * @param array<string, list<int>> $sourceIndexesByCompactPrefix
     * @return list<array<string, mixed>>
     */
    private function combineAdjacentPositionedPdfFragmentsMatchingSourceLines(
        array $positionedItems,
        array $sourceItems,
        array $sourceIndexesByKey,
        array $sourceIndexesByComparablePrefix,
        array $sourceIndexesByCompactPrefix
    ): array {
        $combined = [];
        $count = count($positionedItems);
        for ($index = 0; $index < $count; $index++) {
            $item = $positionedItems[$index];
            if (!$this->pdfLayoutHasGeometry($item)
                || ($item['code'] ?? false) === true
                || !isset($item['sourceOrderStart'], $item['sourceOrderEnd'])) {
                $combined[] = $item;
                continue;
            }

            $candidate = $item;
            $matchedEnd = null;
            for ($end = $index + 1; $end < min($count, $index + 4); $end++) {
                $next = $positionedItems[$end];
                if (!$this->positionedPdfFragmentsCanComposeSourceLine($candidate, $next)) {
                    break;
                }

                $candidate = $this->composePositionedPdfSourceLineFragments($candidate, $next);
                if ($candidate === null) {
                    break;
                }
                $key = $this->pdfComparableLineText((string) ($candidate['text'] ?? ''));
                $hasDirectSourceLine = $this->length($key) >= 12 && isset($sourceIndexesByKey[$key]);
                $hasSourceFragmentSequence = !$hasDirectSourceLine
                    && $this->sourcePdfFragmentIndexesMatchingPositionedLine(
                        $sourceItems,
                        [],
                        $key,
                        $sourceIndexesByComparablePrefix,
                        $sourceIndexesByCompactPrefix
                    ) !== [];
                if ($hasDirectSourceLine || $hasSourceFragmentSequence) {
                    $matchedEnd = $end;
                    break;
                }
            }

            if ($matchedEnd === null) {
                $combined[] = $item;
                continue;
            }

            $combined[] = $candidate;
            $index = $matchedEnd;
        }

        return $combined;
    }

    /**
     * Styled run boundaries normally leave a short, terminal lead such as a
     * label or inline subhead before the next fragment resumes body text. Do
     * not compose arbitrary long same-baseline fragments: figures and tables
     * use that geometry too, and their source order is not prose order.
     *
     * @param array<string, mixed> $item
     */
    private function positionedPdfFragmentLooksLikeShortTerminalLead(array $item): bool
    {
        $text = trim((string) ($item['text'] ?? ''));
        $wordCount = count($this->pdfLineWordTokens($text));

        return $wordCount >= 2
            && $wordCount <= 6
            && $this->length($text) <= 72
            && !$this->lineHasPdfListBlockEvidence($text)
            && preg_match('/[.!?:]\s*$/u', $text) === 1;
    }

    /**
     * Bibliographic entries often split publisher or product names across
     * styled fragments on one baseline. The bracketed numeric marker is a
     * structural reference signal, and an exact source-line match still gates
     * the composition, so this does not broaden ordinary prose joining.
     *
     * @param array<string, mixed> $item
     */
    private function positionedPdfFragmentLooksLikeReferenceLead(array $item): bool
    {
        $text = trim((string) ($item['text'] ?? ''));

        return $this->length($text) <= 72
            && count($this->pdfLineWordTokens($text)) <= 6
            && preg_match('/^\[\d{1,3}\]\s+[^\s]+/u', $text) === 1;
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private function positionedPdfFragmentsCanComposeSourceLine(array $left, array $right): bool
    {
        if (!$this->pdfLayoutHasGeometry($left)
            || !$this->pdfLayoutHasGeometry($right)
            || ($left['code'] ?? false) === true
            || ($right['code'] ?? false) === true
            || !isset($left['sourceOrderEnd'], $right['sourceOrderStart'])
            || (int) ($left['page'] ?? 0) !== (int) ($right['page'] ?? 0)) {
            return false;
        }
        if ($this->positionedPdfFragmentSeparator($left, $right) === null) {
            return false;
        }

        $leftOrderEnd = (int) $left['sourceOrderEnd'];
        $rightOrderStart = (int) $right['sourceOrderStart'];
        if ($rightOrderStart <= $leftOrderEnd || $rightOrderStart - $leftOrderEnd > 12) {
            return false;
        }

        $fontSize = max(1.0, (float) $left['fontSize'], (float) $right['fontSize']);
        if (abs((float) $left['fontSize'] - (float) $right['fontSize']) > max(1.5, $fontSize * 0.25)) {
            return false;
        }

        $leftCenter = ((float) $left['y1'] + (float) $left['y2']) / 2.0;
        $rightCenter = ((float) $right['y1'] + (float) $right['y2']) / 2.0;
        if (abs($leftCenter - $rightCenter) > max(2.5, $fontSize * 0.35)
            || (float) $right['x1'] < (float) $left['x1'] - max(2.0, $fontSize * 0.25)) {
            return false;
        }

        $gap = (float) $right['x1'] - (float) $left['x2'];
        $compactInlineContinuation = $this->positionedPdfFragmentIsCompactInlineContinuation($right);

        return $gap >= -max(
            18.0,
            $fontSize * ($compactInlineContinuation ? 6.0 : 3.0)
        )
            && $gap <= max(36.0, $fontSize * 4.0);
    }

    /**
     * Font metrics for a superscript or short inline token can overlap the
     * preceding run's bounding box even though both runs share a baseline and
     * source order. The caller still requires an exact source match before
     * composing the pair, so this only broadens the geometric tolerance for
     * a compact continuation rather than arbitrary overlapping labels.
     *
     * @param array<string, mixed> $item
     */
    private function positionedPdfFragmentIsCompactInlineContinuation(array $item): bool
    {
        $text = trim((string) ($item['text'] ?? ''));
        $compact = $this->pdfComparableLineText($text);
        $tokens = $this->pdfLineWordTokens($text);

        return $compact !== ''
            && $this->length($compact) <= 8
            && (count($tokens) <= 1
                || (count($tokens) <= 2
                    && (preg_match('/[\p{N}\p{P}]/u', $text) === 1
                        || preg_match('/^\p{Lu}{1,3}\b/u', $tokens[0] ?? '') === 1)));
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private function positionedPdfFragmentSeparator(array $left, array $right): ?string
    {
        $leftText = rtrim((string) ($left['text'] ?? ''));
        $rightText = ltrim((string) ($right['text'] ?? ''));
        if ($leftText === '' || $rightText === '') {
            return '';
        }
        if (($left['endsWithWhitespace'] ?? false) === true
            || ($right['startsWithWhitespace'] ?? false) === true) {
            return ' ';
        }
        if (($right['hasWordBoundaryBefore'] ?? false) !== true) {
            return null;
        }

        $leftEnd = (float) ($left['textX2'] ?? $left['x2'] ?? 0.0);
        $rightStart = (float) ($right['textX1'] ?? $right['x1'] ?? 0.0);

        return $this->positionedBoundarySeparator(
            $rightStart - $leftEnd,
            max(1.0, (float) ($left['fontSize'] ?? 0.0), (float) ($right['fontSize'] ?? 0.0)),
            (bool) ($left['endsWithWhitespace'] ?? false),
            (bool) ($right['startsWithWhitespace'] ?? false),
            true,
            (bool) ($right['wordBoundaryBefore'] ?? false),
            is_string($right['wordBoundarySource'] ?? null)
                ? $right['wordBoundarySource']
                : null
        );
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     * @return array<string, mixed>|null
     */
    private function composePositionedPdfSourceLineFragments(array $left, array $right): ?array
    {
        $separator = $this->positionedPdfFragmentSeparator($left, $right);
        if ($separator === null) {
            return null;
        }

        $leftTextX2 = (float) ($left['textX2'] ?? $left['x2'] ?? 0.0);
        $rightTextX1 = (float) ($right['textX1'] ?? $right['x1'] ?? 0.0);
        $fontSize = max(1.0, (float) ($left['fontSize'] ?? 0.0), (float) ($right['fontSize'] ?? 0.0));
        $sourceVerifiedBoundarySeparators = $this->positionedJoinedSourceVerifiedBoundarySeparators(
            $left,
            $right,
            $rightTextX1 - $leftTextX2,
            $fontSize,
            (bool) ($left['endsWithWhitespace'] ?? false),
            (bool) ($right['startsWithWhitespace'] ?? false),
            (bool) ($right['hasWordBoundaryBefore'] ?? false),
            (bool) ($right['wordBoundaryBefore'] ?? false),
            is_string($right['wordBoundarySource'] ?? null)
                ? $right['wordBoundarySource']
                : null
        );

        return array_replace($left, [
            'text' => rtrim((string) ($left['text'] ?? '')) . $separator . ltrim((string) ($right['text'] ?? '')),
            'x1' => min((float) $left['x1'], (float) $right['x1']),
            'y1' => min((float) $left['y1'], (float) $right['y1']),
            'x2' => max((float) $left['x2'], (float) $right['x2']),
            'y2' => max((float) $left['y2'], (float) $right['y2']),
            'textX1' => min((float) ($left['textX1'] ?? $left['x1']), (float) ($right['textX1'] ?? $right['x1'])),
            'textY1' => min((float) ($left['textY1'] ?? $left['y1']), (float) ($right['textY1'] ?? $right['y1'])),
            'textX2' => max((float) ($left['textX2'] ?? $left['x2']), (float) ($right['textX2'] ?? $right['x2'])),
            'textY2' => max((float) ($left['textY2'] ?? $left['y2']), (float) ($right['textY2'] ?? $right['y2'])),
            'fontSize' => $fontSize,
            'sourceOrderStart' => min((int) $left['sourceOrderStart'], (int) $right['sourceOrderStart']),
            'sourceOrderEnd' => max((int) $left['sourceOrderEnd'], (int) $right['sourceOrderEnd']),
            'startsWithWhitespace' => (bool) ($left['startsWithWhitespace'] ?? false),
            'endsWithWhitespace' => (bool) ($right['endsWithWhitespace'] ?? false),
            'hasWordBoundaryBefore' => (bool) ($left['hasWordBoundaryBefore'] ?? false),
            'wordBoundaryBefore' => (bool) ($left['wordBoundaryBefore'] ?? false),
            'wordBoundarySource' => $left['wordBoundarySource'] ?? null,
            'sourceVerifiedBoundarySeparators' => $sourceVerifiedBoundarySeparators,
            'sourceCompositePositionedFragments' => true,
        ]);
    }

    /**
     * The text layer can split one painted visual line at font, size, or
     * glyph-run boundaries. Match only a short, contiguous source sequence
     * whose normalized text exactly covers one positioned visual line. This
     * recovers the source layer's whitespace without using vocabulary or
     * document-specific rules.
     *
     * @param list<array{page: int, stream: int, text: string}> $sourceItems
     * @param array<int, true> $matchedSourceIndexes
     * @param array<string, list<int>> $sourceIndexesByComparablePrefix
     * @return list<int>
     */
    private function sourcePdfFragmentIndexesMatchingPositionedLine(
        array $sourceItems,
        array $matchedSourceIndexes,
        string $positionedKey,
        array $sourceIndexesByComparablePrefix,
        array $sourceIndexesByCompactPrefix = []
    ): array {
        $shortInlineContinuation = $this->length($positionedKey) < 16
            && preg_match('/^\d{1,3}\p{Ll}/u', $positionedKey) === 1;
        if ($this->length($positionedKey) < 16 && !$shortInlineContinuation) {
            return [];
        }

        $prefix = substr($positionedKey, 0, 8);
        $startIndexes = $shortInlineContinuation
            ? array_keys($sourceItems)
            : ($sourceIndexesByComparablePrefix[$prefix] ?? []);
        if ($startIndexes === [] && !$shortInlineContinuation && $this->length($positionedKey) >= 24) {
            $startIndexes = $sourceIndexesByCompactPrefix[substr($positionedKey, 0, 1)] ?? [];
        }
        foreach ($startIndexes as $startIndex) {
            if (isset($matchedSourceIndexes[$startIndex])) {
                continue;
            }

            $sourceStream = $sourceItems[$startIndex]['stream'];
            $joinedKey = '';
            $indexes = [];
            for ($index = $startIndex; $index < count($sourceItems) && $index < $startIndex + 24; $index++) {
                if (isset($matchedSourceIndexes[$index])
                    || $sourceItems[$index]['stream'] !== $sourceStream
                    || ($index > $startIndex && preg_match('/[.!?]\s*$/u', $sourceItems[$index - 1]['text']) === 1)) {
                    break;
                }

                $fragmentKey = $this->pdfComparableLineText($sourceItems[$index]['text']);
                if ($fragmentKey === '') {
                    if ($indexes !== []
                        && $this->sourcePdfFragmentIsInlineIgnorableGlyph($sourceItems[$index]['text'])) {
                        $indexes[] = $index;
                        continue;
                    }
                    break;
                }
                if (!str_starts_with($positionedKey, $joinedKey . $fragmentKey)) {
                    break;
                }

                $joinedKey .= $fragmentKey;
                $indexes[] = $index;
                if (count($indexes) >= 2 && $joinedKey === $positionedKey) {
                    if ((!$shortInlineContinuation
                            && $this->sourcePdfLongFragmentSequenceMatchesPositionedLine($sourceItems, $indexes, $positionedKey))
                        || $this->sourcePdfShortFragmentSequenceMatchesPositionedLine($sourceItems, $indexes, $positionedKey)) {
                        $indexes = $this->sourcePdfFragmentIndexesWithLeadingListMarker(
                            $sourceItems,
                            $indexes,
                            $matchedSourceIndexes
                        );

                        return $this->sourcePdfFragmentIndexesWithTrailingPunctuation(
                            $sourceItems,
                            $indexes,
                            $matchedSourceIndexes
                        );
                    }
                }
            }
        }

        return [];
    }

    private function sourcePdfFragmentIsInlineIgnorableGlyph(string $text): bool
    {
        $text = trim($text);

        return $text !== ''
            && $this->length($text) <= 3
            && preg_match('/^[\p{M}\p{Sk}]+$/u', $text) === 1;
    }

    /**
     * A small superscript can be emitted as its own source record while the
     * following lower-case words share its visual baseline. Accept that short
     * sequence only when it is exactly represented by the positioned line.
     * This keeps ordinary short labels and list markers out of source-line
     * reconciliation.
     *
     * @param list<array{page: int, stream: int, text: string}> $sourceItems
     * @param list<int> $indexes
     */
    private function sourcePdfShortFragmentSequenceMatchesPositionedLine(
        array $sourceItems,
        array $indexes,
        string $positionedKey
    ): bool {
        if (count($indexes) !== 2
            || preg_match('/^\d{1,3}\p{Ll}/u', $positionedKey) !== 1) {
            return false;
        }

        $first = trim($sourceItems[$indexes[0]]['text']);
        $second = ltrim($sourceItems[$indexes[1]]['text']);

        return preg_match('/^\d{1,3}$/u', $first) === 1
            && preg_match('/^\p{Ll}/u', $second) === 1;
    }

    /**
     * A source text layer may split inline notation into individual glyph runs
     * while the positioned layer exposes the complete visual line. Extend the
     * ordinary short-sequence match only when every extra interior record is a
     * compact inline fragment and both layers agree exactly after normalization.
     *
     * @param list<array{page: int, stream: int, text: string}> $sourceItems
     * @param list<int> $indexes
     */
    private function sourcePdfLongFragmentSequenceMatchesPositionedLine(
        array $sourceItems,
        array $indexes,
        string $positionedKey
    ): bool {
        if (count($indexes) <= 4 || $this->length($positionedKey) < 24) {
            return count($indexes) <= 4;
        }

        $first = $this->pdfComparableLineText($sourceItems[$indexes[0]]['text']);
        $last = $this->pdfComparableLineText($sourceItems[$indexes[array_key_last($indexes)]]['text']);
        if (max($this->length($first), $this->length($last)) < 8) {
            $hasSubstantialFragment = false;
            foreach ($indexes as $index) {
                if ($this->length($this->pdfComparableLineText($sourceItems[$index]['text'])) >= 12) {
                    $hasSubstantialFragment = true;
                    break;
                }
            }
            if (!$hasSubstantialFragment) {
                return false;
            }
        }

        $compactInteriorFragments = 0;
        $substantialInteriorFragments = 0;
        foreach (array_slice($indexes, 1, -1) as $index) {
            $sourceText = $sourceItems[$index]['text'];
            $fragment = $this->pdfComparableLineText($sourceText);
            if ($fragment === '') {
                if ($this->sourcePdfFragmentIsInlineIgnorableGlyph($sourceText)) {
                    $compactInteriorFragments++;
                    continue;
                }
                return false;
            }
            if ($this->length($fragment) <= 3) {
                $compactInteriorFragments++;
                continue;
            }
            $substantialInteriorFragments++;
        }

        // A font switch can split a word into a compact accent or superscript
        // plus a normal text run. The positioned visual line gives an exact
        // whole-line match, and the compact interior token proves this is an
        // inline split rather than a sequence of ordinary source lines.
        return $compactInteriorFragments > 0 && $substantialInteriorFragments <= 2;
    }

    /**
     * A bullet can be emitted as a standalone source record immediately
     * before a formula-split list item. Keep it with the verified item so the
     * downstream block parser can preserve the list structure.
     *
     * @param list<array{page: int, stream: int, text: string}> $sourceItems
     * @param list<int> $indexes
     * @param array<int, true> $matchedSourceIndexes
     * @return list<int>
     */
    private function sourcePdfFragmentIndexesWithLeadingListMarker(
        array $sourceItems,
        array $indexes,
        array $matchedSourceIndexes
    ): array {
        $firstIndex = $indexes[0];
        $previousIndex = $firstIndex - 1;
        if (!isset($sourceItems[$previousIndex])
            || isset($matchedSourceIndexes[$previousIndex])
            || $sourceItems[$previousIndex]['page'] !== $sourceItems[$firstIndex]['page']
            || $sourceItems[$previousIndex]['stream'] !== $sourceItems[$firstIndex]['stream']
            || !$this->lineIsStandalonePdfListMarker($sourceItems[$previousIndex]['text'])) {
            return $indexes;
        }

        array_unshift($indexes, $previousIndex);

        return $indexes;
    }

    /**
     * Some text streams emit terminal punctuation as its own record. It has
     * no comparable key, but it is structurally part of the matched source
     * line and must not later surface as an orphaned positioned fragment.
     *
     * @param list<array{page: int, stream: int, text: string}> $sourceItems
     * @param list<int> $indexes
     * @param array<int, true> $matchedSourceIndexes
     * @return list<int>
     */
    private function sourcePdfFragmentIndexesWithTrailingPunctuation(
        array $sourceItems,
        array $indexes,
        array $matchedSourceIndexes
    ): array {
        $lastIndex = $indexes[array_key_last($indexes)];
        $last = $sourceItems[$lastIndex];
        for ($index = $lastIndex + 1; isset($sourceItems[$index]) && $index <= $lastIndex + 2; $index++) {
            if (isset($matchedSourceIndexes[$index])
                || $sourceItems[$index]['page'] !== $last['page']
                || $sourceItems[$index]['stream'] !== $last['stream']) {
                break;
            }
            $text = trim($sourceItems[$index]['text']);
            if (preg_match('/^[,.;:!?\)\]\}]+$/u', $text) !== 1) {
                break;
            }
            $indexes[] = $index;
        }

        return $indexes;
    }

    /**
     * A positioned body line can end immediately before a run of inline
     * notation. Recover the complete source sequence only when that sequence
     * starts with the positioned text, ends at a source sentence boundary, and
     * has the same compact-fragment evidence as an exact long match.
     *
     * @param list<array{page: int, stream: int, text: string}> $sourceItems
     * @param array<int, true> $matchedSourceIndexes
     * @param array<string, list<int>> $sourceIndexesByComparablePrefix
     * @return list<int>
     */
    private function sourcePdfFragmentIndexesExtendingPositionedLine(
        array $sourceItems,
        array $matchedSourceIndexes,
        string $positionedKey,
        array $sourceIndexesByComparablePrefix
    ): array {
        if ($this->length($positionedKey) < 24) {
            return [];
        }

        $startIndexes = $sourceIndexesByComparablePrefix[substr($positionedKey, 0, 8)] ?? [];
        foreach ($startIndexes as $startIndex) {
            if (isset($matchedSourceIndexes[$startIndex])) {
                continue;
            }

            $sourceStream = $sourceItems[$startIndex]['stream'];
            $joinedKey = '';
            $indexes = [];
            $extendsPositionedText = false;
            for ($index = $startIndex; $index < count($sourceItems) && $index < $startIndex + 24; $index++) {
                if (isset($matchedSourceIndexes[$index])
                    || $sourceItems[$index]['stream'] !== $sourceStream
                    || ($index > $startIndex && preg_match('/[.!?]\s*$/u', $sourceItems[$index - 1]['text']) === 1)) {
                    break;
                }

                $fragmentKey = $this->pdfComparableLineText($sourceItems[$index]['text']);
                if ($fragmentKey === '') {
                    break;
                }
                $candidateKey = $joinedKey . $fragmentKey;
                if (!str_starts_with($candidateKey, $positionedKey)
                    && !str_starts_with($positionedKey, $candidateKey)) {
                    break;
                }

                $joinedKey = $candidateKey;
                $indexes[] = $index;
                $extendsPositionedText = $extendsPositionedText
                    || ($joinedKey !== $positionedKey && str_starts_with($joinedKey, $positionedKey));
                if (!$extendsPositionedText
                    || preg_match('/[.!?]\s*$/u', $sourceItems[$index]['text']) !== 1) {
                    continue;
                }

                if (count($indexes) > 4
                    && $this->sourcePdfLongFragmentSequenceMatchesPositionedLine(
                        $sourceItems,
                        $indexes,
                        $joinedKey
                    )) {
                    return $indexes;
                }
                break;
            }
        }

        return [];
    }

    /**
     * The text runs for one visual line can be emitted out of visual-list
     * order. A source-order-adjacent sibling on the same baseline proves that
     * a prefix line really continues through inline notation.
     *
     * @param list<array<string, mixed>> $positionedItems
     * @return list<int>
     */
    private function positionedPdfInlineContinuationSiblingIndexes(array $positionedItems, int $positionedIndex): array
    {
        $item = $positionedItems[$positionedIndex] ?? null;
        if (!is_array($item)
            || !$this->pdfLayoutHasGeometry($item)
            || !isset($item['sourceOrderStart'], $item['sourceOrderEnd'])) {
            return [];
        }

        $fontSize = max(1.0, (float) $item['fontSize']);
        $sourceOrderEnd = (int) $item['sourceOrderEnd'];
        $minimumExtension = (float) $item['x2'] + max(8.0, $fontSize);
        $maximumX2 = (float) $item['x2'];
        $hasCompactInlineSibling = false;
        $siblings = [];
        foreach ($positionedItems as $index => $candidate) {
            if ($index === $positionedIndex
                || !$this->pdfLayoutHasGeometry($candidate)
                || !isset($candidate['sourceOrderStart'], $candidate['sourceOrderEnd'])
                || ($candidate['page'] ?? null) !== ($item['page'] ?? null)
                || (float) $candidate['x1'] < (float) $item['x1'] - max(4.0, $fontSize * 0.5)
                || abs(((float) $candidate['y1'] + (float) $candidate['y2']) / 2.0 - ((float) $item['y1'] + (float) $item['y2']) / 2.0) > max(2.5, $fontSize * 0.35)) {
                continue;
            }

            $candidateStart = (int) $candidate['sourceOrderStart'];
            $candidateEnd = (int) $candidate['sourceOrderEnd'];
            if ($candidateEnd <= (int) $item['sourceOrderEnd']
                || $candidateStart > $sourceOrderEnd + 48) {
                continue;
            }

            $siblings[] = $index;
            $sourceOrderEnd = max($sourceOrderEnd, $candidateEnd);
            $maximumX2 = max($maximumX2, (float) $candidate['x2']);
            $hasCompactInlineSibling = $hasCompactInlineSibling
                || $this->positionedPdfFragmentIsCompactInlineContinuation($candidate);
        }

        return $sourceOrderEnd > (int) $item['sourceOrderEnd']
            && ($maximumX2 >= $minimumExtension || $hasCompactInlineSibling)
            ? $siblings
            : [];
    }

    /**
     * @param array<string, mixed> $item
     * @param list<array<string, mixed>> $positionedItems
     * @param list<int> $siblingIndexes
     */
    private function positionedPdfInlineContinuationText(array $item, array $positionedItems, array $siblingIndexes): ?string
    {
        $fragments = [$item];
        foreach ($siblingIndexes as $index) {
            $sibling = $positionedItems[$index] ?? null;
            if (is_array($sibling)) {
                $fragments[] = $sibling;
            }
        }
        usort($fragments, static function (array $left, array $right): int {
            return ((float) $left['x1'] <=> (float) $right['x1'])
                ?: ((int) ($left['sourceOrderStart'] ?? 0) <=> (int) ($right['sourceOrderStart'] ?? 0));
        });

        $text = '';
        $previous = null;
        foreach ($fragments as $fragment) {
            $fragmentText = trim((string) ($fragment['text'] ?? ''));
            if ($fragmentText === '') {
                continue;
            }
            if ($previous !== null) {
                $separator = $this->positionedPdfFragmentSeparator($previous, $fragment);
                if ($separator === null) {
                    return null;
                }
                $text = rtrim($text) . $separator;
            }
            $text .= ltrim($fragmentText);
            $previous = $fragment;
        }

        return $text;
    }

    /**
     * @param array<string, mixed> $item
     * @param list<array<string, mixed>> $positionedItems
     * @param list<int> $siblingIndexes
     * @return array<string, mixed>
     */
    private function positionedPdfCompositeInlineContinuationLayout(array $item, array $positionedItems, array $siblingIndexes): array
    {
        foreach ($siblingIndexes as $index) {
            $sibling = $positionedItems[$index] ?? null;
            if (!is_array($sibling) || !$this->pdfLayoutHasGeometry($sibling)) {
                continue;
            }
            $item['x1'] = min((float) $item['x1'], (float) $sibling['x1']);
            $item['y1'] = min((float) $item['y1'], (float) $sibling['y1']);
            $item['x2'] = max((float) $item['x2'], (float) $sibling['x2']);
            $item['y2'] = max((float) $item['y2'], (float) $sibling['y2']);
            $item['fontSize'] = max((float) $item['fontSize'], (float) $sibling['fontSize']);
            $item['sourceOrderStart'] = min((int) $item['sourceOrderStart'], (int) $sibling['sourceOrderStart']);
            $item['sourceOrderEnd'] = max((int) $item['sourceOrderEnd'], (int) $sibling['sourceOrderEnd']);
        }

        return $item;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, mixed> $positionedItem
     * @return array<string, mixed>
     */
    private function markSourcePdfVerifiedGeometryText(array $item, array $positionedItem): array
    {
        $sourceKey = $this->pdfComparableLineText((string) ($item['text'] ?? ''));
        $positionedKey = $this->pdfComparableLineText((string) ($positionedItem['text'] ?? ''));
        if ($sourceKey === '' || $sourceKey !== $positionedKey) {
            return $item;
        }

        // Retain exact source-to-positioned provenance even for a short line.
        // The broader geometry-text marker below deliberately excludes short
        // labels because they are weak evidence on their own. A neighboring
        // source/layout pair, however, can use an exact short continuation as
        // one part of its occurrence-local proof without making it a general
        // text or vocabulary rule.
        $item['sourcePdfExactPositionedText'] = true;
        if ($this->length($sourceKey) < 24
            || count($this->pdfLineWordTokens((string) ($item['text'] ?? ''))) < 4) {
            return $item;
        }

        $item['sourceVerifiedGeometryText'] = true;

        return $item;
    }

    /**
     * A full source/positioned text match is retained separately from the
     * longer-prose geometry marker. This lets an adjacent wrapped pair use a
     * short, exact continuation as local provenance while other layout logic
     * keeps its stricter long-line threshold.
     *
     * @param array<string, mixed> $item
     */
    private function sourcePdfItemHasExactPositionedText(array $item): bool
    {
        return ($item['sourceVerifiedGeometryText'] ?? false) === true
            || ($item['sourcePdfExactPositionedText'] ?? false) === true;
    }

    /**
     * @param list<array{page: int, stream: int, text: string}> $sourceItems
     * @param list<int> $indexes
     * @return array{page: int, stream: int, text: string}|null
     */
    private function sourcePdfJoinedFragmentLineItem(
        array $sourceItems,
        array $indexes,
        ?array $matchingPositionedItem = null
    ): ?array
    {
        $first = $sourceItems[$indexes[0]];
        $reconciliation = $this->sourcePdfFragmentSeparatorsFromPositionedText(
            $sourceItems,
            $indexes,
            $matchingPositionedItem
        );
        if ($reconciliation === null) {
            return null;
        }
        $text = '';
        foreach ($indexes as $index) {
            $fragment = $reconciliation['fragments'][$index] ?? trim($sourceItems[$index]['text']);
            if ($fragment === '') {
                continue;
            }
            $needsSpace = $reconciliation['separators'][$index] ?? false;
            if ($text !== '' && $needsSpace) {
                $text .= ' ';
            }
            if ($text !== '' && ($reconciliation['compactIdentifierBoundaryBefore'][$index] ?? false) === true) {
                $text .= self::PDF_SOURCE_COMPACT_IDENTIFIER_BOUNDARY;
            }
            $text .= $fragment;
        }

        return [
            'page' => $first['page'],
            'stream' => $first['stream'],
            'text' => $text,
        ];
    }

    /**
     * Reconcile one contiguous source-fragment sequence with the one
     * positioned visual line it matched. This is deliberately
     * occurrence-local: an `all` + `owed` boundary may be compact in one line
     * and spaced in the next.
     *
     * The positioned line's verified boundary map is applied to the matching
     * compact source occurrence before inter-fragment whitespace is derived.
     * That lets a source fragment contain `ati` while the local visual run
     * proves `at i`, and independently lets the following superscript `2`
     * remain touching. The positioned text is never used as a wholesale text
     * replacement: its compact characters must exactly equal this source
     * sequence and only verified separator offsets are restored.
     *
     * @param list<array{page: int, stream: int, text: string}> $sourceItems
     * @param list<int> $indexes
     * @param array<string, mixed>|null $matchingPositionedItem
     * @return array{fragments: array<int, string>, separators: array<int, bool>, compactIdentifierBoundaryBefore: array<int, true>}|null
     *         `separators[index]` means a space precedes that source index.
     */
    private function sourcePdfFragmentSeparatorsFromPositionedText(
        array $sourceItems,
        array $indexes,
        ?array $matchingPositionedItem
    ): ?array {
        if ($matchingPositionedItem === null) {
            return null;
        }

        $target = $this->pdfExactLayoutText((string) ($matchingPositionedItem['text'] ?? ''));
        if ($target === '') {
            return null;
        }

        $fragments = [];
        $sourceLayout = '';
        $fragmentCompactOffsets = [];
        $sourceCompact = '';
        foreach ($indexes as $index) {
            $fragment = $this->pdfExactLayoutText((string) ($sourceItems[$index]['text'] ?? ''));
            $sourceLayout .= $this->pdfLayoutTextPreservingEdgeWhitespace(
                (string) ($sourceItems[$index]['text'] ?? '')
            );
            if ($fragment !== '') {
                $fragments[$index] = $fragment;
                $fragmentCompactOffsets[$index] = $this->length($sourceCompact);
                $sourceCompact .= preg_replace('/\s+/u', '', $fragment) ?? $fragment;
            }
        }
        $targetCompact = preg_replace('/\s+/u', '', $target) ?? $target;
        if ($fragments === [] || $sourceCompact === '' || $sourceCompact !== $targetCompact) {
            return null;
        }

        // Inline delimiters are a source-level structural boundary. A
        // superscript or styled formula atom can be painted with a different
        // advance from the surrounding prose, so its visual gap is not a
        // reliable instruction to rewrite the source's own notation. Keep
        // only the exact delimited occurrence protected; ordinary words and
        // all other source fragments still use the matched positioned line.
        $protectedDelimiterOffsets = $this->sourcePdfDelimitedNotationSeparatorOffsets($sourceLayout);
        foreach ($protectedDelimiterOffsets as $offset => $_) {
            if ($offset <= 0 || $offset >= $this->length($sourceCompact)) {
                unset($protectedDelimiterOffsets[$offset]);
            }
        }

        $positionedBoundarySeparators = $this->positionedSourceVerifiedBoundarySeparators($matchingPositionedItem);
        $boundarySeparators = [];
        foreach ($positionedBoundarySeparators as $offset => $separator) {
            if ($offset > 0
                && $offset < $this->length($sourceCompact)
                && !isset($protectedDelimiterOffsets[$offset])) {
                $boundarySeparators[$offset] = $separator;
            }
        }

        // Apply a local geometry decision to the fragments that contain its
        // compact boundary. Decisions that land exactly between two source
        // fragments are intentionally deferred to the separator derivation
        // below, so this pass cannot introduce a cross-occurrence rewrite.
        foreach ($fragments as $index => $fragment) {
            $start = $fragmentCompactOffsets[$index];
            $length = $this->length(preg_replace('/\s+/u', '', $fragment) ?? $fragment);
            $fragmentSeparators = [];
            foreach ($boundarySeparators as $offset => $separator) {
                if ($offset > $start && $offset < $start + $length) {
                    $fragmentSeparators[$offset - $start] = $separator;
                }
            }
            if ($fragmentSeparators !== []) {
                $fragments[$index] = $this->sourcePdfLineTextWithProvenPositionedSeparators(
                    $fragment,
                    $fragment,
                    $fragmentSeparators
                );
            }
        }

        // A positioned producer can expose ordinary whitespace imprecisely at
        // a style or superscript boundary. Normalize only those offsets for
        // which its own extractor provenance is explicit, then use the
        // remaining visual whitespace solely to choose separators between the
        // already-verified source fragments.
        if ($boundarySeparators !== []) {
            $target = $this->sourcePdfLineTextWithProvenPositionedSeparators(
                $target,
                $target,
                $boundarySeparators
            );
        }
        if ($protectedDelimiterOffsets !== []) {
            // The visual text may contain a normal word gap immediately
            // after a closing delimiter even though the source item records
            // one structural notation run. Normalize that one local span
            // before matching, rather than replacing the whole line.
            $target = $this->sourcePdfLineTextWithProvenPositionedSeparators(
                $target,
                $target,
                array_fill_keys(array_keys($protectedDelimiterOffsets), '')
            );
        }

        // Preserve whitespace already present inside a source fragment, but
        // permit the positioned extractor to omit it. Captured groups cover
        // only inter-fragment boundaries: a non-whitespace character there
        // means this is not the same source composition.
        $pattern = '/^';
        $separatorIndexes = [];
        foreach ($fragments as $index => $fragment) {
            if ($separatorIndexes !== []) {
                $pattern .= '(\s*)';
                $separatorIndexes[] = $index;
            } else {
                $separatorIndexes[] = $index;
            }
            $pattern .= str_replace(' ', '\s*', preg_quote($fragment, '/'));
        }
        $pattern .= '$/u';
        if (preg_match($pattern, $target, $matches) !== 1) {
            return null;
        }

        $separators = [];
        $capture = 1;
        foreach (array_slice($separatorIndexes, 1) as $index) {
            $separators[$index] = ($matches[$capture] ?? '') !== '';
            $capture++;
        }

        // At a protected source-fragment seam retain the source's local
        // structural separator rule. This covers a formula atom emitted as a
        // separate styled fragment without changing similarly spelled prose
        // elsewhere in the document.
        $previousIndex = null;
        foreach ($fragments as $index => $fragment) {
            if ($previousIndex !== null
                && isset($protectedDelimiterOffsets[$fragmentCompactOffsets[$index] ?? -1])) {
                $separators[$index] = $this->sourcePdfJoinedFragmentsNeedSpace(
                    $fragments[$previousIndex],
                    $fragment
                );
            }
            $previousIndex = $index;
        }

        $compactIdentifierBoundaryBefore = [];
        $previousIndex = null;
        foreach ($fragments as $index => $fragment) {
            if ($previousIndex !== null
                && ($separators[$index] ?? false) === false
                && $this->sourcePdfFragmentBoundaryIsCompactIdentifier(
                    (string) ($sourceItems[$previousIndex]['text'] ?? ''),
                    (string) ($sourceItems[$index]['text'] ?? ''),
                    (int) ($fragmentCompactOffsets[$index] ?? -1),
                    $positionedBoundarySeparators
                )) {
                $compactIdentifierBoundaryBefore[$index] = true;
            }
            $previousIndex = $index;
        }

        return [
            'fragments' => $fragments,
            'separators' => $separators,
            'compactIdentifierBoundaryBefore' => $compactIdentifierBoundaryBefore,
        ];
    }

    /**
     * A later prose pass has to split ambiguous `word42` text, but it must
     * not undo a compact identifier whose exact source fragment and matching
     * positioned boundary both establish the join. Limit that protection to
     * a punctuation-leading identifier fragment with no preceding source
     * word in that record: a detached or inline `R` + `42` remains ambiguous
     * and is intentionally left to the prose pass.
     *
     * @param array<int, string> $positionedBoundarySeparators
     */
    private function sourcePdfFragmentBoundaryIsCompactIdentifier(
        string $previousSourceText,
        string $nextSourceText,
        int $compactOffset,
        array $positionedBoundarySeparators
    ): bool {
        if ($compactOffset <= 0
            || ($positionedBoundarySeparators[$compactOffset] ?? null) !== '') {
            return false;
        }

        $previous = rtrim($this->pdfLayoutTextPreservingEdgeWhitespace($previousSourceText));
        $next = ltrim($this->pdfLayoutTextPreservingEdgeWhitespace($nextSourceText));
        if (preg_match('/\p{L}$/u', $previous) !== 1
            || preg_match('/^\d{2,}/u', $next) !== 1) {
            return false;
        }

        return preg_match('/^\s*[^\p{L}\p{N}]+\p{L}+\s*$/u', $previous) === 1;
    }

    /**
     * Preserve spaces while comparing a source fragment sequence with one
     * positioned visual line. Unlike the ordinary comparable-text key, this
     * intentionally distinguishes "New York" from "NewYork" so a direct
     * character match can prove that a source-stream boundary is intra-word.
     */
    private function pdfExactLayoutText(string $text): string
    {
        return trim($this->pdfLayoutTextPreservingEdgeWhitespace($text));
    }

    /**
     * Keep literal edge whitespace while normalizing a source fragment. This
     * is used only to identify attached delimiter notation before the normal
     * compact comparison removes whitespace.
     */
    private function pdfLayoutTextPreservingEdgeWhitespace(string $text): string
    {
        $text = $this->normalizePdfTextEncoding($text);
        $text = str_replace(["\u{00AD}", "\u{2010}", "\u{2011}"], '-', $text);
        $text = str_replace("\u{00A0}", ' ', $text);

        return preg_replace('/\s+/u', ' ', $text) ?? $text;
    }

    /**
     * Find compact-character boundaries that belong to an attached
     * parenthesized, bracketed, or braced source notation run. Text inside a
     * normal parenthetical is deliberately excluded unless the opening
     * delimiter directly follows an identifier or number in the source.
     *
     * @return array<int, true> Compact-character offset => true.
     */
    private function sourcePdfDelimitedNotationSeparatorOffsets(string $sourceLayout): array
    {
        $characters = preg_split('//u', $sourceLayout, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($characters) || $characters === []) {
            return [];
        }

        $compactOffsets = [];
        $compactLength = 0;
        foreach ($characters as $index => $character) {
            if (preg_match('/^\s$/u', $character) === 1) {
                continue;
            }
            $compactOffsets[$index] = $compactLength;
            $compactLength++;
        }

        $opens = ['(' => ')', '[' => ']', '{' => '}'];
        $stack = [];
        $offsets = [];
        foreach ($characters as $index => $character) {
            if (isset($opens[$character])) {
                $previous = $characters[$index - 1] ?? null;
                if (!is_string($previous)
                    || !isset($compactOffsets[$index - 1])
                    || preg_match('/^[\p{L}\p{N}]$/u', $previous) !== 1) {
                    continue;
                }
                $stack[] = [
                    'closing' => $opens[$character],
                    'start' => $compactOffsets[$index - 1],
                ];
                continue;
            }
            if ($stack === [] || !isset($compactOffsets[$index])) {
                continue;
            }

            $lastIndex = array_key_last($stack);
            $opening = $stack[$lastIndex];
            if ($character !== $opening['closing']) {
                continue;
            }
            array_pop($stack);
            $closingOffset = $compactOffsets[$index];
            for ($offset = $opening['start'] + 1; $offset <= $closingOffset; $offset++) {
                $offsets[$offset] = true;
            }

            $next = $characters[$index + 1] ?? null;
            if (is_string($next)
                && isset($compactOffsets[$index + 1])
                && preg_match('/^[\p{L}\p{N}]$/u', $next) === 1) {
                $offsets[$closingOffset + 1] = true;
            }
        }

        return $offsets;
    }

    /**
     * Recover an inter-fragment separator from syntax only when a local
     * delimiter run explicitly withheld it from geometry. This is the former
     * source-stream fallback, deliberately free of vocabulary or casing
     * rules.
     */
    private function sourcePdfJoinedFragmentsNeedSpace(string $previous, string $next): bool
    {
        $previous = rtrim($previous);
        $next = ltrim($next);
        if ($this->lineLooksLikeUrlOnly($previous)
            && !preg_match('/[\/-]$/u', $previous)
            && $this->lineLooksLikeBareDomain($next)) {
            return true;
        }
        if (preg_match('/[-\x{2010}-\x{2015}\/(\[{]\s*$/u', $previous) === 1
            || preg_match('/^\s*[\)\]\},.!?;:]/u', $next) === 1) {
            return false;
        }
        if (preg_match('/^[\p{M}\p{Sk}]+$/u', $next) === 1
            || preg_match('/[\p{M}\p{Sk}]$/u', $previous) === 1) {
            return false;
        }
        if (preg_match('/^\p{N}{1,3}$/u', $previous) === 1 && preg_match('/^\p{Ll}/u', $next) === 1) {
            return true;
        }
        if (preg_match('/[\p{L}]$/u', $previous) === 1 && preg_match('/^\p{N}/u', $next) === 1) {
            return false;
        }
        if (preg_match('/\p{N}$/u', $previous) === 1 && preg_match('/^\p{L}/u', $next) === 1) {
            return false;
        }

        return true;
    }

    private function lineLooksLikeBareDomain(string $text): bool
    {
        return preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9-]*\.)+[A-Za-z]{2,}(?:[\/?#][^\s]*)?$/u', trim($text)) === 1;
    }

    /**
     * The normal text layer is preferred for spacing, but a substantial
     * positioned caption can be the only readable representation of a visual
     * text run. Preserve only complete-looking lines, never short labels or
     * glyph fragments from diagrams.
     *
     * @param array<string, mixed> $item
     */
    private function positionedPdfLineCanSupplementSource(array $item): bool
    {
        if (($item['code'] ?? false) === true || $this->lineIsOnlyPdfNoise((string) ($item['text'] ?? ''))) {
            return false;
        }
        $text = trim((string) ($item['text'] ?? ''));

        return $this->length($text) >= 28 && count($this->pdfLineWordTokens($text)) >= 5;
    }

    /**
     * A formula or diagram can be painted immediately before a normal body
     * sentence on the same positioned line. The source text layer often
     * exposes only the damaged prefix. Keep the visual line only when its
     * punctuation provides an independently recoverable sentence suffix.
     *
     * @param array<string, mixed> $item
     */
    private function positionedPdfLineHasRecoverableSentenceSuffix(array $item): bool
    {
        if (!$this->pdfLayoutHasGeometry($item) || ($item['code'] ?? false) === true) {
            return false;
        }

        $text = ltrim((string) ($item['text'] ?? ''));
        if ($text === ''
            || $this->lineIsOnlyPdfNoise($text)
            || preg_match('/^[^\p{L}\p{N}]/u', $text) !== 1
            || preg_match('/[.!?]\s*((?:(?:[\(\[]\s*)?(?:\p{N}+\s*[\)\]]\s*)?)?\p{Lu}[\s\S]*)$/u', $text, $matches) !== 1) {
            return false;
        }

        return count($this->pdfLineWordTokens($matches[1])) >= 5;
    }

    /**
     * A source text layer can omit a colored, italic, or subset-font fragment
     * from an otherwise ordinary body line. Accept a short positioned fallback
     * only inside an already-established parallel body flow. The shared font,
     * baseline band, and horizontal span keep diagram labels and detached
     * figure text out of this path.
     *
     * @param array<string, mixed> $item
     * @param list<array<string, mixed>> $matchedItems
     */
    private function positionedPdfShortLineCanSupplementMatchedBodyFlow(array $item, array $matchedItems): bool
    {
        if (!$this->pdfLayoutHasGeometry($item)
            || ($item['code'] ?? false) === true) {
            return false;
        }

        $text = trim((string) ($item['text'] ?? ''));
        $wordCount = count($this->pdfLineWordTokens($text));
        $terminal = preg_match('/[.!?;:]\s*$/u', $text) === 1;
        if ($text === ''
            || $this->lineIsOnlyPdfNoise($text)
            || ($this->lineLooksLikePdfDiagramLabel($text) && $this->length($text) < 10)
            || $this->length($text) < 4
            || ($wordCount === 0)) {
            return false;
        }

        foreach ($matchedItems as $neighbor) {
            if (!$this->pdfLayoutHasGeometry($neighbor)
                || ($neighbor['page'] ?? null) !== ($item['page'] ?? null)
                || ($neighbor['code'] ?? false) === true) {
                continue;
            }

            $fontSize = max(1.0, (float) $item['fontSize'], (float) $neighbor['fontSize']);
            if (abs((float) $item['fontSize'] - (float) $neighbor['fontSize']) > max(1.5, $fontSize * 0.22)) {
                continue;
            }

            $verticalDistance = abs((float) $item['y1'] - (float) $neighbor['y1']);
            $horizontalTolerance = max(16.0, $fontSize * 2.0);
            $withinNeighborSpan = (float) $item['x1'] >= (float) $neighbor['x1'] - $horizontalTolerance
                && (float) $item['x1'] <= (float) $neighbor['x2'] + $horizontalTolerance;
            if (!$withinNeighborSpan || $verticalDistance > max(18.0, $fontSize * 2.2)) {
                continue;
            }

            if ($wordCount > 1 || $terminal || $this->length($text) >= 10) {
                return true;
            }

            $adjacentOnBaseline = $verticalDistance <= max(1.5, $fontSize * 0.35)
                && (float) $item['x1'] >= (float) $neighbor['x2'] - $horizontalTolerance
                && (float) $item['x1'] - (float) $neighbor['x2'] <= $horizontalTolerance;
            if ($adjacentOnBaseline) {
                return true;
            }
        }

        return false;
    }

    /**
     * Some PDFs omit an entire source-text run, so no exact source neighbor is
     * available to corroborate it. In a verified parallel body-column layout,
     * nearby positioned text with the same font is sufficient geometric proof
     * of the flow. The wide column-span allowance covers a line split into two
     * positioned fragments on the same baseline.
     *
     * @param array<string, mixed> $item
     * @param list<array{x: float, width: float, fontSize: float, count: int}> $columns
     * @param list<array<string, mixed>> $positionedItems
     */
    private function positionedPdfShortLineCanSupplementStableBodyColumn(
        array $item,
        array $columns,
        array $positionedItems
    ): bool {
        if (!$this->pdfLayoutHasGeometry($item) || ($item['code'] ?? false) === true) {
            return false;
        }

        $text = trim((string) ($item['text'] ?? ''));
        if ($text === ''
            || $this->lineIsOnlyPdfNoise($text)
            || ($this->lineLooksLikePdfDiagramLabel($text) && $this->length($text) < 10)
            || $this->length($text) < 4
            || count($this->pdfLineWordTokens($text)) === 0) {
            return false;
        }

        $column = null;
        foreach ($columns as $candidate) {
            $fontSize = max(1.0, (float) $item['fontSize'], $candidate['fontSize']);
            if (abs((float) $item['fontSize'] - $candidate['fontSize']) > max(1.5, $fontSize * 0.22)) {
                continue;
            }
            $leftTolerance = max(16.0, $fontSize * 2.0);
            if ((float) $item['x1'] < $candidate['x'] - $leftTolerance
                || (float) $item['x1'] > $candidate['x'] + $candidate['width'] * 0.85) {
                continue;
            }
            $column = $candidate;
            break;
        }
        if ($column === null) {
            return false;
        }

        foreach ($positionedItems as $neighbor) {
            if ($neighbor === $item
                || !$this->pdfLayoutHasGeometry($neighbor)
                || ($neighbor['page'] ?? null) !== ($item['page'] ?? null)
                || ($neighbor['code'] ?? false) === true) {
                continue;
            }

            $fontSize = max(1.0, (float) $item['fontSize'], (float) $neighbor['fontSize'], $column['fontSize']);
            if (abs((float) $item['fontSize'] - (float) $neighbor['fontSize']) > max(1.5, $fontSize * 0.22)
                || abs((float) $item['y1'] - (float) $neighbor['y1']) > max(24.0, $fontSize * 2.5)) {
                continue;
            }
            if ((float) $neighbor['x1'] < $column['x'] - max(16.0, $fontSize * 2.0)
                || (float) $neighbor['x1'] > $column['x'] + $column['width'] * 0.85) {
                continue;
            }
            if ($this->lineIsOnlyPdfNoise((string) ($neighbor['text'] ?? ''))
                || count($this->pdfLineWordTokens((string) ($neighbor['text'] ?? ''))) === 0) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * Positioning can sometimes join text from adjacent regions onto one
     * baseline. Record substantial source overlap now; later geometry decides
     * whether the positioned line has a credible visual place to supplement.
     *
     * @param array<string, mixed> $positionedItem
     * @param list<array{page: int, stream: int, text: string}> $sourceItems
     */
    private function positionedPdfLineSubstantiallyOverlapsSource(array $positionedItem, array $sourceItems): bool
    {
        $positionedText = $this->pdfComparableLineText((string) ($positionedItem['text'] ?? ''));
        $positionedLength = $this->length($positionedText);
        if ($positionedLength < 16) {
            return false;
        }

        foreach ($sourceItems as $sourceItem) {
            $sourceText = $this->pdfComparableLineText($sourceItem['text']);
            $sourceLength = $this->length($sourceText);
            if ($sourceLength < 16) {
                continue;
            }
            if (!str_contains($positionedText, $sourceText) && !str_contains($sourceText, $positionedText)) {
                continue;
            }

            if ($this->lineLooksLikeUrlOnly((string) ($positionedItem['text'] ?? ''))
                && $positionedLength >= 20
                && str_contains($sourceText, $positionedText)) {
                return true;
            }

            if (min($positionedLength, $sourceLength) / max($positionedLength, $sourceLength) >= 0.72) {
                return true;
            }
        }

        foreach ($sourceItems as $sourceItem) {
            if ($this->sourcePdfComparableTextIsCoveredBySupplementalPositioning(
                (string) ($sourceItem['text'] ?? ''),
                (string) ($positionedItem['text'] ?? '')
            )) {
                return true;
            }
        }

        return false;
    }

    /**
     * Geometry-confirmed lines are emitted in visual order. For a missing
     * positioned line, keep an immediately adjacent hyphenated continuation
     * with its known neighbor rather than appending it to the end of the page.
     * This preserves wrapped words and URLs while leaving uncertain fragments
     * isolated instead of guessing where they belong.
     *
     * @param list<array{page: int, stream: int, text: string}> $sourceItems
     * @param array{items: list<array<string, mixed>>, sourceIndexes: array<int, true>, itemsBySourceIndex: array<int, array<string, mixed>>, visualEntries: list<array{item: array<string, mixed>, sourceIndex: int|null}>} $match
     * @return list<array<string, mixed>>
     */
    private function sourcePdfItemsInVisualOrder(
        array $sourceItems,
        array $match,
        bool $includeUnmatchedPositionedSupplements = true
    ): array
    {
        $tableSourceGroups = $this->sourcePdfTableSourceGroups($sourceItems);
        $before = [];
        $after = [];
        $fallback = [];
        foreach ($sourceItems as $index => $sourceItem) {
            if (isset($match['sourceIndexes'][$index])) {
                continue;
            }

            $nextIndex = $index + 1;
            $shortCommaLead = isset($sourceItems[$nextIndex], $match['itemsBySourceIndex'][$nextIndex])
                && $this->sourcePdfItemsShareStream($sourceItem, $sourceItems[$nextIndex])
                && $this->sourcePdfShortCommaLeadPrecedesKnownLayout(
                    $sourceItem['text'],
                    $sourceItems[$nextIndex]['text']
                );
            if (isset($sourceItems[$nextIndex], $match['itemsBySourceIndex'][$nextIndex])
                && $this->sourcePdfItemsShareStream($sourceItem, $sourceItems[$nextIndex])
                && ($this->sourcePdfLinesAreWrappedContinuation($sourceItem['text'], $sourceItems[$nextIndex]['text'])
                    || $shortCommaLead)) {
                $item = $this->sourcePdfLineItemBeforeKnownLayout(
                    $sourceItem,
                    $match['itemsBySourceIndex'][$nextIndex]
                );
                if ($shortCommaLead) {
                    $item['sourceShortCommaLead'] = true;
                }
                $before[$nextIndex][] = $this->sourcePdfAttachSourceItemProvenance($item, $index, $tableSourceGroups);
                continue;
            }

            $previousIndex = $index - 1;
            if (isset($sourceItems[$previousIndex], $match['itemsBySourceIndex'][$previousIndex])
                && $this->sourcePdfItemsShareStream($sourceItems[$previousIndex], $sourceItem)
                && $this->sourcePdfLinesAreWrappedContinuation($sourceItems[$previousIndex]['text'], $sourceItem['text'])) {
                $item = $this->sourcePdfLineItemAfterKnownLayout(
                    $sourceItem,
                    $match['itemsBySourceIndex'][$previousIndex]
                );
                $after[$previousIndex][] = $this->sourcePdfAttachSourceItemProvenance($item, $index, $tableSourceGroups);
                continue;
            }

            if (!isset($tableSourceGroups[$index]) && $this->sourcePdfUnmatchedLineLooksLikeFloatingFragment($sourceItem['text'])) {
                continue;
            }
            $fallbackItem = $this->sourcePdfLineItem($sourceItem, null, true);
            // These source-only entries have no trustworthy visual location.
            // Keep them available for prose, but let the later coherence pass
            // discard a sustained cluster of unplaceable diagram labels.
            $fallbackItem['sourceUnmatchedFallback'] = true;
            $fallback[] = $this->sourcePdfAttachSourceItemProvenance($fallbackItem, $index, $tableSourceGroups);
        }

        $ordered = [];
        foreach ($match['visualEntries'] as $entry) {
            if (!$includeUnmatchedPositionedSupplements
                && $entry['sourceIndex'] === null
                && (($entry['item']['sourceSupplementalSourceOverlap'] ?? false) !== true)
                && (($entry['item']['sourceSupplementalRecoverableSentenceSuffix'] ?? false) !== true)) {
                continue;
            }
            $sourceIndex = $entry['sourceIndex'];
            if ($sourceIndex === null) {
                $ordered[] = $entry['item'];
                continue;
            }
            foreach ($before[$sourceIndex] ?? [] as $item) {
                $ordered[] = $item;
            }
            $ordered[] = $this->sourcePdfAttachSourceItemProvenance(
                $match['itemsBySourceIndex'][$sourceIndex],
                $sourceIndex,
                $tableSourceGroups
            );
            foreach ($after[$sourceIndex] ?? [] as $item) {
                $ordered[] = $item;
            }
        }

        [$ordered, $fallback] = $this->restoreSourcePdfPartialSupplementalLines($ordered, $fallback);
        if (!$includeUnmatchedPositionedSupplements) {
            $fallback = array_values(array_filter(
                $fallback,
                fn (array $item): bool => $this->sourcePdfUnmatchedFallbackCanStandAlone($item)
            ));
        }
        $ordered = $this->removeSourcePdfItemsCoveredBySupplementalPositioning($ordered);
        $ordered = $this->removeSourcePdfSupplementalRowDuplicates($ordered);
        $ordered = $this->orderSourcePdfItemsWithinStableColumns($ordered);
        $ordered = $this->removeSourcePdfInferredCrossColumnDuplicates($ordered);
        $ordered = $this->removeSourcePdfNearBaselinePrefixDuplicates($ordered);
        $ordered = $this->retainSourcePdfShortPositionedBodySupplements($ordered);
        $ordered = $this->removeSourcePdfNearBaselinePrefixDuplicates($ordered);
        $ordered = $this->removeSourcePdfSupplementalOverlaysThatInterruptSourceWrap($ordered);
        if ($this->sourcePdfGeometryOrderHasStableBodyColumns($ordered)) {
            $fallback = array_values(array_filter(
                $fallback,
                fn (array $item): bool => isset($item['sourcePdfTableGroup'])
                    || ($item['code'] ?? false) === true
                    || $this->lineLooksLikeUrlOnly((string) ($item['text'] ?? ''))
            ));
        }
        $fallback = $this->removeSourcePdfFallbackCoveredBySupplementalPositioning($fallback, $ordered);
        $complexGeometryPage = count(array_filter(
            $ordered,
            static fn (array $item): bool => ($item['sourceComplexGeometryPage'] ?? false) === true
        )) > 0;
        $detachedDiagramEvidencePage = $this->sourcePdfPageHasDetachedDiagramEvidence($ordered, $fallback);
        if ($complexGeometryPage) {
            foreach ($fallback as &$item) {
                $item['sourceComplexGeometryPage'] = true;
            }
            unset($item);
        }
        if ($detachedDiagramEvidencePage) {
            foreach ($ordered as &$item) {
                $item['sourceDetachedDiagramEvidencePage'] = true;
            }
            unset($item);
            foreach ($fallback as &$item) {
                $item['sourceDetachedDiagramEvidencePage'] = true;
            }
            unset($item);
        }

        $combined = $this->restoreSourcePdfAdjacentVisualContinuations(array_merge($ordered, $fallback), $sourceItems);
        $combined = $this->restoreSourcePdfTableItemSequences($combined, $sourceItems);
        $combined = $this->restoreSourcePdfStandaloneListMarkerPrefixes($combined, $sourceItems);

        $combined = $this->markSourcePdfOrphanedInferredContinuations($combined, $sourceItems);

        return $this->propagateSourcePdfUnresolvedInterruptedFlows($combined);
    }

    /**
     * A PDF text stream can emit a list marker as its own source record even
     * when the positioned row has lost that glyph. Source adjacency then
     * provides stronger list evidence than the reconstructed row alone.
     * Attach the marker only to its immediate same-stream successor and drop
     * the standalone duplicate, preserving ordinary nearby prose unchanged.
     *
     * @param list<array<string, mixed>> $items
     * @param list<array{page: int, stream: int, text: string}> $sourceItems
     * @return list<array<string, mixed>>
     */
    private function restoreSourcePdfStandaloneListMarkerPrefixes(array $items, array $sourceItems): array
    {
        $markerSourceIndexesBySuccessor = [];
        foreach ($sourceItems as $sourceIndex => $sourceItem) {
            $marker = trim($sourceItem['text']);
            if (!$this->lineIsStandalonePdfListMarker($marker)) {
                continue;
            }

            $successorIndex = $sourceIndex + 1;
            if (!isset($sourceItems[$successorIndex])
                || $sourceItems[$successorIndex]['page'] !== $sourceItem['page']
                || $sourceItems[$successorIndex]['stream'] !== $sourceItem['stream']
                || trim($sourceItems[$successorIndex]['text']) === '') {
                continue;
            }

            $markerSourceIndexesBySuccessor[$successorIndex][] = [
                'sourceIndex' => $sourceIndex,
                'marker' => $marker,
            ];
        }
        if ($markerSourceIndexesBySuccessor === []) {
            return $items;
        }

        $prefixedMarkerSourceIndexes = [];
        foreach ($items as &$item) {
            if (isset($item['sourcePdfTableGroup'])) {
                continue;
            }
            $sourceStart = $item['sourcePdfSourceIndex'] ?? null;
            if (!is_int($sourceStart)) {
                continue;
            }
            $sourceEnd = (int) ($item['sourcePdfSourceIndexEnd'] ?? $sourceStart);
            foreach ($markerSourceIndexesBySuccessor as $successorIndex => $markers) {
                if ($successorIndex < $sourceStart || $successorIndex > $sourceEnd) {
                    continue;
                }
                $text = ltrim((string) ($item['text'] ?? ''));
                if ($text === '' || $this->listItem($text) !== null) {
                    continue;
                }
                foreach ($markers as $markerEntry) {
                    $text = $markerEntry['marker'] . ' ' . $text;
                    $prefixedMarkerSourceIndexes[(int) $markerEntry['sourceIndex']] = true;
                }
                $item['text'] = $text;
                $item['sourceStandaloneListMarkerPrefix'] = true;
                $item['forceBlockBreakBefore'] = true;
            }
        }
        unset($item);
        if ($prefixedMarkerSourceIndexes === []) {
            return $items;
        }

        return array_values(array_filter(
            $items,
            static function (array $item) use ($prefixedMarkerSourceIndexes): bool {
                $sourceIndex = $item['sourcePdfSourceIndex'] ?? null;

                return !is_int($sourceIndex) || !isset($prefixedMarkerSourceIndexes[$sourceIndex]);
            }
        ));
    }

    /**
     * A positioned text layer can omit a trailing styled run while the source
     * layer retains the full line. Restore that source text only when the
     * positioned fragment is a substantial prefix or suffix and its adjacent
     * source line proves the same visual baseline. This avoids treating a
     * similarly worded line elsewhere on a page as a replacement.
     *
     * @param list<array<string, mixed>> $ordered
     * @param list<array<string, mixed>> $fallback
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function restoreSourcePdfPartialSupplementalLines(array $ordered, array $fallback): array
    {
        $consumedFallback = [];
        foreach ($ordered as $orderedIndex => &$positioned) {
            if (($positioned['sourceSupplementalPositioned'] ?? false) !== true
                || !$this->pdfLayoutHasGeometry($positioned)
                || isset($positioned['sourcePdfTableGroup'])) {
                continue;
            }

            $bestFallbackIndex = null;
            $bestCoverage = 0.0;
            foreach ($fallback as $fallbackIndex => $source) {
                if (isset($consumedFallback[$fallbackIndex])
                    || !isset($source['sourcePdfSourceIndex'], $source['sourceStream'])
                    || isset($source['sourcePdfTableGroup'])
                    || ($source['page'] ?? null) !== ($positioned['page'] ?? null)
                    || (isset($positioned['sourceStream'])
                        && ($source['sourceStream'] ?? null) !== ($positioned['sourceStream'] ?? null))) {
                    continue;
                }

                $coverage = $this->sourcePdfTextCoverageForPartialSupplemental(
                    (string) ($source['text'] ?? ''),
                    (string) ($positioned['text'] ?? '')
                );
                if ($coverage <= $bestCoverage
                    || !$this->sourcePdfFallbackNeighborsSupplementalLayout($source, $positioned, $ordered)) {
                    continue;
                }

                $bestFallbackIndex = $fallbackIndex;
                $bestCoverage = $coverage;
            }

            if ($bestFallbackIndex === null) {
                continue;
            }

            $source = $fallback[$bestFallbackIndex];
            $positioned['text'] = (string) $source['text'];
            $positioned['sourceStream'] = $source['sourceStream'];
            $positioned['sourcePdfSourceIndex'] = $source['sourcePdfSourceIndex'];
            if (isset($source['sourcePdfSourceIndexEnd'])) {
                $positioned['sourcePdfSourceIndexEnd'] = $source['sourcePdfSourceIndexEnd'];
            }
            unset(
                $positioned['sourceSupplementalPositioned'],
                $positioned['sourceSupplementalBodyFlow'],
                $positioned['sourceSupplementalSourceOverlap'],
                $positioned['sourceSupplementalUrlOverlap'],
                $positioned['sourceShortSupplementalCandidate']
            );
            $positioned['sourceRecoveredPartialSupplemental'] = true;
            $ordered[$orderedIndex] = $positioned;
            $consumedFallback[$bestFallbackIndex] = true;
        }
        unset($positioned);

        if ($consumedFallback === []) {
            return [$ordered, $fallback];
        }

        return [
            $ordered,
            array_values(array_filter(
                $fallback,
                static fn (array $_source, int $index): bool => !isset($consumedFallback[$index]),
                ARRAY_FILTER_USE_BOTH
            )),
        ];
    }

    private function sourcePdfTextCoverageForPartialSupplemental(string $sourceText, string $positionedText): float
    {
        $source = $this->pdfComparableLineText($sourceText);
        $positioned = $this->pdfComparableLineText($positionedText);
        $sourceLength = $this->length($source);
        $positionedLength = $this->length($positioned);
        if ($sourceLength < 18
            || $positionedLength < 18
            || $sourceLength < $positionedLength
            || (!str_starts_with($source, $positioned) && !str_ends_with($source, $positioned))) {
            return 0.0;
        }

        $coverage = $positionedLength / $sourceLength;

        return $coverage >= 0.55 ? $coverage : 0.0;
    }

    /**
     * @param array<string, mixed> $source
     * @param array<string, mixed> $positioned
     * @param list<array<string, mixed>> $ordered
     */
    private function sourcePdfFallbackNeighborsSupplementalLayout(array $source, array $positioned, array $ordered): bool
    {
        $sourceIndex = (int) $source['sourcePdfSourceIndex'];
        foreach ($ordered as $neighbor) {
            if (($neighbor['sourceSupplementalPositioned'] ?? false) === true
                || !$this->pdfLayoutHasGeometry($neighbor)
                || ($neighbor['page'] ?? null) !== ($source['page'] ?? null)
                || ($neighbor['sourceStream'] ?? null) !== ($source['sourceStream'] ?? null)
                || !isset($neighbor['sourcePdfSourceIndex'])) {
                continue;
            }

            $neighborStart = (int) $neighbor['sourcePdfSourceIndex'];
            $neighborEnd = (int) ($neighbor['sourcePdfSourceIndexEnd'] ?? $neighborStart);
            $sourcePrecedesNeighbor = $neighborStart === $sourceIndex + 1;
            $sourceFollowsNeighbor = $neighborEnd === $sourceIndex - 1;
            if (!$sourcePrecedesNeighbor && !$sourceFollowsNeighbor) {
                continue;
            }

            $fontSize = max(1.0, (float) $positioned['fontSize'], (float) $neighbor['fontSize']);
            if (abs((float) $positioned['fontSize'] - (float) $neighbor['fontSize']) > max(1.5, $fontSize * 0.25)
                || abs((float) $positioned['x1'] - (float) $neighbor['x1']) > max(16.0, $fontSize * 1.5)) {
                continue;
            }

            $verticalStep = $sourcePrecedesNeighbor
                ? (float) $positioned['y1'] - (float) $neighbor['y1']
                : (float) $neighbor['y1'] - (float) $positioned['y1'];
            if ($verticalStep >= $fontSize * 0.30 && $verticalStep <= $fontSize * 2.80) {
                return true;
            }
        }

        return false;
    }

    /**
     * A short source-only continuation can be assigned a neighboring line's
     * geometry. When the source sequence before it has a substantial missing
     * span, that inferred placement cannot establish a complete prose flow.
     * Mark only this narrow shape and nearby positioned overlays in the same
     * lane; normal matched lines, captions, tables, and code remain intact.
     *
     * @param list<array<string, mixed>> $items
     * @param list<array{page: int, stream: int, text: string}> $sourceItems
     * @return list<array<string, mixed>>
     */
    private function markSourcePdfOrphanedInferredContinuations(array $items, array $sourceItems): array
    {
        $previousByFlow = [];
        $pendingPunctuationOrphans = [];
        $markedBounds = [];
        foreach ($items as $index => &$item) {
            if (!$this->pdfLayoutHasGeometry($item)
                || ($item['sourceStructuredGeometry'] ?? false) !== true
                || isset($item['sourcePdfTableGroup'])
                || ($item['code'] ?? false) === true
                || !isset($item['sourcePdfSourceIndex'], $item['sourceStream'], $item['sourceGeometryColumn'])) {
                continue;
            }

            $flow = implode(':', [
                (string) ($item['page'] ?? 0),
                (string) $item['sourceStream'],
                (string) $item['sourceGeometryColumn'],
            ]);
            $previousIndex = $previousByFlow[$flow] ?? null;
            $text = ltrim((string) ($item['text'] ?? ''));
            $startsContinuation = preg_match('/^(?:[^\p{L}\p{N}]*\p{Ll}|[,.;:\)\]\}])/u', $text) === 1;
            $short = count($this->pdfLineWordTokens($text)) <= 4;
            $inferred = ($item['sourceInferredNeighborLayout'] ?? false) === true;
            $orphaned = false;
            $inheritsPunctuationOrphan = false;
            if ($previousIndex !== null) {
                $previous = $items[$previousIndex];
                $inlineInferredFragment = $inferred
                    && ($previous['page'] ?? null) === ($item['page'] ?? null)
                    && ($previous['sourceGeometryColumn'] ?? null) === ($item['sourceGeometryColumn'] ?? null)
                    && abs((float) $previous['y1'] - (float) $item['y1'])
                        <= max(2.0, max((float) $previous['fontSize'], (float) $item['fontSize'], 1.0) * 0.45);
                if ($inlineInferredFragment) {
                    // A short source-only formula fragment can borrow a
                    // neighbor's line box while the positioned text already
                    // carries the complete visual line. It is not a broken
                    // column flow and must not trim the verified prose above.
                    $item['sourceInlineInferredFragment'] = true;
                    continue;
                }
                $previousEnd = (int) ($previous['sourcePdfSourceIndexEnd'] ?? $previous['sourcePdfSourceIndex']);
                $sourceIndex = (int) $item['sourcePdfSourceIndex'];
                $skippedSource = $sourceIndex > $previousEnd + 1
                    && ($previous['sourceSupplementalPositioned'] ?? false) !== true
                    && $this->sourcePdfSkippedSequenceContainsSubstantialText(
                        $sourceItems,
                        $previousEnd + 1,
                        $sourceIndex - 1,
                        (int) ($item['page'] ?? 0),
                        (int) $item['sourceStream']
                    );
                $continuesMarkedFlow = ($previous['sourceOrphanedInferredContinuation'] ?? false) === true
                    && $this->pdfComparableLineText((string) ($previous['text'] ?? '')) !== ''
                    && $startsContinuation
                    && $this->repairedPdfLayoutContinuesWrappedLine($previous, $item);
                $inheritsPunctuationOrphan = ($pendingPunctuationOrphans[$flow] ?? false) === true
                    && $startsContinuation
                    && $this->repairedPdfLayoutContinuesWrappedLine($previous, $item);
                $orphaned = ($inferred && $startsContinuation && $short && $skippedSource)
                    || ($inferred && $startsContinuation && $short && $continuesMarkedFlow)
                    || $inheritsPunctuationOrphan;
            }
            unset($pendingPunctuationOrphans[$flow]);

            if ($orphaned) {
                $item['sourceOrphanedInferredContinuation'] = true;
                $item['sourceOrphanedMissingSourceText'] = $skippedSource || $inheritsPunctuationOrphan;
                $item['sourceInterruptedColumnRegion'] = true;
                $item['forceBlockBreakBefore'] = true;
                if ($this->pdfComparableLineText((string) ($item['text'] ?? '')) === '') {
                    $pendingPunctuationOrphans[$flow] = true;
                }
                $bounds = $markedBounds[$flow] ?? [
                    'minY' => (float) $item['y1'],
                    'maxY' => (float) $item['y1'],
                    'fontSize' => max(1.0, (float) $item['fontSize']),
                ];
                $bounds['minY'] = min($bounds['minY'], (float) $item['y1']);
                $bounds['maxY'] = max($bounds['maxY'], (float) $item['y1']);
                $bounds['fontSize'] = max($bounds['fontSize'], (float) $item['fontSize']);
                $markedBounds[$flow] = $bounds;
            }
            $previousByFlow[$flow] = $index;
        }
        unset($item);

        if ($markedBounds === []) {
            return $items;
        }

        foreach ($items as &$item) {
            if (($item['sourceSupplementalPositioned'] ?? false) !== true
                || isset($item['sourcePdfTableGroup'])
                || ($item['code'] ?? false) === true
                || !$this->pdfLayoutHasGeometry($item)
                || !isset($item['sourceGeometryColumn'])) {
                continue;
            }
            $page = (string) ($item['page'] ?? 0);
            foreach ($markedBounds as $flow => $bounds) {
                [$flowPage, $_stream, $column] = explode(':', $flow, 3);
                if ($flowPage !== $page || (int) $column !== (int) $item['sourceGeometryColumn']) {
                    continue;
                }
                $tolerance = max(18.0, $bounds['fontSize'] * 2.0);
                if ((float) $item['y1'] >= $bounds['minY'] - $tolerance
                    && (float) $item['y1'] <= $bounds['maxY'] + $tolerance) {
                    $item['sourceOrphanedInferredContinuation'] = true;
                    $item['sourceInterruptedColumnRegion'] = true;
                    $item['forceBlockBreakBefore'] = true;
                    break;
                }
            }
        }
        unset($item);

        return $items;
    }

    /**
     * @param list<array{page: int, stream: int, text: string}> $sourceItems
     */
    private function sourcePdfSkippedSequenceContainsSubstantialText(
        array $sourceItems,
        int $start,
        int $end,
        int $page,
        int $stream
    ): bool {
        $characters = 0;
        $words = 0;
        for ($index = $start; $index <= $end; $index++) {
            $source = $sourceItems[$index] ?? null;
            if ($source === null
                || (int) ($source['page'] ?? 0) !== $page
                || (int) ($source['stream'] ?? 0) !== $stream) {
                continue;
            }
            $text = trim((string) ($source['text'] ?? ''));
            $characters += $this->length($this->pdfComparableLineText($text));
            $words += count($this->pdfLineWordTokens($text));
        }

        return $characters >= 12 || $words >= 3;
    }

    /**
     * A figure or state-machine diagram can begin with an inferred punctuation
     * fragment and then continue with compact assignment labels. That pattern
     * proves a damaged display flow, not ordinary prose. Once both pieces of
     * evidence occur in one visual lane, mark the bounded flow through its
     * next forced block boundary. A nearby unfinished predecessor is marked
     * too, preventing it from becoming fabricated prose after cleanup.
     *
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function propagateSourcePdfUnresolvedInterruptedFlows(array $items): array
    {
        $candidateFlows = [];
        $activeFlows = [];
        $activeLanes = [];
        foreach ($items as $index => $item) {
            if (!$this->pdfLayoutHasGeometry($item)
                || ($item['sourceStructuredGeometry'] ?? false) !== true
                || isset($item['sourcePdfTableGroup'])
                || ($item['code'] ?? false) === true
                || !isset($item['sourceGeometryColumn'])) {
                continue;
            }

            $lane = implode(':', [
                (string) ($item['page'] ?? 0),
                (string) $item['sourceGeometryColumn'],
            ]);
            $hasSourceFlow = isset($item['sourceStream']);
            $text = ltrim((string) ($item['text'] ?? ''));
            $startsWithOrphanedPunctuation = ($item['sourceInterruptedColumnRegion'] ?? false) === true
                && ($item['sourceInferredNeighborLayout'] ?? false) === true
                && preg_match('/^[,.;:\)\]\}]/u', $text) === 1;
            if (isset($activeLanes[$lane])) {
                if ($hasSourceFlow
                    && !$startsWithOrphanedPunctuation
                    && ($item['forceBlockBreakBefore'] ?? false) === true) {
                    unset($activeLanes[$lane]);
                    foreach ($activeFlows as $activeFlow => $_active) {
                        if (str_starts_with($activeFlow, $lane . ':')) {
                            unset($activeFlows[$activeFlow]);
                        }
                    }
                } else {
                    $items[$index]['sourceUnresolvedInterruptedFlow'] = true;
                    $items[$index]['sourceInterruptedColumnRegion'] = true;
                    if (!$hasSourceFlow) {
                        continue;
                    }
                }
            }
            if (!$hasSourceFlow) {
                continue;
            }

            $flow = implode(':', [
                (string) ($item['page'] ?? 0),
                (string) $item['sourceStream'],
                (string) $item['sourceGeometryColumn'],
            ]);

            if (isset($activeFlows[$flow])) {
                if (!$startsWithOrphanedPunctuation && ($item['forceBlockBreakBefore'] ?? false) === true) {
                    unset($activeFlows[$flow]);
                    unset($activeLanes[$lane]);
                    continue;
                }
                $items[$index]['sourceUnresolvedInterruptedFlow'] = true;
                $items[$index]['sourceInterruptedColumnRegion'] = true;
                continue;
            }

            if ($startsWithOrphanedPunctuation) {
                $candidateFlows[$flow] = ['indexes' => [$index]];
            } elseif (isset($candidateFlows[$flow])) {
                if (($item['forceBlockBreakBefore'] ?? false) === true
                    && ($item['sourceOrphanedInferredContinuation'] ?? false) !== true) {
                    unset($candidateFlows[$flow]);
                    continue;
                }
                $candidateFlows[$flow]['indexes'][] = $index;
                if (count($candidateFlows[$flow]['indexes']) > 12) {
                    unset($candidateFlows[$flow]);
                    continue;
                }
            } else {
                continue;
            }

            if (!isset($candidateFlows[$flow]) || !$this->sourcePdfTextContainsCompactAssignmentLabel($text)) {
                continue;
            }

            $candidateIndexes = $candidateFlows[$flow]['indexes'];
            foreach ($candidateIndexes as $candidateIndex) {
                $items[$candidateIndex]['sourceUnresolvedInterruptedFlow'] = true;
                $items[$candidateIndex]['sourceInterruptedColumnRegion'] = true;
            }
            $firstCandidateIndex = $candidateIndexes[0];
            $previous = $items[$firstCandidateIndex - 1] ?? null;
            if ($previous !== null
                && $this->sourcePdfRecordSharesVisualFlow($previous, $items[$firstCandidateIndex])
                && preg_match('/[.!?;:]\s*$/u', rtrim((string) ($previous['text'] ?? ''))) !== 1
                && $this->completePdfSentencePrefix((string) ($previous['text'] ?? '')) === '') {
                $previous['sourceUnresolvedInterruptedFlow'] = true;
                $previous['sourceInterruptedColumnRegion'] = true;
                $items[$firstCandidateIndex - 1] = $previous;
            }
            $activeFlows[$flow] = true;
            $activeLanes[$lane] = true;
            unset($candidateFlows[$flow]);
        }

        return $items;
    }

    private function sourcePdfTextContainsCompactAssignmentLabel(string $text): bool
    {
        return preg_match('/(?:^|[^\p{L}\p{N}])\p{L}{1,3}\s*=\s*\p{N}+(?=[^\p{L}\p{N}]|$)/u', $text) === 1;
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private function sourcePdfRecordSharesVisualFlow(array $left, array $right): bool
    {
        return $this->pdfLayoutHasGeometry($left)
            && $this->pdfLayoutHasGeometry($right)
            && ($left['page'] ?? null) === ($right['page'] ?? null)
            && ($left['sourceStream'] ?? null) === ($right['sourceStream'] ?? null)
            && ($left['sourceGeometryColumn'] ?? null) === ($right['sourceGeometryColumn'] ?? null)
            && !isset($left['sourcePdfTableGroup'])
            && ($left['code'] ?? false) !== true;
    }

    /**
     * A content stream can paint one normal body line out of visual order when
     * a page also contains map panels or sidebars. Restore only immediately
     * adjacent source records when their geometry proves they are consecutive
     * lines in the same body lane. This does not use sentence vocabulary and
     * leaves independently positioned columns untouched.
     *
     * @param list<array<string, mixed>> $items
     * @param list<array{page: int, stream: int, text: string}> $sourceItems
     * @return list<array<string, mixed>>
     */
    private function restoreSourcePdfAdjacentVisualContinuations(array $items, array $sourceItems = []): array
    {
        $count = count($items);
        for ($index = 0; $index < $count; $index++) {
            $item = $items[$index];
            if (!$this->pdfLayoutHasGeometry($item)
                || isset($item['sourcePdfTableGroup'], $item['sourceSupplementalPositioned'])
                || !isset($item['sourcePdfSourceIndex'], $item['sourceStream'])) {
                continue;
            }

            $expectedSourceIndex = (int) ($item['sourcePdfSourceIndexEnd'] ?? $item['sourcePdfSourceIndex']) + 1;
            $candidateIndex = null;
            $sourceContinuation = false;
            for ($followingIndex = $index + 2; $followingIndex < $count; $followingIndex++) {
                $candidate = $items[$followingIndex];
                $isVisualContinuation = $this->sourcePdfItemsAreAdjacentVisualContinuation(
                    $item,
                    $candidate,
                    $expectedSourceIndex
                );
                $isSourceContinuation = !$isVisualContinuation
                    && $this->sourcePdfItemsAreAdjacentSourceContinuation($item, $candidate, $expectedSourceIndex);
                $isPanelSeparatedContinuation = !$isVisualContinuation
                    && !$isSourceContinuation
                    && $sourceItems !== []
                    && $this->sourcePdfItemsArePanelSeparatedSourceContinuation(
                        $item,
                        $candidate,
                        $sourceItems,
                        $items
                    );
                if (!$isVisualContinuation && !$isSourceContinuation && !$isPanelSeparatedContinuation) {
                    continue;
                }
                $candidateIndex = $followingIndex;
                $sourceContinuation = $isSourceContinuation || $isPanelSeparatedContinuation;
                break;
            }
            if ($candidateIndex === null) {
                continue;
            }

            $candidate = $items[$candidateIndex];
            if ($sourceContinuation) {
                unset($candidate['forceBlockBreakBefore']);
                $candidate['sourcePdfCrossPanelContinuation'] = true;
            }
            array_splice($items, $candidateIndex, 1);
            array_splice($items, $index + 1, 0, [$candidate]);
            $count = count($items);
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $previous
     * @param array<string, mixed> $candidate
     */
    private function sourcePdfItemsAreAdjacentVisualContinuation(
        array $previous,
        array $candidate,
        int $expectedSourceIndex
    ): bool {
        if (!$this->pdfLayoutHasGeometry($previous)
            || !$this->pdfLayoutHasGeometry($candidate)
            || isset($candidate['sourcePdfTableGroup'], $candidate['sourceSupplementalPositioned'])
            || ($candidate['sourcePdfSourceIndex'] ?? null) !== $expectedSourceIndex
            || ($candidate['page'] ?? null) !== ($previous['page'] ?? null)
            || ($candidate['sourceStream'] ?? null) !== ($previous['sourceStream'] ?? null)
            || preg_match('/[.!?;:]\s*$/u', rtrim((string) ($previous['text'] ?? ''))) === 1
            || $this->lineHasPdfListBlockEvidence((string) ($previous['text'] ?? ''))
            || $this->lineHasPdfListBlockEvidence((string) ($candidate['text'] ?? ''))) {
            return false;
        }

        $fontSize = max(1.0, (float) $previous['fontSize'], (float) $candidate['fontSize']);
        $verticalStep = (float) $previous['y1'] - (float) $candidate['y1'];
        if ($verticalStep < $fontSize * 0.30 || $verticalStep > $fontSize * 2.4) {
            return false;
        }

        return abs((float) $previous['x1'] - (float) $candidate['x1']) <= max(16.0, $fontSize * 2.0);
    }

    /**
     * @param array<string, mixed> $previous
     * @param array<string, mixed> $candidate
     */
    private function sourcePdfItemsAreAdjacentSourceContinuation(
        array $previous,
        array $candidate,
        int $expectedSourceIndex
    ): bool {
        if (isset($previous['sourcePdfTableGroup'], $candidate['sourcePdfTableGroup'])
            || ($previous['sourceSupplementalPositioned'] ?? false) === true
            || ($candidate['sourceSupplementalPositioned'] ?? false) === true
            || !isset($previous['sourcePdfSourceIndex'], $previous['sourceStream'])
            || ($candidate['sourcePdfSourceIndex'] ?? null) !== $expectedSourceIndex
            || ($candidate['page'] ?? null) !== ($previous['page'] ?? null)
            || ($candidate['sourceStream'] ?? null) !== ($previous['sourceStream'] ?? null)
            || preg_match('/[.!?;:]\s*$/u', rtrim((string) ($previous['text'] ?? ''))) === 1
            || $this->lineHasPdfListBlockEvidence((string) ($previous['text'] ?? ''))
            || $this->lineHasPdfListBlockEvidence((string) ($candidate['text'] ?? ''))
            || $this->lineLooksLikeUrlOnly((string) ($previous['text'] ?? ''))
            || $this->lineLooksLikeUrlOnly((string) ($candidate['text'] ?? ''))) {
            return false;
        }

        return preg_match('/^[^\p{L}\p{N}]*(?:\p{Ll}|[\)\]\},;:])/u', ltrim((string) ($candidate['text'] ?? ''))) === 1;
    }

    /**
     * A page footer or side panel may sit in the source stream between the
     * final line of one body column and the lower-case continuation at the
     * top of the next. Continue across that panel only when its smaller text
     * is physically below the body line and the surrounding columns and
     * typography prove the normal multi-column reading flow.
     *
     * @param array<string, mixed> $previous
     * @param array<string, mixed> $candidate
     * @param list<array{page: int, stream: int, text: string}> $sourceItems
     * @param list<array<string, mixed>> $items
     */
    private function sourcePdfItemsArePanelSeparatedSourceContinuation(
        array $previous,
        array $candidate,
        array $sourceItems,
        array $items
    ): bool {
        if (!$this->pdfLayoutHasGeometry($previous)
            || !$this->pdfLayoutHasGeometry($candidate)
            || isset($previous['sourcePdfTableGroup'], $candidate['sourcePdfTableGroup'])
            || ($previous['sourceSupplementalPositioned'] ?? false) === true
            || ($candidate['sourceSupplementalPositioned'] ?? false) === true
            || !isset(
                $previous['sourcePdfSourceIndex'],
                $previous['sourceStream'],
                $previous['sourceGeometryColumn'],
                $candidate['sourcePdfSourceIndex'],
                $candidate['sourceGeometryColumn']
            )
            || ($previous['page'] ?? null) !== ($candidate['page'] ?? null)
            || ($previous['sourceStream'] ?? null) !== ($candidate['sourceStream'] ?? null)
            || (int) $candidate['sourceGeometryColumn'] !== (int) $previous['sourceGeometryColumn'] + 1
            || preg_match('/[.!?;:]\s*$/u', rtrim((string) ($previous['text'] ?? ''))) === 1
            || preg_match('/^[^\p{L}\p{N}]*\p{Ll}/u', ltrim((string) ($candidate['text'] ?? ''))) !== 1
            || $this->lineHasPdfListBlockEvidence((string) ($previous['text'] ?? ''))
            || $this->lineHasPdfListBlockEvidence((string) ($candidate['text'] ?? ''))
            || $this->lineLooksLikeUrlOnly((string) ($previous['text'] ?? ''))
            || $this->lineLooksLikeUrlOnly((string) ($candidate['text'] ?? ''))) {
            return false;
        }

        $previousEnd = (int) ($previous['sourcePdfSourceIndexEnd'] ?? $previous['sourcePdfSourceIndex']);
        $candidateStart = (int) $candidate['sourcePdfSourceIndex'];
        if ($candidateStart <= $previousEnd + 1 || $candidateStart - $previousEnd > 16) {
            return false;
        }

        $fontSize = max(1.0, (float) $previous['fontSize'], (float) $candidate['fontSize']);
        if (abs((float) $previous['fontSize'] - (float) $candidate['fontSize']) > max(1.25, $fontSize * 0.20)
            || (float) $candidate['y1'] - (float) $previous['y1'] <= max(24.0, $fontSize * 3.0)) {
            return false;
        }

        $interveningSourceCount = 0;
        for ($index = $previousEnd + 1; $index < $candidateStart; $index++) {
            $source = $sourceItems[$index] ?? null;
            if ($source === null
                || ($source['page'] ?? null) !== ($previous['page'] ?? null)
                || ($source['stream'] ?? null) !== ($previous['sourceStream'] ?? null)) {
                return false;
            }
            $interveningSourceCount++;
        }
        if ($interveningSourceCount < 3) {
            return false;
        }

        $panelItems = [];
        foreach ($items as $item) {
            if (!$this->pdfLayoutHasGeometry($item)
                || ($item['page'] ?? null) !== ($previous['page'] ?? null)
                || ($item['sourceStream'] ?? null) !== ($previous['sourceStream'] ?? null)
                || !isset($item['sourcePdfSourceIndex'])) {
                continue;
            }
            $itemStart = (int) $item['sourcePdfSourceIndex'];
            $itemEnd = (int) ($item['sourcePdfSourceIndexEnd'] ?? $itemStart);
            if ($itemEnd < $previousEnd + 1 || $itemStart >= $candidateStart) {
                continue;
            }
            $panelItems[] = $item;
        }
        if (count($panelItems) < 3) {
            return false;
        }

        $panelFontSize = $this->median(array_map(
            static fn (array $item): float => max(1.0, (float) $item['fontSize']),
            $panelItems
        ));
        $panelTop = max(array_map(static fn (array $item): float => (float) $item['y1'], $panelItems));
        if ($panelFontSize > min((float) $previous['fontSize'], (float) $candidate['fontSize']) * 0.90
            || $panelTop > (float) $previous['y1'] - max(12.0, $fontSize * 1.20)) {
            return false;
        }

        foreach ($panelItems as $panel) {
            if (($panel['sourceGeometryColumn'] ?? null) === ($candidate['sourceGeometryColumn'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * A source-only line can be placed beside a known neighbor when it looks
     * like a wrapped continuation. Some PDF producers instead concatenate two
     * aligned columns into that source line. When positioned fragments on the
     * same baseline prove both pieces belong to separate columns, prefer those
     * fragments and discard the invented combined line.
     *
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function removeSourcePdfInferredCrossColumnDuplicates(array $items): array
    {
        $drop = [];
        foreach ($items as $index => $item) {
            if (($item['sourceInferredNeighborLayout'] ?? false) !== true
                || !$this->pdfLayoutHasGeometry($item)) {
                continue;
            }

            $sourceText = $this->pdfComparableLineText((string) ($item['text'] ?? ''));
            if ($this->length($sourceText) < 12) {
                continue;
            }
            $fontSize = max(1.0, (float) $item['fontSize']);
            foreach ($items as $suffixIndex => $suffix) {
                if ($suffixIndex === $index
                    || ($suffix['sourceSupplementalPositioned'] ?? false) !== true
                    || !$this->pdfLayoutHasGeometry($suffix)
                    || ($suffix['page'] ?? null) !== ($item['page'] ?? null)
                    || abs((float) $suffix['y1'] - (float) $item['y1']) > max(2.0, $fontSize * 0.35)
                    || abs((float) $suffix['x1'] - (float) $item['x1']) > max(8.0, $fontSize * 0.75)) {
                    continue;
                }

                $suffixText = $this->pdfComparableLineText((string) ($suffix['text'] ?? ''));
                if ($this->length($suffixText) < 4
                    || $this->length($sourceText) <= $this->length($suffixText) + 3
                    || !str_ends_with($sourceText, $suffixText)) {
                    continue;
                }

                foreach ($items as $prefixIndex => $prefix) {
                    if ($prefixIndex === $index || $prefixIndex === $suffixIndex
                        || !$this->pdfLayoutHasGeometry($prefix)
                        || ($prefix['page'] ?? null) !== ($item['page'] ?? null)
                        || abs((float) $prefix['y1'] - (float) $item['y1']) > max(2.0, $fontSize * 0.35)
                        || (float) $prefix['x2'] > (float) $item['x1'] - max(12.0, $fontSize * 1.5)) {
                        continue;
                    }

                    $prefixText = $this->pdfComparableLineText((string) ($prefix['text'] ?? ''));
                    if ($this->length($prefixText) >= 4 && str_contains($sourceText, $prefixText)) {
                        $drop[$index] = true;
                        break 2;
                    }
                }
            }
        }

        if ($drop === []) {
            return $items;
        }

        return array_values(array_filter(
            $items,
            static fn (array $_item, int $index): bool => !isset($drop[$index]),
            ARRAY_FILTER_USE_BOTH
        ));
    }

    /**
     * @param list<array{page: int, stream: int, text: string}> $sourceItems
     * @return array<int, int>
     */
    private function sourcePdfTableSourceGroups(array $sourceItems): array
    {
        $lines = array_column($sourceItems, 'text');
        $candidates = [];
        for ($index = 0, $count = count($lines); $index < $count; $index++) {
            $inline = $this->tableRowsAt($lines, $index);
            $stacked = $this->stackedTableRowsAt($lines, $index);
            $candidate = $stacked['consumed'] > $inline['consumed'] ? $stacked : $inline;
            if ($candidate['rows'] === [] || $candidate['consumed'] < 2) {
                continue;
            }
            if ($this->sourcePdfTableCandidateLooksLikeDiagramLabelGrid($candidate)) {
                continue;
            }

            $candidates[] = [
                'start' => $index,
                'consumed' => $candidate['consumed'],
                'score' => $this->sourcePdfTableCandidateScore($candidate),
            ];
        }

        // Stacked-cell PDFs can make a real three-column table look like
        // several wider, shifted candidates when adjacent prose lands in the
        // same source stream. Prefer the densest candidate, then reserve its
        // source range before considering any overlapping alternative.
        usort($candidates, static function (array $left, array $right): int {
            return $right['score'] <=> $left['score']
                ?: $right['consumed'] <=> $left['consumed']
                ?: $left['start'] <=> $right['start'];
        });

        $groups = [];
        $occupied = [];
        $group = 0;
        foreach ($candidates as $candidate) {
            $end = $candidate['start'] + $candidate['consumed'];
            $overlapsExistingGroup = false;
            for ($index = $candidate['start']; $index < $end; $index++) {
                if (isset($occupied[$index])) {
                    $overlapsExistingGroup = true;
                    break;
                }
            }
            if ($overlapsExistingGroup) {
                continue;
            }

            $group++;
            for ($index = $candidate['start']; $index < $end && $index < count($lines); $index++) {
                $groups[$index] = $group;
                $occupied[$index] = true;
            }
        }

        return $groups;
    }

    /**
     * @param array{rows: list<list<string>>, consumed: int} $candidate
     */
    private function sourcePdfTableCandidateScore(array $candidate): float
    {
        $rows = $candidate['rows'];
        $columnCount = max(1, count($rows[0] ?? []));
        $bodyRows = max(0, count($rows) - 1);
        $headerPenalty = 0.0;
        foreach ($rows[0] ?? [] as $headerCell) {
            $headerCell = trim($headerCell);
            if (preg_match('/[.!?;:]/u', $headerCell) === 1) {
                $headerPenalty += 2.0;
            }
            if (count($this->pdfLineWordTokens($headerCell)) > 5) {
                $headerPenalty += 1.0;
            }
        }

        return ($bodyRows / $columnCount) * 100.0 + $bodyRows * 4.0 - $headerPenalty;
    }

    /**
     * A flowchart can expose its labels as a narrow grid of individual glyphs.
     * Such a grid has no numeric/data-like cells and is dominated by one-letter
     * fragments, unlike ordinary compact tables. Reject it before table source
     * provenance protects those fragments from prose cleanup.
     *
     * @param array{rows: list<list<string>>, consumed: int} $candidate
     */
    private function sourcePdfTableCandidateLooksLikeDiagramLabelGrid(array $candidate): bool
    {
        $cells = [];
        foreach ($candidate['rows'] as $row) {
            foreach ($row as $cell) {
                $cell = trim($cell);
                if ($cell !== '') {
                    $cells[] = $cell;
                }
            }
        }
        if (count($cells) < 6) {
            return false;
        }

        $short = 0;
        $singleGlyph = 0;
        $numeric = 0;
        foreach ($cells as $cell) {
            $compact = preg_replace('/[^\p{L}\p{N}]+/u', '', $cell) ?? '';
            $length = $this->length($compact);
            if ($length <= 7 && count($this->pdfLineWordTokens($cell)) <= 2) {
                $short++;
            }
            if ($length === 1 && preg_match('/^\p{L}$/u', $compact) === 1) {
                $singleGlyph++;
            }
            if (preg_match('/\p{N}/u', $compact) === 1) {
                $numeric++;
            }
        }

        return $numeric === 0
            && $short / count($cells) >= 0.80
            && $singleGlyph >= max(3, (int) ceil(count($cells) * 0.25));
    }

    /**
     * @param array<string, mixed> $item
     * @param array<int, int> $tableSourceGroups
     * @return array<string, mixed>
     */
    private function sourcePdfAttachSourceItemProvenance(array $item, int $sourceIndex, array $tableSourceGroups): array
    {
        $item['sourcePdfSourceIndex'] = $sourceIndex;
        if (isset($tableSourceGroups[$sourceIndex])) {
            $item['sourcePdfTableGroup'] = $tableSourceGroups[$sourceIndex];
        }

        return $item;
    }

    /**
     * Keep the source stream's cell sequence intact while placing a detected
     * table where its first visual item occurred among the surrounding prose.
     * The geometry of individual table cells is often less reliable than its
     * source order, especially when the PDF has overlapping text layers.
     *
     * @param list<array<string, mixed>> $items
     * @param list<array{page: int, stream: int, text: string}> $sourceItems
     * @return list<array<string, mixed>>
     */
    private function restoreSourcePdfTableItemSequences(array $items, array $sourceItems = []): array
    {
        $groups = [];
        foreach ($items as $index => $item) {
            if (!isset($item['sourcePdfTableGroup'], $item['sourcePdfSourceIndex'])) {
                continue;
            }
            $group = (int) $item['sourcePdfTableGroup'];
            $groups[$group]['firstIndex'] = min($groups[$group]['firstIndex'] ?? $index, $index);
            $groups[$group]['items'][] = $item;
            $sourceStart = (int) $item['sourcePdfSourceIndex'];
            $sourceEnd = (int) ($item['sourcePdfSourceIndexEnd'] ?? $sourceStart);
            $groups[$group]['sourceStart'] = min($groups[$group]['sourceStart'] ?? $sourceStart, $sourceStart);
            $groups[$group]['sourceEnd'] = max($groups[$group]['sourceEnd'] ?? $sourceEnd, $sourceEnd);
        }
        if ($groups === []) {
            return $items;
        }

        $groupsAtIndex = [];
        foreach ($groups as $group => $entry) {
            usort($entry['items'], static fn (array $left, array $right): int => (int) $left['sourcePdfSourceIndex'] <=> (int) $right['sourcePdfSourceIndex']);
            $tableItems = $entry['items'];
            if ($sourceItems !== []
                && isset($entry['sourceStart'], $entry['sourceEnd'])
                && $entry['sourceStart'] >= 0
                && $entry['sourceEnd'] < count($sourceItems)) {
                $tableItems = [];
                for ($sourceIndex = $entry['sourceStart']; $sourceIndex <= $entry['sourceEnd']; $sourceIndex++) {
                    $tableItem = $this->sourcePdfLineItem($sourceItems[$sourceIndex]);
                    $tableItem['sourcePdfSourceIndex'] = $sourceIndex;
                    $tableItem['sourcePdfTableGroup'] = $group;
                    $tableItems[] = $tableItem;
                }
            }
            $groupsAtIndex[$entry['firstIndex']][] = $tableItems;
        }

        $restored = [];
        foreach ($items as $index => $item) {
            foreach ($groupsAtIndex[$index] ?? [] as $tableItems) {
                if ($restored !== []) {
                    $tableItems[0]['forceBlockBreakBefore'] = true;
                }
                foreach ($tableItems as $tableItem) {
                    $restored[] = $tableItem;
                }
            }
            if (isset($item['sourcePdfTableGroup'])) {
                continue;
            }
            $restored[] = $item;
        }

        return $restored;
    }

    /**
     * Prefer a visually complete supplemental line over a clipped source
     * duplicate only when both occupy the same baseline and local text
     * region. A larger horizontal offset is evidence of a neighboring column
     * or figure and must remain independently represented.
     *
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function removeSourcePdfItemsCoveredBySupplementalPositioning(array $items): array
    {
        $filtered = [];
        foreach ($items as $item) {
            if (($item['sourceSupplementalUrlOverlap'] ?? false) === true) {
                continue;
            }
            if (($item['sourceSupplementalPositioned'] ?? false) === true
                || isset($item['sourcePdfTableGroup'])
                || !$this->pdfLayoutHasGeometry($item)) {
                $filtered[] = $item;
                continue;
            }
            $covered = false;
            foreach ($items as $supplemental) {
                if (($supplemental['sourceSupplementalPositioned'] ?? false) !== true
                    || !$this->pdfLayoutHasGeometry($supplemental)
                    || ($supplemental['page'] ?? null) !== ($item['page'] ?? null)) {
                    continue;
                }
                $fontSize = max((float) $item['fontSize'], (float) $supplemental['fontSize'], 1.0);
                $itemBaseline = ((float) $item['y1'] + (float) $item['y2']) / 2.0;
                $supplementalBaseline = ((float) $supplemental['y1'] + (float) $supplemental['y2']) / 2.0;
                if (abs($itemBaseline - $supplementalBaseline) > max(2.0, $fontSize * 0.60)
                    || abs((float) $item['x1'] - (float) $supplemental['x1']) > max(16.0, $fontSize * 2.0)) {
                    continue;
                }
                $sourceText = (string) ($item['text'] ?? '');
                $supplementalText = (string) ($supplemental['text'] ?? '');
                if ($this->sourcePdfTextIsCoveredBySupplementalPositioning($sourceText, $supplementalText)
                    || $this->sourcePdfShortTextIsCoveredBySupplementalLine($sourceText, $supplementalText)) {
                    $covered = true;
                    break;
                }
            }
            if (!$covered) {
                $filtered[] = $item;
            }
        }

        return $filtered;
    }

    /**
     * A positioned extractor can combine each row of a source-detected table
     * into a supplemental prose line. When several such duplicates share a
     * left edge and baseline rhythm, retain the source table cells and remove
     * only the repeated positioned rows. Isolated supplemental captions stay
     * available to the prose flow.
     *
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function removeSourcePdfSupplementalRowDuplicates(array $items): array
    {
        $candidates = [];
        foreach ($items as $index => $item) {
            if (($item['sourceSupplementalPositioned'] ?? false) !== true
                || ($item['sourceSupplementalSourceOverlap'] ?? false) !== true
                || ($item['sourceSupplementalRecoverableSentenceSuffix'] ?? false) === true
                || !$this->pdfLayoutHasGeometry($item)) {
                continue;
            }
            $candidates[] = $index;
        }
        if (count($candidates) < 3) {
            return $items;
        }

        $groups = [];
        foreach ($candidates as $index) {
            $item = $items[$index];
            $groupIndex = null;
            foreach ($groups as $candidateIndex => $group) {
                $anchor = $items[$group[0]];
                if (($anchor['page'] ?? null) !== ($item['page'] ?? null)) {
                    continue;
                }
                $fontSize = max(1.0, (float) $anchor['fontSize'], (float) $item['fontSize']);
                if (abs((float) $anchor['x1'] - (float) $item['x1']) <= max(12.0, $fontSize * 1.5)) {
                    $groupIndex = $candidateIndex;
                    break;
                }
            }
            if ($groupIndex === null) {
                $groups[] = [$index];
            } else {
                $groups[$groupIndex][] = $index;
            }
        }

        $drop = [];
        foreach ($groups as $group) {
            if (count($group) < 3) {
                continue;
            }
            usort($group, fn (int $left, int $right): int => (float) $items[$right]['y1'] <=> (float) $items[$left]['y1']);
            $rhythmPairs = 0;
            foreach ($group as $offset => $index) {
                if (!isset($group[$offset + 1])) {
                    break;
                }
                $next = $items[$group[$offset + 1]];
                $fontSize = max(1.0, (float) $items[$index]['fontSize'], (float) $next['fontSize']);
                $step = (float) $items[$index]['y1'] - (float) $next['y1'];
                if ($step >= $fontSize * 0.35 && $step <= $fontSize * 2.2) {
                    $rhythmPairs++;
                }
            }
            if ($rhythmPairs >= count($group) - 2) {
                foreach ($group as $index) {
                    $drop[$index] = true;
                }
            }
        }

        if ($drop === []) {
            return $items;
        }

        return array_values(array_filter(
            $items,
            static fn (array $_item, int $index): bool => !isset($drop[$index]),
            ARRAY_FILTER_USE_BOTH
        ));
    }

    /**
     * A PDF can contain an overlapping fallback text layer for a visible line.
     * When the two runs occupy the same local baseline and one is substantially
     * covered by the other, retaining both repeats part of a sentence.
     * This is geometry and text-coverage based; it does not inspect document
     * vocabulary or assume a particular language.
     *
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function removeSourcePdfNearBaselinePrefixDuplicates(array $items): array
    {
        $drop = [];
        $count = count($items);
        for ($index = 0; $index < $count; $index++) {
            $item = $items[$index];
            if (!$this->sourcePdfNearBaselinePrefixDuplicateCandidate($item)) {
                continue;
            }

            for ($candidateIndex = $index + 1; $candidateIndex < $count; $candidateIndex++) {
                $candidate = $items[$candidateIndex];
                if (!$this->sourcePdfNearBaselinePrefixDuplicateCandidate($candidate)
                    || ($item['page'] ?? null) !== ($candidate['page'] ?? null)) {
                    continue;
                }
                if (($item['sourceGeometryColumn'] ?? null) !== ($candidate['sourceGeometryColumn'] ?? null)
                    && ($item['sourceFloatingGeometry'] ?? false) !== true
                    && ($candidate['sourceFloatingGeometry'] ?? false) !== true) {
                    continue;
                }
                if (isset($item['sourceStream'], $candidate['sourceStream'])
                    && (int) $item['sourceStream'] !== (int) $candidate['sourceStream']) {
                    continue;
                }

                $fontSize = max(1.0, (float) $item['fontSize'], (float) $candidate['fontSize']);
                $horizontalTolerance = max(5.0, $fontSize * 0.60);
                $sameLeftEdge = abs((float) $item['x1'] - (float) $candidate['x1']) <= $horizontalTolerance;
                $oneContainsTheOther = ((float) $item['x1'] <= (float) $candidate['x1'] + $horizontalTolerance
                        && (float) $item['x2'] >= (float) $candidate['x2'] - $horizontalTolerance)
                    || ((float) $candidate['x1'] <= (float) $item['x1'] + $horizontalTolerance
                        && (float) $candidate['x2'] >= (float) $item['x2'] - $horizontalTolerance);
                if (abs((float) $item['y1'] - (float) $candidate['y1']) > max(1.5, $fontSize * 0.35)
                    || (!$sameLeftEdge && !$oneContainsTheOther)) {
                    continue;
                }

                $itemText = $this->pdfComparableLineText((string) ($item['text'] ?? ''));
                $candidateText = $this->pdfComparableLineText((string) ($candidate['text'] ?? ''));
                $itemLength = $this->length($itemText);
                $candidateLength = $this->length($candidateText);
                if (min($itemLength, $candidateLength) < 12) {
                    continue;
                }

                if ($itemText === $candidateText) {
                    $drop[$candidateIndex] = true;
                    continue;
                }

                if ($itemLength < $candidateLength
                    && $this->sourcePdfNearBaselineTextIsCoveredBy(
                        $itemText,
                        $candidateText,
                        $item,
                        $candidate,
                        $fontSize
                    )) {
                    $drop[$index] = true;
                    continue;
                }
                if ($candidateLength < $itemLength
                    && $this->sourcePdfNearBaselineTextIsCoveredBy(
                        $candidateText,
                        $itemText,
                        $candidate,
                        $item,
                        $fontSize
                    )) {
                    $drop[$candidateIndex] = true;
                }
            }
        }

        if ($drop === []) {
            return $items;
        }

        return array_values(array_filter(
            $items,
            static fn (array $_item, int $index): bool => !isset($drop[$index]),
            ARRAY_FILTER_USE_BOTH
        ));
    }

    /**
     * @param array<string, mixed> $shortLayout
     * @param array<string, mixed> $longLayout
     */
    private function sourcePdfNearBaselineTextIsCoveredBy(
        string $shortText,
        string $longText,
        array $shortLayout,
        array $longLayout,
        float $fontSize
    ): bool {
        $shortLength = $this->length($shortText);
        $longLength = $this->length($longText);
        if ($shortLength === 0 || $longLength <= $shortLength || !str_contains($longText, $shortText)) {
            return false;
        }

        $ratio = $shortLength / $longLength;
        $substantial = ($shortLength >= 12 && $ratio >= 0.80)
            || ($shortLength >= 32 && $ratio >= 0.45);
        if (!$substantial) {
            return false;
        }
        if (($shortLayout['sourceInferredNeighborLayout'] ?? false) === true
            || ($longLayout['sourceInferredNeighborLayout'] ?? false) === true) {
            return true;
        }

        $horizontalTolerance = max(8.0, $fontSize);
        return (float) $shortLayout['x1'] >= (float) $longLayout['x1'] - $horizontalTolerance
            && (float) $shortLayout['x2'] <= (float) $longLayout['x2'] + $horizontalTolerance;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function sourcePdfNearBaselinePrefixDuplicateCandidate(array $item): bool
    {
        return $this->pdfLayoutHasGeometry($item)
            && !isset($item['sourcePdfTableGroup'])
            && ($item['sourceSupplementalRecoverableSentenceSuffix'] ?? false) !== true
            && ($item['sourcePdfReferenceEntry'] ?? false) !== true
            && ($item['code'] ?? false) !== true
            && $this->length($this->pdfComparableLineText((string) ($item['text'] ?? ''))) >= 12;
    }

    /**
     * A positioned text layer can paint a diagram label over an ordinary
     * wrapped source line. When the source line has a normal same-column
     * continuation immediately below it, that overlay cannot belong in the
     * source flow. Keep true same-line completions, which have no such source
     * continuation, and discard only the conflicting overlay.
     *
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function removeSourcePdfSupplementalOverlaysThatInterruptSourceWrap(array $items): array
    {
        $drop = [];
        foreach ($items as $overlayIndex => $overlay) {
            if (($overlay['sourceSupplementalPositioned'] ?? false) !== true
                || ($overlay['sourceSupplementalSourceOverlap'] ?? false) !== true
                || ($overlay['sourceSupplementalRecoverableSentenceSuffix'] ?? false) === true
                || isset($overlay['sourcePdfTableGroup'])
                || !$this->pdfLayoutHasGeometry($overlay)) {
                continue;
            }

            foreach ($items as $sourceIndex => $source) {
                if ($sourceIndex === $overlayIndex
                    || ($source['sourceSupplementalPositioned'] ?? false) === true
                    || isset($source['sourcePdfTableGroup'])
                    || ($source['code'] ?? false) === true
                    || !$this->pdfLayoutHasGeometry($source)
                    || ($source['page'] ?? null) !== ($overlay['page'] ?? null)
                    || ($source['sourceGeometryColumn'] ?? null) !== ($overlay['sourceGeometryColumn'] ?? null)) {
                    continue;
                }

                $fontSize = max(1.0, (float) $source['fontSize'], (float) $overlay['fontSize']);
                if (abs((float) $source['y1'] - (float) $overlay['y1']) > max(1.5, $fontSize * 0.35)
                    || (float) $overlay['x1'] < (float) $source['x1'] - max(8.0, $fontSize)
                    || (float) $overlay['x2'] > (float) $source['x2'] + max(8.0, $fontSize)
                    || !$this->sourcePdfRecordHasLowercaseWrappedContinuation($items, $sourceIndex)) {
                    continue;
                }

                $drop[$overlayIndex] = true;
                break;
            }
        }

        if ($drop === []) {
            return $items;
        }

        return array_values(array_filter(
            $items,
            static fn (array $_item, int $index): bool => !isset($drop[$index]),
            ARRAY_FILTER_USE_BOTH
        ));
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function sourcePdfRecordHasLowercaseWrappedContinuation(array $items, int $sourceIndex): bool
    {
        $source = $items[$sourceIndex] ?? null;
        if (!is_array($source)
            || preg_match('/[.!?;:]\s*$/u', rtrim((string) ($source['text'] ?? ''))) === 1
            || !$this->pdfLayoutHasGeometry($source)) {
            return false;
        }

        for ($index = $sourceIndex + 1, $limit = min(count($items), $sourceIndex + 7); $index < $limit; $index++) {
            $candidate = $items[$index];
            if (($candidate['sourceSupplementalPositioned'] ?? false) === true
                || isset($candidate['sourcePdfTableGroup'])
                || !$this->pdfLayoutHasGeometry($candidate)
                || ($candidate['page'] ?? null) !== ($source['page'] ?? null)
                || ($candidate['sourceGeometryColumn'] ?? null) !== ($source['sourceGeometryColumn'] ?? null)) {
                continue;
            }

            $fontSize = max(1.0, (float) $source['fontSize'], (float) $candidate['fontSize']);
            $verticalStep = (float) $source['y1'] - (float) $candidate['y1'];
            if ($verticalStep < $fontSize * 0.35 || $verticalStep > $fontSize * 1.80
                || abs((float) $source['x1'] - (float) $candidate['x1']) > max(8.0, $fontSize)
                || preg_match('/^[^\p{L}\p{N}]*\p{Ll}/u', ltrim((string) ($candidate['text'] ?? ''))) !== 1) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * The source text layer can retain a clipped duplicate of a positioned
     * repair line. When nearly all of that source-only fragment is present at
     * the same end of a supplemental visual line, keeping both fabricates a
     * repeated phrase. Retain the visually complete representation instead.
     *
     * @param list<array<string, mixed>> $fallback
     * @param list<array<string, mixed>> $ordered
     * @return list<array<string, mixed>>
     */
    private function removeSourcePdfFallbackCoveredBySupplementalPositioning(array $fallback, array $ordered): array
    {
        $filtered = [];
        foreach ($fallback as $fallbackItem) {
            if (($fallbackItem['sourceUnmatchedFallback'] ?? false) !== true
                || isset($fallbackItem['sourcePdfTableGroup'])) {
                $filtered[] = $fallbackItem;
                continue;
            }

            $covered = false;
            foreach ($ordered as $positionedItem) {
                if (($positionedItem['sourceSupplementalPositioned'] ?? false) !== true
                    || ($positionedItem['page'] ?? null) !== ($fallbackItem['page'] ?? null)) {
                    continue;
                }
                if ($this->sourcePdfTextIsCoveredBySupplementalPositioning(
                    (string) ($fallbackItem['text'] ?? ''),
                    (string) ($positionedItem['text'] ?? '')
                )) {
                    $covered = true;
                    break;
                }
            }
            if (!$covered) {
                $filtered[] = $fallbackItem;
            }
        }

        return $filtered;
    }

    private function sourcePdfTextIsCoveredBySupplementalPositioning(string $sourceText, string $positionedText): bool
    {
        $sourceTokens = $this->sourcePdfComparableTokens($sourceText);
        $positionedTokens = $this->sourcePdfComparableTokens($positionedText);
        if (count($positionedTokens) < count($sourceTokens)
            && preg_match('/[.!?;:]\s*$/u', rtrim($sourceText)) === 1) {
            // A positioned prefix can lose a trailing font run. A complete
            // source sentence on the same baseline is stronger evidence than
            // that shorter prefix and must remain available for restoration.
            return false;
        }
        if (count($sourceTokens) < 4 || count($positionedTokens) < 4) {
            return $this->sourcePdfComparableTextIsCoveredBySupplementalPositioning($sourceText, $positionedText);
        }

        $prefix = 0;
        $prefixLimit = min(count($sourceTokens), count($positionedTokens));
        while ($prefix < $prefixLimit && $sourceTokens[$prefix] === $positionedTokens[$prefix]) {
            $prefix++;
        }
        $suffix = 0;
        while ($suffix < $prefixLimit
            && $sourceTokens[count($sourceTokens) - 1 - $suffix] === $positionedTokens[count($positionedTokens) - 1 - $suffix]) {
            $suffix++;
        }

        $overlap = max($prefix, $suffix);
        $matchedTokens = array_slice($sourceTokens, $prefix >= $suffix ? 0 : count($sourceTokens) - $suffix, $overlap);
        $matchedCharacters = $this->length(implode('', $matchedTokens));

        if ($overlap >= max(3, (int) ceil(count($sourceTokens) * 0.75))
            && $matchedCharacters >= 18) {
            return true;
        }

        return $this->sourcePdfComparableTextIsCoveredBySupplementalPositioning($sourceText, $positionedText);
    }

    /**
     * A positioned line may preserve a source line whose internal word gaps
     * were lost by font changes or per-glyph text commands. At the same
     * baseline, a long compact substring is stronger evidence than token
     * equality and lets the complete visual line replace its clipped source
     * duplicate without relying on document vocabulary.
     */
    private function sourcePdfComparableTextIsCoveredBySupplementalPositioning(
        string $sourceText,
        string $positionedText
    ): bool {
        $source = $this->pdfComparableLineText($sourceText);
        $positioned = $this->pdfComparableLineText($positionedText);
        $sourceLength = $this->length($source);
        $positionedLength = $this->length($positioned);
        if ($sourceLength < 24 || $positionedLength < $sourceLength) {
            return false;
        }

        return str_contains($positioned, $source)
            && $sourceLength / $positionedLength >= 0.58;
    }

    /**
     * A source text layer may retain only the first few glyphs of a line that
     * is otherwise recovered from positioned text. This is deliberately used
     * only after the caller has established that both entries share a baseline
     * and start position, so a short common word elsewhere on the page cannot
     * cause content to disappear.
     */
    private function sourcePdfShortTextIsCoveredBySupplementalLine(string $sourceText, string $positionedText): bool
    {
        $source = $this->pdfComparableLineText($sourceText);
        $positioned = $this->pdfComparableLineText($positionedText);
        $sourceLength = $this->length($source);
        if ($sourceLength < 3 || $sourceLength > 12 || $this->length($positioned) <= $sourceLength) {
            return false;
        }

        return str_starts_with($positioned, $source) || str_ends_with($positioned, $source);
    }

    /**
     * @return list<string>
     */
    private function sourcePdfComparableTokens(string $text): array
    {
        $tokens = [];
        foreach ($this->pdfLineWordTokens($text) as $token) {
            $normalized = $this->pdfComparableLineText($token);
            if ($normalized !== '') {
                $tokens[] = $normalized;
            }
        }

        return $tokens;
    }

    /**
     * A page with both floating geometry and a sustained source-only label
     * cluster is a diagram-heavy layout. It may still contain ordinary body
     * columns, so later cleanup uses this only for short, detached fragments.
     *
     * @param list<array<string, mixed>> $ordered
     * @param list<array<string, mixed>> $fallback
     */
    private function sourcePdfPageHasDetachedDiagramEvidence(array $ordered, array $fallback): bool
    {
        $floatingGeometry = count(array_filter(
            $ordered,
            static fn (array $item): bool => ($item['sourceFloatingGeometry'] ?? false) === true
        ));
        if ($floatingGeometry < 3 || count($fallback) < 8) {
            return false;
        }

        $compactLabels = 0;
        foreach ($fallback as $item) {
            $text = trim((string) ($item['text'] ?? ''));
            if ($text === ''
                || $this->lineHasPdfListBlockEvidence($text)
                || $this->lineLooksLikeUrlOnly($text)
                || preg_match('/[.!?;:]\s*$/u', $text) === 1) {
                continue;
            }
            $compact = preg_replace('/\s+/u', '', $text) ?? '';
            if ($this->length($compact) <= 18 && count($this->pdfLineWordTokens($text)) <= 4) {
                $compactLabels++;
            }
        }

        return $compactLabels >= 8 && $compactLabels / count($fallback) >= 0.45;
    }

    /**
     * The positioned extractor already provides a useful reading order for
     * ordinary pages. Dense papers can also contain figures whose tiny labels
     * split that order into artificial columns. Rebuild only those pages from
     * stable body-text anchors, leaving every other layout on its existing
     * path.
     *
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function orderSourcePdfItemsWithinStableColumns(array $items): array
    {
        $geometryItems = array_values(array_filter(
            $items,
            fn (array $item): bool => $this->pdfLayoutHasGeometry($item)
        ));
        if (count($geometryItems) < 20) {
            return $items;
        }

        $columns = $this->sourcePdfStableTextColumns($geometryItems);
        if (!$this->sourcePdfColumnsLookLikeParallelBodyColumns($columns)) {
            return $items;
        }

        // A styled inline fragment can begin beyond the normal first-line
        // indent tolerance while still sharing a baseline and source sequence
        // with a body line in the same column. Compose that proven visual
        // line before column assignment so the trailing fragment is not
        // mistaken for detached geometry.
        $items = $this->composePositionedPdfInlineFragmentsWithinStableColumns($items, $columns);

        $columnItems = array_fill(0, count($columns), []);
        $floating = [];
        $floatingGeometryCount = 0;
        $diagramLabelCount = 0;
        $medianFontSize = $this->median(array_map(
            static fn (array $item): float => max(1.0, (float) $item['fontSize']),
            $geometryItems
        ));
        foreach ($items as $item) {
            if (!$this->pdfLayoutHasGeometry($item)) {
                $floating[] = $item;
                continue;
            }

            $columnIndex = $this->sourcePdfColumnIndexForItem($item, $columns);
            if ($columnIndex === null
                && ($item['sourceFootnotePrefixedGeometry'] ?? false) === true) {
                $columnIndex = $this->sourcePdfFootnotePrefixedGeometryColumnIndex($item, $items, $columns);
            }
            if ($columnIndex === null) {
                $item['sourceFloatingGeometry'] = true;
                $floating[] = $item;
                $floatingGeometryCount++;
                $text = trim((string) ($item['text'] ?? ''));
                if ((float) $item['fontSize'] < $medianFontSize * 0.68
                    && $this->length($text) <= 36
                    && count($this->pdfLineWordTokens($text)) <= 5
                    && preg_match('/[.!?;:]\s*$/u', $text) !== 1
                    && !$this->lineHasPdfListBlockEvidence($text)
                    && !$this->lineLooksLikeUrlOnly($text)) {
                    $diagramLabelCount++;
                }
                continue;
            }

            unset($item['forceBlockBreakBefore']);
            $item['sourceStructuredGeometry'] = true;
            $item['sourceGeometryColumn'] = $columnIndex;
            $columnItems[$columnIndex][] = $item;
        }

        $complexFigurePage = $floatingGeometryCount >= 4 && $diagramLabelCount >= 5;
        $interruptedFloating = array_values(array_filter(
            $floating,
            fn (array $item): bool => $this->pdfLayoutHasGeometry($item)
                && !$this->sourcePdfFloatingItemCanStandAlone($item, $medianFontSize)
        ));

        $ordered = [];
        $previousColumnEntries = null;
        $deferredMinorFontEntries = [];
        foreach ($columnItems as $columnIndex => $entries) {
            usort($entries, static function (array $left, array $right): int {
                return ((float) $right['y1'] <=> (float) $left['y1'])
                    ?: ((float) $left['x1'] <=> (float) $right['x1']);
            });
            $entries = $this->markSourcePdfMinorFontFlows($entries, $medianFontSize);
            $minorFontEntries = array_values(array_filter(
                $entries,
                static fn (array $entry): bool => ($entry['sourceMinorFontFlow'] ?? false) === true
            ));
            if ($minorFontEntries !== []) {
                // A footnote may sit below an unfinished body sentence. Keep
                // it on the page, but defer it until after body columns have
                // been connected so it cannot become a false paragraph tail.
                $deferredMinorFontEntries = array_merge($deferredMinorFontEntries, $minorFontEntries);
                $entries = array_values(array_filter(
                    $entries,
                    static fn (array $entry): bool => ($entry['sourceMinorFontFlow'] ?? false) !== true
                ));
            }
            $entries = $this->markSourcePdfIndentedParagraphBoundaries($entries, $columns[$columnIndex]);
            $entries = $this->markSourcePdfOrphanedColumnFlows(
                $entries,
                $previousColumnEntries,
                $columns[$columnIndex]
            );
            if ($interruptedFloating !== []) {
                $entries = $this->markSourcePdfInterruptedColumnFragments($entries, $interruptedFloating);
                // Floating overlap is established after the first flow pass.
                // Re-run it so normal-baseline continuations inherit the
                // interruption marker instead of becoming detached prose.
                $entries = $this->markSourcePdfOrphanedColumnFlows(
                    $entries,
                    $previousColumnEntries,
                    $columns[$columnIndex]
                );
            }
            $entries = $this->prioritizeSourcePdfVerifiedCrossColumnContinuation(
                $entries,
                $previousColumnEntries
            );
            foreach ($entries as $entry) {
                if ($complexFigurePage) {
                    $entry['sourceComplexGeometryPage'] = true;
                }
                $ordered[] = $entry;
            }
            $previousColumnEntries = $entries;
        }
        foreach ($floating as $entry) {
            if (!$this->sourcePdfFloatingItemCanStandAlone($entry, $medianFontSize)) {
                continue;
            }
            if ($complexFigurePage && $this->pdfLayoutHasGeometry($entry)) {
                $entry['sourceComplexGeometryPage'] = true;
            }
            $ordered[] = $entry;
        }
        foreach ($deferredMinorFontEntries as $entry) {
            if ($complexFigurePage && $this->pdfLayoutHasGeometry($entry)) {
                $entry['sourceComplexGeometryPage'] = true;
            }
            $ordered[] = $entry;
        }

        return $ordered;
    }

    /**
     * A positioned line that begins with a source-confirmed footnote marker
     * can continue a body line after the marker itself changes font or rises
     * as superscript. Its suffix may start beyond the usual column width, so
     * assign it only when a normal body line shares its baseline and span.
     *
     * @param array<string, mixed> $item
     * @param list<array<string, mixed>> $items
     * @param list<array{x: float, width: float, fontSize: float, count: int}> $columns
     */
    private function sourcePdfFootnotePrefixedGeometryColumnIndex(array $item, array $items, array $columns): ?int
    {
        if (!$this->pdfLayoutHasGeometry($item)
            || ($item['sourceFootnotePrefixedGeometry'] ?? false) !== true) {
            return null;
        }

        foreach ($items as $neighbor) {
            if ($neighbor === $item
                || !$this->pdfLayoutHasGeometry($neighbor)
                || ($neighbor['code'] ?? false) === true
                || ($neighbor['page'] ?? null) !== ($item['page'] ?? null)) {
                continue;
            }

            $columnIndex = $this->sourcePdfColumnIndexForItem($neighbor, $columns);
            if ($columnIndex === null) {
                continue;
            }
            $fontSize = max(1.0, (float) $item['fontSize'], (float) $neighbor['fontSize']);
            if (abs((float) $item['fontSize'] - (float) $neighbor['fontSize']) > max(1.5, $fontSize * 0.25)
                || abs((float) $item['y1'] - (float) $neighbor['y1']) > max(2.5, $fontSize * 0.35)
                || (float) $item['x1'] < (float) $neighbor['x1'] - max(2.0, $fontSize * 0.25)
                || (float) $item['x1'] > (float) $neighbor['x2'] + max(18.0, $fontSize * 2.0)) {
                continue;
            }

            return $columnIndex;
        }

        return null;
    }

    /**
     * Keep a visual line intact when a PDF paints it as multiple positioned
     * fragments. A fragment is eligible only when a stable body column and
     * the content-stream order both corroborate the local same-baseline
     * geometry. This deliberately never joins fragments assigned to separate
     * columns, even when their physical rows share a baseline.
     *
     * @param list<array<string, mixed>> $items
     * @param list<array{x: float, width: float, fontSize: float, count: int}> $columns
     * @return list<array<string, mixed>>
     */
    private function composePositionedPdfInlineFragmentsWithinStableColumns(array $items, array $columns): array
    {
        $columnIndexes = [];
        foreach ($items as $index => $item) {
            if (!$this->pdfLayoutHasGeometry($item) || ($item['code'] ?? false) === true) {
                continue;
            }
            $columnIndex = $this->sourcePdfColumnIndexForItem($item, $columns);
            if ($columnIndex !== null) {
                $columnIndexes[$index] = $columnIndex;
            }
        }

        // A short trailing styled fragment can start just beyond the normal
        // indent tolerance. Let its already-assigned same-line neighbor carry
        // the column identity, but only under the same bounded layout and
        // source-order evidence required for composition below.
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($items as $index => $item) {
                if (isset($columnIndexes[$index])
                    || !$this->pdfLayoutHasGeometry($item)
                    || ($item['code'] ?? false) === true) {
                    continue;
                }
                foreach ($columnIndexes as $neighborIndex => $columnIndex) {
                    $neighbor = $items[$neighborIndex] ?? null;
                    if (!is_array($neighbor)
                        || !$this->positionedPdfInlineFragmentsCanComposeWithinStableColumn(
                            $neighbor,
                            $item,
                            $columns[$columnIndex]
                        )) {
                        continue;
                    }
                    $columnIndexes[$index] = $columnIndex;
                    $changed = true;
                    break;
                }
            }
        }

        $groups = [];
        foreach ($columnIndexes as $index => $columnIndex) {
            $item = $items[$index];
            $fontSize = max(1.0, (float) $item['fontSize']);
            $baseline = ((float) $item['y1'] + (float) $item['y2']) / 2.0;
            $groupIndex = null;
            foreach ($groups[$columnIndex] ?? [] as $candidateIndex => $group) {
                if (abs($baseline - $group['baseline']) <= max(2.5, $fontSize * 0.35)) {
                    $groupIndex = $candidateIndex;
                    break;
                }
            }
            if ($groupIndex === null) {
                $groups[$columnIndex][] = [
                    'baseline' => $baseline,
                    'indexes' => [$index],
                ];
                continue;
            }
            $groups[$columnIndex][$groupIndex]['indexes'][] = $index;
        }

        $consumed = [];
        $composed = [];
        foreach ($groups as $columnIndex => $columnGroups) {
            foreach ($columnGroups as $group) {
                if (count($group['indexes']) < 2) {
                    continue;
                }
                $indexes = $group['indexes'];
                usort($indexes, fn (int $left, int $right): int => ((float) $items[$left]['x1'] <=> (float) $items[$right]['x1'])
                    ?: ((int) ($items[$left]['sourceOrderStart'] ?? 0) <=> (int) ($items[$right]['sourceOrderStart'] ?? 0)));

                $candidate = $items[$indexes[0]];
                $candidateIndexes = [$indexes[0]];
                for ($offset = 1, $count = count($indexes); $offset < $count; $offset++) {
                    $nextIndex = $indexes[$offset];
                    $next = $items[$nextIndex];
                    if (!$this->positionedPdfInlineFragmentsCanComposeWithinStableColumn(
                        $candidate,
                        $next,
                        $columns[$columnIndex]
                    )) {
                        if (count($candidateIndexes) > 1) {
                            $firstIndex = $candidateIndexes[0];
                            $composed[$firstIndex] = $candidate;
                            foreach (array_slice($candidateIndexes, 1) as $consumedIndex) {
                                $consumed[$consumedIndex] = true;
                            }
                        }
                        $candidate = $next;
                        $candidateIndexes = [$nextIndex];
                        continue;
                    }
                    $composedCandidate = $this->composePositionedPdfInlineLayoutFragments($candidate, $next);
                    if ($composedCandidate === null) {
                        if (count($candidateIndexes) > 1) {
                            $firstIndex = $candidateIndexes[0];
                            $composed[$firstIndex] = $candidate;
                            foreach (array_slice($candidateIndexes, 1) as $consumedIndex) {
                                $consumed[$consumedIndex] = true;
                            }
                        }
                        $candidate = $next;
                        $candidateIndexes = [$nextIndex];
                        continue;
                    }
                    $candidate = $composedCandidate;
                    $candidateIndexes[] = $nextIndex;
                }
                if (count($candidateIndexes) > 1) {
                    $firstIndex = $candidateIndexes[0];
                    $composed[$firstIndex] = $candidate;
                    foreach (array_slice($candidateIndexes, 1) as $consumedIndex) {
                        $consumed[$consumedIndex] = true;
                    }
                }
            }
        }

        if ($composed === []) {
            return $items;
        }

        $result = [];
        foreach ($items as $index => $item) {
            if (isset($consumed[$index])) {
                continue;
            }
            $result[] = $composed[$index] ?? $item;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $first
     * @param array<string, mixed> $second
     * @param array{x: float, width: float, fontSize: float, count: int} $column
     */
    private function positionedPdfInlineFragmentsCanComposeWithinStableColumn(
        array $first,
        array $second,
        array $column
    ): bool {
        if (!$this->pdfLayoutHasGeometry($first)
            || !$this->pdfLayoutHasGeometry($second)
            || ($first['code'] ?? false) === true
            || ($second['code'] ?? false) === true
            || !isset($first['sourceOrderStart'], $first['sourceOrderEnd'], $second['sourceOrderStart'])) {
            return false;
        }
        if ($this->positionedPdfFragmentSeparator($first, $second) === null) {
            return false;
        }

        if ((int) ($first['page'] ?? 0) !== (int) ($second['page'] ?? 0)) {
            return false;
        }

        $fontSize = max(1.0, (float) $first['fontSize'], (float) $second['fontSize'], $column['fontSize']);
        if (abs((float) $first['fontSize'] - (float) $second['fontSize']) > max(1.5, $fontSize * 0.25)) {
            return false;
        }

        $firstCenter = ((float) $first['y1'] + (float) $first['y2']) / 2.0;
        $secondCenter = ((float) $second['y1'] + (float) $second['y2']) / 2.0;
        if (abs($firstCenter - $secondCenter) > max(2.5, $fontSize * 0.35)) {
            return false;
        }

        $firstStart = (int) $first['sourceOrderStart'];
        $firstEnd = (int) $first['sourceOrderEnd'];
        $secondStart = (int) $second['sourceOrderStart'];
        if ($secondStart <= $firstEnd || $secondStart - $firstEnd > 48) {
            return false;
        }

        if ((float) $second['x1'] < (float) $first['x1'] - max(2.0, $fontSize * 0.25)) {
            return false;
        }

        $gap = (float) $second['x1'] - (float) $first['x2'];
        return $gap >= -max(24.0, $fontSize * 3.0)
            && $gap <= min(36.0, max(18.0, $column['width'] * 0.25));
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     * @return array<string, mixed>|null
     */
    private function composePositionedPdfInlineLayoutFragments(array $left, array $right): ?array
    {
        $leftText = rtrim((string) ($left['text'] ?? ''));
        $rightText = ltrim((string) ($right['text'] ?? ''));
        $separator = $this->positionedPdfFragmentSeparator($left, $right);
        if ($separator === null) {
            return null;
        }

        $leftTextX2 = (float) ($left['textX2'] ?? $left['x2'] ?? 0.0);
        $rightTextX1 = (float) ($right['textX1'] ?? $right['x1'] ?? 0.0);
        $fontSize = max(1.0, (float) ($left['fontSize'] ?? 0.0), (float) ($right['fontSize'] ?? 0.0));
        $sourceVerifiedBoundarySeparators = $this->positionedJoinedSourceVerifiedBoundarySeparators(
            $left,
            $right,
            $rightTextX1 - $leftTextX2,
            $fontSize,
            (bool) ($left['endsWithWhitespace'] ?? false),
            (bool) ($right['startsWithWhitespace'] ?? false),
            (bool) ($right['hasWordBoundaryBefore'] ?? false),
            (bool) ($right['wordBoundaryBefore'] ?? false),
            is_string($right['wordBoundarySource'] ?? null)
                ? $right['wordBoundarySource']
                : null
        );

        return array_replace($left, [
            'text' => $leftText
                . ($leftText !== '' && $rightText !== '' ? $separator : '')
                . $rightText,
            'x1' => min((float) $left['x1'], (float) $right['x1']),
            'y1' => min((float) $left['y1'], (float) $right['y1']),
            'x2' => max((float) $left['x2'], (float) $right['x2']),
            'y2' => max((float) $left['y2'], (float) $right['y2']),
            'textX1' => min((float) ($left['textX1'] ?? $left['x1']), (float) ($right['textX1'] ?? $right['x1'])),
            'textY1' => min((float) ($left['textY1'] ?? $left['y1']), (float) ($right['textY1'] ?? $right['y1'])),
            'textX2' => max((float) ($left['textX2'] ?? $left['x2']), (float) ($right['textX2'] ?? $right['x2'])),
            'textY2' => max((float) ($left['textY2'] ?? $left['y2']), (float) ($right['textY2'] ?? $right['y2'])),
            'fontSize' => $fontSize,
            'sourceOrderStart' => min((int) $left['sourceOrderStart'], (int) $right['sourceOrderStart']),
            'sourceOrderEnd' => max((int) $left['sourceOrderEnd'], (int) $right['sourceOrderEnd']),
            'startsWithWhitespace' => (bool) ($left['startsWithWhitespace'] ?? false),
            'endsWithWhitespace' => (bool) ($right['endsWithWhitespace'] ?? false),
            'hasWordBoundaryBefore' => (bool) ($left['hasWordBoundaryBefore'] ?? false),
            'wordBoundaryBefore' => (bool) ($left['wordBoundaryBefore'] ?? false),
            'wordBoundarySource' => $left['wordBoundarySource'] ?? null,
            'sourceVerifiedBoundarySeparators' => $sourceVerifiedBoundarySeparators,
            'sourceInlineLayoutComposite' => true,
        ]);
    }

    /**
     * A side figure can occupy the top of the next visual column while body
     * prose continues from the prior column below it. Move that continuation
     * ahead of the figure-side records only when an exact source/geometry
     * match proves both the line and its lower-case cross-column continuation.
     *
     * @param list<array<string, mixed>> $entries
     * @param list<array<string, mixed>>|null $previousColumnEntries
     * @return list<array<string, mixed>>
     */
    private function prioritizeSourcePdfVerifiedCrossColumnContinuation(array $entries, ?array $previousColumnEntries): array
    {
        if ($previousColumnEntries === null || $previousColumnEntries === []) {
            return $entries;
        }

        foreach ($entries as $index => $entry) {
            $predecessorIndex = $this->sourcePdfColumnContinuationPredecessorIndex(
                $previousColumnEntries,
                $entry
            );
            if (($entry['sourceVerifiedGeometryText'] ?? false) !== true
                || ($index !== 0
                    && ($entry['forceBlockBreakBefore'] ?? false) !== true
                    && !$this->sourcePdfEntryFollowsSideFigureInterruption($entries, $index))
                || $predecessorIndex === null) {
                continue;
            }

            unset(
                $entry['sourceInterruptedColumnRegion'],
                $entry['sourceUnresolvedInterruptedFlow'],
                $entry['sourceOrphanedInferredContinuation'],
                $entry['forceBlockBreakBefore']
            );
            $entry['sourceCrossColumnContinuation'] = true;
            $entry['sourceCrossColumnPredecessorIndex'] = $predecessorIndex;
            $entries[$index] = $entry;

            for ($continuationIndex = $index + 1; isset($entries[$continuationIndex]); $continuationIndex++) {
                $continuation = $entries[$continuationIndex];
                if (($continuation['page'] ?? null) !== ($entry['page'] ?? null)
                    || ($continuation['sourceGeometryColumn'] ?? null) !== ($entry['sourceGeometryColumn'] ?? null)
                    || ($continuation['sourceVerifiedGeometryText'] ?? false) !== true
                    || ($continuation['forceBlockBreakBefore'] ?? false) === true
                    || preg_match('/^[^\p{L}\p{N}]*\p{Ll}/u', ltrim((string) ($continuation['text'] ?? ''))) !== 1) {
                    break;
                }
                unset(
                    $continuation['sourceInterruptedColumnRegion'],
                    $continuation['sourceUnresolvedInterruptedFlow'],
                    $continuation['sourceOrphanedInferredContinuation']
                );
                $entries[$continuationIndex] = $continuation;
            }

            return array_merge(array_slice($entries, $index), array_slice($entries, 0, $index));
        }

        return $entries;
    }

    /**
     * A lower-case body line can continue an unfinished paragraph from the
     * previous column below a side figure or table. The large local gap makes
     * it a visual interruption rather than an ordinary wrapped line.
     *
     * @param list<array<string, mixed>> $entries
     */
    private function sourcePdfEntryFollowsSideFigureInterruption(array $entries, int $index): bool
    {
        if ($index < 1 || !isset($entries[$index - 1], $entries[$index])) {
            return false;
        }

        $previous = $entries[$index - 1];
        $current = $entries[$index];
        if (!$this->pdfLayoutHasGeometry($previous)
            || !$this->pdfLayoutHasGeometry($current)
            || ($previous['page'] ?? null) !== ($current['page'] ?? null)
            || ($previous['sourceGeometryColumn'] ?? null) !== ($current['sourceGeometryColumn'] ?? null)
            || ($previous['code'] ?? false) === true
            || ($current['code'] ?? false) === true
            || isset($previous['sourcePdfTableGroup'], $current['sourcePdfTableGroup'])
            || preg_match('/^[^\p{L}\p{N}]*\p{Ll}/u', ltrim((string) ($current['text'] ?? ''))) !== 1) {
            return false;
        }

        $fontSize = max(1.0, (float) $previous['fontSize'], (float) $current['fontSize']);
        $verticalGap = (float) $previous['y1'] - (float) $current['y1'];

        return $verticalGap >= max(24.0, $fontSize * 2.8);
    }

    /**
     * Some PDF readers retain source provenance only after a page has chosen
     * its final geometry path. Re-apply the verified continuation ordering at
     * that boundary so a side figure cannot separate a proven paragraph.
     *
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function prioritizeSourcePdfVerifiedCrossColumnContinuationPages(array $items): array
    {
        $pages = [];
        foreach ($items as $item) {
            $pages[(string) ($item['page'] ?? 0)][] = $item;
        }

        $ordered = [];
        foreach ($pages as $pageItems) {
            $columns = [];
            $other = [];
            foreach ($pageItems as $item) {
                if (($item['sourceStructuredGeometry'] ?? false) === true
                    && isset($item['sourceGeometryColumn'])
                    && ($item['sourceMinorFontFlow'] ?? false) !== true) {
                    $columns[(int) $item['sourceGeometryColumn']][] = $item;
                    continue;
                }
                $other[] = $item;
            }
            if (count($columns) < 2) {
                foreach ($pageItems as $item) {
                    $ordered[] = $item;
                }
                continue;
            }

            ksort($columns, SORT_NUMERIC);
            $previousColumnEntries = null;
            $previousColumn = null;
            $orderedColumns = [];
            $changed = false;
            foreach ($columns as $columnIndex => $entries) {
                $entries = $previousColumn !== null && $columnIndex === $previousColumn + 1
                    ? $this->prioritizeSourcePdfVerifiedCrossColumnContinuation($entries, $previousColumnEntries)
                    : $entries;
                $crossColumnContinuation = array_values(array_filter(
                    $entries,
                    static fn (array $entry): bool => ($entry['sourceCrossColumnContinuation'] ?? false) === true
                ));
                if ($crossColumnContinuation !== []
                    && is_array($previousColumnEntries)
                    && $previousColumnEntries !== []
                    && $orderedColumns !== []) {
                    $tailIndex = $crossColumnContinuation[0]['sourceCrossColumnPredecessorIndex']
                        ?? array_key_last($previousColumnEntries);
                    if (isset($previousColumnEntries[$tailIndex])) {
                        $tail = $previousColumnEntries[$tailIndex];
                        for ($leadIndex = $tailIndex; isset($previousColumnEntries[$leadIndex]); $leadIndex--) {
                            $lead = $previousColumnEntries[$leadIndex];
                            if (($lead['page'] ?? null) !== ($tail['page'] ?? null)
                                || ($lead['sourceGeometryColumn'] ?? null) !== ($tail['sourceGeometryColumn'] ?? null)) {
                                break;
                            }
                            $lead['sourceCrossColumnContinuationLead'] = true;
                            if ($leadIndex === $tailIndex) {
                                $lead['sourceCrossColumnContinuationTail'] = true;
                            }
                            $previousColumnEntries[$leadIndex] = $lead;
                            foreach ($orderedColumns as $orderedIndex => $orderedEntry) {
                                if (($orderedEntry['sourcePdfSourceIndex'] ?? null) === ($lead['sourcePdfSourceIndex'] ?? null)
                                    && ($orderedEntry['x1'] ?? null) === ($lead['x1'] ?? null)
                                    && ($orderedEntry['y1'] ?? null) === ($lead['y1'] ?? null)) {
                                    $orderedColumns[$orderedIndex] = $lead;
                                    break;
                                }
                            }
                            if ($leadIndex !== $tailIndex
                                && ($lead['forceBlockBreakBefore'] ?? false) === true) {
                                break;
                            }
                        }
                    }
                }
                foreach ($entries as $entry) {
                    $changed = $changed || (($entry['sourceCrossColumnContinuation'] ?? false) === true);
                    $orderedColumns[] = $entry;
                }
                $previousColumnEntries = $entries;
                $previousColumn = $columnIndex;
            }
            if (!$changed) {
                foreach ($pageItems as $item) {
                    $ordered[] = $item;
                }
                continue;
            }

            foreach ($orderedColumns as $item) {
                $ordered[] = $item;
            }
            foreach ($other as $item) {
                $ordered[] = $item;
            }
        }

        return $ordered;
    }

    /**
     * Short positioned text is useful only after source-only lines have been
     * attached to their neighboring geometry. At that point a real body lane
     * can corroborate the candidate; otherwise discard it rather than leaking
     * figure labels into prose.
     *
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function retainSourcePdfShortPositionedBodySupplements(array $items): array
    {
        $hasCandidates = false;
        foreach ($items as $item) {
            if (($item['sourceShortSupplementalCandidate'] ?? false) === true) {
                $hasCandidates = true;
                break;
            }
        }
        if (!$hasCandidates) {
            return $items;
        }

        $geometryItems = array_values(array_filter(
            $items,
            fn (array $item): bool => $this->pdfLayoutHasGeometry($item)
        ));
        $columns = $this->sourcePdfStableTextColumns($geometryItems);
        if (!$this->sourcePdfColumnsLookLikeParallelBodyColumns($columns)) {
            return array_values(array_filter(
                $items,
                static fn (array $item): bool => ($item['sourceShortSupplementalCandidate'] ?? false) !== true
            ));
        }

        $filtered = [];
        foreach ($items as $item) {
            if (($item['sourceShortSupplementalCandidate'] ?? false) !== true) {
                $filtered[] = $item;
                continue;
            }
            if ($this->sourcePdfShortSupplementalDuplicatesVisibleSourceItem($item, $items)) {
                continue;
            }
            if (!$this->positionedPdfShortLineCanSupplementMatchedBodyFlow($item, $items)
                && !$this->positionedPdfShortLineCanSupplementStableBodyColumn($item, $columns, $items)) {
                continue;
            }
            $item['sourceSupplementalBodyFlow'] = true;
            $filtered[] = $item;
        }

        return $filtered;
    }

    /**
     * A small positioned fragment can be part of the same visual row as a
     * complete source-matched line. In that situation it is a duplicate text
     * layer, not omitted body content, even if the surrounding body column is
     * otherwise strong enough to retain short positioned supplements.
     *
     * @param array<string, mixed> $supplement
     * @param list<array<string, mixed>> $items
     */
    private function sourcePdfShortSupplementalDuplicatesVisibleSourceItem(array $supplement, array $items): bool
    {
        if (!$this->pdfLayoutHasGeometry($supplement)) {
            return false;
        }
        $shortText = $this->pdfComparableLineText((string) ($supplement['text'] ?? ''));
        if ($this->length($shortText) < 8) {
            return false;
        }

        foreach ($items as $source) {
            if (($source['sourceSupplementalPositioned'] ?? false) === true
                || !$this->pdfLayoutHasGeometry($source)
                || ($source['page'] ?? null) !== ($supplement['page'] ?? null)
                || ($source['sourceGeometryColumn'] ?? null) !== ($supplement['sourceGeometryColumn'] ?? null)) {
                continue;
            }
            $sourceText = $this->pdfComparableLineText((string) ($source['text'] ?? ''));
            if ($this->length($sourceText) <= $this->length($shortText)
                || !str_contains($sourceText, $shortText)) {
                continue;
            }
            $fontSize = max(1.0, (float) $source['fontSize'], (float) $supplement['fontSize']);
            if (abs((float) $source['y1'] - (float) $supplement['y1']) > max(2.0, $fontSize * 0.40)
                || abs((float) $source['x1'] - (float) $supplement['x1']) > max(8.0, $fontSize)
                || (float) $supplement['x2'] > (float) $source['x2'] + max(8.0, $fontSize)) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * Footnotes and figure-side prose commonly use a smaller font than the
     * body column. Keep them as normal content when complete, but record their
     * lower confidence so an unfinished tail is not emitted as body prose.
     *
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function markSourcePdfMinorFontFlows(array $items, float $bodyFontSize): array
    {
        $threshold = max(1.0, $bodyFontSize * 0.93);
        foreach ($items as &$item) {
            if ($this->pdfLayoutHasGeometry($item)
                && (float) $item['fontSize'] < $threshold
                && !isset($item['sourcePdfTableGroup'])
                && ($item['code'] ?? false) !== true) {
                $item['sourceMinorFontFlow'] = true;
            }
        }
        unset($item);

        return $items;
    }

    /**
     * A page can contain a regular prose column beside a table, illustration,
     * or partially extractable formula. A broad vertical range between all
     * detached fragments is not evidence that the entire column is damaged.
     * Instead, mark only a body line that overlaps a detached fragment in the
     * same local visual band. The later flow check can then reject an
     * incomplete run without discarding unrelated prose above or below it.
     *
     * @param list<array<string, mixed>> $items
     * @param list<array<string, mixed>> $floating
     * @return list<array<string, mixed>>
     */
    private function markSourcePdfInterruptedColumnFragments(array $items, array $floating): array
    {
        foreach ($items as &$item) {
            if (isset($item['sourcePdfTableGroup'])
                || ($item['code'] ?? false) === true
                || !$this->pdfLayoutHasGeometry($item)) {
                continue;
            }
            foreach ($floating as $fragment) {
                if ($this->sourcePdfFloatingFragmentInterruptsColumnFlow($item, $fragment)) {
                    $item['sourceInterruptedColumnRegion'] = true;
                    break;
                }
            }
        }
        unset($item);

        return $items;
    }

    /**
     * @param array<string, mixed> $columnItem
     * @param array<string, mixed> $fragment
     */
    private function sourcePdfFloatingFragmentInterruptsColumnFlow(array $columnItem, array $fragment): bool
    {
        if (!$this->pdfLayoutHasGeometry($columnItem) || !$this->pdfLayoutHasGeometry($fragment)) {
            return false;
        }

        $fontSize = max(1.0, (float) $columnItem['fontSize'], (float) $fragment['fontSize']);
        $columnWidth = max(0.0, (float) $columnItem['x2'] - (float) $columnItem['x1']);
        if ($columnWidth < $fontSize * 10.0) {
            return false;
        }

        // A detached item can only interrupt a body line when it occupies the
        // same visual band. A multi-line tolerance turns a clipped text run
        // into false damage evidence for its neighboring prose lines.
        $columnCenter = ((float) $columnItem['y1'] + (float) $columnItem['y2']) / 2.0;
        $fragmentCenter = ((float) $fragment['y1'] + (float) $fragment['y2']) / 2.0;
        if (abs($columnCenter - $fragmentCenter) > max(4.0, $fontSize * 0.90)) {
            return false;
        }

        $horizontalTolerance = max(4.0, $fontSize * 0.75);
        return (float) $fragment['x2'] >= (float) $columnItem['x1'] - $horizontalTolerance
            && (float) $fragment['x1'] <= (float) $columnItem['x2'] + $horizontalTolerance;
    }

    /**
     * Once a page has stable body columns, text outside those anchors has no
     * reliable place in the prose flow. Retain only content that can stand on
     * its own structurally; table cells and code remain source-ordered and a
     * complete caption or display label remains useful independently.
     *
     * @param array<string, mixed> $item
     */
    private function sourcePdfFloatingItemCanStandAlone(array $item, float $medianFontSize): bool
    {
        if (isset($item['sourcePdfTableGroup']) || ($item['code'] ?? false) === true) {
            return true;
        }

        $text = trim((string) ($item['text'] ?? ''));
        if ($text === '') {
            return false;
        }
        if ($this->lineHasPdfListBlockEvidence($text) || $this->lineLooksLikeUrlOnly($text)) {
            return true;
        }
        if ($this->length($text) >= 64
            && count($this->pdfLineWordTokens($text)) >= 12
            && (($item['sourceUnmatchedFallback'] ?? false) === true
                || preg_match('/^[^\p{L}\p{N}]*\p{Lu}/u', $text) === 1)) {
            return true;
        }

        if ($this->pdfLayoutHasGeometry($item)
            && (float) $item['fontSize'] > $medianFontSize * 1.35
            && count($this->pdfLineWordTokens($text)) >= 2) {
            return true;
        }

        return $this->length($text) >= 48
            && count($this->pdfLineWordTokens($text)) >= 8
            && preg_match('/[.!?;:]\s*$/u', $text) === 1;
    }

    /**
     * In typeset prose, wrapped lines return to the column edge while a new
     * paragraph often begins with a small indent. Retain that visual boundary
     * even when the PDF text stream itself has no paragraph marker.
     *
     * @param list<array<string, mixed>> $items
     * @param array{x: float, width: float, fontSize: float, count: int} $column
     * @return list<array<string, mixed>>
     */
    private function markSourcePdfIndentedParagraphBoundaries(array $items, array $column): array
    {
        $count = count($items);
        for ($index = 1; $index < $count; $index++) {
            $previous = $items[$index - 1];
            $current = $items[$index];
            $text = ltrim((string) ($current['text'] ?? ''));
            if ($text === ''
                || ($current['code'] ?? false) === true
                || $this->lineHasPdfListBlockEvidence($text)
                || preg_match('/^[^\p{L}\p{N}]*\p{Lu}/u', $text) !== 1) {
                continue;
            }

            $fontSize = max((float) $previous['fontSize'], (float) $current['fontSize'], $column['fontSize'], 1.0);
            $step = (float) $previous['y1'] - (float) $current['y1'];
            if ($step < $fontSize * 0.30 || $step > $fontSize * 1.80) {
                continue;
            }

            $indent = (float) $current['x1'] - $column['x'];
            if ($indent < $fontSize * 0.80 || $indent > min(32.0, $column['width'] * 0.14)) {
                continue;
            }

            // A list marker can be painted in a glyph run that has no
            // usable text mapping. Its first text line then starts slightly
            // left of its wrapped hanging continuation. The preceding visual
            // gap already marks the list item boundary; do not split its
            // immediately following line into a second paragraph.
            $hangingOffset = (float) $current['x1'] - (float) $previous['x1'];
            if (($previous['forceBlockBreakBefore'] ?? false) === true
                && $hangingOffset >= $fontSize * 0.45
                && $hangingOffset <= $fontSize * 1.75
                && preg_match('/[.!?;:]\s*$/u', rtrim((string) ($previous['text'] ?? ''))) !== 1) {
                continue;
            }

            $items[$index]['forceBlockBreakBefore'] = true;
        }

        return $items;
    }

    /**
     * A stable body column should advance in a regular baseline rhythm. When
     * a new run begins after a clear gap with continuation punctuation or a
     * lower-case letter, the missing material is spatially observable: it is
     * not safe to make that run a new paragraph. Likewise, a wrapped hyphen
     * with no nearby continuation on the same page is a damaged flow rather
     * than a complete block.
     *
     * @param list<array<string, mixed>> $items
     * @param list<array<string, mixed>>|null $previousColumnItems
     * @param array{x: float, width: float, fontSize: float, count: int} $column
     * @return list<array<string, mixed>>
     */
    private function markSourcePdfOrphanedColumnFlows(
        array $items,
        ?array $previousColumnItems = null,
        array $column = []
    ): array
    {
        $count = count($items);
        for ($index = 0; $index < $count; $index++) {
            $item = $items[$index];
            $text = trim((string) ($item['text'] ?? ''));
            if ($text === ''
                || isset($item['sourcePdfTableGroup'])
                || ($item['code'] ?? false) === true
                || $this->lineHasPdfListBlockEvidence($text)
                || !$this->pdfLayoutHasGeometry($item)) {
                continue;
            }

            $previous = $index > 0 ? $items[$index - 1] : null;
            $startsWithOrphanPunctuation = preg_match('/^[,.;:\)\]\}]/u', $text) === 1;
            $startsParentheticalAfterGap = preg_match('/^\(/u', $text) === 1
                && ($previous === null || $this->sourcePdfColumnFlowHasUnexpectedGap($previous, $item));
            $startsUnsafeColumn = $index === 0
                && preg_match('/^[^\p{L}\p{N}]*\p{Ll}/u', $text) === 1
                && !$this->sourcePdfColumnLeadingItemContinuesPreviousColumn($previousColumnItems, $item);
            $startsForcedLowercaseFragment = ($item['forceBlockBreakBefore'] ?? false) === true
                && preg_match('/^[^\p{L}\p{N}]*\p{Ll}/u', $text) === 1;
            $startsWithUnexpectedMissingPrefix = $previous !== null
                && $this->sourcePdfColumnLineStartsWithUnexpectedMissingPrefix($previous, $item, $column);
            $followsEncodingNoise = $previous !== null
                && $this->lineIsOnlyPdfNoise((string) ($previous['text'] ?? ''))
                && preg_match('/^[^\p{L}\p{N}]*\p{Ll}/u', $text) === 1;
            // A lower-case visual continuation immediately after a line whose
            // geometry already proves an interruption belongs to that damaged
            // flow. Do not let a subsequent font-run reconciliation turn it
            // into an independent body paragraph.
            $followsInterruptedFlow = $previous !== null
                && ($previous['sourceInterruptedColumnRegion'] ?? false) === true
                && ($previous['sourceStream'] ?? null) === ($item['sourceStream'] ?? null)
                && ($previous['sourceGeometryColumn'] ?? null) === ($item['sourceGeometryColumn'] ?? null)
                && ($item['forceBlockBreakBefore'] ?? false) !== true
                && !$this->sourcePdfColumnFlowHasUnexpectedGap($previous, $item)
                && preg_match('/^[^\p{L}\p{N}]*\p{Ll}/u', $text) === 1;
            if ($startsWithOrphanPunctuation
                || $startsParentheticalAfterGap
                || $startsUnsafeColumn
                || $startsForcedLowercaseFragment
                || $startsWithUnexpectedMissingPrefix
                || $followsEncodingNoise
                || $followsInterruptedFlow
                || ($previous !== null
                    && $this->sourcePdfColumnFlowHasUnexpectedGap($previous, $item)
                    && preg_match('/^[^\p{L}\p{N}]*\p{Ll}/u', $text) === 1)) {
                $items[$index]['sourceInterruptedColumnRegion'] = true;
                if ($startsWithOrphanPunctuation
                    && $previous !== null
                    && !$this->sourcePdfColumnFlowHasUnexpectedGap($previous, $item)
                    && preg_match('/[.!?;:]\s*$/u', rtrim((string) ($previous['text'] ?? ''))) !== 1) {
                    $items[$index - 1]['sourceInterruptedColumnRegion'] = true;
                }
            }
            if ($startsWithUnexpectedMissingPrefix) {
                if ($previous !== null) {
                    $items[$index - 1]['sourceInterruptedColumnRegion'] = true;
                }
                $items[$index]['forceBlockBreakBefore'] = true;
            }

            if (preg_match('/[-\x{2010}-\x{2015}]\s*$/u', $text) !== 1) {
                continue;
            }
            $next = $items[$index + 1] ?? null;
            if ($next !== null && $this->sourcePdfColumnFlowHasUnexpectedGap($item, $next)) {
                $items[$index]['sourceInterruptedColumnRegion'] = true;
            }
        }

        return $items;
    }

    /**
     * In a regular text column, a lower-case wrapped line normally returns to
     * the column edge. A much larger left gap while the preceding line remains
     * unfinished means glyphs are missing from the beginning of this visual
     * line. It is not a first-line indent because that starts a new, normally
     * capitalized paragraph after a boundary.
     *
     * @param array<string, mixed> $previous
     * @param array<string, mixed> $current
     * @param array{x?: float, width?: float, fontSize?: float, count?: int} $column
     */
    private function sourcePdfColumnLineStartsWithUnexpectedMissingPrefix(
        array $previous,
        array $current,
        array $column
    ): bool {
        if (!$this->pdfLayoutHasGeometry($previous) || !$this->pdfLayoutHasGeometry($current)
            || !isset($column['x'])
            || ($previous['page'] ?? null) !== ($current['page'] ?? null)
            || ($previous['code'] ?? false) === true
            || ($current['code'] ?? false) === true) {
            return false;
        }

        $previousText = rtrim((string) ($previous['text'] ?? ''));
        $currentText = ltrim((string) ($current['text'] ?? ''));
        if ($previousText === '' || $currentText === ''
            || $this->lineHasPdfListBlockEvidence($previousText)
            || $this->lineHasPdfListBlockEvidence($currentText)
            || preg_match('/^[^\p{L}\p{N}]*\p{Ll}/u', $currentText) !== 1
            || preg_match('/[.!?;:]\s*$/u', $previousText) === 1) {
            return false;
        }

        $fontSize = max(
            1.0,
            (float) $previous['fontSize'],
            (float) $current['fontSize'],
            (float) ($column['fontSize'] ?? 1.0)
        );
        $step = (float) $previous['y1'] - (float) $current['y1'];
        if ($step < $fontSize * 0.30 || $step > $fontSize * 1.80) {
            return false;
        }

        $leftGap = (float) $current['x1'] - (float) $column['x'];
        $minimumGap = max(24.0, $fontSize * 2.5, (float) ($column['width'] ?? 0.0) * 0.08);

        // A line-end hyphen is only a safe continuation when the next line
        // returns to the body edge. A lower-case run displaced deep into the
        // column has lost its leading visual content instead.
        return $leftGap > $minimumGap;
    }

    /**
     * @param list<array<string, mixed>>|null $previousColumnItems
     * @param array<string, mixed> $item
     */
    private function sourcePdfColumnLeadingItemContinuesPreviousColumn(?array $previousColumnItems, array $item): bool
    {
        return $this->sourcePdfColumnContinuationPredecessorIndex($previousColumnItems, $item) !== null;
    }

    /**
     * A footnote, caption, or small diagram can be the last physical item in
     * a column even when an unfinished body sentence above it continues in
     * the next column. Find the nearest compatible body line rather than
     * assuming the literal last item is the paragraph tail.
     *
     * @param list<array<string, mixed>>|null $previousColumnItems
     */
    private function sourcePdfColumnContinuationPredecessorIndex(?array $previousColumnItems, array $item): ?int
    {
        if ($previousColumnItems === null
            || $previousColumnItems === []
            || !$this->pdfLayoutHasGeometry($item)
            || ($item['code'] ?? false) === true
            || isset($item['sourcePdfTableGroup'])
            || ($item['sourceVerifiedGeometryText'] ?? false) !== true
            || preg_match('/^[^\p{L}\p{N}]*\p{Ll}/u', ltrim((string) ($item['text'] ?? ''))) !== 1
            || count($this->pdfLineWordTokens((string) ($item['text'] ?? ''))) < 4) {
            return null;
        }

        foreach (array_reverse(array_keys($previousColumnItems)) as $index) {
            $previous = $previousColumnItems[$index];
            if (!$this->pdfLayoutHasGeometry($previous)
                || ($previous['page'] ?? null) !== ($item['page'] ?? null)
                || ($previous['code'] ?? false) === true
                || isset($previous['sourcePdfTableGroup'])
                || ($previous['sourceVerifiedGeometryText'] ?? false) !== true
                || $this->lineHasPdfListBlockEvidence((string) ($previous['text'] ?? ''))
                || preg_match('/[.!?;:]\s*$/u', rtrim((string) ($previous['text'] ?? ''))) === 1
                || count($this->pdfLineWordTokens((string) ($previous['text'] ?? ''))) < 4) {
                continue;
            }

            $fontSize = max(1.0, (float) $previous['fontSize'], (float) $item['fontSize']);
            $smallerFontSize = min((float) $previous['fontSize'], (float) $item['fontSize']);
            if ($smallerFontSize / $fontSize < 0.92
                || abs((float) $previous['fontSize'] - (float) $item['fontSize']) > max(1.25, $fontSize * 0.20)
                || (float) $item['y1'] - (float) $previous['y1'] <= max(24.0, $fontSize * 3.0)) {
                continue;
            }

            return (int) $index;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $upper
     * @param array<string, mixed> $lower
     */
    private function sourcePdfColumnFlowHasUnexpectedGap(array $upper, array $lower): bool
    {
        if (!$this->pdfLayoutHasGeometry($upper) || !$this->pdfLayoutHasGeometry($lower)
            || ($upper['page'] ?? null) !== ($lower['page'] ?? null)) {
            return false;
        }

        $fontSize = max(1.0, (float) $upper['fontSize'], (float) $lower['fontSize']);
        $step = (float) $upper['y1'] - (float) $lower['y1'];

        return $step > max(18.0, $fontSize * 2.6);
    }

    /**
     * @param list<array{x: float, width: float, fontSize: float, count: int}> $columns
     */
    private function sourcePdfColumnsLookLikeParallelBodyColumns(array $columns): bool
    {
        if (count($columns) < 2 || count($columns) > 4) {
            return false;
        }

        $widths = array_column($columns, 'width');
        $fontSize = max(array_column($columns, 'fontSize'));
        $minimumWidth = min($widths);
        $maximumWidth = max($widths);
        if ($minimumWidth < max(100.0, $fontSize * 12.0)
            || $maximumWidth / $minimumWidth > 1.30) {
            return false;
        }

        foreach ($columns as $index => $column) {
            if ($column['count'] < 8) {
                return false;
            }
            if (!isset($columns[$index + 1])) {
                continue;
            }

            $separation = $columns[$index + 1]['x'] - $column['x'];
            if ($separation < $minimumWidth * 0.65 || $separation > $maximumWidth * 1.65) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array{x: float, width: float, fontSize: float, count: int}>
     */
    private function sourcePdfStableTextColumns(array $items): array
    {
        $fontSizes = array_map(
            static fn (array $item): float => max(1.0, (float) $item['fontSize']),
            $items
        );
        $medianFontSize = max(1.0, $this->median($fontSizes));
        $startTolerance = max(18.0, $medianFontSize * 2.0);
        $groups = [];
        foreach ($items as $item) {
            $text = trim((string) ($item['text'] ?? ''));
            if (($item['code'] ?? false) === true
                || (float) $item['fontSize'] < $medianFontSize * 0.78
                || ($this->length($text) < 24 && count($this->pdfLineWordTokens($text)) < 5)) {
                continue;
            }

            $groupIndex = null;
            foreach ($groups as $index => $group) {
                if (abs((float) $item['x1'] - $group['x']) <= $startTolerance) {
                    $groupIndex = $index;
                    break;
                }
            }
            if ($groupIndex === null) {
                $groups[] = ['x' => (float) $item['x1'], 'items' => [$item]];
                continue;
            }
            $groups[$groupIndex]['items'][] = $item;
            $groups[$groupIndex]['x'] = $this->median(array_map(
                static fn (array $entry): float => (float) $entry['x1'],
                $groups[$groupIndex]['items']
            ));
        }

        $columns = [];
        foreach ($groups as $group) {
            $groupItems = $group['items'];
            if (count($groupItems) < 6) {
                continue;
            }
            usort($groupItems, static fn (array $left, array $right): int => (float) $right['y1'] <=> (float) $left['y1']);
            $rhythmPairs = 0;
            foreach ($groupItems as $index => $item) {
                if (!isset($groupItems[$index + 1])) {
                    break;
                }
                $next = $groupItems[$index + 1];
                $fontSize = max((float) $item['fontSize'], (float) $next['fontSize'], $medianFontSize, 1.0);
                $step = (float) $item['y1'] - (float) $next['y1'];
                if ($step >= $fontSize * 0.30 && $step <= $fontSize * 2.4) {
                    $rhythmPairs++;
                }
            }
            if ($rhythmPairs < max(3, (int) ceil((count($groupItems) - 1) * 0.35))) {
                continue;
            }

            $widths = array_map(
                static fn (array $item): float => max(1.0, (float) $item['x2'] - (float) $item['x1']),
                $groupItems
            );
            $width = $this->median($widths);
            if ($width < max(80.0, $medianFontSize * 12.0)) {
                continue;
            }
            $columns[] = [
                'x' => (float) $group['x'],
                'width' => $width,
                'fontSize' => $medianFontSize,
                'count' => count($groupItems),
            ];
        }

        usort($columns, static fn (array $left, array $right): int => $left['x'] <=> $right['x']);

        return $this->sourcePdfMergeIndentedColumnVariants($columns);
    }

    /**
     * A single text column can have a second recurring left edge for
     * first-line indents. Merge only nearby, similarly wide anchor groups so
     * that an indented paragraph does not become a phantom third column.
     *
     * @param list<array{x: float, width: float, fontSize: float, count: int}> $columns
     * @return list<array{x: float, width: float, fontSize: float, count: int}>
     */
    private function sourcePdfMergeIndentedColumnVariants(array $columns): array
    {
        $merged = [];
        foreach ($columns as $column) {
            $lastIndex = array_key_last($merged);
            if ($lastIndex === null) {
                $merged[] = $column;
                continue;
            }

            $last = $merged[$lastIndex];
            $minimumWidth = min($last['width'], $column['width']);
            $maximumWidth = max($last['width'], $column['width']);
            $indentTolerance = max(24.0, min(48.0, $minimumWidth * 0.16), max($last['fontSize'], $column['fontSize']) * 3.0);
            if ($column['x'] - $last['x'] > $indentTolerance || $maximumWidth / $minimumWidth > 1.35) {
                $merged[] = $column;
                continue;
            }

            $count = $last['count'] + $column['count'];
            $merged[$lastIndex] = [
                'x' => min($last['x'], $column['x']),
                'width' => max($last['width'], $column['width']),
                'fontSize' => (($last['fontSize'] * $last['count']) + ($column['fontSize'] * $column['count'])) / $count,
                'count' => $count,
            ];
        }

        return $merged;
    }

    /**
     * @param array<string, mixed> $item
     * @param list<array{x: float, width: float, fontSize: float, count: int}> $columns
     */
    private function sourcePdfColumnIndexForItem(array $item, array $columns): ?int
    {
        $bestIndex = null;
        $bestDistance = INF;
        foreach ($columns as $index => $column) {
            $fontSize = max((float) $item['fontSize'], $column['fontSize'], 1.0);
            $leftTolerance = max(16.0, $fontSize * 2.0);
            $indentTolerance = max(32.0, min(112.0, $column['width'] * 0.50), $fontSize * 4.0);
            $start = (float) $item['x1'];
            if ($start < $column['x'] - $leftTolerance || $start > $column['x'] + $indentTolerance) {
                continue;
            }
            $distance = abs($start - $column['x']);
            if ($distance < $bestDistance) {
                $bestIndex = $index;
                $bestDistance = $distance;
            }
        }

        return $bestIndex;
    }

    /**
     * @param array{page: int, stream: int, text: string} $left
     * @param array{page: int, stream: int, text: string} $right
     */
    private function sourcePdfItemsShareStream(array $left, array $right): bool
    {
        return $left['page'] === $right['page'] && $left['stream'] === $right['stream'];
    }

    private function sourcePdfLinesAreWrappedContinuation(string $previous, string $line): bool
    {
        if (preg_match('/[-\x{2010}-\x{2015}]\s*$/u', rtrim($previous)) === 1
            && preg_match('/^\s*[\p{L}\p{N}]/u', $line) === 1) {
            return true;
        }

        return preg_match('/[.!?]\s*$/u', rtrim($previous)) !== 1
            && preg_match('/^\s*\p{Ll}/u', $line) === 1;
    }

    /**
     * A short source-only phrase with an internal comma cannot stand alone
     * when the immediately following source line starts a capitalized visual
     * continuation. Infer its baseline from that known line rather than
     * dropping the phrase as a floating fragment. This deliberately excludes
     * terminal punctuation, labels, and list markers.
     */
    private function sourcePdfShortCommaLeadPrecedesKnownLayout(string $lead, string $following): bool
    {
        $lead = trim($lead);
        $following = ltrim($following);
        if ($lead === ''
            || $following === ''
            || $this->lineHasPdfListBlockEvidence($lead)
            || $this->lineLooksLikeUrlOnly($lead)
            || count($this->pdfLineWordTokens($lead)) > 4
            || $this->length($lead) > 42
            || preg_match('/^[^\p{L}\p{N}]*\p{Lu}/u', $lead) !== 1
            || preg_match('/,\s*[^\s]/u', $lead) !== 1
            || preg_match('/[.!?;:]\s*$/u', $lead) === 1) {
            return false;
        }

        return preg_match('/^[^\p{L}\p{N}]*\p{Lu}/u', $following) === 1;
    }

    /**
     * On a page where nearly all source text has a visual position, a short
     * unmatched lowercase fragment has no reliable place in reading order. It
     * is safer to omit that floating fragment than append it to an unrelated
     * column or figure caption. Uppercase labels, lists, URLs, and complete
     * sentences remain available to the normal fallback path.
     */
    private function sourcePdfUnmatchedLineLooksLikeFloatingFragment(string $line): bool
    {
        $line = trim($line);
        if ($line === ''
            || $this->lineHasPdfListBlockEvidence($line)
            || $this->lineLooksLikeUrlOnly($line)
            || preg_match('/[.!?;:]\s*$/u', $line) === 1
            || $this->length($line) > 28
            || count($this->pdfLineWordTokens($line)) > 5) {
            return false;
        }

        if (preg_match('/^\p{L}$/u', $line) === 1) {
            return true;
        }

        return preg_match('/^[^\p{L}]*\p{Ll}/u', $line) === 1
            || preg_match('/^\d+\s*[^\p{L}]?\s*\p{Ll}/u', $line) === 1;
    }

    /**
     * Once source lines have a reliable visual correspondence, an unplaced
     * fragment cannot safely be inserted into the reading order. Preserve
     * explicit structured content and independently complete prose, but keep
     * short or wrapped fragments out of the body flow.
     *
     * @param array<string, mixed> $item
     */
    private function sourcePdfUnmatchedFallbackCanStandAlone(array $item): bool
    {
        if (isset($item['sourcePdfTableGroup']) || ($item['code'] ?? false) === true) {
            return true;
        }

        $text = trim((string) ($item['text'] ?? ''));
        if ($this->lineLooksLikeUrlOnly($text) || $this->lineLooksLikeCompletePdfCaption($text)) {
            return true;
        }

        return $this->length($text) >= 48
            && count($this->pdfLineWordTokens($text)) >= 8
            && preg_match('/[.!?;:]\s*$/u', $text) === 1;
    }

    /**
     * @param array{page: int, stream: int, text: string} $sourceItem
     * @param array<string, mixed> $nextItem
     * @return array<string, mixed>
     */
    private function sourcePdfLineItemBeforeKnownLayout(array $sourceItem, array $nextItem): array
    {
        $layout = $this->sourcePdfInferredNeighborLayout($nextItem, 1.0);
        $item = $this->sourcePdfLineItem($sourceItem, $layout);
        $item['sourceInferredNeighborLayout'] = true;

        return $item;
    }

    /**
     * @param array{page: int, stream: int, text: string} $sourceItem
     * @param array<string, mixed> $previousItem
     * @return array<string, mixed>
     */
    private function sourcePdfLineItemAfterKnownLayout(array $sourceItem, array $previousItem): array
    {
        $layout = $this->sourcePdfInferredNeighborLayout($previousItem, -1.0);
        $item = $this->sourcePdfLineItem($sourceItem, $layout);
        $item['sourceInferredNeighborLayout'] = true;

        return $item;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function sourcePdfInferredNeighborLayout(array $item, float $direction): array
    {
        $height = max(1.0, (float) $item['y2'] - (float) $item['y1']);
        $step = max($height * 1.05, (float) $item['fontSize'] * 1.10, 1.0) * $direction;
        $item['y1'] += $step;
        $item['y2'] += $step;

        return $item;
    }

    /**
     * Matched source lines have clean text and usable visual bounds. A larger
     * than normal baseline step within one text column is a structural
     * paragraph boundary even when the source stream did not encode one.
     *
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function markPositionedPdfParagraphBoundaries(array $items): array
    {
        if (count($items) < 3) {
            return $items;
        }

        $baselineSteps = [];
        foreach ($items as $index => $item) {
            if (!isset($items[$index + 1]) || !$this->pdfLayoutHasGeometry($item) || !$this->pdfLayoutHasGeometry($items[$index + 1])) {
                continue;
            }
            $next = $items[$index + 1];
            $fontSize = max((float) $item['fontSize'], (float) $next['fontSize'], 1.0);
            if (abs((float) $item['x1'] - (float) $next['x1']) > max(16.0, $fontSize * 2.0)) {
                continue;
            }
            $step = (float) $item['y1'] - (float) $next['y1'];
            if ($step >= $fontSize * 0.35 && $step <= $fontSize * 2.4) {
                $baselineSteps[] = $step;
            }
        }
        if ($baselineSteps === []) {
            return $items;
        }

        $expectedStep = $this->median($baselineSteps);
        foreach ($items as $index => &$item) {
            if ($index === 0 || !$this->pdfLayoutHasGeometry($item) || !$this->pdfLayoutHasGeometry($items[$index - 1])) {
                continue;
            }
            $previous = $items[$index - 1];
            $fontSize = max((float) $item['fontSize'], (float) $previous['fontSize'], 1.0);
            $step = (float) $previous['y1'] - (float) $item['y1'];
            if ($step > max($expectedStep * 1.20, $fontSize * 1.25)) {
                $item['forceBlockBreakBefore'] = true;
            }
        }
        unset($item);

        // The first visible line of a list item may lose its marker when the
        // marker font has no usable Unicode mapping. A large gap rightly
        // starts that item, but a hanging continuation immediately below it
        // must not become a second forced paragraph boundary.
        for ($index = 1; $index < count($items); $index++) {
            $previous = $items[$index - 1];
            $current = $items[$index];
            if (($previous['forceBlockBreakBefore'] ?? false) !== true
                || ($current['forceBlockBreakBefore'] ?? false) !== true
                || !$this->pdfLayoutHasGeometry($previous)
                || !$this->pdfLayoutHasGeometry($current)
                || ($previous['page'] ?? null) !== ($current['page'] ?? null)
                || ($previous['sourceGeometryColumn'] ?? null) !== ($current['sourceGeometryColumn'] ?? null)
                || isset($previous['sourcePdfTableGroup'], $current['sourcePdfTableGroup'])
                || ($previous['code'] ?? false) === true
                || ($current['code'] ?? false) === true
                || preg_match('/[.!?;:]\s*$/u', rtrim((string) ($previous['text'] ?? ''))) === 1) {
                continue;
            }

            $fontSize = max(1.0, (float) $previous['fontSize'], (float) $current['fontSize']);
            $verticalStep = (float) $previous['y1'] - (float) $current['y1'];
            $hangingOffset = (float) $current['x1'] - (float) $previous['x1'];
            if ($verticalStep < $fontSize * 0.35
                || $verticalStep > $fontSize * 1.80
                || $hangingOffset < $fontSize * 0.45
                || $hangingOffset > $fontSize * 1.75
                || preg_match('/^[^\p{L}\p{N}]*\p{Lu}/u', ltrim((string) ($current['text'] ?? ''))) !== 1) {
                continue;
            }

            unset($items[$index]['forceBlockBreakBefore']);
        }

        return $items;
    }

    /**
     * @param list<array{page: int, stream: int, text: string}> $sourceItems
     * @param array<int, true> $matchedSourceIndexes
     */
    private function sourcePdfLineGeometryMatchIsReliable(array $sourceItems, array $matchedSourceIndexes): bool
    {
        $significantIndexes = [];
        $totalCharacters = 0;
        $matchedCharacters = 0;
        foreach ($sourceItems as $index => $item) {
            $length = $this->length($this->pdfComparableLineText($item['text']));
            if ($length < 8) {
                continue;
            }
            $significantIndexes[] = $index;
            $totalCharacters += $length;
            if (isset($matchedSourceIndexes[$index])) {
                $matchedCharacters += $length;
            }
        }
        if (count($significantIndexes) < 3 || $totalCharacters === 0) {
            return false;
        }

        $matchedLines = count(array_filter(
            $significantIndexes,
            static fn (int $index): bool => isset($matchedSourceIndexes[$index])
        ));

        return $matchedLines / count($significantIndexes) >= 0.75
            && $matchedCharacters / $totalCharacters >= 0.90;
    }

    private function pdfComparableLineText(string $text): string
    {
        $text = $this->normalizePdfTextEncoding($text);
        $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);

        return preg_replace('/[^\p{L}\p{N}]+/u', '', $text) ?? '';
    }

    /**
     * @param array{page: int, stream: int, text: string} $sourceItem
     * @param array<string, mixed>|null $positionedItem
     * @return array<string, mixed>
     */
    private function sourcePdfLineItem(
        array $sourceItem,
        ?array $positionedItem = null,
        bool $forceBlockBreakBefore = false,
        bool $usePositionedWhitespace = false
    ): array
    {
        $text = $sourceItem['text'];
        if ($usePositionedWhitespace && $positionedItem !== null) {
            $text = $this->sourcePdfLineTextWithPositionedWhitespace(
                $text,
                (string) ($positionedItem['text'] ?? '')
            );
        }
        $item = [
            'text' => $text,
            'page' => $sourceItem['page'],
            'sourceStream' => $sourceItem['stream'],
        ];
        if ($positionedItem !== null) {
            foreach (['x1', 'y1', 'x2', 'y2', 'fontSize', 'code', 'codeText', 'sourceOrderStart', 'sourceOrderEnd', 'sourceCompositePositionedFragments'] as $key) {
                if (array_key_exists($key, $positionedItem)) {
                    $item[$key] = $positionedItem[$key];
                }
            }
        }
        if ($forceBlockBreakBefore) {
            $item['forceBlockBreakBefore'] = true;
        }

        return $item;
    }

    /**
     * A short, repeated source line can be matched to the wrong visual copy
     * when columns reorder otherwise identical labels. Preserve its source
     * spelling unless neighboring matched records establish the same source
     * ordering. This gates only a local separator override; it does not alter
     * ordinary source/geometry matching or visual order.
     *
     * @param list<array{page: int, stream: int, text: string}> $sourceItems
     * @param array<string, mixed> $positionedItem
     * @param array<int, array<string, mixed>> $matchedItemsBySourceIndex
     */
    private function sourcePdfCanApplyProvenPositionedSeparators(
        array $sourceItems,
        int $sourceIndex,
        array $positionedItem,
        array $matchedItemsBySourceIndex
    ): bool {
        $sourceItem = $sourceItems[$sourceIndex] ?? null;
        if (!is_array($sourceItem)) {
            return false;
        }

        $compact = preg_replace('/\s+/u', '', $this->pdfExactLayoutText((string) $sourceItem['text'])) ?? '';
        if ($compact === '' || $this->length($compact) > 24) {
            return true;
        }

        $duplicateCount = 0;
        foreach ($sourceItems as $candidate) {
            if (($candidate['page'] ?? null) !== $sourceItem['page']
                || ($candidate['stream'] ?? null) !== $sourceItem['stream']) {
                continue;
            }
            $candidateCompact = preg_replace(
                '/\s+/u',
                '',
                $this->pdfExactLayoutText((string) ($candidate['text'] ?? ''))
            ) ?? '';
            if ($candidateCompact === $compact) {
                $duplicateCount++;
                if ($duplicateCount > 1) {
                    break;
                }
            }
        }
        if ($duplicateCount <= 1) {
            return true;
        }

        return $this->sourcePdfDuplicateCompactLineHasMatchedOrderAnchor(
            $sourceItems,
            $sourceIndex,
            $positionedItem,
            $matchedItemsBySourceIndex
        );
    }

    /**
     * @param list<array{page: int, stream: int, text: string}> $sourceItems
     * @param array<string, mixed> $positionedItem
     * @param array<int, array<string, mixed>> $matchedItemsBySourceIndex
     */
    private function sourcePdfDuplicateCompactLineHasMatchedOrderAnchor(
        array $sourceItems,
        int $sourceIndex,
        array $positionedItem,
        array $matchedItemsBySourceIndex
    ): bool {
        if (!isset($positionedItem['sourceOrderStart'], $positionedItem['sourceOrderEnd'])) {
            return false;
        }

        $sourceItem = $sourceItems[$sourceIndex] ?? null;
        if (!is_array($sourceItem)) {
            return false;
        }

        $start = (int) $positionedItem['sourceOrderStart'];
        $end = (int) $positionedItem['sourceOrderEnd'];
        foreach ([$sourceIndex - 1, $sourceIndex + 1] as $neighborIndex) {
            $neighborSource = $sourceItems[$neighborIndex] ?? null;
            $neighborItem = $matchedItemsBySourceIndex[$neighborIndex] ?? null;
            if (!is_array($neighborSource)
                || !is_array($neighborItem)
                || ($neighborSource['page'] ?? null) !== $sourceItem['page']
                || ($neighborSource['stream'] ?? null) !== $sourceItem['stream']
                || !isset($neighborItem['sourceOrderStart'], $neighborItem['sourceOrderEnd'])) {
                continue;
            }

            $neighborStart = (int) $neighborItem['sourceOrderStart'];
            $neighborEnd = (int) $neighborItem['sourceOrderEnd'];
            if (($neighborIndex < $sourceIndex && $neighborEnd < $start)
                || ($neighborIndex > $sourceIndex && $neighborStart > $end)) {
                return true;
            }
        }

        return false;
    }

    private function sourcePdfLineTextWithPositionedWhitespace(string $sourceText, string $positionedText): string
    {
        $sourceCompact = preg_replace('/\s+/u', '', $this->normalizePdfTextEncoding($sourceText)) ?? '';
        $positionedCompact = preg_replace('/\s+/u', '', $this->normalizePdfTextEncoding($positionedText)) ?? '';
        if ($sourceCompact === '' || $sourceCompact !== $positionedCompact) {
            return $sourceText;
        }

        return trim($positionedText);
    }

    /**
     * Restore only separator offsets that a positioned run proved at this
     * exact visual occurrence. The compact strings must agree byte-for-byte
     * after whitespace removal, so this cannot rewrite a similar word on a
     * different source line or replace the line wholesale with geometry text.
     *
     * @param array<int, string> $boundarySeparators Compact-character offset
     *                                                => required separator.
     */
    private function sourcePdfLineTextWithProvenPositionedSeparators(
        string $sourceText,
        string $positionedText,
        array $boundarySeparators
    ): string {
        if ($boundarySeparators === []) {
            return $sourceText;
        }

        $sourceCompact = preg_replace('/\s+/u', '', $this->pdfExactLayoutText($sourceText)) ?? '';
        $positionedCompact = preg_replace('/\s+/u', '', $this->pdfExactLayoutText($positionedText)) ?? '';
        if ($sourceCompact === '' || $sourceCompact !== $positionedCompact) {
            return $sourceText;
        }

        $separators = [];
        foreach ($boundarySeparators as $offset => $separator) {
            if (is_int($offset)
                && $offset > 0
                && $offset < $this->length($sourceCompact)
                && ($separator === '' || $separator === ' ')) {
                $separators[$offset] = $separator;
            }
        }
        if ($separators === []) {
            return $sourceText;
        }

        $characters = preg_split('//u', $sourceText, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($characters) || $characters === []) {
            return $sourceText;
        }

        $result = '';
        $pendingWhitespace = '';
        $compactOffset = 0;
        foreach ($characters as $character) {
            if (preg_match('/^\s$/u', $character) === 1) {
                $pendingWhitespace .= $character;
                continue;
            }

            if (array_key_exists($compactOffset, $separators)) {
                if ($separators[$compactOffset] === '') {
                    $pendingWhitespace = '';
                } elseif ($pendingWhitespace === '' && $result !== '') {
                    $result .= ' ';
                }
            }
            $result .= $pendingWhitespace . $character;
            $pendingWhitespace = '';
            $compactOffset++;
        }

        return $result . $pendingWhitespace;
    }

    /**
     * @param list<array{text: string, page?: int, code?: bool}> $items
     * @return list<string>
     */
    private function positionedCodeBlocksFromLineItems(array $items): array
    {
        $blocks = [];
        $lines = [];
        $page = null;
        $flush = static function () use (&$blocks, &$lines, &$page): void {
            $text = rtrim(implode("\n", $lines));
            if ($text !== '') {
                $blocks[] = $text;
            }
            $lines = [];
            $page = null;
        };

        foreach ($items as $item) {
            $itemPage = max(1, (int) ($item['page'] ?? 1));
            if (($item['code'] ?? false) !== true || ($page !== null && $page !== $itemPage)) {
                $flush();
            }
            if (($item['code'] ?? false) !== true) {
                continue;
            }

            $page = $itemPage;
            $lines[] = (string) $item['text'];
        }
        $flush();

        return $blocks;
    }

    /**
     * Geometry can identify a listing even when the ordinary PDF text stream
     * has better prose spacing. Replace only a span whose leading and trailing
     * token sequences match and whose complete token inventory substantially
     * agrees with the geometry-derived listing.
     *
     * @param list<string> $lines
     * @param list<string> $codeBlocks
     * @return list<string>
     */
    private function injectPositionedCodeBlocks(array $lines, array $codeBlocks): array
    {
        if ($lines === [] || $codeBlocks === []) {
            return $lines;
        }

        $document = implode("\n", $lines);
        $replacements = [];
        foreach ($codeBlocks as $index => $codeBlock) {
            $codeTokens = array_column($this->pdfCodeMatchTokens($codeBlock), 'token');
            if (count($codeTokens) < 24) {
                continue;
            }

            $documentTokens = $this->pdfCodeMatchTokens($document);
            $match = $this->matchingPdfCodeTokenSpan($documentTokens, $codeTokens);
            if ($match === null) {
                continue;
            }

            $placeholder = "\x1EPDF-CODE-ID:" . $index . "\x1F";
            $before = substr($document, 0, $match['start']);
            $after = substr($document, $match['end']);
            if ($before !== '' && !str_ends_with($before, "\n")) {
                $before = rtrim($before) . "\n";
            }
            if ($after !== '' && !str_starts_with($after, "\n")) {
                $after = "\n" . ltrim($after);
            }
            $document = $before . $placeholder . $after;
            $replacements[$placeholder] = self::PDF_CODE_BLOCK_PREFIX . rtrim($codeBlock);
        }

        if ($replacements === []) {
            return $lines;
        }

        $result = [];
        foreach (explode("\n", $document) as $line) {
            if (isset($replacements[$line])) {
                $result[] = $replacements[$line];
                continue;
            }
            $line = trim($line);
            if ($line !== '') {
                $result[] = $line;
            }
        }

        return $result;
    }

    /**
     * Protect a geometry-verified listing before prose cleanup can discard or
     * merge its source lines. This accepts only matches whose token span is
     * line-aligned in the source stream, so nearby prose cannot be replaced
     * by a code block merely because it shares a few identifiers.
     *
     * @param list<string> $lines
     * @param list<array<string, mixed>> $layouts
     * @param list<string> $codeBlocks
     * @return array{lines: list<string>, layouts: list<array<string, mixed>>, remainingCodeBlocks: list<string>}
     */
    private function injectPositionedCodeBlocksIntoRepairSource(
        array $lines,
        array $layouts,
        array $codeBlocks
    ): array {
        if ($lines === [] || $codeBlocks === []) {
            return [
                'lines' => $lines,
                'layouts' => $layouts,
                'remainingCodeBlocks' => $codeBlocks,
            ];
        }

        if (count($layouts) !== count($lines)) {
            $layouts = array_fill(0, count($lines), []);
        }

        $document = implode("\n", $lines);
        $documentTokens = $this->pdfCodeMatchTokens($document);
        $lineStarts = [];
        $offset = 0;
        foreach ($lines as $line) {
            $lineStarts[] = $offset;
            $offset += strlen($line) + 1;
        }

        $lineIndexAtOffset = static function (int $target) use ($lineStarts): ?int {
            $index = null;
            foreach ($lineStarts as $candidate => $start) {
                if ($start > $target) {
                    break;
                }
                $index = $candidate;
            }

            return $index;
        };

        $replacements = [];
        $remaining = [];
        foreach ($codeBlocks as $codeIndex => $codeBlock) {
            $codeTokens = array_column($this->pdfCodeMatchTokens($codeBlock), 'token');
            if (count($codeTokens) < 24) {
                $remaining[] = $codeBlock;
                continue;
            }
            $match = $this->matchingPdfCodeTokenSpan($documentTokens, $codeTokens);
            if ($match === null) {
                $remaining[] = $codeBlock;
                continue;
            }

            $startLine = $lineIndexAtOffset($match['start']);
            $endLine = $lineIndexAtOffset(max($match['start'], $match['end'] - 1));
            if ($startLine === null || $endLine === null || $startLine > $endLine) {
                $remaining[] = $codeBlock;
                continue;
            }

            $startColumn = $match['start'] - $lineStarts[$startLine];
            $endColumn = $match['end'] - $lineStarts[$endLine];
            $leading = substr($lines[$startLine], 0, max(0, $startColumn));
            $trailing = substr($lines[$endLine], max(0, $endColumn));
            if (trim($leading) !== '' || trim($trailing) !== '') {
                $remaining[] = $codeBlock;
                continue;
            }

            $overlaps = false;
            foreach ($replacements as $replacement) {
                if ($startLine <= $replacement['endLine'] && $endLine >= $replacement['startLine']) {
                    $overlaps = true;
                    break;
                }
            }
            if ($overlaps) {
                $remaining[] = $codeBlock;
                continue;
            }

            $replacements[] = [
                'startLine' => $startLine,
                'endLine' => $endLine,
                'text' => self::PDF_CODE_BLOCK_PREFIX . rtrim($codeBlock),
            ];
        }

        if ($replacements === []) {
            return [
                'lines' => $lines,
                'layouts' => $layouts,
                'remainingCodeBlocks' => $remaining,
            ];
        }

        usort($replacements, static fn (array $left, array $right): int => $right['startLine'] <=> $left['startLine']);
        foreach ($replacements as $replacement) {
            $length = $replacement['endLine'] - $replacement['startLine'] + 1;
            $codeText = substr($replacement['text'], strlen(self::PDF_CODE_BLOCK_PREFIX));
            array_splice($lines, $replacement['startLine'], $length, [$replacement['text']]);
            array_splice($layouts, $replacement['startLine'], $length, [[
                'code' => true,
                'codeText' => $codeText,
            ]]);
        }

        return [
            'lines' => $lines,
            'layouts' => $layouts,
            'remainingCodeBlocks' => $remaining,
        ];
    }

    /**
     * @return list<array{token: string, start: int, end: int}>
     */
    private function pdfCodeMatchTokens(string $text): array
    {
        if (preg_match_all('/[\p{L}\p{N}_$]+|[^\s\p{L}\p{N}_$]/u', $text, $matches, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }

        $tokens = [];
        foreach ($matches[0] as [$token, $offset]) {
            $normalized = function_exists('mb_strtolower')
                ? mb_strtolower($token, 'UTF-8')
                : strtolower($token);
            $tokens[] = [
                'token' => $normalized,
                'start' => (int) $offset,
                'end' => (int) $offset + strlen($token),
            ];
        }

        return $tokens;
    }

    /**
     * @param list<array{token: string, start: int, end: int}> $documentTokens
     * @param list<string> $codeTokens
     * @return array{start: int, end: int}|null
     */
    private function matchingPdfCodeTokenSpan(array $documentTokens, array $codeTokens): ?array
    {
        $codeTokenCount = count($codeTokens);
        if ($codeTokenCount < 24 || count($documentTokens) < $codeTokenCount * 0.60) {
            return null;
        }

        $anchorSize = min(10, max(6, (int) floor($codeTokenCount / 8)));
        $firstAnchor = array_slice($codeTokens, 0, $anchorSize);
        $lastAnchor = array_slice($codeTokens, -$anchorSize);
        $documentValues = array_column($documentTokens, 'token');
        $firstOffsets = $this->pdfTokenSequenceOffsets($documentValues, $firstAnchor);
        if ($firstOffsets === []) {
            return null;
        }

        $best = null;
        $bestScore = 0.0;
        foreach ($firstOffsets as $firstOffset) {
            $lastOffsets = $this->pdfTokenSequenceOffsets(
                $documentValues,
                $lastAnchor,
                $firstOffset + $anchorSize
            );
            foreach ($lastOffsets as $lastOffset) {
                $segmentEnd = $lastOffset + $anchorSize;
                $segmentTokens = array_slice($documentValues, $firstOffset, $segmentEnd - $firstOffset);
                $segmentTokenCount = count($segmentTokens);
                if ($segmentTokenCount < $codeTokenCount * 0.60 || $segmentTokenCount > $codeTokenCount * 1.65) {
                    if ($segmentTokenCount > $codeTokenCount * 1.65) {
                        break;
                    }
                    continue;
                }

                $score = $this->pdfTokenInventorySimilarity($codeTokens, $segmentTokens);
                if ($score < 0.78 || $score <= $bestScore) {
                    continue;
                }
                $bestScore = $score;
                $best = [
                    'start' => $documentTokens[$firstOffset]['start'],
                    'end' => $documentTokens[$segmentEnd - 1]['end'],
                ];
            }
        }

        return $best;
    }

    /**
     * @param list<string> $tokens
     * @param list<string> $sequence
     * @return list<int>
     */
    private function pdfTokenSequenceOffsets(array $tokens, array $sequence, int $start = 0): array
    {
        $offsets = [];
        $sequenceCount = count($sequence);
        $limit = count($tokens) - $sequenceCount;
        for ($index = max(0, $start); $index <= $limit; $index++) {
            for ($offset = 0; $offset < $sequenceCount; $offset++) {
                if ($tokens[$index + $offset] !== $sequence[$offset]) {
                    continue 2;
                }
            }
            $offsets[] = $index;
        }

        return $offsets;
    }

    /**
     * @param list<string> $expected
     * @param list<string> $actual
     */
    private function pdfTokenInventorySimilarity(array $expected, array $actual): float
    {
        $expectedCounts = array_count_values($expected);
        $actualCounts = array_count_values($actual);
        $overlap = 0;
        foreach ($expectedCounts as $token => $count) {
            $overlap += min($count, $actualCounts[$token] ?? 0);
        }
        if ($overlap === 0) {
            return 0.0;
        }

        $precision = $overlap / count($actual);
        $recall = $overlap / count($expected);

        return 2.0 * $precision * $recall / ($precision + $recall);
    }

    /**
     * @param list<array<string, mixed>> $runs
     * @return list<array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float}>
     */
    private function positionedProseLineItemsFromTextRuns(array $runs): array
    {
        if ($runs === []) {
            return [];
        }
        if (!$this->positionedTextRunsArePageOrdered($runs)) {
            return $this->positionedProseLineItemsFromRunsByPage(
                $this->positionedRunsByPageFromTextRuns($runs)
            );
        }

        $items = [];
        $pageRuns = [];
        $page = null;
        foreach ($this->positionedRunsWithLiteralWhitespaceProvenance($runs) as $normalized) {
            $runPage = (int) $normalized['page'];
            if ($page !== null && $runPage !== $page) {
                foreach ($this->positionedProseLineItemsForPage($pageRuns) as $item) {
                    $items[] = $item;
                }
                $pageRuns = [];
            }
            $page = $runPage;
            $pageRuns[] = $normalized;
        }
        foreach ($this->positionedProseLineItemsForPage($pageRuns) as $item) {
            $items[] = $item;
        }

        return $items;
    }

    /**
     * PDF page content contexts are emitted in page order. Keep an explicit
     * fallback for callers that provide synthetic or otherwise unordered
     * runs, where a page map is still needed to preserve the prior behavior.
     *
     * @param list<array<string, mixed>> $runs
     */
    private function positionedTextRunsArePageOrdered(array $runs): bool
    {
        $previousPage = 0;
        foreach ($runs as $run) {
            $page = max(1, (int) ($run['page'] ?? 1));
            if ($page < $previousPage) {
                return false;
            }
            $previousPage = $page;
        }

        return true;
    }

    /**
     * Normalize text-showing runs once before consumers derive prose lines,
     * spacing candidates, or table rows from their geometry.
     *
     * @param list<array<string, mixed>> $runs
     * @return array<int, list<array<string, mixed>>>
     */
    private function positionedRunsByPageFromTextRuns(array $runs): array
    {
        $runsByPage = [];
        foreach ($this->positionedRunsWithLiteralWhitespaceProvenance($runs) as $normalized) {
            $runsByPage[$normalized['page']][] = $normalized;
        }

        ksort($runsByPage);

        return $runsByPage;
    }

    /**
     * @param array<int, list<array<string, mixed>>> $runsByPage
     * @return list<array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float}>
     */
    private function positionedProseLineItemsFromRunsByPage(array $runsByPage): array
    {
        if ($runsByPage === []) {
            return [];
        }

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

        foreach ($runs as &$run) {
            $run['fontSize'] = max(
                1.0,
                (float) ($run['fontSize'] ?? 0.0),
                abs((float) ($run['y2'] ?? 0.0) - (float) ($run['y1'] ?? 0.0)) * 0.8
            );
        }
        unset($run);

        $fontSizes = array_map(static fn (array $run): float => $run['fontSize'], $runs);
        $medianFontSize = max(1.0, $this->median($fontSizes));
        $rowTolerance = max(3.0, $medianFontSize * 0.55);
        $rows = $this->clusterPositionedRows($runs, $rowTolerance);
        $rows = $this->mergePositionedProseRowFragments($rows, $this->positionedRowsBounds($rows));
        $rows = $this->markPositionedCodeListingRows($rows);
        $rows = $this->splitPositionedRowsIntoProseFragments($rows);
        $rows = $this->orderPositionedProseRows($rows, $medianFontSize);
        $rows = $this->mergeAdjacentPositionedProseRowsOnSameBaseline($rows);
        $rows = $this->markPositionedCodeListingRows($rows);

        $items = [];
        foreach ($rows as $row) {
            $line = $this->positionedRowText($row);
            if ($line !== '' && !$this->lineIsOnlyPdfNoise($line)) {
                $bounds = $this->positionedProseRowBounds($row);
                $firstRun = $row['runs'][0];
                $lastRun = $row['runs'][array_key_last($row['runs'])];
                $item = [
                    'text' => (string) ($row['codeText'] ?? $line),
                    'page' => (int) $row['runs'][0]['page'],
                    'x1' => $bounds['x1'],
                    'y1' => $bounds['y1'],
                    'x2' => $bounds['x2'],
                    'y2' => $bounds['y2'],
                    'fontSize' => $this->positionedRowMaxFontSize($row),
                    'code' => (bool) ($row['code'] ?? false),
                    'startsWithWhitespace' => (bool) ($firstRun['startsWithWhitespace'] ?? false),
                    'endsWithWhitespace' => (bool) ($lastRun['endsWithWhitespace'] ?? false),
                    'hasWordBoundaryBefore' => (bool) ($firstRun['hasWordBoundaryBefore'] ?? false),
                    'wordBoundaryBefore' => (bool) ($firstRun['wordBoundaryBefore'] ?? false),
                ];
                if (is_string($firstRun['wordBoundarySource'] ?? null)) {
                    $item['wordBoundarySource'] = $firstRun['wordBoundarySource'];
                }
                $sourceVerifiedBoundarySeparators = $this->positionedProseRowSourceVerifiedBoundarySeparators($row);
                if ($sourceVerifiedBoundarySeparators !== []) {
                    $item['sourceVerifiedBoundarySeparators'] = $sourceVerifiedBoundarySeparators;
                }
                $sourceOrder = $this->positionedProseRowSourceOrderBounds($row);
                if ($sourceOrder !== null) {
                    $item['sourceOrderStart'] = $sourceOrder['start'];
                    $item['sourceOrderEnd'] = $sourceOrder['end'];
                }
                $items[] = $item;
            }
        }

        return $this->filterPositionedPdfDecorativeItems($items, $medianFontSize);
    }

    /**
     * @param array{runs: list<array<string, mixed>>} $row
     * @return list<int>
     */
    private function positionedProseRowSourceVerifiedBoundarySeparators(array $row): array
    {
        $separators = [];
        $length = 0;
        foreach ($row['runs'] as $run) {
            foreach ($this->positionedSourceVerifiedBoundarySeparators($run) as $offset => $separator) {
                $separators[$length + $offset] = $separator;
            }
            $length += $this->positionedCompactTextLength((string) ($run['text'] ?? ''));
        }

        foreach ($separators as $offset => $separator) {
            if ($offset <= 0 || $offset >= $length || ($separator !== '' && $separator !== ' ')) {
                unset($separators[$offset]);
            }
        }
        ksort($separators, SORT_NUMERIC);

        return $separators;
    }

    /**
     * @param array{runs: list<array<string, mixed>>} $row
     * @return array{start: int, end: int}|null
     */
    private function positionedProseRowSourceOrderBounds(array $row): ?array
    {
        $start = null;
        $end = null;
        foreach ($row['runs'] as $run) {
            if (!isset($run['order'])) {
                return null;
            }
            $order = (int) $run['order'];
            $start = min($start ?? $order, $order);
            $end = max($end ?? $order, (int) ($run['lastOrder'] ?? $order));
        }

        return $start === null || $end === null ? null : ['start' => $start, 'end' => $end];
    }

    /**
     * A rotated or malformed display layer can yield a short, oversized text
     * item with an unmatched drawing delimiter. It is not body text and tends
     * to be duplicated by a correctly oriented text layer elsewhere on the
     * page, so exclude it before it can join surrounding prose.
     *
     * @param list<array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float, code: bool}> $items
     * @return list<array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float, code: bool}>
     */
    private function filterPositionedPdfDecorativeItems(array $items, float $medianFontSize): array
    {
        $filtered = [];
        foreach ($items as $item) {
            $text = trim($item['text']);
            $width = max(0.0, $item['x2'] - $item['x1']);
            $height = max(0.0, $item['y2'] - $item['y1']);
            $short = $this->length(preg_replace('/\s+/u', '', $text) ?? '') <= 18
                && count($this->pdfLineWordTokens($text)) <= 3;
            $large = $item['fontSize'] >= max(24.0, $medianFontSize * 4.5);
            $vertical = $height >= max($item['fontSize'] * 1.2, $width * 0.60);
            if (($item['code'] ?? false) !== true
                && $short
                && $large
                && $vertical
                && $this->pdfTextHasUnmatchedDrawingDelimiter($text)) {
                continue;
            }
            $filtered[] = $item;
        }

        return $filtered;
    }

    private function pdfTextHasUnmatchedDrawingDelimiter(string $text): bool
    {
        foreach ([['{', '}'], ['[', ']'], ['(', ')']] as [$open, $close]) {
            if (substr_count($text, $open) !== substr_count($text, $close)) {
                return true;
            }
        }

        return false;
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
            $sourceOrdered = $row['runs'];
            usort($sourceOrdered, static fn (array $left, array $right): int => ((int) ($left['order'] ?? 0)) <=> ((int) ($right['order'] ?? 0)));
            $merged = [];
            foreach ($sourceOrdered as $run) {
                $lastIndex = array_key_last($merged);
                if ($lastIndex === null) {
                    $merged[] = $run;
                    continue;
                }

                $last = $merged[$lastIndex];
                $gap = $run['textX1'] - $last['textX2'];
                $fontSize = max($run['fontSize'], $last['fontSize'], 1.0);
                $sourceOrderGap = (int) ($run['order'] ?? 0) - (int) ($last['lastOrder'] ?? $last['order'] ?? 0);
                $visualColumnBoundary = ($run['startsAfterTextBoundary'] ?? false) === true
                    || $sourceOrderGap > 8
                    || (($run['startsWithWhitespace'] ?? false) === true && $gap >= max(8.0, $fontSize * 0.75));
                $sameSourceLine = !$visualColumnBoundary
                    && $gap <= max(6.0, $fontSize)
                    && $gap >= -max(16.0, $fontSize * 1.5);
                // A monospaced listing often uses a deliberately wide grid
                // gap before an inline comment. Keep its two physical runs
                // separate until code-row detection can reconstruct the
                // columns. Otherwise a missing extractor boundary on "//"
                // collapses the gap into ordinary prose and hides the stable
                // pitch that identifies the listing.
                $codeColumnBoundary = !$visualColumnBoundary
                    && $this->positionedProseRunsSpanCodeColumns($last, $run, $gap, $pageWidth);
                $looksLikeLineContinuation = !$visualColumnBoundary
                    && !$codeColumnBoundary
                    && $this->positionedProseRunsLookLikeLineContinuation($last, $run, $gap, $pageWidth);
                if ($sameSourceLine || $looksLikeLineContinuation) {
                    $sourceVerifiedBoundarySeparators = $this->positionedJoinedSourceVerifiedBoundarySeparators(
                        $last,
                        $run,
                        $gap,
                        $fontSize,
                        (bool) ($last['endsWithWhitespace'] ?? false),
                        (bool) ($run['startsWithWhitespace'] ?? false),
                        (bool) ($run['hasWordBoundaryBefore'] ?? false),
                        (bool) ($run['wordBoundaryBefore'] ?? false),
                        is_string($run['wordBoundarySource'] ?? null)
                            ? $run['wordBoundarySource']
                            : null
                    );
                    $merged[$lastIndex] = [
                        'page' => $last['page'],
                        'text' => $this->joinPositionedCellText(
                            $last['text'],
                            $run['text'],
                            $gap,
                            $fontSize,
                            (bool) ($last['endsWithWhitespace'] ?? false),
                            (bool) ($run['startsWithWhitespace'] ?? false),
                            (bool) ($run['hasWordBoundaryBefore'] ?? false),
                            (bool) ($run['wordBoundaryBefore'] ?? false),
                            is_string($run['wordBoundarySource'] ?? null)
                                ? $run['wordBoundarySource']
                                : null
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
                        'hasWordBoundaryBefore' => (bool) ($last['hasWordBoundaryBefore'] ?? false),
                        'wordBoundaryBefore' => (bool) ($last['wordBoundaryBefore'] ?? false),
                        'wordBoundarySource' => $last['wordBoundarySource'] ?? null,
                        'sourceVerifiedBoundarySeparators' => $sourceVerifiedBoundarySeparators,
                        'startsAfterTextBoundary' => (bool) ($last['startsAfterTextBoundary'] ?? false),
                        'order' => min((int) ($last['order'] ?? 0), (int) ($run['order'] ?? 0)),
                        'lastOrder' => max((int) ($last['lastOrder'] ?? $last['order'] ?? 0), (int) ($run['lastOrder'] ?? $run['order'] ?? 0)),
                    ];
                    continue;
                }

                $merged[] = $run;
            }
            $merged = $this->removeOverprintedPositionedRuns($merged);
            usort($merged, static fn (array $left, array $right): int => ($left['x1'] <=> $right['x1']) ?: (((int) ($left['order'] ?? 0)) <=> ((int) ($right['order'] ?? 0))));
            $row['runs'] = $merged;
        }
        unset($row);

        return $rows;
    }

    /**
     * After column ordering, small styled text runs from one visual line are
     * adjacent again. Recombine only when their baselines and font metrics
     * agree and at least one run is too short to represent a full column line.
     *
     * @param list<array{center: float, runs: list<array<string, mixed>>}> $rows
     * @return list<array{center: float, runs: list<array<string, mixed>>}>
     */
    private function mergeAdjacentPositionedProseRowsOnSameBaseline(array $rows): array
    {
        $merged = [];
        foreach ($rows as $row) {
            $lastIndex = array_key_last($merged);
            if ($lastIndex === null || ($row['code'] ?? false) === true || ($merged[$lastIndex]['code'] ?? false) === true) {
                $merged[] = $row;
                continue;
            }

            $last = $merged[$lastIndex];
            $lastBounds = $this->positionedProseRowBounds($last);
            $rowBounds = $this->positionedProseRowBounds($row);
            $lastPage = (int) ($last['runs'][0]['page'] ?? 1);
            $rowPage = (int) ($row['runs'][0]['page'] ?? 1);
            $fontSize = max($this->positionedRowMaxFontSize($last), $this->positionedRowMaxFontSize($row), 1.0);
            $lastWidth = max(0.0, $lastBounds['x2'] - $lastBounds['x1']);
            $rowWidth = max(0.0, $rowBounds['x2'] - $rowBounds['x1']);
            $gap = $rowBounds['x1'] - $lastBounds['x2'];
            $sameBaseline = abs((float) $last['center'] - (float) $row['center']) <= max(2.5, $fontSize * 0.30);
            $nearby = $gap >= -max(2.0, $fontSize * 0.15) && $gap <= max(18.0, $fontSize * 1.5);
            $hasShortFragment = min($lastWidth, $rowWidth) <= $fontSize * 6.5;

            if ($lastPage !== $rowPage
                || !$sameBaseline
                || !$nearby
                || !$hasShortFragment
                || !$this->positionedProseRowsAreContiguousInSourceOrder($last, $row)) {
                $merged[] = $row;
                continue;
            }

            $runs = array_merge($last['runs'], $row['runs']);
            usort($runs, static fn (array $left, array $right): int => ($left['x1'] <=> $right['x1']) ?: (((int) ($left['order'] ?? 0)) <=> ((int) ($right['order'] ?? 0))));
            $merged[$lastIndex] = [
                'center' => ((float) $last['center'] + (float) $row['center']) / 2.0,
                'runs' => $runs,
            ];
        }

        return $merged;
    }

    /**
     * Same-baseline fragments are only one visual line when their content
     * stream runs are locally adjacent. A short formula label near a column
     * gutter otherwise looks like a styled continuation and can get joined to
     * unrelated body prose in the neighboring column.
     *
     * @param array{runs: list<array<string, mixed>>} $left
     * @param array{runs: list<array<string, mixed>>} $right
     */
    private function positionedProseRowsAreContiguousInSourceOrder(array $left, array $right): bool
    {
        $leftLast = null;
        foreach ($left['runs'] as $run) {
            if (!isset($run['order'])) {
                return true;
            }
            $leftLast = max($leftLast ?? (int) $run['order'], (int) ($run['lastOrder'] ?? $run['order']));
        }

        $rightFirst = null;
        foreach ($right['runs'] as $run) {
            if (!isset($run['order'])) {
                return true;
            }
            $rightFirst = min($rightFirst ?? (int) $run['order'], (int) $run['order']);
        }

        return $leftLast !== null
            && $rightFirst !== null
            && $rightFirst > $leftLast
            && $rightFirst - $leftLast <= 8;
    }

    /**
     * Monospaced listings expose a stable character pitch and baseline rhythm
     * even when the PDF does not carry semantic tags. Mark only sustained runs
     * that also contain generic programming punctuation; proportional prose
     * and ordinary aligned columns remain prose.
     *
     * @param list<array{center: float, runs: list<array<string, mixed>>}> $rows
     * @return list<array{center: float, runs: list<array<string, mixed>>}>
     */
    private function markPositionedCodeListingRows(array $rows): array
    {
        if (count($rows) < 4) {
            return $rows;
        }

        $signatures = array_map(fn (array $row): array => $this->positionedCodeRowSignature($row), $rows);
        $rowCount = count($rows);
        $index = 0;
        while ($index < $rowCount) {
            if ($signatures[$index]['pitch'] === null) {
                $index++;
                continue;
            }

            $end = $index;
            while ($end + 1 < $rowCount && $this->positionedCodeRowsShareBand($signatures[$end], $signatures[$end + 1])) {
                $end++;
            }

            $bandSignatures = array_slice($signatures, $index, $end - $index + 1);
            $firstCodeOffset = null;
            $lastCodeOffset = null;
            foreach ($bandSignatures as $offset => $signature) {
                if ($signature['codeEvidence'] < 1 || $signature['codeCoverage'] < 0.55) {
                    continue;
                }
                $firstCodeOffset ??= $offset;
                $lastCodeOffset = $offset;
            }
            if ($firstCodeOffset !== null && $lastCodeOffset !== null) {
                $candidateSignatures = array_slice(
                    $bandSignatures,
                    $firstCodeOffset,
                    $lastCodeOffset - $firstCodeOffset + 1
                );
            } else {
                $candidateSignatures = [];
            }
            if ($this->positionedBandLooksLikeCode($candidateSignatures)) {
                $pitch = $this->median(array_map(
                    static fn (array $signature): float => (float) $signature['pitch'],
                    $candidateSignatures
                ));
                $minimumX = min(array_map(
                    static fn (array $signature): float => (float) $signature['x1'],
                    $candidateSignatures
                ));
                $firstCodeRow = $index + $firstCodeOffset;
                $lastCodeRow = $index + $lastCodeOffset;
                for ($rowIndex = $firstCodeRow; $rowIndex <= $lastCodeRow; $rowIndex++) {
                    $rows[$rowIndex]['code'] = true;
                    $rows[$rowIndex]['codeText'] = $this->positionedCodeLineText($rows[$rowIndex], $minimumX, $pitch);
                }
            }

            $index = $end + 1;
        }

        return $rows;
    }

    /**
     * @param array{center: float, runs: list<array<string, mixed>>} $row
     * @return array{center: float, x1: float, y1: float, y2: float, fontSize: float, pitch: ?float, codeEvidence: int, codeCoverage: float}
     */
    private function positionedCodeRowSignature(array $row): array
    {
        $bounds = $this->positionedProseRowBounds($row);
        $pitches = [];
        foreach ($row['runs'] as $run) {
            $text = trim((string) ($run['text'] ?? ''));
            $characters = $this->length($text);
            $width = abs((float) ($run['textX2'] ?? $run['x2'] ?? 0.0) - (float) ($run['textX1'] ?? $run['x1'] ?? 0.0));
            if ($characters >= 2 && $width > 0.0) {
                $pitches[] = $width / $characters;
            }
        }

        $pitch = $pitches === [] ? null : $this->median($pitches);
        if ($pitch !== null && count($pitches) >= 2) {
            $consistent = 0;
            foreach ($pitches as $candidate) {
                if (abs($candidate - $pitch) <= max(0.35, $pitch * 0.18)) {
                    $consistent++;
                }
            }
            if ($consistent / count($pitches) < 0.65) {
                $pitch = null;
            }
        }

        $totalWidth = 0.0;
        $codeWidth = 0.0;
        $codeEvidence = 0;
        foreach ($row['runs'] as $run) {
            $text = trim((string) ($run['text'] ?? ''));
            $width = max(0.0, (float) ($run['textX2'] ?? $run['x2'] ?? 0.0) - (float) ($run['textX1'] ?? $run['x1'] ?? 0.0));
            $evidence = $this->positionedCodeSyntaxEvidence($text);
            $totalWidth += $width;
            $codeEvidence += $evidence;
            if ($evidence >= 2) {
                $codeWidth += $width;
            }
        }

        $rowEvidence = $this->positionedCodeSyntaxEvidence($this->positionedRowText($row));
        $codeCoverage = $totalWidth > 0.0 ? $codeWidth / $totalWidth : 0.0;
        if ($rowEvidence >= 2) {
            $codeCoverage = 1.0;
        }

        return [
            'center' => (float) $row['center'],
            'x1' => $bounds['x1'],
            'y1' => $bounds['y1'],
            'y2' => $bounds['y2'],
            'fontSize' => $this->positionedRowMaxFontSize($row),
            'pitch' => $pitch,
            'codeEvidence' => max($codeEvidence, $rowEvidence),
            'codeCoverage' => $codeCoverage,
        ];
    }

    /**
     * @param array{center: float, x1: float, y1: float, y2: float, fontSize: float, pitch: ?float, codeEvidence: int, codeCoverage: float} $upper
     * @param array{center: float, x1: float, y1: float, y2: float, fontSize: float, pitch: ?float, codeEvidence: int, codeCoverage: float} $lower
     */
    private function positionedCodeRowsShareBand(array $upper, array $lower): bool
    {
        if ($upper['pitch'] === null || $lower['pitch'] === null) {
            return false;
        }

        $fontSize = max($upper['fontSize'], $lower['fontSize'], 1.0);
        if (abs($upper['fontSize'] - $lower['fontSize']) > max(1.25, $fontSize * 0.18)) {
            return false;
        }
        $pitch = max($upper['pitch'], $lower['pitch'], 0.1);
        if (abs($upper['pitch'] - $lower['pitch']) > max(0.45, $pitch * 0.15)) {
            return false;
        }

        $height = max(
            1.0,
            $upper['y2'] - $upper['y1'],
            $lower['y2'] - $lower['y1'],
            $fontSize
        );
        $baselineGap = $upper['center'] - $lower['center'];

        return $baselineGap >= $height * 0.35 && $baselineGap <= $height * 2.2;
    }

    /**
     * @param list<array{center: float, x1: float, y1: float, y2: float, fontSize: float, pitch: ?float, codeEvidence: int, codeCoverage: float}> $signatures
     */
    private function positionedBandLooksLikeCode(array $signatures): bool
    {
        $count = count($signatures);
        if ($count < 6) {
            return false;
        }

        $evidenceRows = 0;
        $evidenceScore = 0;
        $pitches = [];
        foreach ($signatures as $signature) {
            $evidenceScore += $signature['codeEvidence'];
            if ($signature['codeEvidence'] >= 2 && $signature['codeCoverage'] >= 0.75) {
                $evidenceRows++;
            }
            if ($signature['pitch'] !== null) {
                $pitches[] = $signature['pitch'];
            }
        }
        if (count($pitches) !== $count) {
            return false;
        }

        $medianPitch = $this->median($pitches);
        $stablePitchRows = 0;
        foreach ($pitches as $pitch) {
            if (abs($pitch - $medianPitch) <= max(0.4, $medianPitch * 0.14)) {
                $stablePitchRows++;
            }
        }

        return $stablePitchRows / $count >= 0.80
            && $evidenceRows >= max(3, (int) ceil($count * 0.50))
            && $evidenceScore >= max(4, (int) ceil($count * 0.35));
    }

    private function positionedCodeSyntaxEvidence(string $line): int
    {
        $line = trim($line);
        if ($line === '') {
            return 0;
        }

        $score = 0;
        if (preg_match('~(?:^|\s)(?://|/\*|\*/|#(?:include|define|if|else|endif)\b)~u', $line) === 1) {
            $score += 2;
        }
        if (preg_match('/(?::=|===?|!==?|<=|>=|=>|->|\+\+|--|&&|\|\|)/u', $line) === 1) {
            $score += 2;
        }
        if (preg_match('/[{};]|\[[^\]]*\]/u', $line) === 1) {
            $score++;
        }
        if (preg_match('/^[A-Za-z_.$][A-Za-z0-9_.$-]*:\s*(?:(?:\/\/|#).*)?$/u', $line) === 1) {
            $score += 2;
        }
        if (preg_match('/[A-Za-z_.$][A-Za-z0-9_.$-]*\s*\([^)]*\)/u', $line) === 1
            && preg_match('/[.!?]\s*$/u', $line) !== 1) {
            $score++;
        }
        return $score;
    }

    /**
     * @param array{center: float, runs: list<array<string, mixed>>} $row
     */
    private function positionedCodeLineText(array $row, float $minimumX, float $pitch): string
    {
        $pitch = max(0.5, $pitch);
        $line = '';
        foreach ($row['runs'] as $run) {
            $text = trim((string) ($run['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $startX = (float) ($run['textX1'] ?? $run['x1'] ?? $minimumX);
            $targetColumn = max(0, (int) round(($startX - $minimumX) / $pitch));
            $currentColumn = $this->length($line);
            if ($line === '') {
                $line = str_repeat(' ', $targetColumn);
            } else {
                $paddingColumns = max(1, $targetColumn - $currentColumn);
                // Code runs are trimmed before their grid positions are
                // reconstructed. A reported source/layout boundary is an
                // omitted separator in addition to any measured blank grid
                // cells, so retain it locally rather than inferring anything
                // from the text in either column.
                if (($run['startsWithWhitespace'] ?? false) === true
                    || (($run['hasWordBoundaryBefore'] ?? false) === true
                        && ($run['wordBoundaryBefore'] ?? false) === true)) {
                    $paddingColumns++;
                }
                $line .= str_repeat(' ', $paddingColumns);
            }
            $line .= $text;
        }

        return rtrim($line);
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
        // The extractor records whether the actual positioned transition was
        // a word boundary. Never infer a continuation from letter case or
        // fragment length: ordinary adjacent letter fragments can represent
        // either one word or two words.
        if (($right['hasWordBoundaryBefore'] ?? false) !== true
            || ($right['wordBoundaryBefore'] ?? false) === true) {
            return false;
        }

        $fontSize = max($left['fontSize'], $right['fontSize'], 1.0);
        $maxContinuationGap = max($fontSize * 3.0, $pageWidth > 0.0 ? $pageWidth * 0.45 : 24.0);

        return $gap <= $maxContinuationGap;
    }

    /**
     * Preserve an aligned code/comment column boundary until the listing
     * classifier has enough rows to decide that it is code. This intentionally
     * uses only the two adjacent physical runs: source adjacency, a shared
     * character pitch, a grid-aligned gap, and programming punctuation. It
     * does not depend on the spelling, case, or length of ordinary words.
     *
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private function positionedProseRunsSpanCodeColumns(array $left, array $right, float $gap, float $pageWidth): bool
    {
        if ($gap <= 0.0
            || (int) ($left['page'] ?? 1) !== (int) ($right['page'] ?? 1)
            || !isset($left['lastOrder'], $right['order'])) {
            return false;
        }

        $sourceGap = (int) $right['order'] - (int) $left['lastOrder'];
        if ($sourceGap !== 1 || ($pageWidth > 0.0 && $gap > $pageWidth * 0.25)) {
            return false;
        }

        $leftPitch = $this->positionedRunCharacterPitch($left);
        $rightPitch = $this->positionedRunCharacterPitch($right);
        if ($leftPitch === null || $rightPitch === null) {
            return false;
        }

        $pitch = max($leftPitch, $rightPitch, 0.1);
        if (abs($leftPitch - $rightPitch) > max(0.35, $pitch * 0.12)) {
            return false;
        }

        $columns = $gap / $pitch;
        if ($columns < 1.5 || $columns > 24.0 || abs($columns - round($columns)) > 0.25) {
            return false;
        }

        return max(
            $this->positionedCodeSyntaxEvidence((string) ($left['text'] ?? '')),
            $this->positionedCodeSyntaxEvidence((string) ($right['text'] ?? '')),
            $this->positionedCodeSyntaxEvidence(
                trim((string) ($left['text'] ?? '')) . ' ' . trim((string) ($right['text'] ?? ''))
            )
        ) >= 2;
    }

    /**
     * @param array<string, mixed> $run
     */
    private function positionedRunCharacterPitch(array $run): ?float
    {
        $text = trim((string) ($run['text'] ?? ''));
        $characters = $this->length($text);
        $start = $this->numericValue($run['textX1'] ?? $run['x1'] ?? null);
        $end = $this->numericValue($run['textX2'] ?? $run['x2'] ?? null);
        if ($characters < 2 || $start === null || $end === null) {
            return null;
        }

        $width = abs($end - $start);

        return $width > 0.0 ? $width / $characters : null;
    }

    /**
     * @param list<array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>}> $rows
     * @return list<array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>}>
     */
    private function splitPositionedRowsIntoProseFragments(array $rows): array
    {
        $fragments = [];
        foreach ($rows as $row) {
            if (count($row['runs']) <= 1 || ($row['code'] ?? false) === true) {
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

        $left = $this->orderPositionedProseBand(
            $columns['left'],
            $this->positionedRowsBounds($columns['left']),
            $medianFontSize
        );
        $right = $this->orderPositionedProseBand(
            $columns['right'],
            $this->positionedRowsBounds($columns['right']),
            $medianFontSize
        );

        return array_merge($left, $right);
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

        $pageMidpoint = ($pageBounds['x1'] + $pageBounds['x2']) / 2.0;
        foreach ($this->positionedProseColumnCutCandidates($rows, $pageMidpoint, $medianFontSize) as $candidate) {
            $cut = $candidate['cut'];
            $left = [];
            $right = [];
            $leftStarts = [];
            $rightStarts = [];
            $leftEnds = [];
            foreach ($rows as $row) {
                $bounds = $this->positionedProseRowBounds($row);
                if ($bounds['x1'] < $cut) {
                    $left[] = $row;
                    $leftStarts[] = $bounds['x1'];
                    $leftEnds[] = $bounds['x2'];
                    continue;
                }

                $right[] = $row;
                $rightStarts[] = $bounds['x1'];
            }

            if (count($left) < 2 || count($right) < 2) {
                continue;
            }
            if ($candidate['gap'] > 0.0 && (!$this->positionedProseColumnHasVerticalRhythm($left, $medianFontSize)
                || !$this->positionedProseColumnHasVerticalRhythm($right, $medianFontSize)
                || !$this->positionedProseColumnHasStableStart($left, $medianFontSize)
                || !$this->positionedProseColumnHasStableStart($right, $medianFontSize))) {
                continue;
            }

            $startGap = $this->median($rightStarts) - $this->median($leftStarts);
            if ($startGap < max(80.0, $medianFontSize * 8.0)) {
                continue;
            }

            $gutter = $this->median($rightStarts) - $this->median($leftEnds);
            if ($gutter < -max(24.0, $medianFontSize * 2.0)) {
                continue;
            }

            return ['left' => $left, 'right' => $right];
        }

        return null;
    }

    /**
     * @param list<array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>}> $rows
     */
    private function positionedProseColumnHasVerticalRhythm(array $rows, float $medianFontSize): bool
    {
        if (count($rows) < 2) {
            return false;
        }

        usort($rows, static fn (array $left, array $right): int => $right['center'] <=> $left['center']);
        $nearbyPairs = 0;
        $pairs = count($rows) - 1;
        foreach ($rows as $index => $row) {
            if (!isset($rows[$index + 1])) {
                break;
            }
            $upper = $this->positionedProseRowBounds($row);
            $lower = $this->positionedProseRowBounds($rows[$index + 1]);
            $fontSize = max(
                $this->positionedRowMaxFontSize($row),
                $this->positionedRowMaxFontSize($rows[$index + 1]),
                $medianFontSize,
                1.0
            );
            $gap = $upper['y1'] - $lower['y2'];
            if ($gap >= -$fontSize * 0.5 && $gap <= $fontSize * 2.4) {
                $nearbyPairs++;
            }
        }

        return $nearbyPairs >= max(1, (int) ceil($pairs * 0.50));
    }

    /**
     * @param list<array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>}> $rows
     */
    private function positionedProseColumnHasStableStart(array $rows, float $medianFontSize): bool
    {
        if (count($rows) < 2) {
            return false;
        }

        $starts = array_map(
            fn (array $row): float => $this->positionedProseRowBounds($row)['x1'],
            $rows
        );
        $medianStart = $this->median($starts);
        $tolerance = max(12.0, $medianFontSize * 1.5);
        $stableStarts = 0;
        foreach ($starts as $start) {
            if (abs($start - $medianStart) <= $tolerance) {
                $stableStarts++;
            }
        }

        return $stableStarts / count($starts) >= 0.75;
    }

    /**
     * A page midpoint catches conventional two-column papers but misses
     * label-plus-body layouts whose two columns both sit on one half of a
     * brochure page. Repeated left edges expose their gutter without relying
     * on language or document-specific content.
     *
     * @param list<array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>}> $rows
     * @return list<array{cut: float, gap: float}>
     */
    private function positionedProseColumnCutCandidates(array $rows, float $pageMidpoint, float $medianFontSize): array
    {
        $starts = [];
        foreach ($rows as $row) {
            $starts[] = $this->positionedProseRowBounds($row)['x1'];
        }
        sort($starts, SORT_NUMERIC);

        $minimumGap = max(80.0, $medianFontSize * 8.0);
        $candidates = [['cut' => $pageMidpoint, 'gap' => 0.0]];
        for ($index = 0, $count = count($starts) - 1; $index < $count; $index++) {
            $gap = $starts[$index + 1] - $starts[$index];
            if ($gap < $minimumGap) {
                continue;
            }
            $candidates[] = [
                'cut' => ($starts[$index] + $starts[$index + 1]) / 2.0,
                'gap' => $gap,
            ];
        }

        usort($candidates, static fn (array $left, array $right): int => $right['gap'] <=> $left['gap']);
        $cuts = [];
        foreach ($candidates as $candidate) {
            $cut = (float) $candidate['cut'];
            foreach ($cuts as $existing) {
                if (abs($existing['cut'] - $cut) < 1.0) {
                    continue 2;
                }
            }
            $cuts[] = ['cut' => $cut, 'gap' => (float) $candidate['gap']];
        }

        return $cuts;
    }

    /**
     * @param array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>} $row
     * @param array{x1: float, y1: float, x2: float, y2: float} $pageBounds
     */
    private function positionedProseRowIsFullWidth(array $row, array $pageBounds, float $medianFontSize): bool
    {
        if (($row['code'] ?? false) === true) {
            return true;
        }

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
     * @param list<array<string, mixed>> $positionedRuns
     */
    private function positionedProseLinesLookUsable(
        array $positionedLines,
        array $textLines,
        array $positionedRuns = [],
        ?bool $positionedRunsAreGlyphFragments = null
    ): bool
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
        if (
            ($positionedRunsAreGlyphFragments
                ?? $this->positionedRunsArePredominantlyGlyphFragments($positionedRuns, count($textLines)))
            && $textSpacingDamage <= $positionedSpacingDamage + 1
        ) {
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
     * PDFs that paint nearly every glyph as an individual run can expose
     * imprecise placement order even when the ordinary text layer is coherent.
     * Keep geometry for actual words and phrases; otherwise prefer text order.
     *
     * @param list<array<string, mixed>> $runs
     */
    private function positionedRunsArePredominantlyGlyphFragments(array $runs, int $textLineCount): bool
    {
        if (count($runs) < 128 || $textLineCount < 24) {
            return false;
        }

        $runCount = 0;
        $singleGlyphRuns = 0;
        $shortRuns = 0;
        $characters = 0;
        foreach ($runs as $run) {
            $text = trim($this->normalizePdfTextEncoding((string) ($run['text'] ?? '')));
            if ($text === '') {
                continue;
            }

            $length = $this->length($text);
            $runCount++;
            $characters += $length;
            if ($length === 1) {
                $singleGlyphRuns++;
            }
            if ($length <= 2) {
                $shortRuns++;
            }
        }
        if ($runCount < 128 || $runCount / $textLineCount < 6.0) {
            return false;
        }

        return $singleGlyphRuns / $runCount >= 0.70
            && $shortRuns / $runCount >= 0.80
            && $characters / $runCount <= 1.75;
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
     * @return list<string>
     */
    private function repairProseTextLines(array $lines, bool $repairGluedText = true, array $lineLayouts = []): array
    {
        $cleaned = [];
        $pendingListMarker = null;
        foreach ($lines as $index => $line) {
            $layout = $lineLayouts[$index] ?? null;
            if (str_starts_with($line, self::PDF_CODE_BLOCK_PREFIX)) {
                $code = rtrim(substr($line, strlen(self::PDF_CODE_BLOCK_PREFIX)));
                if ($code !== '') {
                    $cleaned[] = [
                        'text' => $code,
                        'layout' => [
                            'code' => true,
                            'codeText' => $code,
                        ],
                    ];
                }
                continue;
            }
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
        $cleaned = $this->markPdfDisplayHeadingRecords($cleaned);
        $cleaned = $this->removeLowConfidencePdfReferenceEntries($cleaned);
        $cleaned = $this->removeLowCoherencePdfMapRegions($cleaned);
        $cleaned = $this->removeLowCoherencePdfFloatingRegions($cleaned);
        $cleaned = $this->removeLowCoherencePdfDiagramLabelRegions($cleaned);
        $cleaned = $this->removeLowCoherencePdfStructuredFloatingFragments($cleaned);
        $cleaned = $this->removeSourcePdfOrphanedInferredContinuations($cleaned);
        $cleaned = $this->removeLowCoherencePdfUnpositionedLabelRegions($cleaned);
        $cleaned = $this->removeIncompletePdfComplexColumnSegments($cleaned);
        $cleaned = $this->removeIncompletePdfInterruptedFlowSegments($cleaned);
        $cleaned = $this->trimIncompletePdfInterruptedSentenceTails($cleaned);
        $cleaned = $this->trimIncompletePdfSupplementalSegmentTails($cleaned);
        $cleaned = $this->trimIncompletePdfUnstructuredFragments($cleaned);
        $cleaned = $this->removeOrphanedPdfPageOpeningFragments($cleaned);
        $cleaned = $this->removeIsolatedPdfDiagramFlowFragments($cleaned);

        $merged = $this->mergeRepairedPdfRecords($cleaned);
        $merged = $this->trimIncompletePdfMergedLinesBeforeStackedTables($merged);
        $merged = $this->trimDanglingPdfHyphenatedParagraphTails($merged);
        $repaired = [];
        foreach ($merged as $line) {
            if (str_starts_with($line, self::PDF_CODE_BLOCK_PREFIX)) {
                $repaired[] = $line;
                continue;
            }
            if (str_starts_with($line, self::PDF_MAP_LABEL_PREFIX)) {
                $label = substr($line, strlen(self::PDF_MAP_LABEL_PREFIX));
                $label = $repairGluedText
                    ? $this->repairGluedProseLine($label)
                    : trim($label);
                if ($label !== '') {
                    $repaired[] = self::PDF_MAP_LABEL_PREFIX . $label;
                }
                continue;
            }
            if (str_starts_with($line, self::PDF_DISPLAY_HEADING_PREFIX)) {
                $heading = substr($line, strlen(self::PDF_DISPLAY_HEADING_PREFIX));
                $heading = $repairGluedText
                    ? $this->repairGluedProseLine($heading)
                    : trim($heading);
                if ($heading !== '') {
                    $repaired[] = self::PDF_DISPLAY_HEADING_PREFIX . $heading;
                }
                continue;
            }
            $line = $repairGluedText
                ? $this->repairGluedProseLine($line)
                : trim($line);
            if ($line !== '') {
                $repaired[] = $line;
            }
        }

        return $repaired;
    }

    /**
     * Preserve compact visual display labels as headings before text repair
     * discards their font information. This requires a substantial font-size
     * contrast within the same page, avoiding a lexical guess about ordinary
     * short prose or list introductions.
     *
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     * @return list<array{text: string, layout: array<string, mixed>|null}>
     */
    private function markPdfDisplayHeadingRecords(array $records): array
    {
        $fontSizesByPage = [];
        foreach ($records as $record) {
            $layout = $record['layout'];
            if (!$this->pdfLayoutHasGeometry($layout) || ($layout['code'] ?? false) === true) {
                continue;
            }
            $fontSizesByPage[(int) $layout['page']][] = max(1.0, (float) $layout['fontSize']);
        }

        $medianFontSizesByPage = [];
        foreach ($fontSizesByPage as $page => $fontSizes) {
            $medianFontSizesByPage[$page] = max(1.0, $this->median($fontSizes));
        }

        foreach ($records as &$record) {
            $layout = $record['layout'];
            $text = trim($record['text']);
            if (!$this->pdfLayoutHasGeometry($layout)
                || ($layout['code'] ?? false) === true
                || $text === ''
                || $this->lineHasPdfListBlockEvidence($text)
                || $this->lineLooksLikeUrlOnly($text)
                || $this->lineLooksLikePdfReferenceEntry($text)
                || count($this->pdfLineWordTokens($text)) > 6
                || $this->length($text) > 88) {
                continue;
            }

            $pageMedian = $medianFontSizesByPage[(int) $layout['page']] ?? 1.0;
            $fontSize = max(1.0, (float) $layout['fontSize']);
            if ($fontSize >= max(16.0, $pageMedian * 1.65)) {
                $record['layout']['sourcePdfDisplayHeading'] = true;
            }
        }
        unset($record);

        return $records;
    }

    /**
     * Stacked-table recognition happens after prose records are merged. The
     * record immediately before a confirmed table therefore has no table
     * boundary while it is being merged. Revisit only that structural shape
     * and drop an unresolved tail rather than presenting it as prose.
     *
     * @param list<string> $lines
     * @return list<string>
     */
    private function trimIncompletePdfMergedLinesBeforeStackedTables(array $lines): array
    {
        $trimmed = [];
        foreach ($lines as $index => $line) {
            $line = trim($line);
            $followingTable = isset($lines[$index + 1])
                && $this->stackedTableRowsAt($lines, $index + 1)['rows'] !== [];
            if (!$followingTable
                || str_starts_with($line, self::PDF_CODE_BLOCK_PREFIX)
                || str_starts_with($line, self::PDF_MAP_LABEL_PREFIX)
                || $this->lineHasPdfListBlockEvidence($line)
                || $this->lineLooksLikeUrlOnly($line)
                || $this->repairedLineLooksLikeSectionLabel($line)
                || preg_match('/[.!?;:]\s*$/u', $line) === 1
                || preg_match('/[-\x{00AD}\x{2010}-\x{2015}]\s*$/u', $line) === 1) {
                if ($line !== '') {
                    $trimmed[] = $line;
                }
                continue;
            }

            $prefix = $this->completePdfSentencePrefix($line);
            if ($prefix !== '' && count($this->pdfLineWordTokens($prefix)) >= 5) {
                $trimmed[] = $prefix;
                continue;
            }
            if (count($this->pdfLineWordTokens($line)) < 5 && $line !== '') {
                $trimmed[] = $line;
            }
        }

        return $trimmed;
    }

    /**
     * A line-end hyphen normally joins the next visual line. If no such
     * continuation survives, the paragraph ends with a visibly incomplete
     * word. Keep any preceding complete sentence and discard only the
     * unresolved tail; this is safer than inventing the missing glyphs.
     *
     * @param list<string> $lines
     * @return list<string>
     */
    private function trimDanglingPdfHyphenatedParagraphTails(array $lines): array
    {
        $trimmed = [];
        foreach ($lines as $line) {
            if (str_starts_with($line, self::PDF_CODE_BLOCK_PREFIX)
                || str_starts_with($line, self::PDF_MAP_LABEL_PREFIX)
                || preg_match('/[-\x{2010}]\s*$/u', rtrim($line)) !== 1) {
                $trimmed[] = $line;
                continue;
            }

            if (preg_match_all('/(?<!\p{Lu})[.!?](?=\s+\p{Lu}|$)/u', $line, $matches, PREG_OFFSET_CAPTURE) !== false
                && $matches[0] !== []) {
                $last = $matches[0][array_key_last($matches[0])];
                $prefix = trim(substr($line, 0, $last[1] + strlen($last[0])));
                if ($prefix !== '') {
                    $trimmed[] = $prefix;
                }
            }
        }

        return $trimmed;
    }

    /**
     * Figure captions and other free-positioned text can be emitted as one
     * source line even when a later visual run is missing. Unlike a body
     * column, there is no reliable line rhythm to reconstruct that omission.
     * Keep a verified sentence prefix, or omit the isolated fragment rather
     * than presenting a fabricated sentence.
     *
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     * @return list<array{text: string, layout: array<string, mixed>|null}>
     */
    private function trimIncompletePdfUnstructuredFragments(array $records): array
    {
        $filtered = [];
        foreach ($records as $index => $record) {
            $layout = is_array($record['layout'] ?? null) ? $record['layout'] : null;
            $text = trim($record['text']);
            $preceding = $records[$index - 1] ?? null;
            $following = $records[$index + 1] ?? null;
            if (!$this->pdfUnstructuredPdfFragmentNeedsTrimming($text, $layout, $preceding, $following)) {
                $filtered[] = $record;
                continue;
            }

            $prefix = $this->completePdfSentencePrefix($text);
            $hasGluedSentenceBoundary = preg_match('/(?<!\p{Lu})[.!?]\p{Lu}/u', $text) === 1;
            if (!$hasGluedSentenceBoundary && $prefix !== '' && count($this->pdfLineWordTokens($prefix)) >= 5) {
                $record['text'] = $prefix;
                $filtered[] = $record;
            }
        }

        return $filtered;
    }

    /**
     * @param array<string, mixed>|null $layout
     * @param array{text: string, layout: array<string, mixed>|null}|null $preceding
     * @param array{text: string, layout: array<string, mixed>|null}|null $following
     */
    private function pdfUnstructuredPdfFragmentNeedsTrimming(
        string $text,
        ?array $layout,
        ?array $preceding,
        ?array $following
    ): bool
    {
        $wordCount = count($this->pdfLineWordTokens($text));
        $unprovenShortLowercaseFragment = $this->pdfLayoutHasGeometry($layout)
            && ($layout['sourceStructuredGeometry'] ?? false) !== true
            && !isset($layout['sourceStream'], $layout['sourcePdfSourceIndex'])
            && $wordCount <= 2
            && preg_match('/^[^\p{L}\p{N}]*\p{Ll}/u', $text) === 1
            && !$this->lineHasPdfListBlockEvidence($text)
            && !$this->lineLooksLikeUrlOnly($text);
        if ($unprovenShortLowercaseFragment) {
            if ($this->pdfUnstructuredPdfFragmentHasCoherentNeighbor($layout, $preceding)
                || $this->pdfUnstructuredPdfFragmentHasCoherentNeighbor($layout, $following)) {
                return false;
            }
            if ($following === null) {
                return true;
            }
            $followingLayout = is_array($following['layout'] ?? null) ? $following['layout'] : null;
            if (!$this->pdfLayoutHasGeometry($followingLayout)
                || ($followingLayout['page'] ?? null) !== ($layout['page'] ?? null)) {
                return true;
            }
            $fontSize = max(1.0, (float) $layout['fontSize'], (float) $followingLayout['fontSize']);

            return abs((float) $layout['y1'] - (float) $followingLayout['y1']) > max(48.0, $fontSize * 5.0);
        }
        if (!$this->pdfLayoutHasGeometry($layout)
            || ($layout['sourceStructuredGeometry'] ?? false) === true
            || ($layout['code'] ?? false) === true
            || $text === ''
            || str_starts_with($text, self::PDF_MAP_LABEL_PREFIX)
            || $wordCount < 5
            || $this->lineHasPdfListBlockEvidence($text)
            || $this->lineLooksLikeUrlOnly($text)
            || $this->lineLooksLikeCompletePdfCaption($text)
            || $this->repairedLineLooksLikeSectionLabel($text)
            || preg_match('/[.!?;:]\s*$/u', $text) === 1) {
            return false;
        }

        if ($this->pdfUnstructuredPdfFragmentHasCoherentNeighbor($layout, $preceding)
            || $this->pdfUnstructuredPdfFragmentHasCoherentNeighbor($layout, $following)) {
            return false;
        }

        if ($following === null) {
            return ($layout['forceBlockBreakBefore'] ?? false) === true;
        }

        $followingLayout = is_array($following['layout'] ?? null) ? $following['layout'] : null;
        if (!$this->pdfLayoutHasGeometry($followingLayout)) {
            return false;
        }
        if (($followingLayout['page'] ?? null) !== ($layout['page'] ?? null)) {
            return true;
        }

        $fontSize = max(1.0, (float) $layout['fontSize'], (float) $followingLayout['fontSize']);

        return abs((float) $layout['y1'] - (float) $followingLayout['y1']) > max(48.0, $fontSize * 5.0);
    }

    /**
     * @param array<string, mixed> $layout
     * @param array{text: string, layout: array<string, mixed>|null}|null $neighbor
     */
    private function pdfUnstructuredPdfFragmentHasCoherentNeighbor(array $layout, ?array $neighbor): bool
    {
        $neighborLayout = is_array($neighbor['layout'] ?? null) ? $neighbor['layout'] : null;
        if (!$this->pdfLayoutHasGeometry($neighborLayout)
            || ($neighborLayout['sourceStructuredGeometry'] ?? false) === true
            || ($neighborLayout['code'] ?? false) === true
            || ($neighborLayout['page'] ?? null) !== ($layout['page'] ?? null)) {
            return false;
        }

        $fontSize = max(1.0, (float) $layout['fontSize'], (float) $neighborLayout['fontSize']);

        return abs((float) $layout['x1'] - (float) $neighborLayout['x1']) <= max(16.0, $fontSize * 1.5)
            && abs((float) $layout['y1'] - (float) $neighborLayout['y1']) <= max(24.0, $fontSize * 2.5);
    }

    private function completePdfSentencePrefix(string $text): string
    {
        if (preg_match_all('/(?<!\p{Lu})[.!?](?=\s+\p{Lu}|\p{Lu}|$)/u', $text, $matches, PREG_OFFSET_CAPTURE) === false
            || $matches[0] === []) {
            return '';
        }

        $last = $matches[0][array_key_last($matches[0])];

        return trim(substr($text, 0, $last[1] + strlen($last[0])));
    }

    /**
     * A dense page can contain a regular prose column plus text painted inside
     * a figure. Geometry identifies the prose column by recurring left edges
     * and a line-height rhythm. Short items outside that structure are kept
     * only when they look like a list, URL, heading, or a visibly larger label.
     *
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     * @return list<array{text: string, layout: array<string, mixed>|null}>
     */
    private function removeLowCoherencePdfFloatingRegions(array $records): array
    {
        $recordIndexesByPage = [];
        foreach ($records as $index => $record) {
            $layout = is_array($record['layout'] ?? null) ? $record['layout'] : null;
            if (!$this->pdfLayoutHasGeometry($layout)) {
                continue;
            }
            $recordIndexesByPage[(int) $layout['page']][] = $index;
        }

        $drop = [];
        foreach ($recordIndexesByPage as $indexes) {
            $pageRecords = array_map(static fn (int $index): array => $records[$index], $indexes);
            $medianFontSize = $this->median(array_map(
                static fn (array $record): float => max(1.0, (float) $record['layout']['fontSize']),
                $pageRecords
            ));
            foreach ($indexes as $index) {
                if ($this->pdfUnstructuredDisplayFragmentLooksIncomplete($records[$index], $medianFontSize)) {
                    $drop[$index] = true;
                }
            }
            $anchors = $this->pdfProseLayoutAnchors($pageRecords);
            if (count($anchors) < 2) {
                continue;
            }
            $longLines = 0;
            foreach ($pageRecords as $record) {
                if ($this->length(trim($record['text'])) >= 32) {
                    $longLines++;
                }
            }
            if ($longLines < max(6, (int) ceil(count($pageRecords) * 0.25))) {
                continue;
            }

            foreach ($indexes as $index) {
                $record = $records[$index];
                $layout = $record['layout'];
                $line = trim($record['text']);
                if (str_starts_with($line, self::PDF_MAP_LABEL_PREFIX)
                    || ($layout['code'] ?? false) === true
                    || ($layout['sourceFootnotePrefixedGeometry'] ?? false) === true
                    || $this->lineHasPdfListBlockEvidence($line)
                    || $this->lineLooksLikeUrlOnly($line)
                    || $this->lineLooksLikePdfAllCapsDisplayText($line)
                    || $this->lineLooksLikeCompletePdfCaption($line)
                    || $this->length($line) > 42
                    || count($this->pdfLineWordTokens($line)) > 7
                    || (float) $layout['fontSize'] > $medianFontSize * 1.35) {
                    continue;
                }

                $anchor = $this->nearestPdfProseLayoutAnchor($layout, $anchors);
                if ($anchor === null) {
                    continue;
                }
                $startTolerance = max(10.0, $medianFontSize * 1.25);
                $onAnchor = abs((float) $layout['x1'] - $anchor['x']) <= $startTolerance;
                $hasRhythmNeighbor = $this->pdfLayoutHasProseRhythmNeighbor(
                    $layout,
                    $pageRecords,
                    $anchor['x'],
                    $startTolerance,
                    $medianFontSize
                );
                if ($this->pdfRecordHasWrappedSourceContinuationAbove($record, $pageRecords, $medianFontSize)) {
                    continue;
                }
                if ($this->pdfRecordHasCoherentSourceContinuationBelow($record, $pageRecords, $medianFontSize)) {
                    continue;
                }
                if ($this->pdfRecordContinuesInlineTerminalLead($record, $pageRecords)) {
                    continue;
                }
                if (!$onAnchor || !$hasRhythmNeighbor) {
                    $drop[$index] = true;
                }
            }
        }

        if ($drop === []) {
            return $records;
        }

        return array_values(array_filter(
            $records,
            static fn (array $_record, int $index): bool => !isset($drop[$index]),
            ARRAY_FILTER_USE_BOTH
        ));
    }

    /**
     * Decorative PDF text can lose its first glyph while retaining a large
     * display fragment such as a lower-case word followed by a capitalized
     * word. It is not reliable prose or a complete label. Drop only this
     * unstructured oversized shape; ordinary headings retain their initial
     * capital and source-structured body text is never considered here.
     *
     * @param array{text: string, layout: array<string, mixed>|null} $record
     */
    private function pdfUnstructuredDisplayFragmentLooksIncomplete(array $record, float $medianFontSize): bool
    {
        $layout = is_array($record['layout'] ?? null) ? $record['layout'] : null;
        $line = trim((string) ($record['text'] ?? ''));
        if (!$this->pdfLayoutHasGeometry($layout)
            || ($layout['sourceStructuredGeometry'] ?? false) === true
            || ($layout['code'] ?? false) === true
            || $this->lineHasPdfListBlockEvidence($line)
            || $this->lineLooksLikeUrlOnly($line)
            || preg_match('/[.!?;:]\s*$/u', $line) === 1
            || count($this->pdfLineWordTokens($line)) < 2
            || count($this->pdfLineWordTokens($line)) > 4
            || $this->length($line) > 36
            || (float) $layout['fontSize'] < max(18.0, $medianFontSize * 2.20)) {
            return false;
        }

        return preg_match('/^[^\p{L}\p{N}]*\p{Ll}\p{L}*(?:\s+\p{Lu}\p{L}*)/u', $line) === 1;
    }

    /**
     * A display callout can place a short phrase to the right of a short
     * colon-ended lead, then continue on the lead's next baseline. The PDF
     * stream frequently paints that right-hand phrase later than the wrapped
     * continuation, so it looks like a floating fragment unless the baseline,
     * font, and narrow inline gap are considered together.
     *
     * @param array{text: string, layout: array<string, mixed>|null} $record
     * @param list<array{text: string, layout: array<string, mixed>|null}> $pageRecords
     */
    private function pdfRecordContinuesInlineTerminalLead(array $record, array $pageRecords): bool
    {
        $layout = is_array($record['layout'] ?? null) ? $record['layout'] : null;
        if (!$this->pdfLayoutHasGeometry($layout)) {
            return false;
        }

        foreach ($pageRecords as $candidate) {
            $candidateLayout = is_array($candidate['layout'] ?? null) ? $candidate['layout'] : null;
            if (!$this->pdfLayoutHasGeometry($candidateLayout)
                || (float) $candidateLayout['x1'] > (float) $layout['x1']) {
                continue;
            }
            if ($this->pdfLayoutsFormInlineTerminalLead(
                (string) ($candidate['text'] ?? ''),
                (string) ($record['text'] ?? ''),
                $candidateLayout,
                $layout
            )) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed>|null $leftLayout
     * @param array<string, mixed>|null $rightLayout
     */
    private function pdfLayoutsFormInlineTerminalLead(
        string $left,
        string $right,
        ?array $leftLayout,
        ?array $rightLayout
    ): bool {
        $left = trim($left);
        $right = ltrim($right);
        if (!$this->pdfLayoutHasGeometry($leftLayout)
            || !$this->pdfLayoutHasGeometry($rightLayout)
            || ($leftLayout['page'] ?? null) !== ($rightLayout['page'] ?? null)
            || ($leftLayout['code'] ?? false) === true
            || ($rightLayout['code'] ?? false) === true
            || $this->lineHasPdfListBlockEvidence($left)
            || $this->lineHasPdfListBlockEvidence($right)
            || count($this->pdfLineWordTokens($left)) > 4
            || count($this->pdfLineWordTokens($right)) > 4
            || preg_match('/:\s*$/u', $left) !== 1
            || preg_match('/^[^\p{L}\p{N}]*\p{Lu}/u', $right) !== 1) {
            return false;
        }

        $fontSize = max(1.0, (float) $leftLayout['fontSize'], (float) $rightLayout['fontSize']);
        if (abs((float) $leftLayout['fontSize'] - (float) $rightLayout['fontSize']) > max(1.5, $fontSize * 0.22)) {
            return false;
        }
        $leftCenter = ((float) $leftLayout['y1'] + (float) $leftLayout['y2']) / 2.0;
        $rightCenter = ((float) $rightLayout['y1'] + (float) $rightLayout['y2']) / 2.0;
        $gap = (float) $rightLayout['x1'] - (float) $leftLayout['x2'];

        return abs($leftCenter - $rightCenter) <= max(2.5, $fontSize * 0.35)
            && (float) $rightLayout['x1'] >= (float) $leftLayout['x1']
            && $gap >= -max(2.0, $fontSize * 0.20)
            && $gap <= max(18.0, $fontSize * 1.5);
    }

    private function lineLooksLikePdfAllCapsDisplayText(string $line): bool
    {
        $words = $this->pdfLineWordTokens($line);
        if (count($words) < 2) {
            return false;
        }
        foreach ($words as $word) {
            if (preg_match('/^\p{Lu}[\p{Lu}\p{N}.-]*$/u', $word) !== 1) {
                return false;
            }
        }

        return true;
    }

    private function lineLooksLikeCompletePdfCaption(string $line): bool
    {
        return count($this->pdfLineWordTokens($line)) >= 4
            && preg_match('/[.!?]\s*$/u', trim($line)) === 1;
    }

    /**
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     * @return list<array{x: float, count: int}>
     */
    private function pdfProseLayoutAnchors(array $records): array
    {
        if (count($records) < 12) {
            return [];
        }
        $fontSizes = array_map(
            static fn (array $record): float => max(1.0, (float) $record['layout']['fontSize']),
            $records
        );
        $medianFontSize = $this->median($fontSizes);
        $startTolerance = max(10.0, $medianFontSize * 1.25);
        usort($records, static fn (array $left, array $right): int => $left['layout']['x1'] <=> $right['layout']['x1']);

        $groups = [];
        foreach ($records as $record) {
            $start = (float) $record['layout']['x1'];
            $groupIndex = null;
            foreach ($groups as $index => $group) {
                if (abs($start - $group['x']) <= $startTolerance) {
                    $groupIndex = $index;
                    break;
                }
            }
            if ($groupIndex === null) {
                $groups[] = ['x' => $start, 'records' => [$record]];
                continue;
            }
            $groups[$groupIndex]['records'][] = $record;
            $groups[$groupIndex]['x'] = $this->median(array_map(
                static fn (array $entry): float => (float) $entry['layout']['x1'],
                $groups[$groupIndex]['records']
            ));
        }

        $anchors = [];
        foreach ($groups as $group) {
            $groupRecords = $group['records'];
            if (count($groupRecords) < 4) {
                continue;
            }
            usort($groupRecords, static fn (array $left, array $right): int => $right['layout']['y1'] <=> $left['layout']['y1']);
            $rhythmPairs = 0;
            foreach ($groupRecords as $index => $record) {
                if (!isset($groupRecords[$index + 1])) {
                    break;
                }
                $upper = $record['layout'];
                $lower = $groupRecords[$index + 1]['layout'];
                $fontSize = max((float) $upper['fontSize'], (float) $lower['fontSize'], $medianFontSize, 1.0);
                $step = (float) $upper['y1'] - (float) $lower['y1'];
                if ($step >= $fontSize * 0.30 && $step <= $fontSize * 3.0) {
                    $rhythmPairs++;
                }
            }
            if ($rhythmPairs < 2) {
                continue;
            }
            $anchors[] = ['x' => (float) $group['x'], 'count' => count($groupRecords)];
        }

        return $anchors;
    }

    /**
     * @param array<string, mixed> $layout
     * @param list<array{x: float, count: int}> $anchors
     * @return array{x: float, count: int}|null
     */
    private function nearestPdfProseLayoutAnchor(array $layout, array $anchors): ?array
    {
        $nearest = null;
        $distance = INF;
        foreach ($anchors as $anchor) {
            $candidateDistance = abs((float) $layout['x1'] - $anchor['x']);
            if ($candidateDistance < $distance) {
                $nearest = $anchor;
                $distance = $candidateDistance;
            }
        }

        return $nearest;
    }

    /**
     * @param array<string, mixed> $layout
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     */
    private function pdfLayoutHasProseRhythmNeighbor(
        array $layout,
        array $records,
        float $anchorX,
        float $startTolerance,
        float $medianFontSize
    ): bool {
        foreach ($records as $record) {
            $candidate = $record['layout'];
            if ($candidate === $layout || abs((float) $candidate['x1'] - $anchorX) > $startTolerance) {
                continue;
            }
            $fontSize = max((float) $candidate['fontSize'], (float) $layout['fontSize'], $medianFontSize, 1.0);
            $distance = abs((float) $candidate['y1'] - (float) $layout['y1']);
            if ($distance >= $fontSize * 0.30 && $distance <= $fontSize * 3.0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Some PDFs indent the first line of a text run differently from its
     * wrapped continuation. Retain a short lowercase continuation when a
     * substantially longer source-stream line sits directly above it, even if
     * the continuation begins at a different visual edge.
     *
     * @param array{text: string, layout: array<string, mixed>|null} $record
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     */
    private function pdfRecordHasWrappedSourceContinuationAbove(array $record, array $records, float $medianFontSize): bool
    {
        $layout = $record['layout'];
        if (!$this->pdfLayoutHasGeometry($layout)
            || !isset($layout['sourceStream'])
            || preg_match('/^\s*\p{Ll}/u', $record['text']) !== 1) {
            return false;
        }
        $lineLength = max(1, $this->length(trim($record['text'])));
        foreach ($records as $candidate) {
            $candidateLayout = $candidate['layout'];
            if (!$this->pdfLayoutHasGeometry($candidateLayout)
                || ($candidateLayout['sourceStream'] ?? null) !== $layout['sourceStream']) {
                continue;
            }
            $fontSize = max((float) $candidateLayout['fontSize'], (float) $layout['fontSize'], $medianFontSize, 1.0);
            $step = (float) $candidateLayout['y1'] - (float) $layout['y1'];
            if ($step < $fontSize * 0.30 || $step > $fontSize * 3.0) {
                continue;
            }
            if ($this->length(trim($candidate['text'])) < max(42, $lineLength * 1.5)
                || preg_match('/[.!?]\s*$/u', trim($candidate['text'])) === 1) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * A source run can start after a styled heading or an omitted glyph run
     * and therefore have an indented visual left edge. Keep that short run
     * when the next source line proves it continues into the ordinary body
     * rhythm. This is stronger than treating every off-anchor short line as a
     * figure label.
     *
     * @param array{text: string, layout: array<string, mixed>|null} $record
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     */
    private function pdfRecordHasCoherentSourceContinuationBelow(array $record, array $records, float $medianFontSize): bool
    {
        $layout = $record['layout'];
        $text = trim($record['text']);
        if (!$this->pdfLayoutHasGeometry($layout)
            || !isset($layout['sourceStream'], $layout['sourcePdfSourceIndex'])
            || $text === ''
            || ($layout['code'] ?? false) === true
            || $this->lineHasPdfListBlockEvidence($text)
            || preg_match('/[.!?;:]\s*$/u', $text) === 1
            || preg_match('/^[^\p{L}\p{N}]*\p{Ll}/u', $text) === 1) {
            return false;
        }

        foreach ($records as $candidate) {
            $candidateLayout = $candidate['layout'];
            $candidateText = ltrim($candidate['text']);
            if (!$this->pdfLayoutHasGeometry($candidateLayout)
                || ($candidateLayout['page'] ?? null) !== ($layout['page'] ?? null)
                || ($candidateLayout['sourceStream'] ?? null) !== $layout['sourceStream']
                || ($candidateLayout['sourcePdfSourceIndex'] ?? null) !== $layout['sourcePdfSourceIndex'] + 1
                || ($candidateLayout['sourceGeometryColumn'] ?? null) !== ($layout['sourceGeometryColumn'] ?? null)
                || ($candidateLayout['code'] ?? false) === true
                || $this->lineHasPdfListBlockEvidence($candidateText)
                || preg_match('/^[^\p{L}\p{N}]*\p{Ll}/u', $candidateText) !== 1) {
                continue;
            }

            $fontSize = max((float) $layout['fontSize'], (float) $candidateLayout['fontSize'], $medianFontSize, 1.0);
            $step = (float) $layout['y1'] - (float) $candidateLayout['y1'];
            if ($step >= $fontSize * 0.30 && $step <= max(18.0, $fontSize * 2.0)) {
                return true;
            }
        }

        return false;
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
            } else {
                foreach ($this->atomicPdfMapLegendRecords($candidate) as $record) {
                    $filtered[] = $record;
                }
            }
            $candidate = [];
        };

        foreach ($records as $record) {
            $line = $record['text'];
            if ($this->lineLooksLikePdfMapLabelNoise($line, $record['layout'])
                || $this->lineLooksLikePdfMapLabelBridge($record, $candidate)) {
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
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     * @return list<array{text: string, layout: array<string, mixed>|null}>
     */
    private function atomicPdfMapLegendRecords(array $records): array
    {
        $atomic = [];
        $positionedRun = [];
        $flushRun = function () use (&$atomic, &$positionedRun): void {
            if ($this->pdfMapLabelClusterLooksLikeLegend($positionedRun)) {
                foreach ($this->atomicPdfMapLabelRecords($positionedRun) as $record) {
                    $atomic[] = $record;
                }
            }
            $positionedRun = [];
        };

        foreach ($records as $record) {
            if ($this->pdfLayoutHasGeometry($record['layout'])) {
                $positionedRun[] = $record;
                continue;
            }
            $flushRun();
        }
        $flushRun();

        return $atomic;
    }

    /**
     * Keep a true map legend available as isolated text, rather than letting
     * its labels masquerade as document headings or list continuations. A
     * legend has multiple recurring label columns; free-positioned diagram
     * labels do not meet this geometric condition and remain suppressed.
     *
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     */
    private function pdfMapLabelClusterLooksLikeLegend(array $records): bool
    {
        if (count($records) < 8) {
            return false;
        }

        $columns = [];
        foreach ($records as $record) {
            $layout = $record['layout'];
            if (!$this->pdfLayoutHasGeometry($layout)
                || ($layout['sourceDetachedDiagramEvidencePage'] ?? false) === true) {
                return false;
            }

            $fontSize = max(1.0, (float) $layout['fontSize']);
            $matchedColumn = null;
            foreach ($columns as $index => $column) {
                if (abs((float) $layout['x1'] - $column['x']) <= max(16.0, $fontSize * 3.0)) {
                    $matchedColumn = $index;
                    break;
                }
            }
            if ($matchedColumn === null) {
                $columns[] = ['x' => (float) $layout['x1'], 'count' => 1];
                continue;
            }
            $columns[$matchedColumn]['count']++;
        }

        $repeatedColumns = array_filter(
            $columns,
            static fn (array $column): bool => $column['count'] >= 3
        );

        if (count($repeatedColumns) >= 2) {
            return true;
        }
        if (count($repeatedColumns) !== 1) {
            return false;
        }

        // A map key can be a single, vertically stacked label column rather
        // than a two-column legend. Keep it only when the same geometry shows
        // a sustained baseline rhythm; a scattered one-column diagram does
        // not meet this condition.
        $column = array_values($repeatedColumns)[0];
        if ($column['count'] < 5) {
            return false;
        }
        $columnRecords = array_values(array_filter(
            $records,
            static fn (array $record): bool => abs((float) $record['layout']['x1'] - $column['x'])
                <= max(16.0, (float) $record['layout']['fontSize'] * 3.0)
        ));
        usort($columnRecords, static fn (array $left, array $right): int => $right['layout']['y1'] <=> $left['layout']['y1']);
        $rhythmPairs = 0;
        foreach ($columnRecords as $index => $record) {
            if (!isset($columnRecords[$index + 1])) {
                break;
            }
            $next = $columnRecords[$index + 1];
            $fontSize = max(1.0, (float) $record['layout']['fontSize'], (float) $next['layout']['fontSize']);
            $step = (float) $record['layout']['y1'] - (float) $next['layout']['y1'];
            if ($step >= $fontSize * 0.30 && $step <= $fontSize * 3.0) {
                $rhythmPairs++;
            }
        }

        return $rhythmPairs >= 4;
    }

    /**
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     * @return list<array{text: string, layout: array<string, mixed>|null}>
     */
    private function atomicPdfMapLabelRecords(array $records): array
    {
        $atomic = [];
        foreach ($records as $record) {
            $text = trim($record['text']);
            if ($text === '') {
                continue;
            }
            $record['text'] = self::PDF_MAP_LABEL_PREFIX . $text;
            if (is_array($record['layout'])) {
                $record['layout']['forceBlockBreakBefore'] = true;
            }
            $atomic[] = $record;
        }

        return $atomic;
    }

    /**
     * A broken text layer can insert spaces into a compact map label, causing
     * it to look like ordinary short prose. Keep it with an adjacent map-label
     * run only when the geometry proves it occupies the same small, regular
     * label column. This avoids folding it into an unrelated list or paragraph.
     *
     * @param array{text: string, layout: array<string, mixed>|null} $record
     * @param list<array{text: string, layout: array<string, mixed>|null}> $candidate
     */
    private function lineLooksLikePdfMapLabelBridge(array $record, array $candidate): bool
    {
        if ($candidate === [] || $this->lineLooksLikePdfListItem($record['text']) || $this->lineLooksLikeUrlOnly($record['text'])) {
            return false;
        }

        $line = trim($record['text']);
        $layout = $record['layout'];
        if (!$this->pdfLayoutHasGeometry($layout)
            || preg_match('/[.!?;:]\s*$/u', $line) === 1
            || $this->length(preg_replace('/\s+/u', '', $line) ?? '') > 36
            || count($this->pdfLineWordTokens($line)) > 7) {
            return false;
        }

        for ($index = count($candidate) - 1; $index >= max(0, count($candidate) - 2); $index--) {
            $previousLayout = $candidate[$index]['layout'];
            if (!$this->pdfLayoutHasGeometry($previousLayout)
                || ($previousLayout['page'] ?? null) !== ($layout['page'] ?? null)) {
                continue;
            }

            $fontSize = max((float) $previousLayout['fontSize'], (float) $layout['fontSize'], 1.0);
            if ((float) $layout['fontSize'] > (float) $previousLayout['fontSize'] * 1.35
                || abs((float) $layout['x1'] - (float) $previousLayout['x1']) > max(18.0, $fontSize * 3.0)
                || abs((float) $layout['y1'] - (float) $previousLayout['y1']) > max(18.0, $fontSize * 2.8)) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * Diagram labels are frequently emitted as individual glyph fragments in
     * reading order. They can look like a stacked table after text extraction,
     * even though their only reliable semantic counterpart is the surrounding
     * figure caption. Drop only sustained clusters that contain repeated word
     * fragments; ordinary short headings and compact data grids remain intact.
     *
     * @param list<array{text: string, layout: array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float}|null}> $records
     * @return list<array{text: string, layout: array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float}|null}>
     */
    private function removeLowCoherencePdfDiagramLabelRegions(array $records): array
    {
        $filtered = [];
        $candidate = [];
        $flushCandidate = function () use (&$filtered, &$candidate): void {
            if ($candidate === []) {
                return;
            }
            if (!$this->pdfDiagramLabelClusterShouldBeDropped($candidate)) {
                foreach ($candidate as $record) {
                    $filtered[] = $record;
                }
            }
            $candidate = [];
        };

        foreach ($records as $record) {
            $layout = is_array($record['layout'] ?? null) ? $record['layout'] : null;
            if (str_starts_with($record['text'], self::PDF_MAP_LABEL_PREFIX)
                || ($layout['code'] ?? false) === true
                || !$this->lineLooksLikePdfDiagramLabel($record['text'])) {
                $flushCandidate();
                $filtered[] = $record;
                continue;
            }
            $candidate[] = $record;
        }
        $flushCandidate();

        return $filtered;
    }

    /**
     * A line that could not be assigned to one of a complex page's stable
     * prose columns has no reliable reading position. Keep explicit content
     * such as lists, URLs, complete captions, and substantial captions; drop
     * the small unfinished fragments commonly painted inside diagrams.
     *
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     * @return list<array{text: string, layout: array<string, mixed>|null}>
     */
    private function removeLowCoherencePdfStructuredFloatingFragments(array $records): array
    {
        $filtered = [];
        foreach ($records as $record) {
            $layout = is_array($record['layout'] ?? null) ? $record['layout'] : null;
            if (str_starts_with($record['text'], self::PDF_MAP_LABEL_PREFIX)
                || ($layout['sourceFloatingGeometry'] ?? false) !== true
                || (($layout['sourceComplexGeometryPage'] ?? false) !== true
                    && ($layout['sourceDetachedDiagramEvidencePage'] ?? false) !== true)) {
                $filtered[] = $record;
                continue;
            }

            $line = trim($record['text']);
            if (($layout['code'] ?? false) === true
                || $this->lineHasPdfListBlockEvidence($line)
                || $this->lineLooksLikeUrlOnly($line)
                || $this->lineLooksLikePdfAllCapsDisplayText($line)
                || $this->lineLooksLikeCompletePdfCaption($line)) {
                $filtered[] = $record;
                continue;
            }
            if (preg_match('/^[\]\)}.,;:]/u', $line) === 1) {
                continue;
            }
            if ($this->length($line) >= 48 && count($this->pdfLineWordTokens($line)) >= 8) {
                $filtered[] = $record;
            }
        }

        return $filtered;
    }

    /**
     * A source-only fragment with a skipped source prefix cannot be promoted
     * to prose merely because a neighboring line supplied coordinates. The
     * marker is assigned only by source-stream and geometry evidence above.
     *
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     * @return list<array{text: string, layout: array<string, mixed>|null}>
     */
    private function removeSourcePdfOrphanedInferredContinuations(array $records): array
    {
        $filtered = [];
        $orphanedFlowLayout = null;
        foreach ($records as $record) {
            $layout = is_array($record['layout'] ?? null) ? $record['layout'] : null;
            $text = (string) ($record['text'] ?? '');
            if (($layout['sourceInlineInferredFragment'] ?? false) === true) {
                continue;
            }
            if (($layout['sourceCrossColumnContinuation'] ?? false) === true
                || ($layout['sourceCrossColumnContinuationLead'] ?? false) === true) {
                $orphanedFlowLayout = null;
                $filtered[] = $record;
                continue;
            }
            if (($layout['sourceVerifiedGeometryText'] ?? false) === true
                && $this->length($this->pdfComparableLineText($text)) >= 24
                && count($this->pdfLineWordTokens($text)) >= 4) {
                $orphanedFlowLayout = null;
                $filtered[] = $record;
                continue;
            }
            $startsOrphanedFlow = ($layout['sourceOrphanedInferredContinuation'] ?? false) === true
                || ($layout['sourceUnresolvedInterruptedFlow'] ?? false) === true;
            $continuesOrphanedFlow = !$startsOrphanedFlow
                && $orphanedFlowLayout !== null
                && $this->sourcePdfRecordContinuesOrphanedInferredFlow($layout, $orphanedFlowLayout);
            if ($continuesOrphanedFlow
                && ($orphanedFlowLayout['sourceUnresolvedInterruptedFlow'] ?? false) !== true
                && preg_match('/^[^\p{L}\p{N}]*\p{Lu}/u', ltrim($text)) === 1
                && preg_match('/[.!?]\s*$/u', rtrim($text)) === 1) {
                $continuesOrphanedFlow = false;
            }
            if (!$startsOrphanedFlow && !$continuesOrphanedFlow) {
                $orphanedFlowLayout = null;
            }
            if (!$startsOrphanedFlow && $orphanedFlowLayout === null) {
                $filtered[] = $record;
                continue;
            }

            if ($startsOrphanedFlow || $continuesOrphanedFlow) {
                $orphanedFlowLayout = $layout;
            }
            if ($startsOrphanedFlow) {
                $this->trimSourcePdfFlowBeforeOrphanedContinuation($filtered, $layout);
            }
            $previousIndex = array_key_last($filtered);
            if ($previousIndex === null || $layout === null) {
                continue;
            }
            $previousLayout = is_array($filtered[$previousIndex]['layout'] ?? null)
                ? $filtered[$previousIndex]['layout']
                : null;
            $previousText = rtrim((string) ($filtered[$previousIndex]['text'] ?? ''));
            if (!$this->pdfLayoutHasGeometry($previousLayout)
                || ($previousLayout['page'] ?? null) !== ($layout['page'] ?? null)
                || ($previousLayout['sourceGeometryColumn'] ?? null) !== ($layout['sourceGeometryColumn'] ?? null)
                || preg_match('/[.!?;:]\s*$/u', $previousText) === 1) {
                continue;
            }

            $prefix = $this->completePdfSentencePrefix($previousText);
            if ($prefix !== '' && count($this->pdfLineWordTokens($prefix)) >= 5) {
                $filtered[$previousIndex]['text'] = $prefix;
            }
        }

        return $filtered;
    }

    /**
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     * @param array<string, mixed>|null $layout
     */
    private function trimSourcePdfFlowBeforeOrphanedContinuation(array &$records, ?array $layout): void
    {
        if (!$this->pdfLayoutHasGeometry($layout)) {
            return;
        }

        $limit = max(0, count($records) - 10);
        $nextSourceIndex = null;
        for ($index = count($records) - 1; $index >= $limit; $index--) {
            $candidateLayout = is_array($records[$index]['layout'] ?? null) ? $records[$index]['layout'] : null;
            $candidateStart = (int) ($candidateLayout['sourcePdfSourceIndex'] ?? -1);
            $candidateEnd = (int) ($candidateLayout['sourcePdfSourceIndexEnd'] ?? $candidateStart);
            if (!$this->pdfLayoutHasGeometry($candidateLayout)
                || ($candidateLayout['page'] ?? null) !== ($layout['page'] ?? null)
                || !isset($candidateLayout['sourceStream'], $layout['sourceStream'])
                || (int) $candidateLayout['sourceStream'] !== (int) $layout['sourceStream']
                || $candidateStart < 0
                || ($nextSourceIndex !== null && $candidateEnd + 1 !== $nextSourceIndex)
                || isset($candidateLayout['sourcePdfTableGroup'])
                || ($candidateLayout['code'] ?? false) === true) {
                break;
            }
            $nextSourceIndex = $candidateStart;
            $prefix = $this->completePdfSentencePrefix((string) ($records[$index]['text'] ?? ''));
            if ($prefix === '' || count($this->pdfLineWordTokens($prefix)) < 5) {
                continue;
            }
            $records[$index]['text'] = $prefix;
            $records = array_slice($records, 0, $index + 1);

            return;
        }
    }

    /**
     * @param array<string, mixed>|null $layout
     * @param array<string, mixed> $orphanedLayout
     */
    private function sourcePdfRecordContinuesOrphanedInferredFlow(?array $layout, array $orphanedLayout): bool
    {
        if (!$this->pdfLayoutHasGeometry($layout)
            || !$this->pdfLayoutHasGeometry($orphanedLayout)
            || ($layout['page'] ?? null) !== ($orphanedLayout['page'] ?? null)
            || ($layout['sourceGeometryColumn'] ?? null) !== ($orphanedLayout['sourceGeometryColumn'] ?? null)
            || isset($layout['sourcePdfTableGroup'])
            || ($layout['code'] ?? false) === true) {
            return false;
        }

        $fontSize = max(1.0, (float) $layout['fontSize'], (float) $orphanedLayout['fontSize']);
        if (abs((float) $layout['fontSize'] - (float) $orphanedLayout['fontSize']) > max(1.5, $fontSize * 0.22)) {
            return false;
        }
        $verticalStep = (float) $orphanedLayout['y1'] - (float) $layout['y1'];

        return (($orphanedLayout['sourceOrphanedMissingSourceText'] ?? false) === true
                || $this->pdfComparableLineText((string) ($orphanedLayout['text'] ?? '')) !== '')
            && $verticalStep >= -$fontSize * 0.35
            && $verticalStep <= max(18.0, $fontSize * 2.2);
    }

    /**
     * A damaged figure can leave a column-shaped run with its ending words
     * omitted. On a page already proven to contain detached diagram text, do
     * not emit such a run as a fabricated paragraph. Complete sentences and
     * labels remain; only a spatially isolated, unfinished flow is removed.
     *
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     * @return list<array{text: string, layout: array<string, mixed>|null}>
     */
    private function removeIncompletePdfComplexColumnSegments(array $records): array
    {
        $filtered = [];
        $segment = [];
        $visualPreceding = null;
        $discardedFlowLayout = null;
        $flushSegment = function (?array $following = null, ?array $afterFollowing = null) use (&$filtered, &$segment, &$visualPreceding, &$discardedFlowLayout): void {
            if ($segment === []) {
                return;
            }
            $segment = $this->trimLeadingIncompletePdfComplexSegment($segment);
            $preceding = $filtered === [] ? null : $filtered[array_key_last($filtered)];
            if ($this->pdfComplexColumnSegmentShouldBeDropped(
                $segment,
                $preceding,
                $following,
                $afterFollowing,
                $visualPreceding
            )) {
                $lastLayout = is_array($segment[array_key_last($segment)]['layout'] ?? null)
                    ? $segment[array_key_last($segment)]['layout']
                    : null;
                $completePrefix = $this->completePdfComplexSegmentPrefix($segment);
                if ($this->pdfComplexSegmentPrefixLooksComplete($completePrefix)) {
                    foreach ($completePrefix as $record) {
                        $filtered[] = $record;
                    }
                } else {
                    $completeSuffix = $this->completePdfComplexSegmentSuffix($segment);
                    if ($this->pdfComplexSegmentPrefixLooksComplete($completeSuffix)) {
                        foreach ($completeSuffix as $record) {
                            $filtered[] = $record;
                        }
                    }
                }
                $discardedFlowLayout = $this->pdfLayoutHasGeometry($lastLayout) ? $lastLayout : null;
            } else {
                foreach ($segment as $record) {
                    $filtered[] = $record;
                }
                $discardedFlowLayout = null;
            }
            $segment = [];
            $visualPreceding = null;
        };

        foreach ($records as $recordIndex => $record) {
            $afterRecord = $records[$recordIndex + 1] ?? null;
            $layout = is_array($record['layout'] ?? null) ? $record['layout'] : null;
            if (!$this->pdfLayoutHasGeometry($layout)
                || ($layout['sourceStructuredGeometry'] ?? false) !== true
                || isset($layout['sourcePdfTableGroup'])
                || ($layout['sourcePdfReferenceEntry'] ?? false) === true
                || ($layout['code'] ?? false) === true) {
                $flushSegment($record, $afterRecord);
                $filtered[] = $record;
                continue;
            }

            $last = $segment === [] ? null : $segment[array_key_last($segment)];
            if ($last === null) {
                if ($this->pdfComplexColumnRecordContinuesDroppedFlow($record, $discardedFlowLayout)) {
                    $layout['sourceInterruptedColumnRegion'] = true;
                    $record['layout'] = $layout;
                } else {
                    $discardedFlowLayout = null;
                }
            }
            $recordHasDamageEvidence = $this->pdfComplexColumnRecordHasDamageEvidence($record);
            $recoverableSentenceSuffix = ($layout['sourceSupplementalRecoverableSentenceSuffix'] ?? false) === true;
            if ($recordHasDamageEvidence
                && ($last === null
                    || ($layout['forceBlockBreakBefore'] ?? false) === true
                    || $recoverableSentenceSuffix)) {
                $record = $this->trimLeadingIncompletePdfComplexRecord($record);
            }
            $startsOrphanPunctuation = $recordHasDamageEvidence
                && preg_match('/^[,.;:\)\]\}]/u', ltrim($record['text'])) === 1;
            $forceDamagedBoundary = $last !== null
                && ($layout['forceBlockBreakBefore'] ?? false) === true
                && $recordHasDamageEvidence;
            $lastLayout = is_array($last['layout'] ?? null) ? $last['layout'] : null;
            $forcedSectionBoundary = $last !== null
                && ($lastLayout['forceBlockBreakBefore'] ?? false) === true
                && ($this->repairedLineLooksLikeSectionLabel($last['text'])
                    || $this->pdfComplexColumnRecordLooksLikeForcedDisplayHeading($last, $record));
            if ($last === null || (!$recoverableSentenceSuffix
                && !$forceDamagedBoundary
                && !$forcedSectionBoundary
                && !$this->pdfComplexColumnRecordsHaveDamagedFlowBoundary($last, $record)
                && $this->pdfComplexColumnRecordsShareFlow($last, $record)
                && !$this->pdfComplexColumnRecordsHaveSentenceBoundary($last, $record)
                && !$startsOrphanPunctuation)) {
                $segment[] = $record;
                continue;
            }
            $flushSegment($record, $afterRecord);
            $visualPreceding = $last;
            $layout = is_array($record['layout'] ?? null) ? $record['layout'] : null;
            if ($this->pdfComplexColumnRecordContinuesDroppedFlow($record, $discardedFlowLayout)) {
                $layout['sourceInterruptedColumnRegion'] = true;
                $record['layout'] = $layout;
            } else {
                $discardedFlowLayout = null;
            }
            $segment[] = $record;
        }
        $flushSegment();

        return $filtered;
    }

    /**
     * A complex page can expose a short run whose geometry has already proved
     * interrupted. When that run begins as a lower-case continuation after a
     * completed visual block and ends at a forced new block, it has no valid
     * paragraph start. Remove the entire unresolved run rather than retaining
     * a coincidental sentence from a formula, figure, or text overlay.
     *
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     * @return list<array{text: string, layout: array<string, mixed>|null}>
     */
    private function removeIncompletePdfInterruptedFlowSegments(array $records): array
    {
        $drop = [];
        $count = count($records);
        for ($start = 0; $start < $count; $start++) {
            if (isset($drop[$start])) {
                continue;
            }
            $layout = is_array($records[$start]['layout'] ?? null) ? $records[$start]['layout'] : null;
            $text = ltrim((string) ($records[$start]['text'] ?? ''));
            $startsLowercaseContinuation = preg_match('/^[^\p{L}\p{N}]*\p{Ll}/u', $text) === 1;
            $startsShortUnfinishedLead = preg_match('/^[^\p{L}\p{N}]*\p{Lu}/u', $text) === 1
                && preg_match('/[.!?;:]\s*$/u', $text) !== 1
                && count($this->pdfLineWordTokens($text)) <= 5;
            $startsInterruptedSupplementLead = ($layout['sourceSupplementalPositioned'] ?? false) === true
                && preg_match('/[.!?;:]\s*$/u', $text) !== 1;
            if (!$this->pdfLayoutHasGeometry($layout)
                || ($layout['sourceVerifiedGeometryText'] ?? false) === true
                || ($layout['sourceInterruptedColumnRegion'] ?? false) !== true
                || (($layout['forceBlockBreakBefore'] ?? false) === true && !$startsInterruptedSupplementLead)
                || $this->lineHasPdfListBlockEvidence($text)
                || $this->lineLooksLikeUrlOnly($text)
                || (!$startsLowercaseContinuation && !$startsShortUnfinishedLead && !$startsInterruptedSupplementLead)) {
                continue;
            }

            $end = $start;
            while (isset($records[$end + 1])) {
                $nextLayout = is_array($records[$end + 1]['layout'] ?? null) ? $records[$end + 1]['layout'] : null;
                $nextText = ltrim((string) ($records[$end + 1]['text'] ?? ''));
                if (!$this->pdfLayoutHasGeometry($nextLayout)
                    || ($nextLayout['sourceInterruptedColumnRegion'] ?? false) !== true
                    || ($nextLayout['forceBlockBreakBefore'] ?? false) === true
                    || ($nextLayout['page'] ?? null) !== ($layout['page'] ?? null)
                    || ($nextLayout['sourceGeometryColumn'] ?? null) !== ($layout['sourceGeometryColumn'] ?? null)
                    || (isset($nextLayout['sourceStream'], $layout['sourceStream'])
                        && $nextLayout['sourceStream'] !== $layout['sourceStream'])
                    || $this->lineHasPdfListBlockEvidence($nextText)
                    || $this->lineLooksLikeUrlOnly($nextText)
                    || preg_match('/^[^\p{L}\p{N}]*\p{Ll}/u', $nextText) !== 1
                    || !$this->pdfInterruptedFlowRecordsShareBaselineRhythm($records[$end], $records[$end + 1])) {
                    break;
                }
                $end++;
            }

            if (!isset($records[$start - 1], $records[$end + 1])) {
                continue;
            }
            $preceding = $records[$start - 1];
            $following = $records[$end + 1];
            $precedingLayout = is_array($preceding['layout'] ?? null) ? $preceding['layout'] : null;
            $followingLayout = is_array($following['layout'] ?? null) ? $following['layout'] : null;
            $followingText = ltrim((string) ($following['text'] ?? ''));
            if (!$this->pdfLayoutHasGeometry($precedingLayout)
                || !$this->pdfLayoutHasGeometry($followingLayout)
                || ($precedingLayout['page'] ?? null) !== ($layout['page'] ?? null)
                || ($followingLayout['page'] ?? null) !== ($layout['page'] ?? null)
                || ($precedingLayout['sourceGeometryColumn'] ?? null) !== ($layout['sourceGeometryColumn'] ?? null)
                || ($followingLayout['sourceGeometryColumn'] ?? null) !== ($layout['sourceGeometryColumn'] ?? null)
                || preg_match('/[.!?;:]\s*$/u', rtrim((string) ($preceding['text'] ?? ''))) !== 1
                || ($followingLayout['forceBlockBreakBefore'] ?? false) !== true
                || preg_match('/^[^\p{L}\p{N}]*\p{Lu}/u', $followingText) !== 1) {
                continue;
            }

            for ($index = $start; $index <= $end; $index++) {
                $drop[$index] = true;
            }
            $start = $end;
        }

        if ($drop === []) {
            return $records;
        }

        return array_values(array_filter(
            $records,
            static fn (array $_record, int $index): bool => !isset($drop[$index]),
            ARRAY_FILTER_USE_BOTH
        ));
    }

    /**
     * @param array{text: string, layout: array<string, mixed>|null} $upper
     * @param array{text: string, layout: array<string, mixed>|null} $lower
     */
    private function pdfInterruptedFlowRecordsShareBaselineRhythm(array $upper, array $lower): bool
    {
        $upperLayout = is_array($upper['layout'] ?? null) ? $upper['layout'] : null;
        $lowerLayout = is_array($lower['layout'] ?? null) ? $lower['layout'] : null;
        if (!$this->pdfLayoutHasGeometry($upperLayout)
            || !$this->pdfLayoutHasGeometry($lowerLayout)) {
            return false;
        }

        $fontSize = max(1.0, (float) $upperLayout['fontSize'], (float) $lowerLayout['fontSize']);
        $step = (float) $upperLayout['y1'] - (float) $lowerLayout['y1'];

        return $step >= $fontSize * 0.30 && $step <= $fontSize * 2.2;
    }

    /**
     * A damaged source run can retain a complete sentence followed by one or
     * two stranded glyphs from the next line. When the next visual record
     * starts a new sentence in the same body column, retain the complete
     * prefix and make the visual break explicit instead of fabricating one
     * combined paragraph.
     *
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     * @return list<array{text: string, layout: array<string, mixed>|null}>
     */
    private function trimIncompletePdfInterruptedSentenceTails(array $records): array
    {
        $count = count($records);
        for ($index = 0; $index < $count - 1; $index++) {
            $layout = is_array($records[$index]['layout'] ?? null) ? $records[$index]['layout'] : null;
            $nextLayout = is_array($records[$index + 1]['layout'] ?? null) ? $records[$index + 1]['layout'] : null;
            $text = rtrim($records[$index]['text']);
            $nextText = ltrim($records[$index + 1]['text']);
            if (!$this->pdfLayoutHasGeometry($layout)
                || !$this->pdfLayoutHasGeometry($nextLayout)
                || ($layout['sourceRecoveredPartialSupplemental'] ?? false) === true
                || ($layout['sourceInterruptedColumnRegion'] ?? false) !== true
                || ($layout['page'] ?? null) !== ($nextLayout['page'] ?? null)
                || ($layout['sourceGeometryColumn'] ?? null) !== ($nextLayout['sourceGeometryColumn'] ?? null)
                || preg_match('/[.!?]\s*$/u', $text) === 1
                || preg_match('/^[^\p{L}\p{N}]*\p{Lu}/u', $nextText) !== 1) {
                continue;
            }

            $tail = $this->lastWordToken($text);
            if ($tail === '' || $this->length($tail) > 3) {
                continue;
            }
            $fontSize = max(1.0, (float) $layout['fontSize'], (float) $nextLayout['fontSize']);
            $verticalStep = (float) $layout['y1'] - (float) $nextLayout['y1'];
            if ($verticalStep < -$fontSize * 0.5 || $verticalStep > max(20.0, $fontSize * 2.4)) {
                continue;
            }
            if (preg_match_all('/[.!?](?=\s|$)/u', $text, $matches, PREG_OFFSET_CAPTURE) === false
                || $matches[0] === []) {
                continue;
            }

            $last = $matches[0][array_key_last($matches[0])];
            $records[$index]['text'] = substr($text, 0, $last[1] + strlen($last[0]));
            $nextLayout['forceBlockBreakBefore'] = true;
            $records[$index + 1]['layout'] = $nextLayout;
        }

        return $records;
    }

    /**
     * A damaged figure can leave several positioned supplements inside one
     * source column. When that region ends in an unfinished tail before a
     * forced visual block, retain the last complete sentence rather than
     * merging the figure residue into prose.
     *
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     * @return list<array{text: string, layout: array<string, mixed>|null}>
     */
    private function trimIncompletePdfSupplementalSegmentTails(array $records): array
    {
        $trimmed = [];
        $segment = [];
        $flush = function () use (&$trimmed, &$segment): void {
            if ($segment === []) {
                return;
            }

            $supplements = 0;
            $interrupted = false;
            foreach ($segment as $record) {
                $layout = is_array($record['layout'] ?? null) ? $record['layout'] : null;
                if ($layout === null) {
                    continue;
                }
                $supplements += ($layout['sourceSupplementalPositioned'] ?? false) === true ? 1 : 0;
                $interrupted = $interrupted || (($layout['sourceInterruptedColumnRegion'] ?? false) === true);
            }
            $lastText = rtrim((string) ($segment[array_key_last($segment)]['text'] ?? ''));
            $lastLayout = is_array($segment[array_key_last($segment)]['layout'] ?? null)
                ? $segment[array_key_last($segment)]['layout']
                : null;
            $hasInferredTail = is_array($lastLayout)
                && ($lastLayout['sourceInferredNeighborLayout'] ?? false) === true;
            if ((($interrupted && $supplements >= 3) || $hasInferredTail)
                && preg_match('/[.!?]\s*$/u', $lastText) !== 1) {
                $lastComplete = null;
                $truncatedRecord = null;
                for ($index = count($segment) - 1; $index >= 0; $index--) {
                    if (preg_match('/[.!?]\s*$/u', rtrim($segment[$index]['text'])) === 1) {
                        $lastComplete = $index;
                        break;
                    }
                    $prefix = $this->completePdfSentencePrefix($segment[$index]['text']);
                    if ($prefix !== '' && count($this->pdfLineWordTokens($prefix)) >= 5) {
                        $lastComplete = $index;
                        $truncatedRecord = array_replace($segment[$index], ['text' => $prefix]);
                        break;
                    }
                }
                if ($lastComplete !== null) {
                    foreach (array_slice($segment, 0, $lastComplete + 1) as $recordIndex => $record) {
                        if ($truncatedRecord !== null && $recordIndex === $lastComplete) {
                            $record = $truncatedRecord;
                        }
                        $trimmed[] = $record;
                    }
                    $segment = [];
                    return;
                }
            }

            foreach ($segment as $record) {
                $trimmed[] = $record;
            }
            $segment = [];
        };

        foreach ($records as $record) {
            $layout = is_array($record['layout'] ?? null) ? $record['layout'] : null;
            $previous = $segment === [] ? null : $segment[array_key_last($segment)];
            $previousLayout = is_array($previous['layout'] ?? null) ? $previous['layout'] : null;
            $startsNewSegment = $previous !== null && (
                !$this->pdfLayoutHasGeometry($layout)
                || !$this->pdfLayoutHasGeometry($previousLayout)
                || ($layout['page'] ?? null) !== ($previousLayout['page'] ?? null)
                || ($layout['sourceGeometryColumn'] ?? null) !== ($previousLayout['sourceGeometryColumn'] ?? null)
                || ($layout['forceBlockBreakBefore'] ?? false) === true
                || isset($layout['sourcePdfTableGroup'])
                || ($layout['sourcePdfReferenceEntry'] ?? false) === true
                || ($layout['code'] ?? false) === true
            );
            if ($startsNewSegment) {
                $flush();
            }
            $segment[] = $record;
        }
        $flush();

        return $trimmed;
    }

    /**
     * Some PDFs encode numbered section headings as ordinary text instead of
     * semantic headings. A forced, short line in a larger font immediately
     * before smaller body text is still a reliable visual block boundary.
     *
     * @param array{text: string, layout: array<string, mixed>|null} $record
     * @param array{text: string, layout: array<string, mixed>|null} $following
     */
    private function pdfComplexColumnRecordLooksLikeForcedDisplayHeading(array $record, array $following): bool
    {
        $layout = is_array($record['layout'] ?? null) ? $record['layout'] : null;
        $followingLayout = is_array($following['layout'] ?? null) ? $following['layout'] : null;
        $text = trim($record['text']);
        if (!$this->pdfLayoutHasGeometry($layout)
            || !$this->pdfLayoutHasGeometry($followingLayout)
            || ($layout['page'] ?? null) !== ($followingLayout['page'] ?? null)
            || ($layout['sourceGeometryColumn'] ?? null) !== ($followingLayout['sourceGeometryColumn'] ?? null)
            || ($layout['forceBlockBreakBefore'] ?? false) !== true
            || $text === ''
            || count($this->pdfLineWordTokens($text)) > 8
            || preg_match('/[.!?;:]\s*$/u', $text) === 1
            || (float) $layout['fontSize'] <= (float) $followingLayout['fontSize'] * 1.05) {
            return false;
        }

        return preg_match('/^(?:\p{N}+(?:\.\p{N}+)*\.?\s+|[^\p{L}\p{N}]*\p{Lu})/u', $text) === 1;
    }

    /**
     * References are often emitted in source order even when italic titles or
     * other font subsets fail to decode. Keep entries whose source and visual
     * geometry agree, but omit an entry once a missing visual run leaves a
     * continuation stranded in the middle of its column. This is deliberately
     * structural: titles, authors, and language are never inspected.
     *
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     * @return list<array{text: string, layout: array<string, mixed>|null}>
     */
    private function removeLowConfidencePdfReferenceEntries(array $records): array
    {
        $referenceCount = 0;
        foreach ($records as $record) {
            if ($this->lineLooksLikePdfReferenceEntry($record['text'])) {
                $referenceCount++;
            }
        }
        if ($referenceCount < 3) {
            return $records;
        }

        $filtered = [];
        $entry = [];
        $flushEntry = function () use (&$filtered, &$entry): void {
            if ($entry !== [] && !$this->pdfReferenceEntryHasUnresolvedVisualFragment($entry)) {
                foreach ($entry as $record) {
                    $filtered[] = $record;
                }
            }
            $entry = [];
        };

        foreach ($records as $record) {
            if ($this->lineLooksLikePdfReferenceEntry($record['text'])) {
                $flushEntry();
                $entry[] = $record;
                continue;
            }
            if ($entry !== []) {
                $entry[] = $record;
                continue;
            }
            $filtered[] = $record;
        }
        $flushEntry();

        return $filtered;
    }

    /**
     * @param list<array{text: string, layout: array<string, mixed>|null}> $entry
     */
    private function pdfReferenceEntryHasUnresolvedVisualFragment(array $entry): bool
    {
        if ($entry === []) {
            return false;
        }

        $last = rtrim($entry[array_key_last($entry)]['text']);
        if ($last === '' || preg_match('/[-\x{2010}-\x{2015}]\s*$/u', $last) === 1) {
            return true;
        }

        $leftEdge = null;
        $previous = null;
        foreach ($entry as $record) {
            $text = ltrim($record['text']);
            if ($text !== '' && preg_match('/^[,;:]/u', $text) === 1) {
                return true;
            }

            $layout = $record['layout'];
            if ($previous !== null
                && preg_match('/[-\x{2010}-\x{2015}]\s*$/u', rtrim($previous['text'])) === 1
                && preg_match('/^[^\p{L}\p{N}]*\p{Lu}/u', $text) === 1) {
                return true;
            }
            if (!$this->pdfLayoutHasGeometry($layout)) {
                $previous = $record;
                continue;
            }

            $leftEdge = $leftEdge === null ? (float) $layout['x1'] : min($leftEdge, (float) $layout['x1']);
            if ($previous !== null
                && $this->pdfLayoutHasGeometry($previous['layout'])
                && ($previous['layout']['page'] ?? null) === ($layout['page'] ?? null)
                && !$this->lineLooksLikeUrlOnly($text)
                && preg_match('/[-\x{2010}-\x{2015}]\s*$/u', rtrim($previous['text'])) !== 1) {
                $fontSize = max(1.0, (float) $layout['fontSize'], (float) $previous['layout']['fontSize']);
                if ((float) $layout['x1'] > $leftEdge + max(32.0, $fontSize * 4.0)) {
                    return true;
                }
            }
            $previous = $record;
        }

        return false;
    }

    /**
     * Once an incomplete page-end flow has been removed, its lower-case tail
     * can survive at the opening of the next page. A complete retained
     * sentence before the page break proves that this tail cannot belong to
     * the preceding block, so omit it rather than emit a fabricated sentence.
     *
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     * @return list<array{text: string, layout: array<string, mixed>|null}>
     */
    private function removeOrphanedPdfPageOpeningFragments(array $records): array
    {
        $filtered = [];
        $previous = null;
        $droppingPage = null;
        foreach ($records as $record) {
            $layout = $record['layout'];
            $page = is_array($layout) && isset($layout['page']) ? (int) $layout['page'] : null;
            $previousPage = is_array($previous['layout'] ?? null) && isset($previous['layout']['page'])
                ? (int) $previous['layout']['page']
                : null;
            $text = ltrim($record['text']);
            $startsContinuation = preg_match('/^(?:[^\p{L}\p{N}]*\p{Ll}|[,;:\)\]\}])/u', $text) === 1;
            $startsFormulaAssignment = $this->sourcePdfTextContainsCompactAssignmentLabel($text);
            if ($page !== null
                && $page !== $previousPage
                && $previous !== null
                && preg_match('/[.!?]\s*$/u', rtrim($previous['text'])) === 1
                && $startsContinuation
                && !$startsFormulaAssignment
                && !$this->lineHasPdfListBlockEvidence($text)
                && !$this->lineLooksLikeUrlOnly($text)) {
                $droppingPage = $page;
            }

            if ($page !== null
                && $droppingPage === $page
                && $startsContinuation
                && !$this->lineHasPdfListBlockEvidence($text)
                && !$this->lineLooksLikeUrlOnly($text)) {
                continue;
            }
            if ($page !== null && $droppingPage === $page) {
                $droppingPage = null;
            }

            $filtered[] = $record;
            $previous = $record;
        }

        return $filtered;
    }

    /**
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     */
    private function pdfComplexSegmentPrefixLooksComplete(array $records): bool
    {
        if ($records === []) {
            return false;
        }

        $first = trim($records[0]['text']);
        $last = trim($records[array_key_last($records)]['text']);

        return preg_match('/^[^\p{L}\p{N}]*(?:\p{Lu}|\p{N})/u', $first) === 1
            && count($this->pdfLineWordTokens(implode(' ', array_column($records, 'text')))) >= 5
            && preg_match('/[.!?]\s*$/u', $last) === 1;
    }

    /**
     * Keep the recoverable sentence prefix of a damaged flow. This is useful
     * when a missing visual run occurs after one or more complete sentences:
     * emitting the prefix is more faithful than either joining the missing
     * tail or discarding the entire paragraph.
     *
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     * @return list<array{text: string, layout: array<string, mixed>|null}>
     */
    private function completePdfComplexSegmentPrefix(array $records): array
    {
        $records = $this->pdfComplexSegmentBeforeVisualCollision($records);
        for ($index = count($records) - 1; $index >= 0; $index--) {
            $text = $records[$index]['text'];
            if (preg_match_all('/[.!?](?=\s|$)/u', $text, $matches, PREG_OFFSET_CAPTURE) !== false
                && $matches[0] !== []) {
                $last = $matches[0][array_key_last($matches[0])];
                $records[$index]['text'] = substr($text, 0, $last[1] + strlen($last[0]));

                return array_slice($records, 0, $index + 1);
            }
        }

        return [];
    }

    /**
     * Source text from a malformed figure can contain two incompatible runs
     * on the same baseline. Once that collision appears, later punctuation is
     * not evidence that the preceding body flow is complete. Preserve only
     * the records before the collision for sentence-prefix recovery.
     *
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     * @return list<array{text: string, layout: array<string, mixed>|null}>
     */
    private function pdfComplexSegmentBeforeVisualCollision(array $records): array
    {
        for ($index = 1, $count = count($records); $index < $count; $index++) {
            $previousLayout = is_array($records[$index - 1]['layout'] ?? null)
                ? $records[$index - 1]['layout']
                : null;
            $layout = is_array($records[$index]['layout'] ?? null)
                ? $records[$index]['layout']
                : null;
            if (!$this->pdfLayoutHasGeometry($previousLayout)
                || !$this->pdfLayoutHasGeometry($layout)
                || ($previousLayout['page'] ?? null) !== ($layout['page'] ?? null)
                || ($previousLayout['sourceGeometryColumn'] ?? null) !== ($layout['sourceGeometryColumn'] ?? null)) {
                continue;
            }

            $fontSize = max(1.0, (float) $previousLayout['fontSize'], (float) $layout['fontSize']);
            if (abs((float) $previousLayout['y1'] - (float) $layout['y1']) <= max(2.0, $fontSize * 0.45)
                && abs((float) $previousLayout['x1'] - (float) $layout['x1']) <= max(12.0, $fontSize * 1.25)) {
                return array_slice($records, 0, $index);
            }
        }

        return $records;
    }

    /**
     * Preserve complete sentences that follow an observable missing prefix.
     * The segment is already known to be unsafe, so this requires a sentence
     * boundary followed by a capitalized sentence start and trims any final
     * unresolved tail. It relies only on punctuation and visual damage marks,
     * not document language or vocabulary.
     *
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     * @return list<array{text: string, layout: array<string, mixed>|null}>
     */
    private function completePdfComplexSegmentSuffix(array $records): array
    {
        if ($records === []) {
            return [];
        }

        $first = ltrim($records[0]['text']);
        if (preg_match('/^(?:[^\p{L}\p{N}]*\p{Ll}|[,.;:\)\]\}])/u', $first) !== 1) {
            return [];
        }

        foreach ($records as $index => $record) {
            if (preg_match('/[.!?]\s*((?:(?:[\(\[]\s*)?(?:\p{N}+\s*[\)\]]\s*)?)?\p{Lu}[\s\S]*)$/u', $record['text'], $matches) !== 1) {
                continue;
            }

            $suffix = array_slice($records, $index);
            $suffix[0]['text'] = $matches[1];
            $suffix = $this->completePdfComplexSegmentPrefix($suffix);
            if (!$this->pdfComplexSegmentPrefixLooksComplete($suffix)) {
                continue;
            }

            return $suffix;
        }

        return [];
    }

    /**
     * A damaged visual line can still contain a complete sentence after its
     * missing prefix. Split at that punctuation boundary before assessing the
     * surrounding geometric flow, so a broken lead-in does not discard the
     * independently complete sentence that follows.
     *
     * @param array{text: string, layout: array<string, mixed>|null} $record
     * @return array{text: string, layout: array<string, mixed>|null}
     */
    private function trimLeadingIncompletePdfComplexRecord(array $record): array
    {
        $text = ltrim($record['text']);
        $layout = is_array($record['layout'] ?? null) ? $record['layout'] : [];
        $hasRecoverableSentenceSuffix = ($layout['sourceSupplementalRecoverableSentenceSuffix'] ?? false) === true;
        if ((preg_match('/^[^\p{L}\p{N}]*\p{Ll}/u', $text) === 1
                || preg_match('/^[,.;:]\s+/u', $text) === 1
                || $hasRecoverableSentenceSuffix)
            && preg_match('/[.!?]\s*((?:(?:[\(\[]\s*)?(?:\p{N}+\s*[\)\]]\s*)?)?\p{Lu}[\s\S]*)$/u', $text, $matches) === 1) {
            if (count($this->pdfLineWordTokens($matches[1])) < 5) {
                return $record;
            }
            $record['text'] = $matches[1];
        }

        return $record;
    }

    /**
     * @param array{text: string, layout: array<string, mixed>|null} $record
     */
    private function pdfComplexColumnRecordHasDamageEvidence(array $record): bool
    {
        $layout = is_array($record['layout'] ?? null) ? $record['layout'] : [];
        if (($layout['sourceVerifiedGeometryText'] ?? false) === true) {
            return false;
        }
        if (($layout['sourceInterruptedColumnRegion'] ?? false) === true
            || ($layout['sourceMinorFontFlow'] ?? false) === true) {
            return true;
        }

        return ($layout['forceBlockBreakBefore'] ?? false) === true
            && preg_match('/^(?:[,.;:\)\]\}]|\(|[^\p{L}\p{N}]*\p{Ll})/u', ltrim($record['text'])) === 1;
    }

    /**
     * Keep a segment unless its own geometry makes the missing content
     * observable. A page with a chart or a figure may still contain ordinary
     * body prose, so a page-wide complexity marker alone cannot justify
     * deleting text.
     *
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     * @param array{text: string, layout: array<string, mixed>|null}|null $preceding
     * @param array{text: string, layout: array<string, mixed>|null}|null $following
     * @param array{text: string, layout: array<string, mixed>|null}|null $afterFollowing
     * @param array{text: string, layout: array<string, mixed>|null}|null $visualPreceding
     */
    private function pdfComplexColumnSegmentShouldBeDropped(
        array $records,
        ?array $preceding,
        ?array $following,
        ?array $afterFollowing = null,
        ?array $visualPreceding = null
    ): bool
    {
        foreach ($records as $record) {
            if (($record['layout']['sourceCrossColumnContinuationTail'] ?? false) === true) {
                return false;
            }
        }
        if ($this->pdfComplexColumnSegmentIsIsolatedInterruptedSupplement($records, $following)) {
            return true;
        }
        if ($this->pdfComplexColumnSegmentIsFollowedByRecoverableSentenceSuffix($records, $following)) {
            return true;
        }
        if ($this->pdfComplexColumnSegmentHasInterruptedVisualCollision($records)) {
            return true;
        }
        if ($this->pdfComplexColumnSegmentHasDetachedInterruptedStart(
            $records,
            $visualPreceding ?? $preceding
        )) {
            return true;
        }
        if ($this->pdfComplexColumnSegmentHasIsolatedContinuationFragment($records, $preceding, $following)
            || $this->pdfComplexColumnSegmentHasIsolatedContinuationFragment($records, $visualPreceding, $following)) {
            return true;
        }

        if (!$this->pdfComplexColumnSegmentLooksIncomplete($records)) {
            return false;
        }

        if ($this->pdfComplexColumnSegmentEndsAtForcedInterruptedHyphen($records, $following)) {
            return true;
        }

        if ($this->pdfComplexColumnSegmentIsIsolatedUnresolvedForcedLine($records, $following)) {
            return true;
        }

        if ($this->pdfComplexColumnSegmentHasInterruptedLeadingFragment(
            $records,
            $visualPreceding ?? $preceding
        )
            || $this->pdfComplexColumnSegmentIsFollowedByInterruptedSuffix($records, $following)) {
            return true;
        }

        $hasComplexPage = false;
        foreach ($records as $record) {
            $layout = is_array($record['layout'] ?? null) ? $record['layout'] : [];
            $hasComplexPage = $hasComplexPage
                || ($layout['sourceComplexGeometryPage'] ?? false) === true
                || ($layout['sourceDetachedDiagramEvidencePage'] ?? false) === true;
        }

        $endsAtUnresolvedBoundary = $this->pdfComplexColumnSegmentEndsAtUnresolvedVisualBoundary(
            $records,
            $following,
            $afterFollowing
        );
        if (count($records) >= 2
            && $endsAtUnresolvedBoundary
            && ($hasComplexPage || $this->pdfComplexColumnSegmentHasForcedSameColumnBoundary(
                $records,
                $following,
                $afterFollowing
            ))) {
            return true;
        }

        if ($this->pdfComplexColumnSegmentIsVisuallyDetachedComplexFragment($records, $preceding, $following)) {
            return true;
        }
        return $this->pdfComplexColumnSegmentIsFollowedByDamagedGap($records, $following);
    }

    /**
     * A normal wrapped paragraph advances by at least one line height. When
     * an explicitly interrupted flow has two records in the same body column
     * on one baseline, they are incompatible visual layers rather than a
     * readable sentence. Formula subscripts and diagram labels commonly have
     * this shape.
     *
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     */
    private function pdfComplexColumnSegmentHasInterruptedVisualCollision(array $records): bool
    {
        $interrupted = false;
        foreach ($records as $record) {
            if (($record['layout']['sourceInterruptedColumnRegion'] ?? false) === true) {
                $interrupted = true;
                break;
            }
        }
        if (!$interrupted || count($records) < 2) {
            return false;
        }

        return count($this->pdfComplexSegmentBeforeVisualCollision($records)) < count($records);
    }

    /**
     * A visual line with a recoverable sentence suffix proves that the run
     * immediately before it is a display or formula prefix, not ordinary
     * prose. A colon-ended predecessor has no independently complete text to
     * merge into that suffix; retain an earlier full sentence if present and
     * start the recovered suffix as its own flow.
     *
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     * @param array{text: string, layout: array<string, mixed>|null}|null $following
     */
    private function pdfComplexColumnSegmentIsFollowedByRecoverableSentenceSuffix(
        array $records,
        ?array $following
    ): bool {
        if ($records === [] || $following === null) {
            return false;
        }

        $last = $records[array_key_last($records)];
        $layout = is_array($last['layout'] ?? null) ? $last['layout'] : null;
        $followingLayout = is_array($following['layout'] ?? null) ? $following['layout'] : null;
        if (($followingLayout['sourceSupplementalRecoverableSentenceSuffix'] ?? false) !== true
            || !$this->pdfLayoutHasGeometry($layout)
            || !$this->pdfLayoutHasGeometry($followingLayout)
            || ($layout['page'] ?? null) !== ($followingLayout['page'] ?? null)
            || ($layout['sourceGeometryColumn'] ?? null) !== ($followingLayout['sourceGeometryColumn'] ?? null)
            || preg_match('/:\s*$/u', rtrim($last['text'])) !== 1) {
            return false;
        }

        $fontSize = max(1.0, (float) $layout['fontSize'], (float) $followingLayout['fontSize']);

        return (float) $layout['y1'] - (float) $followingLayout['y1'] <= max(24.0, $fontSize * 3.0);
    }

    /**
     * A short positioned fallback can start at the body edge even when its
     * missing formula or diagram prefix makes it unsafe prose. If the next
     * same-column record is explicitly interrupted and begins as a
     * continuation, discard the isolated fallback rather than promoting it to
     * a heading or a paragraph.
     *
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     * @param array{text: string, layout: array<string, mixed>|null}|null $following
     */
    private function pdfComplexColumnSegmentIsIsolatedInterruptedSupplement(
        array $records,
        ?array $following
    ): bool {
        if (count($records) !== 1 || $following === null) {
            return false;
        }

        $record = $records[0];
        $layout = is_array($record['layout'] ?? null) ? $record['layout'] : null;
        $followingLayout = is_array($following['layout'] ?? null) ? $following['layout'] : null;
        $text = trim($record['text']);
        $followingText = ltrim($following['text']);
        if (!$this->pdfLayoutHasGeometry($layout)
            || !$this->pdfLayoutHasGeometry($followingLayout)
            || ($layout['sourceSupplementalPositioned'] ?? false) !== true
            || ($layout['sourceInterruptedColumnRegion'] ?? false) !== true
            || ($layout['forceBlockBreakBefore'] ?? false) !== true
            || ($layout['page'] ?? null) !== ($followingLayout['page'] ?? null)
            || ($layout['sourceGeometryColumn'] ?? null) !== ($followingLayout['sourceGeometryColumn'] ?? null)
            || $this->lineHasPdfListBlockEvidence($text)
            || $this->lineLooksLikeUrlOnly($text)
            || preg_match('/[.!?]\s*$/u', $text) === 1
            || (($followingLayout['sourceInterruptedColumnRegion'] ?? false) !== true)
            || preg_match('/^(?:[^\p{L}\p{N}]*\p{Ll}|[\)\]\},;:])/u', $followingText) !== 1) {
            return false;
        }

        $fontSize = max(1.0, (float) $layout['fontSize'], (float) $followingLayout['fontSize']);

        return (float) $layout['y1'] - (float) $followingLayout['y1'] <= max(24.0, $fontSize * 3.0);
    }

    /**
     * A punctuated or lower-case line at a forced boundary normally belongs
     * to the end of an omitted visual run. The interruption marker comes from
     * geometry, so this rejects the entire malformed run without relying on
     * vocabulary or a document-specific phrase.
     *
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     * @param array{text: string, layout: array<string, mixed>|null}|null $preceding
     */
    private function pdfComplexColumnSegmentHasInterruptedLeadingFragment(array $records, ?array $preceding = null): bool
    {
        if ($records === []) {
            return false;
        }

        $first = $records[0];
        $layout = is_array($first['layout'] ?? null) ? $first['layout'] : [];
        $text = ltrim($first['text']);

        if (($layout['forceBlockBreakBefore'] ?? false) !== true
            || preg_match('/^(?:[,.;:\)\]\}]|[^\p{L}\p{N}]*\p{Ll})/u', $text) !== 1) {
            return false;
        }
        if (($layout['sourceInterruptedColumnRegion'] ?? false) === true) {
            return true;
        }

        $precedingLayout = is_array($preceding['layout'] ?? null) ? $preceding['layout'] : null;
        if (!$this->pdfLayoutHasGeometry($layout)
            || !$this->pdfLayoutHasGeometry($precedingLayout)
            || ($layout['page'] ?? null) !== ($precedingLayout['page'] ?? null)
            || ($layout['sourceGeometryColumn'] ?? null) !== ($precedingLayout['sourceGeometryColumn'] ?? null)) {
            return false;
        }

        $fontSize = max(1.0, (float) $layout['fontSize'], (float) $precedingLayout['fontSize']);

        return (float) $precedingLayout['y1'] - (float) $layout['y1'] > max(18.0, $fontSize * 1.8)
            && abs((float) $layout['x1'] - (float) $precedingLayout['x1']) >= max(48.0, $fontSize * 5.0);
    }

    /**
     * A one-line interrupted run followed immediately by another forced,
     * interrupted lower-case or punctuated suffix has no complete reading
     * order. Treat both starts as fragments rather than turning either into
     * a paragraph.
     *
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     * @param array{text: string, layout: array<string, mixed>|null}|null $following
     */
    private function pdfComplexColumnSegmentIsFollowedByInterruptedSuffix(array $records, ?array $following): bool
    {
        if (count($records) !== 1 || $following === null) {
            return false;
        }

        $record = $records[0];
        $layout = is_array($record['layout'] ?? null) ? $record['layout'] : null;
        $followingLayout = is_array($following['layout'] ?? null) ? $following['layout'] : null;
        $text = rtrim($record['text']);
        $followingText = ltrim($following['text']);
        if (!$this->pdfLayoutHasGeometry($layout)
            || !$this->pdfLayoutHasGeometry($followingLayout)
            || ($layout['page'] ?? null) !== ($followingLayout['page'] ?? null)
            || ($layout['sourceGeometryColumn'] ?? null) !== ($followingLayout['sourceGeometryColumn'] ?? null)
            || ($layout['forceBlockBreakBefore'] ?? false) !== true
            || ($followingLayout['forceBlockBreakBefore'] ?? false) !== true
            || preg_match('/[.!?;:]\s*$/u', $text) === 1
            || preg_match('/^(?:[,.;:\)\]\}]|[^\p{L}\p{N}]*\p{Ll})/u', $followingText) !== 1) {
            return false;
        }

        $fontSize = max(1.0, (float) $layout['fontSize'], (float) $followingLayout['fontSize']);
        $hasDamageEvidence = ($layout['sourceInterruptedColumnRegion'] ?? false) === true
            || ($followingLayout['sourceInterruptedColumnRegion'] ?? false) === true
            || abs((float) $layout['x1'] - (float) $followingLayout['x1']) >= max(32.0, $fontSize * 3.5);
        if (!$hasDamageEvidence) {
            return false;
        }
        $verticalGap = (float) $layout['y1'] - (float) $followingLayout['y2'];

        return $verticalGap <= max(24.0, $fontSize * 3.0);
    }

    /**
     * A line-end hyphen normally continues directly at the body edge. When
     * its next source record is forcibly separated, marked as interrupted,
     * and shifted deep into the same column, the missing visual run is
     * observable. Keep a complete sentence prefix rather than inventing a
     * hyphenated word across that gap.
     *
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     * @param array{text: string, layout: array<string, mixed>|null}|null $following
     */
    private function pdfComplexColumnSegmentEndsAtForcedInterruptedHyphen(array $records, ?array $following): bool
    {
        if ($records === [] || $following === null) {
            return false;
        }

        $last = $records[array_key_last($records)];
        $layout = is_array($last['layout'] ?? null) ? $last['layout'] : null;
        $followingLayout = is_array($following['layout'] ?? null) ? $following['layout'] : null;
        if (!$this->pdfLayoutHasGeometry($layout)
            || !$this->pdfLayoutHasGeometry($followingLayout)
            || ($layout['page'] ?? null) !== ($followingLayout['page'] ?? null)
            || ($layout['sourceGeometryColumn'] ?? null) !== ($followingLayout['sourceGeometryColumn'] ?? null)
            || preg_match('/[-\x{2010}-\x{2015}]\s*$/u', rtrim($last['text'])) !== 1
            || ($layout['sourceInterruptedColumnRegion'] ?? false) !== true
            || ($followingLayout['forceBlockBreakBefore'] ?? false) !== true
            || !$this->pdfComplexColumnRecordHasDamageEvidence($following)) {
            return false;
        }

        $fontSize = max(1.0, (float) $layout['fontSize'], (float) $followingLayout['fontSize']);

        return abs((float) $followingLayout['x1'] - (float) $layout['x1']) >= max(24.0, $fontSize * 2.4);
    }

    /**
     * A complex figure may leave one indented source line between otherwise
     * complete body blocks. Its missing bullet, prefix, or continuation means
     * it cannot safely become prose. The evidence is purely geometric: the
     * line is isolated, begins after a completed body sentence, is indented,
     * and is followed by another detached visual region.
     *
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     * @param array{text: string, layout: array<string, mixed>|null}|null $preceding
     * @param array{text: string, layout: array<string, mixed>|null}|null $following
     */
    private function pdfComplexColumnSegmentIsVisuallyDetachedComplexFragment(
        array $records,
        ?array $preceding,
        ?array $following
    ): bool {
        if (count($records) !== 1) {
            return false;
        }

        $record = $records[0];
        $layout = is_array($record['layout'] ?? null) ? $record['layout'] : null;
        $text = ltrim($record['text']);
        if (!$this->pdfLayoutHasGeometry($layout)
            || (($layout['sourceComplexGeometryPage'] ?? false) !== true
                && ($layout['sourceDetachedDiagramEvidencePage'] ?? false) !== true)
            || $text === ''
            || preg_match('/[.!?;:]\s*$/u', rtrim($text)) === 1) {
            return false;
        }

        if (preg_match('/^[,.;:\)\]\}]/u', $text) === 1) {
            return true;
        }
        if (($layout['forceBlockBreakBefore'] ?? false) !== true || $preceding === null || $following === null) {
            return false;
        }

        $precedingLayout = is_array($preceding['layout'] ?? null) ? $preceding['layout'] : null;
        $followingLayout = is_array($following['layout'] ?? null) ? $following['layout'] : null;
        if (!$this->pdfLayoutHasGeometry($precedingLayout)
            || !$this->pdfLayoutHasGeometry($followingLayout)
            || ($precedingLayout['page'] ?? null) !== ($layout['page'] ?? null)
            || ($followingLayout['page'] ?? null) !== ($layout['page'] ?? null)
            || ($precedingLayout['sourceGeometryColumn'] ?? null) !== ($layout['sourceGeometryColumn'] ?? null)
            || ($followingLayout['sourceGeometryColumn'] ?? null) !== ($layout['sourceGeometryColumn'] ?? null)
            || preg_match('/[.!?;:]\s*$/u', rtrim($preceding['text'])) !== 1
            || preg_match('/^[^\p{L}\p{N}]*\p{Lu}/u', $text) !== 1
            || preg_match('/^[^\p{L}\p{N}]*\p{Lu}/u', ltrim($following['text'])) !== 1
            || ($followingLayout['forceBlockBreakBefore'] ?? false) !== true) {
            return false;
        }

        $fontSize = max(1.0, (float) $precedingLayout['fontSize'], (float) $layout['fontSize']);

        return (float) $layout['x1'] - (float) $precedingLayout['x1'] >= max(7.0, $fontSize * 0.80);
    }

    /**
     * A source line can be stranded between two visual regions even on a
     * page without a detected figure. The interrupted marker, large gap from
     * the preceding body flow, and a forced block boundary together prove a
     * missing run. A lower-case suffix with the same displaced shape is the
     * trailing half of that fragment, not an independent paragraph.
     *
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     * @param array{text: string, layout: array<string, mixed>|null}|null $preceding
     */
    private function pdfComplexColumnSegmentHasDetachedInterruptedStart(array $records, ?array $preceding): bool
    {
        if (count($records) !== 1 || $preceding === null) {
            return false;
        }

        $record = $records[0];
        $layout = is_array($record['layout'] ?? null) ? $record['layout'] : null;
        $precedingLayout = is_array($preceding['layout'] ?? null) ? $preceding['layout'] : null;
        $text = ltrim($record['text']);
        if (!$this->pdfLayoutHasGeometry($layout)
            || !$this->pdfLayoutHasGeometry($precedingLayout)
            || ($layout['page'] ?? null) !== ($precedingLayout['page'] ?? null)
            || ($layout['sourceGeometryColumn'] ?? null) !== ($precedingLayout['sourceGeometryColumn'] ?? null)) {
            return false;
        }

        $fontSize = max(1.0, (float) $layout['fontSize'], (float) $precedingLayout['fontSize']);
        $gap = (float) $precedingLayout['y1'] - (float) $layout['y2'];
        if (($layout['sourceInterruptedColumnRegion'] ?? false) === true
            && $gap > max(22.0, $fontSize * 3.3)) {
            return true;
        }

        return ($layout['forceBlockBreakBefore'] ?? false) === true
            && preg_match('/^[,.;:\)\]\}]|^[^\p{L}\p{N}]*\p{Ll}/u', $text) === 1
            && abs((float) $layout['x1'] - (float) $precedingLayout['x1']) >= max(24.0, $fontSize * 2.4);
    }

    /**
     * A lower-case or punctuation-led record after a large empty band cannot
     * safely begin a new prose block. If it has no nearby continuation in the
     * same visual column, the omitted material is observable from geometry;
     * retain a later complete sentence if one exists, otherwise omit it.
     *
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     * @param array{text: string, layout: array<string, mixed>|null}|null $preceding
     * @param array{text: string, layout: array<string, mixed>|null}|null $following
     */
    private function pdfComplexColumnSegmentHasIsolatedContinuationFragment(
        array $records,
        ?array $preceding,
        ?array $following
    ): bool {
        if (count($records) !== 1 || $preceding === null) {
            return false;
        }

        $record = $records[0];
        $layout = is_array($record['layout'] ?? null) ? $record['layout'] : null;
        $precedingLayout = is_array($preceding['layout'] ?? null) ? $preceding['layout'] : null;
        $text = ltrim($record['text']);
        if (!$this->pdfLayoutHasGeometry($layout)
            || !$this->pdfLayoutHasGeometry($precedingLayout)
            || ($layout['sourceStructuredGeometry'] ?? false) !== true
            || ($layout['page'] ?? null) !== ($precedingLayout['page'] ?? null)
            || ($layout['sourceGeometryColumn'] ?? null) !== ($precedingLayout['sourceGeometryColumn'] ?? null)
            || $this->lineHasPdfListBlockEvidence($text)
            || $this->lineLooksLikeUrlOnly($text)
            || preg_match('/^(?:[^\p{L}\p{N}]*\p{Ll}|[,.;:\)\]\}])/u', $text) !== 1) {
            return false;
        }

        $fontSize = max(1.0, (float) $layout['fontSize'], (float) $precedingLayout['fontSize']);
        $verticalGap = (float) $precedingLayout['y1'] - (float) $layout['y2'];
        if ($verticalGap <= max(18.0, $fontSize * 2.2)) {
            return false;
        }

        if ($following === null) {
            return true;
        }

        $followingLayout = is_array($following['layout'] ?? null) ? $following['layout'] : null;
        return !$this->pdfLayoutHasGeometry($followingLayout)
            || ($followingLayout['page'] ?? null) !== ($layout['page'] ?? null)
            || ($followingLayout['sourceGeometryColumn'] ?? null) !== ($layout['sourceGeometryColumn'] ?? null)
            || !$this->pdfComplexColumnRecordsShareFlow($record, $following);
    }

    /**
     * A lone unfinished body line followed by a distant forced block cannot be
     * a wrapped continuation. This removes clipped prose tails while keeping
     * headings, lists, and ordinary paragraph boundaries intact.
     *
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     * @param array{text: string, layout: array<string, mixed>|null}|null $following
     */
    private function pdfComplexColumnSegmentIsIsolatedUnresolvedForcedLine(array $records, ?array $following): bool
    {
        if (count($records) !== 1 || $following === null) {
            return false;
        }

        $record = $records[0];
        $layout = is_array($record['layout'] ?? null) ? $record['layout'] : null;
        $followingLayout = is_array($following['layout'] ?? null) ? $following['layout'] : null;
        $text = trim($record['text']);
        $followingText = ltrim($following['text']);
        if (!$this->pdfLayoutHasGeometry($layout)
            || !$this->pdfLayoutHasGeometry($followingLayout)
            || ($layout['sourceStructuredGeometry'] ?? false) !== true
            || ($layout['page'] ?? null) !== ($followingLayout['page'] ?? null)
            || ($layout['sourceGeometryColumn'] ?? null) !== ($followingLayout['sourceGeometryColumn'] ?? null)
            || ($layout['forceBlockBreakBefore'] ?? false) !== true
            || ($followingLayout['forceBlockBreakBefore'] ?? false) !== true
            || $this->lineHasPdfListBlockEvidence($text)
            || $this->lineLooksLikeUrlOnly($text)
            || $this->repairedLineLooksLikeSectionLabel($text)
            || $this->lineLooksLikePdfAllCapsDisplayText($text)
            || count($this->pdfLineWordTokens($text)) < 8
            || preg_match('/[.!?;:]\s*$/u', $text) === 1
            || preg_match('/[-\x{2010}-\x{2015}]\s*$/u', $text) === 1
            || preg_match('/^[^\p{L}\p{N}]*\p{Lu}/u', $followingText) !== 1) {
            return false;
        }

        $fontSize = max(1.0, (float) $layout['fontSize'], (float) $followingLayout['fontSize']);

        return (float) $layout['y1'] - (float) $followingLayout['y1'] > max(24.0, $fontSize * 2.6);
    }

    /**
     * @param array{text: string, layout: array<string, mixed>|null} $record
     * @param array<string, mixed>|null $discardedLayout
     */
    private function pdfComplexColumnRecordContinuesDroppedFlow(array $record, ?array $discardedLayout): bool
    {
        $layout = is_array($record['layout'] ?? null) ? $record['layout'] : null;
        $text = ltrim($record['text']);
        $continuesForcedInterruption = is_array($discardedLayout)
            && ($discardedLayout['sourceInterruptedColumnRegion'] ?? false) === true
            && ($discardedLayout['forceBlockBreakBefore'] ?? false) === true;
        if ($this->pdfLayoutHasGeometry($layout)
            && $this->pdfLayoutHasGeometry($discardedLayout)
            && $continuesForcedInterruption
            && ($layout['page'] ?? null) === ($discardedLayout['page'] ?? null)
            && ($layout['sourceGeometryColumn'] ?? null) === ($discardedLayout['sourceGeometryColumn'] ?? null)
            && !$this->lineHasPdfListBlockEvidence($text)
            && !$this->lineLooksLikeUrlOnly($text)
            && preg_match('/^[^\p{L}\p{N}]*\p{Lu}/u', $text) === 1) {
            $fontSize = max(1.0, (float) $layout['fontSize'], (float) $discardedLayout['fontSize']);
            if (abs((float) $layout['y1'] - (float) $discardedLayout['y1']) <= max(3.0, $fontSize * 0.45)
                && abs((float) $layout['x1'] - (float) $discardedLayout['x1']) <= max(12.0, $fontSize * 1.25)) {
                return true;
            }
        }
        if (!$this->pdfLayoutHasGeometry($layout)
            || !$this->pdfLayoutHasGeometry($discardedLayout)
            || ($layout['page'] ?? null) !== ($discardedLayout['page'] ?? null)
            || ($layout['sourceGeometryColumn'] ?? null) !== ($discardedLayout['sourceGeometryColumn'] ?? null)
            || (($layout['forceBlockBreakBefore'] ?? false) !== true && !$continuesForcedInterruption)
            || $this->lineHasPdfListBlockEvidence($text)
            || $this->lineLooksLikeUrlOnly($text)
            || preg_match('/^(?:[^\p{L}\p{N}]*\p{Ll}|[,.;:\)\]\}])/u', $text) !== 1) {
            return false;
        }

        $fontSize = max(1.0, (float) $layout['fontSize'], (float) $discardedLayout['fontSize']);
        $step = (float) $discardedLayout['y1'] - (float) $layout['y1'];

        return $step >= $fontSize * 0.30 && $step <= max(24.0, $fontSize * 3.0)
            && abs((float) $layout['x1'] - (float) $discardedLayout['x1']) >= max(32.0, $fontSize * 3.5);
    }

    /**
     * A short visual region may have a missing line between it and the next
     * source line. This is a local signal: the next line is independently
     * marked as interrupted and the observed vertical gap is much larger than
     * the normal text rhythm. It is stronger evidence than a generic column
     * break, which is common in ordinary papers.
     *
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     * @param array{text: string, layout: array<string, mixed>|null}|null $following
     */
    private function pdfComplexColumnSegmentIsFollowedByDamagedGap(array $records, ?array $following): bool
    {
        if ($records === [] || $following === null) {
            return false;
        }

        $record = $records[array_key_last($records)];
        $layout = is_array($record['layout'] ?? null) ? $record['layout'] : null;
        $followingLayout = is_array($following['layout'] ?? null) ? $following['layout'] : null;
        if (!$this->pdfLayoutHasGeometry($layout)
            || !$this->pdfLayoutHasGeometry($followingLayout)
            || ($layout['page'] ?? null) !== ($followingLayout['page'] ?? null)
            || ($layout['sourceGeometryColumn'] ?? null) !== ($followingLayout['sourceGeometryColumn'] ?? null)
            || !$this->pdfComplexColumnRecordHasDamageEvidence($following)) {
            return false;
        }

        $fontSize = max(1.0, (float) $layout['fontSize'], (float) $followingLayout['fontSize']);
        $verticalGap = (float) $layout['y1'] - (float) $followingLayout['y2'];

        return $verticalGap > max(18.0, $fontSize * 2.2);
    }

    /**
     * A figure can replace the opening words of a visual flow while leaving a
     * later, complete sentence on the same physical line. Retain that sentence
     * when punctuation makes the boundary explicit; otherwise leave the
     * fragment for the ordinary incomplete-flow rejection below.
     *
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     * @return list<array{text: string, layout: array<string, mixed>|null}>
     */
    private function trimLeadingIncompletePdfComplexSegment(array $records): array
    {
        if ($records === []) {
            return $records;
        }

        $first = ltrim($records[0]['text']);
        if (preg_match('/^[,.;:]\s+(\p{Lu}[\s\S]*)$/u', $first, $matches) === 1) {
            $records[0]['text'] = $matches[1];

            return $records;
        }
        if (preg_match('/^[^\p{L}\p{N}]*\p{Ll}/u', $first) !== 1
            || ($records[0]['layout']['sourceInterruptedColumnRegion'] ?? false) !== true
            || ($records[0]['layout']['forceBlockBreakBefore'] ?? false) !== true) {
            return $records;
        }

        foreach ($records as $index => $record) {
            if (preg_match('/[.!?]\s+((?:(?:[\(\[]\s*)?(?:\p{N}+\s*[\)\]]\s*)?)?\p{Lu}[\s\S]*)$/u', $record['text'], $matches) !== 1
                || count($this->pdfLineWordTokens($matches[1])) < 5) {
                continue;
            }

            $trimmed = array_slice($records, $index);
            $trimmed[0]['text'] = $matches[1];

            return $trimmed;
        }

        return $records;
    }

    /**
     * @param array{text: string, layout: array<string, mixed>|null} $previous
     * @param array{text: string, layout: array<string, mixed>|null} $current
     */
    private function pdfComplexColumnRecordsShareFlow(array $previous, array $current): bool
    {
        $previousLayout = $previous['layout'];
        $currentLayout = $current['layout'];
        if (!$this->pdfLayoutHasGeometry($previousLayout) || !$this->pdfLayoutHasGeometry($currentLayout)
            || ($previousLayout['page'] ?? null) !== ($currentLayout['page'] ?? null)) {
            return false;
        }

        $fontSize = max((float) $previousLayout['fontSize'], (float) $currentLayout['fontSize'], 1.0);
        $previousColumn = $previousLayout['sourceGeometryColumn'] ?? null;
        $currentColumn = $currentLayout['sourceGeometryColumn'] ?? null;
        if ($previousColumn !== null || $currentColumn !== null) {
            if ($previousColumn === null || $currentColumn === null || $previousColumn !== $currentColumn) {
                return false;
            }
        } elseif (abs((float) $previousLayout['x1'] - (float) $currentLayout['x1']) > max(16.0, $fontSize * 1.5)) {
            return false;
        }
        $step = (float) $previousLayout['y1'] - (float) $currentLayout['y1'];

        return $step >= -$fontSize * 0.35 && $step <= max(16.0, $fontSize * 1.75);
    }

    /**
     * Figure-heavy pages may lose the last visual lines of one paragraph
     * while leaving the preceding paragraph intact. Treat a terminal line
     * followed by a new capitalized visual line as a hard boundary before
     * deciding whether either flow is incomplete.
     *
     * @param array{text: string, layout: array<string, mixed>|null} $previous
     * @param array{text: string, layout: array<string, mixed>|null} $current
     */
    private function pdfComplexColumnRecordsHaveSentenceBoundary(array $previous, array $current): bool
    {
        return preg_match('/[.!?;:]\s*$/u', rtrim($previous['text'])) === 1
            && preg_match('/^[^\p{L}\p{N}]*\p{Lu}/u', ltrim($current['text'])) === 1;
    }

    /**
     * A broken visual line can be followed immediately by an otherwise normal
     * paragraph. Once the next line starts a new capitalized flow, retaining
     * both would invent a sentence across the missing glyphs. The earlier line
     * already has structural damage evidence; this only makes that boundary
     * explicit to the segmenter.
     *
     * @param array{text: string, layout: array<string, mixed>|null} $previous
     * @param array{text: string, layout: array<string, mixed>|null} $current
     */
    private function pdfComplexColumnRecordsHaveDamagedFlowBoundary(array $previous, array $current): bool
    {
        if (!$this->pdfComplexColumnRecordHasDamageEvidence($previous)) {
            return false;
        }

        $previousText = rtrim($previous['text']);
        $currentText = ltrim($current['text']);
        if ($previousText === '' || $currentText === ''
            || preg_match('/[.!?;:]\s*$/u', $previousText) === 1
            || preg_match('/^[^\p{L}\p{N}]*\p{Lu}/u', $currentText) !== 1) {
            return false;
        }

        $previousLayout = $previous['layout'];
        $currentLayout = $current['layout'];

        return $this->pdfLayoutHasGeometry($previousLayout)
            && $this->pdfLayoutHasGeometry($currentLayout)
            && ($previousLayout['page'] ?? null) === ($currentLayout['page'] ?? null)
            && ($previousLayout['sourceGeometryColumn'] ?? null) === ($currentLayout['sourceGeometryColumn'] ?? null);
    }

    /**
     * An offset inline fragment followed by a normal lower-case body line is
     * an explicit visual boundary. The preceding flow cannot safely cross it
     * when it ends mid-sentence. This deliberately excludes ordinary forced
     * paragraph and cross-column boundaries.
     *
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     * @param array{text: string, layout: array<string, mixed>|null}|null $following
     * @param array{text: string, layout: array<string, mixed>|null}|null $afterFollowing
     */
    private function pdfComplexColumnSegmentHasForcedSameColumnBoundary(
        array $records,
        ?array $following,
        ?array $afterFollowing
    ): bool
    {
        if ($records === [] || $following === null || $afterFollowing === null) {
            return false;
        }

        $last = $records[array_key_last($records)];
        $lastLayout = is_array($last['layout'] ?? null) ? $last['layout'] : null;
        $followingLayout = is_array($following['layout'] ?? null) ? $following['layout'] : null;
        $afterFollowingLayout = is_array($afterFollowing['layout'] ?? null) ? $afterFollowing['layout'] : null;
        if (!$this->pdfLayoutHasGeometry($lastLayout)
            || !$this->pdfLayoutHasGeometry($followingLayout)
            || !$this->pdfLayoutHasGeometry($afterFollowingLayout)
            || ($lastLayout['page'] ?? null) !== ($followingLayout['page'] ?? null)
            || ($lastLayout['page'] ?? null) !== ($afterFollowingLayout['page'] ?? null)
            || ($lastLayout['sourceGeometryColumn'] ?? null) !== ($followingLayout['sourceGeometryColumn'] ?? null)
            || ($lastLayout['sourceGeometryColumn'] ?? null) !== ($afterFollowingLayout['sourceGeometryColumn'] ?? null)
            || ($followingLayout['forceBlockBreakBefore'] ?? false) !== true
            || abs((float) $followingLayout['x1'] - (float) $lastLayout['x1']) < max(
                32.0,
                max((float) $lastLayout['fontSize'], (float) $followingLayout['fontSize'], 1.0) * 3.5
            )) {
            return false;
        }

        $followingText = trim($following['text']);
        return preg_match('/^[^\p{L}\p{N}]*\p{Lu}/u', $followingText) === 1
            && preg_match('/[.!?;:]\s*$/u', $followingText) !== 1
            && count($this->pdfLineWordTokens($followingText)) <= 7
            && preg_match('/^[^\p{L}\p{N}]*\p{Ll}/u', ltrim($afterFollowing['text'])) === 1
            && $this->pdfComplexColumnRecordsShareFlow($following, $afterFollowing);
    }

    /**
     * A stable text flow that stops mid-sentence at a forced visual break has
     * lost content. This catches clipped paragraph tails beside figures, code,
     * and section boundaries even when the extractor did not leave a separate
     * orphan glyph fragment to mark the prior line as damaged.
     *
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     * @param array{text: string, layout: array<string, mixed>|null}|null $following
     * @param array{text: string, layout: array<string, mixed>|null}|null $afterFollowing
     */
    private function pdfComplexColumnSegmentEndsAtUnresolvedVisualBoundary(
        array $records,
        ?array $following,
        ?array $afterFollowing = null
    ): bool
    {
        if ($records === [] || $following === null) {
            return false;
        }

        $last = $records[array_key_last($records)];
        $lastLayout = $last['layout'];
        $lastText = rtrim($last['text']);
        if (!$this->pdfLayoutHasGeometry($lastLayout)
            || $lastText === ''
            || $this->lineHasPdfListBlockEvidence($lastText)
            || $this->lineLooksLikeUrlOnly($lastText)
            || $this->repairedLineLooksLikeSectionLabel($lastText)
            || $this->lineLooksLikePdfAllCapsDisplayText($lastText)
            || preg_match('/[.!?;:]\s*$/u', $lastText) === 1
            || preg_match('/[-\x{2010}-\x{2015}]\s*$/u', $lastText) === 1) {
            return false;
        }
        if (count($records) < 2 && $this->length($lastText) < 32) {
            return false;
        }

        $followingLayout = $following['layout'];
        $followingText = ltrim($following['text']);
        if (($followingLayout['code'] ?? false) === true) {
            return true;
        }
        if (($followingLayout['forceBlockBreakBefore'] ?? false) === true
            && $this->pdfComplexColumnRecordHasDamageEvidence($following)) {
            return true;
        }
        if (!$this->pdfLayoutHasGeometry($followingLayout)
            || ($followingLayout['page'] ?? null) !== ($lastLayout['page'] ?? null)) {
            return false;
        }

        $fontSize = max(1.0, (float) $lastLayout['fontSize'], (float) $followingLayout['fontSize']);
        $lastColumn = $lastLayout['sourceGeometryColumn'] ?? null;
        $followingColumn = $followingLayout['sourceGeometryColumn'] ?? null;
        if ($lastColumn !== null || $followingColumn !== null) {
            if ($lastColumn === null || $followingColumn === null) {
                return ($lastLayout['sourceStructuredGeometry'] ?? false) === true
                    && ($followingLayout['code'] ?? false) !== true
                    && !isset($followingLayout['sourcePdfTableGroup']);
            }
            if ($lastColumn !== $followingColumn) {
                if ($this->lineLooksLikeCompletePdfCaption($followingText)) {
                    return true;
                }
                if (preg_match('/^[^\p{L}\p{N}]*\p{Lu}/u', $followingText) === 1
                    && (float) $followingLayout['y1'] - (float) $lastLayout['y1'] > max(24.0, $fontSize * 3.0)) {
                    return true;
                }
                if ($this->pdfComplexColumnRecordHasCodeSyntax($following)
                    || ($afterFollowing !== null && $this->pdfComplexColumnRecordHasCodeSyntax($afterFollowing))) {
                    return true;
                }

                return preg_match('/^[^\p{L}\p{N}]*\p{Ll}/u', $followingText) === 1
                    && $this->pdfComplexColumnContinuationIsIsolated($following, $afterFollowing);
            }
        } elseif (abs((float) $lastLayout['x1'] - (float) $followingLayout['x1']) > max(16.0, $fontSize * 1.5)) {
            return false;
        }
        if ($followingText === ''
            || preg_match('/^[^\p{L}\p{N}]*\p{Lu}/u', $followingText) !== 1) {
            return false;
        }
        if (($followingLayout['forceBlockBreakBefore'] ?? false) === true) {
            return true;
        }
        $verticalGap = (float) $lastLayout['y1'] - (float) $followingLayout['y2'];

        return $verticalGap > max(18.0, $fontSize * 2.2);
    }

    /**
     * A code listing in an adjacent visual column is a hard semantic boundary
     * for prose. The signal is punctuation-based so it also works for code in
     * languages other than the document's natural language.
     *
     * @param array{text: string, layout: array<string, mixed>|null} $record
     */
    private function pdfComplexColumnRecordHasCodeSyntax(array $record): bool
    {
        $layout = is_array($record['layout'] ?? null) ? $record['layout'] : [];

        return ($layout['code'] ?? false) === true
            || $this->positionedCodeSyntaxEvidence((string) ($record['text'] ?? '')) >= 1;
    }

    /**
     * The first line in a neighboring column can be a real continuation only
     * when it itself leads into the next local visual line. A lone lower-case
     * fragment followed by a new block proves that the cross-column paragraph
     * was clipped between the two regions.
     *
     * @param array{text: string, layout: array<string, mixed>|null} $continuation
     * @param array{text: string, layout: array<string, mixed>|null}|null $next
     */
    private function pdfComplexColumnContinuationIsIsolated(array $continuation, ?array $next): bool
    {
        if ($next === null) {
            return false;
        }

        $continuationLayout = $continuation['layout'];
        $nextLayout = $next['layout'];
        if (($nextLayout['code'] ?? false) === true) {
            return true;
        }
        $continuationText = rtrim($continuation['text']);
        $nextText = ltrim($next['text']);
        if ($continuationText === '' || $nextText === '') {
            return true;
        }
        if (($nextLayout['forceBlockBreakBefore'] ?? false) === true
            && preg_match('/^[^\p{L}\p{N}]*\p{Lu}/u', $nextText) === 1) {
            return true;
        }

        return !$this->pdfComplexColumnRecordsShareFlow($continuation, $next)
            && !$this->pdfComplexColumnRecordsHaveSentenceBoundary($continuation, $next);
    }

    /**
     * Positioned captions and figure-adjacent prose occasionally survive as a
     * lone, unfinished line at the end of a page. Unlike a structured body
     * column, there is no neighboring flow that can prove a cross-page
     * continuation, so emitting that line would fabricate a dangling sentence.
     *
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     * @param array{text: string, layout: array<string, mixed>|null}|null $following
     */
    private function pdfComplexColumnSegmentIsStandaloneUnstructuredFragment(array $records, ?array $following): bool
    {
        if ($records === []) {
            return false;
        }

        $last = $records[array_key_last($records)];
        $layout = $last['layout'];
        $text = rtrim($last['text']);
        if (!$this->pdfLayoutHasGeometry($layout)
            || ($layout['sourceStructuredGeometry'] ?? false) === true
            || ($layout['forceBlockBreakBefore'] ?? false) !== true
            || $text === ''
            || $this->lineHasPdfListBlockEvidence($text)
            || $this->lineLooksLikeUrlOnly($text)
            || $this->lineLooksLikeCompletePdfCaption($text)
            || $this->repairedLineLooksLikeSectionLabel($text)
            || preg_match('/[.!?;:]\s*$/u', $text) === 1
            || $this->length($text) < 48
            || count($this->pdfLineWordTokens($text)) < 8) {
            return false;
        }

        if ($following === null) {
            return true;
        }

        $followingLayout = $following['layout'];

        return $this->pdfLayoutHasGeometry($followingLayout)
            && ($followingLayout['page'] ?? null) !== ($layout['page'] ?? null);
    }

    /**
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     */
    private function pdfComplexColumnSegmentLooksIncomplete(array $records): bool
    {
        $first = trim($records[0]['text']);
        $last = trim($records[array_key_last($records)]['text']);
        if ($first === '' || $last === '') {
            return true;
        }
        $interruptedColumnRegion = count(array_filter(
            $records,
            static fn (array $record): bool => ($record['layout']['sourceInterruptedColumnRegion'] ?? false) === true
        )) > 0;
        if ($this->lineHasPdfListBlockEvidence($first)
            || $this->lineLooksLikeUrlOnly($first)
            || $this->lineLooksLikePdfAllCapsDisplayText($first)) {
            return false;
        }
        if ($this->repairedLineLooksLikeSectionLabel($first)
            && count($records) === 1) {
            return false;
        }
        if (preg_match('/^(?:[,.;:\)\]\}]|\()/u', $first) === 1) {
            return true;
        }
        if (preg_match('/^[^\p{L}\p{N}]*\p{Ll}/u', $first) === 1) {
            return true;
        }
        if (preg_match('/[.!?]\s*$/u', $last) === 1) {
            return false;
        }
        if (preg_match('/[;:]\s*$/u', $last) === 1) {
            return $interruptedColumnRegion;
        }
        if (preg_match('/[-\x{2010}-\x{2015}]\s*$/u', $last) === 1) {
            return $interruptedColumnRegion;
        }

        return true;
    }

    /**
     * Diagram-heavy pages can leave one or two body-looking lines stranded
     * between a figure and the next paragraph. Without their missing visual
     * neighbors they read as a fabricated sentence, so retain only complete
     * flows and ordinary multi-line prose.
     *
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     * @return list<array{text: string, layout: array<string, mixed>|null}>
     */
    private function removeIsolatedPdfDiagramFlowFragments(array $records): array
    {
        $filtered = [];
        $segment = [];
        $flushSegment = function () use (&$filtered, &$segment): void {
            if ($segment === []) {
                return;
            }
            if (!$this->pdfDetachedDiagramFlowFragmentShouldBeDropped($segment)) {
                foreach ($segment as $record) {
                    $filtered[] = $record;
                }
            }
            $segment = [];
        };

        foreach ($records as $record) {
            $layout = is_array($record['layout'] ?? null) ? $record['layout'] : null;
            if (($layout['sourceStructuredGeometry'] ?? false) !== true
                || ($layout['sourceDetachedDiagramEvidencePage'] ?? false) !== true) {
                $flushSegment();
                $filtered[] = $record;
                continue;
            }

            $last = $segment === [] ? null : $segment[array_key_last($segment)];
            if ($last === null
                || (($layout['forceBlockBreakBefore'] ?? false) !== true
                    && $this->pdfComplexColumnRecordsShareFlow($last, $record))) {
                $segment[] = $record;
                continue;
            }
            $flushSegment();
            $segment[] = $record;
        }
        $flushSegment();

        return $filtered;
    }

    /**
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     */
    private function pdfDetachedDiagramFlowFragmentShouldBeDropped(array $records): bool
    {
        if (count($records) === 0 || count($records) > 2) {
            return false;
        }

        $first = trim($records[0]['text']);
        $last = trim($records[array_key_last($records)]['text']);
        if ($first === '' || $last === ''
            || $this->lineHasPdfListBlockEvidence($first)
            || $this->lineLooksLikeUrlOnly($first)
            || $this->repairedLineLooksLikeSectionLabel($first)
            || $this->lineLooksLikePdfAllCapsDisplayText($first)) {
            return false;
        }

        $startsLowercase = preg_match('/^[^\p{L}\p{N}]*\p{Ll}/u', $first) === 1;
        $endsComplete = preg_match('/[.!?;:]\s*$/u', $last) === 1
            || preg_match('/[-\x{2010}-\x{2015}]\s*$/u', $last) === 1;

        return $startsLowercase || !$endsComplete;
    }

    /**
     * Source text that could not be matched to a positioned line is normally
     * retained. On a geometry-confirmed page, however, a run of repeated,
     * compact, unplaceable labels is almost certainly diagram text rather
     * than a prose paragraph. Drop only that narrow shape; regular source
     * text and lists have no such marker and remain untouched.
     *
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     * @return list<array{text: string, layout: array<string, mixed>|null}>
     */
    private function removeLowCoherencePdfUnpositionedLabelRegions(array $records): array
    {
        $filtered = [];
        $candidate = [];
        $flushCandidate = function () use (&$filtered, &$candidate): void {
            if ($candidate === []) {
                return;
            }
            if (!$this->pdfUnpositionedLabelClusterShouldBeDropped($candidate)) {
                foreach ($candidate as $record) {
                    $filtered[] = $record;
                }
            }
            $candidate = [];
        };

        foreach ($records as $record) {
            $layout = is_array($record['layout'] ?? null) ? $record['layout'] : null;
            if (($layout['sourceUnmatchedFallback'] ?? false) !== true
                || (($layout['sourceComplexGeometryPage'] ?? false) !== true
                    && ($layout['sourceDetachedDiagramEvidencePage'] ?? false) !== true)) {
                $flushCandidate();
                $filtered[] = $record;
                continue;
            }
            $candidate[] = $record;
        }
        $flushCandidate();

        return $filtered;
    }

    /**
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     */
    private function pdfUnpositionedLabelClusterShouldBeDropped(array $records): bool
    {
        if (count($records) < 5) {
            return false;
        }

        $shortLabels = 0;
        $repeated = [];
        foreach ($records as $record) {
            $line = trim($record['text']);
            if ($this->lineHasPdfListBlockEvidence($line)
                || $this->lineLooksLikeUrlOnly($line)
                || preg_match('/[.!?;:]\s*$/u', $line) === 1) {
                return false;
            }
            $compact = preg_replace('/\s+/u', '', $line) ?? '';
            if ($this->length($compact) <= 18 && count($this->pdfLineWordTokens($line)) <= 4) {
                $shortLabels++;
            }
            $key = $this->pdfComparableLineText($line);
            if ($key !== '') {
                $repeated[$key] = ($repeated[$key] ?? 0) + 1;
            }
        }

        return $shortLabels / count($records) >= 0.80
            && ($repeated === [] ? 0 : max($repeated)) >= 3;
    }

    private function lineLooksLikePdfDiagramLabel(string $line): bool
    {
        $line = trim($line);
        if ($line === '' || $this->length($line) > 36 || preg_match('/[.!?]\s*$/u', $line) === 1) {
            return false;
        }
        if (preg_match('/[{};]|:=|=>|->|\/\//u', $line) === 1) {
            return false;
        }

        return count($this->pdfLineWordTokens($line)) <= 5;
    }

    /**
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     */
    private function pdfDiagramLabelClusterShouldBeDropped(array $records): bool
    {
        $count = count($records);
        if ($count < 6) {
            return false;
        }

        $shortLabels = 0;
        $singleLetterFragments = 0;
        $lowercaseFragments = 0;
        $fragmentTransitions = 0;
        $previousWasShortPrefix = false;
        foreach ($records as $record) {
            $line = trim($record['text']);
            if ($this->length($line) <= 24) {
                $shortLabels++;
            }

            $startsLowercaseFragment = preg_match('/^\p{Ll}{1,8}(?=\s|$)/u', $line) === 1;
            $isShortPrefix = preg_match('/^\p{L}{1,2}$/u', $line) === 1;
            if ($isShortPrefix) {
                $singleLetterFragments++;
            }
            if ($startsLowercaseFragment) {
                $lowercaseFragments++;
            }
            if ($previousWasShortPrefix && $startsLowercaseFragment) {
                $fragmentTransitions++;
            }
            $previousWasShortPrefix = $isShortPrefix || $startsLowercaseFragment;
        }

        return $shortLabels / $count >= 0.80
            && $singleLetterFragments >= 2
            && $lowercaseFragments >= 2
            && $fragmentTransitions >= 2;
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
        if (($layout['code'] ?? false) === true) {
            return false;
        }

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
        if (!$this->pdfLayoutHasGeometry($layout) || $this->lineLooksLikePdfListItem($line) || $this->lineLooksLikeUrlOnly($line)) {
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


    private function repairGluedProseLine(string $line): string
    {
        $line = trim($line);
        if ($line === '') {
            return '';
        }

        $line = $this->repairPdfMisorderedDiacritics($line);
        $line = $this->removeStandaloneBraceArtifacts($line);
        $line = $this->repairSplitUrlWhitespace($line);
        // Source whitespace beside a soft hyphen is not, by itself, proof
        // that the hyphen was a discretionary line break. The merge stage
        // deletes one only when the two exact source/layout records share a
        // sourcePdfWrappedHyphenPairAfter/Before markers. Every remaining
        // soft hyphen is therefore rendered as a visible separator,
        // including extractor whitespace around the same in-word boundary.
        $line = preg_replace('/\s*\x{00AD}\s*/u', '-', $line) ?? $line;
        $lineHasWordSpacing = preg_match('/\p{L}\s+\p{L}/u', $line) === 1;
        $line = preg_replace('/(?<=\S) +(?=[.,!?\)\]])/u', '', $line) ?? $line;
        // A valid thousands separator or clock colon is not punctuation
        // followed by a missing prose space (for example "10,000" or
        // "12:30"). Check the full numeric form instead of preserving every
        // digit-to-digit comma or colon: "2019,2020" still needs a space.
        $line = preg_replace_callback(
            '/([,;:!?])(?=\S)/u',
            function (array $matches) use ($line): string {
                $punctuation = $matches[1][0];
                $offset = $matches[1][1];

                return $this->pdfCompactNumericPunctuationAt($line, $offset, $punctuation)
                    ? $punctuation
                    : $punctuation . ' ';
            },
            $line,
            -1,
            $count,
            PREG_OFFSET_CAPTURE
        ) ?? $line;
        // A decimal point is followed by a digit, not a capitalized prose
        // token. Restore missing sentence/label boundaries, except where the
        // matched punctuation itself belongs to an explicit URL.
        $line = preg_replace_callback(
            '/(?<=\S)\.(?=\p{Lu})/u',
            function (array $matches) use ($line): string {
                $text = $matches[0][0];
                $offset = $matches[0][1];
                if ($this->pdfOffsetIsWithinExplicitUrl($line, $offset)) {
                    return $text;
                }

                return '. ';
            },
            $line,
            -1,
            $count,
            PREG_OFFSET_CAPTURE
        ) ?? $line;
        if ($lineHasWordSpacing) {
            // An all-caps acronym surrounded by ordinary prose is a reliable
            // glued-word boundary. Camel-case words and short plural suffixes
            // are intentionally left alone because they are identifiers.
            $line = preg_replace('/([\p{Ll}])(\p{Lu}{2,})(?=\p{Ll})/u', '$1 $2', $line) ?? $line;
            $line = preg_replace('/(?<!\p{Lu})(\p{Lu}{2})(\p{Ll}{3,})/u', '$1 $2', $line) ?? $line;
        }
        $line = $this->repairPdfLetterDigitBoundaries($line);
        $line = preg_replace_callback(
            '/(\d)([\p{L}]{2,})/u',
            function (array $matches) use ($line): string {
                $text = $matches[0][0];
                $offset = $matches[0][1];
                $suffix = $matches[2][0];
                // Ordinal suffixes are deliberately attached to their
                // number. Treating "19th" as a glued prose boundary turns
                // perfectly valid dates into "19 th".
                if (preg_match('/^(?:st|nd|rd|th)(?!\p{L})/iu', $suffix) === 1) {
                    return $text;
                }
                if ($this->pdfOffsetIsWithinUrlOrDomain($line, $offset, strlen($text))) {
                    return $text;
                }

                return $matches[1][0] . ' ' . $matches[2][0];
            },
            $line,
            -1,
            $count,
            PREG_OFFSET_CAPTURE
        ) ?? $line;
        // In scientific and technical PDFs a Greek glyph often represents an
        // inline variable. A following Latin word belongs to prose, not the
        // variable name, even when a font switch omitted the space.
        $line = preg_replace('/([\p{Greek}])(?=[A-Za-z])/u', '$1 ', $line) ?? $line;
        $line = preg_replace('/\/\/(?=[A-Za-z])/', '// ', $line) ?? $line;
        $line = $this->repairSplitUrlWhitespace($line);
        $line = preg_replace('/([\x{2019}\']t)(?=[A-Za-z])/u', '$1 ', $line) ?? $line;

        $line = preg_replace('/\s+/u', ' ', $line) ?? $line;
        $line = str_replace(self::PDF_SOURCE_COMPACT_IDENTIFIER_BOUNDARY, '', $line);

        return trim($line);
    }

    /**
     * Some PDF producers paint an accent before the base glyph. When that
     * sequence sits inside a word, restore the normal Unicode order before
     * applying NFC normalization. The surrounding-letter requirement keeps
     * standalone notation and punctuation unchanged.
     */
    private function repairPdfMisorderedDiacritics(string $text): string
    {
        $combiningMarks = [
            "\u{00A8}" => "\u{0308}",
            "\u{00AF}" => "\u{0304}",
            "\u{00B4}" => "\u{0301}",
            "\u{00B8}" => "\u{0327}",
            "\u{02C7}" => "\u{030C}",
            "\u{02D8}" => "\u{0306}",
            "\u{02D9}" => "\u{0307}",
            "\u{02DA}" => "\u{030A}",
            "\u{02DC}" => "\u{0303}",
        ];
        $text = preg_replace_callback(
            '/(?<=\p{L})([\x{00A8}\x{00AF}\x{00B4}\x{00B8}\x{02C7}\x{02D8}\x{02D9}\x{02DA}\x{02DC}])([\p{L}])/u',
            static function (array $matches) use ($combiningMarks): string {
                $base = match ($matches[2]) {
                    "\u{0131}" => 'i',
                    "\u{0237}" => 'j',
                    default => $matches[2],
                };

                return $base . $combiningMarks[$matches[1]];
            },
            $text
        ) ?? $text;

        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($text, \Normalizer::FORM_C);
            if (is_string($normalized)) {
                return $normalized;
            }
        }

        return $text;
    }

    private function repairPdfLetterDigitBoundaries(string $line): string
    {
        return preg_replace_callback('/\b([\p{L}]+)(\d{2,})\b/u', function (array $matches): string {
            $letters = $matches[1];
            if (preg_match('/^\p{Lu}{2,}$/u', $letters) === 1) {
                return $matches[0];
            }

            return $letters . ' ' . $matches[2];
        }, $line) ?? $line;
    }

    /**
     * Keep only conventional compact numeric punctuation. A generic
     * digit-to-digit test would incorrectly treat a missing list separator
     * such as "2019,2020" or a ratio such as "1:2ratio" as intentional.
     */
    private function pdfCompactNumericPunctuationAt(string $line, int $offset, string $punctuation): bool
    {
        if ($offset <= 0) {
            return false;
        }

        $before = substr($line, 0, $offset);
        $after = substr($line, $offset + strlen($punctuation));
        if ($punctuation === ',') {
            return preg_match('/(?:^|[^\d])\d{1,3}(?:,\d{3})*$/D', $before) === 1
                && preg_match('/^\d{3}(?!\d)/', $after) === 1;
        }
        if ($punctuation === ':') {
            return preg_match('/(?:^|[^\d])(?:[01]?\d|2[0-3])(?::[0-5]\d)?$/D', $before) === 1
                && preg_match('/^[0-5]\d(?!\d)/', $after) === 1;
        }

        return false;
    }

    private function pdfOffsetIsWithinUrlOrDomain(string $line, int $offset, int $length): bool
    {
        $before = substr($line, 0, $offset);
        $after = substr($line, $offset + $length);
        preg_match('/[^\s]+$/u', $before, $leftMatch);
        preg_match('/^[^\s]+/u', $after, $rightMatch);
        $token = ($leftMatch[0] ?? '') . substr($line, $offset, $length) . ($rightMatch[0] ?? '');
        $token = trim($token, "()[]{}<>,.;:'\"”’");

        return $this->lineLooksLikeUrlOnly($token) || $this->lineLooksLikeBareDomain($token);
    }

    private function pdfOffsetIsWithinExplicitUrl(string $line, int $offset): bool
    {
        $prefix = substr($line, 0, $offset);

        return preg_match('~(?:https?://|www\.)[^\s]*$~iu', $prefix) === 1;
    }

    private function repairSplitUrlWhitespace(string $line): string
    {
        $line = preg_replace('/\b(https?):\s*\/\/\s*/iu', '$1://', $line) ?? $line;
        $line = preg_replace('/\b(www)\s+\.(?=[A-Za-z0-9-])/iu', '$1.', $line) ?? $line;
        $line = preg_replace_callback(
            '/\b((?:https?:\/\/|www\.)\S*[A-Za-z])\s+(\d[\w.-]*)(?=[\/.)?#]|$)/iu',
            static function (array $matches): string {
                $left = $matches[1];
                $right = $matches[2];
                // A domain-shaped, digit-leading token is often a second
                // visible URL in a compact resource list, not a continuation
                // of the preceding host. Slash and hyphen paths remain safe
                // to join in the dedicated rule below.
                if (!str_ends_with($left, '/')
                    && !str_ends_with($left, '-')
                    && preg_match('/^\d[\w-]*\.[A-Za-z]{2,}$/u', $right) === 1) {
                    return $matches[0];
                }

                return $left . $right;
            },
            $line
        ) ?? $line;
        $line = preg_replace('/\b((?:https?:\/\/|www\.)\S*[-\/])\s+([A-Za-z0-9][\w.-]*)(?=[\/.)?#]|$)/iu', '$1$2', $line) ?? $line;

        return preg_replace_callback(
            '/\b((?:https?:\/\/|www\.)[A-Za-z0-9.-]*[A-Za-z0-9])\s+((?!www(?=\.))[A-Za-z0-9-]{1,24})(?=\.)/iu',
            static function (array $matches): string {
                $left = $matches[1];
                $right = $matches[2];
                if (preg_match('/^\d/u', $right) === 1
                    && preg_match('~(?:https?://|www\.)[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,}$~iu', $left) === 1) {
                    return $matches[0];
                }

                return $left . $right;
            },
            $line
        ) ?? $line;
    }

    private function removeStandaloneBraceArtifacts(string $line): string
    {
        $line = preg_replace('/(^|\s)\{\s*\}(?=\s|$)/u', '$1', $line) ?? $line;
        $line = preg_replace('/\s+[\{\}](?=\s*$)/u', '', $line) ?? $line;

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

        if (preg_match('/^[^\p{L}\p{N}]+$/u', $line) === 1) {
            return true;
        }
        if (preg_match('/^[,.;:()\[\]{}|_~`\'"’‘“”\-#!$%&*+=<>?@\\\\\/]+$/u', $line) === 1) {
            return true;
        }

        $compact = preg_replace('/\s+/u', '', $line) ?? '';
        $length = $this->length($compact);
        if ($length < 8) {
            return false;
        }
        $letters = preg_match_all('/\p{L}/u', $compact, $matches);
        $wordTokens = $this->pdfLineWordTokens($line);
        $longestWord = 0;
        foreach ($wordTokens as $word) {
            $longestWord = max($longestWord, $this->length($word));
        }
        $garbledDelimiterCount = preg_match_all('/[<>#]/u', $compact);

        if ($letters !== false
            && $garbledDelimiterCount !== false
            && $garbledDelimiterCount >= 3
            && $letters / $length <= 0.35
            && $longestWord <= 8) {
            return true;
        }

        return $letters !== false
            && $letters / $length <= 0.25
            && $longestWord <= 4
            && count($wordTokens) <= 12;
    }

    /**
     * Preserve geometry-confirmed code lines as one multiline block while
     * retaining the existing prose, list, and stacked-table merge behavior
     * for every surrounding record.
     *
     * @param list<array{text: string, layout: array<string, mixed>|null}> $records
     * @return list<string>
     */
    private function mergeRepairedPdfRecords(array $records): array
    {
        $merged = [];
        $prose = [];
        $code = [];
        $flushProse = function () use (&$merged, &$prose): void {
            if ($prose === []) {
                return;
            }
            $hasLayout = false;
            foreach ($prose as $record) {
                if ($this->pdfLayoutHasGeometry(is_array($record['layout'] ?? null) ? $record['layout'] : null)) {
                    $hasLayout = true;
                    break;
                }
            }
            $lines = !$hasLayout && ($this->pdfLinesLookLikeDenseListLayout($prose) || $this->pdfLinesLookLikeSparseLongTextChunks($prose))
                ? array_map(static fn (array $record): string => $record['text'], $prose)
                : $this->mergeRepairedProseLinesPreservingStackedTables($prose);
            foreach ($lines as $line) {
                $merged[] = $line;
            }
            $prose = [];
        };
        $flushCode = function () use (&$merged, &$code): void {
            if ($code === []) {
                return;
            }
            $text = rtrim(implode("\n", $code));
            if ($text !== '') {
                $merged[] = self::PDF_CODE_BLOCK_PREFIX . $text;
            }
            $code = [];
        };

        foreach ($records as $record) {
            $layout = is_array($record['layout'] ?? null) ? $record['layout'] : null;
            if (($layout['code'] ?? false) === true) {
                $flushProse();
                $code[] = (string) ($layout['codeText'] ?? $record['text']);
                continue;
            }

            $flushCode();
            $prose[] = $record;
        }
        $flushCode();
        $flushProse();

        return $merged;
    }

    /**
     * @param list<array{text: string, layout: array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float}|null}> $records
     * @return list<string>
     */
    private function mergeRepairedProseLinesPreservingStackedTables(array $records): array
    {
        $merged = [];
        $pending = [];
        $texts = array_map(static fn (array $record): string => $record['text'], $records);
        for ($index = 0, $count = count($records); $index < $count;) {
            $stackedTable = $this->stackedTableRowsAt($texts, $index);
            if ($stackedTable['rows'] === []) {
                $pending[] = $records[$index];
                $index++;
                continue;
            }

            if ($pending !== []) {
                foreach ($this->mergeRepairedProseLines($pending, $records[$index]) as $line) {
                    $merged[] = $line;
                }
                $pending = [];
            }
            for ($offset = 0; $offset < $stackedTable['consumed']; $offset++) {
                $merged[] = $records[$index + $offset]['text'];
            }
            $index += $stackedTable['consumed'];
        }
        if ($pending !== []) {
            foreach ($this->mergeRepairedProseLines($pending) as $line) {
                $merged[] = $line;
            }
        }

        return $merged;
    }

    /**
     * @param list<array{text: string, layout: array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float}|null}> $records
     * @param array{text: string, layout: array<string, mixed>|null}|null $followingRecord
     * @return list<string>
     */
    private function mergeRepairedProseLines(
        array $records,
        ?array $followingRecord = null
    ): array
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
            if (($layout['sourcePdfDisplayHeading'] ?? false) === true) {
                if ($pending !== '') {
                    $merged[] = $pending;
                }
                $pending = self::PDF_DISPLAY_HEADING_PREFIX . $line;
                $pendingLayout = $layout;
                continue;
            }
            if ($pending === '') {
                $pending = $line;
                $pendingLayout = $layout;
                continue;
            }
            if (($pendingLayout['sourcePdfDisplayHeading'] ?? false) === true) {
                $merged[] = $pending;
                $pending = $line;
                $pendingLayout = $layout;
                continue;
            }
            if ($this->pdfLayoutsFormInlineTerminalLead($pending, $line, $pendingLayout, $layout)) {
                $pending = rtrim($pending) . ' ' . ltrim($line);
                // Keep the left-hand lead's geometry so its wrapped lines
                // still align with the paragraph's actual visual start.
                continue;
            }
            if ($this->lineLooksLikePdfReferenceEntry($pending)
                && !$this->lineLooksLikePdfReferenceEntry($line)) {
                if ($this->pdfReferenceEntryEndsWithUrl($pending)
                    && preg_match('/^[^\p{L}\p{N}]*\p{Lu}/u', ltrim($line)) === 1) {
                    $merged[] = $pending;
                    $pending = $line;
                    $pendingLayout = $layout;
                    continue;
                }
                if ($this->lineLooksLikeUrlOnly($line)
                    && preg_match('/-\s*$/u', $pending) === 1) {
                    $pending = rtrim($pending) . ' ' . ltrim($line);
                } elseif (preg_match('/[-\x{2010}\x{2011}]\s*$/u', $pending) === 1) {
                    $pending = $this->repairedLineShouldRemoveHyphenatedBreak($pending, $line, $pendingLayout, $layout)
                        ? rtrim(preg_replace('/[-\x{2010}\x{2011}]\s*$/u', '', $pending) ?? rtrim(substr($pending, 0, -1))) . ltrim($line)
                        : rtrim($pending) . ltrim($line);
                } elseif (preg_match('/(?:https?:\/\/|www\.)\S*-\s*$/i', $pending) === 1
                    && preg_match('/^[A-Za-z0-9]/', ltrim($line)) === 1) {
                    $pending = rtrim($pending) . ltrim($line);
                } else {
                    $pending = rtrim($pending) . ' ' . ltrim($line);
                }
                $pendingLayout = $layout;
                continue;
            }
            if (($layout['forceBlockBreakBefore'] ?? false) === true) {
                if ($this->repairedPdfForcedBoundaryContinuesWrappedText($pending, $line, $pendingLayout, $layout)) {
                    if (preg_match('/\x{00AD}\s*$/u', $pending) === 1) {
                        $pending = $this->repairedPdfLayoutsHaveLocalWrappedHyphenPair($pendingLayout, $layout)
                            ? rtrim(preg_replace('/\x{00AD}\s*$/u', '', $pending) ?? $pending) . ltrim($line)
                            : rtrim($pending) . ltrim($line);
                    } elseif (preg_match('/[-\x{2010}\x{2011}]\s*$/u', $pending) === 1) {
                        $pending = $this->repairedLineShouldRemoveHyphenatedBreak($pending, $line, $pendingLayout, $layout)
                            ? rtrim(preg_replace('/[-\x{2010}\x{2011}]\s*$/u', '', $pending) ?? rtrim(substr($pending, 0, -1))) . ltrim($line)
                            : rtrim($pending) . ltrim($line);
                    } else {
                        $pending = rtrim($pending) . ' ' . ltrim($line);
                    }
                    $pendingLayout = $layout;
                    continue;
                }
                if (($layout['sourceSupplementalRecoverableSentenceSuffix'] ?? false) === true
                    && preg_match('/:\s*$/u', $pending) === 1) {
                    // The following positioned record began with an omitted
                    // formula or diagram prefix. Its colon introduced that
                    // display content, so preserve a readable sentence once
                    // the independently recoverable prose suffix starts.
                    $pending = rtrim(preg_replace('/:\s*$/u', '.', $pending) ?? $pending);
                }
                $pending = $this->trimIncompletePdfTailBeforeForcedBlockBoundary(
                    $pending,
                    $line,
                    $pendingLayout,
                    $layout
                );
                if ($pending !== '') {
                    $merged[] = $pending;
                }
                $pending = $line;
                $pendingLayout = $layout;
                continue;
            }
            if (($layout['sourcePdfCrossPanelContinuation'] ?? false) === true) {
                $separator = preg_match('/^[\)\]\},.;:]/u', ltrim($line)) === 1
                    || preg_match('/[-\x{00AD}\x{2010}-\x{2015}]\s*$/u', $pending) === 1
                    ? ''
                    : ' ';
                $pending = rtrim($pending) . $separator . ltrim($line);
                $pendingLayout = $layout;
                continue;
            }
            if (($layout['sourceSupplementalRecoverableSentenceSuffix'] ?? false) === true
                && preg_match('/:\s*$/u', $pending) === 1) {
                // A colon preceding a recoverable suffix introduced visual
                // formula/diagram content that is not safe body prose. End
                // the prior sentence before retaining the verified suffix.
                $merged[] = rtrim(preg_replace('/:\s*$/u', '.', $pending) ?? $pending);
                $pending = $line;
                $pendingLayout = $layout;
                continue;
            }
            if ($this->repairedPdfNumberedDisplayHeadingPrecedesBody($pending, $line, $pendingLayout, $layout)) {
                $merged[] = self::PDF_NUMBERED_HEADING_PREFIX . $pending;
                $pending = $line;
                $pendingLayout = $layout;
                continue;
            }
            if ($this->repairedPdfPageBoundaryLeavesUnresolvedTail($pending, $line, $pendingLayout, $layout)) {
                $pending = $this->trimIncompletePdfTailBeforeUnconfirmedPageBoundary(
                    $pending,
                    $line,
                    $pendingLayout,
                    $layout
                );
                if ($pending !== '') {
                    $merged[] = $pending;
                }
                $pending = $line;
                $pendingLayout = $layout;
                continue;
            }
            if ($this->repairedPdfSourceStreamStartsNewBlock($pending, $line, $pendingLayout, $layout)) {
                $tail = $this->repairedPdfSourceStreamTailText($pending);
                if ($tail !== '') {
                    $merged[] = $tail;
                }
                $pending = $line;
                $pendingLayout = $layout;
                continue;
            }
            if (preg_match('/(?:https?:\/\/|www\.)\S*-\s*$/i', $pending) === 1 && preg_match('/^[A-Za-z0-9]/', ltrim($line)) === 1) {
                $pending = rtrim($pending) . ltrim($line);
                $pendingLayout = $layout;
                continue;
            }
            // A discretionary hyphen explicitly marks this exact continuation
            // boundary; preserve it regardless of the next word's case.
            if (preg_match('/\x{00AD}\s*$/u', $pending) === 1) {
                $pending = $this->repairedPdfLayoutsHaveLocalWrappedHyphenPair($pendingLayout, $layout)
                    ? rtrim(preg_replace('/\x{00AD}\s*$/u', '', $pending) ?? $pending) . ltrim($line)
                    : rtrim($pending) . ltrim($line);
                $pendingLayout = $layout;
                continue;
            }
            // A dash separated from the preceding token is punctuation, not
            // a wrapped word fragment. Preserve its visible separator when
            // the next visual line begins; this fixes references and other
            // source text without treating URLs or names specially.
            if (preg_match('/\s[-\x{2010}\x{2011}]\s*$/u', $pending) === 1
                && $this->repairedPdfLayoutsHaveLocalTerminalDashSeparatorPair($pendingLayout, $layout)) {
                $pending = rtrim($pending) . ' ' . ltrim($line);
                $pendingLayout = $layout;
                continue;
            }
            if (preg_match('/-\s*$/', $pending) === 1 && $this->lineLooksLikeUrlOnly($line)) {
                $pending = rtrim($pending) . ' ' . ltrim($line);
                $pendingLayout = $layout;
                continue;
            }
            if (preg_match('/[-\x{2010}\x{2011}]\s*$/u', $pending) === 1) {
                $pending = $this->repairedLineShouldRemoveHyphenatedBreak($pending, $line, $pendingLayout, $layout)
                    ? rtrim(preg_replace('/[-\x{2010}\x{2011}]\s*$/u', '', $pending) ?? rtrim(substr($pending, 0, -1))) . ltrim($line)
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
            if ($followingRecord !== null) {
                $followingLayout = is_array($followingRecord['layout'] ?? null)
                    ? $followingRecord['layout']
                    : null;
                $pending = $this->trimIncompletePdfTailBeforeForcedBlockBoundary(
                    $pending,
                    (string) ($followingRecord['text'] ?? ''),
                    $pendingLayout,
                    $followingLayout
                );
            }
            if ($pending !== '') {
                $merged[] = $pending;
            }
        }

        return $merged;
    }

    /**
     * Some positioned PDF sources mark each wrapped display line as a block
     * even though its alignment, line rhythm, and lower-case continuation
     * prove it is one visual phrase. Preserve that phrase before honoring the
     * ordinary forced block boundary. This is useful for wrapped titles and
     * quotations as well as regular prose.
     *
     * @param array<string, mixed>|null $previousLayout
     * @param array<string, mixed>|null $lineLayout
     */
    private function repairedPdfForcedBoundaryContinuesWrappedText(
        string $previous,
        string $line,
        ?array $previousLayout,
        ?array $lineLayout
    ): bool {
        if (!$this->pdfLayoutHasGeometry($previousLayout)
            || !$this->pdfLayoutHasGeometry($lineLayout)
            || ($previousLayout['page'] ?? null) !== ($lineLayout['page'] ?? null)
            || ($previousLayout['code'] ?? false) === true
            || ($lineLayout['code'] ?? false) === true
            || $this->lineHasPdfListBlockEvidence($previous)
            || $this->lineHasPdfListBlockEvidence($line)
            || preg_match('/[.!?;:]\s*$/u', rtrim($previous)) === 1
            || preg_match('/^[^\p{L}\p{N}]*\p{Ll}/u', ltrim($line)) !== 1) {
            return false;
        }

        $fontSize = max(1.0, (float) $previousLayout['fontSize'], (float) $lineLayout['fontSize']);
        // A source-overlap supplement can supply a clipped body-line suffix.
        // Its explicit source provenance and normal line rhythm are stronger
        // evidence than the display-font threshold used for ordinary callouts.
        $sourceSupplementalContinuation = ($lineLayout['sourceSupplementalPositioned'] ?? false) === true
            && ($lineLayout['sourceSupplementalSourceOverlap'] ?? false) === true
            && (($previousLayout['sourceVerifiedGeometryText'] ?? false) === true
                || isset($previousLayout['sourceStream']))
            && ($previousLayout['sourceDetachedDiagramEvidencePage'] ?? false) !== true
            && ($lineLayout['sourceDetachedDiagramEvidencePage'] ?? false) !== true;
        if ($fontSize < 12.0 && !$sourceSupplementalContinuation) {
            return false;
        }
        if (abs((float) $previousLayout['fontSize'] - (float) $lineLayout['fontSize']) > max(1.5, $fontSize * 0.25)) {
            return false;
        }
        $verticalStep = (float) $previousLayout['y1'] - (float) $lineLayout['y1'];
        if ($verticalStep < $fontSize * 0.30 || $verticalStep > $fontSize * 2.20) {
            return false;
        }

        return abs((float) $previousLayout['x1'] - (float) $lineLayout['x1']) <= max(16.0, $fontSize * 1.5);
    }

    /**
     * @param array<string, mixed>|null $previousLayout
     * @param array<string, mixed>|null $lineLayout
     */
    private function repairedPdfLayoutsHaveLocalWrappedHyphenPair(?array $previousLayout, ?array $lineLayout): bool
    {
        // A middle physical line can finish one wrapped word and begin the
        // next. Keep those two occurrence-local decisions directional so the
        // later pair cannot overwrite the prior line's incoming provenance.
        // The scalar fallback accepts records created by older in-memory
        // callers, while all source matching now emits the directional form.
        $pair = $previousLayout['sourcePdfWrappedHyphenPairAfter']
            ?? $previousLayout['sourcePdfWrappedHyphenPair']
            ?? null;
        $followingPair = $lineLayout['sourcePdfWrappedHyphenPairBefore']
            ?? $lineLayout['sourcePdfWrappedHyphenPair']
            ?? null;

        return is_string($pair) && $pair !== '' && $pair === $followingPair;
    }

    /**
     * @param array<string, mixed>|null $previousLayout
     * @param array<string, mixed>|null $lineLayout
     */
    private function repairedPdfLayoutsHaveLocalTerminalDashSeparatorPair(?array $previousLayout, ?array $lineLayout): bool
    {
        $pair = $previousLayout['sourcePdfTerminalDashSeparatorPairAfter']
            ?? $previousLayout['sourcePdfTerminalDashSeparatorPair']
            ?? null;
        $followingPair = $lineLayout['sourcePdfTerminalDashSeparatorPairBefore']
            ?? $lineLayout['sourcePdfTerminalDashSeparatorPair']
            ?? null;

        return is_string($pair) && $pair !== '' && $pair === $followingPair;
    }

    /**
     * @param array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float}|null $previousLayout
     * @param array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float}|null $lineLayout
     */
    private function repairedLineShouldRemoveHyphenatedBreak(
        string $previous,
        string $line,
        ?array $previousLayout,
        ?array $lineLayout
    ): bool
    {
        // A hyphen at the end of an explicit URL is URL syntax, not a word
        // break. Keep it at this adjacent continuation boundary before the
        // bibliography layout rule below considers ordinary wrapped prose.
        if (preg_match('~(?:https?://|www\.)\S*-\s*$~iu', $previous) === 1
            && preg_match('/^[A-Za-z0-9]/u', ltrim($line)) === 1) {
            return false;
        }

        // The marker is attached while source and positioned records still
        // identify this exact adjacent line pair. Never infer the decision
        // from a matching token elsewhere in the final prose stream.
        if ($this->repairedPdfLayoutsHaveLocalWrappedHyphenPair($previousLayout, $lineLayout)) {
            return true;
        }

        // A hanging bibliography indent is still one source entry. Its
        // verified visual rhythm makes a terminal hyphen a wrap artifact even
        // when no semantic tag is available.
        if (($previousLayout['sourcePdfReferenceEntry'] ?? false) === true
            && ($lineLayout['sourcePdfReferenceEntry'] ?? false) === true
            && $this->repairedPdfLayoutContinuesWrappedLine($previousLayout, $lineLayout)) {
            return true;
        }

        // A physical page boundary can split a single visual paragraph. When
        // baseline, font, and unfinished syntax prove that flow, the terminal
        // hyphen is a line-wrap artifact. Do not use fragment spelling,
        // length, or a similar token elsewhere in the document: `re-enter`
        // and `reenter` have indistinguishable text shapes without local
        // layout evidence.
        return $this->repairedPdfLayoutContinuesAcrossPageBoundary(
            $previous,
            $line,
            $previousLayout,
            $lineLayout
        );
    }

    /**
     * @param array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float}|null $previousLayout
     * @param array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float}|null $lineLayout
     */
    private function repairedLineShouldStartNewBlock(string $previous, string $line, ?array $previousLayout = null, ?array $lineLayout = null): bool
    {
        if ($previousLayout !== null && $lineLayout !== null
            && isset($previousLayout['page'], $lineLayout['page'])
            && $previousLayout['page'] !== $lineLayout['page']) {
            return !$this->repairedPdfLayoutContinuesAcrossPageBoundary($previous, $line, $previousLayout, $lineLayout);
        }
        if (($lineLayout['forceBlockBreakBefore'] ?? false) === true) {
            return true;
        }
        if ($this->repairedPdfAdjacentSourceContinuationAcrossRegions($previous, $line, $previousLayout, $lineLayout)) {
            return false;
        }
        if ($this->repairedPdfSourceStreamStartsNewBlock($previous, $line, $previousLayout, $lineLayout)) {
            return true;
        }
        if ($this->lineHasPdfListBlockEvidence($line)) {
            return true;
        }
        if ($this->repairedPdfLayoutContinuesAcrossColumnBoundary($previous, $line, $previousLayout, $lineLayout)) {
            return false;
        }
        if ($this->repairedPdfLayoutStartsNewBlock($previousLayout, $lineLayout)) {
            return true;
        }
        // A title-like capitalized phrase can be ordinary prose at the start
        // of a wrapped visual line. When geometry keeps it in the same body
        // lane and the preceding line is unfinished, preserve that flow
        // before applying lexical heading heuristics.
        if (count($this->pdfLineWordTokens($previous)) >= 4
            && preg_match('/[.!?;:]\s*$/u', rtrim($previous)) !== 1
            && $this->repairedPdfLayoutContinuesWrappedLine($previousLayout, $lineLayout)) {
            return false;
        }
        if ($this->repairedLineLooksLikeSectionLabel($previous)) {
            if ($this->repairedSectionLabelContinuesOnNextVisualLine($previous, $line, $previousLayout, $lineLayout)) {
                return false;
            }
            return true;
        }
        if ($this->repairedLineLooksLikeSectionLabel($line)) {
            if ($this->repairedSectionLabelContinuesOnNextVisualLine($previous, $line, $previousLayout, $lineLayout)) {
                return false;
            }
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
     * Some maps and multi-panel handouts paint the continuation of one source
     * sentence in a distant panel. Preserve it only when consecutive source
     * records, matching font metrics, and lower-case syntax jointly prove the
     * flow. This excludes detached diagram labels and ordinary column order.
     *
     * @param array<string, mixed>|null $previousLayout
     * @param array<string, mixed>|null $lineLayout
     */
    private function repairedPdfAdjacentSourceContinuationAcrossRegions(
        string $previous,
        string $line,
        ?array $previousLayout,
        ?array $lineLayout
    ): bool {
        if (!$this->pdfLayoutHasGeometry($previousLayout)
            || !$this->pdfLayoutHasGeometry($lineLayout)
            || ($previousLayout['page'] ?? null) !== ($lineLayout['page'] ?? null)
            || ($previousLayout['sourceStream'] ?? null) !== ($lineLayout['sourceStream'] ?? null)
            || !isset($previousLayout['sourcePdfSourceIndex'], $lineLayout['sourcePdfSourceIndex'])
            || ($previousLayout['sourceVerifiedGeometryText'] ?? false) !== true
            || ($lineLayout['sourceVerifiedGeometryText'] ?? false) !== true
            || ($previousLayout['sourceDetachedDiagramEvidencePage'] ?? false) === true
            || ($lineLayout['sourceDetachedDiagramEvidencePage'] ?? false) === true
            || ($previousLayout['code'] ?? false) === true
            || ($lineLayout['code'] ?? false) === true) {
            return false;
        }

        $previousEnd = (int) ($previousLayout['sourcePdfSourceIndexEnd'] ?? $previousLayout['sourcePdfSourceIndex']);
        if ((int) $lineLayout['sourcePdfSourceIndex'] !== $previousEnd + 1) {
            return false;
        }

        $previous = rtrim($previous);
        $line = ltrim($line);
        if ($previous === '' || $line === ''
            || preg_match('/[.!?;:]\s*$/u', $previous) === 1
            || preg_match('/^[^\p{L}\p{N}]*\p{Ll}/u', $line) !== 1
            || $this->lineHasPdfListBlockEvidence($previous)
            || $this->lineHasPdfListBlockEvidence($line)
            || count($this->pdfLineWordTokens($previous)) < 4
            || count($this->pdfLineWordTokens($line)) < 4
            || $this->repairedPdfLayoutContinuesWrappedLine($previousLayout, $lineLayout)) {
            return false;
        }

        $fontSize = max(1.0, (float) $previousLayout['fontSize'], (float) $lineLayout['fontSize']);
        if (abs((float) $previousLayout['fontSize'] - (float) $lineLayout['fontSize']) > max(1.25, $fontSize * 0.20)) {
            return false;
        }

        return abs((float) $previousLayout['x1'] - (float) $lineLayout['x1']) > max(24.0, $fontSize * 3.0)
            || abs((float) $previousLayout['y1'] - (float) $lineLayout['y1']) > max(24.0, $fontSize * 3.0);
    }

    /**
     * Separate source content streams often correspond to adjacent visual
     * regions. Without usable coordinates, a stream transition can still
     * prove that an uppercase fresh line must not be appended to an unfinished
     * sentence from a different region. Lowercase and hyphenated continuations
     * remain eligible for the normal wrapped-line merge.
     *
     * @param array<string, mixed>|null $previousLayout
     * @param array<string, mixed>|null $lineLayout
     */
    private function repairedPdfSourceStreamStartsNewBlock(
        string $previous,
        string $line,
        ?array $previousLayout,
        ?array $lineLayout
    ): bool {
        if ($this->pdfLayoutHasGeometry($previousLayout) || $this->pdfLayoutHasGeometry($lineLayout)) {
            return false;
        }
        if ($previousLayout === null || $lineLayout === null
            || !isset($previousLayout['sourceStream'], $lineLayout['sourceStream'])) {
            return false;
        }
        if (($previousLayout['page'] ?? null) !== ($lineLayout['page'] ?? null)) {
            return true;
        }
        if ((int) $previousLayout['sourceStream'] === (int) $lineLayout['sourceStream']) {
            return false;
        }

        $previous = rtrim($previous);
        $line = ltrim($line);
        if ($previous === '' || $line === '' || preg_match('/[-\x{2010}-\x{2015}]$/u', $previous) === 1) {
            return false;
        }
        if (preg_match('/[.!?]\s*$/u', $previous) === 1) {
            return true;
        }

        return preg_match('/^[^\p{L}\p{N}]*\p{Lu}/u', $line) === 1;
    }

    /**
     * A compact numbered display heading can sit immediately above its body
     * text in the same source stream. Preserve that visual boundary before
     * line merging so block parsing does not mistake the result for a list.
     *
     * @param array<string, mixed>|null $previousLayout
     * @param array<string, mixed>|null $lineLayout
     */
    private function repairedPdfNumberedDisplayHeadingPrecedesBody(
        string $previous,
        string $line,
        ?array $previousLayout,
        ?array $lineLayout
    ): bool {
        if (!$this->pdfLayoutHasGeometry($previousLayout)
            || !$this->pdfLayoutHasGeometry($lineLayout)
            || ($previousLayout['page'] ?? null) !== ($lineLayout['page'] ?? null)
            || preg_match('/^\s*\d+(?:\.\d+)*(?:\.)?\s+\p{Lu}/u', $previous) !== 1
            || preg_match('/[.!?;:]\s*$/u', trim($previous)) === 1
            || count($this->pdfLineWordTokens($previous)) > 8
            || preg_match('/^[^\p{L}\p{N}]*\p{Lu}/u', ltrim($line)) !== 1
            || $this->lineHasPdfListBlockEvidence($line)) {
            return false;
        }

        $previousFont = max(1.0, (float) $previousLayout['fontSize']);
        $lineFont = max(1.0, (float) $lineLayout['fontSize']);
        if ($previousFont <= $lineFont * 1.10) {
            return false;
        }

        $verticalStep = (float) $previousLayout['y1'] - (float) $lineLayout['y1'];

        return $verticalStep >= $lineFont * 0.30
            && $verticalStep <= max(24.0, $previousFont * 2.5);
    }

    private function repairedPdfSourceStreamTailText(string $text): string
    {
        $text = trim($text);
        if ($text === ''
            || $this->lineHasPdfListBlockEvidence($text)
            || $this->lineLooksLikeUrlOnly($text)
            || $this->repairedLineLooksLikeSectionLabel($text)
            || $this->looksLikeRepairedPdfTitle($this->repairGluedProseLine($text))
            || count($this->pdfLineWordTokens($text)) < 14
            || preg_match('/[.!?;:]\s*$/u', $text) === 1
            || preg_match('/[-\x{00AD}\x{2010}-\x{2015}]\s*$/u', $text) === 1) {
            return $text;
        }

        $completePrefix = $this->completePdfSentencePrefix($text);
        if ($completePrefix !== '' && count($this->pdfLineWordTokens($completePrefix)) >= 5) {
            return $completePrefix;
        }

        return '';
    }

    /**
     * A forced visual boundary proves that the next record is an independent
     * block. If the preceding prose run has no terminal punctuation, its
     * missing continuation must not be emitted as a complete paragraph. Keep
     * any verified sentence prefix and otherwise omit only that unresolved
     * run. This relies on layout provenance, not vocabulary.
     *
     * @param array<string, mixed>|null $previousLayout
     * @param array<string, mixed>|null $followingLayout
     */
    private function trimIncompletePdfTailBeforeForcedBlockBoundary(
        string $previous,
        string $following,
        ?array $previousLayout,
        ?array $followingLayout
    ): string {
        $previous = trim($previous);
        $following = ltrim($following);
        if ($previous === ''
            || !$this->pdfLayoutHasGeometry($previousLayout)
            || !$this->pdfLayoutHasGeometry($followingLayout)
            || ($previousLayout['sourceStructuredGeometry'] ?? false) !== true
            || ($followingLayout['sourceStructuredGeometry'] ?? false) !== true
            || ($followingLayout['forceBlockBreakBefore'] ?? false) !== true
            || ($previousLayout['code'] ?? false) === true
            || ($followingLayout['code'] ?? false) === true
            || $this->lineHasPdfListBlockEvidence($previous)
            || $this->lineLooksLikeUrlOnly($previous)
            || $this->repairedLineLooksLikeSectionLabel($previous)
            || preg_match('/[.!?;:]\s*$/u', $previous) === 1
            || preg_match('/[-\x{00AD}\x{2010}-\x{2015}]\s*$/u', $previous) === 1
            || ($following !== ''
                && preg_match('/^[^\p{L}\p{N}]*\p{Ll}/u', $following) === 1
                && !$this->repairedLineLooksLikeSectionLabel($following))) {
            return $previous;
        }

        $prefix = $this->completePdfSentencePrefix($previous);
        if ($prefix !== '' && count($this->pdfLineWordTokens($prefix)) >= 5) {
            return $prefix;
        }

        return count($this->pdfLineWordTokens($previous)) >= 5 ? '' : $previous;
    }

    /**
     * A normal page-spanning paragraph is retained by the cross-page layout
     * continuation check. When that check fails, an unfinished structured
     * body tail cannot safely survive as its own paragraph: the next page may
     * begin with a figure, caption, or unrelated column. Keep only a verified
     * complete sentence prefix.
     *
     * @param array<string, mixed>|null $previousLayout
     * @param array<string, mixed>|null $followingLayout
     */
    private function repairedPdfPageBoundaryLeavesUnresolvedTail(
        string $previous,
        string $following,
        ?array $previousLayout,
        ?array $followingLayout
    ): bool {
        if (!$this->pdfLayoutHasGeometry($previousLayout)
            || !$this->pdfLayoutHasGeometry($followingLayout)
            || ($previousLayout['sourceStructuredGeometry'] ?? false) !== true
            || ($followingLayout['sourceStructuredGeometry'] ?? false) !== true
            || ($previousLayout['page'] ?? null) === ($followingLayout['page'] ?? null)) {
            return false;
        }

        return !$this->repairedPdfLayoutContinuesAcrossPageBoundary(
            $previous,
            $following,
            $previousLayout,
            $followingLayout
        );
    }

    /**
     * @param array<string, mixed>|null $previousLayout
     * @param array<string, mixed>|null $followingLayout
     */
    private function trimIncompletePdfTailBeforeUnconfirmedPageBoundary(
        string $previous,
        string $following,
        ?array $previousLayout,
        ?array $followingLayout
    ): string {
        $previous = trim($previous);
        $following = ltrim($following);
        if ($previous === ''
            || !$this->repairedPdfPageBoundaryLeavesUnresolvedTail(
                $previous,
                $following,
                $previousLayout,
                $followingLayout
            )
            || ($previousLayout['code'] ?? false) === true
            || ($followingLayout['code'] ?? false) === true
            || $this->lineHasPdfListBlockEvidence($previous)
            || $this->lineLooksLikeUrlOnly($previous)
            || $this->repairedLineLooksLikeSectionLabel($previous)
            || preg_match('/[.!?;:]\s*$/u', $previous) === 1
            || preg_match('/[-\x{00AD}\x{2010}-\x{2015}]\s*$/u', $previous) === 1) {
            return $previous;
        }

        $prefix = $this->completePdfSentencePrefix($previous);
        if ($prefix !== '' && count($this->pdfLineWordTokens($prefix)) >= 5) {
            return $prefix;
        }

        return count($this->pdfLineWordTokens($previous)) >= 5 ? '' : $previous;
    }

    /**
     * @param array<string, mixed>|null $layout
     */
    private function pdfLayoutHasGeometry(?array $layout): bool
    {
        if ($layout === null) {
            return false;
        }
        foreach (['x1', 'y1', 'x2', 'y2', 'fontSize'] as $key) {
            if (!isset($layout[$key]) || !is_numeric($layout[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float}|null $previousLayout
     * @param array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float}|null $lineLayout
     */
    private function repairedSectionLabelContinuesOnNextVisualLine(
        string $previous,
        string $line,
        ?array $previousLayout,
        ?array $lineLayout
    ): bool {
        if (!$this->repairedPdfLayoutContinuesWrappedLine($previousLayout, $lineLayout)) {
            return false;
        }
        if (preg_match('/^[^\p{L}\p{N}]*\p{Ll}/u', ltrim($line)) === 1
            && preg_match('/[.!?;:]\s*$/u', trim($previous)) !== 1) {
            return true;
        }
        $previousWords = $this->pdfLineWordTokens($previous);
        if (preg_match('/[.!?]\s*$/u', trim($previous)) === 1
            || count($previousWords) > 6
            || count($this->pdfLineWordTokens($previous . ' ' . $line)) > 9) {
            return false;
        }

        $previousWidth = max(1.0, $previousLayout['x2'] - $previousLayout['x1']);
        $lineWidth = max(1.0, $lineLayout['x2'] - $lineLayout['x1']);

        return $lineWidth <= $previousWidth * 1.35;
    }

    /**
     * A paragraph can continue from the bottom of the left body column to
     * the top of the right column. The large coordinate jump is expected in
     * that reading order, so preserve it only when the column provenance,
     * font metrics, and unfinished lower-case continuation all agree.
     *
     * @param array<string, mixed>|null $previousLayout
     * @param array<string, mixed>|null $lineLayout
     */
    private function repairedPdfLayoutContinuesAcrossColumnBoundary(
        string $previous,
        string $line,
        ?array $previousLayout,
        ?array $lineLayout
    ): bool {
        if (!$this->pdfLayoutHasGeometry($previousLayout) || !$this->pdfLayoutHasGeometry($lineLayout)
            || ($previousLayout['sourceStructuredGeometry'] ?? false) !== true
            || ($lineLayout['sourceStructuredGeometry'] ?? false) !== true
            || ($previousLayout['page'] ?? null) !== ($lineLayout['page'] ?? null)
            || !isset($previousLayout['sourceGeometryColumn'], $lineLayout['sourceGeometryColumn'])) {
            return false;
        }

        if ((int) $lineLayout['sourceGeometryColumn'] !== (int) $previousLayout['sourceGeometryColumn'] + 1) {
            return false;
        }

        $previous = rtrim($previous);
        $line = ltrim($line);
        if ($previous === '' || $line === ''
            || preg_match('/[.!?;:]\s*$/u', $previous) === 1
            || preg_match('/^[^\p{L}\p{N}]*\p{Ll}/u', $line) !== 1) {
            return false;
        }

        $fontSize = max(1.0, (float) $previousLayout['fontSize'], (float) $lineLayout['fontSize']);
        if (abs((float) $previousLayout['fontSize'] - (float) $lineLayout['fontSize']) > max(1.25, $fontSize * 0.20)) {
            return false;
        }

        return (float) $lineLayout['y1'] - (float) $previousLayout['y1'] > max(24.0, $fontSize * 3.0);
    }

    /**
     * A page break is normally a paragraph boundary, but a two-column flow
     * can end at the bottom of one page and continue at the top of the next.
     * Preserve that continuation only when the baseline positions, font, and
     * unfinished lower-case syntax all agree. This avoids joining unrelated
     * page headers or a new paragraph that merely happens to start lower-case.
     *
     * @param array<string, mixed>|null $previousLayout
     * @param array<string, mixed>|null $lineLayout
     */
    private function repairedPdfLayoutContinuesAcrossPageBoundary(
        string $previous,
        string $line,
        ?array $previousLayout,
        ?array $lineLayout
    ): bool {
        if (!$this->pdfLayoutHasGeometry($previousLayout) || !$this->pdfLayoutHasGeometry($lineLayout)
            || (int) ($lineLayout['page'] ?? 0) !== (int) ($previousLayout['page'] ?? 0) + 1) {
            return false;
        }

        $previous = rtrim($previous);
        $line = ltrim($line);
        if ($previous === '' || $line === ''
            || preg_match('/[.!?;:]\s*$/u', $previous) === 1
            || preg_match('/^[^\p{L}\p{N}]*\p{Ll}/u', $line) !== 1) {
            return false;
        }

        $fontSize = max(1.0, (float) $previousLayout['fontSize'], (float) $lineLayout['fontSize']);
        if (abs((float) $previousLayout['fontSize'] - (float) $lineLayout['fontSize']) > max(1.25, $fontSize * 0.20)) {
            return false;
        }

        return (float) $previousLayout['y1'] <= max(96.0, $fontSize * 12.0)
            && (float) $lineLayout['y1'] >= max(180.0, $fontSize * 20.0);
    }

    /**
     * @param array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float}|null $previousLayout
     * @param array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float}|null $lineLayout
     */
    private function repairedPdfLayoutStartsNewBlock(?array $previousLayout, ?array $lineLayout): bool
    {
        if (!$this->pdfLayoutHasGeometry($previousLayout) || !$this->pdfLayoutHasGeometry($lineLayout)) {
            return false;
        }
        if ($previousLayout['page'] !== $lineLayout['page']) {
            return true;
        }

        $previousHeight = max(1.0, $previousLayout['y2'] - $previousLayout['y1']);
        $lineHeight = max(1.0, $lineLayout['y2'] - $lineLayout['y1']);
        $referenceHeight = max($previousHeight, $lineHeight, $previousLayout['fontSize'], $lineLayout['fontSize'], 1.0);
        $verticalGap = $previousLayout['y1'] - $lineLayout['y2'];
        if ($verticalGap < -$referenceHeight * 1.5 || $verticalGap > $referenceHeight * 0.80) {
            return true;
        }

        $leftDelta = abs($previousLayout['x1'] - $lineLayout['x1']);
        if ($leftDelta > max(30.0, $referenceHeight * 3.0) && $verticalGap > $referenceHeight * 0.75) {
            return true;
        }

        // Text that starts far away on the same baseline, without sharing a
        // horizontal extent, belongs to a neighboring layout region. Keeping
        // it in the pending paragraph would splice columns, captions, or
        // samples into each other. Overlapping fragments remain eligible for
        // the ordinary same-line reconstruction path.
        $sameBaseline = abs((($previousLayout['y1'] + $previousLayout['y2']) / 2.0)
            - (($lineLayout['y1'] + $lineLayout['y2']) / 2.0)) <= max(2.0, $referenceHeight * 0.35);
        $horizontalOverlap = min($previousLayout['x2'], $lineLayout['x2'])
            - max($previousLayout['x1'], $lineLayout['x1']);
        if ($sameBaseline
            && $leftDelta > max(50.0, $referenceHeight * 4.0)
            && $horizontalOverlap <= max(2.0, $referenceHeight * 0.35)) {
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
        if (!$this->pdfLayoutHasGeometry($previousLayout) || !$this->pdfLayoutHasGeometry($lineLayout)
            || $previousLayout['page'] !== $lineLayout['page']) {
            return false;
        }

        $previousHeight = max(1.0, $previousLayout['y2'] - $previousLayout['y1']);
        $lineHeight = max(1.0, $lineLayout['y2'] - $lineLayout['y1']);
        $referenceHeight = max($previousHeight, $lineHeight, $previousLayout['fontSize'], $lineLayout['fontSize'], 1.0);
        $largerFont = max($previousLayout['fontSize'], $lineLayout['fontSize'], 1.0);
        $smallerFont = max(1.0, min($previousLayout['fontSize'], $lineLayout['fontSize']));
        if ($largerFont / $smallerFont > 1.35) {
            return false;
        }
        $verticalGap = $previousLayout['y1'] - $lineLayout['y2'];
        if ($verticalGap < -$referenceHeight * 0.4 || $verticalGap > $referenceHeight * 1.45) {
            return false;
        }

        // First lines are often indented while wrapped continuation lines
        // return to the column edge. Bibliographies additionally use a
        // hanging indent after the numbered marker. The source stream plus
        // reference provenance makes that wider allowance structural rather
        // than a guess based on the words themselves.
        $leftTolerance = max(16.0, $referenceHeight * 1.5);
        if (($previousLayout['sourcePdfReferenceEntry'] ?? false) === true
            && ($lineLayout['sourcePdfReferenceEntry'] ?? false) === true
            && isset($previousLayout['sourceStream'], $lineLayout['sourceStream'])
            && (int) $previousLayout['sourceStream'] === (int) $lineLayout['sourceStream']) {
            $leftTolerance = max($leftTolerance, 28.0, $referenceHeight * 3.5);
        }

        return abs($previousLayout['x1'] - $lineLayout['x1']) <= $leftTolerance;
    }

    /**
     * @param array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float}|null $previousLayout
     * @param array{text: string, page: int, x1: float, y1: float, x2: float, y2: float, fontSize: float}|null $lineLayout
     */
    private function repairedPdfLayoutLeavesListItem(?array $previousLayout, ?array $lineLayout): bool
    {
        if (!$this->pdfLayoutHasGeometry($previousLayout) || !$this->pdfLayoutHasGeometry($lineLayout)) {
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
        if (preg_match('/^\p{Lu}[\p{Lu}\p{N},;:() \-]+$/u', $line) === 1) {
            return true;
        }
        if (count($words) <= 3 && preg_match('/^\p{Lu}[\p{L}\p{N}&()\/ .-]*$/u', $line) === 1) {
            return true;
        }

        return $this->looksLikeRepairedPdfTitle($line)
            && (preg_match('/[:：]$/u', $line) !== 1 || $this->looksLikeRepairedPdfTitle(rtrim($line, ":：")));
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
    private function blocksFromLines(array $lines, bool $allowStackedTables = true): array
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
            if (str_starts_with($line, self::PDF_CODE_BLOCK_PREFIX)) {
                $flushList();
                $code = substr($line, strlen(self::PDF_CODE_BLOCK_PREFIX));
                if ($code !== '') {
                    $blocks[] = new AstNode('code_block', ['text' => $code]);
                }
                $index++;
                continue;
            }
            if (str_starts_with($line, self::PDF_MAP_LABEL_PREFIX)) {
                $flushList();
                $label = trim(substr($line, strlen(self::PDF_MAP_LABEL_PREFIX)));
                if ($label !== '') {
                    $blocks[] = $this->paragraph($label);
                }
                $index++;
                continue;
            }
            if (str_starts_with($line, self::PDF_DISPLAY_HEADING_PREFIX)) {
                $flushList();
                $heading = trim(substr($line, strlen(self::PDF_DISPLAY_HEADING_PREFIX)));
                if ($heading !== '') {
                    $blocks[] = new AstNode('heading', ['level' => $index === 0 ? 1 : 2, 'text' => $heading], $this->inlines($heading));
                }
                $index++;
                continue;
            }
            if (str_starts_with($line, self::PDF_NUMBERED_HEADING_PREFIX)) {
                $flushList();
                $heading = trim(substr($line, strlen(self::PDF_NUMBERED_HEADING_PREFIX)));
                if ($heading !== '') {
                    $blocks[] = new AstNode('heading', ['level' => $index === 0 ? 1 : 2, 'text' => $heading], $this->inlines($heading));
                }
                $index++;
                continue;
            }
            $tableRun = $this->tableRowsAt($lines, $index);
            if ($tableRun['rows'] !== []) {
                $flushList();
                $blocks[] = $this->table($tableRun['rows']);
                $index += $tableRun['consumed'];
                continue;
            }
            if ($allowStackedTables) {
                $stackedTableRun = $this->stackedTableRowsAt($lines, $index);
                if ($stackedTableRun['rows'] !== []) {
                    $flushList();
                    $blocks[] = $this->table($stackedTableRun['rows']);
                    $index += $stackedTableRun['consumed'];
                    continue;
                }
            }

            if ($this->numberedPdfLineLooksLikeSectionHeading($lines, $index)) {
                $flushList();
                $blocks[] = new AstNode('heading', ['level' => $index === 0 ? 1 : 2, 'text' => $line], $this->inlines($line));
                $index++;
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
     * @param array<int, list<AstNode>>|null $tableBlocksByPage
     * @return list<AstNode>
     */
    private function blocksFromPositionedTables(array $runs, array $filledRectangles = [], ?array &$tableBlocksByPage = null): array
    {
        $tableBlocksByPage = [];
        if ($runs === []) {
            return [];
        }

        $filledRectanglesByPage = [];
        foreach ($filledRectangles as $rectangle) {
            $normalized = $this->positionedFillRectangle($rectangle);
            if ($normalized !== null) {
                $filledRectanglesByPage[$normalized['page']][] = $normalized;
            }
        }

        if (!$this->positionedTextRunsArePageOrdered($runs)) {
            return $this->blocksFromPositionedTablesByPageMap($runs, $filledRectanglesByPage, $tableBlocksByPage);
        }

        $blocks = [];
        $pageRuns = [];
        $page = null;
        $flush = function () use (&$blocks, &$pageRuns, &$tableBlocksByPage, &$page, $filledRectanglesByPage): void {
            if ($page === null || $pageRuns === []) {
                return;
            }
            $pageBlocks = $this->blocksFromPositionedPageTables(
                $pageRuns,
                $filledRectanglesByPage[$page] ?? []
            );
            if ($this->countNodesOfType($pageBlocks, 'table') > 0) {
                $tableBlocksByPage[$page] = $pageBlocks;
                foreach ($pageBlocks as $block) {
                    $blocks[] = $block;
                }
            }
            $pageRuns = [];
        };
        foreach ($this->positionedRunsWithLiteralWhitespaceProvenance($runs) as $normalized) {
            $runPage = (int) $normalized['page'];
            if ($page !== null && $runPage !== $page) {
                $flush();
            }
            $page = $runPage;
            $pageRuns[] = $normalized;
        }
        $flush();

        return $blocks;
    }

    /**
     * @param list<array<string, mixed>> $runs
     * @param array<int, list<array<string, mixed>>> $filledRectanglesByPage
     * @param array<int, list<AstNode>> $tableBlocksByPage
     * @return list<AstNode>
     */
    private function blocksFromPositionedTablesByPageMap(
        array $runs,
        array $filledRectanglesByPage,
        array &$tableBlocksByPage
    ): array {

        $runsByPage = [];
        foreach ($this->positionedRunsWithLiteralWhitespaceProvenance($runs) as $normalized) {
            $runsByPage[$normalized['page']][] = $normalized;
        }
        if ($runsByPage === []) {
            return [];
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
        foreach ($runsByPage as $page => $_pageRuns) {
            $pageBlocks = $blocksByPage[$page] ?? [];
            if ($this->countNodesOfType($pageBlocks, 'table') === 0) {
                continue;
            }
            $tableBlocksByPage[(int) $page] = $pageBlocks;
            foreach ($pageBlocks as $block) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    /**
     * Geometry is authoritative for a page with a verified table, but using
     * it for every other page can scramble ordinary multi-column prose. Keep
     * source-reconciled text on those pages and replace only the table pages.
     *
     * @param list<string> $sourceLines
     * @param list<array<string, mixed>> $sourceLayouts
     * @param array<int, list<AstNode>> $tableBlocksByPage
     * @return list<AstNode>
     */
    private function blocksWithPositionedPdfTablePages(
        array $sourceLines,
        array $sourceLayouts,
        array $tableBlocksByPage
    ): array {
        $linesByPage = [];
        $layoutsByPage = [];
        foreach ($sourceLines as $index => $line) {
            $layout = is_array($sourceLayouts[$index] ?? null) ? $sourceLayouts[$index] : [];
            $page = max(1, (int) ($layout['page'] ?? 1));
            $linesByPage[$page][] = $line;
            $layoutsByPage[$page][] = $layout;
        }

        $pages = array_fill_keys(array_keys($linesByPage), true);
        foreach (array_keys($tableBlocksByPage) as $page) {
            $pages[(int) $page] = true;
        }
        ksort($pages, SORT_NUMERIC);

        $blocks = [];
        foreach (array_keys($pages) as $page) {
            if (isset($tableBlocksByPage[$page])) {
                foreach ($tableBlocksByPage[$page] as $block) {
                    $blocks[] = $block;
                }
                continue;
            }

            $pageLines = $linesByPage[$page] ?? [];
            if ($pageLines === []) {
                continue;
            }
            $pageLayouts = $layoutsByPage[$page] ?? [];
            $repaired = $this->proseTextRepairEnabled()
                ? $this->repairProseTextLines(
                    $pageLines,
                    $this->looksLikeProseRepairCandidate($pageLines),
                    $pageLayouts
                )
                : $pageLines;
            foreach ($this->blocksFromLines($repaired) as $block) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    /**
     * Normalize visible positioned runs while carrying a literal whitespace
     * operator to the next visible run from the same page content stream.
     *
     * A whitespace-only text-showing operator has no cell text after
     * normalization, so it must not become a zero-width prose/table cell.
     * It is nevertheless direct, per-occurrence evidence for the separator
     * immediately before the next painted text run. Keep that fact on the
     * following run instead of guessing from word spelling, case, or another
     * occurrence elsewhere in the document.
     *
     * @param list<array<string, mixed>> $runs
     * @return \Generator<int, array<string, mixed>>
     */
    private function positionedRunsWithLiteralWhitespaceProvenance(array $runs): \Generator
    {
        $pendingWhitespace = [];
        $lastVisibleRun = [];
        foreach ($runs as $index => $run) {
            $run['_order'] = array_key_exists('_order', $run) ? $run['_order'] : $index;
            $page = max(1, (int) ($run['page'] ?? 1));
            $whitespaceKey = $page . ':' . max(0, (int) ($run['stream'] ?? 0));
            $rawText = $this->normalizePdfTextEncoding((string) ($run['text'] ?? ''));
            if ($rawText !== '' && trim($rawText) === '' && preg_match('/\s/u', $rawText) === 1) {
                $pendingWhitespace[$whitespaceKey] = [
                    'hardBoundary' => (bool) (($pendingWhitespace[$whitespaceKey]['hardBoundary'] ?? false)
                        || preg_match('/[\t\r\n]/u', $rawText) === 1),
                    'run' => $run,
                    'previousVisibleRun' => $pendingWhitespace[$whitespaceKey]['previousVisibleRun']
                        ?? ($lastVisibleRun[$whitespaceKey] ?? null),
                ];
                continue;
            }

            $normalized = $this->positionedRun($run);
            if ($normalized === null) {
                continue;
            }
            if (isset($pendingWhitespace[$whitespaceKey])) {
                $pending = $pendingWhitespace[$whitespaceKey];
                unset($pendingWhitespace[$whitespaceKey]);
                // A whitespace-only Tj may be followed by another text line
                // in the same content stream. It proves a separator only at
                // the same visual baseline; otherwise it must not leak into
                // the first word of that next line.
                if ($this->pendingLiteralWhitespaceSharesVisualBaseline($pending, $normalized)) {
                    $normalized['startsWithWhitespace'] = true;
                    $normalized['startsAfterTextBoundary'] = (bool) (
                        ($normalized['startsAfterTextBoundary'] ?? false)
                        || $pending['hardBoundary']
                    );
                }
            }
            $lastVisibleRun[$whitespaceKey] = $normalized;
            yield $normalized;
        }
    }

    /**
     * A literal whitespace operator is local to the painted line that
     * contains it. Use its own bounds when available, then its immediately
     * preceding visible run as a safe fallback for producers that omit
     * whitespace glyph geometry.
     *
     * @param array<string, mixed> $pending
     * @param array<string, mixed> $followingRun
     */
    private function pendingLiteralWhitespaceSharesVisualBaseline(array $pending, array $followingRun): bool
    {
        $whitespaceRun = $pending['run'] ?? null;
        if (is_array($whitespaceRun)
            && $this->positionedRunsShareVisualBaseline($whitespaceRun, $followingRun)) {
            return true;
        }

        $previousVisibleRun = $pending['previousVisibleRun'] ?? null;

        return is_array($previousVisibleRun)
            && $this->positionedRunsShareVisualBaseline($previousVisibleRun, $followingRun);
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private function positionedRunsShareVisualBaseline(array $left, array $right): bool
    {
        $leftY1 = $this->numericValue($left['textY1'] ?? $left['y1'] ?? null);
        $leftY2 = $this->numericValue($left['textY2'] ?? $left['y2'] ?? null);
        $rightY1 = $this->numericValue($right['textY1'] ?? $right['y1'] ?? null);
        $rightY2 = $this->numericValue($right['textY2'] ?? $right['y2'] ?? null);
        if ($leftY1 === null || $leftY2 === null || $rightY1 === null || $rightY2 === null) {
            return false;
        }

        $leftFontSize = $this->numericValue($left['fontSize'] ?? null) ?? abs($leftY2 - $leftY1);
        $rightFontSize = $this->numericValue($right['fontSize'] ?? null) ?? abs($rightY2 - $rightY1);
        $tolerance = max(1.5, max($leftFontSize, $rightFontSize, 1.0) * 0.35);

        return abs((($leftY1 + $leftY2) / 2.0) - (($rightY1 + $rightY2) / 2.0)) <= $tolerance;
    }

    /**
     * @param array<string, mixed> $run
     * @return array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float, startsWithWhitespace: bool, endsWithWhitespace: bool, wordBoundaryBefore: bool, hasWordBoundaryBefore: bool, wordBoundarySource: ?string}|null
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
            'fontSize' => max(1.0, $fontSize ?? abs($y2 - $y1)),
            'nominalFontSize' => max(1.0, $fontSize ?? abs($y2 - $y1)),
            'startsWithWhitespace' => preg_match('/^\s/u', $rawText) === 1,
            'endsWithWhitespace' => preg_match('/\s$/u', $rawText) === 1,
            'wordBoundaryBefore' => ($run['wordBoundaryBefore'] ?? false) === true,
            'hasWordBoundaryBefore' => array_key_exists('wordBoundaryBefore', $run),
            'wordBoundarySource' => is_string($run['wordBoundarySource'] ?? null)
                ? $run['wordBoundarySource']
                : null,
            'startsAfterTextBoundary' => false,
            'order' => max(0, (int) ($run['_order'] ?? 0)),
            'lastOrder' => max(0, (int) ($run['_order'] ?? 0)),
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
        $logicalRows = $this->withPositionedCellBackgrounds($logicalRows, $filledRectangles);

        if (!$this->isPositionedTableCandidate($logicalRows, count($logicalRows[0] ?? [])) || $this->positionedRenderedRowsLookLikeFragmentGrid($logicalRows)) {
            return [];
        }

        return $logicalRows;
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
        $lowConfidenceCandidatesBefore = $this->lowConfidenceGeometryTableCandidates;
        $segments = $this->positionedTableSegments($rows, $columnTolerance, $filledRectangles, $medianFontSize);
        if ($segments === []) {
            if ($this->lowConfidenceGeometryTableCandidates > $lowConfidenceCandidatesBefore) {
                return [];
            }
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
            foreach ($this->blocksFromLines($lines, false) as $block) {
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

        return $this->blocksFromLines($lines, false);
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
        $regularRowGap = $this->positionedTypicalRowGap($rows);
        while ($index < $rowCount) {
            if (!$this->positionedRowLooksMultiColumn($rows[$index]) || $this->positionedRowLooksStandaloneHeading($rows[$index], $medianFontSize)) {
                $index++;
                continue;
            }

            $end = $index;
            while ($end + 1 < $rowCount && !$this->positionedRowsHaveTableSegmentBreak($rows[$end], $rows[$end + 1], $medianFontSize, $regularRowGap)) {
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
        $logicalRows = $this->withPositionedCellBackgrounds($logicalRows, $filledRectangles);

        if (!$this->isPositionedTableCandidate($logicalRows, count($logicalRows[0] ?? [])) || $this->positionedRenderedRowsLookLikeFragmentGrid($logicalRows)) {
            return [];
        }

        return $logicalRows;
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
    private function positionedRowsHaveTableSegmentBreak(
        array $current,
        array $next,
        float $medianFontSize,
        ?float $regularRowGap = null
    ): bool
    {
        $gap = $this->positionedRowGap($current, $next);
        $currentRuns = count($current['runs']);
        $nextRuns = count($next['runs']);
        $regularRowGap ??= max(1.0, $medianFontSize);

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

        $structuralGap = max($medianFontSize * 2.6, $regularRowGap * 1.45);
        if ($currentRuns >= 3 && $nextRuns <= 1 && $gap >= $structuralGap) {
            return true;
        }

        if ($currentRuns <= 2 && $nextRuns === 1 && $gap >= max($medianFontSize * 2.6, $regularRowGap * 1.55)) {
            return true;
        }

        return false;
    }

    /**
     * @param list<array{center: float, runs: list<array{page: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>}> $rows
     */
    private function positionedTypicalRowGap(array $rows): float
    {
        $gaps = [];
        for ($index = 0, $count = count($rows) - 1; $index < $count; $index++) {
            $gap = $this->positionedRowGap($rows[$index], $rows[$index + 1]);
            if ($gap > 0.0 && $gap <= 72.0) {
                $gaps[] = $gap;
            }
        }

        return $gaps === [] ? 1.0 : max(1.0, $this->median($gaps));
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
     * Form XObjects can apply a large coordinate transform to nominally tiny
     * text. Keep that original text-state size separate from the expanded
     * visual bounds when deciding whether operator-level spacing is reliable.
     *
     * @param array{runs: list<array<string, mixed>>} $row
     */
    private function positionedRowMaxNominalFontSize(array $row): float
    {
        $fontSize = 0.0;
        foreach ($row['runs'] as $run) {
            $fontSize = max($fontSize, (float) ($run['nominalFontSize'] ?? $run['fontSize'] ?? 0.0));
        }

        return max(1.0, $fontSize);
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
                    $sourceVerifiedBoundarySeparators = $this->positionedJoinedSourceVerifiedBoundarySeparators(
                        $last,
                        $run,
                        $gap,
                        max($run['fontSize'], $last['fontSize']),
                        (bool) ($last['endsWithWhitespace'] ?? false),
                        (bool) ($run['startsWithWhitespace'] ?? false),
                        (bool) ($run['hasWordBoundaryBefore'] ?? false),
                        (bool) ($run['wordBoundaryBefore'] ?? false),
                        is_string($run['wordBoundarySource'] ?? null)
                            ? $run['wordBoundarySource']
                            : null
                    );
                    $merged[$lastIndex] = [
                        'page' => $last['page'],
                        'text' => $this->joinPositionedCellText(
                            $last['text'],
                            $run['text'],
                            $gap,
                            max($run['fontSize'], $last['fontSize']),
                            (bool) ($last['endsWithWhitespace'] ?? false),
                            (bool) ($run['startsWithWhitespace'] ?? false),
                            (bool) ($run['hasWordBoundaryBefore'] ?? false),
                            (bool) ($run['wordBoundaryBefore'] ?? false),
                            is_string($run['wordBoundarySource'] ?? null)
                                ? $run['wordBoundarySource']
                                : null
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
                        'hasWordBoundaryBefore' => (bool) ($last['hasWordBoundaryBefore'] ?? false),
                        'wordBoundaryBefore' => (bool) ($last['wordBoundaryBefore'] ?? false),
                        'wordBoundarySource' => $last['wordBoundarySource'] ?? null,
                        'sourceVerifiedBoundarySeparators' => $sourceVerifiedBoundarySeparators,
                        'startsAfterTextBoundary' => (bool) ($last['startsAfterTextBoundary'] ?? false),
                        'order' => min((int) ($last['order'] ?? 0), (int) ($run['order'] ?? 0)),
                        'lastOrder' => max((int) ($last['lastOrder'] ?? $last['order'] ?? 0), (int) ($run['lastOrder'] ?? $run['order'] ?? 0)),
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

        $cellX1 = min($x1, $x2);
        $cellY1 = min($y1, $y2);
        $cellX2 = max($x1, $x2);
        $cellY2 = max($y1, $y2);
        $cellArea = ($cellX2 - $cellX1) * ($cellY2 - $cellY1);
        if ($cellArea <= 0.0) {
            return null;
        }

        $bestColor = null;
        $bestArea = INF;
        foreach ($filledRectangles as $rectangle) {
            $overlapWidth = max(0.0, min($cellX2, $rectangle['x2']) - max($cellX1, $rectangle['x1']));
            $overlapHeight = max(0.0, min($cellY2, $rectangle['y2']) - max($cellY1, $rectangle['y1']));
            $coverage = ($overlapWidth * $overlapHeight) / $cellArea;
            // A table rule can cross a cell center without being its fill.
            if ($coverage < 0.6) {
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
        if ($this->positionedRowsLookLikeNarrativeColumnLayout($rows, $columnCount)
            || $this->positionedRowsHavePredominantlyNonHorizontalText($rows)
            || $this->positionedRowsLookLikeCompactLabelGrid($rows, $columnCount)
            || $this->positionedRowsLookLikeShortLabelGrid($rows, $columnCount)) {
            $this->lowConfidenceGeometryTableCandidates++;

            return false;
        }
        if ($this->positionedRowsLookLikeTitleMetadataGrid($rows, $columnCount)) {
            $this->lowConfidenceGeometryTableCandidates++;

            return false;
        }
        if ($this->positionedRowsAreUndersizedNonNumericGrid($rows, $columnCount)
            || $this->positionedRowsHaveSparsePlaceholderColumns($rows, $columnCount)) {
            $this->lowConfidenceGeometryTableCandidates++;

            return false;
        }
        if ($this->positionedRowsLookLikeFormLayout($rows, $columnCount)) {
            $this->lowConfidenceGeometryTableCandidates++;

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
     * Side-by-side narrative columns share the visual alignment of a table,
     * but neither column has row-wise data semantics. Prefer editable prose
     * when most cells contain long text in wide columns. This deliberately
     * handles brochure panels with more than two columns as well as ordinary
     * two-column prose.
     *
     * @param list<list<mixed>> $rows
     */
    private function positionedRowsLookLikeNarrativeColumnLayout(array $rows, int $columnCount): bool
    {
        // Brochures can lay out several independent prose panels on one
        // baseline. They may look like a wide table, but long narrative cells
        // and recurring wide prose are the relevant evidence. Do not stop
        // evaluating that evidence merely because the layout has more than
        // six columns.
        if ($columnCount < 2 || $columnCount > 10 || count($rows) < 2) {
            return false;
        }

        $columnPresence = array_fill(0, $columnCount, 0);
        foreach ($rows as $row) {
            for ($index = 0; $index < $columnCount; $index++) {
                if (trim($this->cellTextValue($row[$index] ?? '')) !== '') {
                    $columnPresence[$index]++;
                }
            }
        }
        $minimumPresence = max(2, (int) ceil(count($rows) * 0.20));
        $activeColumns = [];
        foreach ($columnPresence as $index => $presence) {
            if ($presence >= $minimumPresence) {
                $activeColumns[] = $index;
            }
        }
        if (count($activeColumns) < 2 || count($activeColumns) > 10) {
            return false;
        }

        $multiCellRows = 0;
        $narrativeCells = 0;
        $wideCells = 0;
        $populatedCells = 0;
        foreach ($rows as $row) {
            $populated = 0;
            foreach ($activeColumns as $columnIndex) {
                $cell = $row[$columnIndex] ?? '';
                $text = trim($this->cellTextValue($cell));
                if ($text === '') {
                    continue;
                }
                $populated++;
                $populatedCells++;
                if ($this->positionedCellWordCount($text) >= 7 || $this->length($text) >= 48) {
                    $narrativeCells++;
                }
                $width = $this->positionedCellContentWidth($cell);
                if ($width !== null && $width >= 80.0) {
                    $wideCells++;
                }
            }
            if ($populated >= 2) {
                $multiCellRows++;
            }
        }

        if ($multiCellRows < max(2, (int) ceil(count($rows) * 0.50)) || $populatedCells === 0) {
            return false;
        }

        // In a broad panel layout, many perfectly ordinary prose lines are
        // short continuations. Requiring every panel to meet the longer-line
        // threshold misclassifies that layout as a table. The stronger wide
        // cell requirement keeps this limited to genuine side-by-side prose,
        // not a many-column data grid.
        $broadPanelLayout = count($activeColumns) >= 5;
        $requiredNarrativeRatio = $broadPanelLayout ? 0.30 : 0.40;
        $requiredWideCellRatio = $broadPanelLayout ? 0.75 : 0.65;

        return $narrativeCells / $populatedCells >= $requiredNarrativeRatio
            && $wideCells / $populatedCells >= $requiredWideCellRatio;
    }

    /**
     * @param list<list<mixed>> $rows
     */
    private function positionedRowsHavePredominantlyNonHorizontalText(array $rows): bool
    {
        $measuredCells = 0;
        $horizontalCells = 0;
        foreach ($rows as $row) {
            foreach ($row as $cell) {
                if (trim($this->cellTextValue($cell)) === '') {
                    continue;
                }
                $width = $this->positionedCellContentWidth($cell);
                if ($width === null) {
                    continue;
                }
                $measuredCells++;
                if ($width > 1.0) {
                    $horizontalCells++;
                }
            }
        }

        return $measuredCells >= 6 && $horizontalCells / $measuredCells < 0.30;
    }

    /**
     * Map legends can expose rotated or diagonally arranged label fragments
     * as a regular grid. Without readable data values or horizontal words,
     * that geometry is not sufficient evidence of a semantic table.
     *
     * @param list<list<mixed>> $rows
     */
    private function positionedRowsLookLikeCompactLabelGrid(array $rows, int $columnCount): bool
    {
        if ($columnCount < 2 || count($rows) < 2) {
            return false;
        }

        $widths = [];
        $populatedCells = 0;
        foreach ($rows as $row) {
            foreach ($row as $cell) {
                $text = trim($this->cellTextValue($cell));
                if ($text === '') {
                    continue;
                }
                if ($this->positionedCellLooksNumericAnchor($text)
                    || preg_match('/[\pL\pN]{3,}/u', $text) === 1) {
                    return false;
                }
                $populatedCells++;
                $width = $this->positionedCellContentWidth($cell);
                if ($width !== null) {
                    $widths[] = $width;
                }
            }
        }

        return $populatedCells >= 4
            && count($widths) >= 4
            && $this->median($widths) <= 8.0;
    }

    /**
     * @param list<list<mixed>> $rows
     */
    private function positionedRowsLookLikeShortLabelGrid(array $rows, int $columnCount): bool
    {
        if ($columnCount < 2 || count($rows) < 2) {
            return false;
        }

        $populatedCells = 0;
        $shortCells = 0;
        $numericCells = 0;
        $wideCells = 0;
        foreach ($rows as $row) {
            foreach ($row as $cell) {
                $text = trim($this->cellTextValue($cell));
                if ($text === '') {
                    continue;
                }
                $populatedCells++;
                if ($this->positionedCellWordCount($text) <= 2) {
                    $shortCells++;
                }
                if ($this->positionedCellLooksNumericAnchor($text)) {
                    $numericCells++;
                }
                $width = $this->positionedCellContentWidth($cell);
                if ($width !== null && $width >= 80.0) {
                    $wideCells++;
                }
            }
        }

        $rowGap = $this->positionedLogicalRowsMedianGap($rows);

        return $populatedCells >= 4
            && $shortCells / $populatedCells >= 0.70
            && $numericCells / $populatedCells <= 0.30
            && $wideCells / $populatedCells <= 0.10
            && $rowGap !== null
            && $rowGap <= 12.0;
    }

    /**
     * @param list<list<mixed>> $rows
     */
    private function positionedLogicalRowsMedianGap(array $rows): ?float
    {
        $centers = [];
        foreach ($rows as $row) {
            $cellCenters = [];
            foreach ($row as $cell) {
                if (trim($this->cellTextValue($cell)) === '' || !is_array($cell)) {
                    continue;
                }
                $y1 = $this->numericValue($cell['contentY1'] ?? $cell['y1'] ?? null);
                $y2 = $this->numericValue($cell['contentY2'] ?? $cell['y2'] ?? null);
                if ($y1 !== null && $y2 !== null) {
                    $cellCenters[] = ($y1 + $y2) / 2.0;
                }
            }
            if ($cellCenters !== []) {
                $centers[] = $this->median($cellCenters);
            }
        }
        if (count($centers) < 2) {
            return null;
        }

        $gaps = [];
        for ($index = 0, $count = count($centers) - 1; $index < $count; $index++) {
            $gap = abs($centers[$index] - $centers[$index + 1]);
            if ($gap > 0.0) {
                $gaps[] = $gap;
            }
        }

        return $gaps === [] ? null : $this->median($gaps);
    }

    private function positionedCellContentWidth(mixed $cell): ?float
    {
        if (!is_array($cell)) {
            return null;
        }
        $x1 = $this->numericValue($cell['contentX1'] ?? $cell['x1'] ?? null);
        $x2 = $this->numericValue($cell['contentX2'] ?? $cell['x2'] ?? null);

        return $x1 === null || $x2 === null ? null : max(0.0, $x2 - $x1);
    }

    /**
     * A two-row, two-column title and qualifier pair is common in forms and
     * page headers. It is not enough evidence to turn the surrounding page
     * into a data table.
     *
     * @param list<list<mixed>> $rows
     */
    private function positionedRowsLookLikeTitleMetadataGrid(array $rows, int $columnCount): bool
    {
        if ($columnCount !== 2 || count($rows) !== 2) {
            return false;
        }

        $texts = [];
        foreach ($rows as $row) {
            $populated = [];
            foreach ($row as $cell) {
                $text = trim($this->cellTextValue($cell));
                if ($text !== '') {
                    $populated[] = $text;
                }
            }
            if (count($populated) !== 2) {
                return false;
            }
            $texts[] = $populated;
        }

        foreach ([...$texts[0], ...$texts[1]] as $text) {
            if ($this->positionedCellIsNumericValue($text)) {
                return false;
            }
        }

        $firstRowCompact = $this->positionedCellWordCount($texts[0][0]) <= 5
            && $this->positionedCellWordCount($texts[0][1]) <= 5;
        $secondRowHasNarrative = max(
            $this->positionedCellWordCount($texts[1][0]),
            $this->positionedCellWordCount($texts[1][1])
        ) >= 5;

        return $firstRowCompact && $secondRowHasNarrative;
    }

    /**
     * A small three-or-more-column grid needs data values before it is safer
     * to call it a table than to preserve it as a form or visual layout.
     *
     * @param list<list<mixed>> $rows
     */
    private function positionedRowsAreUndersizedNonNumericGrid(array $rows, int $columnCount): bool
    {
        if (count($rows) >= 5 || $columnCount < 3) {
            return false;
        }
        if (count($rows) === 2 && $this->positionedRowsHaveFilledEmptyCell($rows)) {
            return false;
        }

        $numericValues = 0;
        foreach ($rows as $row) {
            foreach ($row as $cell) {
                if ($this->positionedCellIsNumericValue(trim($this->cellTextValue($cell)))) {
                    $numericValues++;
                }
            }
        }

        return $numericValues < 2;
    }

    /**
     * @param list<list<mixed>> $rows
     */
    private function positionedRowsHaveFilledEmptyCell(array $rows): bool
    {
        foreach ($rows as $row) {
            foreach ($row as $cell) {
                if (!is_array($cell) || trim($this->cellTextValue($cell)) !== '') {
                    continue;
                }
                $attributes = is_array($cell['htmlAttributes'] ?? null) ? $cell['htmlAttributes'] : [];
                if (is_string($attributes['data-pdf-fill-color'] ?? null) && $attributes['data-pdf-fill-color'] !== '') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<list<mixed>> $rows
     */
    private function positionedRowsHaveSparsePlaceholderColumns(array $rows, int $columnCount): bool
    {
        if ($columnCount < 12 || $rows === []) {
            return false;
        }

        $populated = 0;
        $placeholders = 0;
        foreach ($rows as $row) {
            foreach ($row as $cell) {
                $text = trim($this->cellTextValue($cell));
                if ($text === '') {
                    continue;
                }

                $populated++;
                if (preg_match('/^[._\x{00B7}\x{2026}\x{2013}\x{2014}-]+$/u', $text) === 1) {
                    $placeholders++;
                }
            }
        }
        if ($populated === 0) {
            return true;
        }

        $fillRatio = $populated / (count($rows) * $columnCount);

        return $fillRatio < 0.50 && $placeholders / $populated >= 0.20;
    }

    /**
     * A short matrix of stacked labels beside prose is usually a printable
     * form layout, not a semantic table. Prefer document text for that
     * ambiguous case unless the first row has an ordinary compact header.
     *
     * @param list<list<mixed>> $rows
     */
    private function positionedRowsLookLikeFormLayout(array $rows, int $columnCount): bool
    {
        $rowCount = count($rows);
        if ($rowCount < 2 || $rowCount > 8 || $columnCount < 3) {
            return false;
        }
        if ($this->positionedRowLooksLikeCompactTableHeader($rows[0] ?? [])) {
            return false;
        }

        $leadingCells = 0;
        $shortLeadingCells = 0;
        $detailRows = 0;
        $numericValueCells = 0;
        $populatedCells = 0;
        foreach ($rows as $row) {
            $firstCell = true;
            $hasDetail = false;
            foreach ($row as $cell) {
                $text = trim($this->cellTextValue($cell));
                if ($text === '') {
                    continue;
                }

                $populatedCells++;
                if ($this->positionedCellIsNumericValue($text)) {
                    $numericValueCells++;
                }
                if ($firstCell) {
                    $leadingCells++;
                    if ($this->positionedCellWordCount($text) <= 4) {
                        $shortLeadingCells++;
                    }
                    $firstCell = false;
                    continue;
                }
                if ($this->positionedCellWordCount($text) >= 8) {
                    $hasDetail = true;
                }
            }
            if ($hasDetail) {
                $detailRows++;
            }
        }

        if ($leadingCells < (int) ceil($rowCount * 0.75) || $populatedCells === 0) {
            return false;
        }

        return $shortLeadingCells / $leadingCells >= 0.60
            && $detailRows >= max(1, (int) ceil($rowCount * 0.35))
            && $numericValueCells / $populatedCells < 0.15;
    }

    /**
     * @param list<mixed> $row
     */
    private function positionedRowLooksLikeCompactTableHeader(array $row): bool
    {
        $compactCells = 0;
        $populatedCells = 0;
        foreach ($row as $cell) {
            $text = trim($this->cellTextValue($cell));
            if ($text === '') {
                continue;
            }

            $populatedCells++;
            if ($this->positionedCellWordCount($text) <= 4) {
                $compactCells++;
            }
        }

        return $populatedCells >= 3 && $compactCells / $populatedCells >= 0.80;
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
        $compactTwoColumnGrid = $rowCount === 2
            && $columnCount === 2
            && $numericAnchors < 2
            && $this->positionedRowsFormCompactTwoColumnGrid($rows);

        $score = 0.0;
        $score += $rowCount >= 3 ? 0.18 : 0.08;
        $score += $columnCount >= 3 ? 0.18 : 0.08;
        $score += 0.20 * min(1.0, $multiCellRowRatio);
        $score += 0.20 * min(1.0, $recurringColumnRatio);
        $score += $fillRatio >= 0.70 ? 0.16 : ($fillRatio >= 0.50 ? 0.08 : 0.0);
        $score += $numericRatio >= 0.20 ? 0.14 : ($numericAnchors >= 2 ? 0.10 : 0.0);
        $score += $wideCellRatio >= 0.75 ? 0.08 : ($wideCellRatio >= 0.50 ? 0.04 : 0.0);

        if ($rowCount <= 2 && $columnCount <= 2 && $numericAnchors < 2) {
            $score = min($score, $compactTwoColumnGrid ? 0.76 : 0.55);
        }
        if ($columnCount === 2 && $rowCount < 4 && $numericAnchors < 2) {
            $score = min($score, $compactTwoColumnGrid ? 0.76 : 0.65);
        }

        return round(min(1.0, $score), 4);
    }

    /**
     * A fully populated 2x2 grid whose rendered cell contents are small
     * compared with its gutter is structural evidence, unlike two prose
     * columns that merely happen to have two lines.
     *
     * @param list<list<mixed>> $rows
     */
    private function positionedRowsFormCompactTwoColumnGrid(array $rows): bool
    {
        if (count($rows) !== 2) {
            return false;
        }

        $leftStarts = [];
        $rightStarts = [];
        $widestCell = 0.0;
        foreach ($rows as $row) {
            $populated = [];
            foreach ($row as $cell) {
                if ($this->cellTextValue($cell) !== '') {
                    $populated[] = $cell;
                }
            }
            if (count($populated) !== 2) {
                return false;
            }

            $left = $populated[0];
            $right = $populated[1];
            if (!is_array($left) || !is_array($right)) {
                return false;
            }
            $leftStart = $this->numericValue($left['contentX1'] ?? $left['x1'] ?? null);
            $rightStart = $this->numericValue($right['contentX1'] ?? $right['x1'] ?? null);
            $leftEnd = $this->numericValue($left['contentX2'] ?? $left['x2'] ?? null);
            $rightEnd = $this->numericValue($right['contentX2'] ?? $right['x2'] ?? null);
            if ($leftStart === null || $rightStart === null || $leftEnd === null || $rightEnd === null || $rightStart <= $leftStart) {
                return false;
            }

            $leftStarts[] = $leftStart;
            $rightStarts[] = $rightStart;
            $widestCell = max($widestCell, $leftEnd - $leftStart, $rightEnd - $rightStart);
        }

        $columnGutter = $this->median($rightStarts) - $this->median($leftStarts);

        return $columnGutter > 0.0 && $widestCell <= $columnGutter * 0.42;
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

    private function positionedCellIsNumericValue(string $text): bool
    {
        return preg_match('/^\s*[-+]?(?:[$€£¥]\s*)?\d+(?:[,\s]\d{3})*(?:[.,]\d+)?\s*(?:[%$€£¥])?\s*$/u', $text) === 1;
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
        bool $rightStartsWithWhitespace = false,
        bool $rightHasWordBoundaryBefore = false,
        bool $rightWordBoundaryBefore = false,
        ?string $rightWordBoundarySource = null
    ): string
    {
        $separator = $this->positionedBoundarySeparator(
            $gap,
            $fontSize,
            $leftEndsWithWhitespace,
            $rightStartsWithWhitespace,
            $rightHasWordBoundaryBefore,
            $rightWordBoundaryBefore,
            $rightWordBoundarySource
        );

        return $this->positionedCellText($left . $separator . $right);
    }

    /**
     * Decide one painted run boundary from its local provenance and rendered
     * endpoints. This never consults the text of either fragment: a false
     * text-position continuation can still have a visible word gap, while a
     * producer can mark a touching reset as a line break.
     */
    private function positionedBoundarySeparator(
        float $gap,
        float $fontSize,
        bool $leftEndsWithWhitespace,
        bool $rightStartsWithWhitespace,
        bool $rightHasWordBoundaryBefore,
        bool $rightWordBoundaryBefore,
        ?string $rightWordBoundarySource
    ): string {
        // A literal text-showing whitespace operand is direct evidence and
        // deliberately wins over any coordinate or line-break estimate.
        if ($leftEndsWithWhitespace || $rightStartsWithWhitespace) {
            return ' ';
        }

        $fontSize = max(1.0, $fontSize);
        $substantialGap = $gap > max(0.75, $fontSize * 0.18);
        $touchingOrOverlap = $gap <= max(0.50, $fontSize * 0.08);

        if ($rightHasWordBoundaryBefore) {
            if (!$rightWordBoundaryBefore) {
                // Incomplete font metrics can make the extractor label this
                // transition a continuation. Its rendered endpoints still
                // prove a separator at this one occurrence when a material
                // gap remains.
                return $rightWordBoundarySource === 'text-position-continuation'
                    && $substantialGap
                    ? ' '
                    : '';
            }

            // A real line break normally starts a new word, but some PDF
            // producers reset the line matrix between pieces of one painted
            // word. Touching or overlapping glyph endpoints are stronger
            // local evidence than that bookkeeping boundary.
            if ($rightWordBoundarySource === 'line-break' && $touchingOrOverlap) {
                return '';
            }

            return ' ';
        }

        return $gap > max(1.0, $fontSize * 0.35) ? ' ' : '';
    }

    /**
     * A boundary decision is locally safe to reconcile with source text only
     * when it came from an explicit extractor provenance or a literal text
     * whitespace operand. The decision may be an empty separator: a touching
     * line-matrix reset can prove that a source-space belongs inside one word.
     */
    private function positionedBoundaryHasSourceVerifiedDecision(
        bool $leftEndsWithWhitespace,
        bool $rightStartsWithWhitespace,
        bool $rightHasWordBoundaryBefore,
        ?string $rightWordBoundarySource
    ): bool {
        if ($leftEndsWithWhitespace || $rightStartsWithWhitespace) {
            return true;
        }

        return $rightHasWordBoundaryBefore && in_array(
            $rightWordBoundarySource,
            ['text-position-continuation', 'text-position-layout', 'tj-layout', 'line-break'],
            true
        );
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     * @return array<int, string> Compact-character offset => required separator.
     */
    private function positionedJoinedSourceVerifiedBoundarySeparators(
        array $left,
        array $right,
        float $gap,
        float $fontSize,
        bool $leftEndsWithWhitespace,
        bool $rightStartsWithWhitespace,
        bool $rightHasWordBoundaryBefore,
        bool $rightWordBoundaryBefore,
        ?string $rightWordBoundarySource
    ): array {
        $separators = $this->positionedSourceVerifiedBoundarySeparators($left);
        $leftLength = $this->positionedCompactTextLength((string) ($left['text'] ?? ''));
        if ($this->positionedBoundaryHasSourceVerifiedDecision(
            $leftEndsWithWhitespace,
            $rightStartsWithWhitespace,
            $rightHasWordBoundaryBefore,
            $rightWordBoundarySource
        )) {
            $separators[$leftLength] = $this->positionedBoundarySeparator(
                $gap,
                $fontSize,
                $leftEndsWithWhitespace,
                $rightStartsWithWhitespace,
                $rightHasWordBoundaryBefore,
                $rightWordBoundaryBefore,
                $rightWordBoundarySource
            );
        }
        foreach ($this->positionedSourceVerifiedBoundarySeparators($right) as $offset => $separator) {
            $separators[$leftLength + $offset] = $separator;
        }

        foreach ($separators as $offset => $separator) {
            if ($offset <= 0 || ($separator !== '' && $separator !== ' ')) {
                unset($separators[$offset]);
            }
        }
        ksort($separators, SORT_NUMERIC);

        return $separators;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<int, string>
     */
    private function positionedSourceVerifiedBoundarySeparators(array $item): array
    {
        $separators = [];
        foreach (($item['sourceVerifiedBoundarySeparators'] ?? []) as $offset => $separator) {
            if (is_int($offset)
                && $offset > 0
                && ($separator === '' || $separator === ' ')) {
                $separators[$offset] = $separator;
            }
        }
        ksort($separators, SORT_NUMERIC);

        return $separators;
    }

    private function positionedCompactTextLength(string $text): int
    {
        return $this->length(preg_replace('/\s+/u', '', $text) ?? $text);
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

        if (count($rows) < 2 || $this->tableRowsLookLikeFragmentedLabelGrid($rows)) {
            return ['rows' => [], 'consumed' => 0];
        }

        return ['rows' => $rows, 'consumed' => $consumed];
    }

    /**
     * Detects tables whose cells were extracted as one line per visual cell.
     *
     * @param list<string> $lines
     * @return array{rows: list<list<string>>, consumed: int}
     */
    private function stackedTableRowsAt(array $lines, int $start): array
    {
        $lineCount = count($lines);
        foreach ([3, 4, 5] as $columnCount) {
            if ($start + ($columnCount * 4) > $lineCount) {
                continue;
            }
            $header = [];
            for ($offset = 0; $offset < $columnCount; $offset++) {
                $headerLine = trim($lines[$start + $offset] ?? '');
                if (!$this->stackedTableHeaderCell($headerLine)) {
                    $header = [];
                    break;
                }
                $header[] = $headerLine;
            }
            if ($header === []) {
                continue;
            }

            $rows = [$header];
            $index = $start + $columnCount;
            while ($index + $columnCount - 1 < $lineCount && $this->stackedTableRowKeyCell((string) ($lines[$index] ?? ''))) {
                $row = [];
                for ($column = 0; $column < $columnCount; $column++) {
                    $cell = trim((string) ($lines[$index + $column] ?? ''));
                    if ($cell === '' || ($column > 0 && !$this->stackedTableBodyCell($cell))) {
                        break 2;
                    }
                    $row[] = $cell;
                }
                $index += $columnCount;

                $continuations = 0;
                while (
                    $index < $lineCount
                    && !$this->stackedTableRowKeyCell((string) ($lines[$index] ?? ''))
                    && $this->stackedTableContinuationCell((string) ($lines[$index] ?? ''))
                ) {
                    $row[$columnCount - 1] .= ' ' . trim((string) $lines[$index]);
                    $index++;
                    $continuations++;
                    if ($continuations >= 2) {
                        break;
                    }
                }

                $rows[] = $row;
            }

            if (count($rows) >= 4) {
                if (!$this->stackedTableRowsLookLikeDataGrid($rows, $columnCount)
                    || $this->tableRowsLookLikeFragmentedLabelGrid($rows)) {
                    continue;
                }

                return ['rows' => $rows, 'consumed' => $index - $start];
            }
        }

        return ['rows' => [], 'consumed' => 0];
    }

    /**
     * Stacked grids are prone to false positives when diagram labels and
     * single-glyph markers survive as a regular sequence. Require each grid
     * to carry repeated textual values or substantial numeric values; a run of
     * isolated one-digit markers is preserved as prose instead.
     *
     * @param list<list<string>> $rows
     */
    private function stackedTableRowsLookLikeDataGrid(array $rows, int $columnCount): bool
    {
        $bodyCells = 0;
        $numericCells = 0;
        $semanticRows = 0;
        foreach (array_slice($rows, 1) as $row) {
            $rowHasSemanticValue = false;
            foreach ($row as $cell) {
                $text = trim($cell);
                if ($text === '') {
                    continue;
                }
                $bodyCells++;
                if ($this->positionedCellIsNumericValue($text)) {
                    $numericCells++;
                }
                if (preg_match('/\p{L}{3,}/u', $text) === 1
                    || preg_match('/(?:[$€£¥]|\d[\d,]*\.\d+|\d{2,})/u', $text) === 1) {
                    $rowHasSemanticValue = true;
                }
            }
            if ($rowHasSemanticValue) {
                $semanticRows++;
            }
        }

        $bodyRows = max(1, count($rows) - 1);
        if ($semanticRows >= (int) ceil($bodyRows * 0.50)) {
            return true;
        }

        return $columnCount > 3
            && $numericCells >= max(3, (int) ceil($bodyCells * 0.20));
    }

    /**
     * A columnar text stream such as "T / r / ace" is a split label, not
     * three data cells. This occurs in vector diagrams where each word is
     * painted in separate fragments. It is intentionally rejected before the
     * generic stacked-table parser can promote the diagram to a data table.
     *
     * @param list<list<string>> $rows
     */
    private function tableRowsLookLikeFragmentedLabelGrid(array $rows): bool
    {
        if (count($rows) < 2 || count($rows[0] ?? []) < 3) {
            return false;
        }

        $fragmentedRows = 0;
        foreach ($rows as $row) {
            $fragments = 0;
            for ($index = 0, $limit = count($row) - 1; $index < $limit; $index++) {
                $left = $this->lastWordToken((string) $row[$index]);
                $right = $this->firstWordToken((string) $row[$index + 1]);
                if (preg_match('/^\p{L}{1,2}$/u', $left) !== 1
                    || preg_match('/^\p{Ll}{1,8}$/u', $right) !== 1) {
                    continue;
                }
                $fragments++;
            }
            if ($fragments >= 2) {
                $fragmentedRows++;
            }
        }

        return $fragmentedRows >= 2 && $fragmentedRows / count($rows) >= 0.50;
    }

    private function stackedTableHeaderCell(string $line): bool
    {
        $line = trim($line);
        if ($line === '' || $this->listItem($line) !== null || $this->length($line) > 40) {
            return false;
        }
        if (preg_match('/[.!?]\s*$/u', $line) === 1 || preg_match('/^\d+$/u', $line) === 1) {
            return false;
        }

        return preg_match('/\p{L}/u', $line) === 1;
    }

    private function stackedTableRowKeyCell(string $line): bool
    {
        $line = trim($line);
        if ($line === '' || str_contains($line, ' ') || $this->length($line) > 10) {
            return false;
        }
        if (preg_match('/[.!?,:;]$/u', $line) === 1) {
            return false;
        }

        return preg_match('/\d/u', $line) === 1 || preg_match('/^[\p{Lu}]{1,4}$/u', $line) === 1;
    }

    private function stackedTableBodyCell(string $line): bool
    {
        $line = trim($line);
        if ($line === '' || $this->listItem($line) !== null || $this->length($line) > 120) {
            return false;
        }

        return preg_match('/[.!?]\s*$/u', $line) !== 1;
    }

    private function stackedTableContinuationCell(string $line): bool
    {
        $line = trim($line);
        if ($line === '' || $this->listItem($line) !== null || $this->length($line) > 50) {
            return false;
        }
        if (preg_match('/[.!?]\s*$/u', $line) === 1) {
            return false;
        }
        $tokens = preg_split('/\s+/u', $line) ?: [];

        return count($tokens) <= 4;
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
     * @param list<string> $lines
     */
    private function numberedPdfLineLooksLikeSectionHeading(array $lines, int $index): bool
    {
        $line = $lines[$index] ?? '';
        if (preg_match('/^\s*(\d+(?:\.\d+)*)(?:\.)?\s+(.+)$/u', $line, $matches) !== 1
            || preg_match('/^\p{Lu}/u', $matches[2]) !== 1
            || !$this->repairedLineLooksLikeSectionLabel($matches[2])) {
            return false;
        }

        // A final item has no following sibling to inspect, so also retain a
        // list when its immediately preceding item proves the sequence.
        $previous = $lines[$index - 1] ?? null;
        if ($previous !== null
            && preg_match('/^\s*(\d+)\.\s+/', $previous, $previousMatches) === 1
            && preg_match('/^\d+$/', $matches[1]) === 1
            && (int) $matches[1] === (int) $previousMatches[1] + 1) {
            return false;
        }

        $next = $lines[$index + 1] ?? null;
        if ($next === null) {
            return false;
        }
        $nextItem = $this->listItem($next);
        if ($nextItem === null || $nextItem[0] !== true) {
            return true;
        }
        if (preg_match('/^\s*(\d+)\./u', $next, $nextMatches) !== 1) {
            return true;
        }

        return (int) $nextMatches[1] !== (int) $matches[1] + 1;
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
        if ($this->listItem($line) !== null || $this->lineLooksLikePdfReferenceEntry($line)) {
            return true;
        }

        [$allowBullets, $allowOrdered] = $this->embeddedListMarkerPermissions($this->embeddedListMarkers($line));

        return $allowBullets || $allowOrdered;
    }

    private function lineLooksLikePdfReferenceEntry(string $line): bool
    {
        return preg_match('/^\s*\[\d+(?:[,.\-\x{2013}\x{2014}]\d+)*\]\s+/u', $line) === 1;
    }

    private function pdfReferenceEntryEndsWithUrl(string $line): bool
    {
        return preg_match('/(?:https?:\/\/|www\.)\S+[.)]\s*$/i', rtrim($line)) === 1;
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

        // A capitalized first word is normal sentence prose. Without tagged
        // PDF structure or retained style metadata, require a real title
        // pattern before promoting a line to a heading. This keeps wrapped
        // sentence fragments out of the document outline.
        return $this->repairedLineLooksLikeSectionLabel($line);
    }

    private function paragraph(string $text, array $attrs = []): AstNode
    {
        return new AstNode('paragraph', array_replace($attrs, ['text' => $text]), $this->inlines($text));
    }

    /**
     * @param list<array<string, mixed>> $annotations
     * @param list<AstNode> $blocks
     * @return list<array<string, mixed>>
     */
    private function unambiguousLinkAnnotations(array $annotations, array $blocks): array
    {
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

            // Prefer an exact final-text match. Annotation geometry can have
            // a different idea of word spacing than the text stream, so a
            // unique whitespace-only reconciliation is also safe. Never
            // reconcile across nodes or accept a duplicate label.
            $matchedText = $this->uniqueLinkAnnotationTextMatch($blocks, $text)
                ?? $this->uniqueWhitespaceReconciledLinkAnnotationTextMatch($blocks, $text);
            if ($matchedText === null) {
                continue;
            }

            $normalizedAnnotation = $annotation;
            $normalizedAnnotation['text'] = $matchedText;
            $normalizedAnnotation['uri'] = $uri;
            if ($matchedText !== $text) {
                $normalizedAnnotation['annotationText'] = $text;
            }
            $normalized[] = $normalizedAnnotation + [
                'text' => $matchedText,
                'uri' => $uri,
            ];
        }

        return $normalized;
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function uniqueLinkAnnotationTextMatch(array $blocks, string $label): ?string
    {
        if ($label === '') {
            return null;
        }

        $matches = 0;
        foreach ($this->linkAnnotationTextNodes($blocks) as $text) {
            $offset = 0;
            while (($position = strpos($text, $label, $offset)) !== false) {
                $matches++;
                if ($matches > 1) {
                    return null;
                }
                $offset = $position + strlen($label);
            }
        }

        return $matches === 1 ? $label : null;
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function uniqueWhitespaceReconciledLinkAnnotationTextMatch(array $blocks, string $label): ?string
    {
        $compact = preg_replace('/\s+/u', '', $label) ?? $label;
        if ($this->length($compact) < 5 || preg_match('/[\p{L}\p{N}]/u', $compact) !== 1) {
            return null;
        }

        $characters = preg_split('//u', $compact, -1, PREG_SPLIT_NO_EMPTY);
        if ($characters === false || $characters === []) {
            return null;
        }
        $startsWithWord = preg_match('/^[\p{L}\p{N}]$/u', $characters[0]) === 1;
        $endsWithWord = preg_match('/[\p{L}\p{N}]$/u', $characters[array_key_last($characters)]) === 1;
        $pattern = '/'
            . ($startsWithWord ? '(?<![\p{L}\p{N}])' : '')
            . implode('\\s*', array_map(static fn (string $character): string => preg_quote($character, '/'), $characters))
            . ($endsWithWord ? '(?![\p{L}\p{N}])' : '')
            . '/u';

        $matches = [];
        foreach ($this->linkAnnotationTextNodes($blocks) as $text) {
            $foundCount = preg_match_all($pattern, $text, $found);
            if ($foundCount === false || $foundCount === 0) {
                continue;
            }
            foreach ($found[0] ?? [] as $match) {
                if ((preg_replace('/\s+/u', '', $match) ?? $match) !== $compact) {
                    continue;
                }
                $matches[] = $match;
                if (count($matches) > 1) {
                    return null;
                }
            }
        }

        return $matches[0] ?? null;
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<string>
     */
    private function linkAnnotationTextNodes(array $nodes): array
    {
        $texts = [];
        foreach ($nodes as $node) {
            $text = $node->attr('text');
            if (is_string($text) && in_array($node->type, ['paragraph', 'heading', 'plain'], true)) {
                $texts[] = $text;
            }
            if ($node->children !== []) {
                array_push($texts, ...$this->linkAnnotationTextNodes($node->children));
            }
        }

        return $texts;
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

    /**
     * Dense figures can produce many cell-like regions even when they contain
     * ordinary prose. A document with several such large prose grids is not a
     * trustworthy geometry-table result, so let the text-table path decide
     * whether there is a real table instead.
     *
     * @param list<AstNode> $nodes
     */
    private function geometryPdfTableBlocksLookOversegmented(array $nodes): bool
    {
        $tables = [];
        $collectTables = function (array $candidates) use (&$collectTables, &$tables): void {
            foreach ($candidates as $candidate) {
                if ($candidate->type === 'table') {
                    $tables[] = $candidate;
                }
                if ($candidate->children !== []) {
                    $collectTables($candidate->children);
                }
            }
        };
        $collectTables($nodes);
        if (count($tables) < 6) {
            return false;
        }

        $proseGrids = 0;
        foreach ($tables as $table) {
            $cellTexts = [];
            $collectCells = function (AstNode $candidate) use (&$collectCells, &$cellTexts): void {
                if ($candidate->type === 'table_cell') {
                    $cellTexts[] = trim((string) ($candidate->attrs['text'] ?? ''));
                }
                foreach ($candidate->children as $child) {
                    $collectCells($child);
                }
            };
            $collectCells($table);

            $longProseCells = 0;
            foreach ($cellTexts as $text) {
                if ($this->length($text) >= 72 || count($this->pdfLineWordTokens($text)) >= 12) {
                    $longProseCells++;
                }
            }
            if ($longProseCells >= 3) {
                $proseGrids++;
            }
        }

        return $proseGrids >= max(3, (int) ceil(count($tables) * 0.25));
    }

    /**
     * @return array<string, mixed>
     */
    private function documentStructuralMetadata(string $pdfBytes): array
    {
        if (!class_exists(PdfMetadataExtractor::class)) {
            return [];
        }

        try {
            return (new PdfMetadataExtractor())->extractReaderStructuralMetadata($pdfBytes);
        } catch (\Throwable) {
            // Structural provenance is optional. Do not fall back to scanning
            // arbitrary PDF bytes when the parser cannot establish it.
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function documentMetadata(string $pdfBytes): array
    {
        if (!class_exists(PdfMetadataExtractor::class)) {
            return [];
        }

        try {
            return (new PdfMetadataExtractor())->extractReaderMetadata($pdfBytes);
        } catch (\Throwable) {
            // Metadata is optional import provenance.  Do not fall back to
            // scanning arbitrary PDF bytes when the parser cannot establish
            // a bounded Info dictionary or Metadata stream.
            return [];
        }
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function metadataString(array $metadata, string $key): string
    {
        return is_string($metadata[$key] ?? null)
            ? $this->clipMetadataValue($metadata[$key])
            : '';
    }

    private function firstMetadataString(mixed $value): string
    {
        if (!is_array($value)) {
            return '';
        }

        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                return $this->clipMetadataValue($item);
            }
        }

        return '';
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
