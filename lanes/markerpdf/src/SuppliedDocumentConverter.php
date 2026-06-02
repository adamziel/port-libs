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

    public function __construct(
        ?PdfTextDocumentExtractor $textExtractor = null,
        ?CorePdfConverter $coreConverter = null,
        ?LayoutAnnotator $layoutAnnotator = null,
        ?LayoutOrderer $layoutOrderer = null,
        ?TableFormatter $tableFormatter = null,
        ?TableRecognizer $tableRecognizer = null,
        ?ConversionFinalizer $finalizer = null
    ) {
        $this->textExtractor = $textExtractor ?? new PdfTextDocumentExtractor();
        $this->coreConverter = $coreConverter ?? new CorePdfConverter();
        $this->layoutAnnotator = $layoutAnnotator ?? new LayoutAnnotator();
        $this->layoutOrderer = $layoutOrderer ?? new LayoutOrderer();
        $this->tableFormatter = $tableFormatter ?? new TableFormatter();
        $this->tableRecognizer = $tableRecognizer ?? new TableRecognizer();
        $this->finalizer = $finalizer ?? new ConversionFinalizer();
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
        $documentPageCount = $this->nullableIntOption($options, 'document_page_count') ?? count($pdftextPages);

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
            function (array $pages, array $context) use ($options, $settings, $extracted, $batchMultiplier): array {
                return $this->runSuppliedPipeline($pages, $context, $options, $settings, $extracted, (float) $batchMultiplier);
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
        float $batchMultiplier
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

        $layoutResults = $this->listOption($options, 'layout_results');
        if ($layoutResults !== []) {
            $layout = $this->layoutAnnotator->runWithSuppliedLayouts(
                $this->listOption($options, 'lowres_images'),
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

        $orderResults = $this->listOption($options, 'order_results');
        if ($orderResults !== []) {
            $ordered = $this->layoutOrderer->runWithSuppliedOrder(
                $this->listOption($options, 'order_images'),
                $pages,
                $orderResults,
                $batchMultiplier
            );
            $pages = $ordered['pages'];
            $metadata['order_plan'] = $ordered['plan'];
            $metadata['supplied_boundaries'][] = 'order';
        }

        $pages = $this->layoutOrderer->sortBlocksInReadingOrder($pages);

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
                $metadata['supplied_boundaries'][] = 'table-cell-routing';
            }
            $recognition = $this->tableRecognizer->formatRecognizedTables($recognizedTables, $tablePlan['image_sizes']);
            $markdownTables = $recognition['markdown_tables'];
            $metadata['table_plan'] = $this->tablePlanMetadata($tablePlan);
            $metadata['table_assigned_cells'] = $recognition['assigned_cells'];
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
