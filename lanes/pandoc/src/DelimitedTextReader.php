<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DelimitedTextReader
{
    private const CONTROL_CHARACTER_SAMPLE_LIMIT = 8;
    private const CONTROL_CHARACTER_SAMPLE_RADIUS = 4;

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
        $sourceRowIndexes = $parse['sourceRowIndexes'];
        $blankRows = $parse['blankRows'];
        $controlCharacters = $this->controlCharacterSummary($sourceText, $dialect, $options);
        if ($rows === []) {
            $sourceAnalysis = $this->sourceAnalysis($sourceText, $delimiter, []);
            return new AstNode('document', [
                'sourceFormat' => $format,
                'delimitedText' => $this->reviewPacket($format, $dialect, [], [], [], $blankRows, $hasHeader, $formatInference, $sourceAnalysis, $parse['diagnostics'], $parse['metrics'], $controlCharacters),
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
            'delimitedText' => $this->reviewPacket($format, $dialect, $sourceHeader === null ? $rows : [$sourceHeader, ...$rows], $widths, $sourceRowIndexes, $blankRows, $hasHeader, $formatInference, $sourceAnalysis, $parse['diagnostics'], $parse['metrics'], $controlCharacters),
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
     *     sourceRowIndexes:list<int>,
     *     blankRows:list<int>,
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
        $sourceRowIndexes = [];
        $blankRows = [];
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
            &$sourceRowIndexes,
            &$blankRows,
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
                    $sourceRowIndexes[] = $rowIndex;
                } else {
                    $blankRows[] = $rowIndex;
                }
            } else {
                $blankRows[] = $rowIndex;
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
            'sourceRowIndexes' => $sourceRowIndexes,
            'blankRows' => $blankRows,
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
     * @param list<int> $sourceRowIndexes
     * @param list<int> $blankRows
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
     * @param array<string, mixed> $controlCharacters
     * @return array<string, mixed>
     */
    private function reviewPacket(
        string $format,
        array $dialect,
        array $rows,
        array $widths,
        array $sourceRowIndexes,
        array $blankRows,
        bool $hasHeader,
        array $formatInference,
        array $sourceAnalysis,
        array $parseDiagnostics,
        array $parseMetrics,
        array $controlCharacters
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
        $rowWidthSummary = $this->rowWidthSummary($rows, $widths, $sourceRowIndexes, $blankRows, $hasHeader);
        $rowRepairSummary = $this->rowRepairSummary($rows, $widths, $sourceRowIndexes, $blankRows, $hasHeader);
        $controlCharacters = $this->annotateControlCharactersWithRepair($controlCharacters, $rowRepairSummary);
        $diagnostics = $this->reviewDiagnostics($hasHeader, $formatInference, $sourceAnalysis, $rowWidthSummary, $controlCharacters);
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
            'blankRowCount' => count($blankRows),
            'blankRows' => $blankRows,
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
            'rowWidthSummary' => $rowWidthSummary,
            'rowRepairSummary' => $rowRepairSummary,
            'controlCharacters' => $controlCharacters,
            'controlRepairSummary' => $controlCharacters['repairSummary'],
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
     * @param array<string, mixed> $rowWidthSummary
     * @param array<string, mixed> $controlCharacters
     * @return list<array<string, mixed>>
     */
    private function reviewDiagnostics(
        bool $hasHeader,
        array $formatInference,
        array $sourceAnalysis,
        array $rowWidthSummary,
        array $controlCharacters
    ): array
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

        if (($rowWidthSummary['blankRowCount'] ?? 0) > 0) {
            $diagnostics[] = [
                'code' => 'delimited-text-blank-rows-skipped',
                'severity' => 'info',
                'message' => 'Blank source rows were skipped before table construction and preserved in review metadata.',
            ];
        }

        if (($rowWidthSummary['trailingEmptyFieldRows'] ?? []) !== []) {
            $diagnostics[] = [
                'code' => 'delimited-text-trailing-empty-fields-preserved',
                'severity' => 'info',
                'message' => 'Rows with trailing empty fields were preserved before table padding.',
            ];
        }

        if (($rowWidthSummary['strict']['mismatchCount'] ?? 0) > 0) {
            $diagnostics[] = [
                'code' => 'delimited-text-strict-row-width-mismatch',
                'severity' => 'warning',
                'message' => 'Strict row-width policy would reject rows whose field counts differ from the first/header row.',
            ];
        }

        if (($rowWidthSummary['relaxed']['consistent'] ?? true) === false) {
            $diagnostics[] = [
                'code' => 'delimited-text-row-widths-uneven',
                'severity' => 'warning',
                'message' => 'Delimited text rows have uneven field counts; shorter rows were padded in the native table.',
            ];
        }

        if (($rowWidthSummary['header']['mismatchCount'] ?? 0) > 0) {
            $diagnostics[] = [
                'code' => 'delimited-text-header-width-mismatch',
                'severity' => 'warning',
                'message' => 'Header and body row field counts differ; column names were normalized to the padded table width.',
            ];
        }

        $controlCount = $controlCharacters['totalCount'] ?? 0;
        if (is_int($controlCount) && $controlCount > 0) {
            $diagnostic = [
                'code' => 'delimited-text-control-characters',
                'severity' => 'warning',
                'message' => "Delimited text input contains {$controlCount} non-structural control character(s) in fields; byte samples are bounded for review.",
                'controlCount' => $controlCount,
                'nulCount' => $controlCharacters['nulCount'] ?? 0,
                'sampleCount' => $controlCharacters['sampleCount'] ?? 0,
                'sampleLimit' => $controlCharacters['sampleLimit'] ?? self::CONTROL_CHARACTER_SAMPLE_LIMIT,
                'controlsInChangedRows' => $controlCharacters['repairSummary']['controlsInChangedRows'] ?? 0,
            ];
            if (($controlCharacters['sourcePath'] ?? null) !== null) {
                $diagnostic['sourcePath'] = $controlCharacters['sourcePath'];
            }

            $diagnostics[] = $diagnostic;
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
            $rowRole = $hasHeader && $index === 0 ? 'header' : 'body';
            if ($index > 0 && $width !== $expectedWidth) {
                $strictMismatches[] = $this->rowWidthMismatch($index, $sourceRow, $rowRole, $width, $expectedWidth);
            }

            if ($hasHeader && $index > 0 && $width !== $expectedWidth) {
                $headerMismatches[] = $this->rowWidthMismatch($index, $sourceRow, 'body', $width, $expectedWidth);
            }

            if ($width < $columnCount) {
                $relaxedPaddedRows[] = $this->rowWidthMismatch($index, $sourceRow, $rowRole, $width, $columnCount);
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
     * @param list<list<string>> $rows
     * @param list<int> $widths
     * @param list<int> $sourceRowIndexes
     * @param list<int> $blankRows
     * @return array<string, mixed>
     */
    private function rowRepairSummary(array $rows, array $widths, array $sourceRowIndexes, array $blankRows, bool $hasHeader): array
    {
        $columnCount = $widths === [] ? 0 : max($widths);
        $rowsSummary = [];
        $paddedRows = [];
        $truncatedRows = [];

        foreach ($widths as $index => $width) {
            $sourceRow = $sourceRowIndexes[$index] ?? $index;
            $rowRole = $hasHeader && $index === 0 ? 'header' : 'body';
            $missingFields = max(0, $columnCount - $width);
            $extraFields = max(0, $width - $columnCount);
            $repair = $missingFields > 0 ? 'padded' : ($extraFields > 0 ? 'truncated' : 'unchanged');
            $rowSummary = [
                'row' => $index,
                'sourceRow' => $sourceRow,
                'rowLabel' => 'source-row-' . $sourceRow,
                'rowRole' => $rowRole,
                'repair' => $repair,
                'originalColumnCount' => $width,
                'repairedColumnCount' => $columnCount,
                'missingFieldsAdded' => $missingFields,
                'extraFieldsDropped' => $extraFields,
            ];
            $rowsSummary[] = $rowSummary;
            if ($repair === 'padded') {
                $paddedRows[] = $rowSummary;
            }
            if ($repair === 'truncated') {
                $truncatedRows[] = $rowSummary;
            }
        }

        return [
            'policy' => 'relaxed-pad-to-wide-row',
            'strictPolicy' => $hasHeader && $widths !== [] ? 'header-row' : 'first-row',
            'originalColumnCounts' => $widths,
            'repairedColumnCount' => $columnCount,
            'sourceRowIndexes' => $sourceRowIndexes,
            'blankRowCount' => count($blankRows),
            'blankRows' => $blankRows,
            'changedRowCount' => count($paddedRows) + count($truncatedRows),
            'paddedRowCount' => count($paddedRows),
            'truncatedRowCount' => count($truncatedRows),
            'paddedRows' => $paddedRows,
            'truncatedRows' => $truncatedRows,
            'rows' => $rowsSummary,
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

    /**
     * @param array{delimiter:string, delimiterName:string, quote:string|null, escape:string|null} $dialect
     * @param array{sourcePath?:string} $options
     * @return array<string, mixed>
     */
    private function controlCharacterSummary(string $text, array $dialect, array $options): array
    {
        $delimiter = $dialect['delimiter'];
        $quote = $dialect['quote'];
        $sourcePath = $this->sourcePathContext($options);
        $samples = [];
        $byCodepoint = [];
        $sourceRows = [];
        $fieldQuoteBuckets = [
            'quoted' => [
                'controlCount' => 0,
                'sampleCount' => 0,
                'sampleIndexes' => [],
            ],
            'unquoted' => [
                'controlCount' => 0,
                'sampleCount' => 0,
                'sampleIndexes' => [],
            ],
        ];
        $totalCount = 0;
        $nulCount = 0;
        $rowIndex = 0;
        $columnIndex = 0;
        $fieldStarted = false;
        $fieldQuoted = false;
        $inQuotedField = false;
        $length = strlen($text);

        for ($offset = 0; $offset < $length; $offset++) {
            $byte = $text[$offset];

            if ($quote !== null && $byte === $quote) {
                if (!$fieldStarted) {
                    $fieldStarted = true;
                    $fieldQuoted = true;
                    $inQuotedField = true;
                    continue;
                }

                if ($inQuotedField) {
                    if (($text[$offset + 1] ?? '') === $quote) {
                        $offset++;
                        continue;
                    }

                    $inQuotedField = false;
                    continue;
                }
            }

            if (!$inQuotedField && $byte === $delimiter) {
                $columnIndex++;
                $fieldStarted = false;
                $fieldQuoted = false;
                continue;
            }

            if (!$inQuotedField && ($byte === "\n" || $byte === "\r")) {
                if ($byte === "\r" && ($text[$offset + 1] ?? '') === "\n") {
                    $offset++;
                }

                $rowIndex++;
                $columnIndex = 0;
                $fieldStarted = false;
                $fieldQuoted = false;
                continue;
            }

            $fieldStarted = true;
            $ordinal = ord($byte);
            if (!$this->isReviewableControlByte($ordinal)) {
                continue;
            }

            $totalCount++;
            if ($ordinal === 0) {
                $nulCount++;
            }

            $quoteBucket = $fieldQuoted ? 'quoted' : 'unquoted';
            $fieldQuoteBuckets[$quoteBucket]['controlCount']++;
            $codepoint = sprintf('U+%04X', $ordinal);
            $byCodepoint[$codepoint] = ($byCodepoint[$codepoint] ?? 0) + 1;

            $rowKey = (string) $rowIndex;
            if (!isset($sourceRows[$rowKey])) {
                $sourceRows[$rowKey] = [
                    'sourceRow' => $rowIndex,
                    'rowLabel' => 'source-row-' . $rowIndex,
                    'controlCount' => 0,
                    'quotedControlCount' => 0,
                    'unquotedControlCount' => 0,
                    'columns' => [],
                    'byCodepoint' => [],
                ];
            }
            $sourceRows[$rowKey]['controlCount']++;
            $sourceRows[$rowKey][$fieldQuoted ? 'quotedControlCount' : 'unquotedControlCount']++;
            $sourceRows[$rowKey]['byCodepoint'][$codepoint] = ($sourceRows[$rowKey]['byCodepoint'][$codepoint] ?? 0) + 1;
            if (!in_array($columnIndex, $sourceRows[$rowKey]['columns'], true)) {
                $sourceRows[$rowKey]['columns'][] = $columnIndex;
            }

            if (count($samples) >= self::CONTROL_CHARACTER_SAMPLE_LIMIT) {
                continue;
            }

            $sampleIndex = count($samples);
            $samples[] = [
                'sampleIndex' => $sampleIndex,
                'rowIndex' => $rowIndex,
                'rowNumber' => $rowIndex + 1,
                'sourceRow' => $rowIndex,
                'sourceRowNumber' => $rowIndex + 1,
                'columnIndex' => $columnIndex,
                'columnNumber' => $columnIndex + 1,
                'sourceColumn' => $columnIndex,
                'sourceColumnNumber' => $columnIndex + 1,
                'fieldQuoted' => $fieldQuoted,
                'quoteBucket' => $quoteBucket,
                'byteOffset' => $offset,
                'byteHex' => sprintf('%02X', $ordinal),
                'codepoint' => $codepoint,
                'name' => $this->controlByteName($ordinal),
                'byteSampleHex' => $this->byteSampleHex($text, $offset),
                'textSample' => $this->escapedTextSample($text, $offset),
                'positionBeforeRepair' => [
                    'sourceRow' => $rowIndex,
                    'sourceRowNumber' => $rowIndex + 1,
                    'sourceColumn' => $columnIndex,
                    'sourceColumnNumber' => $columnIndex + 1,
                ],
            ];
            $fieldQuoteBuckets[$quoteBucket]['sampleCount']++;
            $fieldQuoteBuckets[$quoteBucket]['sampleIndexes'][] = $sampleIndex;
        }

        ksort($byCodepoint);
        ksort($sourceRows, SORT_NUMERIC);
        foreach ($sourceRows as &$sourceRow) {
            sort($sourceRow['columns']);
            ksort($sourceRow['byCodepoint']);
        }
        unset($sourceRow);

        return [
            'policy' => 'report-c0-del-controls-except-ht-lf-cr',
            'sourcePath' => $sourcePath,
            'totalCount' => $totalCount,
            'nulCount' => $nulCount,
            'quotedFieldCount' => $fieldQuoteBuckets['quoted']['controlCount'],
            'unquotedFieldCount' => $fieldQuoteBuckets['unquoted']['controlCount'],
            'fieldQuoteBuckets' => $fieldQuoteBuckets,
            'sampleLimit' => self::CONTROL_CHARACTER_SAMPLE_LIMIT,
            'sampleCount' => count($samples),
            'truncated' => $totalCount > count($samples),
            'byCodepoint' => $byCodepoint,
            'sourceRows' => array_values($sourceRows),
            'samples' => $samples,
        ];
    }

    /**
     * @param array<string, mixed> $controlCharacters
     * @param array<string, mixed> $rowRepairSummary
     * @return array<string, mixed>
     */
    private function annotateControlCharactersWithRepair(array $controlCharacters, array $rowRepairSummary): array
    {
        $repairRowsBySource = [];
        foreach ($rowRepairSummary['rows'] ?? [] as $row) {
            if (is_array($row) && isset($row['sourceRow'])) {
                $repairRowsBySource[(string) $row['sourceRow']] = $row;
            }
        }

        $samples = [];
        foreach ($controlCharacters['samples'] ?? [] as $sample) {
            if (!is_array($sample)) {
                continue;
            }

            $sourceRow = $sample['sourceRow'] ?? $sample['rowIndex'] ?? null;
            $sourceColumn = $sample['sourceColumn'] ?? $sample['columnIndex'] ?? null;
            $repairRow = is_int($sourceRow) ? ($repairRowsBySource[(string) $sourceRow] ?? null) : null;
            if (is_array($repairRow)) {
                $sample['rowRepair'] = $this->controlSampleRepairContext($repairRow);
                $sample['positionAfterRepair'] = [
                    'row' => $repairRow['row'],
                    'rowNumber' => $repairRow['row'] + 1,
                    'sourceRow' => $repairRow['sourceRow'],
                    'sourceRowNumber' => $repairRow['sourceRow'] + 1,
                    'columnIndex' => $sourceColumn,
                    'columnNumber' => is_int($sourceColumn) ? $sourceColumn + 1 : null,
                    'columnWithinRepairedWidth' => is_int($sourceColumn)
                        && $sourceColumn < $repairRow['repairedColumnCount'],
                ];
            } else {
                $sample['rowRepair'] = [
                    'repair' => 'unmapped',
                    'rowRole' => 'unknown',
                    'rowLabel' => is_int($sourceRow) ? 'source-row-' . $sourceRow : 'unknown',
                    'originalColumnCount' => 0,
                    'repairedColumnCount' => 0,
                    'missingFieldsAdded' => 0,
                    'extraFieldsDropped' => 0,
                ];
                $sample['positionAfterRepair'] = null;
            }

            $samples[] = $sample;
        }

        $controlCharacters['samples'] = $samples;
        $controlCharacters['repairSummary'] = $this->controlRepairSummary($controlCharacters, $repairRowsBySource);

        return $controlCharacters;
    }

    /**
     * @param array<string, mixed> $repairRow
     * @return array<string, mixed>
     */
    private function controlSampleRepairContext(array $repairRow): array
    {
        return [
            'row' => $repairRow['row'],
            'sourceRow' => $repairRow['sourceRow'],
            'rowLabel' => $repairRow['rowLabel'],
            'rowRole' => $repairRow['rowRole'],
            'repair' => $repairRow['repair'],
            'originalColumnCount' => $repairRow['originalColumnCount'],
            'repairedColumnCount' => $repairRow['repairedColumnCount'],
            'missingFieldsAdded' => $repairRow['missingFieldsAdded'],
            'extraFieldsDropped' => $repairRow['extraFieldsDropped'],
        ];
    }

    /**
     * @param array<string, mixed> $controlCharacters
     * @param array<string, array<string, mixed>> $repairRowsBySource
     * @return array<string, mixed>
     */
    private function controlRepairSummary(array $controlCharacters, array $repairRowsBySource): array
    {
        $byRepair = [
            'padded' => 0,
            'unchanged' => 0,
            'truncated' => 0,
            'unmapped' => 0,
        ];
        $sourceRows = [];

        foreach ($controlCharacters['sourceRows'] ?? [] as $sourceRowControls) {
            if (!is_array($sourceRowControls)) {
                continue;
            }

            $sourceRow = $sourceRowControls['sourceRow'] ?? null;
            $controlCount = $sourceRowControls['controlCount'] ?? 0;
            $repairRow = is_int($sourceRow) ? ($repairRowsBySource[(string) $sourceRow] ?? null) : null;
            $repair = is_array($repairRow) ? (string) $repairRow['repair'] : 'unmapped';
            if (!array_key_exists($repair, $byRepair)) {
                $byRepair[$repair] = 0;
            }
            if (is_int($controlCount)) {
                $byRepair[$repair] += $controlCount;
            }

            $sourceRows[] = [
                'sourceRow' => $sourceRow,
                'rowLabel' => is_array($repairRow) ? $repairRow['rowLabel'] : $sourceRowControls['rowLabel'],
                'rowRole' => is_array($repairRow) ? $repairRow['rowRole'] : 'unknown',
                'repair' => $repair,
                'controlCount' => $controlCount,
                'quotedControlCount' => $sourceRowControls['quotedControlCount'] ?? 0,
                'unquotedControlCount' => $sourceRowControls['unquotedControlCount'] ?? 0,
                'columns' => $sourceRowControls['columns'] ?? [],
                'originalColumnCount' => is_array($repairRow) ? $repairRow['originalColumnCount'] : 0,
                'repairedColumnCount' => is_array($repairRow) ? $repairRow['repairedColumnCount'] : 0,
                'missingFieldsAdded' => is_array($repairRow) ? $repairRow['missingFieldsAdded'] : 0,
                'extraFieldsDropped' => is_array($repairRow) ? $repairRow['extraFieldsDropped'] : 0,
            ];
        }

        return [
            'policy' => 'annotate-controls-after-relaxed-row-repair',
            'sourcePath' => $controlCharacters['sourcePath'] ?? null,
            'controlCount' => $controlCharacters['totalCount'] ?? 0,
            'sampleCount' => $controlCharacters['sampleCount'] ?? 0,
            'controlsInChangedRows' => $byRepair['padded'] + $byRepair['truncated'],
            'controlsInPaddedRows' => $byRepair['padded'],
            'controlsInTruncatedRows' => $byRepair['truncated'],
            'controlsInUnchangedRows' => $byRepair['unchanged'],
            'controlsInUnmappedRows' => $byRepair['unmapped'],
            'byRepair' => $byRepair,
            'sourceRows' => $sourceRows,
        ];
    }

    /**
     * @param array{sourcePath?:string} $options
     */
    private function sourcePathContext(array $options): ?string
    {
        if (!array_key_exists('sourcePath', $options)) {
            return null;
        }

        if (!is_string($options['sourcePath'])) {
            throw new \InvalidArgumentException('Delimited text sourcePath option must be a string');
        }

        $sourcePath = trim($options['sourcePath']);

        return $sourcePath === '' ? null : $sourcePath;
    }

    private function isReviewableControlByte(int $ordinal): bool
    {
        if ($ordinal === 9 || $ordinal === 10 || $ordinal === 13) {
            return false;
        }

        return $ordinal < 32 || $ordinal === 127;
    }

    private function byteSampleHex(string $text, int $offset): string
    {
        $start = max(0, $offset - self::CONTROL_CHARACTER_SAMPLE_RADIUS);
        $sample = substr($text, $start, (self::CONTROL_CHARACTER_SAMPLE_RADIUS * 2) + 1);
        $bytes = [];
        $length = strlen($sample);
        for ($index = 0; $index < $length; $index++) {
            $bytes[] = sprintf('%02X', ord($sample[$index]));
        }

        return implode(' ', $bytes);
    }

    private function escapedTextSample(string $text, int $offset): string
    {
        $start = max(0, $offset - self::CONTROL_CHARACTER_SAMPLE_RADIUS);
        $sample = substr($text, $start, (self::CONTROL_CHARACTER_SAMPLE_RADIUS * 2) + 1);
        $escaped = '';
        $length = strlen($sample);
        for ($index = 0; $index < $length; $index++) {
            $ordinal = ord($sample[$index]);
            if ($ordinal === 9) {
                $escaped .= '\\t';
                continue;
            }
            if ($ordinal === 10) {
                $escaped .= '\\n';
                continue;
            }
            if ($ordinal === 13) {
                $escaped .= '\\r';
                continue;
            }
            if ($ordinal < 32 || $ordinal === 127 || $ordinal > 126) {
                $escaped .= sprintf('\\x%02X', $ordinal);
                continue;
            }

            $escaped .= $sample[$index];
        }

        return $escaped;
    }

    private function controlByteName(int $ordinal): string
    {
        return [
            0 => 'NUL',
            1 => 'SOH',
            2 => 'STX',
            3 => 'ETX',
            4 => 'EOT',
            5 => 'ENQ',
            6 => 'ACK',
            7 => 'BEL',
            8 => 'BS',
            11 => 'VT',
            12 => 'FF',
            14 => 'SO',
            15 => 'SI',
            16 => 'DLE',
            17 => 'DC1',
            18 => 'DC2',
            19 => 'DC3',
            20 => 'DC4',
            21 => 'NAK',
            22 => 'SYN',
            23 => 'ETB',
            24 => 'CAN',
            25 => 'EM',
            26 => 'SUB',
            27 => 'ESC',
            28 => 'FS',
            29 => 'GS',
            30 => 'RS',
            31 => 'US',
            127 => 'DEL',
        ][$ordinal] ?? sprintf('C0-%02X', $ordinal);
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
