<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteDatabase
{
    private function __construct(
        private readonly string $bytes,
        public readonly SQLiteHeader $header,
    ) {
    }

    public static function fromBytes(string $bytes): self
    {
        $header = SQLiteHeader::parse($bytes);
        if (strlen($bytes) < $header->pageSize) {
            throw new \InvalidArgumentException('SQLite database reader requires a complete first page image');
        }

        return new self($bytes, $header);
    }

    public static function fromFile(string $path): self
    {
        $bytes = @file_get_contents($path);
        if ($bytes === false) {
            throw new \InvalidArgumentException("Unable to read SQLite database file: {$path}");
        }

        return self::fromBytes($bytes);
    }

    public function pageCount(): int
    {
        return intdiv(strlen($this->bytes), $this->header->pageSize);
    }

    public function usablePageSize(): int
    {
        $usableSize = $this->header->pageSize - $this->header->reservedSpace;
        if ($usableSize < 480) {
            throw new \InvalidArgumentException('SQLite usable page size is too small');
        }

        return $usableSize;
    }

    public function page(int $pageNumber): string
    {
        if ($pageNumber < 1) {
            throw new \InvalidArgumentException('SQLite page numbers are one-based');
        }

        $offset = ($pageNumber - 1) * $this->header->pageSize;
        if ($offset + $this->header->pageSize > strlen($this->bytes)) {
            throw new \InvalidArgumentException("SQLite page {$pageNumber} is not present in the database image");
        }

        return substr($this->bytes, $offset, $this->header->pageSize);
    }

    public function pageHeader(int $pageNumber): SQLiteBTreePageHeader
    {
        return SQLiteBTreePageHeader::parsePage(
            $this->page($pageNumber),
            $this->header->pageSize,
            $pageNumber === 1 ? 100 : 0,
        );
    }

    /**
     * @return list<SQLiteSchemaRecord>
     */
    public function schemaRecords(): array
    {
        $records = [];
        foreach ($this->tableLeafCells(1) as $cell) {
            $records[] = SQLiteSchemaRecord::fromTableLeafCell($cell, $this->header->textEncoding);
        }

        return $records;
    }

    public function tableRootPage(string $tableName): ?int
    {
        foreach ($this->schemaRecords() as $record) {
            if ($record->isTable($tableName)) {
                return $record->rootPage;
            }
        }

        return null;
    }

    public function tablePageHeader(string $tableName): ?SQLiteBTreePageHeader
    {
        $rootPage = $this->tableRootPage($tableName);
        if ($rootPage === null) {
            return null;
        }

        return $this->pageHeader($rootPage);
    }

    /**
     * @return list<SQLiteTableLeafCell>
     */
    public function tableLeafCells(int $rootPageNumber, ?int $limit = null): array
    {
        $visited = [];
        $cells = [];
        $this->collectTableLeafCells($rootPageNumber, $visited, $cells, $limit);

        return $cells;
    }

    /**
     * @return list<SQLiteTableRow>
     */
    public function tableRows(int $rootPageNumber, ?int $limit = null): array
    {
        $rows = [];
        foreach ($this->tableLeafCells($rootPageNumber, $limit) as $cell) {
            $rows[] = SQLiteTableRow::fromTableLeafCell($cell, $this->header->textEncoding);
        }

        return $rows;
    }

    /**
     * @return list<SQLiteTableRow>
     */
    public function tableRowsByName(string $tableName, ?int $limit = null): array
    {
        $rootPage = $this->tableRootPage($tableName);
        if ($rootPage === null) {
            return [];
        }

        return $this->tableRows($rootPage, $limit);
    }

    /**
     * @return list<SQLiteSequenceRecord>
     */
    public function sqliteSequenceRecords(?int $limit = null): array
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite sqlite_sequence row limit cannot be negative');
        }

        $rootPage = $this->tableRootPage('sqlite_sequence');
        if ($rootPage === null || $limit === 0) {
            return [];
        }

        $records = [];
        foreach ($this->tableRows($rootPage, $limit) as $row) {
            $records[] = SQLiteSequenceRecord::fromTableRow($row);
        }

        return $records;
    }

    public function sqliteSequenceForTable(string $tableName): ?SQLiteSequenceRecord
    {
        foreach ($this->sqliteSequenceRecords() as $record) {
            if ($record->matchesTable($tableName)) {
                return $record;
            }
        }

        return null;
    }

    public function autoincrementStateForTable(string $tableName): SQLiteAutoincrementState
    {
        $tableRootPage = $this->tableRootPage($tableName);
        if ($tableRootPage === null) {
            throw new \InvalidArgumentException("SQLite table {$tableName} is not present");
        }

        if ($this->tableRootPage('sqlite_sequence') === null) {
            throw new \InvalidArgumentException('SQLite AUTOINCREMENT allocation requires sqlite_sequence');
        }

        $largestTableRowId = null;
        foreach ($this->tableLeafCells($tableRootPage) as $cell) {
            if ($largestTableRowId === null || $cell->rowId > $largestTableRowId) {
                $largestTableRowId = $cell->rowId;
            }
        }

        $sequenceRecord = null;
        $largestSequenceRowId = 0;
        foreach ($this->sqliteSequenceRecords() as $record) {
            if ($record->rowId > $largestSequenceRowId) {
                $largestSequenceRowId = $record->rowId;
            }
            if ($sequenceRecord === null && $record->matchesTable($tableName)) {
                $sequenceRecord = $record;
            }
        }

        return SQLiteAutoincrementState::fromDatabaseState(
            $tableName,
            $sequenceRecord,
            $largestTableRowId,
            $largestSequenceRowId,
        );
    }

    public function tableRowByRowId(int $rootPageNumber, int $rowId): ?SQLiteTableRow
    {
        $visited = [];
        $cell = $this->findTableLeafCellByRowId($rootPageNumber, $rowId, $visited);

        return $cell === null ? null : SQLiteTableRow::fromTableLeafCell($cell, $this->header->textEncoding);
    }

    public function tableRowByRowIdByName(string $tableName, int $rowId): ?SQLiteTableRow
    {
        $rootPage = $this->tableRootPage($tableName);
        if ($rootPage === null) {
            return null;
        }

        return $this->tableRowByRowId($rootPage, $rowId);
    }

    /**
     * @return list<SQLiteIndexCell>
     */
    public function indexCells(int $rootPageNumber, ?int $limit = null): array
    {
        $visited = [];
        $cells = [];
        $this->collectIndexCells($rootPageNumber, $visited, $cells, $limit);

        return $cells;
    }

    /**
     * @return list<SQLiteSchemaRecord>
     */
    public function indexRecordsForTable(string $tableName): array
    {
        $indexes = [];
        foreach ($this->schemaRecords() as $record) {
            if ($record->isIndexForTable($tableName) && $record->rootPage !== null) {
                $indexes[] = $record;
            }
        }

        return $indexes;
    }

    public function indexRootPageForColumn(string $tableName, string $columnName): ?int
    {
        $lookup = $this->indexLookupForColumn($tableName, $columnName);

        return $lookup['rootPage'] ?? null;
    }

    public function indexRootPageForPointLookup(string $tableName, string $columnName, mixed $value): ?int
    {
        $lookup = $this->indexLookupForColumn($tableName, $columnName, $value, true);

        return $lookup['rootPage'] ?? null;
    }

    /**
     * @param list<mixed> $values
     */
    public function indexRootPageForInLookup(string $tableName, string $columnName, array $values): ?int
    {
        $lookup = $this->indexLookupForColumnInList($tableName, $columnName, $values);

        return $lookup['rootPage'] ?? null;
    }

    /**
     * @param list<mixed> $values
     */
    public function indexRootPageForLowercaseInLookup(string $tableName, string $columnName, array $values): ?int
    {
        $lookup = $this->indexLookupForLowerExpressionColumnInList($tableName, $columnName, $values);

        return $lookup['rootPage'] ?? null;
    }

    /**
     * @param list<mixed> $values
     */
    public function indexRootPageForUppercaseInLookup(string $tableName, string $columnName, array $values): ?int
    {
        $lookup = $this->indexLookupForUpperExpressionColumnInList($tableName, $columnName, $values);

        return $lookup['rootPage'] ?? null;
    }

    /**
     * @param array<string, mixed> $equalityConstraints
     */
    public function indexRootPageForPointLookupWithConstraints(
        string $tableName,
        string $columnName,
        mixed $value,
        array $equalityConstraints,
    ): ?int {
        $equalityConstraints[$columnName] = $value;
        $lookup = $this->indexLookupForColumn($tableName, $columnName, $value, true, $equalityConstraints);

        return $lookup['rootPage'] ?? null;
    }

    public function indexRootPageForLowercasePointLookup(string $tableName, string $columnName, string $value): ?int
    {
        $lookup = $this->indexLookupForLowerExpressionColumn($tableName, $columnName, $value);

        return $lookup['rootPage'] ?? null;
    }

    public function indexRootPageForUppercasePointLookup(string $tableName, string $columnName, string $value): ?int
    {
        $lookup = $this->indexLookupForUpperExpressionColumn($tableName, $columnName);

        return $lookup['rootPage'] ?? null;
    }

    public function indexRootPageForTrimmedPointLookup(
        string $tableName,
        string $columnName,
        string $value,
        string $functionName = 'trim',
        ?string $characters = null,
    ): ?int {
        self::sqliteTrim($value, $functionName, $characters);
        $lookup = $this->indexLookupForTrimExpressionColumn($tableName, $columnName, $functionName, $characters);

        return $lookup['rootPage'] ?? null;
    }

    public function indexRootPageForLengthPointLookup(string $tableName, string $columnName, int $length): ?int
    {
        $lookup = $this->indexLookupForLengthExpressionColumn($tableName, $columnName, $length);

        return $lookup['rootPage'] ?? null;
    }

    public function indexRootPageForIntegerCastPointLookup(string $tableName, string $columnName, int $value): ?int
    {
        $lookup = $this->indexLookupForIntegerCastExpressionColumn($tableName, $columnName, $value);

        return $lookup['rootPage'] ?? null;
    }

    /**
     * @param list<mixed> $values
     */
    public function indexRootPageForIntegerCastInLookup(string $tableName, string $columnName, array $values): ?int
    {
        $lookup = $this->indexLookupForIntegerCastExpressionColumnInList($tableName, $columnName, $values);

        return $lookup['rootPage'] ?? null;
    }

    public function indexRootPageForIntegerCastRangeLookup(
        string $tableName,
        string $columnName,
        ?int $lowerInclusive = null,
        ?int $upperBound = null,
        bool $upperInclusive = false,
    ): ?int {
        $lookup = $this->indexLookupForIntegerCastExpressionColumnRange(
            $tableName,
            $columnName,
            $lowerInclusive,
            $upperBound,
            $upperInclusive,
        );

        return $lookup['rootPage'] ?? null;
    }

    public function indexRootPageForJsonExtractPointLookup(
        string $tableName,
        string $columnName,
        string $path,
        mixed $value,
    ): ?int {
        self::sqliteJsonScalar($value);
        $lookup = $this->indexLookupForJsonExtractExpressionColumn($tableName, $columnName, $path);

        return $lookup['rootPage'] ?? null;
    }

    public function indexRootPageForJsonValueOperatorPointLookup(
        string $tableName,
        string $columnName,
        string $path,
        mixed $value,
    ): ?int {
        self::sqliteJsonTextValue($value);
        $lookup = $this->indexLookupForJsonValueOperatorExpressionColumn($tableName, $columnName, $path);

        return $lookup['rootPage'] ?? null;
    }

    /**
     * @param list<mixed> $values
     */
    public function indexRootPageForJsonValueOperatorInLookup(
        string $tableName,
        string $columnName,
        string $path,
        array $values,
    ): ?int {
        if ($values === []) {
            return null;
        }

        self::sqliteJsonTextValueList($values);
        $lookup = $this->indexLookupForJsonValueOperatorExpressionColumn($tableName, $columnName, $path);

        return $lookup['rootPage'] ?? null;
    }

    public function indexRootPageForJsonValueOperatorRangeLookup(
        string $tableName,
        string $columnName,
        string $path,
        mixed $lowerInclusive = null,
        mixed $upperBound = null,
        bool $upperInclusive = false,
    ): ?int {
        $lowerKey = $lowerInclusive === null ? null : self::sqliteJsonTextValue($lowerInclusive);
        $upperKey = $upperBound === null ? null : self::sqliteJsonTextValue($upperBound);
        $lookup = $this->indexLookupForJsonValueOperatorExpressionColumnRange(
            $tableName,
            $columnName,
            $path,
            $lowerKey,
            $upperKey,
            $upperInclusive,
        );

        return $lookup['rootPage'] ?? null;
    }

    /**
     * @param list<mixed> $values
     */
    public function indexRootPageForJsonExtractInLookup(
        string $tableName,
        string $columnName,
        string $path,
        array $values,
    ): ?int {
        $lookupValues = self::sqliteJsonScalarList($values);
        if (!self::containsNonNullValue($lookupValues)) {
            return null;
        }

        $lookup = $this->indexLookupForJsonExtractExpressionColumn($tableName, $columnName, $path);

        return $lookup['rootPage'] ?? null;
    }

    public function indexRootPageForJsonExtractRangeLookup(
        string $tableName,
        string $columnName,
        string $path,
        mixed $lowerInclusive = null,
        mixed $upperBound = null,
        bool $upperInclusive = false,
    ): ?int {
        $lowerKey = $lowerInclusive === null ? null : self::sqliteJsonScalar($lowerInclusive);
        $upperKey = $upperBound === null ? null : self::sqliteJsonScalar($upperBound);
        $lookup = $this->indexLookupForJsonExtractExpressionColumnRange(
            $tableName,
            $columnName,
            $path,
            $lowerKey,
            $upperKey,
            $upperInclusive,
        );

        return $lookup['rootPage'] ?? null;
    }

    public function indexRootPageForLengthRangeLookup(
        string $tableName,
        string $columnName,
        ?int $lowerInclusive = null,
        ?int $upperBound = null,
        bool $upperInclusive = false,
    ): ?int {
        $lookup = $this->indexLookupForLengthExpressionColumnRange(
            $tableName,
            $columnName,
            $lowerInclusive,
            $upperBound,
            $upperInclusive,
        );

        return $lookup['rootPage'] ?? null;
    }

    /**
     * @param list<mixed> $lengths
     */
    public function indexRootPageForLengthInLookup(string $tableName, string $columnName, array $lengths): ?int
    {
        $lookup = $this->indexLookupForLengthExpressionColumnInList($tableName, $columnName, $lengths);

        return $lookup['rootPage'] ?? null;
    }

    public function indexRootPageForSubstringPointLookup(
        string $tableName,
        string $columnName,
        int $start,
        ?int $length,
        string $value,
    ): ?int {
        $lookup = $this->indexLookupForSubstringExpressionColumn($tableName, $columnName, $start, $length);

        return $lookup['rootPage'] ?? null;
    }

    /**
     * @param list<mixed> $values
     */
    public function indexRootPageForSubstringInLookup(
        string $tableName,
        string $columnName,
        int $start,
        ?int $length,
        array $values,
    ): ?int {
        $lookup = $this->indexLookupForSubstringExpressionColumnInList($tableName, $columnName, $start, $length, $values);

        return $lookup['rootPage'] ?? null;
    }

    public function indexRootPageForLowercaseRangeLookup(
        string $tableName,
        string $columnName,
        ?string $lowerInclusive = null,
        ?string $upperBound = null,
        bool $upperInclusive = false,
    ): ?int {
        $lookup = $this->indexLookupForLowerExpressionColumnRange(
            $tableName,
            $columnName,
            $lowerInclusive,
            $upperBound,
            $upperInclusive,
        );

        return $lookup['rootPage'] ?? null;
    }

    public function indexRootPageForUppercaseRangeLookup(
        string $tableName,
        string $columnName,
        ?string $lowerInclusive = null,
        ?string $upperBound = null,
        bool $upperInclusive = false,
    ): ?int {
        $lookup = $this->indexLookupForUpperExpressionColumnRange(
            $tableName,
            $columnName,
            $lowerInclusive,
            $upperBound,
            $upperInclusive,
        );

        return $lookup['rootPage'] ?? null;
    }

    public function indexRootPageForRangeLookup(
        string $tableName,
        string $columnName,
        mixed $lowerInclusive = null,
        mixed $upperBound = null,
        bool $upperInclusive = false,
    ): ?int {
        $lookup = $this->indexLookupForColumnRange($tableName, $columnName, $lowerInclusive, $upperBound, $upperInclusive);

        return $lookup['rootPage'] ?? null;
    }

    /**
     * @param non-empty-array<string, mixed> $equalityPrefix
     */
    public function indexRootPageForPrefixRangeLookup(
        string $tableName,
        array $equalityPrefix,
        string $rangeColumnName,
        mixed $lowerInclusive = null,
        mixed $upperBound = null,
        bool $upperInclusive = false,
    ): ?int {
        if ($equalityPrefix === []) {
            throw new \InvalidArgumentException('SQLite index prefix range lookup requires at least one equality column');
        }

        $lookup = $this->indexLookupForColumnPrefixRange(
            $tableName,
            array_keys($equalityPrefix),
            array_values($equalityPrefix),
            $rangeColumnName,
            $lowerInclusive,
            $upperBound,
            $upperInclusive,
        );

        return $lookup['rootPage'] ?? null;
    }

    /**
     * @param non-empty-array<string, mixed> $columnValues
     */
    public function indexRootPageForPointLookupColumns(string $tableName, array $columnValues): ?int
    {
        if ($columnValues === []) {
            throw new \InvalidArgumentException('SQLite index prefix lookup requires at least one column');
        }
        if (count($columnValues) === 1) {
            $columnName = array_key_first($columnValues);

            return $this->indexRootPageForPointLookup($tableName, $columnName, $columnValues[$columnName]);
        }

        $lookup = $this->indexLookupForColumnPrefix(
            $tableName,
            array_keys($columnValues),
            array_values($columnValues),
        );

        return $lookup['rootPage'] ?? null;
    }

    /**
     * @return null|array{rootPage:int,collation:string,descending:bool}
     */
    private function indexLookupForColumn(
        string $tableName,
        string $columnName,
        mixed $pointLookupValue = null,
        bool $isPointLookup = false,
        array $equalityConstraints = [],
        bool $allowEqualityPartialPredicate = true,
        array $rangeConstraints = [],
    ): ?array
    {
        if ($isPointLookup) {
            $equalityConstraints[$columnName] = $pointLookupValue;
        }
        $autoIndexFirstColumns = null;
        $autoIndexOrdinal = 0;
        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql !== null) {
                $firstColumn = SQLiteCreateIndex::firstColumn($record->sql);
                if ($firstColumn !== null && strcasecmp($firstColumn->columnName, $columnName) === 0) {
                    if ($firstColumn->partial) {
                        $partialPredicate = $firstColumn->partialPredicate;
                        $hasPredicateConstraints = $isPointLookup || $rangeConstraints !== [];
                        if (
                            !$hasPredicateConstraints
                            || $partialPredicate === null
                            || !self::partialPredicateIsImpliedByConstraints(
                                $partialPredicate,
                                $equalityConstraints,
                                $rangeConstraints,
                                $allowEqualityPartialPredicate,
                            )
                        ) {
                            continue;
                        }
                    }

                    return [
                        'rootPage' => $record->rootPage,
                        'collation' => $firstColumn->collation,
                        'descending' => $firstColumn->descending,
                    ];
                }
            }
            if ($record->sql === null && self::isAutomaticIndex($record, $tableName)) {
                if ($autoIndexFirstColumns === null) {
                    $autoIndexFirstColumns = $this->automaticIndexFirstColumnsForTable($tableName);
                }
                $firstColumn = $autoIndexFirstColumns[$autoIndexOrdinal] ?? null;
                $autoIndexOrdinal++;
                if ($firstColumn !== null && strcasecmp($firstColumn->columnName, $columnName) === 0) {
                    return [
                        'rootPage' => $record->rootPage,
                        'collation' => $firstColumn->collation,
                        'descending' => $firstColumn->descending,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @param list<mixed> $values
     * @return null|array{rootPage:int,collation:string,descending:bool}
     */
    private function indexLookupForColumnInList(string $tableName, string $columnName, array $values): ?array
    {
        $hasNonNullValue = false;
        foreach ($values as $value) {
            if ($value !== null) {
                $hasNonNullValue = true;
                break;
            }
        }
        if (!$hasNonNullValue) {
            return null;
        }

        $autoIndexFirstColumns = null;
        $autoIndexOrdinal = 0;
        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql !== null) {
                $firstColumn = SQLiteCreateIndex::firstColumn($record->sql);
                if ($firstColumn !== null && strcasecmp($firstColumn->columnName, $columnName) === 0) {
                    if (
                        $firstColumn->partial
                        && (
                            $firstColumn->partialPredicate === null
                            || !self::partialPredicateIsImpliedByInListConstraints(
                                $firstColumn->partialPredicate,
                                $columnName,
                                $values,
                            )
                        )
                    ) {
                        continue;
                    }

                    return [
                        'rootPage' => $record->rootPage,
                        'collation' => $firstColumn->collation,
                        'descending' => $firstColumn->descending,
                    ];
                }
            }
            if ($record->sql === null && self::isAutomaticIndex($record, $tableName)) {
                if ($autoIndexFirstColumns === null) {
                    $autoIndexFirstColumns = $this->automaticIndexFirstColumnsForTable($tableName);
                }
                $firstColumn = $autoIndexFirstColumns[$autoIndexOrdinal] ?? null;
                $autoIndexOrdinal++;
                if ($firstColumn !== null && strcasecmp($firstColumn->columnName, $columnName) === 0) {
                    return [
                        'rootPage' => $record->rootPage,
                        'collation' => $firstColumn->collation,
                        'descending' => $firstColumn->descending,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @param list<mixed> $values
     * @return null|array{rootPage:int,collation:string,descending:bool}
     */
    private function indexLookupForLowerExpressionColumnInList(string $tableName, string $columnName, array $values): ?array
    {
        $hasNonNullValue = false;
        foreach ($values as $value) {
            if ($value !== null) {
                $hasNonNullValue = true;
                break;
            }
        }
        if (!$hasNonNullValue) {
            return null;
        }

        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $firstExpression = SQLiteCreateIndex::firstLowerExpression($record->sql);
            if ($firstExpression === null || strcasecmp($firstExpression->columnName, $columnName) !== 0) {
                continue;
            }

            if (
                $firstExpression->partial
                && (
                    $firstExpression->partialPredicate === null
                    || !self::lowerExpressionRangeImpliesPartialPredicate(
                        $firstExpression->partialPredicate,
                        $columnName,
                    )
                )
            ) {
                continue;
            }

            return [
                'rootPage' => $record->rootPage,
                'collation' => $firstExpression->collation,
                'descending' => $firstExpression->descending,
            ];
        }

        return null;
    }

    /**
     * @param list<mixed> $values
     * @return null|array{rootPage:int,collation:string,descending:bool}
     */
    private function indexLookupForUpperExpressionColumnInList(string $tableName, string $columnName, array $values): ?array
    {
        $hasNonNullValue = false;
        foreach ($values as $value) {
            if ($value !== null) {
                $hasNonNullValue = true;
                break;
            }
        }
        if (!$hasNonNullValue) {
            return null;
        }

        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $firstExpression = SQLiteCreateIndex::firstUpperExpression($record->sql);
            if ($firstExpression === null || strcasecmp($firstExpression->columnName, $columnName) !== 0) {
                continue;
            }

            if (
                $firstExpression->partial
                && (
                    $firstExpression->partialPredicate === null
                    || !self::lowerExpressionRangeImpliesPartialPredicate(
                        $firstExpression->partialPredicate,
                        $columnName,
                    )
                )
            ) {
                continue;
            }

            return [
                'rootPage' => $record->rootPage,
                'collation' => $firstExpression->collation,
                'descending' => $firstExpression->descending,
            ];
        }

        return null;
    }

    /**
     * @return null|array{rootPage:int,collation:string,descending:bool}
     */
    private function indexLookupForLowerExpressionColumn(
        string $tableName,
        string $columnName,
        string $pointLookupValue,
    ): ?array {
        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $firstExpression = SQLiteCreateIndex::firstLowerExpression($record->sql);
            if ($firstExpression === null || strcasecmp($firstExpression->columnName, $columnName) !== 0) {
                continue;
            }

            if (
                $firstExpression->partial
                && (
                    $firstExpression->partialPredicate === null
                    || !self::partialPredicateIsImpliedByConstraints(
                        $firstExpression->partialPredicate,
                        [$columnName => $pointLookupValue],
                        [],
                        true,
                    )
                )
            ) {
                continue;
            }

            return [
                'rootPage' => $record->rootPage,
                'collation' => $firstExpression->collation,
                'descending' => $firstExpression->descending,
            ];
        }

        return null;
    }

    /**
     * @return null|array{rootPage:int,collation:string,descending:bool}
     */
    private function indexLookupForUpperExpressionColumn(
        string $tableName,
        string $columnName,
    ): ?array {
        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $firstExpression = SQLiteCreateIndex::firstUpperExpression($record->sql);
            if ($firstExpression === null || strcasecmp($firstExpression->columnName, $columnName) !== 0) {
                continue;
            }

            if (
                $firstExpression->partial
                && (
                    $firstExpression->partialPredicate === null
                    || !self::lowerExpressionRangeImpliesPartialPredicate(
                        $firstExpression->partialPredicate,
                        $columnName,
                    )
                )
            ) {
                continue;
            }

            return [
                'rootPage' => $record->rootPage,
                'collation' => $firstExpression->collation,
                'descending' => $firstExpression->descending,
            ];
        }

        return null;
    }

    /**
     * @return null|array{rootPage:int,collation:string,descending:bool}
     */
    private function indexLookupForTrimExpressionColumn(
        string $tableName,
        string $columnName,
        string $functionName,
        ?string $characters,
    ): ?array {
        $functionName = self::normalizeTrimFunctionName($functionName);
        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $firstExpression = SQLiteCreateIndex::firstTrimExpression($record->sql);
            if (
                $firstExpression === null
                || $firstExpression->functionName !== $functionName
                || $firstExpression->characters !== $characters
                || strcasecmp($firstExpression->columnName, $columnName) !== 0
            ) {
                continue;
            }

            if (
                $firstExpression->partial
                && (
                    $firstExpression->partialPredicate === null
                    || !self::lowerExpressionRangeImpliesPartialPredicate(
                        $firstExpression->partialPredicate,
                        $columnName,
                    )
                )
            ) {
                continue;
            }

            return [
                'rootPage' => $record->rootPage,
                'collation' => $firstExpression->collation,
                'descending' => $firstExpression->descending,
            ];
        }

        return null;
    }

    /**
     * @return null|array{rootPage:int,collation:string,descending:bool}
     */
    private function indexLookupForLengthExpressionColumn(
        string $tableName,
        string $columnName,
        int $pointLookupValue,
    ): ?array {
        if ($pointLookupValue < 0) {
            throw new \InvalidArgumentException('SQLite length expression index lookup length cannot be negative');
        }

        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $firstExpression = SQLiteCreateIndex::firstLengthExpression($record->sql);
            if ($firstExpression === null || strcasecmp($firstExpression->columnName, $columnName) !== 0) {
                continue;
            }

            if (
                $firstExpression->partial
                && (
                    $firstExpression->partialPredicate === null
                    || !self::lowerExpressionRangeImpliesPartialPredicate(
                        $firstExpression->partialPredicate,
                        $columnName,
                    )
                )
            ) {
                continue;
            }

            return [
                'rootPage' => $record->rootPage,
                'collation' => $firstExpression->collation,
                'descending' => $firstExpression->descending,
            ];
        }

        return null;
    }

    /**
     * @return null|array{rootPage:int,collation:string,descending:bool}
     */
    private function indexLookupForLengthExpressionColumnRange(
        string $tableName,
        string $columnName,
        ?int $lowerInclusive = null,
        ?int $upperBound = null,
        bool $upperInclusive = false,
    ): ?array {
        if ($lowerInclusive === null && $upperBound === null) {
            throw new \InvalidArgumentException('SQLite length expression index range lookup requires at least one bound');
        }
        if ($lowerInclusive !== null && $lowerInclusive < 0) {
            throw new \InvalidArgumentException('SQLite length expression index range lower bound cannot be negative');
        }
        if ($upperBound !== null && $upperBound < 0) {
            throw new \InvalidArgumentException('SQLite length expression index range upper bound cannot be negative');
        }

        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $firstExpression = SQLiteCreateIndex::firstLengthExpression($record->sql);
            if ($firstExpression === null || strcasecmp($firstExpression->columnName, $columnName) !== 0) {
                continue;
            }

            if (
                $firstExpression->partial
                && (
                    $firstExpression->partialPredicate === null
                    || !self::lowerExpressionRangeImpliesPartialPredicate(
                        $firstExpression->partialPredicate,
                        $columnName,
                    )
                )
            ) {
                continue;
            }

            return [
                'rootPage' => $record->rootPage,
                'collation' => $firstExpression->collation,
                'descending' => $firstExpression->descending,
            ];
        }

        return null;
    }

    /**
     * @return null|array{rootPage:int,collation:string,descending:bool}
     */
    private function indexLookupForIntegerCastExpressionColumn(
        string $tableName,
        string $columnName,
        int $pointLookupValue,
    ): ?array {
        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $firstExpression = SQLiteCreateIndex::firstIntegerCastExpression($record->sql);
            if ($firstExpression === null || strcasecmp($firstExpression->columnName, $columnName) !== 0) {
                continue;
            }

            if (
                $firstExpression->partial
                && (
                    $firstExpression->partialPredicate === null
                    || !self::lowerExpressionRangeImpliesPartialPredicate(
                        $firstExpression->partialPredicate,
                        $columnName,
                    )
                )
            ) {
                continue;
            }

            return [
                'rootPage' => $record->rootPage,
                'collation' => $firstExpression->collation,
                'descending' => $firstExpression->descending,
            ];
        }

        return null;
    }

    /**
     * @param list<mixed> $values
     * @return null|array{rootPage:int,collation:string,descending:bool}
     */
    private function indexLookupForIntegerCastExpressionColumnInList(
        string $tableName,
        string $columnName,
        array $values,
    ): ?array {
        $hasNonNullValue = false;
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }
            if (!is_int($value)) {
                throw new \InvalidArgumentException('SQLite CAST AS INTEGER expression index IN lookup values must be integers or null');
            }
            $hasNonNullValue = true;
        }
        if (!$hasNonNullValue) {
            return null;
        }

        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $firstExpression = SQLiteCreateIndex::firstIntegerCastExpression($record->sql);
            if ($firstExpression === null || strcasecmp($firstExpression->columnName, $columnName) !== 0) {
                continue;
            }

            if (
                $firstExpression->partial
                && (
                    $firstExpression->partialPredicate === null
                    || !self::lowerExpressionRangeImpliesPartialPredicate(
                        $firstExpression->partialPredicate,
                        $columnName,
                    )
                )
            ) {
                continue;
            }

            return [
                'rootPage' => $record->rootPage,
                'collation' => $firstExpression->collation,
                'descending' => $firstExpression->descending,
            ];
        }

        return null;
    }

    /**
     * @return null|array{rootPage:int,collation:string,descending:bool}
     */
    private function indexLookupForIntegerCastExpressionColumnRange(
        string $tableName,
        string $columnName,
        ?int $lowerInclusive = null,
        ?int $upperBound = null,
        bool $upperInclusive = false,
    ): ?array {
        if ($lowerInclusive === null && $upperBound === null) {
            throw new \InvalidArgumentException('SQLite CAST AS INTEGER expression index range lookup requires at least one bound');
        }

        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $firstExpression = SQLiteCreateIndex::firstIntegerCastExpression($record->sql);
            if ($firstExpression === null || strcasecmp($firstExpression->columnName, $columnName) !== 0) {
                continue;
            }

            if (
                $firstExpression->partial
                && (
                    $firstExpression->partialPredicate === null
                    || !self::lowerExpressionRangeImpliesPartialPredicate(
                        $firstExpression->partialPredicate,
                        $columnName,
                    )
                )
            ) {
                continue;
            }

            return [
                'rootPage' => $record->rootPage,
                'collation' => $firstExpression->collation,
                'descending' => $firstExpression->descending,
            ];
        }

        return null;
    }

    /**
     * @return null|array{rootPage:int,collation:string,descending:bool,path:string}
     */
    private function indexLookupForJsonExtractExpressionColumn(
        string $tableName,
        string $columnName,
        string $path,
    ): ?array {
        $requestedPath = self::parseSimpleJsonPath($path);

        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $expression = SQLiteCreateIndex::firstJsonExtractExpression($record->sql)
                ?? SQLiteCreateIndex::firstJsonTextOperatorExpression($record->sql);
            if (
                $expression === null
                || strcasecmp($expression->columnName, $columnName) !== 0
                || !self::jsonExpressionPathMatches($expression->path, $requestedPath)
            ) {
                continue;
            }

            if (
                $expression->partial
                && (
                    $expression->partialPredicate === null
                    || !self::lowerExpressionRangeImpliesPartialPredicate(
                        $expression->partialPredicate,
                        $columnName,
                    )
                )
            ) {
                continue;
            }

            return [
                'rootPage' => $record->rootPage,
                'collation' => $expression->collation,
                'descending' => $expression->descending,
                'path' => $expression->path,
            ];
        }

        return null;
    }

    /**
     * @return null|array{rootPage:int,collation:string,descending:bool,path:string}
     */
    private function indexLookupForJsonValueOperatorExpressionColumn(
        string $tableName,
        string $columnName,
        string $path,
    ): ?array {
        $requestedPath = self::parseSimpleJsonPath($path);

        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $expression = SQLiteCreateIndex::firstJsonValueOperatorExpression($record->sql);
            if (
                $expression === null
                || strcasecmp($expression->columnName, $columnName) !== 0
                || !self::jsonExpressionPathMatches($expression->path, $requestedPath)
            ) {
                continue;
            }

            if (
                $expression->partial
                && (
                    $expression->partialPredicate === null
                    || !self::lowerExpressionRangeImpliesPartialPredicate(
                        $expression->partialPredicate,
                        $columnName,
                    )
                )
            ) {
                continue;
            }

            return [
                'rootPage' => $record->rootPage,
                'collation' => $expression->collation,
                'descending' => $expression->descending,
                'path' => $expression->path,
            ];
        }

        return null;
    }

    /**
     * @param list<array{kind:string,value:int|string|null}> $requestedPath
     */
    private static function jsonExpressionPathMatches(string $expressionPath, array $requestedPath): bool
    {
        try {
            return self::parseSimpleJsonPath($expressionPath) === $requestedPath;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    /**
     * @return null|array{rootPage:int,collation:string,descending:bool,path:string}
     */
    private function indexLookupForJsonExtractExpressionColumnRange(
        string $tableName,
        string $columnName,
        string $path,
        mixed $lowerInclusive = null,
        mixed $upperBound = null,
        bool $upperInclusive = false,
    ): ?array {
        if ($lowerInclusive === null && $upperBound === null) {
            throw new \InvalidArgumentException('SQLite json_extract expression index range lookup requires at least one bound');
        }

        return $this->indexLookupForJsonExtractExpressionColumn($tableName, $columnName, $path);
    }

    /**
     * @return null|array{rootPage:int,collation:string,descending:bool,path:string}
     */
    private function indexLookupForJsonValueOperatorExpressionColumnRange(
        string $tableName,
        string $columnName,
        string $path,
        ?string $lowerInclusive = null,
        ?string $upperBound = null,
        bool $upperInclusive = false,
    ): ?array {
        if ($lowerInclusive === null && $upperBound === null) {
            throw new \InvalidArgumentException('SQLite JSON -> expression index range lookup requires at least one bound');
        }

        return $this->indexLookupForJsonValueOperatorExpressionColumn($tableName, $columnName, $path);
    }

    /**
     * @param list<mixed> $values
     * @return null|array{rootPage:int,collation:string,descending:bool}
     */
    private function indexLookupForLengthExpressionColumnInList(
        string $tableName,
        string $columnName,
        array $values,
    ): ?array {
        $hasNonNullValue = false;
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }
            if (!is_int($value)) {
                throw new \InvalidArgumentException('SQLite length expression index IN lookup lengths must be integers or null');
            }
            if ($value < 0) {
                throw new \InvalidArgumentException('SQLite length expression index IN lookup lengths cannot be negative');
            }
            $hasNonNullValue = true;
        }
        if (!$hasNonNullValue) {
            return null;
        }

        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $firstExpression = SQLiteCreateIndex::firstLengthExpression($record->sql);
            if ($firstExpression === null || strcasecmp($firstExpression->columnName, $columnName) !== 0) {
                continue;
            }

            if (
                $firstExpression->partial
                && (
                    $firstExpression->partialPredicate === null
                    || !self::lowerExpressionRangeImpliesPartialPredicate(
                        $firstExpression->partialPredicate,
                        $columnName,
                    )
                )
            ) {
                continue;
            }

            return [
                'rootPage' => $record->rootPage,
                'collation' => $firstExpression->collation,
                'descending' => $firstExpression->descending,
            ];
        }

        return null;
    }

    /**
     * @return null|array{rootPage:int,collation:string,descending:bool,expression:SQLiteSubstringIndexExpression}
     */
    private function indexLookupForSubstringExpressionColumn(
        string $tableName,
        string $columnName,
        int $start,
        ?int $length,
    ): ?array {
        if ($start === 0) {
            throw new \InvalidArgumentException('SQLite substr expression index lookup start cannot be zero');
        }
        if ($length !== null && $length < 0) {
            throw new \InvalidArgumentException('SQLite substr expression index lookup length cannot be negative');
        }

        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $expression = SQLiteCreateIndex::firstSubstringExpression($record->sql);
            if (
                $expression === null
                || strcasecmp($expression->columnName, $columnName) !== 0
                || $expression->start !== $start
                || $expression->length !== $length
            ) {
                continue;
            }

            if (
                $expression->partial
                && (
                    $expression->partialPredicate === null
                    || !self::lowerExpressionRangeImpliesPartialPredicate(
                        $expression->partialPredicate,
                        $columnName,
                    )
                )
            ) {
                continue;
            }

            return [
                'rootPage' => $record->rootPage,
                'collation' => $expression->collation,
                'descending' => $expression->descending,
                'expression' => $expression,
            ];
        }

        return null;
    }

    /**
     * @param list<mixed> $values
     * @return null|array{rootPage:int,collation:string,descending:bool,expression:SQLiteSubstringIndexExpression}
     */
    private function indexLookupForSubstringExpressionColumnInList(
        string $tableName,
        string $columnName,
        int $start,
        ?int $length,
        array $values,
    ): ?array {
        if (!self::containsNonNullValue($values)) {
            return null;
        }

        return $this->indexLookupForSubstringExpressionColumn($tableName, $columnName, $start, $length);
    }

    /**
     * @return null|array{rootPage:int,collation:string,descending:bool}
     */
    private function indexLookupForLowerExpressionColumnRange(
        string $tableName,
        string $columnName,
        ?string $lowerInclusive = null,
        ?string $upperBound = null,
        bool $upperInclusive = false,
    ): ?array {
        if ($lowerInclusive === null && $upperBound === null) {
            throw new \InvalidArgumentException('SQLite lower expression index range lookup requires at least one bound');
        }

        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $firstExpression = SQLiteCreateIndex::firstLowerExpression($record->sql);
            if ($firstExpression === null || strcasecmp($firstExpression->columnName, $columnName) !== 0) {
                continue;
            }

            if (
                $firstExpression->partial
                && (
                    $firstExpression->partialPredicate === null
                    || !self::lowerExpressionRangeImpliesPartialPredicate(
                        $firstExpression->partialPredicate,
                        $columnName,
                    )
                )
            ) {
                continue;
            }

            return [
                'rootPage' => $record->rootPage,
                'collation' => $firstExpression->collation,
                'descending' => $firstExpression->descending,
            ];
        }

        return null;
    }

    /**
     * @return null|array{rootPage:int,collation:string,descending:bool}
     */
    private function indexLookupForUpperExpressionColumnRange(
        string $tableName,
        string $columnName,
        ?string $lowerInclusive = null,
        ?string $upperBound = null,
        bool $upperInclusive = false,
    ): ?array {
        if ($lowerInclusive === null && $upperBound === null) {
            throw new \InvalidArgumentException('SQLite upper expression index range lookup requires at least one bound');
        }

        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $firstExpression = SQLiteCreateIndex::firstUpperExpression($record->sql);
            if ($firstExpression === null || strcasecmp($firstExpression->columnName, $columnName) !== 0) {
                continue;
            }

            if (
                $firstExpression->partial
                && (
                    $firstExpression->partialPredicate === null
                    || !self::lowerExpressionRangeImpliesPartialPredicate(
                        $firstExpression->partialPredicate,
                        $columnName,
                    )
                )
            ) {
                continue;
            }

            return [
                'rootPage' => $record->rootPage,
                'collation' => $firstExpression->collation,
                'descending' => $firstExpression->descending,
            ];
        }

        return null;
    }

    /**
     * @return null|array{rootPage:int,collation:string,descending:bool}
     */
    private function indexLookupForColumnRange(
        string $tableName,
        string $columnName,
        mixed $lowerInclusive = null,
        mixed $upperBound = null,
        bool $upperInclusive = false,
    ): ?array {
        if ($lowerInclusive === null && $upperBound === null) {
            throw new \InvalidArgumentException('SQLite index range lookup requires at least one bound');
        }

        $rangeConstraints = [
            $columnName => [
                'lowerInclusive' => $lowerInclusive,
                'upperBound' => $upperBound,
                'upperInclusive' => $upperInclusive,
            ],
        ];

        return $this->indexLookupForColumn(
            $tableName,
            $columnName,
            null,
            false,
            [],
            false,
            $rangeConstraints,
        );
    }

    /**
     * @return list<SQLiteWordPressOption>
     */
    public function wordpressOptions(int $limit = 100): array
    {
        if ($limit < 0) {
            throw new \InvalidArgumentException('SQLite wp_options limit cannot be negative');
        }

        $options = [];
        foreach ($this->tableRowsByName('wp_options', $limit) as $row) {
            $options[] = SQLiteWordPressOption::fromTableRow($row);
        }

        return $options;
    }

    public function wordpressOptionByIndexedName(string $optionName): ?SQLiteWordPressOption
    {
        $tableRootPage = $this->tableRootPage('wp_options');
        if ($tableRootPage === null) {
            return null;
        }

        $indexLookup = $this->indexLookupForColumn('wp_options', 'option_name', $optionName, true);
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite wp_options option_name index is not present');
        }

        $visited = [];
        $indexCell = $this->findIndexCellByFirstValue(
            $indexLookup['rootPage'],
            $optionName,
            $visited,
            $indexLookup['collation'],
            $indexLookup['descending'],
        );
        if ($indexCell === null) {
            return null;
        }

        $rowId = $this->rowIdFromIndexCell($indexCell);
        $row = $this->tableRowByRowId($tableRootPage, $rowId);
        if ($row === null) {
            throw new \InvalidArgumentException("SQLite wp_options index points to missing rowid {$rowId}");
        }

        return SQLiteWordPressOption::fromTableRow($row);
    }

    /**
     * @param list<?string> $optionNames
     * @return list<SQLiteWordPressOption>
     */
    public function wordpressOptionsByIndexedNames(array $optionNames, ?int $limit = null): array
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite wp_options indexed IN lookup limit cannot be negative');
        }
        if ($limit === 0 || $optionNames === []) {
            return [];
        }

        $hasNonNullName = false;
        foreach ($optionNames as $optionName) {
            if ($optionName !== null && !is_string($optionName)) {
                throw new \InvalidArgumentException('SQLite wp_options indexed IN lookup names must be strings or null');
            }
            $hasNonNullName = $hasNonNullName || $optionName !== null;
        }
        if (!$hasNonNullName) {
            return [];
        }

        $tableRootPage = $this->tableRootPage('wp_options');
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForColumnInList('wp_options', 'option_name', $optionNames);
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite wp_options option_name IN-list index is not present');
        }

        $options = [];
        foreach ($this->indexCellsByFirstValueList(
            $indexLookup['rootPage'],
            $optionNames,
            $indexLookup['collation'],
            $indexLookup['descending'],
        ) as $indexCell) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite wp_options index points to missing rowid {$rowId}");
            }

            $options[] = SQLiteWordPressOption::fromTableRow($row);
            if ($limit !== null && count($options) >= $limit) {
                break;
            }
        }

        return $options;
    }

    /**
     * @param list<?string> $optionNames
     * @return list<SQLiteWordPressOption>
     */
    public function wordpressOptionsByIndexedLowercaseNames(array $optionNames, ?int $limit = null): array
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite wp_options lower expression indexed IN lookup limit cannot be negative');
        }
        if ($limit === 0 || $optionNames === []) {
            return [];
        }

        $lookupValues = [];
        foreach ($optionNames as $optionName) {
            if ($optionName !== null && !is_string($optionName)) {
                throw new \InvalidArgumentException('SQLite wp_options lower expression indexed IN lookup names must be strings or null');
            }
            $lookupValues[] = $optionName === null ? null : self::asciiLower($optionName);
        }
        if (!self::containsNonNullValue($lookupValues)) {
            return [];
        }

        $tableRootPage = $this->tableRootPage('wp_options');
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForLowerExpressionColumnInList('wp_options', 'option_name', $lookupValues);
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite wp_options lower(option_name) expression IN-list index is not present');
        }

        $options = [];
        foreach ($this->indexCellsByFirstValueList(
            $indexLookup['rootPage'],
            $lookupValues,
            $indexLookup['collation'],
            $indexLookup['descending'],
        ) as $indexCell) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite wp_options expression index points to missing rowid {$rowId}");
            }

            $option = SQLiteWordPressOption::fromTableRow($row);
            if (self::inListContainsSQLiteScalar($lookupValues, self::asciiLower($option->optionName), $indexLookup['collation'])) {
                $options[] = $option;
                if ($limit !== null && count($options) >= $limit) {
                    break;
                }
            }
        }

        return $options;
    }

    /**
     * @param list<?string> $optionNames
     * @return list<SQLiteWordPressOption>
     */
    public function wordpressOptionsByIndexedUppercaseNames(array $optionNames, ?int $limit = null): array
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite wp_options upper expression indexed IN lookup limit cannot be negative');
        }
        if ($limit === 0 || $optionNames === []) {
            return [];
        }

        $lookupValues = [];
        foreach ($optionNames as $optionName) {
            if ($optionName !== null && !is_string($optionName)) {
                throw new \InvalidArgumentException('SQLite wp_options upper expression indexed IN lookup names must be strings or null');
            }
            $lookupValues[] = $optionName === null ? null : self::asciiUpper($optionName);
        }
        if (!self::containsNonNullValue($lookupValues)) {
            return [];
        }

        $tableRootPage = $this->tableRootPage('wp_options');
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForUpperExpressionColumnInList('wp_options', 'option_name', $lookupValues);
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite wp_options upper(option_name) expression IN-list index is not present');
        }

        $options = [];
        foreach ($this->indexCellsByFirstValueList(
            $indexLookup['rootPage'],
            $lookupValues,
            $indexLookup['collation'],
            $indexLookup['descending'],
        ) as $indexCell) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite wp_options expression index points to missing rowid {$rowId}");
            }

            $option = SQLiteWordPressOption::fromTableRow($row);
            if (self::inListContainsSQLiteScalar($lookupValues, self::asciiUpper($option->optionName), $indexLookup['collation'])) {
                $options[] = $option;
                if ($limit !== null && count($options) >= $limit) {
                    break;
                }
            }
        }

        return $options;
    }

    public function wordpressOptionByIndexedNameForAutoload(string $optionName, string $autoload): ?SQLiteWordPressOption
    {
        $tableRootPage = $this->tableRootPage('wp_options');
        if ($tableRootPage === null) {
            return null;
        }

        $indexLookup = $this->indexLookupForColumn('wp_options', 'option_name', $optionName, true, [
            'autoload' => $autoload,
        ]);
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite wp_options option_name index matching the autoload constraint is not present');
        }

        foreach (
            $this->indexCellsByFirstValue(
                $indexLookup['rootPage'],
                $optionName,
                $indexLookup['collation'],
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite wp_options index points to missing rowid {$rowId}");
            }

            $option = SQLiteWordPressOption::fromTableRow($row);
            if (
                $option->autoload === $autoload
                && self::compareSQLiteScalar($option->optionName, $optionName, $indexLookup['collation']) === 0
            ) {
                return $option;
            }
        }

        return null;
    }

    public function wordpressOptionByIndexedLowercaseName(string $optionName): ?SQLiteWordPressOption
    {
        $tableRootPage = $this->tableRootPage('wp_options');
        if ($tableRootPage === null) {
            return null;
        }

        $indexLookup = $this->indexLookupForLowerExpressionColumn('wp_options', 'option_name', $optionName);
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite wp_options lower(option_name) expression index is not present');
        }

        $lookupValue = self::asciiLower($optionName);
        foreach (
            $this->indexCellsByFirstValue(
                $indexLookup['rootPage'],
                $lookupValue,
                $indexLookup['collation'],
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite wp_options expression index points to missing rowid {$rowId}");
            }

            $option = SQLiteWordPressOption::fromTableRow($row);
            if (self::compareSQLiteScalar(self::asciiLower($option->optionName), $lookupValue, $indexLookup['collation']) === 0) {
                return $option;
            }
        }

        return null;
    }

    public function wordpressOptionByIndexedUppercaseName(string $optionName): ?SQLiteWordPressOption
    {
        $tableRootPage = $this->tableRootPage('wp_options');
        if ($tableRootPage === null) {
            return null;
        }

        $indexLookup = $this->indexLookupForUpperExpressionColumn('wp_options', 'option_name');
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite wp_options upper(option_name) expression index is not present');
        }

        $lookupValue = self::asciiUpper($optionName);
        foreach (
            $this->indexCellsByFirstValue(
                $indexLookup['rootPage'],
                $lookupValue,
                $indexLookup['collation'],
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite wp_options expression index points to missing rowid {$rowId}");
            }

            $option = SQLiteWordPressOption::fromTableRow($row);
            if (self::compareSQLiteScalar(self::asciiUpper($option->optionName), $lookupValue, $indexLookup['collation']) === 0) {
                return $option;
            }
        }

        return null;
    }

    public function wordpressOptionByIndexedTrimmedName(
        string $optionName,
        string $functionName = 'trim',
        ?string $characters = null,
    ): ?SQLiteWordPressOption {
        $tableRootPage = $this->tableRootPage('wp_options');
        if ($tableRootPage === null) {
            return null;
        }

        $indexLookup = $this->indexLookupForTrimExpressionColumn('wp_options', 'option_name', $functionName, $characters);
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite wp_options trim(option_name) expression index is not present');
        }

        $lookupValue = self::sqliteTrim($optionName, $functionName, $characters);
        foreach (
            $this->indexCellsByFirstValue(
                $indexLookup['rootPage'],
                $lookupValue,
                $indexLookup['collation'],
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite wp_options expression index points to missing rowid {$rowId}");
            }

            $option = SQLiteWordPressOption::fromTableRow($row);
            if (self::compareSQLiteScalar(
                self::sqliteTrim($option->optionName, $functionName, $characters),
                $lookupValue,
                $indexLookup['collation'],
            ) === 0) {
                return $option;
            }
        }

        return null;
    }

    /**
     * @return list<SQLiteWordPressOption>
     */
    public function wordpressOptionsByIndexedNamePrefix(string $prefix, ?int $limit = null): array
    {
        if ($prefix === '') {
            throw new \InvalidArgumentException('SQLite wp_options substr(option_name) prefix lookup requires a non-empty prefix');
        }
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite wp_options substr(option_name) prefix lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $tableRootPage = $this->tableRootPage('wp_options');
        if ($tableRootPage === null) {
            return [];
        }

        $length = strlen($prefix);
        $indexLookup = $this->indexLookupForSubstringExpressionColumn(
            'wp_options',
            'option_name',
            1,
            $length,
        );
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite wp_options substr(option_name) expression index is not present');
        }

        $options = [];
        foreach (
            $this->indexCellsByFirstValue(
                $indexLookup['rootPage'],
                $prefix,
                $indexLookup['collation'],
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite wp_options expression index points to missing rowid {$rowId}");
            }

            $option = SQLiteWordPressOption::fromTableRow($row);
            if (
                self::compareSQLiteScalar(
                    self::sqliteSubstring($option->optionName, 1, $length),
                    $prefix,
                    $indexLookup['collation'],
                ) === 0
            ) {
                $options[] = $option;
                if ($limit !== null && count($options) >= $limit) {
                    break;
                }
            }
        }

        return $options;
    }

    /**
     * @param list<?string> $prefixes
     * @return list<SQLiteWordPressOption>
     */
    public function wordpressOptionsByIndexedNamePrefixes(array $prefixes, ?int $limit = null): array
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite wp_options substr(option_name) prefix IN-list lookup limit cannot be negative');
        }
        if ($limit === 0 || $prefixes === []) {
            return [];
        }

        $prefixLength = null;
        foreach ($prefixes as $prefix) {
            if ($prefix !== null && !is_string($prefix)) {
                throw new \InvalidArgumentException('SQLite wp_options substr(option_name) prefix IN-list values must be strings or null');
            }
            if ($prefix === null) {
                continue;
            }
            if ($prefix === '') {
                throw new \InvalidArgumentException('SQLite wp_options substr(option_name) prefix IN-list values must be non-empty');
            }
            $currentLength = self::sqliteLength($prefix);
            if ($prefixLength === null) {
                $prefixLength = $currentLength;
                continue;
            }
            if ($currentLength !== $prefixLength) {
                throw new \InvalidArgumentException('SQLite wp_options substr(option_name) prefix IN-list values must share one prefix length');
            }
        }
        if ($prefixLength === null) {
            return [];
        }

        $tableRootPage = $this->tableRootPage('wp_options');
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForSubstringExpressionColumnInList(
            'wp_options',
            'option_name',
            1,
            $prefixLength,
            $prefixes,
        );
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite wp_options substr(option_name) expression IN-list index is not present');
        }

        $options = [];
        foreach ($this->indexCellsByFirstValueList(
            $indexLookup['rootPage'],
            $prefixes,
            $indexLookup['collation'],
            $indexLookup['descending'],
        ) as $indexCell) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite wp_options expression index points to missing rowid {$rowId}");
            }

            $option = SQLiteWordPressOption::fromTableRow($row);
            if (self::inListContainsSQLiteScalar(
                $prefixes,
                self::sqliteSubstring($option->optionName, 1, $prefixLength),
                $indexLookup['collation'],
            )) {
                $options[] = $option;
                if ($limit !== null && count($options) >= $limit) {
                    break;
                }
            }
        }

        return $options;
    }

    /**
     * @return list<SQLiteWordPressOption>
     */
    public function wordpressOptionsByIndexedNameSuffix(string $suffix, ?int $limit = null): array
    {
        if ($suffix === '') {
            throw new \InvalidArgumentException('SQLite wp_options substr(option_name) suffix lookup requires a non-empty suffix');
        }
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite wp_options substr(option_name) suffix lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $tableRootPage = $this->tableRootPage('wp_options');
        if ($tableRootPage === null) {
            return [];
        }

        $start = -self::sqliteLength($suffix);
        $indexLookup = $this->indexLookupForSubstringExpressionColumn(
            'wp_options',
            'option_name',
            $start,
            null,
        );
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite wp_options substr(option_name) suffix expression index is not present');
        }

        $options = [];
        foreach (
            $this->indexCellsByFirstValue(
                $indexLookup['rootPage'],
                $suffix,
                $indexLookup['collation'],
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite wp_options expression index points to missing rowid {$rowId}");
            }

            $option = SQLiteWordPressOption::fromTableRow($row);
            if (
                self::compareSQLiteScalar(
                    self::sqliteSubstring($option->optionName, $start, null),
                    $suffix,
                    $indexLookup['collation'],
                ) === 0
            ) {
                $options[] = $option;
                if ($limit !== null && count($options) >= $limit) {
                    break;
                }
            }
        }

        return $options;
    }

    /**
     * @return list<SQLiteWordPressOption>
     */
    public function wordpressOptionsByIndexedNameLength(int $length, ?int $limit = null): array
    {
        if ($length < 0) {
            throw new \InvalidArgumentException('SQLite wp_options length(option_name) lookup length cannot be negative');
        }
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite wp_options length(option_name) lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $tableRootPage = $this->tableRootPage('wp_options');
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForLengthExpressionColumn('wp_options', 'option_name', $length);
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite wp_options length(option_name) expression index is not present');
        }

        $options = [];
        foreach (
            $this->indexCellsByFirstValue(
                $indexLookup['rootPage'],
                $length,
                $indexLookup['collation'],
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite wp_options expression index points to missing rowid {$rowId}");
            }

            $option = SQLiteWordPressOption::fromTableRow($row);
            if (self::sqliteLength($option->optionName) === $length) {
                $options[] = $option;
                if ($limit !== null && count($options) >= $limit) {
                    break;
                }
            }
        }

        return $options;
    }

    /**
     * @param list<?int> $lengths
     * @return list<SQLiteWordPressOption>
     */
    public function wordpressOptionsByIndexedNameLengths(array $lengths, ?int $limit = null): array
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite wp_options length(option_name) IN-list lookup limit cannot be negative');
        }
        if ($limit === 0 || $lengths === []) {
            return [];
        }

        foreach ($lengths as $length) {
            if ($length === null) {
                continue;
            }
            if (!is_int($length)) {
                throw new \InvalidArgumentException('SQLite wp_options length(option_name) IN-list values must be integers or null');
            }
            if ($length < 0) {
                throw new \InvalidArgumentException('SQLite wp_options length(option_name) IN-list values cannot be negative');
            }
        }
        if (!self::containsNonNullValue($lengths)) {
            return [];
        }

        $tableRootPage = $this->tableRootPage('wp_options');
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForLengthExpressionColumnInList('wp_options', 'option_name', $lengths);
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite wp_options length(option_name) expression IN-list index is not present');
        }

        $options = [];
        foreach ($this->indexCellsByFirstValueList(
            $indexLookup['rootPage'],
            $lengths,
            $indexLookup['collation'],
            $indexLookup['descending'],
        ) as $indexCell) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite wp_options expression index points to missing rowid {$rowId}");
            }

            $option = SQLiteWordPressOption::fromTableRow($row);
            if (self::inListContainsSQLiteScalar($lengths, self::sqliteLength($option->optionName), $indexLookup['collation'])) {
                $options[] = $option;
                if ($limit !== null && count($options) >= $limit) {
                    break;
                }
            }
        }

        return $options;
    }

    /**
     * @return list<SQLiteWordPressOption>
     */
    public function wordpressOptionsByIndexedNameLengthRange(
        ?int $lowerInclusive,
        ?int $upperBound,
        ?int $limit = null,
        bool $upperInclusive = false,
    ): array {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite wp_options length(option_name) range lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $tableRootPage = $this->tableRootPage('wp_options');
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForLengthExpressionColumnRange(
            'wp_options',
            'option_name',
            $lowerInclusive,
            $upperBound,
            $upperInclusive,
        );
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite wp_options length(option_name) expression range index is not present');
        }
        if ($lowerInclusive !== null && $upperBound !== null) {
            $boundaryComparison = self::compareSQLiteScalar($lowerInclusive, $upperBound, $indexLookup['collation']);
            if ($boundaryComparison > 0 || ($boundaryComparison === 0 && !$upperInclusive)) {
                return [];
            }
        }

        $options = [];
        foreach (
            $this->indexCellsByFirstValueRange(
                $indexLookup['rootPage'],
                $lowerInclusive,
                $upperBound,
                $indexLookup['collation'],
                $upperInclusive,
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite wp_options expression index points to missing rowid {$rowId}");
            }

            $option = SQLiteWordPressOption::fromTableRow($row);
            if (self::firstValueIsInRange(
                self::sqliteLength($option->optionName),
                $lowerInclusive,
                $upperBound,
                $upperInclusive,
                $indexLookup['collation'],
            )) {
                $options[] = $option;
                if ($limit !== null && count($options) >= $limit) {
                    break;
                }
            }
        }

        return $options;
    }

    /**
     * @return list<SQLiteWordPressOption>
     */
    public function wordpressOptionsByIndexedIntegerOptionValue(int $value, ?int $limit = null): array
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite wp_options CAST(option_value AS INTEGER) lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $tableRootPage = $this->tableRootPage('wp_options');
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForIntegerCastExpressionColumn('wp_options', 'option_value', $value);
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite wp_options CAST(option_value AS INTEGER) expression index is not present');
        }

        $options = [];
        foreach (
            $this->indexCellsByFirstValue(
                $indexLookup['rootPage'],
                $value,
                $indexLookup['collation'],
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite wp_options expression index points to missing rowid {$rowId}");
            }

            $option = SQLiteWordPressOption::fromTableRow($row);
            if (self::sqliteCastAsInteger($option->optionValue) === $value) {
                $options[] = $option;
                if ($limit !== null && count($options) >= $limit) {
                    break;
                }
            }
        }

        return $options;
    }

    /**
     * @param list<?int> $values
     * @return list<SQLiteWordPressOption>
     */
    public function wordpressOptionsByIndexedIntegerOptionValues(array $values, ?int $limit = null): array
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite wp_options CAST(option_value AS INTEGER) IN-list lookup limit cannot be negative');
        }
        if ($limit === 0 || $values === []) {
            return [];
        }

        foreach ($values as $value) {
            if ($value !== null && !is_int($value)) {
                throw new \InvalidArgumentException('SQLite wp_options CAST(option_value AS INTEGER) IN-list values must be integers or null');
            }
        }
        if (!self::containsNonNullValue($values)) {
            return [];
        }

        $tableRootPage = $this->tableRootPage('wp_options');
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForIntegerCastExpressionColumnInList('wp_options', 'option_value', $values);
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite wp_options CAST(option_value AS INTEGER) expression IN-list index is not present');
        }

        $options = [];
        foreach ($this->indexCellsByFirstValueList(
            $indexLookup['rootPage'],
            $values,
            $indexLookup['collation'],
            $indexLookup['descending'],
        ) as $indexCell) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite wp_options expression index points to missing rowid {$rowId}");
            }

            $option = SQLiteWordPressOption::fromTableRow($row);
            if (self::inListContainsSQLiteScalar($values, self::sqliteCastAsInteger($option->optionValue), $indexLookup['collation'])) {
                $options[] = $option;
                if ($limit !== null && count($options) >= $limit) {
                    break;
                }
            }
        }

        return $options;
    }

    /**
     * @return list<SQLiteWordPressOption>
     */
    public function wordpressOptionsByIndexedIntegerOptionValueRange(
        ?int $lowerInclusive,
        ?int $upperBound,
        ?int $limit = null,
        bool $upperInclusive = false,
    ): array {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite wp_options CAST(option_value AS INTEGER) range lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $tableRootPage = $this->tableRootPage('wp_options');
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForIntegerCastExpressionColumnRange(
            'wp_options',
            'option_value',
            $lowerInclusive,
            $upperBound,
            $upperInclusive,
        );
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite wp_options CAST(option_value AS INTEGER) expression range index is not present');
        }
        if ($lowerInclusive !== null && $upperBound !== null) {
            $boundaryComparison = self::compareSQLiteScalar($lowerInclusive, $upperBound, $indexLookup['collation']);
            if ($boundaryComparison > 0 || ($boundaryComparison === 0 && !$upperInclusive)) {
                return [];
            }
        }

        $options = [];
        foreach (
            $this->indexCellsByFirstValueRange(
                $indexLookup['rootPage'],
                $lowerInclusive,
                $upperBound,
                $indexLookup['collation'],
                $upperInclusive,
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite wp_options expression index points to missing rowid {$rowId}");
            }

            $option = SQLiteWordPressOption::fromTableRow($row);
            if (self::firstValueIsInRange(
                self::sqliteCastAsInteger($option->optionValue),
                $lowerInclusive,
                $upperBound,
                $upperInclusive,
                $indexLookup['collation'],
            )) {
                $options[] = $option;
                if ($limit !== null && count($options) >= $limit) {
                    break;
                }
            }
        }

        return $options;
    }

    /**
     * @return list<SQLiteWordPressOption>
     */
    public function wordpressOptionsByIndexedJsonOptionValue(string $jsonPath, mixed $value, ?int $limit = null): array
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite wp_options json_extract(option_value) lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $lookupValue = self::sqliteJsonScalar($value);
        $tableRootPage = $this->tableRootPage('wp_options');
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForJsonExtractExpressionColumn(
            'wp_options',
            'option_value',
            $jsonPath,
        );
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite wp_options json_extract(option_value) expression index is not present');
        }

        $options = [];
        foreach (
            $this->indexCellsByFirstValue(
                $indexLookup['rootPage'],
                $lookupValue,
                $indexLookup['collation'],
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite wp_options expression index points to missing rowid {$rowId}");
            }

            $option = SQLiteWordPressOption::fromTableRow($row);
            if (
                self::compareSQLiteScalar(
                    self::sqliteJsonExtract($option->optionValue, $jsonPath, $row->record->serialTypes[2] ?? null),
                    $lookupValue,
                    $indexLookup['collation'],
                ) === 0
            ) {
                $options[] = $option;
                if ($limit !== null && count($options) >= $limit) {
                    break;
                }
            }
        }

        return $options;
    }

    /**
     * @return list<SQLiteWordPressOption>
     */
    public function wordpressOptionsByIndexedJsonOptionFragment(string $jsonPath, mixed $value, ?int $limit = null): array
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite wp_options JSON -> lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $lookupValue = self::sqliteJsonTextValue($value);
        $tableRootPage = $this->tableRootPage('wp_options');
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForJsonValueOperatorExpressionColumn(
            'wp_options',
            'option_value',
            $jsonPath,
        );
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite wp_options JSON -> expression index is not present');
        }

        $options = [];
        foreach (
            $this->indexCellsByFirstValue(
                $indexLookup['rootPage'],
                $lookupValue,
                $indexLookup['collation'],
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite wp_options expression index points to missing rowid {$rowId}");
            }

            $option = SQLiteWordPressOption::fromTableRow($row);
            if (
                self::compareSQLiteScalar(
                    self::sqliteJsonValueOperator($option->optionValue, $jsonPath, $row->record->serialTypes[2] ?? null),
                    $lookupValue,
                    $indexLookup['collation'],
                ) === 0
            ) {
                $options[] = $option;
                if ($limit !== null && count($options) >= $limit) {
                    break;
                }
            }
        }

        return $options;
    }

    /**
     * @param list<mixed> $values
     * @return list<SQLiteWordPressOption>
     */
    public function wordpressOptionsByIndexedJsonOptionFragments(string $jsonPath, array $values, ?int $limit = null): array
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite wp_options JSON -> IN-list lookup limit cannot be negative');
        }
        if ($limit === 0 || $values === []) {
            return [];
        }

        $lookupValues = self::sqliteJsonTextValueList($values);
        $tableRootPage = $this->tableRootPage('wp_options');
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForJsonValueOperatorExpressionColumn(
            'wp_options',
            'option_value',
            $jsonPath,
        );
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite wp_options JSON -> expression IN-list index is not present');
        }

        $options = [];
        foreach (
            $this->indexCellsByFirstValueList(
                $indexLookup['rootPage'],
                $lookupValues,
                $indexLookup['collation'],
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite wp_options expression index points to missing rowid {$rowId}");
            }

            $option = SQLiteWordPressOption::fromTableRow($row);
            $fragment = self::sqliteJsonValueOperator($option->optionValue, $jsonPath, $row->record->serialTypes[2] ?? null);
            if (
                $fragment !== null
                && self::inListContainsSQLiteScalar($lookupValues, $fragment, $indexLookup['collation'])
            ) {
                $options[] = $option;
                if ($limit !== null && count($options) >= $limit) {
                    break;
                }
            }
        }

        return $options;
    }

    /**
     * @return list<SQLiteWordPressOption>
     */
    public function wordpressOptionsByIndexedJsonOptionFragmentRange(
        string $jsonPath,
        mixed $lowerInclusive,
        mixed $upperBound,
        ?int $limit = null,
        bool $upperInclusive = false,
    ): array {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite wp_options JSON -> range lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $lowerKey = $lowerInclusive === null ? null : self::sqliteJsonTextValue($lowerInclusive);
        $upperKey = $upperBound === null ? null : self::sqliteJsonTextValue($upperBound);
        if ($lowerKey === null && $upperKey === null) {
            throw new \InvalidArgumentException('SQLite wp_options JSON -> range lookup requires at least one bound');
        }

        $tableRootPage = $this->tableRootPage('wp_options');
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForJsonValueOperatorExpressionColumnRange(
            'wp_options',
            'option_value',
            $jsonPath,
            $lowerKey,
            $upperKey,
            $upperInclusive,
        );
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite wp_options JSON -> expression range index is not present');
        }
        if ($lowerKey !== null && $upperKey !== null) {
            $boundaryComparison = self::compareSQLiteScalar($lowerKey, $upperKey, $indexLookup['collation']);
            if ($boundaryComparison > 0 || ($boundaryComparison === 0 && !$upperInclusive)) {
                return [];
            }
        }

        $options = [];
        foreach (
            $this->indexCellsByFirstValueRange(
                $indexLookup['rootPage'],
                $lowerKey,
                $upperKey,
                $indexLookup['collation'],
                $upperInclusive,
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite wp_options expression index points to missing rowid {$rowId}");
            }

            $option = SQLiteWordPressOption::fromTableRow($row);
            $fragment = self::sqliteJsonValueOperator($option->optionValue, $jsonPath, $row->record->serialTypes[2] ?? null);
            if (
                $fragment !== null
                && self::firstValueIsInRange(
                    $fragment,
                    $lowerKey,
                    $upperKey,
                    $upperInclusive,
                    $indexLookup['collation'],
                )
            ) {
                $options[] = $option;
                if ($limit !== null && count($options) >= $limit) {
                    break;
                }
            }
        }

        return $options;
    }

    /**
     * @param list<mixed> $values
     * @return list<SQLiteWordPressOption>
     */
    public function wordpressOptionsByIndexedJsonOptionValues(string $jsonPath, array $values, ?int $limit = null): array
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite wp_options json_extract(option_value) IN-list lookup limit cannot be negative');
        }
        if ($limit === 0 || $values === []) {
            return [];
        }

        $lookupValues = self::sqliteJsonScalarList($values);
        if (!self::containsNonNullValue($lookupValues)) {
            return [];
        }

        $tableRootPage = $this->tableRootPage('wp_options');
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForJsonExtractExpressionColumn(
            'wp_options',
            'option_value',
            $jsonPath,
        );
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite wp_options json_extract(option_value) expression IN-list index is not present');
        }

        $options = [];
        foreach (
            $this->indexCellsByFirstValueList(
                $indexLookup['rootPage'],
                $lookupValues,
                $indexLookup['collation'],
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite wp_options expression index points to missing rowid {$rowId}");
            }

            $option = SQLiteWordPressOption::fromTableRow($row);
            if (
                self::inListContainsSQLiteScalar(
                    $lookupValues,
                    self::sqliteJsonExtract($option->optionValue, $jsonPath, $row->record->serialTypes[2] ?? null),
                    $indexLookup['collation'],
                )
            ) {
                $options[] = $option;
                if ($limit !== null && count($options) >= $limit) {
                    break;
                }
            }
        }

        return $options;
    }

    /**
     * @return list<SQLiteWordPressOption>
     */
    public function wordpressOptionsByIndexedJsonOptionValueRange(
        string $jsonPath,
        mixed $lowerInclusive,
        mixed $upperBound,
        ?int $limit = null,
        bool $upperInclusive = false,
    ): array {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite wp_options json_extract(option_value) range lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $lowerKey = $lowerInclusive === null ? null : self::sqliteJsonScalar($lowerInclusive);
        $upperKey = $upperBound === null ? null : self::sqliteJsonScalar($upperBound);
        if ($lowerKey === null && $upperKey === null) {
            throw new \InvalidArgumentException('SQLite wp_options json_extract(option_value) range lookup requires at least one bound');
        }

        $tableRootPage = $this->tableRootPage('wp_options');
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForJsonExtractExpressionColumnRange(
            'wp_options',
            'option_value',
            $jsonPath,
            $lowerKey,
            $upperKey,
            $upperInclusive,
        );
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite wp_options json_extract(option_value) expression range index is not present');
        }
        if ($lowerKey !== null && $upperKey !== null) {
            $boundaryComparison = self::compareSQLiteScalar($lowerKey, $upperKey, $indexLookup['collation']);
            if ($boundaryComparison > 0 || ($boundaryComparison === 0 && !$upperInclusive)) {
                return [];
            }
        }

        $options = [];
        foreach (
            $this->indexCellsByFirstValueRange(
                $indexLookup['rootPage'],
                $lowerKey,
                $upperKey,
                $indexLookup['collation'],
                $upperInclusive,
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite wp_options expression index points to missing rowid {$rowId}");
            }

            $option = SQLiteWordPressOption::fromTableRow($row);
            if (self::firstValueIsInRange(
                self::sqliteJsonExtract($option->optionValue, $jsonPath, $row->record->serialTypes[2] ?? null),
                $lowerKey,
                $upperKey,
                $upperInclusive,
                $indexLookup['collation'],
            )) {
                $options[] = $option;
                if ($limit !== null && count($options) >= $limit) {
                    break;
                }
            }
        }

        return $options;
    }

    /**
     * @return list<SQLiteWordPressOption>
     */
    public function wordpressOptionsByIndexedLowercaseNameRange(
        ?string $lowerInclusive,
        ?string $upperBound,
        ?int $limit = null,
        bool $upperInclusive = false,
    ): array {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite wp_options lower expression indexed range lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $tableRootPage = $this->tableRootPage('wp_options');
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForLowerExpressionColumnRange(
            'wp_options',
            'option_name',
            $lowerInclusive,
            $upperBound,
            $upperInclusive,
        );
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite wp_options lower(option_name) expression range index is not present');
        }

        $lowerKey = $lowerInclusive === null ? null : self::asciiLower($lowerInclusive);
        $upperKey = $upperBound === null ? null : self::asciiLower($upperBound);
        if ($lowerKey !== null && $upperKey !== null) {
            $boundaryComparison = self::compareSQLiteScalar($lowerKey, $upperKey, $indexLookup['collation']);
            if ($boundaryComparison > 0 || ($boundaryComparison === 0 && !$upperInclusive)) {
                return [];
            }
        }

        $options = [];
        foreach (
            $this->indexCellsByFirstValueRange(
                $indexLookup['rootPage'],
                $lowerKey,
                $upperKey,
                $indexLookup['collation'],
                $upperInclusive,
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite wp_options expression index points to missing rowid {$rowId}");
            }

            $option = SQLiteWordPressOption::fromTableRow($row);
            if (self::firstValueIsInRange(
                self::asciiLower($option->optionName),
                $lowerKey,
                $upperKey,
                $upperInclusive,
                $indexLookup['collation'],
            )) {
                $options[] = $option;
                if ($limit !== null && count($options) >= $limit) {
                    break;
                }
            }
        }

        return $options;
    }

    /**
     * @return list<SQLiteWordPressOption>
     */
    public function wordpressOptionsByIndexedUppercaseNameRange(
        ?string $lowerInclusive,
        ?string $upperBound,
        ?int $limit = null,
        bool $upperInclusive = false,
    ): array {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite wp_options upper expression indexed range lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $tableRootPage = $this->tableRootPage('wp_options');
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForUpperExpressionColumnRange(
            'wp_options',
            'option_name',
            $lowerInclusive,
            $upperBound,
            $upperInclusive,
        );
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite wp_options upper(option_name) expression range index is not present');
        }

        $lowerKey = $lowerInclusive === null ? null : self::asciiUpper($lowerInclusive);
        $upperKey = $upperBound === null ? null : self::asciiUpper($upperBound);
        if ($lowerKey !== null && $upperKey !== null) {
            $boundaryComparison = self::compareSQLiteScalar($lowerKey, $upperKey, $indexLookup['collation']);
            if ($boundaryComparison > 0 || ($boundaryComparison === 0 && !$upperInclusive)) {
                return [];
            }
        }

        $options = [];
        foreach (
            $this->indexCellsByFirstValueRange(
                $indexLookup['rootPage'],
                $lowerKey,
                $upperKey,
                $indexLookup['collation'],
                $upperInclusive,
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite wp_options expression index points to missing rowid {$rowId}");
            }

            $option = SQLiteWordPressOption::fromTableRow($row);
            if (self::firstValueIsInRange(
                self::asciiUpper($option->optionName),
                $lowerKey,
                $upperKey,
                $upperInclusive,
                $indexLookup['collation'],
            )) {
                $options[] = $option;
                if ($limit !== null && count($options) >= $limit) {
                    break;
                }
            }
        }

        return $options;
    }

    /**
     * @return list<SQLiteWordPressOption>
     */
    public function wordpressOptionsByIndexedAutoload(string $autoload, ?int $limit = null): array
    {
        return $this->wordpressOptionsByIndexedFirstColumn('autoload', $autoload, $limit);
    }

    public function wordpressOptionByIndexedAutoloadAndName(string $autoload, string $optionName): ?SQLiteWordPressOption
    {
        $options = $this->wordpressOptionsByIndexedColumnPrefix([
            'autoload' => $autoload,
            'option_name' => $optionName,
        ], 1);

        return $options[0] ?? null;
    }

    /**
     * @return list<SQLiteWordPressOption>
     */
    public function wordpressOptionsByIndexedAutoloadAndNameRange(
        string $autoload,
        ?string $lowerInclusive,
        ?string $upperBound,
        ?int $limit = null,
        bool $upperInclusive = false,
    ): array {
        return $this->wordpressOptionsByIndexedColumnPrefixRange(
            ['autoload' => $autoload],
            'option_name',
            $lowerInclusive,
            $upperBound,
            $limit,
            $upperInclusive,
        );
    }

    /**
     * @return list<SQLiteWordPressOption>
     */
    public function wordpressOptionsByIndexedNameRange(
        ?string $lowerInclusive,
        ?string $upperBound,
        ?int $limit = null,
        bool $upperInclusive = false,
    ): array
    {
        return $this->wordpressOptionsByIndexedFirstColumnRange(
            'option_name',
            $lowerInclusive,
            $upperBound,
            $limit,
            $upperInclusive,
        );
    }

    /**
     * @param array<int, true> $visited
     * @param list<SQLiteTableLeafCell> $cells
     */
    private function collectTableLeafCells(int $pageNumber, array &$visited, array &$cells, ?int $limit): void
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite table leaf cell limit cannot be negative');
        }
        if ($limit !== null && count($cells) >= $limit) {
            return;
        }
        if (isset($visited[$pageNumber])) {
            throw new \InvalidArgumentException("SQLite table b-tree traversal reached page {$pageNumber} more than once");
        }
        $visited[$pageNumber] = true;

        $page = $this->page($pageNumber);
        $header = SQLiteBTreePageHeader::parsePage(
            $page,
            $this->header->pageSize,
            $pageNumber === 1 ? 100 : 0,
        );

        if ($header->pageType === 'table-leaf') {
            $overflowReader = fn (int $firstOverflowPage, int $byteCount): string => $this->readOverflowPayload($firstOverflowPage, $byteCount);
            foreach (SQLiteTableLeafCell::parsePageCells($page, $header, $this->usablePageSize(), $overflowReader) as $cell) {
                if ($limit !== null && count($cells) >= $limit) {
                    return;
                }
                $cells[] = $cell;
            }

            return;
        }
        if ($header->pageType !== 'table-interior') {
            throw new \InvalidArgumentException("SQLite page {$pageNumber} is not a table b-tree page");
        }
        if ($header->rightMostPointer === null || $header->rightMostPointer < 1) {
            throw new \InvalidArgumentException("SQLite table interior page {$pageNumber} has an invalid right-most pointer");
        }

        foreach (SQLiteTableInteriorCell::parsePageCells($page, $header) as $interiorCell) {
            $this->collectTableLeafCells($interiorCell->leftChildPage, $visited, $cells, $limit);
            if ($limit !== null && count($cells) >= $limit) {
                return;
            }
        }
        $this->collectTableLeafCells($header->rightMostPointer, $visited, $cells, $limit);
    }

    /**
     * @param array<int, true> $visited
     * @param list<SQLiteIndexCell> $cells
     */
    private function collectIndexCells(int $pageNumber, array &$visited, array &$cells, ?int $limit): void
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite index cell limit cannot be negative');
        }
        if ($limit !== null && count($cells) >= $limit) {
            return;
        }
        if (isset($visited[$pageNumber])) {
            throw new \InvalidArgumentException("SQLite index b-tree traversal reached page {$pageNumber} more than once");
        }
        $visited[$pageNumber] = true;

        $page = $this->page($pageNumber);
        $header = SQLiteBTreePageHeader::parsePage(
            $page,
            $this->header->pageSize,
            $pageNumber === 1 ? 100 : 0,
        );
        if ($header->pageType !== 'index-leaf' && $header->pageType !== 'index-interior') {
            throw new \InvalidArgumentException("SQLite page {$pageNumber} is not an index b-tree page");
        }

        $overflowReader = fn (int $firstOverflowPage, int $byteCount): string => $this->readOverflowPayload($firstOverflowPage, $byteCount);
        $pageCells = SQLiteIndexCell::parsePageCells($page, $header, $this->usablePageSize(), $overflowReader);
        if ($header->pageType === 'index-leaf') {
            foreach ($pageCells as $cell) {
                if ($limit !== null && count($cells) >= $limit) {
                    return;
                }
                $cells[] = $cell;
            }

            return;
        }

        if ($header->rightMostPointer === null || $header->rightMostPointer < 1) {
            throw new \InvalidArgumentException("SQLite index interior page {$pageNumber} has an invalid right-most pointer");
        }
        foreach ($pageCells as $cell) {
            if ($cell->leftChildPage === null) {
                throw new \InvalidArgumentException("SQLite index interior page {$pageNumber} has a cell without a child pointer");
            }
            $this->collectIndexCells($cell->leftChildPage, $visited, $cells, $limit);
            if ($limit !== null && count($cells) >= $limit) {
                return;
            }
            $cells[] = $cell;
            if ($limit !== null && count($cells) >= $limit) {
                return;
            }
        }
        $this->collectIndexCells($header->rightMostPointer, $visited, $cells, $limit);
    }

    /**
     * @param array<int, true> $visited
     */
    private function findTableLeafCellByRowId(int $pageNumber, int $rowId, array &$visited): ?SQLiteTableLeafCell
    {
        if (isset($visited[$pageNumber])) {
            throw new \InvalidArgumentException("SQLite table b-tree rowid lookup reached page {$pageNumber} more than once");
        }
        $visited[$pageNumber] = true;

        $page = $this->page($pageNumber);
        $header = SQLiteBTreePageHeader::parsePage(
            $page,
            $this->header->pageSize,
            $pageNumber === 1 ? 100 : 0,
        );

        if ($header->pageType === 'table-leaf') {
            $overflowReader = fn (int $firstOverflowPage, int $byteCount): string => $this->readOverflowPayload($firstOverflowPage, $byteCount);
            foreach (SQLiteTableLeafCell::parsePageCells($page, $header, $this->usablePageSize(), $overflowReader) as $cell) {
                if ($cell->rowId === $rowId) {
                    return $cell;
                }
            }

            return null;
        }
        if ($header->pageType !== 'table-interior') {
            throw new \InvalidArgumentException("SQLite page {$pageNumber} is not a table b-tree page");
        }
        if ($header->rightMostPointer === null || $header->rightMostPointer < 1) {
            throw new \InvalidArgumentException("SQLite table interior page {$pageNumber} has an invalid right-most pointer");
        }

        foreach (SQLiteTableInteriorCell::parsePageCells($page, $header) as $interiorCell) {
            if ($rowId <= $interiorCell->key) {
                return $this->findTableLeafCellByRowId($interiorCell->leftChildPage, $rowId, $visited);
            }
        }

        return $this->findTableLeafCellByRowId($header->rightMostPointer, $rowId, $visited);
    }

    /**
     * @param array<int, true> $visited
     */
    private function findIndexCellByFirstValue(
        int $pageNumber,
        mixed $value,
        array &$visited,
        string $collation,
        bool $descending,
    ): ?SQLiteIndexCell
    {
        if (isset($visited[$pageNumber])) {
            throw new \InvalidArgumentException("SQLite index b-tree lookup reached page {$pageNumber} more than once");
        }
        $visited[$pageNumber] = true;

        $page = $this->page($pageNumber);
        $header = SQLiteBTreePageHeader::parsePage(
            $page,
            $this->header->pageSize,
            $pageNumber === 1 ? 100 : 0,
        );
        if ($header->pageType !== 'index-leaf' && $header->pageType !== 'index-interior') {
            throw new \InvalidArgumentException("SQLite page {$pageNumber} is not an index b-tree page");
        }

        $overflowReader = fn (int $firstOverflowPage, int $byteCount): string => $this->readOverflowPayload($firstOverflowPage, $byteCount);
        $cells = SQLiteIndexCell::parsePageCells($page, $header, $this->usablePageSize(), $overflowReader);
        if ($cells === []) {
            return null;
        }

        $lower = 0;
        $upper = count($cells) - 1;
        $comparison = -1;
        while ($lower <= $upper) {
            $index = intdiv($lower + $upper, 2);
            $record = $cells[$index]->record($this->header->textEncoding);
            if ($record->values === []) {
                throw new \InvalidArgumentException('SQLite index record must contain at least one key column');
            }
            $comparison = self::compareSQLiteScalar($record->values[0], $value, $collation);
            if ($descending) {
                $comparison = -$comparison;
            }
            if ($comparison < 0) {
                $lower = $index + 1;
            } elseif ($comparison > 0) {
                $upper = $index - 1;
            } else {
                return $cells[$index];
            }
        }

        if ($header->pageType === 'index-leaf') {
            return null;
        }
        if ($header->rightMostPointer === null || $header->rightMostPointer < 1) {
            throw new \InvalidArgumentException("SQLite index interior page {$pageNumber} has an invalid right-most pointer");
        }

        $childPage = $lower >= count($cells) ? $header->rightMostPointer : $cells[$lower]->leftChildPage;
        if ($childPage === null || $childPage < 1) {
            throw new \InvalidArgumentException("SQLite index interior page {$pageNumber} has an invalid child pointer");
        }

        return $this->findIndexCellByFirstValue($childPage, $value, $visited, $collation, $descending);
    }

    private function rowIdFromIndexCell(SQLiteIndexCell $cell): int
    {
        $record = $cell->record($this->header->textEncoding);
        if ($record->values === []) {
            throw new \InvalidArgumentException('SQLite index record must contain at least one value');
        }
        $rowId = $record->values[array_key_last($record->values)];
        if (!is_int($rowId)) {
            throw new \InvalidArgumentException('SQLite index record must end with an integer rowid');
        }

        return $rowId;
    }

    /**
     * @return list<SQLiteWordPressOption>
     */
    private function wordpressOptionsByIndexedFirstColumn(string $columnName, mixed $value, ?int $limit): array
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite wp_options indexed lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $tableRootPage = $this->tableRootPage('wp_options');
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForColumn('wp_options', $columnName, $value, true);
        if ($indexLookup === null) {
            throw new \InvalidArgumentException("SQLite wp_options {$columnName} index is not present");
        }

        $options = [];
        foreach (
            $this->indexCellsByFirstValue(
                $indexLookup['rootPage'],
                $value,
                $indexLookup['collation'],
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite wp_options index points to missing rowid {$rowId}");
            }

            $options[] = SQLiteWordPressOption::fromTableRow($row);
            if ($limit !== null && count($options) >= $limit) {
                break;
            }
        }

        return $options;
    }

    /**
     * @return list<SQLiteWordPressOption>
     */
    private function wordpressOptionsByIndexedFirstColumnRange(
        string $columnName,
        mixed $lowerInclusive,
        mixed $upperBound,
        ?int $limit,
        bool $upperInclusive = false,
    ): array {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite wp_options indexed range lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $tableRootPage = $this->tableRootPage('wp_options');
        if ($tableRootPage === null) {
            return [];
        }

        $indexLookup = $this->indexLookupForColumnRange('wp_options', $columnName, $lowerInclusive, $upperBound, $upperInclusive);
        if ($indexLookup === null) {
            throw new \InvalidArgumentException("SQLite wp_options {$columnName} range index is not present");
        }
        if ($lowerInclusive !== null && $upperBound !== null) {
            $boundaryComparison = self::compareSQLiteScalar($lowerInclusive, $upperBound, $indexLookup['collation']);
            if ($boundaryComparison > 0 || ($boundaryComparison === 0 && !$upperInclusive)) {
                return [];
            }
        }

        $options = [];
        foreach (
            $this->indexCellsByFirstValueRange(
                $indexLookup['rootPage'],
                $lowerInclusive,
                $upperBound,
                $indexLookup['collation'],
                $upperInclusive,
                $indexLookup['descending'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite wp_options index points to missing rowid {$rowId}");
            }

            $options[] = SQLiteWordPressOption::fromTableRow($row);
            if ($limit !== null && count($options) >= $limit) {
                break;
            }
        }

        return $options;
    }

    /**
     * @param non-empty-array<string, mixed> $columnValues
     * @return list<SQLiteWordPressOption>
     */
    private function wordpressOptionsByIndexedColumnPrefix(array $columnValues, ?int $limit): array
    {
        if ($columnValues === []) {
            throw new \InvalidArgumentException('SQLite wp_options indexed lookup requires at least one column');
        }
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite wp_options indexed lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $tableRootPage = $this->tableRootPage('wp_options');
        if ($tableRootPage === null) {
            return [];
        }

        $columnNames = array_keys($columnValues);
        $values = array_values($columnValues);
        $indexLookup = $this->indexLookupForColumnPrefix('wp_options', $columnNames, $values);
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite wp_options composite index is not present');
        }

        $options = [];
        foreach ($this->indexCellsByColumnPrefix($indexLookup['rootPage'], $values, $indexLookup['columns']) as $indexCell) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite wp_options index points to missing rowid {$rowId}");
            }

            $options[] = SQLiteWordPressOption::fromTableRow($row);
            if ($limit !== null && count($options) >= $limit) {
                break;
            }
        }

        return $options;
    }

    /**
     * @param non-empty-array<string, mixed> $equalityColumnValues
     * @return list<SQLiteWordPressOption>
     */
    private function wordpressOptionsByIndexedColumnPrefixRange(
        array $equalityColumnValues,
        string $rangeColumnName,
        mixed $lowerInclusive,
        mixed $upperBound,
        ?int $limit,
        bool $upperInclusive = false,
    ): array {
        if ($equalityColumnValues === []) {
            throw new \InvalidArgumentException('SQLite wp_options indexed range lookup requires at least one equality column');
        }
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite wp_options indexed range lookup limit cannot be negative');
        }
        if ($limit === 0) {
            return [];
        }

        $tableRootPage = $this->tableRootPage('wp_options');
        if ($tableRootPage === null) {
            return [];
        }

        $equalityColumnNames = array_keys($equalityColumnValues);
        $equalityValues = array_values($equalityColumnValues);
        $indexLookup = $this->indexLookupForColumnPrefixRange(
            'wp_options',
            $equalityColumnNames,
            $equalityValues,
            $rangeColumnName,
            $lowerInclusive,
            $upperBound,
            $upperInclusive,
        );
        if ($indexLookup === null) {
            throw new \InvalidArgumentException('SQLite wp_options composite range index is not present');
        }
        $rangeColumn = $indexLookup['columns'][count($equalityValues)] ?? null;
        if ($rangeColumn === null) {
            throw new \InvalidArgumentException('SQLite wp_options composite range index is missing the range column');
        }
        if ($lowerInclusive !== null && $upperBound !== null) {
            $boundaryComparison = self::compareSQLiteScalar($lowerInclusive, $upperBound, $rangeColumn->collation);
            if ($boundaryComparison > 0 || ($boundaryComparison === 0 && !$upperInclusive)) {
                return [];
            }
        }

        $options = [];
        foreach (
            $this->indexCellsByColumnPrefixRange(
                $indexLookup['rootPage'],
                $equalityValues,
                $lowerInclusive,
                $upperBound,
                $upperInclusive,
                $indexLookup['columns'],
            ) as $indexCell
        ) {
            $rowId = $this->rowIdFromIndexCell($indexCell);
            $row = $this->tableRowByRowId($tableRootPage, $rowId);
            if ($row === null) {
                throw new \InvalidArgumentException("SQLite wp_options index points to missing rowid {$rowId}");
            }

            $options[] = SQLiteWordPressOption::fromTableRow($row);
            if ($limit !== null && count($options) >= $limit) {
                break;
            }
        }

        return $options;
    }

    /**
     * @return list<SQLiteIndexCell>
     */
    private function indexCellsByFirstValue(
        int $rootPageNumber,
        mixed $value,
        string $collation,
        bool $descending = false,
    ): array
    {
        return $this->indexCellsByFirstValueRange(
            $rootPageNumber,
            $value,
            $value,
            $collation,
            true,
            $descending,
        );
    }

    /**
     * @param list<mixed> $values
     * @return list<SQLiteIndexCell>
     */
    private function indexCellsByFirstValueList(
        int $rootPageNumber,
        array $values,
        string $collation,
        bool $descending,
    ): array
    {
        $matches = [];
        $visited = [];
        $this->collectIndexCellsByFirstValueList(
            $rootPageNumber,
            $values,
            $collation,
            $descending,
            $visited,
            $matches,
            false,
            null,
            false,
            null,
        );

        return $matches;
    }

    /**
     * @param list<mixed> $values
     * @param array<int, true> $visited
     * @param list<SQLiteIndexCell> $matches
     */
    private function collectIndexCellsByFirstValueList(
        int $pageNumber,
        array $values,
        string $collation,
        bool $descending,
        array &$visited,
        array &$matches,
        bool $hasIntervalLower,
        mixed $intervalLower,
        bool $hasIntervalUpper,
        mixed $intervalUpper,
    ): void {
        if (
            !self::firstValueListIntersectsInterval(
                $values,
                $hasIntervalLower,
                $intervalLower,
                $hasIntervalUpper,
                $intervalUpper,
                $collation,
            )
        ) {
            return;
        }
        if (isset($visited[$pageNumber])) {
            throw new \InvalidArgumentException("SQLite index b-tree bounded IN-list lookup reached page {$pageNumber} more than once");
        }
        $visited[$pageNumber] = true;

        $page = $this->page($pageNumber);
        $header = SQLiteBTreePageHeader::parsePage(
            $page,
            $this->header->pageSize,
            $pageNumber === 1 ? 100 : 0,
        );
        if ($header->pageType !== 'index-leaf' && $header->pageType !== 'index-interior') {
            throw new \InvalidArgumentException("SQLite page {$pageNumber} is not an index b-tree page");
        }

        $overflowReader = fn (int $firstOverflowPage, int $byteCount): string => $this->readOverflowPayload($firstOverflowPage, $byteCount);
        $cells = SQLiteIndexCell::parsePageCells($page, $header, $this->usablePageSize(), $overflowReader);
        if ($header->pageType === 'index-leaf') {
            foreach ($cells as $cell) {
                $record = $cell->record($this->header->textEncoding);
                if ($record->values === []) {
                    throw new \InvalidArgumentException('SQLite index record must contain at least one key column');
                }
                if (self::inListContainsSQLiteScalar($values, $record->values[0], $collation)) {
                    $matches[] = $cell;
                }
            }

            return;
        }

        if ($header->rightMostPointer === null || $header->rightMostPointer < 1) {
            throw new \InvalidArgumentException("SQLite index interior page {$pageNumber} has an invalid right-most pointer");
        }

        $hasPrevious = false;
        $previousValue = null;
        foreach ($cells as $cell) {
            if ($cell->leftChildPage === null || $cell->leftChildPage < 1) {
                throw new \InvalidArgumentException("SQLite index interior page {$pageNumber} has an invalid child pointer");
            }

            $record = $cell->record($this->header->textEncoding);
            if ($record->values === []) {
                throw new \InvalidArgumentException('SQLite index record must contain at least one key column');
            }
            $currentValue = $record->values[0];

            $childHasLower = $descending ? true : $hasPrevious;
            $childLower = $descending ? $currentValue : $previousValue;
            $childHasUpper = $descending ? $hasPrevious : true;
            $childUpper = $descending ? $previousValue : $currentValue;
            $this->collectIndexCellsByFirstValueList(
                $cell->leftChildPage,
                $values,
                $collation,
                $descending,
                $visited,
                $matches,
                $childHasLower,
                $childLower,
                $childHasUpper,
                $childUpper,
            );

            if (self::inListContainsSQLiteScalar($values, $currentValue, $collation)) {
                $matches[] = $cell;
            }

            $hasPrevious = true;
            $previousValue = $currentValue;
        }

        $rightHasLower = $descending ? false : $hasPrevious;
        $rightLower = $descending ? null : $previousValue;
        $rightHasUpper = $descending ? $hasPrevious : false;
        $rightUpper = $descending ? $previousValue : null;
        $this->collectIndexCellsByFirstValueList(
            $header->rightMostPointer,
            $values,
            $collation,
            $descending,
            $visited,
            $matches,
            $rightHasLower,
            $rightLower,
            $rightHasUpper,
            $rightUpper,
        );
    }

    /**
     * @return list<SQLiteIndexCell>
     */
    private function indexCellsByFirstValueRange(
        int $rootPageNumber,
        mixed $lowerInclusive,
        mixed $upperBound,
        string $collation,
        bool $upperInclusive = false,
        bool $descending = false,
    ): array {
        $matches = [];
        $visited = [];
        $this->collectIndexCellsByFirstValueRange(
            $rootPageNumber,
            $lowerInclusive,
            $upperBound,
            $upperInclusive,
            $collation,
            $descending,
            $visited,
            $matches,
            false,
            null,
            false,
            null,
        );

        return $matches;
    }

    /**
     * @param array<int, true> $visited
     * @param list<SQLiteIndexCell> $matches
     */
    private function collectIndexCellsByFirstValueRange(
        int $pageNumber,
        mixed $lowerInclusive,
        mixed $upperBound,
        bool $upperInclusive,
        string $collation,
        bool $descending,
        array &$visited,
        array &$matches,
        bool $hasIntervalLower,
        mixed $intervalLower,
        bool $hasIntervalUpper,
        mixed $intervalUpper,
    ): void {
        if (
            !self::firstValueRangeIntersectsInterval(
                $lowerInclusive,
                $upperBound,
                $upperInclusive,
                $hasIntervalLower,
                $intervalLower,
                $hasIntervalUpper,
                $intervalUpper,
                $collation,
            )
        ) {
            return;
        }
        if (isset($visited[$pageNumber])) {
            throw new \InvalidArgumentException("SQLite index b-tree bounded lookup reached page {$pageNumber} more than once");
        }
        $visited[$pageNumber] = true;

        $page = $this->page($pageNumber);
        $header = SQLiteBTreePageHeader::parsePage(
            $page,
            $this->header->pageSize,
            $pageNumber === 1 ? 100 : 0,
        );
        if ($header->pageType !== 'index-leaf' && $header->pageType !== 'index-interior') {
            throw new \InvalidArgumentException("SQLite page {$pageNumber} is not an index b-tree page");
        }

        $overflowReader = fn (int $firstOverflowPage, int $byteCount): string => $this->readOverflowPayload($firstOverflowPage, $byteCount);
        $cells = SQLiteIndexCell::parsePageCells($page, $header, $this->usablePageSize(), $overflowReader);
        if ($header->pageType === 'index-leaf') {
            foreach ($cells as $cell) {
                $record = $cell->record($this->header->textEncoding);
                if ($record->values === []) {
                    throw new \InvalidArgumentException('SQLite index record must contain at least one key column');
                }
                if (self::firstValueIsInRange($record->values[0], $lowerInclusive, $upperBound, $upperInclusive, $collation)) {
                    $matches[] = $cell;
                }
            }

            return;
        }

        if ($header->rightMostPointer === null || $header->rightMostPointer < 1) {
            throw new \InvalidArgumentException("SQLite index interior page {$pageNumber} has an invalid right-most pointer");
        }

        $hasPrevious = false;
        $previousValue = null;
        foreach ($cells as $cell) {
            if ($cell->leftChildPage === null || $cell->leftChildPage < 1) {
                throw new \InvalidArgumentException("SQLite index interior page {$pageNumber} has an invalid child pointer");
            }

            $record = $cell->record($this->header->textEncoding);
            if ($record->values === []) {
                throw new \InvalidArgumentException('SQLite index record must contain at least one key column');
            }
            $currentValue = $record->values[0];

            $childHasLower = $descending ? true : $hasPrevious;
            $childLower = $descending ? $currentValue : $previousValue;
            $childHasUpper = $descending ? $hasPrevious : true;
            $childUpper = $descending ? $previousValue : $currentValue;
            $this->collectIndexCellsByFirstValueRange(
                $cell->leftChildPage,
                $lowerInclusive,
                $upperBound,
                $upperInclusive,
                $collation,
                $descending,
                $visited,
                $matches,
                $childHasLower,
                $childLower,
                $childHasUpper,
                $childUpper,
            );

            if (self::firstValueIsInRange($currentValue, $lowerInclusive, $upperBound, $upperInclusive, $collation)) {
                $matches[] = $cell;
            }

            $hasPrevious = true;
            $previousValue = $currentValue;
        }

        $rightHasLower = $descending ? false : $hasPrevious;
        $rightLower = $descending ? null : $previousValue;
        $rightHasUpper = $descending ? $hasPrevious : false;
        $rightUpper = $descending ? $previousValue : null;
        $this->collectIndexCellsByFirstValueRange(
            $header->rightMostPointer,
            $lowerInclusive,
            $upperBound,
            $upperInclusive,
            $collation,
            $descending,
            $visited,
            $matches,
            $rightHasLower,
            $rightLower,
            $rightHasUpper,
            $rightUpper,
        );
    }

    private static function firstValueIsInRange(
        mixed $value,
        mixed $lowerInclusive,
        mixed $upperBound,
        bool $upperInclusive,
        string $collation,
    ): bool {
        if (($lowerInclusive !== null || $upperBound !== null) && $value === null) {
            return false;
        }
        if ($lowerInclusive !== null && self::compareSQLiteScalar($value, $lowerInclusive, $collation) < 0) {
            return false;
        }
        if ($upperBound !== null) {
            $upperComparison = self::compareSQLiteScalar($value, $upperBound, $collation);
            if ($upperComparison > 0 || ($upperComparison === 0 && !$upperInclusive)) {
                return false;
            }
        }

        return true;
    }

    private static function firstValueRangeIntersectsInterval(
        mixed $lowerInclusive,
        mixed $upperBound,
        bool $upperInclusive,
        bool $hasIntervalLower,
        mixed $intervalLower,
        bool $hasIntervalUpper,
        mixed $intervalUpper,
        string $collation,
    ): bool {
        if (
            $lowerInclusive !== null
            && $hasIntervalUpper
            && self::compareSQLiteScalar($intervalUpper, $lowerInclusive, $collation) < 0
        ) {
            return false;
        }
        if ($upperBound !== null && $hasIntervalLower) {
            $lowerToUpper = self::compareSQLiteScalar($intervalLower, $upperBound, $collation);
            if ($lowerToUpper > 0 || ($lowerToUpper === 0 && !$upperInclusive)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<mixed> $values
     */
    private static function firstValueListIntersectsInterval(
        array $values,
        bool $hasIntervalLower,
        mixed $intervalLower,
        bool $hasIntervalUpper,
        mixed $intervalUpper,
        string $collation,
    ): bool {
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }
            if (
                $hasIntervalLower
                && self::compareSQLiteScalar($value, $intervalLower, $collation) < 0
            ) {
                continue;
            }
            if (
                $hasIntervalUpper
                && self::compareSQLiteScalar($value, $intervalUpper, $collation) > 0
            ) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @param list<mixed> $equalityValues
     * @param non-empty-list<SQLiteIndexColumn> $columns
     * @return list<SQLiteIndexCell>
     */
    private function indexCellsByColumnPrefixRange(
        int $rootPageNumber,
        array $equalityValues,
        mixed $lowerInclusive,
        mixed $upperBound,
        bool $upperInclusive,
        array $columns,
    ): array {
        $rangeIndex = count($equalityValues);
        $rangeColumn = $columns[$rangeIndex] ?? null;
        if (!$rangeColumn instanceof SQLiteIndexColumn) {
            throw new \InvalidArgumentException('SQLite index range column metadata is missing');
        }

        $matches = [];
        $visited = [];
        $this->collectIndexCellsByColumnPrefixRange(
            $rootPageNumber,
            $equalityValues,
            $lowerInclusive,
            $upperBound,
            $upperInclusive,
            $columns,
            $visited,
            $matches,
            false,
            null,
            false,
            null,
        );

        return $matches;
    }

    /**
     * @param list<mixed> $equalityValues
     * @param non-empty-list<SQLiteIndexColumn> $columns
     * @param array<int, true> $visited
     * @param list<SQLiteIndexCell> $matches
     * @param null|list<mixed> $intervalLowerValues
     * @param null|list<mixed> $intervalUpperValues
     */
    private function collectIndexCellsByColumnPrefixRange(
        int $pageNumber,
        array $equalityValues,
        mixed $lowerInclusive,
        mixed $upperBound,
        bool $upperInclusive,
        array $columns,
        array &$visited,
        array &$matches,
        bool $hasIntervalLower,
        ?array $intervalLowerValues,
        bool $hasIntervalUpper,
        ?array $intervalUpperValues,
    ): void {
        if (
            !self::columnPrefixRangeIntersectsInterval(
                $equalityValues,
                $lowerInclusive,
                $upperBound,
                $columns,
                $hasIntervalLower,
                $intervalLowerValues,
                $hasIntervalUpper,
                $intervalUpperValues,
            )
        ) {
            return;
        }
        if (isset($visited[$pageNumber])) {
            throw new \InvalidArgumentException("SQLite index b-tree bounded composite lookup reached page {$pageNumber} more than once");
        }
        $visited[$pageNumber] = true;

        $page = $this->page($pageNumber);
        $header = SQLiteBTreePageHeader::parsePage(
            $page,
            $this->header->pageSize,
            $pageNumber === 1 ? 100 : 0,
        );
        if ($header->pageType !== 'index-leaf' && $header->pageType !== 'index-interior') {
            throw new \InvalidArgumentException("SQLite page {$pageNumber} is not an index b-tree page");
        }

        $overflowReader = fn (int $firstOverflowPage, int $byteCount): string => $this->readOverflowPayload($firstOverflowPage, $byteCount);
        $cells = SQLiteIndexCell::parsePageCells($page, $header, $this->usablePageSize(), $overflowReader);
        if ($header->pageType === 'index-leaf') {
            foreach ($cells as $cell) {
                $record = $cell->record($this->header->textEncoding);
                if (self::indexRecordMatchesColumnPrefixRange(
                    $record->values,
                    $equalityValues,
                    $lowerInclusive,
                    $upperBound,
                    $upperInclusive,
                    $columns,
                )) {
                    $matches[] = $cell;
                }
            }

            return;
        }

        if ($header->rightMostPointer === null || $header->rightMostPointer < 1) {
            throw new \InvalidArgumentException("SQLite index interior page {$pageNumber} has an invalid right-most pointer");
        }

        $hasPrevious = false;
        $previousValues = null;
        foreach ($cells as $cell) {
            if ($cell->leftChildPage === null || $cell->leftChildPage < 1) {
                throw new \InvalidArgumentException("SQLite index interior page {$pageNumber} has an invalid child pointer");
            }

            $record = $cell->record($this->header->textEncoding);
            $currentValues = $record->values;
            $this->collectIndexCellsByColumnPrefixRange(
                $cell->leftChildPage,
                $equalityValues,
                $lowerInclusive,
                $upperBound,
                $upperInclusive,
                $columns,
                $visited,
                $matches,
                $hasPrevious ? true : $hasIntervalLower,
                $hasPrevious ? $previousValues : $intervalLowerValues,
                true,
                $currentValues,
            );

            if (self::indexRecordMatchesColumnPrefixRange(
                $record->values,
                $equalityValues,
                $lowerInclusive,
                $upperBound,
                $upperInclusive,
                $columns,
            )) {
                $matches[] = $cell;
            }

            $hasPrevious = true;
            $previousValues = $currentValues;
        }

        $this->collectIndexCellsByColumnPrefixRange(
            $header->rightMostPointer,
            $equalityValues,
            $lowerInclusive,
            $upperBound,
            $upperInclusive,
            $columns,
            $visited,
            $matches,
            $hasPrevious ? true : $hasIntervalLower,
            $hasPrevious ? $previousValues : $intervalLowerValues,
            $hasIntervalUpper,
            $intervalUpperValues,
        );
    }

    /**
     * @param list<mixed> $recordValues
     * @param list<mixed> $equalityValues
     * @param non-empty-list<SQLiteIndexColumn> $columns
     */
    private static function indexRecordMatchesColumnPrefixRange(
        array $recordValues,
        array $equalityValues,
        mixed $lowerInclusive,
        mixed $upperBound,
        bool $upperInclusive,
        array $columns,
    ): bool {
        $rangeIndex = count($equalityValues);
        $rangeColumn = $columns[$rangeIndex] ?? null;
        if (!$rangeColumn instanceof SQLiteIndexColumn) {
            throw new \InvalidArgumentException('SQLite index range column metadata is missing');
        }
        if (count($recordValues) <= $rangeIndex) {
            throw new \InvalidArgumentException('SQLite index record has fewer values than the constrained prefix range');
        }

        foreach ($equalityValues as $index => $value) {
            if (self::compareSQLiteScalar($recordValues[$index], $value, $columns[$index]->collation) !== 0) {
                return false;
            }
        }

        $rangeValue = $recordValues[$rangeIndex];
        if (($lowerInclusive !== null || $upperBound !== null) && $rangeValue === null) {
            return false;
        }
        if ($lowerInclusive !== null && self::compareSQLiteScalar($rangeValue, $lowerInclusive, $rangeColumn->collation) < 0) {
            return false;
        }
        if ($upperBound !== null) {
            $upperComparison = self::compareSQLiteScalar($rangeValue, $upperBound, $rangeColumn->collation);
            if ($upperComparison > 0 || ($upperComparison === 0 && !$upperInclusive)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<mixed> $equalityValues
     * @param non-empty-list<SQLiteIndexColumn> $columns
     * @param null|list<mixed> $intervalLowerValues
     * @param null|list<mixed> $intervalUpperValues
     */
    private static function columnPrefixRangeIntersectsInterval(
        array $equalityValues,
        mixed $lowerInclusive,
        mixed $upperBound,
        array $columns,
        bool $hasIntervalLower,
        ?array $intervalLowerValues,
        bool $hasIntervalUpper,
        ?array $intervalUpperValues,
    ): bool {
        $rangeIndex = count($equalityValues);
        $prefixLength = count($equalityValues);
        $rangeColumn = $columns[$rangeIndex] ?? null;
        if (!$rangeColumn instanceof SQLiteIndexColumn) {
            throw new \InvalidArgumentException('SQLite index range column metadata is missing');
        }

        if ($prefixLength > 0) {
            if (
                $hasIntervalUpper
                && $intervalUpperValues !== null
                && count($intervalUpperValues) >= $prefixLength
                && self::compareIndexKeyValues(
                    array_slice($intervalUpperValues, 0, $prefixLength),
                    $equalityValues,
                    array_slice($columns, 0, $prefixLength),
                ) < 0
            ) {
                return false;
            }
            if (
                $hasIntervalLower
                && $intervalLowerValues !== null
                && count($intervalLowerValues) >= $prefixLength
                && self::compareIndexKeyValues(
                    array_slice($intervalLowerValues, 0, $prefixLength),
                    $equalityValues,
                    array_slice($columns, 0, $prefixLength),
                ) > 0
            ) {
                return false;
            }
        }

        $physicalLower = null;
        $physicalUpper = null;
        if ($rangeColumn->descending) {
            if ($upperBound !== null) {
                $physicalLower = array_merge($equalityValues, [$upperBound]);
            }
            if ($lowerInclusive !== null) {
                $physicalUpper = array_merge($equalityValues, [$lowerInclusive]);
            }
        } else {
            if ($lowerInclusive !== null) {
                $physicalLower = array_merge($equalityValues, [$lowerInclusive]);
            }
            if ($upperBound !== null) {
                $physicalUpper = array_merge($equalityValues, [$upperBound]);
            }
        }

        $constrainedColumns = array_slice($columns, 0, $rangeIndex + 1);
        if (
            $physicalLower !== null
            && $hasIntervalUpper
            && $intervalUpperValues !== null
            && count($intervalUpperValues) >= $rangeIndex + 1
            && self::compareIndexKeyValues(
                array_slice($intervalUpperValues, 0, $rangeIndex + 1),
                $physicalLower,
                $constrainedColumns,
            ) < 0
        ) {
            return false;
        }
        if (
            $physicalUpper !== null
            && $hasIntervalLower
            && $intervalLowerValues !== null
            && count($intervalLowerValues) >= $rangeIndex + 1
            && self::compareIndexKeyValues(
                array_slice($intervalLowerValues, 0, $rangeIndex + 1),
                $physicalUpper,
                $constrainedColumns,
            ) > 0
        ) {
            return false;
        }

        return true;
    }

    /**
     * @param list<mixed> $leftValues
     * @param list<mixed> $rightValues
     * @param list<SQLiteIndexColumn> $columns
     */
    private static function compareIndexKeyValues(array $leftValues, array $rightValues, array $columns): int
    {
        foreach ($columns as $index => $column) {
            if (!array_key_exists($index, $leftValues) || !array_key_exists($index, $rightValues)) {
                break;
            }
            $comparison = self::compareSQLiteScalar($leftValues[$index], $rightValues[$index], $column->collation);
            if ($comparison !== 0) {
                return $column->descending ? -$comparison : $comparison;
            }
        }

        return count($leftValues) <=> count($rightValues);
    }

    /**
     * @param list<mixed> $values
     * @param non-empty-list<SQLiteIndexColumn> $columns
     * @return list<SQLiteIndexCell>
     */
    private function indexCellsByColumnPrefix(int $rootPageNumber, array $values, array $columns): array
    {
        $matches = [];
        foreach ($this->indexCells($rootPageNumber) as $cell) {
            $record = $cell->record($this->header->textEncoding);
            if (count($record->values) < count($values)) {
                throw new \InvalidArgumentException('SQLite index record has fewer values than the constrained prefix');
            }

            foreach ($values as $index => $value) {
                if (self::compareSQLiteScalar($record->values[$index], $value, $columns[$index]->collation) !== 0) {
                    continue 2;
                }
            }

            $matches[] = $cell;
        }

        return $matches;
    }

    /**
     * @param non-empty-list<string> $columnNames
     * @param non-empty-list<mixed> $pointLookupValues
     * @return null|array{rootPage:int,columns:non-empty-list<SQLiteIndexColumn>}
     */
    private function indexLookupForColumnPrefix(string $tableName, array $columnNames, array $pointLookupValues): ?array
    {
        if ($columnNames === []) {
            throw new \InvalidArgumentException('SQLite index prefix lookup requires at least one column');
        }
        if (count($columnNames) !== count($pointLookupValues)) {
            throw new \InvalidArgumentException('SQLite index prefix lookup requires one value per column');
        }

        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $columns = SQLiteCreateIndex::columns($record->sql);
            if ($columns === null || count($columns) < count($columnNames)) {
                continue;
            }

            $prefix = array_slice($columns, 0, count($columnNames));
            foreach ($prefix as $index => $column) {
                if (strcasecmp($column->columnName, $columnNames[$index]) !== 0) {
                    continue 2;
                }
            }

            if (
                $prefix[0]->partial
                && (
                    $prefix[0]->partialPredicate === null
                    || !self::partialPredicateIsImpliedByConstraints(
                        $prefix[0]->partialPredicate,
                        array_combine($columnNames, $pointLookupValues),
                        [],
                        true,
                    )
                )
            ) {
                continue;
            }

            return [
                'rootPage' => $record->rootPage,
                'columns' => $prefix,
            ];
        }

        return null;
    }

    /**
     * @param non-empty-list<string> $equalityColumnNames
     * @param non-empty-list<mixed> $pointLookupValues
     * @return null|array{rootPage:int,columns:non-empty-list<SQLiteIndexColumn>}
     */
    private function indexLookupForColumnPrefixRange(
        string $tableName,
        array $equalityColumnNames,
        array $pointLookupValues,
        string $rangeColumnName,
        mixed $lowerInclusive = null,
        mixed $upperBound = null,
        bool $upperInclusive = false,
    ): ?array {
        if ($equalityColumnNames === []) {
            throw new \InvalidArgumentException('SQLite index prefix range lookup requires at least one equality column');
        }
        if (count($equalityColumnNames) !== count($pointLookupValues)) {
            throw new \InvalidArgumentException('SQLite index prefix range lookup requires one value per equality column');
        }
        if ($lowerInclusive === null && $upperBound === null) {
            throw new \InvalidArgumentException('SQLite index prefix range lookup requires at least one range bound');
        }

        $wantedColumnNames = array_merge($equalityColumnNames, [$rangeColumnName]);
        foreach ($this->indexRecordsForTable($tableName) as $record) {
            if ($record->sql === null) {
                continue;
            }

            $columns = SQLiteCreateIndex::columns($record->sql);
            if ($columns === null || count($columns) < count($wantedColumnNames)) {
                continue;
            }

            $prefix = array_slice($columns, 0, count($wantedColumnNames));
            foreach ($prefix as $index => $column) {
                if (strcasecmp($column->columnName, $wantedColumnNames[$index]) !== 0) {
                    continue 2;
                }
            }

            if ($prefix[0]->partial) {
                $equalityConstraints = array_combine($equalityColumnNames, $pointLookupValues);
                if ($equalityConstraints === false) {
                    $equalityConstraints = [];
                }
                $rangeConstraints = [
                    $rangeColumnName => [
                        'lowerInclusive' => $lowerInclusive,
                        'upperBound' => $upperBound,
                        'upperInclusive' => $upperInclusive,
                    ],
                ];
                if (
                    $prefix[0]->partialPredicate === null
                    || !self::partialPredicateIsImpliedByConstraints(
                        $prefix[0]->partialPredicate,
                        $equalityConstraints,
                        $rangeConstraints,
                        true,
                    )
                ) {
                    continue;
                }
            }

            return [
                'rootPage' => $record->rootPage,
                'columns' => $prefix,
            ];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $columnValues
     */
    private static function partialPredicateIsImpliedByConstraints(
        SQLiteIndexPredicate $predicate,
        array $columnValues,
        array $rangeConstraints,
        bool $allowEqualityPredicate,
    ): bool {
        if ($predicate->operator === SQLiteIndexPredicate::AND) {
            if (!is_array($predicate->value)) {
                return false;
            }

            foreach ($predicate->value as $subPredicate) {
                if (
                    !$subPredicate instanceof SQLiteIndexPredicate
                    || !self::partialPredicateIsImpliedByConstraints(
                        $subPredicate,
                        $columnValues,
                        $rangeConstraints,
                        $allowEqualityPredicate,
                    )
                ) {
                    return false;
                }
            }

            return true;
        }

        if ($predicate->operator === SQLiteIndexPredicate::OR) {
            if (!is_array($predicate->value)) {
                return false;
            }

            foreach ($predicate->value as $subPredicate) {
                if (
                    $subPredicate instanceof SQLiteIndexPredicate
                    && self::partialPredicateIsImpliedByConstraints(
                        $subPredicate,
                        $columnValues,
                        $rangeConstraints,
                        $allowEqualityPredicate,
                    )
                ) {
                    return true;
                }
            }

            return false;
        }

        if (!$allowEqualityPredicate && $predicate->operator === SQLiteIndexPredicate::EQUALS) {
            return false;
        }

        foreach ($columnValues as $columnName => $value) {
            if ($predicate->isImpliedByPointLookup((string) $columnName, $value)) {
                return true;
            }
        }
        foreach ($rangeConstraints as $columnName => $bounds) {
            if (
                is_array($bounds)
                && $predicate->isImpliedByRangeLookup(
                    (string) $columnName,
                    $bounds['lowerInclusive'] ?? null,
                    $bounds['upperBound'] ?? null,
                    (bool) ($bounds['upperInclusive'] ?? false),
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<mixed> $values
     */
    private static function partialPredicateIsImpliedByInListConstraints(
        SQLiteIndexPredicate $predicate,
        string $columnName,
        array $values,
    ): bool {
        return $predicate->isImpliedByInListLookup($columnName, $values);
    }

    private static function lowerExpressionRangeImpliesPartialPredicate(
        SQLiteIndexPredicate $predicate,
        string $columnName,
    ): bool {
        if ($predicate->operator === SQLiteIndexPredicate::AND) {
            if (!is_array($predicate->value)) {
                return false;
            }

            foreach ($predicate->value as $subPredicate) {
                if (
                    !$subPredicate instanceof SQLiteIndexPredicate
                    || !self::lowerExpressionRangeImpliesPartialPredicate($subPredicate, $columnName)
                ) {
                    return false;
                }
            }

            return true;
        }

        if ($predicate->operator === SQLiteIndexPredicate::OR) {
            if (!is_array($predicate->value)) {
                return false;
            }

            foreach ($predicate->value as $subPredicate) {
                if (
                    $subPredicate instanceof SQLiteIndexPredicate
                    && self::lowerExpressionRangeImpliesPartialPredicate($subPredicate, $columnName)
                ) {
                    return true;
                }
            }

            return false;
        }

        return strcasecmp($predicate->columnName, $columnName) === 0
            && $predicate->operator === SQLiteIndexPredicate::IS_NOT_NULL;
    }

    private function automaticIndexFirstColumnsForTable(string $tableName): array
    {
        foreach ($this->schemaRecords() as $record) {
            if ($record->isTable($tableName) && $record->sql !== null) {
                return SQLiteCreateTable::automaticIndexFirstColumnMetadata($record->sql);
            }
        }

        return [];
    }

    private static function isAutomaticIndex(SQLiteSchemaRecord $record, string $tableName): bool
    {
        return $record->type === 'index'
            && $record->tableName === $tableName
            && str_starts_with($record->name, "sqlite_autoindex_{$tableName}_");
    }

    /**
     * @param list<mixed> $values
     */
    private static function inListContainsSQLiteScalar(array $values, mixed $needle, string $collation): bool
    {
        if ($needle === null) {
            return false;
        }

        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }
            if (self::compareSQLiteScalar($needle, $value, $collation) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<mixed> $values
     */
    private static function containsNonNullValue(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null) {
                return true;
            }
        }

        return false;
    }

    private static function compareSQLiteScalar(mixed $left, mixed $right, string $collation = 'BINARY'): int
    {
        $leftRank = self::sqliteScalarRank($left);
        $rightRank = self::sqliteScalarRank($right);
        if ($leftRank !== $rightRank) {
            return $leftRank <=> $rightRank;
        }
        if ($left === null && $right === null) {
            return 0;
        }
        if (is_int($left) || is_float($left)) {
            return $left <=> $right;
        }
        if (is_string($left)) {
            if (!is_string($right)) {
                throw new \InvalidArgumentException('SQLite scalar comparison values must share a storage class');
            }

            return self::compareSQLiteText($left, $right, $collation);
        }

        throw new \InvalidArgumentException('Unsupported SQLite scalar comparison value');
    }

    private static function compareSQLiteText(string $left, string $right, string $collation): int
    {
        return match (strtoupper($collation)) {
            'BINARY' => strcmp($left, $right),
            'NOCASE' => strcmp(self::asciiLower($left), self::asciiLower($right)),
            'RTRIM' => strcmp(rtrim($left, ' '), rtrim($right, ' ')),
            default => throw new \InvalidArgumentException("Unsupported SQLite index collation: {$collation}"),
        };
    }

    private static function asciiLower(string $value): string
    {
        $bytes = $value;
        $length = strlen($bytes);
        for ($i = 0; $i < $length; $i++) {
            $ord = ord($bytes[$i]);
            if ($ord >= 0x41 && $ord <= 0x5a) {
                $bytes[$i] = chr($ord + 0x20);
            }
        }

        return $bytes;
    }

    private static function asciiUpper(string $value): string
    {
        $bytes = $value;
        $length = strlen($bytes);
        for ($i = 0; $i < $length; $i++) {
            $ord = ord($bytes[$i]);
            if ($ord >= 0x61 && $ord <= 0x7a) {
                $bytes[$i] = chr($ord - 0x20);
            }
        }

        return $bytes;
    }

    private static function normalizeTrimFunctionName(string $functionName): string
    {
        $normalized = strtolower($functionName);
        if (!in_array($normalized, ['trim', 'ltrim', 'rtrim'], true)) {
            throw new \InvalidArgumentException('SQLite trim expression lookup function must be trim, ltrim, or rtrim');
        }

        return $normalized;
    }

    private static function sqliteTrim(string $value, string $functionName, ?string $characters): string
    {
        $functionName = self::normalizeTrimFunctionName($functionName);
        $characters ??= ' ';
        if ($characters === '' || $value === '') {
            return $value;
        }

        $valueCharacters = self::sqliteTextCharacters($value);
        $trimCharacters = self::sqliteTextCharacters($characters);
        if ($valueCharacters === null || $trimCharacters === null) {
            return self::sqliteTrimBytes($value, $functionName, $characters);
        }

        $trimSet = array_fill_keys($trimCharacters, true);
        if ($functionName === 'trim' || $functionName === 'ltrim') {
            while ($valueCharacters !== [] && isset($trimSet[$valueCharacters[0]])) {
                array_shift($valueCharacters);
            }
        }
        if ($functionName === 'trim' || $functionName === 'rtrim') {
            while ($valueCharacters !== []) {
                $lastIndex = count($valueCharacters) - 1;
                if (!isset($trimSet[$valueCharacters[$lastIndex]])) {
                    break;
                }
                array_pop($valueCharacters);
            }
        }

        return implode('', $valueCharacters);
    }

    /**
     * @return null|list<string>
     */
    private static function sqliteTextCharacters(string $value): ?array
    {
        if ($value === '') {
            return [];
        }
        if (function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8')) {
            return null;
        }

        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if ($characters === false) {
            return null;
        }

        return $characters;
    }

    private static function sqliteTrimBytes(string $value, string $functionName, string $characters): string
    {
        $trimBytes = [];
        for ($i = 0, $length = strlen($characters); $i < $length; $i++) {
            $trimBytes[$characters[$i]] = true;
        }

        $start = 0;
        $end = strlen($value);
        if ($functionName === 'trim' || $functionName === 'ltrim') {
            while ($start < $end && isset($trimBytes[$value[$start]])) {
                $start++;
            }
        }
        if ($functionName === 'trim' || $functionName === 'rtrim') {
            while ($end > $start && isset($trimBytes[$value[$end - 1]])) {
                $end--;
            }
        }

        return substr($value, $start, $end - $start);
    }

    private static function sqliteSubstring(string $value, int $start, ?int $length): string
    {
        if ($start === 0) {
            throw new \InvalidArgumentException('SQLite substr helper in this slice does not support zero start offsets');
        }
        if ($length !== null && $length < 0) {
            throw new \InvalidArgumentException('SQLite substr helper in this slice requires a non-negative length');
        }

        if (function_exists('mb_check_encoding') && function_exists('mb_substr') && mb_check_encoding($value, 'UTF-8')) {
            $offset = $start > 0 ? $start - 1 : $start;
            if ($length === null) {
                return mb_substr($value, $offset, null, 'UTF-8');
            }

            return mb_substr($value, $offset, $length, 'UTF-8');
        }

        $offset = $start > 0 ? $start - 1 : $start;
        if ($length === null) {
            return substr($value, $offset);
        }

        return substr($value, $offset, $length);
    }

    private static function sqliteLength(string $value): int
    {
        if (function_exists('mb_check_encoding') && function_exists('mb_strlen') && mb_check_encoding($value, 'UTF-8')) {
            return mb_strlen($value, 'UTF-8');
        }
        if (preg_match('//u', $value) === 1) {
            $count = preg_match_all('/./us', $value);
            if (is_int($count)) {
                return $count;
            }
        }

        return strlen($value);
    }

    private static function sqliteCastAsInteger(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        if (!is_string($value)) {
            throw new \InvalidArgumentException('SQLite CAST AS INTEGER value must be scalar text, numeric, or null');
        }

        $text = ltrim($value);
        if (!preg_match('/^[+-]?\d+/', $text, $matches)) {
            return 0;
        }

        $integer = $matches[0];
        $negative = str_starts_with($integer, '-');
        if ($integer[0] === '-' || $integer[0] === '+') {
            $integer = substr($integer, 1);
        }

        $digits = ltrim($integer, '0');
        if ($digits === '') {
            return 0;
        }

        $limit = $negative ? '9223372036854775808' : '9223372036854775807';
        if (strlen($digits) > strlen($limit) || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) > 0)) {
            return $negative ? PHP_INT_MIN : PHP_INT_MAX;
        }
        if ($negative && $digits === '9223372036854775808') {
            return PHP_INT_MIN;
        }

        $parsed = (int) $digits;

        return $negative ? -$parsed : $parsed;
    }

    private static function sqliteJsonExtract(mixed $json, string $path, ?int $serialType = null): mixed
    {
        $located = self::sqliteJsonLocate($json, $path, $serialType);
        if (!$located['found']) {
            return null;
        }

        return self::sqliteJsonScalar($located['value']);
    }

    private static function sqliteJsonValueOperator(mixed $json, string $path, ?int $serialType = null): ?string
    {
        $located = self::sqliteJsonLocate($json, $path, $serialType);
        if (!$located['found']) {
            return null;
        }

        return self::sqliteJsonTextValue($located['value']);
    }

    /**
     * @return array{found:bool,value:mixed}
     */
    private static function sqliteJsonLocate(mixed $json, string $path, ?int $serialType = null): array
    {
        $segments = self::parseSimpleJsonPath($path);
        if ($json === null) {
            return ['found' => false, 'value' => null];
        }
        if (!is_string($json)) {
            $json = (string) self::sqliteJsonScalar($json);
        }

        $value = self::decodeSQLiteJsonInput($json, $serialType);

        foreach ($segments as $segment) {
            if ($segment['kind'] === 'index' || $segment['kind'] === 'indexFromEnd' || $segment['kind'] === 'arrayAppend') {
                if (!is_array($value) || !array_is_list($value)) {
                    return ['found' => false, 'value' => null];
                }

                if ($segment['kind'] === 'arrayAppend') {
                    return ['found' => false, 'value' => null];
                }

                $indexValue = $segment['value'];
                if (!is_int($indexValue)) {
                    return ['found' => false, 'value' => null];
                }

                $index = $segment['kind'] === 'indexFromEnd'
                    ? count($value) - $indexValue
                    : $indexValue;
                if ($index < 0 || !array_key_exists($index, $value)) {
                    return ['found' => false, 'value' => null];
                }

                $value = $value[$index];
                continue;
            }

            $member = $segment['value'];
            if (
                !is_string($member)
                || !is_array($value)
                || array_is_list($value)
                || !array_key_exists($member, $value)
            ) {
                return ['found' => false, 'value' => null];
            }
            $value = $value[$member];
        }

        return ['found' => true, 'value' => $value];
    }

    private static function decodeSQLiteJsonInput(string $json, ?int $serialType): mixed
    {
        if ($serialType !== null && $serialType >= 12 && $serialType % 2 === 0 && SQLiteJsonB::isJsonB($json)) {
            return SQLiteJsonB::decode($json);
        }

        try {
            return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            try {
                return SQLiteJson5Parser::decode($json);
            } catch (\InvalidArgumentException $json5Exception) {
                throw new \InvalidArgumentException('SQLite json_extract expression index value is not valid strict JSON, supported JSON5, or JSONB', 0, $json5Exception);
            }
        }
    }

    private static function sqliteJsonScalar(mixed $value): mixed
    {
        if ($value === null || is_int($value) || is_float($value) || is_string($value)) {
            return $value;
        }
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if (is_array($value)) {
            $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (!is_string($json)) {
                throw new \InvalidArgumentException('SQLite json_extract lookup value cannot be encoded as JSON');
            }

            return $json;
        }

        throw new \InvalidArgumentException('SQLite json_extract lookup value must be null, scalar, or JSON-encodable array');
    }

    private static function sqliteJsonTextValue(mixed $value): string
    {
        if (is_resource($value) || is_object($value)) {
            throw new \InvalidArgumentException('SQLite JSON -> lookup value must be null, scalar, or JSON-encodable array');
        }

        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            throw new \InvalidArgumentException('SQLite JSON -> lookup value cannot be encoded as JSON');
        }

        return $json;
    }

    /**
     * @param list<mixed> $values
     * @return list<string>
     */
    private static function sqliteJsonTextValueList(array $values): array
    {
        $texts = [];
        foreach ($values as $value) {
            $texts[] = self::sqliteJsonTextValue($value);
        }

        return $texts;
    }

    /**
     * @param list<mixed> $values
     * @return list<mixed>
     */
    private static function sqliteJsonScalarList(array $values): array
    {
        $scalars = [];
        foreach ($values as $value) {
            $scalars[] = self::sqliteJsonScalar($value);
        }

        return $scalars;
    }

    /**
     * @return list<array{kind:string,value:int|string|null}>
     */
    private static function parseSimpleJsonPath(string $path): array
    {
        $length = strlen($path);
        if ($length === 0 || $path[0] !== '$') {
            throw new \InvalidArgumentException('SQLite json_extract expression indexes in this slice require paths that start with $');
        }
        if ($path === '$') {
            return [];
        }

        $segments = [];
        $offset = 1;
        while ($offset < $length) {
            if ($path[$offset] === '[') {
                $close = strpos($path, ']', $offset + 1);
                if ($close === false) {
                    throw new \InvalidArgumentException('SQLite json_extract expression index array path is unterminated');
                }

                $indexText = substr($path, $offset + 1, $close - $offset - 1);
                if (preg_match('/^\d+$/', $indexText) === 1) {
                    $maxIndexText = (string) PHP_INT_MAX;
                    if (
                        strlen($indexText) > strlen($maxIndexText)
                        || (strlen($indexText) === strlen($maxIndexText) && strcmp($indexText, $maxIndexText) > 0)
                    ) {
                        throw new \InvalidArgumentException('SQLite json_extract expression index array index is too large for this slice');
                    }

                    $segments[] = [
                        'kind' => 'index',
                        'value' => (int) $indexText,
                    ];
                    $offset = $close + 1;
                    continue;
                }

                if ($indexText === '#') {
                    $segments[] = [
                        'kind' => 'arrayAppend',
                        'value' => null,
                    ];
                    $offset = $close + 1;
                    continue;
                }

                if (preg_match('/^#-(\d+)$/', $indexText, $matches) === 1) {
                    $digits = ltrim($matches[1], '0');
                    $digits = $digits === '' ? '0' : $digits;
                    $maxIndexText = (string) PHP_INT_MAX;
                    $value = (
                        strlen($digits) > strlen($maxIndexText)
                        || (strlen($digits) === strlen($maxIndexText) && strcmp($digits, $maxIndexText) > 0)
                    )
                        ? $digits
                        : (int) $digits;

                    $segments[] = [
                        'kind' => 'indexFromEnd',
                        'value' => $value,
                    ];
                    $offset = $close + 1;
                    continue;
                }

                throw new \InvalidArgumentException('SQLite json_extract expression indexes in this slice support only non-negative array indexes, [#], or [#-N] reverse array indexes');
            }
            if ($path[$offset] !== '.') {
                throw new \InvalidArgumentException('SQLite json_extract expression indexes in this slice support only object-member and array-index paths');
            }
            $offset++;
            if ($offset >= $length) {
                throw new \InvalidArgumentException('SQLite json_extract expression index path has an empty object member');
            }

            if ($path[$offset] === '"') {
                $end = self::jsonPathQuotedMemberEnd($path, $offset);
                $literal = substr($path, $offset, $end - $offset + 1);
                try {
                    $member = SQLiteJson5Parser::decode($literal);
                } catch (\InvalidArgumentException $exception) {
                    throw new \InvalidArgumentException('SQLite json_extract expression index quoted path member is invalid', 0, $exception);
                }
                if (!is_string($member)) {
                    throw new \InvalidArgumentException('SQLite json_extract expression index quoted path member must decode to text');
                }
                $segments[] = [
                    'kind' => 'member',
                    'value' => $member,
                ];
                $offset = $end + 1;
                continue;
            }

            $end = $offset;
            while ($end < $length && $path[$end] !== '.' && $path[$end] !== '[') {
                $end++;
            }
            if ($end === $offset) {
                throw new \InvalidArgumentException('SQLite json_extract expression index path has an empty object member');
            }
            $member = SQLiteJsonPath::decodeBareMember(substr($path, $offset, $end - $offset));
            if ($member === null) {
                throw new \InvalidArgumentException('SQLite json_extract expression index path member escape is invalid');
            }
            $segments[] = [
                'kind' => 'member',
                'value' => $member,
            ];
            $offset = $end;
        }

        return $segments;
    }

    private static function jsonPathQuotedMemberEnd(string $path, int $offset): int
    {
        $length = strlen($path);
        for ($i = $offset + 1; $i < $length; $i++) {
            if ($path[$i] === '\\') {
                $i++;
                continue;
            }
            if ($path[$i] === '"') {
                return $i;
            }
        }

        throw new \InvalidArgumentException('SQLite json_extract expression index quoted path member is unterminated');
    }

    private static function sqliteScalarRank(mixed $value): int
    {
        if ($value === null) {
            return 0;
        }
        if (is_int($value) || is_float($value)) {
            return 1;
        }
        if (is_string($value)) {
            return 2;
        }

        throw new \InvalidArgumentException('Unsupported SQLite scalar comparison value');
    }

    private function readOverflowPayload(int $firstOverflowPage, int $byteCount): string
    {
        if ($byteCount < 0) {
            throw new \InvalidArgumentException('SQLite overflow byte count cannot be negative');
        }
        if ($byteCount === 0) {
            return '';
        }

        $usableSize = $this->usablePageSize();
        $overflowPagePayloadSize = $usableSize - 4;
        if ($overflowPagePayloadSize <= 0) {
            throw new \InvalidArgumentException('SQLite overflow page payload size is invalid');
        }

        $payload = '';
        $remaining = $byteCount;
        $pageNumber = $firstOverflowPage;
        $visited = [];
        while ($remaining > 0) {
            if ($pageNumber < 2) {
                throw new \InvalidArgumentException('SQLite overflow chain ended before payload was complete');
            }
            if (isset($visited[$pageNumber])) {
                throw new \InvalidArgumentException("SQLite overflow chain loops at page {$pageNumber}");
            }
            if ($pageNumber > $this->pageCount()) {
                throw new \InvalidArgumentException("SQLite overflow page {$pageNumber} is not present in the database image");
            }
            $visited[$pageNumber] = true;

            $page = $this->page($pageNumber);
            $nextPage = self::readUInt32($page, 0);
            $chunkLength = min($remaining, $overflowPagePayloadSize);
            $payload .= substr($page, 4, $chunkLength);
            $remaining -= $chunkLength;
            $pageNumber = $nextPage;
        }

        return $payload;
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        if ($offset < 0 || $offset + 4 > strlen($bytes)) {
            throw new \InvalidArgumentException('SQLite uint32 field is truncated');
        }

        return unpack('N', substr($bytes, $offset, 4))[1];
    }
}
