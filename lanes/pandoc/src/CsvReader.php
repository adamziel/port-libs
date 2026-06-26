<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class CsvReader
{
    /**
     * @param array{header?: bool|null, delimiter?: string|null} $options
     */
    public function __construct(private readonly string $format = 'csv', private readonly array $options = [])
    {
    }

    public function read(string $source): AstNode
    {
        [$source, $delimiter] = $this->sourceAndDelimiter($source);
        $quote = $this->format === 'tsv' ? null : '"';
        $rows = $this->parseRows($source, $delimiter, $quote);
        $columnCount = $this->maxColumnCount($rows);
        $raggedRows = $this->raggedRowCount($rows, $columnCount);
        $rows = $this->normalizeRows($rows, $columnCount);
        $hasHeader = (bool) ($this->options['header'] ?? true);
        $metadata = [
            'csvFormat' => $this->format,
            'csvDelimiter' => $delimiter,
            'csvRowCount' => count($rows),
            'csvColumnCount' => $columnCount,
            'csvDataRowCount' => $hasHeader ? max(0, count($rows) - 1) : count($rows),
            'csvHeader' => $hasHeader,
            'csvRaggedRowCount' => $raggedRows,
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
            fn (array $row): AstNode => $this->tableRow($row, false),
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

        return [$source, ','];
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
    private function parseRows(string $source, string $delimiter, ?string $quote): array
    {
        $source = preg_replace('/^\xEF\xBB\xBF/', '', $source) ?? $source;
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
        $length = strlen($source);

        for ($offset = 0; $offset < $length; $offset++) {
            $char = $source[$offset];
            if ($quote !== null && $inQuote) {
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
                continue;
            }

            $field .= $char;
            $afterClosingQuote = false;
            $lastWasTerminator = false;
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
    private function tableRow(array $cells, bool $header): AstNode
    {
        return new AstNode('table_row', [], array_map(
            fn (string $cell): AstNode => $this->tableCell($cell, $header),
            $cells
        ));
    }

    private function tableCell(string $value, bool $header): AstNode
    {
        return new AstNode('table_cell', [
            'text' => $value,
            'header' => $header,
        ], [
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
