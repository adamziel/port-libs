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
        $delimiter = match ($format) {
            'csv' => ',',
            'tsv' => "\t",
            default => throw new \InvalidArgumentException("Unsupported delimited text format: {$format}"),
        };
        $hasHeader = $this->headerOption($options);

        $sourceText = $this->stripBom($text);
        $rows = $this->parseRows($sourceText, $delimiter);
        if ($rows === []) {
            $sourceAnalysis = $this->sourceAnalysis($sourceText, $delimiter, []);
            return new AstNode('document', [
                'sourceFormat' => $format,
                'delimitedText' => $this->reviewPacket($format, $delimiter, [], [], $hasHeader, $sourceAnalysis),
            ]);
        }

        $widths = array_map('count', $rows);
        $columnCount = max($widths);
        $sourceAnalysis = $this->sourceAnalysis($sourceText, $delimiter, $widths);
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
            'delimitedText' => $this->reviewPacket($format, $delimiter, $sourceHeader === null ? $rows : [$sourceHeader, ...$rows], $widths, $hasHeader, $sourceAnalysis),
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
     * @return list<list<string>>
     */
    private function parseRows(string $text, string $delimiter): array
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new \RuntimeException('Unable to open in-memory CSV stream');
        }

        fwrite($stream, $text);
        rewind($stream);

        $rows = [];
        while (($row = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
            if ($row === [null] || $row === false) {
                continue;
            }

            $rows[] = array_map(
                static fn (?string $value): string => $value ?? '',
                $row
            );
        }

        fclose($stream);

        return $rows;
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
     * @param array{
     *     finalRecordTerminated:bool,
     *     multilineQuotedFieldCount:int,
     *     multilineQuotedRows:list<int>,
     *     quotedFieldNewlineCount:int,
     *     trailingDelimiterRows:list<int>,
     *     unterminatedQuoteRow:int|null,
     *     partialFinalRecordRow:int|null,
     *     partialFinalRecordFieldCount:int|null
     * } $sourceAnalysis
     * @return array<string, mixed>
     */
    private function reviewPacket(string $format, string $delimiter, array $rows, array $widths, bool $hasHeader, array $sourceAnalysis): array
    {
        $rowCount = count($rows);
        $columnCount = $widths === [] ? 0 : max($widths);
        $raggedRows = [];
        foreach ($widths as $index => $width) {
            if ($width !== $columnCount) {
                $raggedRows[] = $index;
            }
        }
        $diagnostics = $this->diagnostics($hasHeader, $sourceAnalysis);

        return [
            'format' => $format,
            'delimiter' => $delimiter === "\t" ? 'tab' : $delimiter,
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
            'finalRecordTerminated' => $sourceAnalysis['finalRecordTerminated'],
            'multilineQuotedFieldCount' => $sourceAnalysis['multilineQuotedFieldCount'],
            'multilineQuotedRows' => $sourceAnalysis['multilineQuotedRows'],
            'quotedFieldNewlineCount' => $sourceAnalysis['quotedFieldNewlineCount'],
            'trailingDelimiterRowCount' => count($sourceAnalysis['trailingDelimiterRows']),
            'trailingDelimiterRows' => $sourceAnalysis['trailingDelimiterRows'],
            'unterminatedQuoteAtEof' => $sourceAnalysis['unterminatedQuoteRow'] !== null,
            'unterminatedQuoteRow' => $sourceAnalysis['unterminatedQuoteRow'],
            'partialFinalRecord' => $sourceAnalysis['partialFinalRecordRow'] !== null,
            'partialFinalRecordRow' => $sourceAnalysis['partialFinalRecordRow'],
            'partialFinalRecordFieldCount' => $sourceAnalysis['partialFinalRecordFieldCount'],
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

    /**
     * @param list<int> $widths
     * @return array{
     *     finalRecordTerminated:bool,
     *     multilineQuotedFieldCount:int,
     *     multilineQuotedRows:list<int>,
     *     quotedFieldNewlineCount:int,
     *     trailingDelimiterRows:list<int>,
     *     unterminatedQuoteRow:int|null,
     *     partialFinalRecordRow:int|null,
     *     partialFinalRecordFieldCount:int|null
     * }
     */
    private function sourceAnalysis(string $text, string $delimiter, array $widths): array
    {
        $length = strlen($text);
        $recordIndex = 0;
        $recordHasContent = false;
        $atFieldStart = true;
        $inQuotes = false;
        $currentQuotedFieldHasNewline = false;
        $multilineQuotedFieldCount = 0;
        $quotedFieldNewlineCount = 0;
        $multilineQuotedRows = [];
        $trailingDelimiterRows = [];

        for ($offset = 0; $offset < $length; $offset++) {
            $char = $text[$offset];
            $next = $offset + 1 < $length ? $text[$offset + 1] : null;

            if ($inQuotes) {
                if ($char === '"') {
                    if ($next === '"') {
                        $offset++;
                        continue;
                    }

                    if ($currentQuotedFieldHasNewline) {
                        $multilineQuotedFieldCount++;
                        $currentQuotedFieldHasNewline = false;
                    }
                    $inQuotes = false;
                    $atFieldStart = false;
                    continue;
                }

                if ($this->isLineBreak($char)) {
                    $quotedFieldNewlineCount++;
                    $currentQuotedFieldHasNewline = true;
                    $multilineQuotedRows[$recordIndex] = true;
                    if ($char === "\r" && $next === "\n") {
                        $offset++;
                    }
                }

                continue;
            }

            if ($this->isLineBreak($char)) {
                if ($recordHasContent) {
                    $recordIndex++;
                }

                $recordHasContent = false;
                $atFieldStart = true;
                if ($char === "\r" && $next === "\n") {
                    $offset++;
                }
                continue;
            }

            $recordHasContent = true;

            if ($char === $delimiter) {
                if ($next === null || $this->isLineBreak($next)) {
                    $trailingDelimiterRows[$recordIndex] = true;
                }
                $atFieldStart = true;
                continue;
            }

            if ($char === '"' && $atFieldStart) {
                $inQuotes = true;
                $currentQuotedFieldHasNewline = false;
                continue;
            }

            $atFieldStart = false;
        }

        $finalRecordTerminated = $text === '' || $this->endsWithLineBreak($text);
        $unterminatedQuoteRow = $inQuotes && $recordHasContent ? $recordIndex : null;
        if ($inQuotes && $currentQuotedFieldHasNewline) {
            $multilineQuotedFieldCount++;
        }
        $partialFinalRecordRow = null;
        $partialFinalRecordFieldCount = null;
        if (!$finalRecordTerminated && $widths !== []) {
            $lastRow = count($widths) - 1;
            $columnCount = max($widths);
            if ($widths[$lastRow] < $columnCount) {
                $partialFinalRecordRow = $lastRow;
                $partialFinalRecordFieldCount = $widths[$lastRow];
            }
        }

        return [
            'finalRecordTerminated' => $finalRecordTerminated,
            'multilineQuotedFieldCount' => $multilineQuotedFieldCount,
            'multilineQuotedRows' => array_map('intval', array_keys($multilineQuotedRows)),
            'quotedFieldNewlineCount' => $quotedFieldNewlineCount,
            'trailingDelimiterRows' => array_map('intval', array_keys($trailingDelimiterRows)),
            'unterminatedQuoteRow' => $unterminatedQuoteRow,
            'partialFinalRecordRow' => $partialFinalRecordRow,
            'partialFinalRecordFieldCount' => $partialFinalRecordFieldCount,
        ];
    }

    /**
     * @param array{
     *     multilineQuotedRows:list<int>,
     *     trailingDelimiterRows:list<int>,
     *     unterminatedQuoteRow:int|null,
     *     partialFinalRecordRow:int|null,
     *     partialFinalRecordFieldCount:int|null
     * } $sourceAnalysis
     * @return list<array{code:string,severity:string,message:string,row?:int,rows?:list<int>,fieldCount?:int}>
     */
    private function diagnostics(bool $hasHeader, array $sourceAnalysis): array
    {
        $diagnostics = [];
        if (!$hasHeader) {
            $diagnostics[] = [
                'code' => 'delimited-text-header-disabled',
                'severity' => 'info',
                'message' => 'No source header row was consumed; generated column labels are review metadata only.',
            ];
        }

        if ($sourceAnalysis['multilineQuotedRows'] !== []) {
            $diagnostics[] = [
                'code' => 'delimited-text-multiline-quoted-field',
                'severity' => 'info',
                'message' => 'One or more quoted fields contain source line breaks and were read as logical records.',
                'rows' => $sourceAnalysis['multilineQuotedRows'],
            ];
        }

        if ($sourceAnalysis['trailingDelimiterRows'] !== []) {
            $diagnostics[] = [
                'code' => 'delimited-text-trailing-delimiter-empty-field',
                'severity' => 'info',
                'message' => 'One or more records end with a delimiter, producing an explicit empty final field.',
                'rows' => $sourceAnalysis['trailingDelimiterRows'],
            ];
        }

        if ($sourceAnalysis['unterminatedQuoteRow'] !== null) {
            $diagnostics[] = [
                'code' => 'delimited-text-unterminated-quote-eof',
                'severity' => 'warning',
                'message' => 'A quoted field reaches EOF before a closing quote; remaining source text is kept in that field.',
                'row' => $sourceAnalysis['unterminatedQuoteRow'],
            ];
        }

        if ($sourceAnalysis['partialFinalRecordRow'] !== null) {
            $diagnostics[] = [
                'code' => 'delimited-text-partial-final-record',
                'severity' => 'warning',
                'message' => 'The final unterminated source record has fewer fields than the widest parsed record and was padded in the table AST.',
                'row' => $sourceAnalysis['partialFinalRecordRow'],
                'fieldCount' => $sourceAnalysis['partialFinalRecordFieldCount'] ?? 0,
            ];
        }

        return $diagnostics;
    }

    private function isLineBreak(string $char): bool
    {
        return $char === "\n" || $char === "\r";
    }

    private function endsWithLineBreak(string $text): bool
    {
        return str_ends_with($text, "\n") || str_ends_with($text, "\r");
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
