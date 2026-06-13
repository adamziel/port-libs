<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DelimitedTextReader
{
    private const INPUT_PREFIX_BYTE_LIMIT = 64;
    private const INPUT_PREFIX_PREVIEW_BYTE_LIMIT = 32;

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
        $inputPrefix = $this->inputPrefixReview($text);
        $parseText = $this->inputTextAfterSupportedPrefix($text, $inputPrefix);
        $formatResolution = $this->formatResolution($format, $parseText, $options);
        $format = $formatResolution['format'];
        $delimiter = $formatResolution['delimiter'];
        $formatInference = $formatResolution['formatInference'];
        $inputPrefix['formatContext'] = $this->formatContext($formatResolution, $options);
        $hasHeader = $this->headerOption($options);

        $rows = $this->parseRows($parseText, $delimiter);
        if ($rows === []) {
            return new AstNode('document', [
                'sourceFormat' => $format,
                'delimitedText' => $this->reviewPacket($format, $delimiter, [], [], $hasHeader, $formatInference, $inputPrefix),
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
            'delimitedText' => $this->reviewPacket($format, $delimiter, $sourceHeader === null ? $rows : [$sourceHeader, ...$rows], $widths, $hasHeader, $formatInference, $inputPrefix),
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
     * @param array<string, mixed> $inputPrefix
     * @return array<string, mixed>
     */
    private function reviewPacket(string $format, string $delimiter, array $rows, array $widths, bool $hasHeader, array $formatInference, array $inputPrefix): array
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
            'inputPrefix' => $inputPrefix,
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
            'diagnostics' => $this->reviewDiagnostics($hasHeader, $formatInference, $inputPrefix),
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
     * @param array<string, mixed> $inputPrefix
     * @return list<array<string, mixed>>
     */
    private function reviewDiagnostics(bool $hasHeader, array $formatInference, array $inputPrefix): array
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

        if (($inputPrefix['bom'] ?? 'none') === 'utf-8') {
            $diagnostics[] = [
                'code' => 'delimited-text-utf8-bom',
                'severity' => 'info',
                'message' => 'UTF-8 byte-order mark was detected and skipped before parsing the first row.',
                'byteCount' => $inputPrefix['bomByteCount'] ?? 0,
            ];
        }

        if (($inputPrefix['leadingWhitespaceByteCount'] ?? 0) > 0) {
            $diagnostics[] = [
                'code' => 'delimited-text-leading-whitespace',
                'severity' => 'info',
                'message' => 'Leading whitespace-only lines were skipped before parsing the first row.',
                'byteCount' => $inputPrefix['leadingWhitespaceByteCount'],
                'lineCount' => $inputPrefix['leadingWhitespaceLineCount'] ?? 0,
            ];
        }

        if (($inputPrefix['nullByteCount'] ?? 0) > 0) {
            $diagnostics[] = [
                'code' => 'delimited-text-null-byte',
                'severity' => 'warning',
                'message' => 'NUL bytes were detected in the bounded input-prefix inspection window.',
                'count' => $inputPrefix['nullByteCount'],
            ];
        }

        if (($inputPrefix['controlCharacterCount'] ?? 0) > 0) {
            $diagnostics[] = [
                'code' => 'delimited-text-control-character',
                'severity' => 'warning',
                'message' => 'Non-whitespace control characters were detected in the bounded input-prefix inspection window.',
                'count' => $inputPrefix['controlCharacterCount'],
            ];
        }

        return $diagnostics;
    }

    /**
     * @return array<string, mixed>
     */
    private function inputPrefixReview(string $text): array
    {
        $bomByteCount = str_starts_with($text, "\xEF\xBB\xBF") ? 3 : 0;
        $leadingWhitespaceByteCount = $this->leadingWhitespaceLinePrefixByteCount(substr($text, $bomByteCount));
        $firstContentOffset = $bomByteCount + $leadingWhitespaceByteCount;
        $inspectedByteCount = min(strlen($text), self::INPUT_PREFIX_BYTE_LIMIT);
        $previewByteCount = min(strlen($text), self::INPUT_PREFIX_PREVIEW_BYTE_LIMIT);
        $controlReview = $this->controlCharacterReview(substr($text, 0, $inspectedByteCount));

        return [
            'encoding' => 'utf-8',
            'bom' => $bomByteCount > 0 ? 'utf-8' : 'none',
            'bomByteCount' => $bomByteCount,
            'leadingWhitespaceByteCount' => $leadingWhitespaceByteCount,
            'leadingWhitespaceLineCount' => $this->lineBreakCount(substr($text, $bomByteCount, $leadingWhitespaceByteCount)),
            'firstContentOffset' => $firstContentOffset,
            'firstContentLine' => $this->lineBreakCount(substr($text, 0, $firstContentOffset)) + 1,
            'inputByteCount' => strlen($text),
            'inspectionByteLimit' => self::INPUT_PREFIX_BYTE_LIMIT,
            'inspectedByteCount' => $inspectedByteCount,
            'inspectionTruncated' => strlen($text) > self::INPUT_PREFIX_BYTE_LIMIT,
            'prefixPreviewByteLimit' => self::INPUT_PREFIX_PREVIEW_BYTE_LIMIT,
            'prefixPreviewByteCount' => $previewByteCount,
            'prefixPreviewHex' => bin2hex(substr($text, 0, $previewByteCount)),
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
        $firstContentOffset = (int) ($inputPrefix['firstContentOffset'] ?? 0);

        return $firstContentOffset > 0 ? substr($text, $firstContentOffset) : $text;
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
    private function controlCharacterReview(string $text): array
    {
        $nullByteCount = 0;
        $nullBytes = [];
        $controlCharacterCount = 0;
        $controlCharacters = [];
        $length = strlen($text);
        for ($offset = 0; $offset < $length; $offset++) {
            $byte = ord($text[$offset]);
            if ($byte === 0) {
                $nullByteCount++;
                if (count($nullBytes) < 8) {
                    $nullBytes[] = [
                        'offset' => $offset,
                        'hex' => '00',
                        'name' => 'NUL',
                    ];
                }
                continue;
            }

            if (!$this->isNonWhitespaceControlByte($byte)) {
                continue;
            }

            $controlCharacterCount++;
            if (count($controlCharacters) < 8) {
                $controlCharacters[] = [
                    'offset' => $offset,
                    'hex' => strtoupper(str_pad(dechex($byte), 2, '0', STR_PAD_LEFT)),
                    'name' => $this->controlCharacterName($byte),
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

    private function isNonWhitespaceControlByte(int $byte): bool
    {
        return ($byte >= 1 && $byte <= 8)
            || $byte === 11
            || $byte === 12
            || ($byte >= 14 && $byte <= 31)
            || $byte === 127;
    }

    private function controlCharacterName(int $byte): string
    {
        $names = [
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
        ];

        return $names[$byte] ?? 'U+' . strtoupper(str_pad(dechex($byte), 4, '0', STR_PAD_LEFT));
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
        $extensionFormat = $extension === null ? null : PandocFormatRegistry::inferTabularDataFormatFromExtension($extension);
        $sourcePathFormat = $sourcePathExtension === null || $sourcePathExtension === ''
            ? null
            : PandocFormatRegistry::inferTabularDataFormatFromExtension($sourcePathExtension);
        $selectedFormat = $formatResolution['format'];
        $knownFormats = array_values(array_filter([$extensionFormat, $sourcePathFormat], static fn (?string $format): bool => $format !== null));

        return [
            'requestedFormat' => (string) ($formatResolution['formatInference']['requestedFormat'] ?? $selectedFormat),
            'selectedFormat' => $selectedFormat,
            'sourcePath' => $sourcePath,
            'sourcePathExtension' => $sourcePathExtension === '' ? null : $sourcePathExtension,
            'sourcePathFormat' => $sourcePathFormat,
            'extension' => $extension,
            'extensionFormat' => $extensionFormat,
            'formatMatchesContext' => $knownFormats === [] ? null : in_array($selectedFormat, $knownFormats, true),
        ];
    }

    private function stripBom(string $text): string
    {
        return str_starts_with($text, "\xEF\xBB\xBF") ? substr($text, 3) : $text;
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
