<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DelimitedTextReader
{
    /**
     * @param array{header?:bool, extension?:string, sourcePath?:string} $options
     */
    public function readCsv(string $text, array $options = []): AstNode
    {
        return $this->read($text, 'csv', $options);
    }

    /**
     * @param array{header?:bool, extension?:string, sourcePath?:string} $options
     */
    public function readTsv(string $text, array $options = []): AstNode
    {
        return $this->read($text, 'tsv', $options);
    }

    /**
     * @param array{header?:bool, extension?:string, sourcePath?:string} $options
     */
    public function readAuto(string $text, array $options = []): AstNode
    {
        return $this->read($text, 'auto', $options);
    }

    /**
     * @param array{header?:bool, extension?:string, sourcePath?:string} $options
     */
    public function read(string $text, string $format = 'csv', array $options = []): AstNode
    {
        $formatResolution = $this->formatResolution($format, $text, $options);
        $format = $formatResolution['format'];
        $delimiter = $formatResolution['delimiter'];
        $formatInference = $formatResolution['formatInference'];
        $hasHeader = $this->headerOption($options);

        $parsedRows = $this->parseRowsWithMetadata($this->stripBom($text), $delimiter);
        $rows = $parsedRows['rows'];
        $sourceRowIndexes = $parsedRows['sourceRowIndexes'];
        $blankRows = $parsedRows['blankRows'];
        if ($rows === []) {
            return new AstNode('document', [
                'sourceFormat' => $format,
                'delimitedText' => $this->reviewPacket($format, $delimiter, [], [], [], $blankRows, $hasHeader, $formatInference),
            ]);
        }

        $widths = array_map('count', $rows);
        $columnCount = max($widths);
        $sourceHeader = $hasHeader ? array_shift($rows) : null;
        $tableRows = [];
        foreach ($rows as $row) {
            $tableRows[] = $this->tableRow($row, false, $columnCount);
        }

        $headRows = [];
        if ($sourceHeader !== null) {
            $headRows[] = $this->tableRow($sourceHeader, true, $columnCount);
        }

        $table = TableGeometry::withReviewPacket(new AstNode('table', [
            'sourceFormat' => $format,
            'alignments' => array_fill(0, $columnCount, 'default'),
            'delimitedText' => $this->reviewPacket($format, $delimiter, $sourceHeader === null ? $rows : [$sourceHeader, ...$rows], $widths, $sourceRowIndexes, $blankRows, $hasHeader, $formatInference),
            'columnNames' => $sourceHeader === null ? $this->generatedColumnLabels($columnCount) : $this->normalizedRow($sourceHeader, $columnCount),
        ], [
            new AstNode('table_head', [], $headRows),
            new AstNode('table_body', [], $tableRows),
        ]));

        return new AstNode('document', [
            'sourceFormat' => $format,
            'delimitedText' => $table->attr('delimitedText'),
        ], [$table]);
    }

    /**
     * @param array{header?:bool, extension?:string, sourcePath?:string} $options
     * @return array{format:string, delimiter:string, formatInference:array<string, mixed>}
     */
    private function formatResolution(string $format, string $text, array $options): array
    {
        $requestedFormat = strtolower(trim($format));
        if ($requestedFormat === '') {
            throw new \InvalidArgumentException('Delimited text format must not be empty; supported formats: csv, tsv, auto');
        }

        if ($requestedFormat === 'csv' || $requestedFormat === 'tsv') {
            return [
                'format' => $requestedFormat,
                'delimiter' => $this->delimiterForFormat($requestedFormat),
                'formatInference' => [
                    'requestedFormat' => $requestedFormat,
                    'selectedFormat' => $requestedFormat,
                    'source' => 'explicit',
                    'sourceValue' => $requestedFormat,
                    'confidence' => 'explicit',
                    'candidateScores' => [],
                ],
            ];
        }

        if ($requestedFormat !== 'auto') {
            throw new \InvalidArgumentException("Unsupported delimited text format: {$requestedFormat}; supported formats: csv, tsv, auto");
        }

        $extensionInference = $this->inferFormatFromOptions($options);
        if ($extensionInference !== null) {
            $inferredFormat = $extensionInference['format'];

            return [
                'format' => $inferredFormat,
                'delimiter' => $this->delimiterForFormat($inferredFormat),
                'formatInference' => [
                    'requestedFormat' => 'auto',
                    'selectedFormat' => $inferredFormat,
                    'source' => $extensionInference['source'],
                    'sourceValue' => $extensionInference['sourceValue'],
                    'confidence' => 'extension',
                    'candidateScores' => [],
                ],
            ];
        }

        $contentInference = $this->inferFormatFromContent($text);
        $inferredFormat = $contentInference['format'] ?? 'csv';

        return [
            'format' => $inferredFormat,
            'delimiter' => $this->delimiterForFormat($inferredFormat),
            'formatInference' => [
                'requestedFormat' => 'auto',
                'selectedFormat' => $inferredFormat,
                'source' => $contentInference['format'] === null ? 'default' : 'content',
                'sourceValue' => null,
                'confidence' => $contentInference['format'] === null ? 'low' : 'high',
                'candidateScores' => $contentInference['candidateScores'],
            ],
        ];
    }

    private function delimiterForFormat(string $format): string
    {
        return match ($format) {
            'csv' => ',',
            'tsv' => "\t",
            default => throw new \InvalidArgumentException("Unsupported delimited text format: {$format}; supported formats: csv, tsv, auto"),
        };
    }

    /**
     * @param array{header?:bool, extension?:string, sourcePath?:string} $options
     * @return array{format:string, source:string, sourceValue:string}|null
     */
    private function inferFormatFromOptions(array $options): ?array
    {
        if (array_key_exists('extension', $options)) {
            if (!is_string($options['extension'])) {
                throw new \InvalidArgumentException('Delimited text extension option must be a string');
            }

            $extension = trim($options['extension']);
            if ($extension !== '') {
                $format = PandocFormatRegistry::inferTabularDataFormatFromExtension($extension);
                if ($format !== null) {
                    return [
                        'format' => $format,
                        'source' => 'extension',
                        'sourceValue' => $extension,
                    ];
                }
            }
        }

        if (array_key_exists('sourcePath', $options)) {
            if (!is_string($options['sourcePath'])) {
                throw new \InvalidArgumentException('Delimited text sourcePath option must be a string');
            }

            $sourcePath = trim($options['sourcePath']);
            $extension = $sourcePath === '' ? '' : pathinfo($sourcePath, PATHINFO_EXTENSION);
            if ($extension !== '') {
                $format = PandocFormatRegistry::inferTabularDataFormatFromExtension($extension);
                if ($format !== null) {
                    return [
                        'format' => $format,
                        'source' => 'source-path',
                        'sourceValue' => $sourcePath,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @return array{format:string|null, candidateScores:array<string, array{rows:int, multicolumnRows:int, columnCount:int, fieldCount:int}>}
     */
    private function inferFormatFromContent(string $text): array
    {
        $text = $this->stripBom($text);
        $candidateScores = [
            'csv' => $this->scoreDelimitedRows($text, ','),
            'tsv' => $this->scoreDelimitedRows($text, "\t"),
        ];

        $csvScore = $this->formatScore($candidateScores['csv']);
        $tsvScore = $this->formatScore($candidateScores['tsv']);
        $format = null;
        if ($csvScore > $tsvScore && $candidateScores['csv']['multicolumnRows'] > 0) {
            $format = 'csv';
        }
        if ($tsvScore > $csvScore && $candidateScores['tsv']['multicolumnRows'] > 0) {
            $format = 'tsv';
        }

        return [
            'format' => $format,
            'candidateScores' => $candidateScores,
        ];
    }

    /**
     * @return array{rows:int, multicolumnRows:int, columnCount:int, fieldCount:int}
     */
    private function scoreDelimitedRows(string $text, string $delimiter): array
    {
        $rows = $this->parseRows($text, $delimiter);
        $widths = array_map('count', $rows);

        return [
            'rows' => count($rows),
            'multicolumnRows' => count(array_filter($widths, static fn (int $width): bool => $width > 1)),
            'columnCount' => $widths === [] ? 0 : max($widths),
            'fieldCount' => array_sum($widths),
        ];
    }

    /**
     * @param array{rows:int, multicolumnRows:int, columnCount:int, fieldCount:int} $score
     */
    private function formatScore(array $score): int
    {
        return ($score['multicolumnRows'] * 1000) + ($score['columnCount'] * 100) + $score['fieldCount'];
    }

    /**
     * @param array{header?:bool} $options
     */
    private function headerOption(array $options): bool
    {
        if (!array_key_exists('header', $options)) {
            return true;
        }

        if (!is_bool($options['header'])) {
            throw new \InvalidArgumentException('Delimited text header option must be a boolean');
        }

        return $options['header'];
    }

    /**
     * @return list<list<string>>
     */
    private function parseRows(string $text, string $delimiter): array
    {
        return $this->parseRowsWithMetadata($text, $delimiter)['rows'];
    }

    /**
     * @return array{rows:list<list<string>>, sourceRowIndexes:list<int>, blankRows:list<int>}
     */
    private function parseRowsWithMetadata(string $text, string $delimiter): array
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new \RuntimeException('Unable to open in-memory CSV stream');
        }

        fwrite($stream, $text);
        rewind($stream);

        $rows = [];
        $sourceRowIndexes = [];
        $blankRows = [];
        $sourceRowIndex = 0;
        while (($row = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
            if ($row === [null] || $row === false) {
                $blankRows[] = $sourceRowIndex;
                $sourceRowIndex++;
                continue;
            }

            $rows[] = array_map(
                static fn (?string $value): string => $value ?? '',
                $row
            );
            $sourceRowIndexes[] = $sourceRowIndex;
            $sourceRowIndex++;
        }

        fclose($stream);

        return [
            'rows' => $rows,
            'sourceRowIndexes' => $sourceRowIndexes,
            'blankRows' => $blankRows,
        ];
    }

    /**
     * @param list<string> $row
     */
    private function tableRow(array $row, bool $header, int $columnCount): AstNode
    {
        $cells = [];
        for ($column = 0; $column < $columnCount; $column++) {
            $text = $row[$column] ?? '';
            $cells[] = new AstNode('table_cell', [
                'header' => $header,
                'text' => $text,
                'sourceColumn' => $column,
            ], $text === '' ? [] : [new AstNode('plain', [], [new AstNode('text', ['text' => $text])])]);
        }

        return new AstNode('table_row', ['header' => $header], $cells);
    }

    /**
     * @param list<list<string>> $rows
     * @param list<int> $widths
     * @param list<int> $sourceRowIndexes
     * @param list<int> $blankRows
     * @return array<string, mixed>
     */
    private function reviewPacket(string $format, string $delimiter, array $rows, array $widths, array $sourceRowIndexes, array $blankRows, bool $hasHeader, array $formatInference): array
    {
        $rowCount = count($rows);
        $columnCount = $widths === [] ? 0 : max($widths);
        $raggedRows = [];
        foreach ($widths as $index => $width) {
            if ($width !== $columnCount) {
                $raggedRows[] = $index;
            }
        }

        return [
            'format' => $format,
            'delimiter' => $delimiter === "\t" ? 'tab' : $delimiter,
            'formatInference' => $formatInference,
            'headerRow' => $hasHeader && $rowCount > 0,
            'headerOption' => $hasHeader ? 'first-row' : 'none',
            'headerSource' => $hasHeader && $rowCount > 0 ? 'source-row-0' : 'generated',
            'rowCount' => $rowCount,
            'bodyRowCount' => $hasHeader ? max(0, $rowCount - 1) : $rowCount,
            'columnCount' => $columnCount,
            'columnNames' => $hasHeader && $rows !== []
                ? $this->normalizedRow($rows[0], $columnCount)
                : $this->generatedColumnLabels($columnCount),
            'fieldCount' => array_sum($widths),
            'minFieldCount' => $widths === [] ? 0 : min($widths),
            'maxFieldCount' => $columnCount,
            'raggedRowCount' => count($raggedRows),
            'raggedRows' => $raggedRows,
            'blankRowCount' => count($blankRows),
            'blankRows' => $blankRows,
            'rowWidthSummary' => $this->rowWidthSummary($rows, $widths, $sourceRowIndexes, $blankRows, $hasHeader),
            'diagnostics' => $this->reviewDiagnostics($hasHeader, $formatInference, $rows, $widths, $sourceRowIndexes, $blankRows),
            'upstreamEvidence' => [
                'denominator' => 2,
                'fixtures' => [
                    'test/command/01.csv',
                    'test/command/3533-rst-csv-tables.csv',
                ],
                'source' => 'lanes/pandoc/src/UpstreamRunnerDependencyAudit.php static extra-source inventory',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $formatInference
     * @return list<array{code:string, severity:string, message:string}>
     */
    private function reviewDiagnostics(bool $hasHeader, array $formatInference, array $rows, array $widths, array $sourceRowIndexes, array $blankRows): array
    {
        $diagnostics = [];
        if (!$hasHeader) {
            $diagnostics[] = [
                'code' => 'delimited-text-header-disabled',
                'severity' => 'info',
                'message' => 'No source header row was consumed; generated column labels are review metadata only.',
            ];
        }

        $source = $formatInference['source'] ?? 'explicit';
        if ($source !== 'explicit') {
            $selectedFormat = (string) ($formatInference['selectedFormat'] ?? 'csv');
            $diagnostics[] = [
                'code' => $source === 'default' ? 'delimited-text-format-defaulted' : 'delimited-text-format-inferred',
                'severity' => 'info',
                'message' => "Delimited text format resolved to {$selectedFormat} from {$source} evidence.",
            ];
        }

        $widthSummary = $this->rowWidthSummary($rows, $widths, $sourceRowIndexes, $blankRows, $hasHeader);
        if ($widthSummary['blankRowCount'] > 0) {
            $diagnostics[] = [
                'code' => 'delimited-text-blank-rows-skipped',
                'severity' => 'info',
                'message' => 'Blank source rows were skipped before table construction and preserved in review metadata.',
            ];
        }

        if ($widthSummary['trailingEmptyFieldRows'] !== []) {
            $diagnostics[] = [
                'code' => 'delimited-text-trailing-empty-fields-preserved',
                'severity' => 'info',
                'message' => 'Rows with trailing empty fields were preserved before table padding.',
            ];
        }

        if (!$widthSummary['relaxed']['consistent']) {
            $diagnostics[] = [
                'code' => 'delimited-text-row-widths-uneven',
                'severity' => 'warning',
                'message' => 'Delimited text rows have uneven field counts; shorter rows were padded in the native table.',
            ];
        }

        if ($widthSummary['header']['mismatchCount'] > 0) {
            $diagnostics[] = [
                'code' => 'delimited-text-header-width-mismatch',
                'severity' => 'warning',
                'message' => 'Header and body row field counts differ; column names were normalized to the padded table width.',
            ];
        }

        return $diagnostics;
    }

    /**
     * @param list<list<string>> $rows
     * @param list<int> $widths
     * @param list<int> $sourceRowIndexes
     * @param list<int> $blankRows
     * @return array<string, mixed>
     */
    private function rowWidthSummary(array $rows, array $widths, array $sourceRowIndexes, array $blankRows, bool $hasHeader): array
    {
        $rowCount = count($rows);
        $columnCount = $widths === [] ? 0 : max($widths);
        $expectedWidth = $widths[0] ?? 0;
        $strictMismatches = [];
        $headerMismatches = [];
        $relaxedPaddedRows = [];
        $trailingEmptyFieldRows = [];

        foreach ($widths as $index => $width) {
            $sourceRow = $sourceRowIndexes[$index] ?? $index;
            if ($index > 0 && $width !== $expectedWidth) {
                $strictMismatches[] = $this->rowWidthMismatch($index, $sourceRow, $hasHeader && $index === 0 ? 'header' : 'body', $width, $expectedWidth);
            }

            if ($hasHeader && $index > 0 && $width !== $expectedWidth) {
                $headerMismatches[] = $this->rowWidthMismatch($index, $sourceRow, 'body', $width, $expectedWidth);
            }

            if ($width < $columnCount) {
                $relaxedPaddedRows[] = $this->rowWidthMismatch($index, $sourceRow, $hasHeader && $index === 0 ? 'header' : 'body', $width, $columnCount);
            }

            $row = $rows[$index] ?? [];
            if ($row !== [] && $row[array_key_last($row)] === '') {
                $trailingEmptyFieldRows[] = $sourceRow;
            }
        }

        return [
            'rowWidths' => $widths,
            'sourceRowIndexes' => $sourceRowIndexes,
            'blankRowCount' => count($blankRows),
            'blankRows' => $blankRows,
            'trailingEmptyFieldRows' => $trailingEmptyFieldRows,
            'widthCounts' => $this->rowWidthCounts($widths),
            'strict' => [
                'policy' => $hasHeader && $rowCount > 0 ? 'header-row' : 'first-row',
                'expectedColumnCount' => $expectedWidth,
                'consistent' => $strictMismatches === [],
                'mismatchCount' => count($strictMismatches),
                'mismatches' => $strictMismatches,
            ],
            'relaxed' => [
                'policy' => 'pad-to-wide-row',
                'columnCount' => $columnCount,
                'consistent' => $widths === [] || min($widths) === $columnCount,
                'paddedRowCount' => count($relaxedPaddedRows),
                'paddedRows' => $relaxedPaddedRows,
            ],
            'header' => [
                'enabled' => $hasHeader,
                'headerColumnCount' => $hasHeader && $widths !== [] ? $widths[0] : 0,
                'dataColumnCounts' => $hasHeader ? array_slice($widths, 1) : $widths,
                'consistentWithBody' => $headerMismatches === [],
                'mismatchCount' => count($headerMismatches),
                'mismatches' => $headerMismatches,
            ],
        ];
    }

    /**
     * @return array{row:int, sourceRow:int, rowLabel:string, rowRole:string, actualFieldCount:int, expectedFieldCount:int, missingFields:int, extraFields:int}
     */
    private function rowWidthMismatch(int $row, int $sourceRow, string $rowRole, int $actual, int $expected): array
    {
        return [
            'row' => $row,
            'sourceRow' => $sourceRow,
            'rowLabel' => 'source-row-' . $sourceRow,
            'rowRole' => $rowRole,
            'actualFieldCount' => $actual,
            'expectedFieldCount' => $expected,
            'missingFields' => max(0, $expected - $actual),
            'extraFields' => max(0, $actual - $expected),
        ];
    }

    /**
     * @param list<int> $widths
     * @return list<array{width:int, count:int}>
     */
    private function rowWidthCounts(array $widths): array
    {
        $counts = [];
        foreach ($widths as $width) {
            $counts[$width] = ($counts[$width] ?? 0) + 1;
        }

        ksort($counts);
        $summary = [];
        foreach ($counts as $width => $count) {
            $summary[] = [
                'width' => (int) $width,
                'count' => $count,
            ];
        }

        return $summary;
    }

    private function stripBom(string $text): string
    {
        return str_starts_with($text, "\xEF\xBB\xBF") ? substr($text, 3) : $text;
    }

    /**
     * @return list<string>
     */
    private function generatedColumnLabels(int $columnCount): array
    {
        $labels = [];
        for ($index = 1; $index <= $columnCount; $index++) {
            $labels[] = 'column' . $index;
        }

        return $labels;
    }

    /**
     * @param list<string> $row
     * @return list<string>
     */
    private function normalizedRow(array $row, int $columnCount): array
    {
        $normalized = [];
        for ($column = 0; $column < $columnCount; $column++) {
            $normalized[] = $row[$column] ?? '';
        }

        return $normalized;
    }
}
