<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class CsvReader
{
    /**
     * @param array{
     *     header?: bool|null,
     *     delimiter?: string|null,
     *     quote?: string|false|null,
     *     escape?: string|null,
     *     comment?: string|null,
     *     encoding?: string|null,
     *     blankLines?: 'skip'|'keep'|null
     * } $options
     */
    public function __construct(private readonly string $format = 'csv', private readonly array $options = [])
    {
    }

    public function read(string $source): AstNode
    {
        $decoded = $this->decodeSource($source);
        $source = $decoded['source'];
        $quote = $this->quoteChar();
        $escape = $this->escapeChar();
        $comment = $this->commentChar();
        [$source, $delimiter] = $this->sourceAndDelimiter($source, $quote, $escape, $comment);
        $parsed = $this->parseRows($source, $delimiter, $quote, $escape, $comment);
        $rowRecords = $parsed['rows'];
        $rows = $this->rowValues($rowRecords);
        $columnCount = $this->maxColumnCount($rows);
        $raggedRows = $this->raggedRows($rows, $columnCount);
        $diagnostics = array_merge($parsed['diagnostics'], $this->raggedDiagnostics($raggedRows));
        $rowRecords = $this->normalizeRowRecords($rowRecords, $columnCount);
        $rows = $this->rowValues($rowRecords);
        $hasHeader = (bool) ($this->options['header'] ?? true);
        $dataRows = $hasHeader ? array_slice($rows, 1) : $rows;
        $columnTypes = $this->inferColumnTypes($dataRows, $columnCount);
        $metadata = [
            'csvFormat' => $this->format,
            'csvDelimiter' => $delimiter,
            'csvQuote' => $quote,
            'csvEscape' => $escape,
            'csvComment' => $comment,
            'csvEncoding' => $decoded['encoding'],
            'csvRowCount' => count($rows),
            'csvColumnCount' => $columnCount,
            'csvDataRowCount' => $hasHeader ? max(0, count($rows) - 1) : count($rows),
            'csvHeader' => $hasHeader,
            'csvRaggedRowCount' => count($raggedRows),
            'csvRaggedRows' => $raggedRows,
            'csvColumnTypes' => $columnTypes,
            'csvDiagnosticCount' => count($diagnostics),
            'csvDiagnostics' => $diagnostics,
            'csvBlankLinePolicy' => $this->blankLinePolicy(),
            'csvSkippedBlankRows' => $parsed['skippedBlankRows'],
            'csvSkippedCommentRows' => $parsed['skippedCommentRows'],
            'csvSourceRows' => $this->rowSourceMetadata($rowRecords),
        ];

        if ($rows === []) {
            return new AstNode('document', ['meta' => $metadata], []);
        }

        $headRow = $hasHeader ? array_shift($rowRecords) : null;
        $tableChildren = [];
        if ($headRow !== null) {
            $tableChildren[] = new AstNode('table_head', [], [
                $this->tableRow($headRow, true),
            ]);
        }
        $tableChildren[] = new AstNode('table_body', [], array_map(
            fn (array $row): AstNode => $this->tableRow($row, false, $columnTypes),
            $rowRecords
        ));

        $metadata['csvHeaderColumnCount'] = $headRow === null ? 0 : count($headRow['cells']);

        return new AstNode('document', ['meta' => $metadata], [
            new AstNode('table', [
                'alignments' => array_fill(0, $columnCount, 'default'),
                'htmlAttributes' => [
                    'data-pandoc-source' => $this->format,
                    'data-csv-row-count' => (string) ($metadata['csvRowCount']),
                    'data-csv-column-count' => (string) $columnCount,
                ],
            ], $tableChildren),
        ]);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function sourceAndDelimiter(string $source, ?string $quote, ?string $escape, ?string $comment): array
    {
        if ($this->format === 'tsv') {
            return [$source, "\t"];
        }

        $configured = (string) ($this->options['delimiter'] ?? '');
        if (strlen($configured) === 1) {
            return [$source, $configured];
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $source);
        if (preg_match('/^sep=(.)\n/i', $normalized, $match) === 1) {
            return [substr($normalized, strlen($match[0])), $match[1]];
        }

        return [$source, $this->detectDelimiter($normalized, $quote, $escape, $comment)];
    }

    private function quoteChar(): ?string
    {
        if (array_key_exists('quote', $this->options)) {
            $quote = $this->options['quote'];
            if ($quote === false || $quote === null || $quote === '') {
                return null;
            }

            return is_string($quote) && strlen($quote) === 1 ? $quote : '"';
        }

        return $this->format === 'tsv' ? null : '"';
    }

    private function escapeChar(): ?string
    {
        $escape = (string) ($this->options['escape'] ?? '');

        return strlen($escape) === 1 ? $escape : null;
    }

    private function commentChar(): ?string
    {
        $comment = (string) ($this->options['comment'] ?? '');

        return strlen($comment) === 1 ? $comment : null;
    }

    /**
     * @return array{source:string,encoding:string}
     */
    private function decodeSource(string $source): array
    {
        $configured = strtoupper(str_replace(['_', '-'], '', (string) ($this->options['encoding'] ?? '')));
        if ($configured !== '') {
            $converted = @mb_convert_encoding($source, 'UTF-8', $configured);
            if (is_string($converted)) {
                return ['source' => $converted, 'encoding' => $configured];
            }
        }

        if (str_starts_with($source, "\xEF\xBB\xBF")) {
            return ['source' => substr($source, 3), 'encoding' => 'UTF-8'];
        }
        if (str_starts_with($source, "\xFF\xFE\x00\x00")) {
            return ['source' => mb_convert_encoding(substr($source, 4), 'UTF-8', 'UTF-32LE'), 'encoding' => 'UTF-32LE'];
        }
        if (str_starts_with($source, "\x00\x00\xFE\xFF")) {
            return ['source' => mb_convert_encoding(substr($source, 4), 'UTF-8', 'UTF-32BE'), 'encoding' => 'UTF-32BE'];
        }
        if (str_starts_with($source, "\xFF\xFE")) {
            return ['source' => mb_convert_encoding(substr($source, 2), 'UTF-8', 'UTF-16LE'), 'encoding' => 'UTF-16LE'];
        }
        if (str_starts_with($source, "\xFE\xFF")) {
            return ['source' => mb_convert_encoding(substr($source, 2), 'UTF-8', 'UTF-16BE'), 'encoding' => 'UTF-16BE'];
        }

        return ['source' => $source, 'encoding' => 'UTF-8'];
    }

    private function detectDelimiter(string $source, ?string $quote, ?string $escape, ?string $comment): string
    {
        $candidates = [',', ';', "\t", '|'];
        $best = ',';
        $bestScore = -1;
        $lines = $this->delimiterSampleLines($source, $comment);
        foreach ($candidates as $candidate) {
            $counts = [];
            $strayQuotes = 0;
            foreach ($lines as $line) {
                $shape = $this->delimiterLineShape($line, $candidate, $quote, $escape);
                $counts[] = $shape['fields'];
                $strayQuotes += $shape['strayQuotes'];
            }
            $positive = array_values(array_filter($counts, static fn (int $count): bool => $count > 1));
            if ($positive === []) {
                continue;
            }
            $frequency = array_count_values($counts);
            arsort($frequency);
            $commonColumns = (int) array_key_first($frequency);
            $commonRows = (int) reset($frequency);
            $score = ($commonRows * 1000)
                + ($commonColumns * 100)
                + (count($positive) * 10)
                - ((max($counts) - min($counts)) * 100)
                - ($strayQuotes * 1000);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }

        return $best;
    }

    /**
     * @return list<string>
     */
    private function delimiterSampleLines(string $source, ?string $comment): array
    {
        $lines = [];
        foreach (explode("\n", $source) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || stripos($trimmed, 'sep=') === 0) {
                continue;
            }
            if ($comment !== null && str_starts_with(ltrim($line, " \t"), $comment)) {
                continue;
            }

            $lines[] = $line;
            if (count($lines) >= 12) {
                break;
            }
        }

        return $lines;
    }

    /**
     * @return array{fields:int,strayQuotes:int}
     */
    private function delimiterLineShape(string $line, string $delimiter, ?string $quote, ?string $escape): array
    {
        $field = '';
        $quoted = false;
        $inQuote = false;
        $afterClosingQuote = false;
        $count = 1;
        $strayQuotes = 0;
        $length = strlen($line);

        for ($offset = 0; $offset < $length; $offset++) {
            $char = $line[$offset];
            if ($quote !== null && $inQuote) {
                if ($escape !== null && $char === $escape && $offset + 1 < $length) {
                    $offset++;
                    continue;
                }
                if ($char === $quote) {
                    if ($offset + 1 < $length && $line[$offset + 1] === $quote) {
                        $offset++;
                        continue;
                    }
                    $inQuote = false;
                    $afterClosingQuote = true;
                    continue;
                }
                continue;
            }

            if ($quote !== null && $char === $quote && trim($field) === '') {
                $field = '';
                $quoted = true;
                $inQuote = true;
                $afterClosingQuote = false;
                continue;
            }
            if ($quote !== null && $char === $quote) {
                $strayQuotes++;
            }

            if ($quote !== null && $quoted && $afterClosingQuote && ($char === ' ' || $char === "\t")) {
                continue;
            }

            if ($char === $delimiter) {
                $count++;
                $field = '';
                $quoted = false;
                $afterClosingQuote = false;
                continue;
            }

            $field .= $char;
            $afterClosingQuote = false;
        }

        if ($inQuote) {
            $strayQuotes++;
        }

        return ['fields' => $count, 'strayQuotes' => $strayQuotes];
    }

    public function readCsvFile(string $path): AstNode
    {
        $source = file_get_contents($path);
        if (!is_string($source)) {
            throw new \RuntimeException("Unable to read '{$path}'.");
        }

        return $this->read($source);
    }

    /**
     * @return array{
     *     rows:list<array{sourceRow:int,sourceLineStart:int,sourceLineEnd:int,cells:list<array{value:string,sourceLine:int,sourceColumn:int,sourceColumnIndex:int,missing?:bool}>}>,
     *     diagnostics:list<array<string,mixed>>,
     *     skippedBlankRows:int,
     *     skippedCommentRows:int
     * }
     */
    private function parseRows(string $source, string $delimiter, ?string $quote, ?string $escape, ?string $comment): array
    {
        $source = str_replace(["\r\n", "\r"], "\n", $source);
        if ($source === '') {
            return [
                'rows' => [],
                'diagnostics' => [],
                'skippedBlankRows' => 0,
                'skippedCommentRows' => 0,
            ];
        }

        $rows = [];
        $diagnostics = [];
        $row = [];
        $field = '';
        $quoted = false;
        $inQuote = false;
        $afterClosingQuote = false;
        $reportedTextAfterClosingQuote = false;
        $lastWasTerminator = false;
        $lineStart = true;
        $line = 1;
        $column = 1;
        $rowStartLine = 1;
        $fieldStartLine = 1;
        $fieldStartColumn = 1;
        $sourceRow = 1;
        $skippedBlankRows = 0;
        $skippedCommentRows = 0;
        $blankLinePolicy = $this->blankLinePolicy();
        $length = strlen($source);

        for ($offset = 0; $offset < $length; $offset++) {
            $char = $source[$offset];
            if ($comment !== null && !$inQuote && $lineStart && $char === $comment) {
                while ($offset < $length && $source[$offset] !== "\n") {
                    $offset++;
                }
                $row = [];
                $field = '';
                $quoted = false;
                $afterClosingQuote = false;
                $reportedTextAfterClosingQuote = false;
                $lastWasTerminator = true;
                $lineStart = true;
                $skippedCommentRows++;
                if ($offset < $length && $source[$offset] === "\n") {
                    $line++;
                    $column = 1;
                    $rowStartLine = $line;
                    $fieldStartLine = $line;
                    $fieldStartColumn = 1;
                    continue;
                }
                continue;
            }
            if ($quote !== null && $inQuote) {
                if ($escape !== null && $char === $escape && $offset + 1 < $length) {
                    $field .= $source[$offset + 1];
                    if ($source[$offset + 1] === "\n") {
                        $line++;
                        $column = 1;
                    } else {
                        $column++;
                    }
                    $offset++;
                    $column++;
                    continue;
                }
                if ($char === $quote) {
                    if ($offset + 1 < $length && $source[$offset + 1] === $quote) {
                        $field .= $quote;
                        $offset++;
                        $column += 2;
                        continue;
                    }
                    $inQuote = false;
                    $afterClosingQuote = true;
                    $column++;
                    continue;
                }
                $field .= $char;
                if ($char === "\n") {
                    $line++;
                    $column = 1;
                } else {
                    $column++;
                }
                continue;
            }

            if ($quote !== null && $char === $quote && trim($field) === '') {
                $field = '';
                $quoted = true;
                $inQuote = true;
                $afterClosingQuote = false;
                $reportedTextAfterClosingQuote = false;
                $lastWasTerminator = false;
                $lineStart = false;
                $column++;
                continue;
            }

            if ($quote !== null && $char === $quote) {
                $diagnostics[] = [
                    'type' => 'stray-quote',
                    'row' => $sourceRow,
                    'line' => $line,
                    'column' => $column,
                    'message' => 'Quote character appeared inside an unquoted field.',
                ];
            }

            if ($quote !== null && $quoted && $afterClosingQuote && ($char === ' ' || $char === "\t")) {
                $column++;
                continue;
            }

            if ($quote !== null && $quoted && $afterClosingQuote && !$reportedTextAfterClosingQuote && $char !== $delimiter && $char !== "\n") {
                $diagnostics[] = [
                    'type' => 'text-after-closing-quote',
                    'row' => $sourceRow,
                    'line' => $line,
                    'column' => $column,
                    'message' => 'Non-delimiter text appeared after a closing quote.',
                ];
                $reportedTextAfterClosingQuote = true;
            }

            if ($char === $delimiter) {
                $row[] = $this->fieldRecord($field, $quoted, $fieldStartLine, $fieldStartColumn, count($row) + 1);
                $field = '';
                $quoted = false;
                $afterClosingQuote = false;
                $reportedTextAfterClosingQuote = false;
                $lastWasTerminator = false;
                $lineStart = false;
                $column++;
                $fieldStartLine = $line;
                $fieldStartColumn = $column;
                continue;
            }

            if ($char === "\n") {
                $row[] = $this->fieldRecord($field, $quoted, $fieldStartLine, $fieldStartColumn, count($row) + 1);
                if ($this->isBlankRow($row) && $blankLinePolicy === 'skip') {
                    $skippedBlankRows++;
                } else {
                    $rows[] = [
                        'sourceRow' => $sourceRow,
                        'sourceLineStart' => $rowStartLine,
                        'sourceLineEnd' => $line,
                        'cells' => $row,
                    ];
                    $sourceRow++;
                }
                $row = [];
                $field = '';
                $quoted = false;
                $afterClosingQuote = false;
                $reportedTextAfterClosingQuote = false;
                $lastWasTerminator = true;
                $lineStart = true;
                $line++;
                $column = 1;
                $rowStartLine = $line;
                $fieldStartLine = $line;
                $fieldStartColumn = 1;
                continue;
            }

            $field .= $char;
            $afterClosingQuote = false;
            $lastWasTerminator = false;
            $column++;
            if ($char !== ' ' && $char !== "\t") {
                $lineStart = false;
            }
        }

        if ($inQuote) {
            $diagnostics[] = [
                'type' => 'unclosed-quote',
                'row' => $sourceRow,
                'line' => $fieldStartLine,
                'column' => $fieldStartColumn,
                'message' => 'Quoted field reached end of input without a closing quote.',
            ];
        }

        if (!$lastWasTerminator || $row !== [] || $field !== '') {
            $row[] = $this->fieldRecord($field, $quoted, $fieldStartLine, $fieldStartColumn, count($row) + 1);
            if ($this->isBlankRow($row) && $blankLinePolicy === 'skip') {
                $skippedBlankRows++;
            } else {
                $rows[] = [
                    'sourceRow' => $sourceRow,
                    'sourceLineStart' => $rowStartLine,
                    'sourceLineEnd' => $line,
                    'cells' => $row,
                ];
            }
        }

        return [
            'rows' => $rows,
            'diagnostics' => $diagnostics,
            'skippedBlankRows' => $skippedBlankRows,
            'skippedCommentRows' => $skippedCommentRows,
        ];
    }

    /**
     * @param list<list<string>> $rows
     */
    private function maxColumnCount(array $rows): int
    {
        $max = 0;
        foreach ($rows as $row) {
            $max = max($max, count($row));
        }

        return $max;
    }

    /**
     * @param list<array{sourceRow:int,sourceLineStart:int,sourceLineEnd:int,cells:list<array{value:string,sourceLine:int,sourceColumn:int,sourceColumnIndex:int,missing?:bool}>}> $rowRecords
     * @return list<list<string>>
     */
    private function rowValues(array $rowRecords): array
    {
        return array_map(
            static fn (array $row): array => array_map(
                static fn (array $cell): string => (string) $cell['value'],
                $row['cells']
            ),
            $rowRecords
        );
    }

    /**
     * @param list<array{sourceRow:int,sourceLineStart:int,sourceLineEnd:int,cells:list<array{value:string,sourceLine:int,sourceColumn:int,sourceColumnIndex:int,missing?:bool}>}> $rowRecords
     * @return list<array{row:int,lineStart:int,lineEnd:int,columnCount:int}>
     */
    private function rowSourceMetadata(array $rowRecords): array
    {
        return array_map(
            static fn (array $row): array => [
                'row' => $row['sourceRow'],
                'lineStart' => $row['sourceLineStart'],
                'lineEnd' => $row['sourceLineEnd'],
                'columnCount' => count($row['cells']),
            ],
            $rowRecords
        );
    }

    /**
     * @param list<list<string>> $rows
     * @return list<array{row:int,columns:int,expectedColumns:int}>
     */
    private function raggedRows(array $rows, int $columnCount): array
    {
        $raggedRows = [];
        foreach ($rows as $index => $row) {
            $columns = count($row);
            if ($columns !== $columnCount) {
                $raggedRows[] = [
                    'row' => $index + 1,
                    'columns' => $columns,
                    'expectedColumns' => $columnCount,
                ];
            }
        }

        return $raggedRows;
    }

    /**
     * @param list<array{row:int,columns:int,expectedColumns:int}> $raggedRows
     * @return list<array<string,mixed>>
     */
    private function raggedDiagnostics(array $raggedRows): array
    {
        return array_map(
            static fn (array $row): array => [
                'type' => 'ragged-row',
                'row' => $row['row'],
                'columns' => $row['columns'],
                'expectedColumns' => $row['expectedColumns'],
                'message' => 'CSV record has a different field count than the widest record.',
            ],
            $raggedRows
        );
    }

    /**
     * @param list<array{sourceRow:int,sourceLineStart:int,sourceLineEnd:int,cells:list<array{value:string,sourceLine:int,sourceColumn:int,sourceColumnIndex:int,missing?:bool}>}> $rows
     * @return list<array{sourceRow:int,sourceLineStart:int,sourceLineEnd:int,cells:list<array{value:string,sourceLine:int,sourceColumn:int,sourceColumnIndex:int,missing?:bool}>}>
     */
    private function normalizeRowRecords(array $rows, int $columnCount): array
    {
        return array_map(function (array $row) use ($columnCount): array {
            $cells = array_slice($row['cells'], 0, $columnCount);
            while (count($cells) < $columnCount) {
                $cells[] = [
                    'value' => '',
                    'sourceLine' => $row['sourceLineEnd'],
                    'sourceColumn' => count($cells) + 1,
                    'sourceColumnIndex' => count($cells) + 1,
                    'missing' => true,
                ];
            }
            $row['cells'] = $cells;

            return $row;
        }, $rows);
    }

    /**
     * @param list<list<string>> $rows
     * @return list<string>
     */
    private function inferColumnTypes(array $rows, int $columnCount): array
    {
        $types = [];
        for ($column = 0; $column < $columnCount; $column++) {
            $observed = [];
            foreach ($rows as $row) {
                $value = trim((string) ($row[$column] ?? ''));
                if ($value === '') {
                    continue;
                }
                $observed[] = $this->inferValueType($value);
            }
            if ($observed === []) {
                $types[] = 'empty';
                continue;
            }
            $unique = array_values(array_unique($observed));
            if ($unique === ['integer']) {
                $types[] = 'integer';
            } elseif (count(array_diff($unique, ['integer', 'number'])) === 0) {
                $types[] = 'number';
            } elseif ($unique === ['boolean']) {
                $types[] = 'boolean';
            } elseif ($unique === ['date']) {
                $types[] = 'date';
            } elseif (count(array_diff($unique, ['date', 'datetime'])) === 0) {
                $types[] = 'datetime';
            } else {
                $types[] = 'string';
            }
        }

        return $types;
    }

    private function inferValueType(string $value): string
    {
        if (preg_match('/^(?:true|false|yes|no)$/iu', $value) === 1) {
            return 'boolean';
        }
        if (preg_match('/^[+-]?\d+$/u', $value) === 1) {
            return 'integer';
        }
        if (preg_match('/^[+-]?(?:(?:\d+\.\d*)|(?:\.\d+)|(?:\d+))(?:[eE][+-]?\d+)?$/u', $value) === 1) {
            return 'number';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/u', $value) === 1) {
            return 'date';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}[T\s]\d{2}:\d{2}(?::\d{2})?(?:Z|[+-]\d{2}:?\d{2})?$/u', $value) === 1) {
            return 'datetime';
        }

        return 'string';
    }

    private function finalizeField(string $field, bool $quoted): string
    {
        if ($quoted) {
            return $field;
        }

        return trim($field, " \t");
    }

    /**
     * @return array{value:string,sourceLine:int,sourceColumn:int,sourceColumnIndex:int}
     */
    private function fieldRecord(string $field, bool $quoted, int $sourceLine, int $sourceColumn, int $sourceColumnIndex): array
    {
        return [
            'value' => $this->finalizeField($field, $quoted),
            'sourceLine' => $sourceLine,
            'sourceColumn' => $sourceColumn,
            'sourceColumnIndex' => $sourceColumnIndex,
        ];
    }

    /**
     * @param list<array{value:string,sourceLine:int,sourceColumn:int,sourceColumnIndex:int,missing?:bool}> $row
     */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $cell) {
            if ((string) $cell['value'] !== '') {
                return false;
            }
        }

        return true;
    }

    private function blankLinePolicy(): string
    {
        $policy = (string) ($this->options['blankLines'] ?? 'skip');

        return $policy === 'keep' ? 'keep' : 'skip';
    }

    /**
     * @param array{sourceRow:int,sourceLineStart:int,sourceLineEnd:int,cells:list<array{value:string,sourceLine:int,sourceColumn:int,sourceColumnIndex:int,missing?:bool}>} $row
     */
    private function tableRow(array $row, bool $header, array $columnTypes = []): AstNode
    {
        $index = 0;

        return new AstNode('table_row', [
            'htmlAttributes' => [
                'data-csv-source-row' => (string) $row['sourceRow'],
                'data-csv-source-line-start' => (string) $row['sourceLineStart'],
                'data-csv-source-line-end' => (string) $row['sourceLineEnd'],
            ],
        ], array_map(
            function (array $cell) use ($header, $columnTypes, &$index): AstNode {
                $type = !$header ? (string) ($columnTypes[$index] ?? '') : '';
                $index++;

                return $this->tableCell($cell, $header, $type);
            },
            $row['cells']
        ));
    }

    /**
     * @param array{value:string,sourceLine:int,sourceColumn:int,sourceColumnIndex:int,missing?:bool} $cell
     */
    private function tableCell(array $cell, bool $header, string $type = ''): AstNode
    {
        $value = (string) $cell['value'];
        $attrs = [
            'text' => $value,
            'header' => $header,
        ];
        $htmlAttributes = [
            'data-csv-source-line' => (string) $cell['sourceLine'],
            'data-csv-source-column' => (string) $cell['sourceColumn'],
            'data-csv-source-field' => (string) $cell['sourceColumnIndex'],
        ];
        if (!$header && $type !== '') {
            $htmlAttributes['data-csv-type'] = $type;
        }
        if (($cell['missing'] ?? false) === true) {
            $htmlAttributes['data-csv-missing-cell'] = 'true';
        }
        $attrs['htmlAttributes'] = $htmlAttributes;

        return new AstNode('table_cell', $attrs, [
            new AstNode('plain', ['text' => $value], $this->cellInlines($value)),
        ]);
    }

    /**
     * @return list<AstNode>
     */
    private function cellInlines(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $nodes = [];
        foreach (explode("\n", $value) as $index => $line) {
            if ($index > 0) {
                $nodes[] = new AstNode('linebreak');
            }
            if ($line !== '') {
                $nodes[] = new AstNode('text', ['text' => $line]);
            }
        }

        return $nodes;
    }
}
