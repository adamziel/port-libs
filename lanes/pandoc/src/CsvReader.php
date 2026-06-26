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
     *     encoding?: string|null
     * } $options
     */
    public function __construct(private readonly string $format = 'csv', private readonly array $options = [])
    {
    }

    public function read(string $source): AstNode
    {
        $decoded = $this->decodeSource($source);
        $source = $decoded['source'];
        [$source, $delimiter] = $this->sourceAndDelimiter($source);
        $quote = $this->quoteChar();
        $escape = $this->escapeChar();
        $comment = $this->commentChar();
        $rows = $this->parseRows($source, $delimiter, $quote, $escape, $comment);
        $columnCount = $this->maxColumnCount($rows);
        $raggedRows = $this->raggedRowCount($rows, $columnCount);
        $rows = $this->normalizeRows($rows, $columnCount);
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
            'csvRaggedRowCount' => $raggedRows,
            'csvColumnTypes' => $columnTypes,
        ];

        if ($rows === []) {
            return new AstNode('document', ['meta' => $metadata], []);
        }

        $headRow = $hasHeader ? array_shift($rows) : [];
        $tableChildren = [];
        if ($headRow !== []) {
            $tableChildren[] = new AstNode('table_head', [], [
                $this->tableRow($headRow, true),
            ]);
        }
        $tableChildren[] = new AstNode('table_body', [], array_map(
            fn (array $row): AstNode => $this->tableRow($row, false, $columnTypes),
            $rows
        ));

        $metadata['csvHeaderColumnCount'] = count($headRow);

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
    private function sourceAndDelimiter(string $source): array
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

        return [$source, $this->detectDelimiter($normalized)];
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

    private function detectDelimiter(string $source): string
    {
        $candidates = [',', ';', "\t", '|'];
        $best = ',';
        $bestScore = -1;
        $lines = array_values(array_filter(
            array_slice(explode("\n", $source), 0, 12),
            static fn (string $line): bool => trim($line) !== '' && stripos(trim($line), 'sep=') !== 0
        ));
        foreach ($candidates as $candidate) {
            $counts = [];
            foreach ($lines as $line) {
                $counts[] = substr_count($line, $candidate) + 1;
            }
            $positive = array_values(array_filter($counts, static fn (int $count): bool => $count > 1));
            if ($positive === []) {
                continue;
            }
            $frequency = array_count_values($positive);
            arsort($frequency);
            $commonColumns = (int) array_key_first($frequency);
            $commonRows = (int) reset($frequency);
            $score = ($commonColumns * 100) + ($commonRows * 10) - (max($positive) - min($positive));
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }

        return $best;
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
     * @return list<list<string>>
     */
    private function parseRows(string $source, string $delimiter, ?string $quote, ?string $escape, ?string $comment): array
    {
        $source = str_replace(["\r\n", "\r"], "\n", $source);
        if ($source === '') {
            return [];
        }

        $rows = [];
        $row = [];
        $field = '';
        $quoted = false;
        $inQuote = false;
        $afterClosingQuote = false;
        $lastWasTerminator = false;
        $lineStart = true;
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
                $lastWasTerminator = true;
                $lineStart = true;
                continue;
            }
            if ($quote !== null && $inQuote) {
                if ($escape !== null && $char === $escape && $offset + 1 < $length) {
                    $field .= $source[$offset + 1];
                    $offset++;
                    continue;
                }
                if ($char === $quote) {
                    if ($offset + 1 < $length && $source[$offset + 1] === $quote) {
                        $field .= $quote;
                        $offset++;
                        continue;
                    }
                    $inQuote = false;
                    $afterClosingQuote = true;
                    continue;
                }
                $field .= $char;
                continue;
            }

            if ($quote !== null && $char === $quote && trim($field) === '') {
                $field = '';
                $quoted = true;
                $inQuote = true;
                $afterClosingQuote = false;
                $lastWasTerminator = false;
                $lineStart = false;
                continue;
            }

            if ($quote !== null && $quoted && $afterClosingQuote && ($char === ' ' || $char === "\t")) {
                continue;
            }

            if ($char === $delimiter) {
                $row[] = $this->finalizeField($field, $quoted);
                $field = '';
                $quoted = false;
                $afterClosingQuote = false;
                $lastWasTerminator = false;
                $lineStart = false;
                continue;
            }

            if ($char === "\n") {
                $row[] = $this->finalizeField($field, $quoted);
                $rows[] = $row;
                $row = [];
                $field = '';
                $quoted = false;
                $afterClosingQuote = false;
                $lastWasTerminator = true;
                $lineStart = true;
                continue;
            }

            $field .= $char;
            $afterClosingQuote = false;
            $lastWasTerminator = false;
            if ($char !== ' ' && $char !== "\t") {
                $lineStart = false;
            }
        }

        if (!$lastWasTerminator || $row !== [] || $field !== '') {
            $row[] = $this->finalizeField($field, $quoted);
            $rows[] = $row;
        }

        return $rows;
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
     * @param list<list<string>> $rows
     */
    private function raggedRowCount(array $rows, int $columnCount): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if (count($row) !== $columnCount) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param list<list<string>> $rows
     * @return list<list<string>>
     */
    private function normalizeRows(array $rows, int $columnCount): array
    {
        return array_map(
            static fn (array $row): array => array_pad(array_slice($row, 0, $columnCount), $columnCount, ''),
            $rows
        );
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
     * @param list<string> $cells
     */
    private function tableRow(array $cells, bool $header, array $columnTypes = []): AstNode
    {
        $index = 0;

        return new AstNode('table_row', [], array_map(
            function (string $cell) use ($header, $columnTypes, &$index): AstNode {
                $type = !$header ? (string) ($columnTypes[$index] ?? '') : '';
                $index++;

                return $this->tableCell($cell, $header, $type);
            },
            $cells
        ));
    }

    private function tableCell(string $value, bool $header, string $type = ''): AstNode
    {
        $attrs = [
            'text' => $value,
            'header' => $header,
        ];
        if (!$header && $type !== '') {
            $attrs['htmlAttributes'] = ['data-csv-type' => $type];
        }

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
