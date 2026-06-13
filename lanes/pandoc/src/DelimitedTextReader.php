<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DelimitedTextReader
{
    /**
     * @param array{header?:bool} $options
     */
    public function readCsv(string $text, array $options = []): AstNode
    {
        return $this->read($text, 'csv', $options);
    }

    /**
     * @param array{header?:bool} $options
     */
    public function readTsv(string $text, array $options = []): AstNode
    {
        return $this->read($text, 'tsv', $options);
    }

    /**
     * @param array{header?:bool} $options
     */
    public function read(string $text, string $format = 'csv', array $options = []): AstNode
    {
        $format = strtolower(trim($format));
        $dialect = $this->dialectProfile($format);
        $delimiter = $dialect['delimiter'];
        $hasHeader = $this->headerOption($options);

        $parse = $this->parseRows($this->stripBom($text), $dialect);
        $rows = $parse['rows'];
        if ($rows === []) {
            return new AstNode('document', [
                'sourceFormat' => $format,
                'delimitedText' => $this->reviewPacket($format, $dialect, [], [], $hasHeader, $parse['diagnostics'], $parse['metrics']),
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
            'delimitedText' => $this->reviewPacket($format, $dialect, $sourceHeader === null ? $rows : [$sourceHeader, ...$rows], $widths, $hasHeader, $parse['diagnostics'], $parse['metrics']),
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
     * @return array{delimiter:string, delimiterName:string, quote:string|null, escape:string|null}
     */
    private function dialectProfile(string $format): array
    {
        return match ($format) {
            'csv' => [
                'delimiter' => ',',
                'delimiterName' => 'comma',
                'quote' => '"',
                'escape' => null,
            ],
            'tsv' => [
                'delimiter' => "\t",
                'delimiterName' => 'tab',
                'quote' => null,
                'escape' => null,
            ],
            default => throw new \InvalidArgumentException("Unsupported delimited text format: {$format}"),
        };
    }

    /**
     * @param array{delimiter:string, delimiterName:string, quote:string|null, escape:string|null} $dialect
     * @return array{
     *     rows:list<list<string>>,
     *     diagnostics:list<array{code:string, severity:string, message:string, row:int, column:int, offset:int}>,
     *     metrics:array{
     *         quotedFieldCount:int,
     *         doubledQuoteEscapeCount:int,
     *         escapedQuoteSequenceCount:int,
     *         quoteInUnquotedFieldCount:int,
     *         textAfterClosingQuoteCount:int,
     *         unclosedQuoteCount:int,
     *         quotedLineBreakCount:int,
     *         multilineFieldCount:int,
     *         partialRecordCount:int
     *     }
     * }
     */
    private function parseRows(string $text, array $dialect): array
    {
        $delimiter = $dialect['delimiter'];
        $quote = $dialect['quote'];
        $length = strlen($text);

        $rows = [];
        $diagnostics = [];
        $metrics = [
            'quotedFieldCount' => 0,
            'doubledQuoteEscapeCount' => 0,
            'escapedQuoteSequenceCount' => 0,
            'quoteInUnquotedFieldCount' => 0,
            'textAfterClosingQuoteCount' => 0,
            'unclosedQuoteCount' => 0,
            'quotedLineBreakCount' => 0,
            'multilineFieldCount' => 0,
            'partialRecordCount' => 0,
        ];
        $row = [];
        $field = '';
        $rowIndex = 0;
        $columnIndex = 0;
        $fieldStarted = false;
        $quotedField = false;
        $inQuotedField = false;
        $afterClosingQuote = false;
        $fieldHadQuotedLineBreak = false;
        $quotedFieldStartRow = 0;
        $quotedFieldStartColumn = 0;
        $quotedFieldStartOffset = 0;

        $finishField = static function () use (
            &$row,
            &$field,
            &$columnIndex,
            &$fieldStarted,
            &$quotedField,
            &$afterClosingQuote,
            &$fieldHadQuotedLineBreak,
            &$metrics
        ): void {
            $row[] = $field;
            if ($quotedField && $fieldHadQuotedLineBreak) {
                $metrics['multilineFieldCount']++;
            }

            $field = '';
            $columnIndex++;
            $fieldStarted = false;
            $quotedField = false;
            $afterClosingQuote = false;
            $fieldHadQuotedLineBreak = false;
        };

        $finishRow = static function () use (
            &$rows,
            &$row,
            &$field,
            &$rowIndex,
            &$columnIndex,
            &$fieldStarted,
            &$quotedField,
            &$afterClosingQuote,
            $finishField
        ): void {
            $hasPendingField = $fieldStarted || $field !== '' || $row !== [] || $quotedField || $afterClosingQuote;
            if ($hasPendingField) {
                $finishField();
                if (!(count($row) === 1 && $row[0] === '')) {
                    $rows[] = $row;
                }
            }

            $row = [];
            $rowIndex++;
            $columnIndex = 0;
        };

        for ($offset = 0; $offset < $length; $offset++) {
            $char = $text[$offset];
            $next = $offset + 1 < $length ? $text[$offset + 1] : '';

            if ($inQuotedField) {
                if ($char === "\r" || $char === "\n") {
                    $field .= "\n";
                    $metrics['quotedLineBreakCount']++;
                    $fieldHadQuotedLineBreak = true;
                    if ($char === "\r" && $next === "\n") {
                        $offset++;
                    }

                    continue;
                }

                if ($quote !== null && $char === '\\' && $next === $quote) {
                    $field .= '\\' . $quote;
                    $metrics['escapedQuoteSequenceCount']++;
                    $diagnostics[] = $this->diagnostic(
                        'delimited-text-backslash-quote-preserved',
                        'info',
                        'Backslash before a quote was preserved; this dialect only recognizes doubled quote escapes.',
                        $rowIndex,
                        $columnIndex,
                        $offset
                    );
                    $offset++;
                    continue;
                }

                if ($quote !== null && $char === $quote) {
                    if ($next === $quote) {
                        $field .= $quote;
                        $metrics['doubledQuoteEscapeCount']++;
                        $offset++;
                        continue;
                    }

                    $inQuotedField = false;
                    $afterClosingQuote = true;
                    continue;
                }

                $field .= $char;
                continue;
            }

            if ($afterClosingQuote) {
                if ($char === $delimiter) {
                    $finishField();
                    continue;
                }

                if ($char === "\r" || $char === "\n") {
                    $finishRow();
                    if ($char === "\r" && $next === "\n") {
                        $offset++;
                    }

                    continue;
                }

                if ($char === ' ' || $char === "\t") {
                    continue;
                }

                $field .= $char;
                $fieldStarted = true;
                $afterClosingQuote = false;
                $metrics['textAfterClosingQuoteCount']++;
                $diagnostics[] = $this->diagnostic(
                    'delimited-text-text-after-closing-quote',
                    'warning',
                    'Text after a closing quote was retained in the field for review.',
                    $rowIndex,
                    $columnIndex,
                    $offset
                );
                continue;
            }

            if ($char === $delimiter) {
                $finishField();
                continue;
            }

            if ($char === "\r" || $char === "\n") {
                $finishRow();
                if ($char === "\r" && $next === "\n") {
                    $offset++;
                }

                continue;
            }

            if ($quote !== null && $char === $quote) {
                if (!$fieldStarted && $field === '') {
                    $fieldStarted = true;
                    $quotedField = true;
                    $inQuotedField = true;
                    $quotedFieldStartRow = $rowIndex;
                    $quotedFieldStartColumn = $columnIndex;
                    $quotedFieldStartOffset = $offset;
                    $metrics['quotedFieldCount']++;
                    continue;
                }

                $field .= $char;
                $fieldStarted = true;
                $metrics['quoteInUnquotedFieldCount']++;
                $diagnostics[] = $this->diagnostic(
                    'delimited-text-quote-in-unquoted-field',
                    'warning',
                    'Quote character appeared inside an unquoted field and was retained literally.',
                    $rowIndex,
                    $columnIndex,
                    $offset
                );
                continue;
            }

            $field .= $char;
            $fieldStarted = true;
        }

        if ($inQuotedField) {
            $metrics['unclosedQuoteCount']++;
            $metrics['partialRecordCount']++;
            $diagnostics[] = $this->diagnostic(
                'delimited-text-unclosed-quoted-field',
                'warning',
                'A quoted field reached end of input before a closing quote; the partial record was preserved.',
                $quotedFieldStartRow,
                $quotedFieldStartColumn,
                $quotedFieldStartOffset
            );
        }

        $hasPendingRow = $fieldStarted || $field !== '' || $row !== [] || $quotedField || $afterClosingQuote;
        if ($hasPendingRow) {
            $finishRow();
        }

        return [
            'rows' => $rows,
            'diagnostics' => $diagnostics,
            'metrics' => $metrics,
        ];
    }

    /**
     * @return array{code:string, severity:string, message:string, row:int, column:int, offset:int}
     */
    private function diagnostic(string $code, string $severity, string $message, int $row, int $column, int $offset): array
    {
        return [
            'code' => $code,
            'severity' => $severity,
            'message' => $message,
            'row' => $row,
            'column' => $column,
            'offset' => $offset,
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
     * @param array{delimiter:string, delimiterName:string, quote:string|null, escape:string|null} $dialect
     * @param list<list<string>> $rows
     * @param list<int> $widths
     * @param list<array{code:string, severity:string, message:string, row:int, column:int, offset:int}> $parseDiagnostics
     * @param array{
     *     quotedFieldCount:int,
     *     doubledQuoteEscapeCount:int,
     *     escapedQuoteSequenceCount:int,
     *     quoteInUnquotedFieldCount:int,
     *     textAfterClosingQuoteCount:int,
     *     unclosedQuoteCount:int,
     *     quotedLineBreakCount:int,
     *     multilineFieldCount:int,
     *     partialRecordCount:int
     * } $parseMetrics
     * @return array<string, mixed>
     */
    private function reviewPacket(string $format, array $dialect, array $rows, array $widths, bool $hasHeader, array $parseDiagnostics, array $parseMetrics): array
    {
        $rowCount = count($rows);
        $columnCount = $widths === [] ? 0 : max($widths);
        $raggedRows = [];
        foreach ($widths as $index => $width) {
            if ($width !== $columnCount) {
                $raggedRows[] = $index;
            }
        }
        $diagnostics = $hasHeader ? [] : [[
            'code' => 'delimited-text-header-disabled',
            'severity' => 'info',
            'message' => 'No source header row was consumed; generated column labels are review metadata only.',
        ]];
        foreach ($parseDiagnostics as $diagnostic) {
            $diagnostics[] = $diagnostic;
        }

        return [
            'format' => $format,
            'delimiter' => $dialect['delimiter'] === "\t" ? 'tab' : $dialect['delimiter'],
            'delimiterName' => $dialect['delimiterName'],
            'quote' => $dialect['quote'],
            'escape' => $dialect['escape'],
            'dialect' => [
                'delimiter' => $dialect['delimiter'] === "\t" ? 'tab' : $dialect['delimiter'],
                'delimiterName' => $dialect['delimiterName'],
                'quote' => $dialect['quote'],
                'escape' => $dialect['escape'],
                'quoteMode' => $dialect['quote'] === null ? 'literal' : 'quoted-fields',
                'escapeMode' => $dialect['escape'] === null ? 'none' : 'escape-character',
            ],
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
            'quotedFieldCount' => $parseMetrics['quotedFieldCount'],
            'doubledQuoteEscapeCount' => $parseMetrics['doubledQuoteEscapeCount'],
            'escapedQuoteSequenceCount' => $parseMetrics['escapedQuoteSequenceCount'],
            'quoteInUnquotedFieldCount' => $parseMetrics['quoteInUnquotedFieldCount'],
            'textAfterClosingQuoteCount' => $parseMetrics['textAfterClosingQuoteCount'],
            'unclosedQuoteCount' => $parseMetrics['unclosedQuoteCount'],
            'quotedLineBreakCount' => $parseMetrics['quotedLineBreakCount'],
            'multilineFieldCount' => $parseMetrics['multilineFieldCount'],
            'partialRecordCount' => $parseMetrics['partialRecordCount'],
            'diagnosticCount' => count($diagnostics),
            'diagnostics' => $diagnostics,
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
