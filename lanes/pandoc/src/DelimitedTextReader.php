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
        $formatInference = $formatResolution['formatInference'];
        $hasHeader = $this->headerOption($options);
        $sourceText = $this->stripBom($text);
        $controlCharacters = $this->controlCharacterSummary($sourceText, $delimiter, $options);

        $rows = $this->parseRows($sourceText, $delimiter);
        if ($rows === []) {
            return new AstNode('document', [
                'sourceFormat' => $format,
                'delimitedText' => $this->reviewPacket($format, $delimiter, [], [], $hasHeader, $formatInference, $controlCharacters),
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
            'delimitedText' => $this->reviewPacket($format, $delimiter, $sourceHeader === null ? $rows : [$sourceHeader, ...$rows], $widths, $hasHeader, $formatInference, $controlCharacters),
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
     * @param array<string, mixed> $controlCharacters
     * @return array<string, mixed>
     */
    private function reviewPacket(string $format, string $delimiter, array $rows, array $widths, bool $hasHeader, array $formatInference, array $controlCharacters): array
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
            'controlCharacters' => $controlCharacters,
            'raggedRowCount' => count($raggedRows),
            'raggedRows' => $raggedRows,
            'diagnostics' => $this->reviewDiagnostics($hasHeader, $formatInference, $controlCharacters),
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
     * @param array<string, mixed> $controlCharacters
     * @return list<array<string, mixed>>
     */
    private function reviewDiagnostics(bool $hasHeader, array $formatInference, array $controlCharacters): array
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
            ];
            if (($controlCharacters['sourcePath'] ?? null) !== null) {
                $diagnostic['sourcePath'] = $controlCharacters['sourcePath'];
            }

            $diagnostics[] = $diagnostic;
        }

        return $diagnostics;
    }

    /**
     * @param array{sourcePath?:string} $options
     * @return array<string, mixed>
     */
    private function controlCharacterSummary(string $text, string $delimiter, array $options): array
    {
        $sourcePath = $this->sourcePathContext($options);
        $samples = [];
        $byCodepoint = [];
        $totalCount = 0;
        $nulCount = 0;
        $quotedFieldCount = 0;
        $unquotedFieldCount = 0;
        $rowIndex = 0;
        $columnIndex = 0;
        $fieldStarted = false;
        $fieldQuoted = false;
        $inQuotedField = false;
        $length = strlen($text);

        for ($offset = 0; $offset < $length; $offset++) {
            $byte = $text[$offset];

            if ($byte === '"') {
                if (!$fieldStarted) {
                    $fieldStarted = true;
                    $fieldQuoted = true;
                    $inQuotedField = true;
                    continue;
                }

                if ($inQuotedField) {
                    if (($text[$offset + 1] ?? '') === '"') {
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
            if ($fieldQuoted) {
                $quotedFieldCount++;
            } else {
                $unquotedFieldCount++;
            }

            $codepoint = sprintf('U+%04X', $ordinal);
            $byCodepoint[$codepoint] = ($byCodepoint[$codepoint] ?? 0) + 1;

            if (count($samples) >= self::CONTROL_CHARACTER_SAMPLE_LIMIT) {
                continue;
            }

            $samples[] = [
                'rowIndex' => $rowIndex,
                'rowNumber' => $rowIndex + 1,
                'columnIndex' => $columnIndex,
                'columnNumber' => $columnIndex + 1,
                'fieldQuoted' => $fieldQuoted,
                'byteOffset' => $offset,
                'byteHex' => sprintf('%02X', $ordinal),
                'codepoint' => $codepoint,
                'name' => $this->controlByteName($ordinal),
                'byteSampleHex' => $this->byteSampleHex($text, $offset),
                'textSample' => $this->escapedTextSample($text, $offset),
            ];
        }

        ksort($byCodepoint);

        return [
            'policy' => 'report-c0-del-controls-except-ht-lf-cr',
            'sourcePath' => $sourcePath,
            'totalCount' => $totalCount,
            'nulCount' => $nulCount,
            'quotedFieldCount' => $quotedFieldCount,
            'unquotedFieldCount' => $unquotedFieldCount,
            'sampleLimit' => self::CONTROL_CHARACTER_SAMPLE_LIMIT,
            'sampleCount' => count($samples),
            'truncated' => $totalCount > count($samples),
            'byCodepoint' => $byCodepoint,
            'samples' => $samples,
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
