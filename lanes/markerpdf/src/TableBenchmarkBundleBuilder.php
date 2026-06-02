<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class TableBenchmarkBundleBuilder
{
    private TableScorer $scorer;

    public function __construct(?TableScorer $scorer = null)
    {
        $this->scorer = $scorer ?? new TableScorer();
    }

    /**
     * Native boundary for marker.benchmark.table score rows with tabled span-grid review metadata.
     *
     * @param list<array<string, mixed>> $tables
     * @return list<array<string, mixed>>
     */
    public function buildRows(array $tables): array
    {
        if ($tables === []) {
            throw new InvalidArgumentException('Table benchmark bundle requires at least one table row.');
        }

        $rows = [];
        foreach ($tables as $index => $table) {
            if (!is_array($table)) {
                throw new InvalidArgumentException("Table benchmark bundle row {$index} must be an array.");
            }

            $document = $this->requiredString($table, 'document', $index);
            $hypothesis = $this->hypothesisMarkdown($table, $index);
            $reference = $this->requiredString($table, 'reference', $index);
            $spanGrid = $this->optionalArray($table, 'span_grid', $index);
            $context = $this->optionalArray($table, 'context', $index);
            $hypothesisCells = $this->scorer->splitToCells($hypothesis);
            $referenceCells = $this->scorer->splitToCells($reference);
            $spanGridSummary = $this->spanGridSummary($spanGrid);
            $contextSummary = $this->contextSummary($context, $spanGridSummary);

            $row = [
                'document' => $document,
                'table_index' => $this->optionalInt($table, 'table_index', $index, 0),
                'score' => $this->scorer->scoreTable($hypothesis, $reference),
                'hypothesis_rows' => count($hypothesisCells),
                'hypothesis_cells' => $this->cellCount($hypothesisCells),
                'reference_rows' => count($referenceCells),
                'reference_cells' => $this->cellCount($referenceCells),
                'review_target' => 'table_ocr_span_grid_benchmark_format',
                'span_grid' => $spanGridSummary,
                'context' => $contextSummary,
                'markdown' => $hypothesis,
            ];

            if (array_key_exists('page_number', $table)) {
                $row['page_number'] = $this->optionalInt($table, 'page_number', $index, 0);
            }
            if (array_key_exists('elapsed', $table)) {
                $row['time'] = $this->optionalFloat($table, 'elapsed', $index);
            }
            if (isset($table['ocr_assignment']) && is_scalar($table['ocr_assignment'])) {
                $row['ocr_assignment'] = (string) $table['ocr_assignment'];
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param array{text?: string, metadata?: array<string, mixed>} $conversion
     * @param list<string>|array<int, string> $references
     * @param array{markdown_tables?: list<string>, document_pages?: int|float, elapsed?: int|float} $options
     * @return list<array<string, mixed>>
     */
    public function buildRowsFromConversion(string $document, array $conversion, array $references, array $options = []): array
    {
        $document = trim($document);
        if ($document === '') {
            throw new InvalidArgumentException('Table benchmark conversion document must not be empty.');
        }
        if ($references === []) {
            throw new InvalidArgumentException('Table benchmark conversion requires at least one reference table.');
        }

        $markdownTables = $this->optionalMarkdownTables($options, $conversion);
        $metadata = isset($conversion['metadata']) && is_array($conversion['metadata']) ? $conversion['metadata'] : [];
        $spanGridReviews = $this->listOfArrays($metadata['table_spanning_grid_review'] ?? []);
        $contextReviews = $this->listOfArrays($metadata['table_section_caption_review'] ?? []);
        $assignments = $metadata['table_ocr_grid_border_conflicts'] ?? [];
        $needsOcr = is_array($metadata['table_needs_ocr'] ?? null) ? array_values($metadata['table_needs_ocr']) : [];

        $tables = [];
        foreach ($markdownTables as $tableIndex => $markdown) {
            if (!array_key_exists($tableIndex, $references) || !is_string($references[$tableIndex]) || trim($references[$tableIndex]) === '') {
                throw new InvalidArgumentException("Missing table benchmark reference for table {$tableIndex}.");
            }

            $context = $contextReviews[$tableIndex] ?? [];
            $record = [
                'document' => $document,
                'table_index' => $tableIndex,
                'markdown' => $markdown,
                'reference' => $references[$tableIndex],
                'span_grid' => $spanGridReviews[$tableIndex] ?? [],
                'context' => $context,
            ];

            if (isset($context['page_number']) && is_numeric($context['page_number'])) {
                $record['page_number'] = (int) $context['page_number'];
            }
            if (array_key_exists('elapsed', $options)) {
                $record['elapsed'] = $options['elapsed'];
            }
            if (($needsOcr[$tableIndex] ?? false) === true) {
                $record['ocr_assignment'] = 'forced_ocr';
            }
            if (is_array($assignments) && isset($assignments[$tableIndex]) && is_array($assignments[$tableIndex]) && $assignments[$tableIndex] !== []) {
                $record['ocr_assignment'] = 'grid_border_review';
            }

            $tables[] = $record;
        }

        return $this->buildRows($tables);
    }

    /**
     * Native boundary for tabulate-compatible table score source rows.
     *
     * @param list<array<string, mixed>> $rows
     * @return array{score_headers: list<string>, score_rows: list<list<string|int|float>>}
     */
    public function outputTables(array $rows): array
    {
        if ($rows === []) {
            throw new InvalidArgumentException('Table benchmark output requires at least one row.');
        }

        $scoreRows = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException("Table benchmark output row {$index} must be an array.");
            }
            if (!isset($row['score']) || !is_numeric($row['score'])) {
                throw new InvalidArgumentException("Table benchmark output row {$index} is missing a numeric score.");
            }
            $spanGrid = isset($row['span_grid']) && is_array($row['span_grid']) ? $row['span_grid'] : [];
            $scoreRows[] = [
                (string) ($row['document'] ?? ''),
                (int) ($row['table_index'] ?? 0),
                (float) $row['score'],
                (int) ($row['reference_cells'] ?? 0),
                (int) ($row['hypothesis_cells'] ?? 0),
                implode(',', $this->stringList($spanGrid['header_ids'] ?? [])),
                $this->spanFlags($spanGrid),
            ];
        }

        return [
            'score_headers' => ['Document', 'Table', 'Score', 'Reference cells', 'Hypothesis cells', 'Header IDs', 'Span grid'],
            'score_rows' => $scoreRows,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function writeJsonRows(string $outputFile, array $rows): void
    {
        $outputFile = trim($outputFile);
        if ($outputFile === '') {
            throw new InvalidArgumentException('Table benchmark output file must not be empty.');
        }

        $this->outputTables($rows);

        try {
            $json = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Unable to encode markerPDF table benchmark rows as JSON.', previous: $exception);
        }

        if (@file_put_contents($outputFile, $json) === false) {
            throw new RuntimeException('Unable to write markerPDF table benchmark rows: ' . $outputFile);
        }
    }

    /**
     * @param array<string, mixed> $table
     */
    private function requiredString(array $table, string $key, int $index): string
    {
        $value = $table[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("Table benchmark bundle row {$index} is missing a non-empty {$key} string.");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $table
     */
    private function hypothesisMarkdown(array $table, int $index): string
    {
        if (isset($table['markdown']) && is_string($table['markdown']) && trim($table['markdown']) !== '') {
            return $table['markdown'];
        }
        if (isset($table['hypothesis']) && is_string($table['hypothesis']) && trim($table['hypothesis']) !== '') {
            return $table['hypothesis'];
        }

        throw new InvalidArgumentException("Table benchmark bundle row {$index} is missing markdown or hypothesis text.");
    }

    /**
     * @param array<string, mixed> $table
     * @return array<string, mixed>
     */
    private function optionalArray(array $table, string $key, int $index): array
    {
        if (!array_key_exists($key, $table) || $table[$key] === null) {
            return [];
        }
        if (!is_array($table[$key])) {
            throw new InvalidArgumentException("Table benchmark bundle row {$index} {$key} must be an array.");
        }

        return $table[$key];
    }

    /**
     * @param array<string, mixed> $table
     */
    private function optionalInt(array $table, string $key, int $index, int $default): int
    {
        if (!array_key_exists($key, $table) || $table[$key] === null) {
            return $default;
        }
        if (!is_int($table[$key]) && !is_float($table[$key]) && !(is_string($table[$key]) && preg_match('/^-?\d+$/', $table[$key]) === 1)) {
            throw new InvalidArgumentException("Table benchmark bundle row {$index} {$key} must be an integer.");
        }

        return (int) $table[$key];
    }

    /**
     * @param array<string, mixed> $table
     */
    private function optionalFloat(array $table, string $key, int $index): float
    {
        if (!is_int($table[$key]) && !is_float($table[$key]) && !(is_string($table[$key]) && is_numeric($table[$key]))) {
            throw new InvalidArgumentException("Table benchmark bundle row {$index} {$key} must be numeric.");
        }

        return (float) $table[$key];
    }

    /**
     * @param list<list<string>> $cells
     */
    private function cellCount(array $cells): int
    {
        return array_sum(array_map('count', $cells));
    }

    /**
     * @param array<string, mixed> $grid
     * @return array<string, mixed>
     */
    private function spanGridSummary(array $grid): array
    {
        $accessibility = isset($grid['accessibility_grid']) && is_array($grid['accessibility_grid']) ? $grid['accessibility_grid'] : [];
        $renderCells = $this->listOfArrays($grid['render_cells'] ?? []);
        $gridCells = $this->listOfArrays($grid['grid_cells'] ?? []);
        $headerCells = $this->listOfArrays($grid['header_cells'] ?? []);
        $dataCells = $this->listOfArrays($grid['data_cells'] ?? []);
        $cellspanCount = 0;
        $hasRowspan = false;
        $hasColspan = false;

        foreach ($renderCells as $cell) {
            $rowspan = (int) ($cell['rowspan'] ?? 1);
            $colspan = (int) ($cell['colspan'] ?? 1);
            if ($rowspan > 1 || $colspan > 1) {
                $cellspanCount++;
            }
            $hasRowspan = $hasRowspan || $rowspan > 1;
            $hasColspan = $hasColspan || $colspan > 1;
        }

        $headerIds = $this->stringList($accessibility['header_ids'] ?? []);
        if ($headerIds === []) {
            $headerIds = $this->stringList(array_map(static fn (array $cell): mixed => $cell['header_id'] ?? null, $headerCells));
        }

        return [
            'source_review_target' => isset($grid['review_target']) && is_scalar($grid['review_target']) ? (string) $grid['review_target'] : 'table_span_grid',
            'rows' => $this->intList($grid['rows'] ?? []),
            'cols' => $this->intList($grid['cols'] ?? []),
            'orientation' => isset($grid['orientation']) && is_scalar($grid['orientation']) ? (string) $grid['orientation'] : 'normal',
            'row_axis' => isset($grid['row_axis']) && is_scalar($grid['row_axis']) ? (string) $grid['row_axis'] : 'y',
            'col_axis' => isset($grid['col_axis']) && is_scalar($grid['col_axis']) ? (string) $grid['col_axis'] : 'x',
            'header_ids' => $headerIds,
            'render_cell_count' => count($renderCells),
            'grid_cell_count' => count($gridCells),
            'covered_cell_count' => count(array_filter($gridCells, static fn (array $cell): bool => ($cell['state'] ?? null) === 'covered')),
            'header_cell_count' => count($headerCells),
            'data_cell_count' => count($dataCells),
            'cellspan_count' => $cellspanCount,
            'has_rowspan' => $hasRowspan,
            'has_colspan' => $hasColspan,
            'accessibility_target' => isset($accessibility['review_target']) && is_scalar($accessibility['review_target'])
                ? (string) $accessibility['review_target']
                : null,
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $spanGridSummary
     * @return array<string, mixed>
     */
    private function contextSummary(array $context, array $spanGridSummary): array
    {
        $accessibility = isset($context['accessibility']) && is_array($context['accessibility']) ? $context['accessibility'] : [];
        $cellspanGrid = isset($accessibility['cellspan_header_grid']) && is_array($accessibility['cellspan_header_grid'])
            ? $accessibility['cellspan_header_grid']
            : [];

        return [
            'source_review_target' => isset($context['review_target']) && is_scalar($context['review_target']) ? (string) $context['review_target'] : 'table_span_grid',
            'table_id' => isset($accessibility['table_id']) && is_scalar($accessibility['table_id']) ? (string) $accessibility['table_id'] : null,
            'caption_id' => isset($cellspanGrid['caption_id']) && is_scalar($cellspanGrid['caption_id']) ? (string) $cellspanGrid['caption_id'] : null,
            'section_id' => isset($cellspanGrid['section_id']) && is_scalar($cellspanGrid['section_id']) ? (string) $cellspanGrid['section_id'] : null,
            'aria_labelledby' => isset($accessibility['aria_labelledby']) && is_scalar($accessibility['aria_labelledby']) ? (string) $accessibility['aria_labelledby'] : null,
            'aria_describedby' => isset($accessibility['aria_describedby']) && is_scalar($accessibility['aria_describedby']) ? (string) $accessibility['aria_describedby'] : null,
            'has_caption' => ($context['has_caption'] ?? false) === true,
            'has_section' => ($context['has_section'] ?? false) === true,
            'caption_bound' => ($cellspanGrid['caption_bound'] ?? false) === true,
            'header_ids' => $this->stringList($accessibility['header_ids'] ?? $spanGridSummary['header_ids'] ?? []),
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $conversion
     * @return list<string>
     */
    private function optionalMarkdownTables(array $options, array $conversion): array
    {
        if (isset($options['markdown_tables'])) {
            if (!is_array($options['markdown_tables']) || !array_is_list($options['markdown_tables'])) {
                throw new InvalidArgumentException('Table benchmark markdown_tables option must be a list.');
            }

            return array_map(static fn (mixed $table): string => (string) $table, $options['markdown_tables']);
        }

        $text = isset($conversion['text']) && is_string($conversion['text']) ? $conversion['text'] : '';
        $tables = $this->extractMarkdownTables($text);
        if ($tables === []) {
            throw new InvalidArgumentException('Table benchmark conversion does not contain Markdown table text.');
        }

        return $tables;
    }

    /**
     * @return list<string>
     */
    private function extractMarkdownTables(string $text): array
    {
        $tables = [];
        $current = [];
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            if ($this->looksLikeMarkdownTableLine($line)) {
                $current[] = rtrim($line);
                continue;
            }

            $this->flushMarkdownTable($tables, $current);
        }
        $this->flushMarkdownTable($tables, $current);

        return $tables;
    }

    /**
     * @param list<string> $tables
     * @param list<string> $current
     */
    private function flushMarkdownTable(array &$tables, array &$current): void
    {
        if (count($current) >= 2 && $this->hasMarkdownSeparator($current)) {
            $tables[] = implode("\n", $current);
        }
        $current = [];
    }

    /**
     * @param list<string> $rows
     */
    private function hasMarkdownSeparator(array $rows): bool
    {
        foreach ($rows as $row) {
            if (preg_match('/^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)+\|?\s*$/', $row) === 1) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeMarkdownTableLine(string $line): bool
    {
        $trimmed = trim($line);

        return $trimmed !== ''
            && str_contains($trimmed, '|')
            && !str_starts_with($trimmed, '<')
            && !str_starts_with($trimmed, '{');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listOfArrays(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        return array_values(array_filter($values, static fn (mixed $value): bool => is_array($value)));
    }

    /**
     * @return list<int>
     */
    private function intList(mixed $values): array
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

        return array_values(array_unique($out));
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $values): array
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
     * @param array<string, mixed> $spanGrid
     */
    private function spanFlags(array $spanGrid): string
    {
        $flags = [];
        if (($spanGrid['has_rowspan'] ?? false) === true) {
            $flags[] = 'rowspan';
        }
        if (($spanGrid['has_colspan'] ?? false) === true) {
            $flags[] = 'colspan';
        }
        if ((int) ($spanGrid['covered_cell_count'] ?? 0) > 0) {
            $flags[] = 'covered';
        }

        return $flags === [] ? 'plain' : implode('+', $flags);
    }
}
