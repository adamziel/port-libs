<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DelimitedTextReader
{
    private const INPUT_PREFIX_BYTE_LIMIT = 64;
    private const INPUT_PREFIX_PREVIEW_BYTE_LIMIT = 32;
    private const CONTROL_CHARACTER_SAMPLE_LIMIT = 8;
    private const CONTROL_CHARACTER_SAMPLE_RADIUS = 4;

    /**
     * @param array{header?:bool, extension?:string, sourcePath?:string, delimiter?:string, quote?:string|null|false, escape?:string|null|false, keepSpace?:bool, strictParsing?:bool} $options
     */
    public function readCsv(string $text, array $options = []): AstNode
    {
        return $this->read($text, 'csv', $options);
    }

    /**
     * @param array{header?:bool, extension?:string, sourcePath?:string, delimiter?:string, quote?:string|null|false, escape?:string|null|false, keepSpace?:bool, strictParsing?:bool} $options
     */
    public function readTsv(string $text, array $options = []): AstNode
    {
        return $this->read($text, 'tsv', $options);
    }

    /**
     * @param array{header?:bool, extension?:string, sourcePath?:string, delimiter?:string, quote?:string|null|false, escape?:string|null|false, keepSpace?:bool, strictParsing?:bool} $options
     */
    public function readAuto(string $text, array $options = []): AstNode
    {
        return $this->read($text, 'auto', $options);
    }

    /**
     * @param array{header?:bool, extension?:string, sourcePath?:string, delimiter?:string, quote?:string|null|false, escape?:string|null|false, keepSpace?:bool, strictParsing?:bool} $options
     */
    public function read(string $text, string $format = 'csv', array $options = []): AstNode
    {
        $inputPrefix = $this->inputPrefixReview($text);
        $strictParsing = $this->strictParsingOption($options);
        $prefixText = $strictParsing
            ? $this->inputTextAfterBomPrefix($text, $inputPrefix)
            : $this->inputTextAfterSupportedPrefix($text, $inputPrefix);
        $sourceText = $this->pandocSourceText($prefixText, $inputPrefix);
        $formatResolution = $this->formatResolution($format, $sourceText, $options);
        $format = $formatResolution['format'];
        $dialect = $this->dialectProfile($format, $options);
        $delimiter = $dialect['delimiter'];
        $formatInference = $formatResolution['formatInference'];
        $inputPrefix['formatContext'] = $this->formatContext($formatResolution, $options);
        $hasHeader = $this->headerOption($options);

        $parse = $this->parseRowsWithDiagnostics($sourceText, $dialect);
        if ($strictParsing) {
            $this->assertStrictParsing($format, $inputPrefix, $parse);
        }
        $rows = $parse['rows'];
        $sourceRowIndexes = $parse['sourceRowIndexes'];
        $blankRows = $parse['blankRows'];
        $controlCharacters = $this->controlCharacterSummary($sourceText, $dialect, $options);
        if ($rows === []) {
            $sourceAnalysis = $this->sourceAnalysis($sourceText, $dialect, []);
            return new AstNode('document', [
                'sourceFormat' => $format,
                'delimitedText' => $this->reviewPacket($format, $dialect, [], [], [], $blankRows, $hasHeader, 0, $formatInference, $inputPrefix, $sourceAnalysis, $parse['diagnostics'], $parse['metrics'], $controlCharacters),
            ]);
        }

        $widths = array_map('count', $rows);
        $columnCount = $this->repairedColumnCount($widths, $hasHeader);
        $sourceAnalysis = $this->sourceAnalysis($sourceText, $dialect, $widths);
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
            'delimitedText' => $this->reviewPacket($format, $dialect, $sourceHeader === null ? $rows : [$sourceHeader, ...$rows], $widths, $sourceRowIndexes, $blankRows, $hasHeader, $columnCount, $formatInference, $inputPrefix, $sourceAnalysis, $parse['diagnostics'], $parse['metrics'], $controlCharacters),
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
     * @param array{strictParsing?:bool} $options
     */
    private function strictParsingOption(array $options): bool
    {
        if (!array_key_exists('strictParsing', $options)) {
            return true;
        }

        if (!is_bool($options['strictParsing'])) {
            throw new \InvalidArgumentException('Delimited text strictParsing option must be a boolean');
        }

        return $options['strictParsing'];
    }

    /**
     * @param list<int> $widths
     */
    private function repairedColumnCount(array $widths, bool $hasHeader): int
    {
        if ($widths === []) {
            return 0;
        }

        return $hasHeader ? $widths[0] : max($widths);
    }

    /**
     * @param list<int> $widths
     */
    private function rowRepairPolicy(bool $hasHeader, array $widths): string
    {
        return $hasHeader && $widths !== [] ? 'header-row-width' : 'pad-to-wide-row';
    }

    /**
     * @return list<list<string>>
     */
    private function parseRows(string $text, string $delimiter): array
    {
        return $this->parseRowsWithDiagnostics($text, $this->dialectProfileForDelimiter($delimiter))['rows'];
    }

    /**
     * @param array{delimiter?:string, quote?:string|null|false, escape?:string|null|false, keepSpace?:bool} $options
     * @return array{delimiter:string, delimiterName:string, quote:string|null, escape:string|null, keepSpace:bool}
     */
    private function dialectProfile(string $format, array $options = []): array
    {
        $profile = match ($format) {
            'csv' => [
                'delimiter' => ',',
                'delimiterName' => 'comma',
                'quote' => '"',
                'escape' => null,
                'keepSpace' => false,
            ],
            'tsv' => [
                'delimiter' => "\t",
                'delimiterName' => 'tab',
                'quote' => null,
                'escape' => null,
                'keepSpace' => false,
            ],
            default => throw new \InvalidArgumentException("Unsupported delimited text format: {$format}; supported formats: csv, tsv, auto"),
        };

        if (array_key_exists('delimiter', $options)) {
            if (!is_string($options['delimiter'])) {
                throw new \InvalidArgumentException('Delimited text delimiter option must be a string');
            }

            $profile['delimiter'] = $this->delimiterOptionValue($options['delimiter']);
            $profile['delimiterName'] = $this->delimiterName($profile['delimiter']);
        }

        if (array_key_exists('quote', $options)) {
            $profile['quote'] = $this->optionalSingleCharacterOption($options['quote'], 'quote');
        }

        if (array_key_exists('escape', $options)) {
            $profile['escape'] = $this->optionalSingleCharacterOption($options['escape'], 'escape');
        }

        if (array_key_exists('keepSpace', $options)) {
            if (!is_bool($options['keepSpace'])) {
                throw new \InvalidArgumentException('Delimited text keepSpace option must be a boolean');
            }
            $profile['keepSpace'] = $options['keepSpace'];
        }

        return $profile;
    }

    /**
     * @return array{delimiter:string, delimiterName:string, quote:string|null, escape:string|null, keepSpace:bool}
     */
    private function dialectProfileForDelimiter(string $delimiter): array
    {
        return $delimiter === "\t" ? $this->dialectProfile('tsv') : $this->dialectProfile('csv');
    }

    private function delimiterOptionValue(string $delimiter): string
    {
        $normalized = strtolower(trim($delimiter));
        $value = match ($normalized) {
            'comma' => ',',
            'tab', '\t' => "\t",
            'space' => ' ',
            'semicolon', 'semi' => ';',
            'pipe', 'bar' => '|',
            default => $delimiter,
        };

        if ($value === '') {
            throw new \InvalidArgumentException('Delimited text delimiter option must not be empty');
        }
        if ($this->length($value) !== 1) {
            throw new \InvalidArgumentException('Delimited text delimiter option must be a single character or known delimiter name');
        }

        return $value;
    }

    private function delimiterName(string $delimiter): string
    {
        return match ($delimiter) {
            ',' => 'comma',
            "\t" => 'tab',
            ' ' => 'space',
            ';' => 'semicolon',
            '|' => 'pipe',
            default => $delimiter,
        };
    }

    private function optionalSingleCharacterOption(mixed $value, string $name): ?string
    {
        if ($value === null || $value === false || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new \InvalidArgumentException("Delimited text {$name} option must be a string, null, or false");
        }
        if ($this->length($value) !== 1) {
            throw new \InvalidArgumentException("Delimited text {$name} option must be a single character");
        }

        return $value;
    }

    private function length(string $text): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($text, 'UTF-8');
        }

        if (preg_match_all('/./us', $text, $matches) === 1) {
            return count($matches[0]);
        }

        return strlen($text);
    }

    /**
     * @param array{delimiter:string, delimiterName:string, quote:string|null, escape:string|null, keepSpace:bool} $dialect
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
        $escape = $dialect['escape'];
        $keepSpace = $dialect['keepSpace'];
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

                if ($escape !== null && $char === $escape && $next !== '' && !$this->isLineBreak($next)) {
                    $field .= $next;
                    if ($quote !== null && $next === $quote) {
                        $metrics['escapedQuoteSequenceCount']++;
                    }
                    $offset++;
                    continue;
                }

                if ($escape === null && $quote !== null && $char === '\\' && $next === $quote) {
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
                    $this->skipPostDelimiterWhitespace($text, $offset, $delimiter, $keepSpace);
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
                $this->skipPostDelimiterWhitespace($text, $offset, $delimiter, $keepSpace);
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
     * @param array<string, mixed> $inputPrefix
     * @param array{
     *     rows:list<list<string>>,
     *     sourceRowIndexes:list<int>,
     *     blankRows:list<int>,
     *     diagnostics:list<array{code:string, severity:string, message:string, row:int, column:int, offset:int}>,
     *     metrics:array<string, int>
     * } $parse
     */
    private function assertStrictParsing(string $format, array $inputPrefix, array $parse): void
    {
        if ((int) ($inputPrefix['leadingWhitespaceByteCount'] ?? 0) > 0) {
            $line = (int) ($inputPrefix['firstContentLine'] ?? 1);
            throw new \InvalidArgumentException("Malformed {$format} input: leading blank or whitespace-only records before line {$line} are not valid.");
        }

        $blankRows = is_array($parse['blankRows'] ?? null) ? $parse['blankRows'] : [];
        if ($blankRows !== []) {
            $row = (int) $blankRows[0] + 1;
            throw new \InvalidArgumentException("Malformed {$format} input: blank record at line {$row}.");
        }

        $metrics = is_array($parse['metrics'] ?? null) ? $parse['metrics'] : [];
        if ((int) ($metrics['textAfterClosingQuoteCount'] ?? 0) > 0) {
            $diagnostic = $this->firstParseDiagnostic($parse, 'delimited-text-text-after-closing-quote');
            $row = $diagnostic === null ? null : ((int) $diagnostic['row'] + 1);
            $column = $diagnostic === null ? null : ((int) $diagnostic['column'] + 1);
            $location = $row === null ? '' : " at line {$row}, column {$column}";
            throw new \InvalidArgumentException("Malformed {$format} input: text after a closing quote{$location}.");
        }

        $diagnostic = $this->firstParseDiagnostic($parse, 'delimited-text-backslash-quote-preserved');
        if ($diagnostic !== null) {
            $row = (int) $diagnostic['row'] + 1;
            $column = (int) $diagnostic['column'] + 1;
            throw new \InvalidArgumentException("Malformed {$format} input: backslash before a quote at line {$row}, column {$column}; this dialect only recognizes doubled quote escapes.");
        }

        if ((int) ($metrics['unclosedQuoteCount'] ?? 0) > 0) {
            $diagnostic = $this->firstParseDiagnostic($parse, 'delimited-text-unclosed-quoted-field');
            $row = $diagnostic === null ? null : ((int) $diagnostic['row'] + 1);
            $column = $diagnostic === null ? null : ((int) $diagnostic['column'] + 1);
            $location = $row === null ? '' : " starting at line {$row}, column {$column}";
            throw new \InvalidArgumentException("Malformed {$format} input: quoted field reaches end of input before a closing quote{$location}.");
        }
    }

    /**
     * @param array{
     *     diagnostics:list<array{code:string, severity:string, message:string, row:int, column:int, offset:int}>
     * } $parse
     * @return array{code:string, severity:string, message:string, row:int, column:int, offset:int}|null
     */
    private function firstParseDiagnostic(array $parse, string $code): ?array
    {
        foreach ($parse['diagnostics'] ?? [] as $diagnostic) {
            if (($diagnostic['code'] ?? null) === $code) {
                return $diagnostic;
            }
        }

        return null;
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

    private function skipPostDelimiterWhitespace(string $text, int &$offset, string $delimiter, bool $keepSpace): void
    {
        if ($keepSpace) {
            return;
        }

        $length = strlen($text);
        $index = $offset + 1;
        while ($index < $length && $this->isPostDelimiterWhitespace($text[$index], $delimiter)) {
            $index++;
        }
        $offset = $index - 1;
    }

    private function isPostDelimiterWhitespace(string $char, string $delimiter): bool
    {
        return $delimiter === "\t" ? $char === ' ' : ($char === ' ' || $char === "\t");
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
            ], $text === '' ? [] : [new AstNode('plain', [], $this->cellInlines($text))]);
        }

        return new AstNode('table_row', ['header' => $header], $cells);
    }

    /**
     * @return list<AstNode>
     */
    private function cellInlines(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $parts = preg_split('/\R/u', $text);
        if ($parts === false || count($parts) <= 1) {
            return [new AstNode('text', ['text' => $text])];
        }

        $inlines = [];
        foreach ($parts as $index => $part) {
            if ($index > 0) {
                $inlines[] = new AstNode('linebreak');
            }
            if ($part !== '') {
                $inlines[] = new AstNode('text', ['text' => $part]);
            }
        }

        return $inlines;
    }

    /**
     * @param array{delimiter:string, delimiterName:string, quote:string|null, escape:string|null, keepSpace:bool} $dialect
     * @param list<list<string>> $rows
     * @param list<int> $widths
     * @param list<int> $sourceRowIndexes
     * @param list<int> $blankRows
     * @param array<string, mixed> $inputPrefix
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
        int $repairedColumnCount,
        array $formatInference,
        array $inputPrefix,
        array $sourceAnalysis,
        array $parseDiagnostics,
        array $parseMetrics,
        array $controlCharacters
    ): array
    {
        $rowCount = count($rows);
        $sourceColumnCount = $widths === [] ? 0 : max($widths);
        $columnCount = max(0, $repairedColumnCount);
        $raggedRows = [];
        foreach ($widths as $index => $width) {
            if ($width !== $columnCount) {
                $raggedRows[] = $index;
            }
        }
        $rowWidthSummary = $this->rowWidthSummary($rows, $widths, $sourceRowIndexes, $blankRows, $hasHeader, $columnCount);
        $rowRepairSummary = $this->rowRepairSummary($rows, $widths, $sourceRowIndexes, $blankRows, $hasHeader, $columnCount);
        $controlCharacters = $this->annotateControlCharactersWithRepair($controlCharacters, $rowRepairSummary);
        $diagnostics = $this->reviewDiagnostics($hasHeader, $formatInference, $inputPrefix, $sourceAnalysis, $rowWidthSummary, $controlCharacters);
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
                'keepSpace' => $dialect['keepSpace'],
                'quoteMode' => $dialect['quote'] === null ? 'literal' : 'quoted-fields',
                'escapeMode' => $dialect['escape'] === null ? 'none' : 'escape-character',
            ],
            'formatInference' => $formatInference,
            'inputPrefix' => $inputPrefix,
            'headerRow' => $hasHeader && $rowCount > 0,
            'headerOption' => $hasHeader ? 'first-row' : 'none',
            'headerSource' => $hasHeader && $rowCount > 0 ? 'source-row-0' : 'generated',
            'rowCount' => $rowCount,
            'bodyRowCount' => $hasHeader ? max(0, $rowCount - 1) : $rowCount,
            'columnCount' => $columnCount,
            'sourceMaxFieldCount' => $sourceColumnCount,
            'columnNames' => $hasHeader && $rows !== []
                ? $this->normalizedRow($rows[0], $columnCount)
                : $this->generatedColumnLabels($columnCount),
            'fieldCount' => array_sum($widths),
            'minFieldCount' => $widths === [] ? 0 : min($widths),
            'maxFieldCount' => $sourceColumnCount,
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
            'upstreamEvidence' => $this->upstreamEvidencePacket($format),
        ];
    }

    /**
     * @return array{
     *     denominator:int,
     *     denominatorScope:string,
     *     fixtures:list<string>,
     *     source:string,
     *     reader:string,
     *     selectedDirectFixtureFormat:string,
     *     directFixtureDenominator:int,
     *     directFixtureCount:int,
     *     directFixtures:list<string>,
     *     csvDirectFixtureDenominator:int,
     *     tsvDirectFixtureDenominator:int,
     *     parserOptionFixtureCount:int,
     *     parserOptionFixtures:list<string>,
     *     integrationFixtureCount:int,
     *     integrationFixtures:list<string>,
     *     adjacentFixtureEvidence:array<string, mixed>,
     *     staticCurrentEvidence:array<string, mixed>,
     *     generatedNativeParitySampleCount:int,
     *     generatedNativeParityEvidence:array<string, mixed>,
     *     runnerEvidence:array{runner:string, status:string, executed:bool, command:null, resultArtifact:null, reason:string, claim:string},
     *     notRunEvidence:list<array{scope:string, runner:string, status:string, executed:bool, reason:string}>,
     *     closedGaps:list<string>,
     *     openGaps:list<string>,
     *     claimBoundaries:list<string>
     * }
     */
    private function upstreamEvidencePacket(string $format): array
    {
        $csvCommandFixtures = [
            'test/command/csv.md',
            'test/command/01.csv',
        ];
        $rstCsvAdjacentEvidence = DelimitedTextUpstreamReaderEvidence::csvAdjacentRstFixtureEvidence();
        $rstCsvFixtures = array_values(array_map(
            static fn (array $fixture): string => (string) $fixture['path'],
            is_array($rstCsvAdjacentEvidence['fixtures'] ?? null) ? $rstCsvAdjacentEvidence['fixtures'] : []
        ));
        $parserOptionFixtures = [
            'comma-delimiter-no-header',
            'space-delimiter-single-quote',
            'backslash-escaped-quote',
            'backslash-escaped-nonquote',
            'keep-space-after-delimiter',
            'semicolon-delimiter-multiline-cell',
            'pipe-delimiter-quoted-field',
            'quote-disabled-literal',
        ];
        $staticCurrentEvidence = DelimitedTextUpstreamReaderEvidence::checkedInCurrentEvidence(dirname(__DIR__, 3));
        $generatedCsvNativeStaticEvidence = is_array($staticCurrentEvidence['generatedCsvNativeStaticEvidence'] ?? null)
            ? $staticCurrentEvidence['generatedCsvNativeStaticEvidence']
            : [];
        $generatedTsvNativeStaticEvidence = is_array($staticCurrentEvidence['generatedTsvNativeStaticEvidence'] ?? null)
            ? $staticCurrentEvidence['generatedTsvNativeStaticEvidence']
            : [];
        $runnerEvidence = [
            'runner' => 'Cabal/Tasty Pandoc reader suite',
            'status' => 'not-run',
            'executed' => false,
            'command' => null,
            'resultArtifact' => null,
            'reason' => 'This native PHP evidence packet is generated without executing the upstream Haskell runner.',
            'claim' => 'No upstream Haskell runner parity is claimed.',
        ];
        $notRunEvidence = [
            [
                'scope' => 'upstream-haskell-runner',
                'runner' => $runnerEvidence['runner'],
                'status' => $runnerEvidence['status'],
                'executed' => $runnerEvidence['executed'],
                'reason' => $runnerEvidence['reason'],
            ],
        ];

        if ($format === 'tsv') {
            return [
                'denominator' => 0,
                'denominatorScope' => 'direct-reader-fixtures',
                'fixtures' => [],
                'source' => 'Pandoc 4f5226df4faa0d66dd2c089465b13886360ab3c2 src/Text/Pandoc/CSV.hs and src/Text/Pandoc/Readers/CSV.hs',
                'reader' => 'tsv',
                'selectedDirectFixtureFormat' => 'tsv',
                'directFixtureDenominator' => 0,
                'directFixtureCount' => 0,
                'directFixtures' => [],
                'csvDirectFixtureDenominator' => count($csvCommandFixtures),
                'tsvDirectFixtureDenominator' => 0,
                'parserOptionFixtureCount' => 0,
                'parserOptionFixtures' => [],
                'integrationFixtureCount' => 0,
                'integrationFixtures' => [],
                'adjacentFixtureEvidence' => [],
                'staticCurrentEvidence' => $staticCurrentEvidence,
                'generatedNativeParitySampleCount' => DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT,
                'generatedNativeParityEvidence' => $generatedTsvNativeStaticEvidence,
                'runnerEvidence' => $runnerEvidence,
                'notRunEvidence' => $notRunEvidence,
                'closedGaps' => [
                    'tsv-tab-delimiter-reader',
                    'tsv-literal-quote-policy',
                    'tsv-trailing-empty-field-preservation',
                    'tsv-row-repair-and-control-character-provenance',
                    'generated-tsv-native-parity-sample',
                ],
                'openGaps' => [
                    'no-dedicated-upstream-tsv-command-fixture-in-pinned-corpus',
                    'upstream-runner-not-run',
                ],
                'claimBoundaries' => [
                    'TSV is an upstream input token but the pinned command corpus evidence is CSV-only.',
                    'TSV parity is covered by native tab-delimited reader semantics, not by a dedicated upstream TSV golden fixture.',
                    'Generated TSV-to-native sample evidence is local executable evidence and is not counted as an upstream TSV fixture.',
                    'This packet does not claim RST csv-table integration or upstream Haskell runner parity.',
                ],
            ];
        }

        return [
            'denominator' => count($csvCommandFixtures),
            'denominatorScope' => 'direct-reader-fixtures',
            'fixtures' => $csvCommandFixtures,
            'source' => 'Pandoc 4f5226df4faa0d66dd2c089465b13886360ab3c2 src/Text/Pandoc/CSV.hs and src/Text/Pandoc/Readers/CSV.hs',
            'reader' => 'csv',
            'selectedDirectFixtureFormat' => 'csv',
            'directFixtureDenominator' => count($csvCommandFixtures),
            'directFixtureCount' => count($csvCommandFixtures),
            'directFixtures' => $csvCommandFixtures,
            'csvDirectFixtureDenominator' => count($csvCommandFixtures),
            'tsvDirectFixtureDenominator' => 0,
            'parserOptionFixtureCount' => count($parserOptionFixtures),
            'parserOptionFixtures' => $parserOptionFixtures,
            'integrationFixtureCount' => count($rstCsvFixtures),
            'integrationFixtures' => $rstCsvFixtures,
            'adjacentFixtureEvidence' => $rstCsvAdjacentEvidence,
            'staticCurrentEvidence' => $staticCurrentEvidence,
            'generatedNativeParitySampleCount' => DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT,
            'generatedNativeParityEvidence' => $generatedCsvNativeStaticEvidence,
            'runnerEvidence' => $runnerEvidence,
            'notRunEvidence' => $notRunEvidence,
            'closedGaps' => [
                'direct-csv-command-reader',
                'shared-csv-parser-option-fixtures',
                'csv-row-repair-and-control-character-provenance',
                'generated-csv-native-parity-sample',
            ],
            'openGaps' => [
                'rst-csv-table-integration-requires-rst-reader',
                'upstream-runner-not-run',
            ],
            'claimBoundaries' => [
                'The direct CSV command reader fixture and parser option behavior are covered locally.',
                'Generated CSV-to-native sample evidence is local executable evidence and is not counted as an upstream CSV direct fixture.',
                'The two RST csv-table files are tracked as upstream CSV-adjacent evidence but remain blocked on native RST reader support.',
                'This packet does not claim upstream Haskell runner parity.',
            ],
        ];
    }

    /**
     * @param array{delimiter:string, delimiterName:string, quote:string|null, escape:string|null, keepSpace:bool} $dialect
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
    private function sourceAnalysis(string $text, array $dialect, array $widths): array
    {
        $delimiter = $dialect['delimiter'];
        $quote = $dialect['quote'];
        $escape = $dialect['escape'];
        $keepSpace = $dialect['keepSpace'];
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
                if ($escape !== null && $char === $escape && $next !== null && !$this->isLineBreak($next)) {
                    $offset++;
                    continue;
                }

                if ($quote !== null && $char === $quote) {
                    if ($escape === null && $next === $quote) {
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
                $this->skipPostDelimiterWhitespace($text, $offset, $delimiter, $keepSpace);
                continue;
            }

            if ($quote !== null && $char === $quote && $atFieldStart) {
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
     * @param array<string, mixed> $inputPrefix
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
        array $inputPrefix,
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

        foreach ($this->inputPrefixDiagnostics($inputPrefix) as $diagnostic) {
            $diagnostics[] = $diagnostic;
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
                'blankRowCount' => $rowWidthSummary['blankRowCount'],
                'rows' => $rowWidthSummary['blankRows'] ?? [],
            ];
        }

        if (($rowWidthSummary['trailingEmptyFieldRows'] ?? []) !== []) {
            $diagnostics[] = [
                'code' => 'delimited-text-trailing-empty-fields-preserved',
                'severity' => 'info',
                'message' => 'Rows with trailing empty fields were preserved before table padding.',
                'rows' => $rowWidthSummary['trailingEmptyFieldRows'],
            ];
        }

        if (($rowWidthSummary['strict']['mismatchCount'] ?? 0) > 0) {
            $diagnostics[] = [
                'code' => 'delimited-text-strict-row-width-mismatch',
                'severity' => 'warning',
                'message' => 'Strict row-width policy would reject rows whose field counts differ from the first/header row.',
                'policy' => $rowWidthSummary['strict']['policy'] ?? 'first-row',
                'expectedColumnCount' => $rowWidthSummary['strict']['expectedColumnCount'] ?? 0,
                'mismatchCount' => $rowWidthSummary['strict']['mismatchCount'] ?? 0,
                'mismatches' => $rowWidthSummary['strict']['mismatches'] ?? [],
            ];
        }

        if (($rowWidthSummary['relaxed']['consistent'] ?? true) === false) {
            $diagnostics[] = [
                'code' => 'delimited-text-row-widths-uneven',
                'severity' => 'warning',
                'message' => 'Delimited text rows have uneven field counts; rows were padded or truncated in the native table.',
                'policy' => $rowWidthSummary['relaxed']['policy'] ?? 'pad-to-wide-row',
                'columnCount' => $rowWidthSummary['relaxed']['columnCount'] ?? 0,
                'changedRowCount' => $rowWidthSummary['relaxed']['changedRowCount'] ?? 0,
                'paddedRowCount' => $rowWidthSummary['relaxed']['paddedRowCount'] ?? 0,
                'truncatedRowCount' => $rowWidthSummary['relaxed']['truncatedRowCount'] ?? 0,
                'paddedRows' => $rowWidthSummary['relaxed']['paddedRows'] ?? [],
                'truncatedRows' => $rowWidthSummary['relaxed']['truncatedRows'] ?? [],
            ];
        }

        if (($rowWidthSummary['header']['mismatchCount'] ?? 0) > 0) {
            $diagnostics[] = [
                'code' => 'delimited-text-header-width-mismatch',
                'severity' => 'warning',
                'message' => 'Header and body row field counts differ; column names were normalized to the reader table width.',
                'headerColumnCount' => $rowWidthSummary['header']['headerColumnCount'] ?? 0,
                'dataColumnCounts' => $rowWidthSummary['header']['dataColumnCounts'] ?? [],
                'mismatchCount' => $rowWidthSummary['header']['mismatchCount'] ?? 0,
                'mismatches' => $rowWidthSummary['header']['mismatches'] ?? [],
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
     * @return array<string, mixed>
     */
    private function inputPrefixReview(string $text): array
    {
        $inputByteCount = strlen($text);
        $bomByteCount = str_starts_with($text, "\xEF\xBB\xBF") ? 3 : 0;
        $leadingWhitespaceByteCount = $this->leadingWhitespaceLinePrefixByteCount(substr($text, $bomByteCount));
        $firstContentOffset = $bomByteCount + $leadingWhitespaceByteCount;
        $inspectedByteCount = min($inputByteCount, self::INPUT_PREFIX_BYTE_LIMIT);
        $previewByteCount = min($inputByteCount, self::INPUT_PREFIX_PREVIEW_BYTE_LIMIT);
        $controlReview = $this->inputPrefixControlReview(substr($text, 0, $inspectedByteCount));

        return [
            'policy' => 'bounded-input-prefix-review',
            'encoding' => 'utf-8',
            'bom' => $bomByteCount > 0 ? 'utf-8' : 'none',
            'bomByteCount' => $bomByteCount,
            'leadingWhitespaceByteCount' => $leadingWhitespaceByteCount,
            'leadingWhitespaceLineCount' => $this->lineBreakCount(substr($text, $bomByteCount, $leadingWhitespaceByteCount)),
            'firstContentOffset' => $firstContentOffset,
            'firstContentLine' => $this->lineBreakCount(substr($text, 0, $firstContentOffset)) + 1,
            'inputByteCount' => $inputByteCount,
            'inspectionByteLimit' => self::INPUT_PREFIX_BYTE_LIMIT,
            'inspectedByteCount' => $inspectedByteCount,
            'inspectionTruncated' => $inputByteCount > self::INPUT_PREFIX_BYTE_LIMIT,
            'prefixPreviewByteLimit' => self::INPUT_PREFIX_PREVIEW_BYTE_LIMIT,
            'prefixPreviewByteCount' => $previewByteCount,
            'prefixPreviewHex' => bin2hex(substr($text, 0, $previewByteCount)),
            'sampleLimit' => self::CONTROL_CHARACTER_SAMPLE_LIMIT,
            'nullByteCount' => $controlReview['nullByteCount'],
            'nullBytes' => $controlReview['nullBytes'],
            'controlCharacterCount' => $controlReview['controlCharacterCount'],
            'controlCharacters' => $controlReview['controlCharacters'],
        ];
    }

    /**
     * @param array<string, mixed> $inputPrefix
     */
    private function inputTextAfterSupportedPrefix(string $text, array $inputPrefix): string
    {
        $firstContentOffset = $inputPrefix['firstContentOffset'] ?? 0;
        if (!is_int($firstContentOffset) || $firstContentOffset <= 0) {
            return $text;
        }

        return substr($text, $firstContentOffset);
    }

    /**
     * @param array<string, mixed> $inputPrefix
     */
    private function inputTextAfterBomPrefix(string $text, array $inputPrefix): string
    {
        $bomByteCount = $inputPrefix['bomByteCount'] ?? 0;
        if (!is_int($bomByteCount) || $bomByteCount <= 0) {
            return $text;
        }

        return substr($text, $bomByteCount);
    }

    /**
     * @param array<string, mixed> $inputPrefix
     */
    private function pandocSourceText(string $text, array &$inputPrefix): string
    {
        $carriageReturnCount = substr_count($text, "\r");
        $inputPrefix['carriageReturnNormalization'] = [
            'policy' => 'pandoc-sources-remove-carriage-returns',
            'removedCount' => $carriageReturnCount,
        ];

        return $carriageReturnCount === 0 ? $text : str_replace("\r", '', $text);
    }

    private function leadingWhitespaceLinePrefixByteCount(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        if (preg_match('/\A(?:[ \t]*(?:\r\n|\r|\n))+[ \t]*/', $text, $matches) !== 1) {
            return 0;
        }

        return strlen($matches[0]);
    }

    private function lineBreakCount(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        preg_match_all('/\r\n|\r|\n/', $text, $matches);

        return count($matches[0]);
    }

    /**
     * @return array{nullByteCount:int, nullBytes:list<array{offset:int, hex:string, name:string}>, controlCharacterCount:int, controlCharacters:list<array{offset:int, hex:string, name:string}>}
     */
    private function inputPrefixControlReview(string $text): array
    {
        $nullByteCount = 0;
        $nullBytes = [];
        $controlCharacterCount = 0;
        $controlCharacters = [];
        $length = strlen($text);

        for ($offset = 0; $offset < $length; $offset++) {
            $ordinal = ord($text[$offset]);
            if ($ordinal === 0) {
                $nullByteCount++;
                if (count($nullBytes) < self::CONTROL_CHARACTER_SAMPLE_LIMIT) {
                    $nullBytes[] = [
                        'offset' => $offset,
                        'hex' => '00',
                        'name' => 'NUL',
                    ];
                }
                continue;
            }

            if (!$this->isReviewableControlByte($ordinal)) {
                continue;
            }

            $controlCharacterCount++;
            if (count($controlCharacters) < self::CONTROL_CHARACTER_SAMPLE_LIMIT) {
                $controlCharacters[] = [
                    'offset' => $offset,
                    'hex' => sprintf('%02X', $ordinal),
                    'name' => $this->controlByteName($ordinal),
                ];
            }
        }

        return [
            'nullByteCount' => $nullByteCount,
            'nullBytes' => $nullBytes,
            'controlCharacterCount' => $controlCharacterCount,
            'controlCharacters' => $controlCharacters,
        ];
    }

    /**
     * @param array<string, mixed> $inputPrefix
     * @return list<array<string, mixed>>
     */
    private function inputPrefixDiagnostics(array $inputPrefix): array
    {
        $diagnostics = [];
        if (($inputPrefix['bom'] ?? 'none') === 'utf-8') {
            $diagnostics[] = [
                'code' => 'delimited-text-input-prefix-utf8-bom',
                'severity' => 'info',
                'message' => 'UTF-8 byte-order mark was detected and skipped before parsing the first row.',
                'byteCount' => $inputPrefix['bomByteCount'] ?? 0,
            ];
        }

        if (($inputPrefix['leadingWhitespaceByteCount'] ?? 0) > 0) {
            $diagnostics[] = [
                'code' => 'delimited-text-input-prefix-leading-whitespace',
                'severity' => 'info',
                'message' => 'Leading whitespace-only lines were skipped before parsing the first row.',
                'byteCount' => $inputPrefix['leadingWhitespaceByteCount'],
                'lineCount' => $inputPrefix['leadingWhitespaceLineCount'] ?? 0,
                'firstContentLine' => $inputPrefix['firstContentLine'] ?? 1,
            ];
        }

        if (($inputPrefix['nullByteCount'] ?? 0) > 0) {
            $diagnostics[] = [
                'code' => 'delimited-text-input-prefix-null-byte',
                'severity' => 'warning',
                'message' => 'NUL bytes were detected in the bounded input-prefix inspection window.',
                'count' => $inputPrefix['nullByteCount'],
                'sampleCount' => count($inputPrefix['nullBytes'] ?? []),
                'sampleLimit' => $inputPrefix['sampleLimit'] ?? self::CONTROL_CHARACTER_SAMPLE_LIMIT,
            ];
        }

        if (($inputPrefix['controlCharacterCount'] ?? 0) > 0) {
            $diagnostics[] = [
                'code' => 'delimited-text-input-prefix-control-character',
                'severity' => 'warning',
                'message' => 'Non-whitespace control characters were detected in the bounded input-prefix inspection window.',
                'count' => $inputPrefix['controlCharacterCount'],
                'sampleCount' => count($inputPrefix['controlCharacters'] ?? []),
                'sampleLimit' => $inputPrefix['sampleLimit'] ?? self::CONTROL_CHARACTER_SAMPLE_LIMIT,
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
    private function rowWidthSummary(array $rows, array $widths, array $sourceRowIndexes, array $blankRows, bool $hasHeader, int $repairedColumnCount): array
    {
        $rowCount = count($rows);
        $sourceColumnCount = $widths === [] ? 0 : max($widths);
        $columnCount = max(0, $repairedColumnCount);
        $expectedWidth = $widths[0] ?? 0;
        $strictMismatches = [];
        $headerMismatches = [];
        $relaxedPaddedRows = [];
        $relaxedTruncatedRows = [];
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
            if ($width > $columnCount) {
                $relaxedTruncatedRows[] = $this->rowWidthMismatch($index, $sourceRow, $rowRole, $width, $columnCount);
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
            'sourceMaxFieldCount' => $sourceColumnCount,
            'strict' => [
                'policy' => $hasHeader && $rowCount > 0 ? 'header-row' : 'first-row',
                'expectedColumnCount' => $expectedWidth,
                'consistent' => $strictMismatches === [],
                'mismatchCount' => count($strictMismatches),
                'mismatches' => $strictMismatches,
            ],
            'relaxed' => [
                'policy' => $this->rowRepairPolicy($hasHeader, $widths),
                'columnCount' => $columnCount,
                'sourceMaxFieldCount' => $sourceColumnCount,
                'consistent' => $widths === [] || ($relaxedPaddedRows === [] && $relaxedTruncatedRows === []),
                'changedRowCount' => count($relaxedPaddedRows) + count($relaxedTruncatedRows),
                'paddedRowCount' => count($relaxedPaddedRows),
                'truncatedRowCount' => count($relaxedTruncatedRows),
                'paddedRows' => $relaxedPaddedRows,
                'truncatedRows' => $relaxedTruncatedRows,
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
    private function rowRepairSummary(array $rows, array $widths, array $sourceRowIndexes, array $blankRows, bool $hasHeader, int $repairedColumnCount): array
    {
        $sourceColumnCount = $widths === [] ? 0 : max($widths);
        $columnCount = max(0, $repairedColumnCount);
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
            'policy' => $this->rowRepairPolicy($hasHeader, $widths),
            'strictPolicy' => $hasHeader && $widths !== [] ? 'header-row' : 'first-row',
            'originalColumnCounts' => $widths,
            'sourceMaxFieldCount' => $sourceColumnCount,
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
     * @param array{format:string, delimiter:string, formatInference:array<string, mixed>} $formatResolution
     * @param array{header?:bool, extension?:string, sourcePath?:string} $options
     * @return array<string, mixed>
     */
    private function formatContext(array $formatResolution, array $options): array
    {
        $extension = $this->metadataStringOption($options, 'extension');
        $sourcePath = $this->metadataStringOption($options, 'sourcePath');
        $sourcePathExtension = $sourcePath === null ? null : pathinfo($sourcePath, PATHINFO_EXTENSION);
        $sourcePathExtension = $sourcePathExtension === '' ? null : $sourcePathExtension;
        $extensionFormat = $extension === null ? null : PandocFormatRegistry::inferTabularDataFormatFromExtension($extension);
        $sourcePathFormat = $sourcePathExtension === null
            ? null
            : PandocFormatRegistry::inferTabularDataFormatFromExtension($sourcePathExtension);
        $selectedFormat = $formatResolution['format'];
        $contextFormats = array_values(array_unique(array_filter(
            [$extensionFormat, $sourcePathFormat],
            static fn (?string $format): bool => $format !== null
        )));

        return [
            'requestedFormat' => (string) ($formatResolution['formatInference']['requestedFormat'] ?? $selectedFormat),
            'selectedFormat' => $selectedFormat,
            'sourcePath' => $sourcePath,
            'sourcePathExtension' => $sourcePathExtension,
            'sourcePathFormat' => $sourcePathFormat,
            'extension' => $extension,
            'extensionFormat' => $extensionFormat,
            'contextFormats' => $contextFormats,
            'formatMatchesContext' => $contextFormats === [] ? null : $contextFormats === [$selectedFormat],
            'contextConflict' => count($contextFormats) > 1,
        ];
    }

    /**
     * @param array{header?:bool, extension?:string, sourcePath?:string} $options
     */
    private function metadataStringOption(array $options, string $key): ?string
    {
        if (!array_key_exists($key, $options)) {
            return null;
        }

        if (!is_string($options[$key])) {
            throw new \InvalidArgumentException("Delimited text {$key} option must be a string");
        }

        $value = trim($options[$key]);

        return $value === '' ? null : $value;
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
