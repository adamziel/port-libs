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
        $dialect = $this->dialectProfile($format);
        $formatInference = $formatResolution['formatInference'];
        $hasHeader = $this->headerOption($options);

        $sourceText = $this->stripBom($text);
        $parse = $this->parseRowsWithDiagnostics($sourceText, $dialect);
        $rows = $parse['rows'];
        if ($rows === []) {
            $sourceAnalysis = $this->sourceAnalysis($sourceText, $delimiter, []);
            return new AstNode('document', [
                'sourceFormat' => $format,
                'delimitedText' => $this->reviewPacket($format, $dialect, [], [], $hasHeader, $formatInference, $sourceAnalysis, $parse['diagnostics'], $parse['metrics']),
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
            'delimitedText' => $this->reviewPacket($format, $dialect, $sourceHeader === null ? $rows : [$sourceHeader, ...$rows], $widths, $hasHeader, $formatInference, $sourceAnalysis, $parse['diagnostics'], $parse['metrics']),
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
        return $this->parseRowsWithDiagnostics($text, $this->dialectProfileForDelimiter($delimiter))['rows'];
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
            default => throw new \InvalidArgumentException("Unsupported delimited text format: {$format}; supported formats: csv, tsv, auto"),
        };
    }

    /**
     * @return array{delimiter:string, delimiterName:string, quote:string|null, escape:string|null}
     */
    private function dialectProfileForDelimiter(string $delimiter): array
    {
        return $delimiter === "\t" ? $this->dialectProfile('tsv') : $this->dialectProfile('csv');
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
    private function parseRowsWithDiagnostics(string $text, array $dialect): array
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
                if ($this->isLineBreak($char)) {
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

                if ($this->isLineBreak($char)) {
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

            if ($this->isLineBreak($char)) {
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
    private function reviewPacket(
        string $format,
        array $dialect,
        array $rows,
        array $widths,
        bool $hasHeader,
        array $formatInference,
        array $sourceAnalysis,
        array $parseDiagnostics,
        array $parseMetrics
    ): array
    {
        $rowCount = count($rows);
        $columnCount = $widths === [] ? 0 : max($widths);
        $raggedRows = [];
        foreach ($widths as $index => $width) {
            if ($width !== $columnCount) {
                $raggedRows[] = $index;
            }
        }
        $diagnostics = $this->reviewDiagnostics($hasHeader, $formatInference, $sourceAnalysis);
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
     * @param array<string, mixed> $formatInference
     * @param array{
     *     multilineQuotedRows:list<int>,
     *     trailingDelimiterRows:list<int>,
     *     unterminatedQuoteRow:int|null,
     *     partialFinalRecordRow:int|null,
     *     partialFinalRecordFieldCount:int|null
     * } $sourceAnalysis
     * @return list<array<string, mixed>>
     */
    private function reviewDiagnostics(bool $hasHeader, array $formatInference, array $sourceAnalysis): array
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
