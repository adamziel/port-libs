<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRealUpstreamBTreeIndexDynamicCorpus
{
    /**
     * @param list<array<string, mixed>> $rows
     * @param list<string> $columns
     * @return array{page:string,records:list<list<mixed>>,column_count:int,row_count:int,source:string}
     */
    public static function buildIndexLeaf(array $rows, array $columns, int $pageSize = 512): array
    {
        if ($rows === []) {
            throw new \InvalidArgumentException('SQLite upstream index corpus requires at least one row');
        }
        if ($columns === []) {
            throw new \InvalidArgumentException('SQLite upstream index corpus requires at least one indexed column');
        }

        $records = [];
        foreach ($rows as $row) {
            $record = [];
            foreach ($columns as $column) {
                if (!array_key_exists($column, $row)) {
                    throw new \InvalidArgumentException("SQLite upstream index corpus row is missing indexed column {$column}");
                }
                $record[] = $row[$column];
            }
            $record[] = $row['rowid'] ?? count($records) + 1;
            $records[] = $record;
        }

        usort($records, self::compareRecords(...));

        $cells = array_map(
            static fn (array $record): string => SQLiteIndexCell::encode(SQLiteRecord::encode($record), $pageSize),
            $records,
        );

        return [
            'page' => SQLiteIndexLeafPage::assemble($cells, $pageSize),
            'records' => $records,
            'column_count' => count($columns),
            'row_count' => count($records),
            'source' => 'index.test index-4.1 through index-4.13 and index2.test index2-2.1/index2-2.2',
        ];
    }

    /**
     * @return list<array{cnt:int,power:int,rowid:int}>
     */
    public static function powerRows(): array
    {
        $rows = [];
        for ($i = 1; $i < 20; $i++) {
            $rows[] = [
                'cnt' => $i,
                'power' => 1 << $i,
                'rowid' => $i,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, int>>
     */
    public static function wideRows(int $rowCount = 101, int $columnCount = 1000): array
    {
        if ($rowCount < 1 || $columnCount < 1) {
            throw new \InvalidArgumentException('SQLite upstream wide index corpus dimensions must be positive');
        }

        $rows = [];
        for ($rowIndex = 0; $rowIndex < $rowCount; $rowIndex++) {
            $row = ['rowid' => $rowIndex + 1];
            $base = $rowIndex === 0 ? 0 : $rowIndex * 10000;
            for ($column = 1; $column <= $columnCount; $column++) {
                $row['c' . $column] = $base + $column;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    public static function wideIndexColumns(int $columnCount = 1000): array
    {
        if ($columnCount < 1) {
            throw new \InvalidArgumentException('SQLite upstream wide index column count must be positive');
        }

        $columns = [];
        for ($column = 1; $column <= $columnCount; $column++) {
            $columns[] = 'c' . $column;
        }

        return $columns;
    }

    /**
     * @return list<list<mixed>>
     */
    public static function scanIndexLeaf(string $page, int $pageSize = 512, int $textEncoding = 1): array
    {
        $header = SQLiteBTreePageHeader::parsePage($page, $pageSize);
        if ($header->pageType !== 'index-leaf') {
            throw new \InvalidArgumentException('SQLite upstream index corpus requires an index leaf page');
        }

        return array_map(
            static fn (SQLiteIndexCell $cell): array => $cell->record($textEncoding)->values,
            SQLiteIndexCell::parsePageCells($page, $header),
        );
    }

    /**
     * @param list<list<mixed>> $records
     * @param list<mixed> $prefix
     * @return list<list<mixed>>
     */
    public static function seekByPrefix(array $records, array $prefix): array
    {
        if ($prefix === []) {
            throw new \InvalidArgumentException('SQLite upstream index corpus seek prefix cannot be empty');
        }

        return array_values(array_filter(
            $records,
            static function (array $record) use ($prefix): bool {
                foreach ($prefix as $index => $value) {
                    if (!array_key_exists($index, $record) || $record[$index] !== $value) {
                        return false;
                    }
                }

                return true;
            },
        ));
    }

    /**
     * @param list<array{name:string,column:string,active:bool}> $events
     * @return array<string, string>
     */
    public static function activeIndexCatalog(array $events): array
    {
        $catalog = [];
        foreach ($events as $event) {
            if (!isset($event['name'], $event['column'], $event['active'])) {
                throw new \InvalidArgumentException('SQLite upstream index corpus catalog events require name, column, and active keys');
            }
            if ($event['active']) {
                $catalog[$event['name']] = $event['column'];
            } else {
                unset($catalog[$event['name']]);
            }
        }
        ksort($catalog);

        return $catalog;
    }

    /**
     * @param list<mixed> $left
     * @param list<mixed> $right
     */
    private static function compareRecords(array $left, array $right): int
    {
        $count = max(count($left), count($right));
        for ($index = 0; $index < $count; $index++) {
            $leftValue = $left[$index] ?? null;
            $rightValue = $right[$index] ?? null;
            if ($leftValue === $rightValue) {
                continue;
            }
            if ($leftValue === null) {
                return -1;
            }
            if ($rightValue === null) {
                return 1;
            }
            if (is_int($leftValue) && is_int($rightValue)) {
                return $leftValue <=> $rightValue;
            }

            return strcmp((string) $leftValue, (string) $rightValue);
        }

        return 0;
    }
}
