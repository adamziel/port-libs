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
     * @param list<array<string, mixed>>|array{pages?: array<mixed>, metadata?: array<string, mixed>} $pdftextPages
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
     *     recognized_tables?: list<array<string, mixed>>|array<string, list<array<string, mixed>>>,
     *     table_text_lines?: list<mixed>,
     *     table_rendered_image_sizes?: array<int, array{width?: int|float, height?: int|float}|list<int|float>>,
     *     table_dpi?: int|float,
     *     table_intersection_threshold?: int|float,
     *     page_review_metadata?: list<array<string, mixed>>,
     *     tagged_tables?: list<array<string, mixed>>,
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
        $options = $this->withSelectedRecognizedTablesOption($options, $filename);
        $langs = array_key_exists('langs', $options) && $options['langs'] !== null
            ? $this->stringListOption($options, 'langs')
            : null;
        $batchMultiplier = $this->numericOption($options, 'batch_multiplier', 1.0);
        $ocrAllPages = (bool) ($options['ocr_all_pages'] ?? false);
        $requestedDocumentPageCount = $this->nullableIntOption($options, 'document_page_count');

        $extracted = $this->textExtractor->getTextBlocks(
            $pdftextPages,
            maxPages: $maxPages,
            startPage: $startPage,
            toc: $toc
        );
        $sourcePageCount = (int) ($extracted['metadata']['source_pages'] ?? count($pdftextPages));
        $documentPageCount = $requestedDocumentPageCount ?? $sourcePageCount;

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

        $selectedPageCount = count($pages);
        $pageRange = $extracted['page_range'];
        $selectedPageNumbers = $this->artifactSelector->pageNumbersFromPages($pages);
        $lowresImages = $this->selectSelectedPageArtifacts(
            $this->pageArtifactOption($options, 'lowres_images'),
            $sourcePageCount,
            $pageRange,
            $selectedPageCount,
            $selectedPageNumbers
        );

        if (!$this->hasBlocks($pages)) {
            $metadata['empty_text_blocks'] = true;
            $metadata['ocr_required'] = $selectedPageCount > 0;
            $metadata['image_only_pdf_handoff'] = $this->imageOnlyOcrHandoff(
                $pages,
                $context,
                $pageRange,
                $lowresImages,
                $sourcePageCount,
                $settings
            );
            $metadata['ocr_required_reasons'] = $metadata['image_only_pdf_handoff']['ocr_required_reasons'];
            $metadata['supplied_boundaries'][] = 'image-only-ocr-handoff';

            return [
                'text' => '',
                'images' => [],
                'metadata' => $metadata,
            ];
        }

        $layoutResults = $this->selectSelectedPageArtifacts(
            $this->pageArtifactOption($options, 'layout_results'),
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
                $batchMultiplier,
                $pageRange
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
            $this->pageArtifactOption($options, 'order_images'),
            $sourcePageCount,
            $pageRange,
            $selectedPageCount,
            $selectedPageNumbers
        );
        $orderResults = $this->selectSelectedPageArtifacts(
            $this->pageArtifactOption($options, 'order_results'),
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
                $batchMultiplier,
                $pageRange
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

        $taggedTables = $this->taggedTablesOption($options);
        if ($taggedTables !== []) {
            $taggedTableInsertion = $this->insertTaggedTableBlocks($pages, $taggedTables);
            $pages = $taggedTableInsertion['pages'];
            $metadata['tagged_tables'] = $taggedTableInsertion['metadata'];
            $metadata['block_stats']['table'] += $taggedTableInsertion['inserted_tables'];
            $metadata['supplied_boundaries'][] = 'tagged-table-structure';
        }

        if (isset($options['_table_result_envelope_review']) && is_array($options['_table_result_envelope_review'])) {
            $metadata['table_result_envelope_review'] = $options['_table_result_envelope_review'];
            $metadata['supplied_boundaries'][] = 'table-result-envelope';
        }

        $markdownTables = $this->listOption($options, 'markdown_tables');
        $recognizedTables = $this->listOption($options, 'recognized_tables');
        $recognizedTablePageResultBoundary = $this->flattenRecognizedTablePageResults($recognizedTables);
        $recognizedTables = $recognizedTablePageResultBoundary['tables'];
        if ($recognizedTablePageResultBoundary['reviews'] !== []) {
            $metadata['table_page_result_boundary_reviews'] = $recognizedTablePageResultBoundary['reviews'];
        }
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
                $detectorCellBoundaryReviews = $this->tableDetectorCellBoundaryReviews($cells['table_detector_cell_boundary_reviews'] ?? []);
                if ($detectorCellBoundaryReviews !== []) {
                    $metadata['table_detector_cell_boundary_reviews'] = $detectorCellBoundaryReviews;
                }
                $metadata['supplied_boundaries'][] = 'table-cell-routing';
            }
            $tableCropImageSizes = $this->tableCropImageSizes($tablePlan);
            $recognition = $this->tableRecognizer->formatRecognizedTables($recognizedTables, $tableCropImageSizes);
            $recognizedTables = $recognition['recognized_tables'];
            $coordinateSpaceReviews = $this->tableCoordinateSpaceReviews($recognition['coordinate_space_reviews'] ?? []);
            if ($coordinateSpaceReviews !== []) {
                $metadata['table_coordinate_space_reviews'] = $coordinateSpaceReviews;
            }
            $assignedSourceBoundaryReviews = $this->tableAssignedSourceBoundaryReviews($recognition['assigned_source_boundary_reviews'] ?? []);
            if ($assignedSourceBoundaryReviews !== []) {
                $metadata['table_assigned_source_boundary_reviews'] = $assignedSourceBoundaryReviews;
            }
            $assignedCropBoundaryReviews = $this->tableAssignedCropBoundaryReviews($recognition['assigned_crop_boundary_reviews'] ?? []);
            if ($assignedCropBoundaryReviews !== []) {
                $metadata['table_assigned_crop_boundary_reviews'] = $assignedCropBoundaryReviews;
            }
            $assignedBandBoundaryReviews = $this->tableAssignedBandBoundaryReviews($recognition['assigned_band_boundary_reviews'] ?? []);
            if ($assignedBandBoundaryReviews !== []) {
                $metadata['table_assigned_band_boundary_reviews'] = $assignedBandBoundaryReviews;
            }
            $markdownTables = $recognition['markdown_tables'];
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

        $equationRecognition = $this->equationRecognitionOption($options, $settings, $batchMultiplier);
        $equationPredictions = $equationRecognition['predictions'];
        if ($equationPredictions !== []) {
            $metadata['supplied_boundaries'][] = 'equation-recognition';
        }
        if ($equationRecognition['review'] !== null) {
            $metadata['equation_result_boundary_review'] = $equationRecognition['review'];
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
     * Upstream tabled.extract.extract_tables returns page-level
     * ExtractPageResult envelopes before extract.py serializes them as
     * per-table records. Native supplied artifacts can arrive at either
     * boundary, so normalize the page envelope without changing flat tables.
     *
     * @param list<array<string, mixed>> $recognizedTables
     * @return array{tables: list<array<string, mixed>>, reviews: list<array<string, mixed>>}
     */
    private function flattenRecognizedTablePageResults(array $recognizedTables): array
    {
        $tables = [];
        $reviews = [];

        foreach ($recognizedTables as $pageResultIndex => $tableOrPageResult) {
            if (!$this->isRecognizedTablePageResult($tableOrPageResult)) {
                $tables[] = $tableOrPageResult;
                continue;
            }

            $cellsSource = $this->pageResultCellsSource($tableOrPageResult);
            $cellsByTable = $cellsSource !== null && isset($tableOrPageResult[$cellsSource]) && is_array($tableOrPageResult[$cellsSource])
                ? array_values($tableOrPageResult[$cellsSource])
                : [];
            $rowsColsByTable = isset($tableOrPageResult['rows_cols']) && is_array($tableOrPageResult['rows_cols'])
                ? array_values($tableOrPageResult['rows_cols'])
                : [];
            $tableBboxesSource = $this->pageResultTableBboxesSource($tableOrPageResult);
            $tableBboxes = $tableBboxesSource !== null && isset($tableOrPageResult[$tableBboxesSource]) && is_array($tableOrPageResult[$tableBboxesSource])
                ? array_values($tableOrPageResult[$tableBboxesSource])
                : [];
            $imageBboxes = isset($tableOrPageResult['image_bboxes']) && is_array($tableOrPageResult['image_bboxes'])
                ? array_values($tableOrPageResult['image_bboxes'])
                : [];
            $tableImages = $this->pageResultTableImages($tableOrPageResult);
            $tableImagesSource = $this->pageResultTableImagesSource($tableOrPageResult);
            $sharedImageBbox = $this->pageResultSharedImageBboxValue($tableOrPageResult);
            $sharedImageBboxSource = $this->pageResultSharedImageBboxSource($tableOrPageResult);

            $firstFlattenedIndex = count($tables);
            $tableCount = count($cellsByTable);
            $surplusRowsColsCount = max(0, count($rowsColsByTable) - $tableCount);
            $surplusTableBboxCount = max(0, count($tableBboxes) - $tableCount);
            $surplusImageBboxCount = max(0, count($imageBboxes) - $tableCount);
            $surplusTableImageCount = max(0, count($tableImages) - $tableCount);
            $ghostTableRecordsSuppressed = $surplusRowsColsCount > 0
                || $surplusTableBboxCount > 0
                || $surplusImageBboxCount > 0
                || $surplusTableImageCount > 0;
            $rowAliases = [];
            $colAliases = [];
            for ($tableIndex = 0; $tableIndex < $tableCount; $tableIndex++) {
                $rowsCols = isset($rowsColsByTable[$tableIndex]) && is_array($rowsColsByTable[$tableIndex])
                    ? $rowsColsByTable[$tableIndex]
                    : [];
                $rowRecords = $this->pageResultRowsColsRecords($rowsCols, 'rows');
                $colRecords = $this->pageResultRowsColsRecords($rowsCols, 'cols');
                $table = [
                    'cells' => $this->pageResultRecordList($cellsByTable[$tableIndex] ?? []),
                    'rows' => $this->pageResultRecordList($rowRecords['records'] ?? []),
                    'cols' => $this->pageResultRecordList($colRecords['records'] ?? []),
                ];
                if ($rowRecords !== null) {
                    $table['rows_source_alias'] = 'rows_cols.' . $rowRecords['alias'];
                    $rowAliases[] = $rowRecords['alias'];
                    $table = $this->withPageResultRowsColsGeometryMetadata($table, $rowsCols, 'rows');
                }
                if ($colRecords !== null) {
                    $table['cols_source_alias'] = 'rows_cols.' . $colRecords['alias'];
                    $colAliases[] = $colRecords['alias'];
                    $table = $this->withPageResultRowsColsGeometryMetadata($table, $rowsCols, 'cols');
                }

                $tableBboxEntry = $tableBboxes[$tableIndex] ?? null;
                $bbox = $this->pageResultBboxValue($tableBboxEntry);
                if ($bbox !== null) {
                    if ($tableBboxesSource !== null && $tableBboxesSource !== 'bboxes') {
                        $table['table_bbox'] = $this->pageResultTableBboxAliasValue($tableBboxEntry) ?? $bbox;
                        $table['table_bbox_source'] = 'ExtractPageResult.' . $tableBboxesSource;
                    } else {
                        $table['bbox'] = $bbox;
                    }
                    $table = $this->withPageResultBboxEntryMetadata($table, $tableBboxEntry, $tableBboxesSource ?? 'bboxes');
                }

                $imageBbox = $this->pageResultImageBboxValue($imageBboxes[$tableIndex] ?? null, $tableOrPageResult, 'image_bboxes');
                if ($imageBbox === null) {
                    $imageBbox = $sharedImageBbox;
                }
                if ($imageBbox !== null) {
                    $table['image_bbox'] = $imageBbox;
                }

                $tableImage = $tableImages[$tableIndex] ?? null;
                if (is_array($tableImage) && $tableImagesSource !== null) {
                    $table = $this->withPageResultTableImageMetadata($table, $tableImage, $tableIndex, $tableImagesSource);
                }

                $table = $this->withPageResultGeometryMetadata($table, $tableOrPageResult);
                if ($cellsSource === 'table_cells') {
                    $table = $this->withPageResultTableCellsGeometryMetadata($table, $tableOrPageResult);
                }

                if (isset($tableOrPageResult['pnum']) && (is_int($tableOrPageResult['pnum']) || is_float($tableOrPageResult['pnum']) || is_string($tableOrPageResult['pnum']))) {
                    $table['pnum'] = is_numeric($tableOrPageResult['pnum'])
                        ? (int) $tableOrPageResult['pnum']
                        : $tableOrPageResult['pnum'];
                }
                $table['tnum'] = $tableIndex;

                $tables[] = $table;
            }

            $reviews[] = [
                'review_target' => 'table_page_result_boundary',
                'upstream_boundary' => 'tabled.schema.ExtractPageResult',
                'page_result_index' => $pageResultIndex,
                'table_count' => $tableCount,
                'flattened_table_indexes' => $tableCount > 0
                    ? range($firstFlattenedIndex, $firstFlattenedIndex + $tableCount - 1)
                    : [],
                'cells_source' => $cellsSource,
                'cells_source_alias' => $cellsSource === 'cells' ? null : $cellsSource,
                'cells_table_count' => count($cellsByTable),
                'rows_cols_table_count' => count($rowsColsByTable),
                'table_bbox_count' => count($tableBboxes),
                'table_bbox_source' => $tableBboxesSource,
                'image_bbox_count' => count($imageBboxes),
                'table_image_count' => count($tableImages),
                'table_image_source' => $tableImagesSource,
                'authoritative_table_count_source' => $cellsSource === null ? null : 'ExtractPageResult.' . $cellsSource,
                'surplus_rows_cols_count' => $surplusRowsColsCount,
                'surplus_table_bbox_count' => $surplusTableBboxCount,
                'surplus_image_bbox_count' => $surplusImageBboxCount,
                'surplus_table_image_count' => $surplusTableImageCount,
                'ghost_table_records_suppressed' => $ghostTableRecordsSuppressed,
                'rows_cols_row_aliases' => $rowAliases,
                'rows_cols_col_aliases' => $colAliases,
                'shared_image_bbox_source' => $sharedImageBboxSource,
                'pnum' => isset($tableOrPageResult['pnum']) && (is_int($tableOrPageResult['pnum']) || is_float($tableOrPageResult['pnum']) || (is_string($tableOrPageResult['pnum']) && is_numeric($tableOrPageResult['pnum'])))
                    ? (int) $tableOrPageResult['pnum']
                    : ($tableOrPageResult['pnum'] ?? null),
            ];
        }

        return [
            'tables' => $tables,
            'reviews' => $reviews,
        ];
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function isRecognizedTablePageResult(array $entry): bool
    {
        $cellsSource = $this->pageResultCellsSource($entry);
        if ($cellsSource === null) {
            return false;
        }

        if (!isset($entry['rows_cols']) || !is_array($entry['rows_cols']) || !array_is_list($entry['rows_cols'])) {
            return false;
        }

        foreach ($entry[$cellsSource] as $tableCells) {
            if (is_array($tableCells) && array_is_list($tableCells)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $pageResult
     */
    private function pageResultCellsSource(array $pageResult): ?string
    {
        foreach (['cells', 'table_cells'] as $source) {
            if (!isset($pageResult[$source]) || !is_array($pageResult[$source]) || !array_is_list($pageResult[$source])) {
                continue;
            }

            foreach ($pageResult[$source] as $tableCells) {
                if (is_array($tableCells) && array_is_list($tableCells)) {
                    return $source;
                }
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pageResultRecordList(mixed $records): array
    {
        if (!is_array($records)) {
            return [];
        }

        return array_values(array_filter(
            $records,
            static fn (mixed $record): bool => is_array($record)
        ));
    }

    /**
     * @param array<string|int, mixed> $rowsCols
     * @return array{alias: string, records: array<int|string, mixed>}|null
     */
    private function pageResultRowsColsRecords(array $rowsCols, string $field): ?array
    {
        foreach ($this->pageResultRowsColsAliases($field) as $alias) {
            if (!isset($rowsCols[$alias]) || !is_array($rowsCols[$alias])) {
                continue;
            }

            return [
                'alias' => $alias,
                'records' => $rowsCols[$alias],
            ];
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function pageResultRowsColsAliases(string $field): array
    {
        if ($field === 'rows') {
            return ['rows', 'row_bboxes', 'row_boxes', 'row_bounds', 'row', 'row_bbox', 'row_box', 'row_bound'];
        }

        return ['cols', 'columns', 'column_bboxes', 'col_bboxes', 'column_boxes', 'col_boxes', 'column', 'col', 'column_bbox', 'col_bbox', 'column_box', 'col_box'];
    }

    /**
     * @param array<string, mixed> $table
     * @param array<string|int, mixed> $rowsCols
     * @return array<string, mixed>
     */
    private function withPageResultRowsColsGeometryMetadata(array $table, array $rowsCols, string $field): array
    {
        $space = $this->pageResultRowsColsCoordinateSpace($rowsCols, $field);
        if ($space !== null) {
            $table[$field . '_coordinate_space'] = $space;
        }

        $order = $this->pageResultRowsColsCoordinateOrder($rowsCols, $field);
        if ($order !== null) {
            $table[$field . '_bbox_order'] = $order;
        }

        $format = $this->pageResultRowsColsCoordinateFormat($rowsCols, $field);
        if ($format !== null) {
            $table[$field . '_bbox_format'] = $format;
        }

        return $table;
    }

    /**
     * @param array<string|int, mixed> $rowsCols
     */
    private function pageResultRowsColsCoordinateSpace(array $rowsCols, string $field): ?string
    {
        foreach ($this->pageResultRowsColsCoordinateSpaceKeys($field) as $key) {
            if (isset($rowsCols[$key]) && is_scalar($rowsCols[$key])) {
                return (string) $rowsCols[$key];
            }
        }

        foreach (['coordinate_space', 'geometry_coordinate_space', 'bbox_coordinate_space', 'geometry_space'] as $key) {
            if (isset($rowsCols[$key]) && is_scalar($rowsCols[$key])) {
                return (string) $rowsCols[$key];
            }
        }

        return null;
    }

    /**
     * @param array<string|int, mixed> $rowsCols
     */
    private function pageResultRowsColsCoordinateOrder(array $rowsCols, string $field): ?string
    {
        foreach ($this->pageResultRowsColsCoordinateOrderKeys($field) as $key) {
            if (isset($rowsCols[$key]) && is_scalar($rowsCols[$key])) {
                return (string) $rowsCols[$key];
            }
        }

        foreach (['bbox_order', 'bbox_coordinate_order', 'bbox_coordinate_format', 'bbox_format', 'coordinate_order'] as $key) {
            if (isset($rowsCols[$key]) && is_scalar($rowsCols[$key])) {
                return (string) $rowsCols[$key];
            }
        }

        return null;
    }

    /**
     * @param array<string|int, mixed> $rowsCols
     */
    private function pageResultRowsColsCoordinateFormat(array $rowsCols, string $field): ?string
    {
        foreach ($this->pageResultRowsColsCoordinateFormatKeys($field) as $key) {
            if (isset($rowsCols[$key]) && is_scalar($rowsCols[$key])) {
                return (string) $rowsCols[$key];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function pageResultRowsColsCoordinateSpaceKeys(string $field): array
    {
        if ($field === 'rows') {
            return [
                'row_bboxes_coordinate_space',
                'row_bbox_coordinate_space',
                'row_boxes_coordinate_space',
                'row_box_coordinate_space',
                'row_bounds_coordinate_space',
                'row_bound_coordinate_space',
                'rows_coordinate_space',
                'row_coordinate_space',
                'row_bboxes_geometry_space',
                'row_bbox_geometry_space',
                'row_boxes_geometry_space',
                'row_box_geometry_space',
                'row_bounds_geometry_space',
                'row_bound_geometry_space',
                'rows_geometry_space',
                'row_geometry_space',
            ];
        }

        return [
            'columns_coordinate_space',
            'column_coordinate_space',
            'cols_coordinate_space',
            'col_coordinate_space',
            'column_bboxes_coordinate_space',
            'column_bbox_coordinate_space',
            'col_bboxes_coordinate_space',
            'col_bbox_coordinate_space',
            'column_boxes_coordinate_space',
            'column_box_coordinate_space',
            'col_boxes_coordinate_space',
            'col_box_coordinate_space',
            'columns_geometry_space',
            'column_geometry_space',
            'cols_geometry_space',
            'col_geometry_space',
            'column_bboxes_geometry_space',
            'column_bbox_geometry_space',
            'col_bboxes_geometry_space',
            'col_bbox_geometry_space',
            'column_boxes_geometry_space',
            'column_box_geometry_space',
            'col_boxes_geometry_space',
            'col_box_geometry_space',
        ];
    }

    /**
     * @return list<string>
     */
    private function pageResultRowsColsCoordinateOrderKeys(string $field): array
    {
        if ($field === 'rows') {
            return [
                'row_bboxes_order',
                'row_bbox_order',
                'row_bboxes_bbox_order',
                'row_boxes_order',
                'row_boxes_bbox_order',
                'row_bounds_order',
                'row_bounds_bbox_order',
                'rows_bbox_order',
                'row_bbox_order',
                'rows_coordinate_order',
                'row_coordinate_order',
            ];
        }

        return [
            'columns_order',
            'column_order',
            'cols_order',
            'col_order',
            'column_bboxes_order',
            'column_bboxes_bbox_order',
            'col_bboxes_order',
            'col_bboxes_bbox_order',
            'column_boxes_order',
            'column_boxes_bbox_order',
            'col_boxes_order',
            'col_boxes_bbox_order',
            'columns_bbox_order',
            'column_bbox_order',
            'cols_bbox_order',
            'col_bbox_order',
            'columns_coordinate_order',
            'column_coordinate_order',
            'cols_coordinate_order',
            'col_coordinate_order',
        ];
    }

    /**
     * @return list<string>
     */
    private function pageResultRowsColsCoordinateFormatKeys(string $field): array
    {
        if ($field === 'rows') {
            return [
                'row_bboxes_bbox_format',
                'row_bboxes_coordinate_format',
                'row_boxes_bbox_format',
                'row_boxes_coordinate_format',
                'row_bounds_bbox_format',
                'row_bounds_coordinate_format',
                'rows_bbox_format',
                'row_bbox_format',
            ];
        }

        return [
            'column_bboxes_bbox_format',
            'column_bboxes_coordinate_format',
            'col_bboxes_bbox_format',
            'col_bboxes_coordinate_format',
            'column_boxes_bbox_format',
            'column_boxes_coordinate_format',
            'col_boxes_bbox_format',
            'col_boxes_coordinate_format',
            'columns_bbox_format',
            'column_bbox_format',
            'cols_bbox_format',
            'col_bbox_format',
        ];
    }

    /**
     * @param array<string, mixed> $pageResult
     */
    private function pageResultTableBboxesSource(array $pageResult): ?string
    {
        foreach (['bboxes', 'table_bboxes', 'table_boxes', 'table_bounds', 'table_regions'] as $key) {
            if (isset($pageResult[$key]) && is_array($pageResult[$key])) {
                return $key;
            }
        }

        return null;
    }

    private function pageResultBboxValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return null;
        }

        if (isset($value['bbox']) && is_array($value['bbox'])) {
            return $value['bbox'];
        }

        if (isset($value['box']) && is_array($value['box'])) {
            return $value['box'];
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $table
     * @return array<string, mixed>
     */
    private function withPageResultBboxEntryMetadata(array $table, mixed $bboxEntry, string $sourceKey = 'bboxes'): array
    {
        if (!is_array($bboxEntry) || array_is_list($bboxEntry) || !$this->pageResultBboxEntryHasTableMetadata($bboxEntry)) {
            return $table;
        }

        $orderedBbox = $this->pageResultOrderedBboxValue($bboxEntry);
        if ($orderedBbox !== null) {
            $table['table_bbox'] = $orderedBbox;
            $table['table_bbox_source'] = 'ExtractPageResult.' . $sourceKey;
        }

        foreach (['table_bbox_coordinate_space', 'bbox_coordinate_space', 'geometry_coordinate_space', 'coordinate_space', 'geometry_space'] as $key) {
            if (array_key_exists('table_bbox_coordinate_space', $table) || !isset($bboxEntry[$key]) || !is_scalar($bboxEntry[$key])) {
                continue;
            }

            $table['table_bbox_coordinate_space'] = (string) $bboxEntry[$key];
            break;
        }

        foreach (['bbox_order', 'bbox_coordinate_order', 'bbox_coordinate_format', 'bbox_format', 'coordinate_order', 'table_bbox_order', 'table_bbox_coordinate_order'] as $key) {
            if (array_key_exists('bbox_order', $table) || !isset($bboxEntry[$key]) || !is_scalar($bboxEntry[$key])) {
                continue;
            }

            $table['bbox_order'] = (string) $bboxEntry[$key];
            break;
        }

        return $table;
    }

    /**
     * @return list<float>|array<mixed>|null
     */
    private function pageResultTableBboxAliasValue(mixed $bboxEntry): mixed
    {
        if (!is_array($bboxEntry) || array_is_list($bboxEntry)) {
            return $this->pageResultBboxValue($bboxEntry);
        }

        $ordered = $this->pageResultOrderedBboxValue($bboxEntry);
        if ($ordered !== null) {
            return $ordered;
        }

        return $this->pageResultBboxValue($bboxEntry);
    }

    /**
     * @param array<string|int, mixed> $bboxEntry
     */
    private function pageResultBboxEntryHasTableMetadata(array $bboxEntry): bool
    {
        foreach ([
            'table_bbox_coordinate_space',
            'bbox_coordinate_space',
            'geometry_coordinate_space',
            'coordinate_space',
            'geometry_space',
            'bbox_order',
            'bbox_coordinate_order',
            'bbox_coordinate_format',
            'bbox_format',
            'coordinate_order',
            'table_bbox_order',
            'table_bbox_coordinate_order',
        ] as $key) {
            if (isset($bboxEntry[$key]) && is_scalar($bboxEntry[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string|int, mixed> $bboxEntry
     * @return list<float>|array<mixed>|null
     */
    private function pageResultOrderedBboxValue(array $bboxEntry): mixed
    {
        $bbox = $this->pageResultBboxValue($bboxEntry);
        if (!is_array($bbox)) {
            return $bbox;
        }

        $raw = $this->pageResultRawBboxCoordinates($bbox);
        $order = $this->pageResultBboxCoordinateOrder($bboxEntry);
        if ($raw === null || $order === null) {
            return $bbox;
        }

        return $this->pageResultCanonicalBbox($this->pageResultApplyBboxCoordinateOrder($raw, $order));
    }

    /**
     * @param array<string|int, mixed> $record
     */
    private function pageResultBboxCoordinateOrder(array $record): ?string
    {
        foreach (['bbox_order', 'bbox_coordinate_order', 'bbox_coordinate_format', 'bbox_format', 'coordinate_order', 'table_bbox_order', 'table_bbox_coordinate_order'] as $key) {
            if (!isset($record[$key]) || !is_scalar($record[$key])) {
                continue;
            }

            $order = $this->pageResultCanonicalBboxCoordinateOrder((string) $record[$key]);
            if ($order !== null) {
                return $order;
            }
        }

        return null;
    }

    private function pageResultCanonicalBboxCoordinateOrder(string $order): ?string
    {
        $normalized = strtolower(trim($order));
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? $normalized;
        $normalized = trim($normalized, '_');

        return match ($normalized) {
            'xyxy',
            'x1_y1_x2_y2',
            'x0_y0_x1_y1',
            'xmin_ymin_xmax_ymax',
            'x_min_y_min_x_max_y_max',
            'left_top_right_bottom' => 'xyxy',
            'xxyy',
            'x1_x2_y1_y2',
            'x0_x1_y0_y1',
            'xmin_xmax_ymin_ymax',
            'x_min_x_max_y_min_y_max',
            'left_right_top_bottom' => 'xxyy',
            'yxyx',
            'y1_x1_y2_x2',
            'y0_x0_y1_x1',
            'ymin_xmin_ymax_xmax',
            'y_min_x_min_y_max_x_max',
            'top_left_bottom_right' => 'yxyx',
            'yyxx',
            'y1_y2_x1_x2',
            'y0_y1_x0_x1',
            'ymin_ymax_xmin_xmax',
            'y_min_y_max_x_min_x_max',
            'top_bottom_left_right' => 'yyxx',
            default => null,
        };
    }

    /**
     * @param array<string|int, mixed> $bbox
     * @return list<float>|null
     */
    private function pageResultRawBboxCoordinates(array $bbox): ?array
    {
        if (count($bbox) !== 4) {
            return null;
        }

        $raw = [];
        foreach (array_values($bbox) as $value) {
            if (is_int($value) || is_float($value)) {
                $raw[] = (float) $value;
                continue;
            }
            if (is_string($value) && is_numeric($value)) {
                $raw[] = (float) $value;
                continue;
            }

            return null;
        }

        return $raw;
    }

    /**
     * @param list<float> $raw
     * @return list<float>
     */
    private function pageResultApplyBboxCoordinateOrder(array $raw, string $order): array
    {
        return match ($order) {
            'xxyy' => [$raw[0], $raw[2], $raw[1], $raw[3]],
            'yxyx' => [$raw[1], $raw[0], $raw[3], $raw[2]],
            'yyxx' => [$raw[2], $raw[0], $raw[3], $raw[1]],
            default => $raw,
        };
    }

    /**
     * @param list<float> $bbox
     * @return list<float>
     */
    private function pageResultCanonicalBbox(array $bbox): array
    {
        return [
            min($bbox[0], $bbox[2]),
            min($bbox[1], $bbox[3]),
            max($bbox[0], $bbox[2]),
            max($bbox[1], $bbox[3]),
        ];
    }

    private function pageResultSharedImageBboxValue(array $pageResult): mixed
    {
        $source = $this->pageResultSharedImageBboxSource($pageResult);
        if ($source === null) {
            return null;
        }

        return $this->pageResultImageBboxValue($pageResult[$source] ?? null, $pageResult, $source);
    }

    private function pageResultSharedImageBboxSource(array $pageResult): ?string
    {
        foreach (['image_bbox', 'page_image_bbox', 'rendered_image_bbox'] as $key) {
            if ($this->pageResultBboxValue($pageResult[$key] ?? null) !== null) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $pageResult
     * @return list<float>|array<mixed>|null
     */
    private function pageResultImageBboxValue(mixed $value, array $pageResult, string $sourceKey): mixed
    {
        if (!is_array($value)) {
            return $this->pageResultBboxValue($value);
        }

        $entry = array_is_list($value) ? ['bbox' => $value] : $value;
        if ($this->pageResultBboxCoordinateOrder($entry) === null) {
            foreach ($this->pageResultImageBboxCoordinateOrderKeys($sourceKey) as $key) {
                if (!isset($pageResult[$key]) || !is_scalar($pageResult[$key])) {
                    continue;
                }

                $entry['bbox_order'] = (string) $pageResult[$key];
                break;
            }
        }

        return $this->pageResultOrderedBboxValue($entry);
    }

    /**
     * @return list<string>
     */
    private function pageResultImageBboxCoordinateOrderKeys(string $sourceKey): array
    {
        return [
            $sourceKey . '_order',
            $sourceKey . '_bbox_order',
            $sourceKey . '_coordinate_order',
            $sourceKey . '_coordinate_format',
            $sourceKey . '_bbox_coordinate_order',
            $sourceKey . '_bbox_coordinate_format',
            $sourceKey . '_bbox_format',
        ];
    }

    /**
     * Upstream tabled keeps one cropped table image in ExtractPageResult.table_imgs
     * for each table. Native sidecars cannot serialize PIL images, but they can
     * keep the crop-plan metadata that defines the same page-image boundary.
     *
     * @param array<string, mixed> $pageResult
     * @return list<mixed>
     */
    private function pageResultTableImages(array $pageResult): array
    {
        $source = $this->pageResultTableImagesSource($pageResult);
        if ($source === null) {
            return [];
        }

        $images = $pageResult[$source] ?? [];

        return is_array($images) ? array_values($images) : [];
    }

    /**
     * @param array<string, mixed> $pageResult
     */
    private function pageResultTableImagesSource(array $pageResult): ?string
    {
        foreach (['table_imgs', 'table_images', 'table_crops', 'crop_images'] as $key) {
            if (isset($pageResult[$key]) && is_array($pageResult[$key])) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $table
     * @param array<string|int, mixed> $tableImage
     * @return array<string, mixed>
     */
    private function withPageResultTableImageMetadata(array $table, array $tableImage, int $tableIndex, string $sourceKey): array
    {
        if (!isset($table['table_image'])) {
            $table['table_image'] = $tableImage;
            $table['table_image_source'] = 'ExtractPageResult.' . $sourceKey . '.' . $tableIndex;
        }

        if (isset($table['table_bbox']) || isset($table['bbox'])) {
            return $table;
        }

        foreach (['table_bbox', 'table_crop_bbox', 'crop_bbox', 'highres_bbox', 'page_table_bbox'] as $bboxKey) {
            if (!array_key_exists($bboxKey, $tableImage)) {
                continue;
            }

            $bbox = $this->pageResultTableImageBboxValue($tableImage, $bboxKey);
            if ($bbox === null) {
                continue;
            }

            $table['table_bbox'] = $bbox;
            $table['table_bbox_source'] = 'ExtractPageResult.' . $sourceKey . '.' . $tableIndex . '.' . $bboxKey;
            $space = $this->pageResultTableImageBboxCoordinateSpace($tableImage, $bboxKey);
            if ($space !== null) {
                $table['table_bbox_coordinate_space'] = $space;
            }
            $order = $this->pageResultTableImageBboxCoordinateOrder($tableImage, $bboxKey);
            if ($order !== null) {
                $table['bbox_order'] = $order;
            }

            return $table;
        }

        return $table;
    }

    /**
     * @param array<string|int, mixed> $tableImage
     * @return list<float>|array<mixed>|null
     */
    private function pageResultTableImageBboxValue(array $tableImage, string $bboxKey): mixed
    {
        $bbox = $this->pageResultBboxValue($tableImage[$bboxKey] ?? null);
        if (!is_array($bbox)) {
            return $bbox;
        }

        $raw = $this->pageResultRawBboxCoordinates($bbox);
        $order = $this->pageResultTableImageBboxCoordinateOrder($tableImage, $bboxKey);
        if ($raw === null || $order === null) {
            return $bbox;
        }

        return $this->pageResultCanonicalBbox($this->pageResultApplyBboxCoordinateOrder($raw, $order));
    }

    /**
     * @param array<string|int, mixed> $tableImage
     */
    private function pageResultTableImageBboxCoordinateSpace(array $tableImage, string $bboxKey): ?string
    {
        foreach ([
            $bboxKey . '_coordinate_space',
            $bboxKey . '_geometry_space',
            $bboxKey . '_bbox_coordinate_space',
            $bboxKey . '_bbox_geometry_space',
            'table_bbox_coordinate_space',
            'table_crop_bbox_coordinate_space',
            'crop_bbox_coordinate_space',
            'bbox_coordinate_space',
            'coordinate_space',
            'geometry_coordinate_space',
            'geometry_space',
        ] as $key) {
            if (isset($tableImage[$key]) && is_scalar($tableImage[$key])) {
                return (string) $tableImage[$key];
            }
        }

        if ($bboxKey === 'highres_bbox' || $bboxKey === 'page_table_bbox') {
            return 'page_image';
        }

        return null;
    }

    /**
     * @param array<string|int, mixed> $tableImage
     */
    private function pageResultTableImageBboxCoordinateOrder(array $tableImage, string $bboxKey): ?string
    {
        foreach ([
            $bboxKey . '_order',
            $bboxKey . '_bbox_order',
            $bboxKey . '_coordinate_order',
            $bboxKey . '_coordinate_format',
            'table_bbox_order',
            'table_bbox_coordinate_order',
            'bbox_order',
            'bbox_coordinate_order',
            'bbox_coordinate_format',
            'bbox_format',
            'coordinate_order',
        ] as $key) {
            if (!isset($tableImage[$key]) || !is_scalar($tableImage[$key])) {
                continue;
            }

            $order = $this->pageResultCanonicalBboxCoordinateOrder((string) $tableImage[$key]);
            if ($order !== null) {
                return $order;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $table
     * @param array<string, mixed> $pageResult
     * @return array<string, mixed>
     */
    private function withPageResultGeometryMetadata(array $table, array $pageResult): array
    {
        foreach ($this->pageResultGeometryMetadataKeys() as $key) {
            if (array_key_exists($key, $table) || !array_key_exists($key, $pageResult)) {
                continue;
            }

            $value = $pageResult[$key];
            if (is_int($value) || is_float($value) || is_string($value) || is_bool($value)) {
                $table[$key] = $value;
            }
        }

        $table = $this->withPageResultTableBboxMetadata($table, $pageResult);

        return $table;
    }

    /**
     * @param array<string, mixed> $table
     * @param array<string, mixed> $pageResult
     * @return array<string, mixed>
     */
    private function withPageResultTableCellsGeometryMetadata(array $table, array $pageResult): array
    {
        foreach ($this->pageResultTableCellsCoordinateSpaceKeys() as $key) {
            if (array_key_exists('cells_coordinate_space', $table) || !isset($pageResult[$key]) || !is_scalar($pageResult[$key])) {
                continue;
            }

            $table['cells_coordinate_space'] = (string) $pageResult[$key];
            break;
        }

        foreach ($this->pageResultTableCellsCoordinateOrderKeys() as $key) {
            if (array_key_exists('cells_bbox_order', $table) || !isset($pageResult[$key]) || !is_scalar($pageResult[$key])) {
                continue;
            }

            $table['cells_bbox_order'] = (string) $pageResult[$key];
            break;
        }

        foreach ($this->pageResultTableCellsCoordinateFormatKeys() as $key) {
            if (array_key_exists('cells_bbox_format', $table) || !isset($pageResult[$key]) || !is_scalar($pageResult[$key])) {
                continue;
            }

            $table['cells_bbox_format'] = (string) $pageResult[$key];
            break;
        }

        return $table;
    }

    /**
     * Page-level ExtractPageResult envelopes name table crop rectangles as
     * bboxes, but flattened recognized-table records expose the selected crop
     * as bbox. Preserve field-specific metadata under the singular keys that
     * TableRecognizer uses to localize the crop before row/cell assignment.
     *
     * @param array<string, mixed> $table
     * @param array<string, mixed> $pageResult
     * @return array<string, mixed>
     */
    private function withPageResultTableBboxMetadata(array $table, array $pageResult): array
    {
        foreach ($this->pageResultTableBboxCoordinateSpaceKeys() as $key) {
            if (array_key_exists('bbox_coordinate_space', $table) || !isset($pageResult[$key]) || !is_scalar($pageResult[$key])) {
                continue;
            }

            $table['bbox_coordinate_space'] = (string) $pageResult[$key];
            break;
        }

        foreach ($this->pageResultTableBboxOrderKeys() as $key) {
            if (array_key_exists('bbox_order', $table) || !isset($pageResult[$key]) || !is_scalar($pageResult[$key])) {
                continue;
            }

            $table['bbox_order'] = (string) $pageResult[$key];
            break;
        }

        foreach ($this->pageResultTableBboxFormatKeys() as $key) {
            if (array_key_exists('bbox_format', $table) || !isset($pageResult[$key]) || !is_scalar($pageResult[$key])) {
                continue;
            }

            $table['bbox_format'] = (string) $pageResult[$key];
            break;
        }

        return $table;
    }

    /**
     * @return list<string>
     */
    private function pageResultTableBboxCoordinateSpaceKeys(): array
    {
        return [
            'bboxes_coordinate_space',
            'table_bboxes_coordinate_space',
            'table_bbox_coordinate_space',
            'bboxes_geometry_space',
            'table_bboxes_geometry_space',
            'table_bbox_geometry_space',
            'bboxes_bbox_coordinate_space',
            'table_bboxes_bbox_coordinate_space',
            'bboxes_bbox_geometry_space',
            'table_bboxes_bbox_geometry_space',
        ];
    }

    /**
     * @return list<string>
     */
    private function pageResultTableBboxOrderKeys(): array
    {
        return [
            'bboxes_order',
            'bboxes_bbox_order',
            'bboxes_coordinate_order',
            'table_bboxes_order',
            'table_bboxes_bbox_order',
            'table_bboxes_coordinate_order',
            'table_bbox_order',
            'table_bbox_coordinate_order',
        ];
    }

    /**
     * @return list<string>
     */
    private function pageResultTableBboxFormatKeys(): array
    {
        return [
            'bboxes_bbox_format',
            'bboxes_coordinate_format',
            'table_bboxes_bbox_format',
            'table_bboxes_coordinate_format',
            'table_bbox_format',
            'table_bbox_coordinate_format',
        ];
    }

    /**
     * @return list<string>
     */
    private function pageResultTableCellsCoordinateSpaceKeys(): array
    {
        return [
            'table_cells_coordinate_space',
            'table_cell_coordinate_space',
            'table_cells_geometry_space',
            'table_cell_geometry_space',
            'table_cells_bbox_coordinate_space',
            'table_cell_bbox_coordinate_space',
        ];
    }

    /**
     * @return list<string>
     */
    private function pageResultTableCellsCoordinateOrderKeys(): array
    {
        return [
            'table_cells_bbox_order',
            'table_cell_bbox_order',
            'table_cells_order',
            'table_cell_order',
            'table_cells_coordinate_order',
            'table_cell_coordinate_order',
            'table_cells_bbox_coordinate_order',
            'table_cell_bbox_coordinate_order',
        ];
    }

    /**
     * @return list<string>
     */
    private function pageResultTableCellsCoordinateFormatKeys(): array
    {
        return [
            'table_cells_bbox_format',
            'table_cell_bbox_format',
            'table_cells_coordinate_format',
            'table_cell_coordinate_format',
        ];
    }

    /**
     * @return list<string>
     */
    private function pageResultGeometryMetadataKeys(): array
    {
        return [
            'coordinate_space',
            'geometry_coordinate_space',
            'bbox_coordinate_space',
            'bbox_order',
            'bbox_coordinate_order',
            'bbox_coordinate_format',
            'bbox_format',
            'coordinate_order',
            'rows_coordinate_space',
            'row_coordinate_space',
            'rows_geometry_space',
            'row_geometry_space',
            'rows_bbox_order',
            'row_bbox_order',
            'row_bboxes_order',
            'row_bboxes_bbox_order',
            'row_boxes_order',
            'row_bounds_order',
            'rows_coordinate_order',
            'row_coordinate_order',
            'rows_bbox_format',
            'row_bbox_format',
            'cols_coordinate_space',
            'col_coordinate_space',
            'cols_geometry_space',
            'col_geometry_space',
            'columns_coordinate_space',
            'column_coordinate_space',
            'columns_geometry_space',
            'column_geometry_space',
            'cols_bbox_order',
            'col_bbox_order',
            'columns_order',
            'column_order',
            'column_bboxes_order',
            'col_bboxes_order',
            'columns_bbox_order',
            'column_bbox_order',
            'cols_coordinate_order',
            'col_coordinate_order',
            'columns_coordinate_order',
            'column_coordinate_order',
            'cols_bbox_format',
            'col_bbox_format',
            'columns_bbox_format',
            'column_bbox_format',
            'cells_coordinate_space',
            'cell_coordinate_space',
            'cells_geometry_space',
            'cell_geometry_space',
            'cells_order',
            'cell_order',
            'cells_bbox_order',
            'cell_bbox_order',
            'cells_coordinate_order',
            'cell_coordinate_order',
            'cells_bbox_format',
            'cell_bbox_format',
            'ocr_grid_border_conflicts_coordinate_space',
            'ocr_grid_border_conflict_coordinate_space',
            'grid_border_conflicts_coordinate_space',
            'grid_border_conflict_coordinate_space',
            'ocr_grid_border_conflicts_geometry_space',
            'ocr_grid_border_conflict_geometry_space',
            'grid_border_conflicts_geometry_space',
            'grid_border_conflict_geometry_space',
            'conflicts_bbox_order',
            'conflict_bbox_order',
            'conflicts_coordinate_order',
            'conflict_coordinate_order',
            'conflicts_bbox_format',
            'conflict_bbox_format',
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
     * @param list<array<string, mixed>> $pages
     * @param array<string, mixed> $context
     * @param list<int> $pageRange
     * @param list<mixed> $lowresImages
     * @return array<string, mixed>
     */
    private function imageOnlyOcrHandoff(
        array $pages,
        array $context,
        array $pageRange,
        array $lowresImages,
        int $sourcePageCount,
        MarkerSettings $settings
    ): array {
        $pageRows = [];
        $presentImageCount = 0;
        $missingImageCount = 0;
        $ocrAllPages = ($context['ocr_all_pages'] ?? false) === true;
        $lowresImagePlan = isset($context['lowres_image_plan']) && is_array($context['lowres_image_plan'])
            ? array_values($context['lowres_image_plan'])
            : [];
        $pageLabels = isset($context['page_labels']) && is_array($context['page_labels'])
            ? array_values($context['page_labels'])
            : [];

        foreach (array_values($pages) as $index => $page) {
            $image = $lowresImages[$index] ?? null;
            $imageSummary = $this->imageOnlyPageImageSummary($image);
            if (($imageSummary['present'] ?? false) === true) {
                $presentImageCount++;
            } else {
                $missingImageCount++;
            }

            $pageReasons = ['no-extracted-text-blocks'];
            $pageReasons[] = ($imageSummary['present'] ?? false) === true
                ? 'rendered-page-image-available'
                : 'rendered-page-image-missing';
            if ($ocrAllPages) {
                $pageReasons[] = 'ocr-all-pages-requested';
            }

            $sourcePageIndex = $pageRange[$index] ?? $index;
            $row = [
                'selected_page_index' => $index,
                'source_page_index' => $sourcePageIndex,
                'page_number' => $sourcePageIndex + 1,
                'pdftext_page' => $this->imageOnlyInteger($page['pnum'] ?? $page['page'] ?? null),
                'page_label' => isset($pageLabels[$index]) && is_string($pageLabels[$index]) ? $pageLabels[$index] : null,
                'bbox' => $this->imageOnlyBbox($page['bbox'] ?? null),
                'rotation' => $this->imageOnlyInteger($page['rotation'] ?? null),
                'text_block_count' => $this->imageOnlyBlockCount($page),
                'text_line_count' => $this->imageOnlyLineCount($page),
                'detected_text_line_count' => $this->imageOnlyDetectedLineCount($page),
                'ocr_required' => true,
                'ocr_required_reasons' => $pageReasons,
                'rendered_image' => $imageSummary,
            ];
            if (isset($lowresImagePlan[$index]) && is_array($lowresImagePlan[$index])) {
                $row['render_plan'] = [
                    'doc_page_index' => $this->imageOnlyInteger($lowresImagePlan[$index]['doc_page_index'] ?? null),
                    'dpi' => $this->imageOnlyNumeric($lowresImagePlan[$index]['dpi'] ?? null),
                ];
            }

            $pageRows[] = $row;
        }

        $reasons = [];
        if ($pageRows === []) {
            $reasons[] = 'no-selected-pages';
        } else {
            $reasons[] = 'no-extracted-text-blocks';
            $reasons[] = $presentImageCount > 0
                ? 'rendered-page-images-available'
                : 'rendered-page-images-missing';
            if ($missingImageCount > 0 && $presentImageCount > 0) {
                $reasons[] = 'some-rendered-page-images-missing';
            }
            if ($ocrAllPages) {
                $reasons[] = 'ocr-all-pages-requested';
            }
        }

        return [
            'schema' => 'markerpdf.image_only_pdf_ocr_handoff.v1',
            'source' => 'sddai/markerPDF marker.convert::convert_single_pdf native image-only boundary',
            'status' => $pageRows === [] ? 'no-selected-pages' : 'ocr-required',
            'ocr_required' => $pageRows !== [],
            'ocr_required_reasons' => $reasons,
            'source_page_count' => $sourcePageCount,
            'selected_page_count' => count($pageRows),
            'selected_page_range' => $pageRange,
            'rendered_page_image_count' => $presentImageCount,
            'missing_rendered_page_image_count' => $missingImageCount,
            'pages' => $pageRows,
            'adapter_hooks' => [
                'pdftext_boundary' => 'supplied pdftext pages with no extracted text blocks',
                'image_boundary' => 'lowres_images selected by source page before OCR runtime',
                'ocr_detection_input' => 'pages[].rendered_image',
                'ocr_recognition_input' => 'pages[].rendered_image',
                'markdown_policy' => 'return empty text until an adapter supplies recognized OCR pages',
            ],
            'diagnostics' => [
                'visible_text_emitted' => false,
                'garbage_text_suppressed' => true,
                'requires_external_ocr_adapter' => $pageRows !== [],
                'ocr_engine_requested' => (string) ($settings->get('OCR_ENGINE') ?? 'surya'),
                'default_language' => (string) ($settings->get('DEFAULT_LANG') ?? 'English'),
                'no_native_ocr_runtime' => true,
            ],
            'review_only' => true,
            'executes_python_or_models' => false,
            'executes_ocr_runtime' => false,
            'executes_multiprocessing' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function imageOnlyPageImageSummary(mixed $image): array
    {
        if ($image === null || PdfPageArtifactSelector::isMissingPageArtifact($image)) {
            return [
                'present' => false,
                'missing' => true,
                'pixel_payload_exposed' => false,
            ];
        }

        if (!is_array($image)) {
            $summary = [
                'present' => true,
                'artifact_type' => get_debug_type($image),
                'pixel_payload_exposed' => false,
            ];
            if (is_scalar($image)) {
                $summary['payload_bytes'] = strlen((string) $image);
                $summary['payload_sha256'] = hash('sha256', (string) $image);
                $reference = $this->imageOnlyReferenceValue($image);
                if ($reference !== null) {
                    $summary['reference'] = $reference;
                }
            }

            return $summary;
        }

        $summary = [
            'present' => true,
            'artifact_type' => 'array',
            'artifact_keys' => array_values(array_map(static fn (int|string $key): string => (string) $key, array_keys($image))),
            'pixel_payload_exposed' => false,
        ];

        foreach (['width', 'height', 'dpi', 'page', 'page_index', 'doc_page_index', 'selected_page_index'] as $key) {
            if (array_key_exists($key, $image)) {
                $numeric = $this->imageOnlyNumeric($image[$key]);
                if ($numeric !== null) {
                    $summary[$key] = $numeric;
                }
            }
        }

        foreach (['bbox', 'image_bbox', 'page_bbox', 'rendered_image_bbox'] as $key) {
            if (array_key_exists($key, $image)) {
                $bbox = $this->imageOnlyBbox($image[$key]);
                if ($bbox !== null) {
                    $summary[$key] = $bbox;
                }
            }
        }

        foreach (['path', 'filename', 'uri', 'url', 'id', 'image_id', 'cache_key', 'image'] as $key) {
            if (!array_key_exists($key, $image)) {
                continue;
            }

            $reference = $this->imageOnlyReferenceValue($image[$key]);
            if ($reference !== null) {
                $summary[$key] = $reference;
                continue;
            }

            if (is_scalar($image[$key])) {
                $summary[$key . '_bytes'] = strlen((string) $image[$key]);
                $summary[$key . '_sha256'] = hash('sha256', (string) $image[$key]);
            }
        }

        return $summary;
    }

    private function imageOnlyReferenceValue(mixed $value): string|int|float|bool|null
    {
        if (is_int($value) || is_float($value) || is_bool($value)) {
            return $value;
        }
        if (!is_string($value) || $value === '' || strlen($value) > 180) {
            return null;
        }
        if (preg_match('/[[:cntrl:]]|\s/', $value) === 1) {
            return null;
        }
        if (str_starts_with(strtolower($value), 'data:')) {
            return null;
        }
        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $value) === 1) {
            return $value;
        }
        if (preg_match('/^[A-Za-z0-9._~\/:@%#?=&,+-]+$/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    private function imageOnlyInteger(mixed $value): ?int
    {
        if (is_int($value) || is_float($value)) {
            return (int) $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    private function imageOnlyNumeric(mixed $value): int|float|null
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return null;
    }

    /**
     * @return list<float>|null
     */
    private function imageOnlyBbox(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }
        if (isset($value['bbox'])) {
            return $this->imageOnlyBbox($value['bbox']);
        }

        $values = array_values($value);
        if (count($values) !== 4) {
            return null;
        }

        $bbox = [];
        foreach ($values as $item) {
            $number = $this->imageOnlyNumeric($item);
            if ($number === null) {
                return null;
            }
            $bbox[] = (float) $number;
        }

        return $bbox;
    }

    /**
     * @param array<string, mixed> $page
     */
    private function imageOnlyBlockCount(array $page): int
    {
        $blocks = $page['blocks'] ?? [];

        return is_array($blocks) ? count(array_filter($blocks, 'is_array')) : 0;
    }

    /**
     * @param array<string, mixed> $page
     */
    private function imageOnlyLineCount(array $page): int
    {
        $count = 0;
        $blocks = $page['blocks'] ?? [];
        if (!is_array($blocks)) {
            return 0;
        }

        foreach ($blocks as $block) {
            if (!is_array($block) || !isset($block['lines']) || !is_array($block['lines'])) {
                continue;
            }
            $count += count(array_filter($block['lines'], 'is_array'));
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $page
     */
    private function imageOnlyDetectedLineCount(array $page): int
    {
        $textLines = $page['text_lines'] ?? $page['textLines'] ?? null;
        if (!is_array($textLines)) {
            return 0;
        }

        $boxes = $textLines['bboxes'] ?? $textLines['boxes'] ?? null;

        return is_array($boxes) ? count($boxes) : 0;
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
                    $size = [
                        'width' => (int) round($cropWidth),
                        'height' => (int) round($cropHeight),
                    ];
                    if (isset($tableImage['highres_bbox']) && is_array($tableImage['highres_bbox'])) {
                        $size['table_bbox'] = $tableImage['highres_bbox'];
                    }
                    $sizes[] = $size;
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
     * @return list<array<string, mixed>>
     */
    private function tableCoordinateSpaceReviews(mixed $reviews): array
    {
        if (!is_array($reviews)) {
            return [];
        }

        $normalized = [];
        foreach ($reviews as $review) {
            if (is_array($review)) {
                $normalized[] = $review;
            }
        }

        return $normalized;
    }

    /**
     * @param mixed $reviews
     * @return list<array<string, mixed>|null>
     */
    private function tableAssignedSourceBoundaryReviews(mixed $reviews): array
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
     * @param mixed $reviews
     * @return list<array<string, mixed>|null>
     */
    private function tableAssignedCropBoundaryReviews(mixed $reviews): array
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
     * @param mixed $reviews
     * @return list<array<string, mixed>|null>
     */
    private function tableAssignedBandBoundaryReviews(mixed $reviews): array
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
     * @param mixed $reviews
     * @return list<array<string, mixed>|null>
     */
    private function tableDetectorCellBoundaryReviews(mixed $reviews): array
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
     * Upstream tabled.extract.py saves results.json as a dictionary keyed by
     * source filename without extension. Accept that envelope at the supplied
     * boundary and select the current PDF's table list before normal table
     * localization.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function withSelectedRecognizedTablesOption(array $options, string $filename): array
    {
        if (!array_key_exists('recognized_tables', $options) || $options['recognized_tables'] === null) {
            return $options;
        }

        $recognizedTables = $options['recognized_tables'];
        if (!is_array($recognizedTables) || array_is_list($recognizedTables)) {
            return $options;
        }

        $selection = $this->selectedRecognizedTablesFromResultEnvelope($recognizedTables, $filename);
        if ($selection === null) {
            throw new InvalidArgumentException(
                'markerPDF supplied document option recognized_tables must be a list or a tabled result envelope keyed by source basename.'
            );
        }

        $options['recognized_tables'] = $selection['tables'];
        $options['_table_result_envelope_review'] = $selection['review'];

        return $options;
    }

    /**
     * @param array<string|int, mixed> $envelope
     * @return array{tables: list<array<string, mixed>>, review: array<string, mixed>}|null
     */
    private function selectedRecognizedTablesFromResultEnvelope(array $envelope, string $filename): ?array
    {
        $normalizedFilename = str_replace('\\', '/', $filename);
        $sourceBasename = basename($normalizedFilename);
        $sourceStem = $this->filenameWithoutFinalExtension($sourceBasename);
        $candidateKeys = $this->recognizedTableEnvelopeCandidateKeys($filename, $normalizedFilename, $sourceBasename, $sourceStem);
        $availableKeys = array_map(static fn (mixed $key): string => (string) $key, array_keys($envelope));

        foreach ($candidateKeys as $candidateKey) {
            if (!array_key_exists($candidateKey, $envelope)) {
                continue;
            }

            $tables = $envelope[$candidateKey];
            if (!is_array($tables) || !array_is_list($tables)) {
                throw new InvalidArgumentException(
                    "markerPDF supplied document option recognized_tables envelope value for {$candidateKey} must be a list."
                );
            }

            return [
                'tables' => array_values($tables),
                'review' => [
                    'review_target' => 'table_saved_result_envelope_boundary',
                    'upstream_boundary' => 'tabled.extract.py results.json basename-keyed table list',
                    'selected_key' => $candidateKey,
                    'source_filename' => $filename,
                    'source_basename' => $sourceBasename,
                    'source_basename_without_extension' => $sourceStem,
                    'basename_without_extension_match' => $candidateKey === $sourceStem,
                    'available_keys' => $availableKeys,
                    'available_key_count' => count($availableKeys),
                    'selected_table_count' => count($tables),
                    'executes_python_or_models' => false,
                    'executes_external_pdf_tools' => false,
                ],
            ];
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function recognizedTableEnvelopeCandidateKeys(
        string $filename,
        string $normalizedFilename,
        string $sourceBasename,
        string $sourceStem
    ): array {
        $candidates = [$sourceStem, $sourceBasename, $normalizedFilename, $filename];
        $keys = [];
        foreach ($candidates as $candidate) {
            if ($candidate === '' || in_array($candidate, $keys, true)) {
                continue;
            }

            $keys[] = $candidate;
        }

        return $keys;
    }

    private function filenameWithoutFinalExtension(string $filename): string
    {
        $extensionOffset = strrpos($filename, '.');
        if ($extensionOffset === false) {
            return $filename;
        }

        return substr($filename, 0, $extensionOffset);
    }

    /**
     * @param array<string, mixed> $options
     * @return list<array<string, mixed>>
     */
    private function taggedTablesOption(array $options): array
    {
        $tables = $this->listOption($options, 'tagged_tables');
        $out = [];
        foreach ($tables as $index => $table) {
            if (!is_array($table)) {
                throw new InvalidArgumentException('markerPDF supplied document option tagged_tables entries must be arrays.');
            }

            $html = $table['html'] ?? null;
            if (!is_string($html) || trim($html) === '') {
                throw new InvalidArgumentException('markerPDF supplied document option tagged_tables[' . $index . '] must include non-empty html.');
            }
            if (($table['unambiguous'] ?? true) !== true) {
                continue;
            }

            $out[] = $table;
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $pages
     * @param list<array<string, mixed>> $taggedTables
     * @return array{pages: list<array<string, mixed>>, inserted_tables: int, metadata: array<string, mixed>}
     */
    private function insertTaggedTableBlocks(array $pages, array $taggedTables): array
    {
        $insertedTables = 0;
        $reviews = [];
        foreach ($taggedTables as $tableIndex => $taggedTable) {
            $pageIndex = $this->taggedTablePageIndex($taggedTable, $pages);
            if ($pageIndex === null) {
                $reviews[] = $this->taggedTableInsertionReview($taggedTable, $tableIndex, null, false, [], null);
                continue;
            }

            $page = $pages[$pageIndex];
            $blocks = array_values(array_filter(
                $page['blocks'] ?? [],
                static fn (mixed $block): bool => is_array($block)
            ));
            $replaceTexts = $this->taggedTableReplaceTexts($taggedTable);
            $newBlocks = [];
            $removedTexts = [];
            $insertPoint = count($blocks);
            foreach ($blocks as $blockIndex => $block) {
                $text = $this->blockPlainText($block);
                if ($text !== '' && in_array($text, $replaceTexts, true)) {
                    if ($removedTexts === []) {
                        $insertPoint = count($newBlocks);
                    }
                    $removedTexts[] = $text;
                    continue;
                }

                $newBlocks[] = $block;
            }

            $bbox = $this->taggedTableBlockBbox($taggedTable, $page);
            $pnum = (int) ($page['pnum'] ?? $pageIndex);
            $tableBlock = $this->taggedTableBlock($bbox, (string) $taggedTable['html'], $pnum, $tableIndex);
            array_splice($newBlocks, min($insertPoint, count($newBlocks)), 0, [$tableBlock]);
            $page['blocks'] = $newBlocks;
            $pages[$pageIndex] = $page;
            $insertedTables++;
            $reviews[] = $this->taggedTableInsertionReview(
                $taggedTable,
                $tableIndex,
                $pageIndex,
                true,
                $removedTexts,
                min($insertPoint, count($newBlocks) - 1)
            );
        }

        return [
            'pages' => array_values($pages),
            'inserted_tables' => $insertedTables,
            'metadata' => [
                'source' => 'tagged_table_structure_insertion',
                'review_only' => true,
                'visible_text_source' => false,
                'table_count' => count($taggedTables),
                'inserted_tables' => $insertedTables,
                'tables' => $reviews,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $taggedTable
     * @param list<array<string, mixed>> $pages
     */
    private function taggedTablePageIndex(array $taggedTable, array $pages): ?int
    {
        foreach (['page', 'page_index'] as $key) {
            $pageIndex = $taggedTable[$key] ?? null;
            if (is_int($pageIndex) && isset($pages[$pageIndex])) {
                return $pageIndex;
            }
        }

        $pageNumber = $taggedTable['page_number'] ?? null;
        if (is_int($pageNumber) && $pageNumber > 0 && isset($pages[$pageNumber - 1])) {
            return $pageNumber - 1;
        }

        $pnum = $taggedTable['pnum'] ?? null;
        if (is_int($pnum)) {
            foreach ($pages as $pageIndex => $page) {
                if ((int) ($page['pnum'] ?? $pageIndex) === $pnum) {
                    return $pageIndex;
                }
            }
        }

        return $pages === [] ? null : 0;
    }

    /**
     * @param array<string, mixed> $taggedTable
     * @return list<string>
     */
    private function taggedTableReplaceTexts(array $taggedTable): array
    {
        $texts = [];
        $replaceTexts = $taggedTable['replace_texts'] ?? [];
        if (!is_array($replaceTexts)) {
            return [];
        }

        foreach ($replaceTexts as $text) {
            if (!is_scalar($text)) {
                continue;
            }

            $text = trim((string) $text);
            if ($text !== '' && !in_array($text, $texts, true)) {
                $texts[] = $text;
            }
        }

        return $texts;
    }

    /**
     * @param array<string, mixed> $taggedTable
     * @param array<string, mixed> $page
     * @return list<float>
     */
    private function taggedTableBlockBbox(array $taggedTable, array $page): array
    {
        $bbox = $this->numericBbox($taggedTable['bbox'] ?? null);
        if ($bbox !== null) {
            return $bbox;
        }

        $pageBbox = $this->numericBbox($page['bbox'] ?? null) ?? [0.0, 0.0, 612.0, 792.0];
        return [$pageBbox[0], $pageBbox[1], $pageBbox[2], min($pageBbox[3], $pageBbox[1] + 24.0)];
    }

    /**
     * @return list<float>|null
     */
    private function numericBbox(mixed $value): ?array
    {
        if (!is_array($value) || count($value) !== 4) {
            return null;
        }

        $bbox = [];
        foreach (array_values($value) as $part) {
            if (!is_int($part) && !is_float($part) && !(is_string($part) && is_numeric($part))) {
                return null;
            }
            $bbox[] = (float) $part;
        }

        return $bbox;
    }

    /**
     * @param list<float> $bbox
     * @return array<string, mixed>
     */
    private function taggedTableBlock(array $bbox, string $html, int $pnum, int $tableIndex): array
    {
        return [
            'bbox' => $bbox,
            'type' => 'Table',
            'block_type' => 'Table',
            'pnum' => $pnum,
            'lines' => [[
                'bbox' => $bbox,
                'spans' => [[
                    'bbox' => $bbox,
                    'span_id' => 'tagged_' . $tableIndex . '_table',
                    'font' => 'TaggedTable',
                    'font_size' => 0,
                    'font_weight' => 0,
                    'block_type' => 'Table',
                    'text' => $html,
                ]],
            ]],
        ];
    }

    /**
     * @param array<string, mixed> $block
     */
    private function blockPlainText(array $block): string
    {
        $parts = [];
        foreach (($block['lines'] ?? []) as $line) {
            if (!is_array($line)) {
                continue;
            }
            if (isset($line['text']) && is_string($line['text'])) {
                $parts[] = trim($line['text']);
                continue;
            }

            $lineText = '';
            foreach (($line['spans'] ?? []) as $span) {
                if (is_array($span)) {
                    $lineText .= (string) ($span['text'] ?? '');
                }
            }
            if (trim($lineText) !== '') {
                $parts[] = trim($lineText);
            }
        }

        return trim(implode(' ', $parts));
    }

    /**
     * @param array<string, mixed> $taggedTable
     * @param list<string> $removedTexts
     * @return array<string, mixed>
     */
    private function taggedTableInsertionReview(
        array $taggedTable,
        int $tableIndex,
        ?int $pageIndex,
        bool $inserted,
        array $removedTexts,
        ?int $insertPoint
    ): array {
        $html = (string) ($taggedTable['html'] ?? '');
        $review = [
            'source' => 'tagged_table_structure_insertion',
            'review_only' => true,
            'visible_text_source' => false,
            'table_index' => $tableIndex,
            'inserted' => $inserted,
            'page_index' => $pageIndex,
            'insert_point' => $insertPoint,
            'html_length' => strlen($html),
            'html_sha256' => hash('sha256', $html),
            'removed_text_count' => count($removedTexts),
        ];

        foreach (['struct_object', 'page', 'page_number', 'page_object'] as $key) {
            if (array_key_exists($key, $taggedTable)) {
                $review[$key] = $taggedTable[$key];
            }
        }
        if ($removedTexts !== []) {
            $review['removed_texts'] = $removedTexts;
        }
        if (isset($taggedTable['metadata']) && is_array($taggedTable['metadata'])) {
            $review['structure'] = $taggedTable['metadata'];
        }

        return array_filter($review, static fn (mixed $value): bool => $value !== null && $value !== []);
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
     * @return array<mixed>
     */
    private function pageArtifactOption(array $options, string $key): array
    {
        if (!array_key_exists($key, $options) || $options[$key] === null) {
            return [];
        }
        if (!is_array($options[$key])) {
            throw new InvalidArgumentException("markerPDF supplied document option {$key} must be an array.");
        }
        if (
            !array_is_list($options[$key])
            && !$this->isSourcePageKeyedArtifactMap($options[$key])
            && !$this->isPageArtifactEnvelopeOption($options[$key])
        ) {
            throw new InvalidArgumentException("markerPDF supplied document option {$key} must be a list, source-page keyed map, or artifact page-list envelope.");
        }

        return $options[$key];
    }

    /**
     * @param array<mixed> $value
     */
    private function isSourcePageKeyedArtifactMap(array $value): bool
    {
        if ($value === [] || array_is_list($value)) {
            return false;
        }

        $hasArtifact = false;
        foreach ($value as $key => $candidate) {
            $candidate = $this->normalizeSourcePageArtifactMapCandidateValue($candidate);
            if ($this->isIntegerArrayKey($key) && is_array($candidate) && !array_is_list($candidate) && $this->hasSourcePageArtifactPayload($candidate)) {
                $hasArtifact = true;
                continue;
            }

            if ($this->isIgnorableSourcePageArtifactSidecar($candidate)) {
                continue;
            }

            return false;
        }

        return $hasArtifact;
    }

    /**
     * @param array<mixed> $value
     */
    private function isPageArtifactEnvelopeOption(array $value): bool
    {
        if ($value === [] || array_is_list($value)) {
            return false;
        }

        $hasEnvelopeKey = false;
        foreach (['pages', 'dictionary_output', 'pdftext', 'page_map', 'pageMap'] as $envelopeKey) {
            if (array_key_exists($envelopeKey, $value)) {
                $hasEnvelopeKey = true;
                break;
            }
        }
        if (!$hasEnvelopeKey) {
            return false;
        }

        foreach (PdfPageArtifactSelector::normalizeSuppliedArtifacts($value) as $artifact) {
            $artifact = $this->normalizeSourcePageArtifactMapCandidateValue($artifact);
            if (is_array($artifact) && !array_is_list($artifact) && $this->hasSelectablePageArtifactPayload($artifact)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Source-page keyed layout/order/image sidecars can come from JSON caches
     * as strings. Decode only artifact-shaped objects for option validation;
     * actual selection and key preservation still happens in PdfPageArtifactSelector.
     */
    private function normalizeSourcePageArtifactMapCandidateValue(mixed $candidate): mixed
    {
        if (!is_string($candidate)) {
            return PdfPageArtifactSelector::normalizeSuppliedArtifactValue($candidate);
        }

        $trimmed = trim($candidate);
        if (str_starts_with($trimmed, "\xEF\xBB\xBF")) {
            $trimmed = trim(substr($trimmed, 3));
        }
        if ($trimmed === '' || !in_array($trimmed[0], ['[', '{'], true)) {
            return $candidate;
        }

        try {
            $decoded = json_decode($trimmed, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $candidate;
        }

        $decoded = PdfPageArtifactSelector::normalizeSuppliedArtifactValue($decoded);
        if (!is_array($decoded) || !$this->hasSourcePageArtifactPayload($decoded)) {
            return $candidate;
        }

        return $decoded;
    }

    private function isIgnorableSourcePageArtifactSidecar(mixed $candidate): bool
    {
        return !is_array($candidate) || !$this->hasSourcePageArtifactPayload($candidate);
    }

    /**
     * @param array<mixed> $value
     */
    private function hasSourcePageArtifactPayload(array $value): bool
    {
        foreach ([
            'blocks',
            'bbox',
            ...$this->selectablePageArtifactPayloadKeys(),
        ] as $key) {
            if (array_key_exists($key, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<mixed> $value
     */
    private function hasSelectablePageArtifactPayload(array $value): bool
    {
        foreach ($this->selectablePageArtifactPayloadKeys() as $key) {
            if (array_key_exists($key, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function selectablePageArtifactPayloadKeys(): array
    {
        return [
            'bboxes',
            'image',
            'image_bbox',
            'layout',
            'layout_result',
            'order',
            'order_result',
            'prediction',
            'result',
            'model_output',
            'output',
            'page_data',
            'page_result',
        ];
    }

    private function isIntegerArrayKey(int|string $key): bool
    {
        if (is_int($key)) {
            return true;
        }

        return preg_match('/^[+-]?\d+(?:\.0+)?$/', trim($key)) === 1;
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
        return $this->equationRecognitionOption($options, new MarkerSettings(), 1.0)['predictions'];
    }

    /**
     * @param array<string, mixed> $options
     * @return array{predictions: list<string>, review: array<string, mixed>|null}
     */
    private function equationRecognitionOption(array $options, MarkerSettings $settings, float $batchMultiplier): array
    {
        $key = null;
        if (array_key_exists('equation_results', $options) && $options['equation_results'] !== null) {
            $key = 'equation_results';
        } elseif (array_key_exists('equation_predictions', $options) && $options['equation_predictions'] !== null) {
            $key = 'equation_predictions';
        }

        if ($key === null) {
            return [
                'predictions' => [],
                'review' => null,
            ];
        }

        $items = $this->listOption($options, $key);
        $predictions = [];
        $records = [];
        $sourceFields = [];
        $directPredictionCount = 0;
        $batchImages = [];
        $batchTokenCounts = [];
        $batchOutputs = [];
        $batchPredictionSlots = [];

        foreach ($items as $item) {
            $itemIndex = count($records);
            if (is_scalar($item)) {
                $prediction = (string) $item;
                $predictions[$itemIndex] = $prediction;
                $records[] = [
                    'result_index' => $itemIndex,
                    'source_field' => 'scalar',
                    'prediction_length' => strlen($prediction),
                    'supplied_model_output' => false,
                ];
                $sourceFields[] = 'scalar';
                $directPredictionCount++;
                continue;
            }

            if (!is_array($item)) {
                throw new InvalidArgumentException("markerPDF supplied document option {$key} must contain strings or arrays.");
            }

            $direct = $this->equationDirectPrediction($item);
            if ($direct !== null) {
                $predictions[$itemIndex] = $direct['value'];
                $records[] = [
                    ...$this->equationResultRecord($item, $itemIndex),
                    'source_field' => $direct['field'],
                    'prediction_length' => strlen($direct['value']),
                    'supplied_model_output' => false,
                ];
                $sourceFields[] = $direct['field'];
                $directPredictionCount++;
                continue;
            }

            $modelOutput = $this->equationModelOutput($item);
            if ($modelOutput !== null) {
                $tokenCount = $this->equationModelTokenCount($item, $key);
                $batchIndex = count($batchOutputs);
                $batchImages[] = $this->equationModelImage($item, $batchIndex);
                $batchTokenCounts[] = $tokenCount;
                $batchOutputs[] = $modelOutput['value'];
                $batchPredictionSlots[$itemIndex] = $batchIndex;
                $records[] = [
                    ...$this->equationResultRecord($item, $itemIndex),
                    'source_field' => $modelOutput['field'],
                    'token_count' => $tokenCount,
                    'model_output_length' => strlen($modelOutput['value']),
                    'supplied_model_output' => true,
                ];
                $sourceFields[] = $modelOutput['field'];
                continue;
            }

            throw new InvalidArgumentException("markerPDF supplied document option {$key} arrays must include latex, prediction, text, or supplied model output with token_count.");
        }

        $batchPlan = [
            'predictions' => [],
            'batches' => [],
            'dropped_output_indexes' => [],
            'batch_size' => 0,
        ];
        if ($batchOutputs !== []) {
            $batchPlan = (new EquationReplacer(settings: $settings))->getLatexBatchedFromSuppliedOutputs(
                $batchImages,
                $batchTokenCounts,
                $batchOutputs,
                $batchMultiplier
            );
            foreach ($batchPredictionSlots as $itemIndex => $batchIndex) {
                $predictions[$itemIndex] = $batchPlan['predictions'][$batchIndex] ?? '';
                if (isset($records[$itemIndex])) {
                    $records[$itemIndex]['prediction_length'] = strlen($predictions[$itemIndex]);
                    $records[$itemIndex]['dropped_by_max_token_sentinel'] = in_array($batchIndex, $batchPlan['dropped_output_indexes'], true);
                }
            }
        }

        ksort($predictions);
        $predictions = array_values($predictions);

        return [
            'predictions' => $predictions,
            'review' => [
                'review_target' => 'equation_model_result_adapter_boundary',
                'source' => 'markerpdf_supplied_equation_result_adapter',
                'upstream_boundary' => 'marker.equations.inference.get_latex_batched supplied outputs',
                'option_key' => $key,
                'result_count' => count($items),
                'prediction_count' => count($predictions),
                'direct_prediction_count' => $directPredictionCount,
                'supplied_model_output_count' => count($batchOutputs),
                'source_fields' => array_values(array_unique($sourceFields)),
                'batch_size' => $batchPlan['batch_size'],
                'batches' => $batchPlan['batches'],
                'dropped_output_indexes' => $batchPlan['dropped_output_indexes'],
                'records' => $records,
                'executes_python_or_models' => false,
                'executes_external_pdf_tools' => false,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return array{field: string, value: string}|null
     */
    private function equationDirectPrediction(array $item): ?array
    {
        foreach (['latex', 'prediction', 'text'] as $field) {
            if (array_key_exists($field, $item) && is_scalar($item[$field])) {
                return [
                    'field' => $field,
                    'value' => (string) $item[$field],
                ];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $item
     * @return array{field: string, value: string}|null
     */
    private function equationModelOutput(array $item): ?array
    {
        foreach (['model_output', 'output', 'generated_text', 'decoded_text'] as $field) {
            if (array_key_exists($field, $item) && is_scalar($item[$field])) {
                return [
                    'field' => $field,
                    'value' => (string) $item[$field],
                ];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function equationModelTokenCount(array $item, string $key): int
    {
        foreach (['token_count', 'tokens', 'input_token_count', 'source_token_count'] as $field) {
            if (!array_key_exists($field, $item)) {
                continue;
            }
            $value = $item[$field];
            if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
                return (int) $value;
            }
        }

        throw new InvalidArgumentException("markerPDF supplied document option {$key} model output arrays must include numeric token_count.");
    }

    /**
     * @param array<string, mixed> $item
     */
    private function equationModelImage(array $item, int $batchIndex): mixed
    {
        foreach (['image', 'equation_image', 'rendered_image', 'crop_image'] as $field) {
            if (array_key_exists($field, $item)) {
                return $item[$field];
            }
        }

        return 'supplied-equation-image-' . $batchIndex;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function equationResultRecord(array $item, int $itemIndex): array
    {
        $record = [
            'result_index' => $itemIndex,
        ];

        foreach (['page', 'pnum', 'page_number', 'bbox', 'score', 'confidence'] as $field) {
            if (array_key_exists($field, $item)) {
                $record[$field] = $item[$field];
            }
        }

        return $record;
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
