<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class SuppliedDocumentConverter
{
    private PdfTextDocumentExtractor $textExtractor;
    private CorePdfConverter $coreConverter;
    private LayoutAnnotator $layoutAnnotator;
    private LayoutOrderer $layoutOrderer;
    private TableFormatter $tableFormatter;
    private TableRecognizer $tableRecognizer;
    private ConversionFinalizer $finalizer;
    private PdfPageArtifactSelector $artifactSelector;

    public function __construct(
        ?PdfTextDocumentExtractor $textExtractor = null,
        ?CorePdfConverter $coreConverter = null,
        ?LayoutAnnotator $layoutAnnotator = null,
        ?LayoutOrderer $layoutOrderer = null,
        ?TableFormatter $tableFormatter = null,
        ?TableRecognizer $tableRecognizer = null,
        ?ConversionFinalizer $finalizer = null,
        ?PdfPageArtifactSelector $artifactSelector = null
    ) {
        $this->textExtractor = $textExtractor ?? new PdfTextDocumentExtractor();
        $this->coreConverter = $coreConverter ?? new CorePdfConverter();
        $this->layoutAnnotator = $layoutAnnotator ?? new LayoutAnnotator();
        $this->layoutOrderer = $layoutOrderer ?? new LayoutOrderer();
        $this->tableFormatter = $tableFormatter ?? new TableFormatter();
        $this->tableRecognizer = $tableRecognizer ?? new TableRecognizer();
        $this->finalizer = $finalizer ?? new ConversionFinalizer();
        $this->artifactSelector = $artifactSelector ?? new PdfPageArtifactSelector();
    }

    /**
     * Document-level native supplied-boundary slice for marker.convert::convert_single_pdf.
     *
     * The caller supplies the artifacts that upstream would normally obtain
     * from pdftext, pypdfium2, Surya ordering/layout, and tabled recognition.
     *
     * @param list<array<string, mixed>> $pdftextPages
     * @param array{
     *     toc?: list<array<string, mixed>>,
     *     max_pages?: int|null,
     *     start_page?: int|null,
     *     metadata?: array<string, mixed>,
     *     langs?: list<string>|null,
     *     batch_multiplier?: int|float,
     *     ocr_all_pages?: bool,
     *     document_page_count?: int|null,
     *     lowres_images?: list<mixed>,
     *     layout_results?: list<array<string, mixed>>,
     *     order_images?: list<mixed>,
     *     order_results?: list<array<string, mixed>>,
     *     bad_span_ids?: list<string>,
     *     ocr_stats?: array<string, mixed>|null,
     *     markdown_tables?: list<string>,
     *     recognized_tables?: list<array<string, mixed>>,
     *     table_text_lines?: list<mixed>,
     *     table_rendered_image_sizes?: array<int, array{width?: int|float, height?: int|float}|list<int|float>>,
     *     table_dpi?: int|float,
     *     table_intersection_threshold?: int|float,
     *     page_review_metadata?: list<array<string, mixed>>,
     *     table_detector_cells?: list<list<array<string, mixed>>>,
     *     table_ocr_text_lines?: list<list<string|array{text?: string}>>,
     *     table_detect_boxes?: bool,
     *     equation_predictions?: list<string>,
     *     equation_results?: list<array<string, mixed>>,
     *     equation_model_max_tokens?: int,
     *     equation_intersection_threshold?: int|float,
     *     image_payloads?: list<list<mixed>>
     * } $options
     * @return array{text: string, images: array<string, mixed>, metadata: array<string, mixed>, context: array<string, mixed>}
     */
    public function convert(string $filename, array $pdftextPages, array $options = [], ?MarkerSettings $settings = null): array
    {
        $settings ??= new MarkerSettings();
        $toc = $this->listOption($options, 'toc');
        $maxPages = $this->nullableIntOption($options, 'max_pages');
        $startPage = $this->nullableIntOption($options, 'start_page');
        $metadata = $this->arrayOption($options, 'metadata');
        $langs = array_key_exists('langs', $options) && $options['langs'] !== null
            ? $this->stringListOption($options, 'langs')
            : null;
        $batchMultiplier = $this->numericOption($options, 'batch_multiplier', 1.0);
        $ocrAllPages = (bool) ($options['ocr_all_pages'] ?? false);
        $sourcePageCount = count($pdftextPages);
        $documentPageCount = $this->nullableIntOption($options, 'document_page_count') ?? $sourcePageCount;

        $extracted = $this->textExtractor->getTextBlocks(
            $pdftextPages,
            maxPages: $maxPages,
            startPage: $startPage,
            toc: $toc
        );

        return $this->coreConverter->convertWithSuppliedPages(
            $filename,
            $extracted['pages'],
            $extracted['toc'],
            function (array $pages, array $context) use ($options, $settings, $extracted, $batchMultiplier, $sourcePageCount): array {
                return $this->runSuppliedPipeline($pages, $context, $options, $settings, $extracted, (float) $batchMultiplier, $sourcePageCount);
            },
            maxPages: $maxPages,
            startPage: $startPage,
            metadata: $metadata,
            langs: $langs,
            batchMultiplier: $batchMultiplier,
            ocrAllPages: $ocrAllPages,
            documentPageCount: $documentPageCount,
            settings: $settings
        );
    }

    /**
     * @param list<array<string, mixed>> $pages
     * @param array<string, mixed> $context
     * @param array<string, mixed> $options
     * @param array<string, mixed> $extracted
     * @return array{text: string, images: array<string, mixed>, metadata: array<string, mixed>}
     */
    private function runSuppliedPipeline(
        array $pages,
        array $context,
        array $options,
        MarkerSettings $settings,
        array $extracted,
        float $batchMultiplier,
        int $sourcePageCount
    ): array {
        $metadata = [
            'page_range' => $extracted['page_range'],
            'pdftext' => $extracted['metadata'],
            'ocr_stats' => $this->ocrStatsOption($options),
            'block_stats' => [
                'table' => 0,
            ],
            'supplied_boundaries' => [],
        ];

        if (!$this->hasBlocks($pages)) {
            $metadata['empty_text_blocks'] = true;

            return [
                'text' => '',
                'images' => [],
                'metadata' => $metadata,
            ];
        }

        $selectedPageCount = count($pages);
        $pageRange = $extracted['page_range'];
        $selectedPageNumbers = $this->artifactSelector->pageNumbersFromPages($pages);
        $lowresImages = $this->selectSelectedPageArtifacts(
            $this->listOption($options, 'lowres_images'),
            $sourcePageCount,
            $pageRange,
            $selectedPageCount,
            $selectedPageNumbers
        );
        $layoutResults = $this->selectSelectedPageArtifacts(
            $this->listOption($options, 'layout_results'),
            $sourcePageCount,
            $pageRange,
            $selectedPageCount,
            $selectedPageNumbers
        );
        if ($layoutResults !== []) {
            $layout = $this->layoutAnnotator->runWithSuppliedLayouts(
                $lowresImages,
                $pages,
                $layoutResults,
                $batchMultiplier
            );
            $pages = $layout['pages'];
            $metadata['layout_plan'] = $layout['plan'];
            $metadata['supplied_boundaries'][] = 'layout';
        }

        $pages = $this->layoutAnnotator->annotateBlockTypes(
            $pages,
            (string) $settings->get('DEFAULT_BLOCK_TYPE')
        );

        $orderImages = $this->selectSelectedPageArtifacts(
            $this->listOption($options, 'order_images'),
            $sourcePageCount,
            $pageRange,
            $selectedPageCount,
            $selectedPageNumbers
        );
        $orderResults = $this->selectSelectedPageArtifacts(
            $this->listOption($options, 'order_results'),
            $sourcePageCount,
            $pageRange,
            $selectedPageCount,
            $selectedPageNumbers
        );
        if ($orderResults !== []) {
            $ordered = $this->layoutOrderer->runWithSuppliedOrder(
                $orderImages,
                $pages,
                $orderResults,
                $batchMultiplier
            );
            $pages = $ordered['pages'];
            $metadata['order_plan'] = $ordered['plan'];
            $metadata['supplied_boundaries'][] = 'order';
        }

        $pages = $this->layoutOrderer->sortBlocksInReadingOrder($pages);

        $pageReviewMetadata = $this->listOption($options, 'page_review_metadata');
        if ($pageReviewMetadata !== []) {
            $pages = $this->withPageReviewMetadata($pages, $pageReviewMetadata);
            $metadata['page_review_metadata_count'] = count($pageReviewMetadata);
            $metadata['supplied_boundaries'][] = 'page-review-metadata';
        }

        $markdownTables = $this->listOption($options, 'markdown_tables');
        $recognizedTables = $this->listOption($options, 'recognized_tables');
        if ($recognizedTables !== []) {
            $detectBoxes = (bool) ($options['table_detect_boxes'] ?? $options['ocr_all_pages'] ?? false);
            $tablePlan = $this->tableFormatter->getTableBoxes(
                $pages,
                $this->listOption($options, 'table_text_lines'),
                $this->listOption($options, 'table_rendered_image_sizes'),
                $this->numericOption($options, 'table_dpi', 192.0),
                $detectBoxes ? $this->tablePageIndexMap($pages) : []
            );
            $detectorCells = $this->listOption($options, 'table_detector_cells');
            $ocrTextLines = $this->listOption($options, 'table_ocr_text_lines');
            if ($detectorCells !== [] || $ocrTextLines !== [] || $detectBoxes || $this->recognizedTablesNeedCells($recognizedTables)) {
                $cells = $this->tableRecognizer->getCells(
                    $tablePlan['table_bboxes'],
                    $tablePlan['image_sizes'],
                    $tablePlan['text_lines'],
                    $detectorCells,
                    $detectBoxes
                );
                $recognizedTables = $this->tableRecognizer->recognizeTables(
                    $cells['table_cells'],
                    $cells['needs_ocr'],
                    $recognizedTables,
                    $ocrTextLines
                );
                $metadata['table_needs_ocr'] = $cells['needs_ocr'];
                $metadata['table_detect_boxes'] = $detectBoxes;
                $metadata['table_cell_counts'] = array_map(static fn (array $cells): int => count($cells), $cells['table_cells']);
                $cellBoundaryReviews = $this->tableTextCellBoundaryReviews($cells['table_text_cell_boundary_reviews'] ?? []);
                if ($cellBoundaryReviews !== []) {
                    $metadata['table_text_cell_boundary_reviews'] = $cellBoundaryReviews;
                }
                $metadata['supplied_boundaries'][] = 'table-cell-routing';
            }
            $recognition = $this->tableRecognizer->formatRecognizedTables($recognizedTables, $tablePlan['image_sizes']);
            $markdownTables = $recognition['markdown_tables'];
            $tableCropImageSizes = $this->tableCropImageSizes($tablePlan);
            $metadata['table_plan'] = $this->tablePlanMetadata($tablePlan);
            $metadata['table_assigned_cells'] = $recognition['assigned_cells'];
            $metadata['table_merged_cell_geometry'] = $this->mergedCellGeometryForTables(
                $recognition['assigned_cells'],
                $recognizedTables,
                $tableCropImageSizes
            );
            $metadata['table_spanning_grid_review'] = $this->spanningGridReviewForTables(
                $recognition['assigned_cells'],
                $recognizedTables,
                $tableCropImageSizes
            );
            $ocrGridBorderConflicts = $this->ocrGridBorderConflictsForTables(
                $recognizedTables,
                $recognition['assigned_cells'],
                $tableCropImageSizes
            );
            if ($ocrGridBorderConflicts !== []) {
                $metadata['table_ocr_grid_border_conflicts'] = $ocrGridBorderConflicts;
            }
            $metadata['supplied_boundaries'][] = 'table-recognition';
        }

        if ($markdownTables !== []) {
            $formattedTables = $this->tableFormatter->formatTables(
                $pages,
                $markdownTables,
                $this->numericOption($options, 'table_intersection_threshold', 0.7)
            );
            $pages = $formattedTables['pages'];
            $metadata['block_stats']['table'] = $formattedTables['table_count'];
            $metadata['inserted_tables'] = $formattedTables['inserted_tables'];
            $metadata['table_section_caption_review'] = $this->tableSectionCaptionReviewForTables(
                $formattedTables['table_context_reviews'] ?? [],
                $metadata['table_spanning_grid_review'] ?? []
            );
            $metadata['supplied_boundaries'][] = 'table-formatting';
        }

        $equationPredictions = $this->equationPredictionsOption($options);
        if ($equationPredictions !== []) {
            $metadata['supplied_boundaries'][] = 'equation-recognition';
        }

        $imagePayloads = $this->listOption($options, 'image_payloads');
        if ($imagePayloads !== [] && $settings->extractImages()) {
            $metadata['supplied_boundaries'][] = 'image-extraction';
        }

        $finalized = $this->finalizer->finalizePages(
            $pages,
            $this->stringListOption($options, 'bad_span_ids'),
            $settings,
            $metadata,
            $imagePayloads,
            $equationPredictions,
            $this->nullableIntOption($options, 'equation_model_max_tokens') ?? (int) $settings->get('TEXIFY_MODEL_MAX'),
            $this->numericOption($options, 'equation_intersection_threshold', (float) $settings->get('BBOX_INTERSECTION_THRESH'))
        );
        $finalized['metadata']['pipeline_stage'] = 'supplied-document';
        $finalized['metadata']['context'] = [
            'filetype' => $context['filetype'] ?? null,
            'batch_multiplier' => $context['batch_multiplier'] ?? null,
            'lowres_image_count' => $context['lowres_image_count'] ?? null,
        ];

        return [
            'text' => $finalized['text'],
            'images' => $finalized['images'],
            'metadata' => $finalized['metadata'],
        ];
    }

    /**
     * @param list<array<string, mixed>> $pages
     * @param list<mixed> $pageReviews
     * @return list<array<string, mixed>>
     */
    private function withPageReviewMetadata(array $pages, array $pageReviews): array
    {
        $byPnum = [];
        $byPageNumber = [];
        $byPageObject = [];

        foreach ($pageReviews as $review) {
            if (!is_array($review)) {
                throw new InvalidArgumentException('markerPDF supplied document option page_review_metadata must contain arrays.');
            }

            $pnum = $review['pnum'] ?? $review['page'] ?? null;
            if (is_int($pnum) || is_float($pnum) || (is_string($pnum) && preg_match('/^-?\d+$/', $pnum) === 1)) {
                $byPnum[(int) $pnum] = $review;
            }

            $pageNumber = $review['page_number'] ?? null;
            if (is_int($pageNumber) || is_float($pageNumber) || (is_string($pageNumber) && preg_match('/^-?\d+$/', $pageNumber) === 1)) {
                $byPageNumber[(int) $pageNumber - 1] = $review;
            }

            $pageObject = $review['page_object'] ?? null;
            if (is_int($pageObject) || is_float($pageObject) || (is_string($pageObject) && preg_match('/^-?\d+$/', $pageObject) === 1)) {
                $byPageObject[(int) $pageObject] = $review;
            }
        }

        foreach ($pages as $index => $page) {
            $pageObject = $page['page_object'] ?? null;
            $pnum = $page['pnum'] ?? $page['page'] ?? $index;
            $matched = null;
            if ((is_int($pageObject) || is_float($pageObject)) && isset($byPageObject[(int) $pageObject])) {
                $matched = $byPageObject[(int) $pageObject];
            } elseif ((is_int($pnum) || is_float($pnum)) && isset($byPnum[(int) $pnum])) {
                $matched = $byPnum[(int) $pnum];
            } elseif (isset($byPageNumber[$index])) {
                $matched = $byPageNumber[$index];
            }

            if ($matched !== null) {
                $page['page_review_metadata'] = $matched;
                $pages[$index] = $page;
            }
        }

        return $pages;
    }

    /**
     * Upstream deletes pages before start_page from the PDFium document before
     * rendering low-res images and running layout/order models. Native supplied
     * artifacts that still span the original pdftext page list need the same
     * range alignment before zip-style model assignment.
     *
     * @param list<mixed> $artifacts
     * @param list<int> $pageRange
     * @return list<mixed>
     */
    private function selectSelectedPageArtifacts(
        array $artifacts,
        int $sourcePageCount,
        array $pageRange,
        int $selectedPageCount,
        array $selectedPageNumbers = []
    ): array {
        return $this->artifactSelector->select($artifacts, $sourcePageCount, $pageRange, $selectedPageCount, $selectedPageNumbers);
    }

    /**
     * @param list<array<string, mixed>> $pages
     */
    private function hasBlocks(array $pages): bool
    {
        foreach ($pages as $page) {
            foreach (($page['blocks'] ?? []) as $block) {
                if (is_array($block)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $tablePlan
     * @return array<string, mixed>
     */
    private function tablePlanMetadata(array $tablePlan): array
    {
        return [
            'table_counts' => $tablePlan['table_counts'],
            'table_bboxes' => $tablePlan['table_bboxes'],
            'image_sizes' => $tablePlan['image_sizes'],
            'doc_indexes' => $tablePlan['doc_indexes'],
            'table_page_indexes' => $tablePlan['table_page_indexes'],
        ];
    }

    /**
     * @param array<string, mixed> $tablePlan
     * @return list<array{width: int, height: int}>
     */
    private function tableCropImageSizes(array $tablePlan): array
    {
        $sizes = [];
        $tableImages = isset($tablePlan['table_images']) && is_array($tablePlan['table_images'])
            ? $tablePlan['table_images']
            : [];

        foreach ($tableImages as $index => $tableImage) {
            if (is_array($tableImage)) {
                $cropWidth = $tableImage['crop_width'] ?? null;
                $cropHeight = $tableImage['crop_height'] ?? null;
                if ((is_int($cropWidth) || is_float($cropWidth)) && (is_int($cropHeight) || is_float($cropHeight)) && $cropWidth > 0 && $cropHeight > 0) {
                    $sizes[] = [
                        'width' => (int) round($cropWidth),
                        'height' => (int) round($cropHeight),
                    ];
                    continue;
                }
            }

            $fallback = $tablePlan['image_sizes'][$index] ?? null;
            if (is_array($fallback)) {
                $width = $fallback['width'] ?? $fallback[0] ?? null;
                $height = $fallback['height'] ?? $fallback[1] ?? null;
                if ((is_int($width) || is_float($width)) && (is_int($height) || is_float($height)) && $width > 0 && $height > 0) {
                    $sizes[] = [
                        'width' => (int) round($width),
                        'height' => (int) round($height),
                    ];
                }
            }
        }

        return $sizes;
    }

    /**
     * @param mixed $reviews
     * @return list<array<string, mixed>|null>
     */
    private function tableTextCellBoundaryReviews(mixed $reviews): array
    {
        if (!is_array($reviews)) {
            return [];
        }

        $normalized = [];
        $hasReview = false;
        foreach ($reviews as $review) {
            if (is_array($review)) {
                $normalized[] = $review;
                $hasReview = true;
                continue;
            }

            $normalized[] = null;
        }

        return $hasReview ? $normalized : [];
    }

    /**
     * @param list<list<array<string, mixed>>> $assignedTables
     * @param list<array<string, mixed>> $recognizedTables
     * @param list<array{width?: int|float, height?: int|float}|list<int|float>> $imageSizes
     * @return list<list<array<string, mixed>>>
     */
    private function mergedCellGeometryForTables(array $assignedTables, array $recognizedTables, array $imageSizes = []): array
    {
        $geometry = [];
        foreach ($assignedTables as $tableIndex => $assignedCells) {
            $table = $recognizedTables[$tableIndex] ?? [];
            if (!is_array($table)) {
                $geometry[] = [];
                continue;
            }

            $rows = isset($table['rows']) && is_array($table['rows']) ? $table['rows'] : [];
            $cols = isset($table['cols']) && is_array($table['cols']) ? $table['cols'] : [];
            $imageSize = isset($imageSizes[$tableIndex]) && is_array($imageSizes[$tableIndex]) ? $imageSizes[$tableIndex] : null;
            $geometry[] = $this->tableRecognizer->mergedCellGeometry($assignedCells, $rows, $cols, $imageSize);
        }

        return $geometry;
    }

    /**
     * @param list<list<array<string, mixed>>> $assignedTables
     * @param list<array<string, mixed>> $recognizedTables
     * @param list<array{width?: int|float, height?: int|float}|list<int|float>> $imageSizes
     * @return list<array<string, mixed>>
     */
    private function spanningGridReviewForTables(array $assignedTables, array $recognizedTables, array $imageSizes = []): array
    {
        $reviews = [];
        foreach ($assignedTables as $tableIndex => $assignedCells) {
            $table = $recognizedTables[$tableIndex] ?? [];
            if (!is_array($table)) {
                $reviews[] = $this->tableRecognizer->spanningGridReview([], [], []);
                continue;
            }

            $rows = isset($table['rows']) && is_array($table['rows']) ? $table['rows'] : [];
            $cols = isset($table['cols']) && is_array($table['cols']) ? $table['cols'] : [];
            $imageSize = isset($imageSizes[$tableIndex]) && is_array($imageSizes[$tableIndex]) ? $imageSizes[$tableIndex] : null;
            $reviews[] = $this->tableRecognizer->spanningGridReview($assignedCells, $rows, $cols, $imageSize);
        }

        return $reviews;
    }

    /**
     * @param list<array<string, mixed>> $recognizedTables
     * @param list<list<array<string, mixed>>> $assignedTables
     * @param list<array{width?: int|float, height?: int|float}|list<int|float>> $imageSizes
     * @return list<list<array<string, mixed>>>
     */
    private function ocrGridBorderConflictsForTables(array $recognizedTables, array $assignedTables, array $imageSizes = []): array
    {
        $conflicts = [];
        foreach ($recognizedTables as $tableIndex => $table) {
            if (!is_array($table) || !isset($table['ocr_grid_border_conflicts']) || !is_array($table['ocr_grid_border_conflicts'])) {
                $conflicts[] = [];
                continue;
            }

            $tableConflicts = array_values(array_filter(
                $table['ocr_grid_border_conflicts'],
                static fn (mixed $item): bool => is_array($item)
            ));
            $assignedCells = isset($assignedTables[$tableIndex]) && is_array($assignedTables[$tableIndex])
                ? $assignedTables[$tableIndex]
                : [];
            $rows = isset($table['rows']) && is_array($table['rows']) ? $table['rows'] : [];
            $cols = isset($table['cols']) && is_array($table['cols']) ? $table['cols'] : [];
            $imageSize = isset($imageSizes[$tableIndex]) && is_array($imageSizes[$tableIndex]) ? $imageSizes[$tableIndex] : null;
            $conflicts[] = $this->tableRecognizer->gridBorderConflictReview($tableConflicts, $assignedCells, $rows, $cols, $imageSize);
        }

        $hasConflict = false;
        foreach ($conflicts as $tableConflicts) {
            if ($tableConflicts !== []) {
                $hasConflict = true;
                break;
            }
        }

        return $hasConflict ? $conflicts : [];
    }

    /**
     * @param list<array<string, mixed>> $contextReviews
     * @param list<array<string, mixed>> $spanningGridReviews
     * @return list<array<string, mixed>>
     */
    private function tableSectionCaptionReviewForTables(array $contextReviews, array $spanningGridReviews): array
    {
        $reviews = [];
        foreach ($contextReviews as $contextReview) {
            if (!is_array($contextReview)) {
                continue;
            }

            $tableIndex = isset($contextReview['table_index']) && (is_int($contextReview['table_index']) || is_float($contextReview['table_index']))
                ? (int) $contextReview['table_index']
                : count($reviews);
            $gridReview = isset($spanningGridReviews[$tableIndex]) && is_array($spanningGridReviews[$tableIndex])
                ? $spanningGridReviews[$tableIndex]
                : [];

            $contextReview['spanning_grid'] = $this->spanningGridSummary($gridReview, $tableIndex);
            if (($contextReview['has_caption'] ?? false) === true && isset($contextReview['caption']) && is_array($contextReview['caption'])) {
                $contextReview['caption']['review_target'] = 'table_span_grid';
            }
            if (($contextReview['has_section'] ?? false) === true && isset($contextReview['section']) && is_array($contextReview['section'])) {
                $contextReview['section']['review_target'] = 'table_span_grid';
            }
            $contextReview['accessibility'] = $this->tableAccessibilityReview($contextReview, $gridReview, $tableIndex);
            if (($contextReview['caption'] ?? null) !== null && is_array($contextReview['caption']) && ($contextReview['accessibility']['caption_id'] ?? null) !== null) {
                $contextReview['caption']['caption_id'] = $contextReview['accessibility']['caption_id'];
                $contextReview['caption']['describes_table_id'] = $contextReview['accessibility']['table_id'];
            }
            if (($contextReview['section'] ?? null) !== null && is_array($contextReview['section']) && ($contextReview['accessibility']['section_id'] ?? null) !== null) {
                $contextReview['section']['section_id'] = $contextReview['accessibility']['section_id'];
                $contextReview['section']['labels_table_id'] = $contextReview['accessibility']['table_id'];
            }

            $reviews[] = $contextReview;
        }

        return $reviews;
    }

    /**
     * @param array<string, mixed> $contextReview
     * @param array<string, mixed> $gridReview
     * @return array<string, mixed>
     */
    private function tableAccessibilityReview(array $contextReview, array $gridReview, int $tableIndex): array
    {
        $tableId = 'markerpdf-table-' . $tableIndex;
        $caption = isset($contextReview['caption']) && is_array($contextReview['caption']) ? $contextReview['caption'] : [];
        $section = isset($contextReview['section']) && is_array($contextReview['section']) ? $contextReview['section'] : [];
        $captionText = trim((string) ($caption['text'] ?? ''));
        $sectionText = trim((string) ($section['text'] ?? ''));
        $captionId = $captionText === '' ? null : $tableId . '-caption';
        $sectionId = $sectionText === '' ? null : $tableId . '-section';

        $headerCells = isset($gridReview['header_cells']) && is_array($gridReview['header_cells'])
            ? array_values(array_filter($gridReview['header_cells'], static fn (mixed $cell): bool => is_array($cell)))
            : [];
        $dataCells = isset($gridReview['data_cells']) && is_array($gridReview['data_cells'])
            ? array_values(array_filter($gridReview['data_cells'], static fn (mixed $cell): bool => is_array($cell)))
            : [];
        $renderCells = isset($gridReview['render_cells']) && is_array($gridReview['render_cells'])
            ? array_values(array_filter($gridReview['render_cells'], static fn (mixed $cell): bool => is_array($cell)))
            : [];

        $cellspanHeaderGrid = $this->cellspanHeaderGridReview($gridReview, $tableIndex, $captionId, $sectionId);
        $rowspanCells = [];
        $colspanCells = [];
        foreach ($renderCells as $renderCell) {
            $rowspan = (int) ($renderCell['rowspan'] ?? 1);
            $colspan = (int) ($renderCell['colspan'] ?? 1);
            if ($rowspan <= 1 && $colspan <= 1) {
                continue;
            }

            $summary = [
                'text' => (string) ($renderCell['text'] ?? ''),
                'row_ids' => $this->integerValues($renderCell['row_ids'] ?? []),
                'col_ids' => $this->integerValues($renderCell['col_ids'] ?? []),
                'anchor' => $renderCell['anchor'] ?? null,
                'rowspan' => $rowspan,
                'colspan' => $colspan,
                'tag' => (string) ($renderCell['tag'] ?? 'td'),
                'scope' => $renderCell['scope'] ?? null,
                'header_id' => $renderCell['header_id'] ?? null,
                'headers' => $this->stringValues($renderCell['headers'] ?? []),
            ];
            if ($rowspan > 1) {
                $rowspanCells[] = $summary;
            }
            if ($colspan > 1) {
                $colspanCells[] = $summary;
            }
        }

        $dataCellHeaders = [];
        foreach ($dataCells as $dataCell) {
            $entry = [
                'render_cell_index' => (int) ($dataCell['render_cell_index'] ?? 0),
                'text' => (string) ($dataCell['text'] ?? ''),
                'row_ids' => $this->integerValues($dataCell['row_ids'] ?? []),
                'col_ids' => $this->integerValues($dataCell['col_ids'] ?? []),
                'anchor' => $dataCell['anchor'] ?? null,
                'headers' => $this->stringValues($dataCell['headers'] ?? []),
                'column_header_ids' => $this->stringValues($dataCell['column_header_ids'] ?? []),
                'row_header_ids' => $this->stringValues($dataCell['row_header_ids'] ?? []),
                'header_texts' => $this->stringValues($dataCell['header_texts'] ?? []),
                'header_text' => (string) ($dataCell['header_text'] ?? ''),
                'caption_id' => $captionId,
            ];
            foreach (['column_header_physical_axis', 'row_header_physical_axis'] as $field) {
                if (isset($dataCell[$field]) && is_scalar($dataCell[$field])) {
                    $entry[$field] = (string) $dataCell[$field];
                }
            }
            $dataCellHeaders[] = $entry;
        }

        return [
            'review_target' => 'table_span_grid_accessibility',
            'table_id' => $tableId,
            'caption_id' => $captionId,
            'section_id' => $sectionId,
            'rotated' => ($gridReview['rotated'] ?? false) === true,
            'orientation' => (string) ($gridReview['orientation'] ?? 'normal'),
            'row_axis' => (string) ($gridReview['row_axis'] ?? 'y'),
            'col_axis' => (string) ($gridReview['col_axis'] ?? 'x'),
            'caption_text' => $captionText,
            'caption_position' => $caption['position'] ?? null,
            'section_text' => $sectionText,
            'aria_describedby' => $captionId === null ? [] : [$captionId],
            'aria_labelledby' => $sectionId === null ? [] : [$sectionId],
            'header_ids' => $this->stringValues(array_map(static fn (array $cell): mixed => $cell['header_id'] ?? null, $headerCells)),
            'data_cell_headers' => $dataCellHeaders,
            'cellspan_header_grid' => $cellspanHeaderGrid,
            'rowspan_cells' => $rowspanCells,
            'colspan_cells' => $colspanCells,
            'rowspan_cell_count' => count($rowspanCells),
            'colspan_cell_count' => count($colspanCells),
            'data_cell_count' => count($dataCellHeaders),
            'accessible_caption_bound' => $captionId !== null,
        ];
    }

    /**
     * Keep the caption binding adjacent to the tabled span grid so WordPress
     * importers do not need to rebuild covered cells from Markdown anchors.
     *
     * @param array<string, mixed> $gridReview
     * @return array<string, mixed>
     */
    private function cellspanHeaderGridReview(array $gridReview, int $tableIndex, ?string $captionId, ?string $sectionId): array
    {
        $accessibilityGrid = isset($gridReview['accessibility_grid']) && is_array($gridReview['accessibility_grid'])
            ? $gridReview['accessibility_grid']
            : [];
        $renderCells = isset($gridReview['render_cells']) && is_array($gridReview['render_cells'])
            ? array_values(array_filter($gridReview['render_cells'], static fn (mixed $cell): bool => is_array($cell)))
            : [];
        $gridCells = isset($gridReview['grid_cells']) && is_array($gridReview['grid_cells'])
            ? array_values(array_filter($gridReview['grid_cells'], static fn (mixed $cell): bool => is_array($cell)))
            : [];
        $dataCells = isset($gridReview['data_cells']) && is_array($gridReview['data_cells'])
            ? array_values(array_filter($gridReview['data_cells'], static fn (mixed $cell): bool => is_array($cell)))
            : [];

        $spanCount = 0;
        $renderCellReviews = [];
        foreach ($renderCells as $renderIndex => $renderCell) {
            $rowspan = (int) ($renderCell['rowspan'] ?? 1);
            $colspan = (int) ($renderCell['colspan'] ?? 1);
            if ($rowspan > 1 || $colspan > 1) {
                $spanCount++;
            }

            $entry = [
                'render_cell_index' => $renderIndex,
                'text' => (string) ($renderCell['text'] ?? ''),
                'row_ids' => $this->integerValues($renderCell['row_ids'] ?? []),
                'col_ids' => $this->integerValues($renderCell['col_ids'] ?? []),
                'anchor' => isset($renderCell['anchor']) && is_array($renderCell['anchor']) ? $renderCell['anchor'] : null,
                'rowspan' => $rowspan,
                'colspan' => $colspan,
                'tag' => (string) ($renderCell['tag'] ?? 'td'),
                'scope' => $renderCell['scope'] ?? null,
                'header' => ($renderCell['header'] ?? false) === true,
                'header_id' => isset($renderCell['header_id']) && is_scalar($renderCell['header_id']) ? (string) $renderCell['header_id'] : null,
                'headers' => $this->stringValues($renderCell['headers'] ?? []),
                'grid_cells' => $this->gridPositionValues($renderCell['grid_cells'] ?? []),
                'caption_id' => $captionId,
            ];
            foreach (['header_role', 'header_axis', 'header_text', 'column_header_physical_axis', 'row_header_physical_axis'] as $field) {
                if (isset($renderCell[$field]) && is_scalar($renderCell[$field])) {
                    $entry[$field] = (string) $renderCell[$field];
                }
            }
            foreach (['header_axes', 'column_header_ids', 'row_header_ids', 'header_texts'] as $field) {
                if (isset($renderCell[$field])) {
                    $entry[$field] = $this->stringValues($renderCell[$field]);
                }
            }
            $renderCellReviews[] = $entry;
        }

        $gridCellReviews = [];
        foreach ($gridCells as $gridCell) {
            $entry = [
                'row_id' => (int) ($gridCell['row_id'] ?? 0),
                'col_id' => (int) ($gridCell['col_id'] ?? 0),
                'state' => (string) ($gridCell['state'] ?? 'unknown'),
                'caption_id' => $captionId,
            ];
            foreach (['render_cell_index', 'rowspan', 'colspan'] as $field) {
                if (isset($gridCell[$field]) && (is_int($gridCell[$field]) || is_float($gridCell[$field]) || is_string($gridCell[$field]))) {
                    $entry[$field] = (int) $gridCell[$field];
                }
            }
            foreach (['text', 'tag', 'scope', 'header_role', 'header_axis', 'header_id', 'header_text'] as $field) {
                if (isset($gridCell[$field]) && is_scalar($gridCell[$field])) {
                    $entry[$field] = (string) $gridCell[$field];
                }
            }
            foreach (['header_axes', 'headers', 'column_header_ids', 'row_header_ids', 'header_texts'] as $field) {
                if (isset($gridCell[$field])) {
                    $entry[$field] = $this->stringValues($gridCell[$field]);
                }
            }
            if (isset($gridCell['covered_by']) && is_array($gridCell['covered_by'])) {
                $entry['covered_by'] = [
                    'row_id' => (int) ($gridCell['covered_by']['row_id'] ?? 0),
                    'col_id' => (int) ($gridCell['covered_by']['col_id'] ?? 0),
                    'render_cell_index' => (int) ($gridCell['covered_by']['render_cell_index'] ?? 0),
                ];
            }
            $gridCellReviews[] = $entry;
        }

        $dataCellHeaders = [];
        foreach ($dataCells as $dataCell) {
            $entry = [
                'render_cell_index' => (int) ($dataCell['render_cell_index'] ?? 0),
                'text' => (string) ($dataCell['text'] ?? ''),
                'row_ids' => $this->integerValues($dataCell['row_ids'] ?? []),
                'col_ids' => $this->integerValues($dataCell['col_ids'] ?? []),
                'anchor' => isset($dataCell['anchor']) && is_array($dataCell['anchor']) ? $dataCell['anchor'] : null,
                'headers' => $this->stringValues($dataCell['headers'] ?? []),
                'column_header_ids' => $this->stringValues($dataCell['column_header_ids'] ?? []),
                'row_header_ids' => $this->stringValues($dataCell['row_header_ids'] ?? []),
                'header_texts' => $this->stringValues($dataCell['header_texts'] ?? []),
                'header_text' => (string) ($dataCell['header_text'] ?? ''),
                'caption_id' => $captionId,
            ];
            foreach (['column_header_physical_axis', 'row_header_physical_axis'] as $field) {
                if (isset($dataCell[$field]) && is_scalar($dataCell[$field])) {
                    $entry[$field] = (string) $dataCell[$field];
                }
            }
            $dataCellHeaders[] = $entry;
        }

        return [
            'review_target' => 'table_ocr_header_grid_caption_cellspan',
            'table_index' => $tableIndex,
            'caption_id' => $captionId,
            'section_id' => $sectionId,
            'caption_bound' => $captionId !== null,
            'rows' => $this->integerValues($gridReview['rows'] ?? []),
            'cols' => $this->integerValues($gridReview['cols'] ?? []),
            'rotated' => ($gridReview['rotated'] ?? false) === true,
            'orientation' => (string) ($gridReview['orientation'] ?? 'normal'),
            'row_axis' => (string) ($gridReview['row_axis'] ?? 'y'),
            'col_axis' => (string) ($gridReview['col_axis'] ?? 'x'),
            'header_ids' => $this->stringValues($accessibilityGrid['header_ids'] ?? []),
            'column_header_grid' => isset($accessibilityGrid['column_header_grid']) && is_array($accessibilityGrid['column_header_grid'])
                ? $accessibilityGrid['column_header_grid']
                : [],
            'row_header_grid' => isset($accessibilityGrid['row_header_grid']) && is_array($accessibilityGrid['row_header_grid'])
                ? $accessibilityGrid['row_header_grid']
                : [],
            'render_cells' => $renderCellReviews,
            'grid_cells' => $gridCellReviews,
            'data_cell_headers' => $dataCellHeaders,
            'cellspan_count' => $spanCount,
            'has_rowspan' => count(array_filter($renderCellReviews, static fn (array $cell): bool => (int) ($cell['rowspan'] ?? 1) > 1)) > 0,
            'has_colspan' => count(array_filter($renderCellReviews, static fn (array $cell): bool => (int) ($cell['colspan'] ?? 1) > 1)) > 0,
        ];
    }

    /**
     * @return list<array{row_id: int, col_id: int}>
     */
    private function gridPositionValues(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $out = [];
        foreach ($values as $value) {
            if (!is_array($value)) {
                continue;
            }
            $out[] = [
                'row_id' => (int) ($value['row_id'] ?? 0),
                'col_id' => (int) ($value['col_id'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return list<int>
     */
    private function integerValues(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $out = [];
        foreach ($values as $value) {
            if (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1)) {
                $out[] = (int) $value;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function stringValues(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $out = [];
        foreach ($values as $value) {
            if (is_scalar($value) && (string) $value !== '') {
                $out[] = (string) $value;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param array<string, mixed> $gridReview
     * @return array<string, mixed>
     */
    private function spanningGridSummary(array $gridReview, int $tableIndex): array
    {
        $renderCells = isset($gridReview['render_cells']) && is_array($gridReview['render_cells']) ? $gridReview['render_cells'] : [];
        $headerCells = isset($gridReview['header_cells']) && is_array($gridReview['header_cells']) ? $gridReview['header_cells'] : [];
        $dataCells = isset($gridReview['data_cells']) && is_array($gridReview['data_cells']) ? $gridReview['data_cells'] : [];

        $hasRowspan = false;
        $hasColspan = false;
        foreach ($renderCells as $renderCell) {
            if (!is_array($renderCell)) {
                continue;
            }
            if ((int) ($renderCell['rowspan'] ?? 1) > 1) {
                $hasRowspan = true;
            }
            if ((int) ($renderCell['colspan'] ?? 1) > 1) {
                $hasColspan = true;
            }
        }

        return [
            'table_index' => $tableIndex,
            'rows' => isset($gridReview['rows']) && is_array($gridReview['rows']) ? array_values($gridReview['rows']) : [],
            'cols' => isset($gridReview['cols']) && is_array($gridReview['cols']) ? array_values($gridReview['cols']) : [],
            'rotated' => ($gridReview['rotated'] ?? false) === true,
            'orientation' => (string) ($gridReview['orientation'] ?? 'normal'),
            'row_axis' => (string) ($gridReview['row_axis'] ?? 'y'),
            'col_axis' => (string) ($gridReview['col_axis'] ?? 'x'),
            'render_cell_count' => count($renderCells),
            'header_cell_count' => count($headerCells),
            'data_cell_count' => count($dataCells),
            'header_ids' => array_values(array_filter(
                array_map(static fn (mixed $cell): ?string => is_array($cell) && isset($cell['header_id']) ? (string) $cell['header_id'] : null, $headerCells),
                static fn (?string $headerId): bool => $headerId !== null
            )),
            'has_rowspan' => $hasRowspan,
            'has_colspan' => $hasColspan,
            'review_target' => 'table_span_grid',
        ];
    }

    /**
     * @param list<array<string, mixed>> $pages
     * @return array<int, bool>
     */
    private function tablePageIndexMap(array $pages): array
    {
        $indexes = [];
        foreach ($pages as $index => $_page) {
            $indexes[$index] = true;
        }

        return $indexes;
    }

    /**
     * @param list<array<string, mixed>> $recognizedTables
     */
    private function recognizedTablesNeedCells(array $recognizedTables): bool
    {
        foreach ($recognizedTables as $table) {
            if (!is_array($table) || !isset($table['cells']) || !is_array($table['cells']) || count($table['cells']) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $options
     * @return list<mixed>
     */
    private function listOption(array $options, string $key): array
    {
        if (!array_key_exists($key, $options) || $options[$key] === null) {
            return [];
        }
        if (!is_array($options[$key]) || !array_is_list($options[$key])) {
            throw new InvalidArgumentException("markerPDF supplied document option {$key} must be a list.");
        }

        return $options[$key];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function arrayOption(array $options, string $key): array
    {
        if (!array_key_exists($key, $options) || $options[$key] === null) {
            return [];
        }
        if (!is_array($options[$key])) {
            throw new InvalidArgumentException("markerPDF supplied document option {$key} must be an array.");
        }

        return $options[$key];
    }

    /**
     * @param array<string, mixed> $options
     * @return array{ocr_pages: int, ocr_failed: int, ocr_success: int, ocr_engine: string}
     */
    private function ocrStatsOption(array $options): array
    {
        if (!array_key_exists('ocr_stats', $options) || $options['ocr_stats'] === null) {
            return [
                'ocr_pages' => 0,
                'ocr_failed' => 0,
                'ocr_success' => 0,
                'ocr_engine' => 'none',
            ];
        }
        if (!is_array($options['ocr_stats'])) {
            throw new InvalidArgumentException('markerPDF supplied document option ocr_stats must be an array.');
        }

        $stats = $options['ocr_stats'];

        return [
            'ocr_pages' => (int) ($stats['ocr_pages'] ?? 0),
            'ocr_failed' => (int) ($stats['ocr_failed'] ?? 0),
            'ocr_success' => (int) ($stats['ocr_success'] ?? 0),
            'ocr_engine' => (string) ($stats['ocr_engine'] ?? 'none'),
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return list<string>
     */
    private function stringListOption(array $options, string $key): array
    {
        if (!array_key_exists($key, $options) || $options[$key] === null) {
            return [];
        }
        if (!is_array($options[$key]) || !array_is_list($options[$key])) {
            throw new InvalidArgumentException("markerPDF supplied document option {$key} must be a list.");
        }

        return array_map(static fn (mixed $value): string => (string) $value, $options[$key]);
    }

    /**
     * @param array<string, mixed> $options
     * @return list<string>
     */
    private function equationPredictionsOption(array $options): array
    {
        $key = null;
        if (array_key_exists('equation_results', $options) && $options['equation_results'] !== null) {
            $key = 'equation_results';
        } elseif (array_key_exists('equation_predictions', $options) && $options['equation_predictions'] !== null) {
            $key = 'equation_predictions';
        }

        if ($key === null) {
            return [];
        }

        $items = $this->listOption($options, $key);
        $predictions = [];
        foreach ($items as $item) {
            if (is_scalar($item)) {
                $predictions[] = (string) $item;
                continue;
            }

            if (!is_array($item)) {
                throw new InvalidArgumentException("markerPDF supplied document option {$key} must contain strings or arrays.");
            }

            $value = null;
            foreach (['latex', 'prediction', 'text'] as $field) {
                if (array_key_exists($field, $item) && is_scalar($item[$field])) {
                    $value = (string) $item[$field];
                    break;
                }
            }

            if ($value === null) {
                throw new InvalidArgumentException("markerPDF supplied document option {$key} arrays must include latex, prediction, or text.");
            }

            $predictions[] = $value;
        }

        return $predictions;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function nullableIntOption(array $options, string $key): ?int
    {
        if (!array_key_exists($key, $options) || $options[$key] === null || $options[$key] === '') {
            return null;
        }
        if (is_int($options[$key])) {
            return $options[$key];
        }
        if (is_string($options[$key]) && preg_match('/^-?\d+$/', $options[$key]) === 1) {
            return (int) $options[$key];
        }

        throw new InvalidArgumentException("markerPDF supplied document option {$key} must be an integer.");
    }

    /**
     * @param array<string, mixed> $options
     */
    private function numericOption(array $options, string $key, float $default): float
    {
        if (!array_key_exists($key, $options) || $options[$key] === null || $options[$key] === '') {
            return $default;
        }
        if (is_int($options[$key]) || is_float($options[$key]) || (is_string($options[$key]) && is_numeric($options[$key]))) {
            return (float) $options[$key];
        }

        throw new InvalidArgumentException("markerPDF supplied document option {$key} must be numeric.");
    }
}
