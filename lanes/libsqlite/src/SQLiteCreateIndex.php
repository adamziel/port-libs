<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCreateIndex
{
    public static function firstColumn(string $sql): ?SQLiteIndexColumn
    {
        $columns = self::parseColumns($sql, 1);

        return $columns[0] ?? null;
    }

    /**
     * @return null|list<SQLiteIndexColumn>
     */
    public static function columns(string $sql): ?array
    {
        return self::parseColumns($sql, null);
    }

    public static function tableName(string $sql): ?string
    {
        $onOffset = self::findTopLevelKeyword($sql, 'ON');
        if ($onOffset === null) {
            return null;
        }

        $table = self::readPossiblyQualifiedIdentifier($sql, $onOffset + 2);
        if ($table === null) {
            return null;
        }

        $offset = self::skipWhitespace($sql, $table[1]);
        if (!isset($sql[$offset]) || $sql[$offset] !== '(') {
            return null;
        }

        return $table[0];
    }

    /**
     * @return list<SQLiteIndexColumn>
     */
    public static function columnsAfterFirstExpression(string $sql): array
    {
        $index = self::indexedTermsAndTail($sql);
        if ($index === null || count($index['terms']) < 2) {
            return [];
        }
        if (self::parseIndexedColumn($index['terms'][0]) !== null) {
            return [];
        }

        $whereOffset = self::findTopLevelKeyword($index['tail'], 'WHERE');
        $partial = $whereOffset !== null;
        $partialPredicate = $whereOffset === null
            ? null
            : self::parsePartialPredicate(substr($index['tail'], $whereOffset + strlen('WHERE')));

        $columns = [];
        foreach (array_slice($index['terms'], 1) as $term) {
            $column = self::parseIndexedColumn($term);
            if ($column === null) {
                break;
            }

            $columns[] = new SQLiteIndexColumn(
                $column['name'],
                $column['collation'],
                $column['descending'],
                $partial,
                $partialPredicate,
            );
        }

        return $columns;
    }

    public static function firstLowerExpression(string $sql): ?SQLiteIndexColumn
    {
        $index = self::indexedTermsAndTail($sql);
        if ($index === null) {
            return null;
        }

        $whereOffset = self::findTopLevelKeyword($index['tail'], 'WHERE');
        $partial = $whereOffset !== null;
        $partialPredicate = $whereOffset === null
            ? null
            : self::parsePartialPredicate(substr($index['tail'], $whereOffset + strlen('WHERE')));

        $term = $index['terms'][0] ?? null;
        if ($term === null) {
            return null;
        }

        $column = self::parseLowerExpressionColumn($term);
        if ($column === null) {
            return null;
        }

        return new SQLiteIndexColumn(
            $column['name'],
            $column['collation'],
            $column['descending'],
            $partial,
            $partialPredicate,
        );
    }

    public static function firstUpperExpression(string $sql): ?SQLiteIndexColumn
    {
        $index = self::indexedTermsAndTail($sql);
        if ($index === null) {
            return null;
        }

        $whereOffset = self::findTopLevelKeyword($index['tail'], 'WHERE');
        $partial = $whereOffset !== null;
        $partialPredicate = $whereOffset === null
            ? null
            : self::parsePartialPredicate(substr($index['tail'], $whereOffset + strlen('WHERE')));

        $term = $index['terms'][0] ?? null;
        if ($term === null) {
            return null;
        }

        $column = self::parseUpperExpressionColumn($term);
        if ($column === null) {
            return null;
        }

        return new SQLiteIndexColumn(
            $column['name'],
            $column['collation'],
            $column['descending'],
            $partial,
            $partialPredicate,
        );
    }

    public static function firstTrimExpression(string $sql): ?SQLiteTrimIndexExpression
    {
        $index = self::indexedTermsAndTail($sql);
        if ($index === null) {
            return null;
        }

        $whereOffset = self::findTopLevelKeyword($index['tail'], 'WHERE');
        $partial = $whereOffset !== null;
        $partialPredicate = $whereOffset === null
            ? null
            : self::parsePartialPredicate(substr($index['tail'], $whereOffset + strlen('WHERE')));

        $term = $index['terms'][0] ?? null;
        if ($term === null) {
            return null;
        }

        $expression = self::parseTrimExpressionColumn($term);
        if ($expression === null) {
            return null;
        }

        return new SQLiteTrimIndexExpression(
            $expression['functionName'],
            $expression['name'],
            $expression['characters'],
            $expression['collation'],
            $expression['descending'],
            $partial,
            $partialPredicate,
        );
    }

    public static function firstLengthExpression(string $sql): ?SQLiteIndexColumn
    {
        $index = self::indexedTermsAndTail($sql);
        if ($index === null) {
            return null;
        }

        $whereOffset = self::findTopLevelKeyword($index['tail'], 'WHERE');
        $partial = $whereOffset !== null;
        $partialPredicate = $whereOffset === null
            ? null
            : self::parsePartialPredicate(substr($index['tail'], $whereOffset + strlen('WHERE')));

        $term = $index['terms'][0] ?? null;
        if ($term === null) {
            return null;
        }

        $column = self::parseLengthExpressionColumn($term);
        if ($column === null) {
            return null;
        }

        return new SQLiteIndexColumn(
            $column['name'],
            $column['collation'],
            $column['descending'],
            $partial,
            $partialPredicate,
        );
    }

    public static function firstIntegerCastExpression(string $sql): ?SQLiteIndexColumn
    {
        $index = self::indexedTermsAndTail($sql);
        if ($index === null) {
            return null;
        }

        $whereOffset = self::findTopLevelKeyword($index['tail'], 'WHERE');
        $partial = $whereOffset !== null;
        $partialPredicate = $whereOffset === null
            ? null
            : self::parsePartialPredicate(substr($index['tail'], $whereOffset + strlen('WHERE')));

        $term = $index['terms'][0] ?? null;
        if ($term === null) {
            return null;
        }

        $column = self::parseIntegerCastExpressionColumn($term);
        if ($column === null) {
            return null;
        }

        return new SQLiteIndexColumn(
            $column['name'],
            $column['collation'],
            $column['descending'],
            $partial,
            $partialPredicate,
        );
    }

    public static function firstJsonExtractExpression(string $sql): ?SQLiteJsonExtractIndexExpression
    {
        $index = self::indexedTermsAndTail($sql);
        if ($index === null) {
            return null;
        }

        $whereOffset = self::findTopLevelKeyword($index['tail'], 'WHERE');
        $partial = $whereOffset !== null;
        $partialPredicate = $whereOffset === null
            ? null
            : self::parsePartialPredicate(substr($index['tail'], $whereOffset + strlen('WHERE')));

        $term = $index['terms'][0] ?? null;
        if ($term === null) {
            return null;
        }

        $expression = self::parseJsonExtractExpressionColumn($term);
        if ($expression === null) {
            return null;
        }

        return new SQLiteJsonExtractIndexExpression(
            $expression['name'],
            $expression['path'],
            $expression['collation'],
            $expression['descending'],
            $partial,
            $partialPredicate,
            $expression['functionName'],
        );
    }

    public static function firstJsonTextOperatorExpression(string $sql): ?SQLiteJsonExtractIndexExpression
    {
        $index = self::indexedTermsAndTail($sql);
        if ($index === null) {
            return null;
        }

        $whereOffset = self::findTopLevelKeyword($index['tail'], 'WHERE');
        $partial = $whereOffset !== null;
        $partialPredicate = $whereOffset === null
            ? null
            : self::parsePartialPredicate(substr($index['tail'], $whereOffset + strlen('WHERE')));

        $term = $index['terms'][0] ?? null;
        if ($term === null) {
            return null;
        }

        $expression = self::parseJsonTextOperatorExpressionColumn($term);
        if ($expression === null) {
            return null;
        }

        return new SQLiteJsonExtractIndexExpression(
            $expression['name'],
            $expression['path'],
            $expression['collation'],
            $expression['descending'],
            $partial,
            $partialPredicate,
            '->>',
        );
    }

    public static function firstJsonValueOperatorExpression(string $sql): ?SQLiteJsonExtractIndexExpression
    {
        $index = self::indexedTermsAndTail($sql);
        if ($index === null) {
            return null;
        }

        $whereOffset = self::findTopLevelKeyword($index['tail'], 'WHERE');
        $partial = $whereOffset !== null;
        $partialPredicate = $whereOffset === null
            ? null
            : self::parsePartialPredicate(substr($index['tail'], $whereOffset + strlen('WHERE')));

        $term = $index['terms'][0] ?? null;
        if ($term === null) {
            return null;
        }

        $expression = self::parseJsonValueOperatorExpressionColumn($term);
        if ($expression === null) {
            return null;
        }

        return new SQLiteJsonExtractIndexExpression(
            $expression['name'],
            $expression['path'],
            $expression['collation'],
            $expression['descending'],
            $partial,
            $partialPredicate,
            '->',
        );
    }

    public static function firstSubstringExpression(string $sql): ?SQLiteSubstringIndexExpression
    {
        $index = self::indexedTermsAndTail($sql);
        if ($index === null) {
            return null;
        }

        $whereOffset = self::findTopLevelKeyword($index['tail'], 'WHERE');
        $partial = $whereOffset !== null;
        $partialPredicate = $whereOffset === null
            ? null
            : self::parsePartialPredicate(substr($index['tail'], $whereOffset + strlen('WHERE')));

        $term = $index['terms'][0] ?? null;
        if ($term === null) {
            return null;
        }

        $expression = self::parseSubstringExpressionColumn($term);
        if ($expression === null) {
            return null;
        }

        return new SQLiteSubstringIndexExpression(
            $expression['name'],
            $expression['start'],
            $expression['length'],
            $expression['collation'],
            $expression['descending'],
            $partial,
            $partialPredicate,
        );
    }

    /**
     * @return null|list<SQLiteIndexColumn>
     */
    private static function parseColumns(string $sql, ?int $limit): ?array
    {
        $index = self::indexedTermsAndTail($sql);
        if ($index === null) {
            return null;
        }

        $whereOffset = self::findTopLevelKeyword($index['tail'], 'WHERE');
        $partial = $whereOffset !== null;
        $partialPredicate = $whereOffset === null
            ? null
            : self::parsePartialPredicate(substr($index['tail'], $whereOffset + strlen('WHERE')));

        $columns = [];
        foreach ($index['terms'] as $term) {
            if ($limit !== null && count($columns) >= $limit) {
                break;
            }
            $column = self::parseIndexedColumn($term);
            if ($column === null) {
                return null;
            }

            $columns[] = new SQLiteIndexColumn(
                $column['name'],
                $column['collation'],
                $column['descending'],
                $partial,
                $partialPredicate,
            );
        }

        return $columns === [] ? null : $columns;
    }

    /**
     * @return null|array{terms:list<string>,tail:string}
     */
    private static function indexedTermsAndTail(string $sql): ?array
    {
        $onOffset = self::findTopLevelKeyword($sql, 'ON');
        if ($onOffset === null) {
            return null;
        }

        $offset = $onOffset + 2;
        $table = self::readIdentifier($sql, $offset);
        if ($table === null) {
            return null;
        }
        $offset = self::skipWhitespace($sql, $table[1]);
        if (isset($sql[$offset]) && $sql[$offset] === '.') {
            $schemaTable = self::readIdentifier($sql, $offset + 1);
            if ($schemaTable === null) {
                return null;
            }
            $offset = self::skipWhitespace($sql, $schemaTable[1]);
        }
        if (!isset($sql[$offset]) || $sql[$offset] !== '(') {
            return null;
        }

        $close = self::matchingParen($sql, $offset);
        if ($close === null) {
            return null;
        }

        return [
            'terms' => self::topLevelTerms(substr($sql, $offset + 1, $close - $offset - 1)),
            'tail' => substr($sql, $close + 1),
        ];
    }

    /**
     * @return null|array{name:string,collation:string,descending:bool}
     */
    private static function parseIndexedColumn(string $term): ?array
    {
        $term = trim($term);
        $identifier = self::readPossiblyQualifiedIdentifier($term, 0);
        if ($identifier === null) {
            return null;
        }

        $offset = self::skipWhitespace($term, $identifier[1]);
        if (isset($term[$offset]) && $term[$offset] === '(') {
            return null;
        }

        $modifiers = self::parseIndexTermModifiers($term, $offset);
        if ($modifiers === null) {
            return null;
        }

        return [
            'name' => $identifier[0],
            'collation' => $modifiers['collation'],
            'descending' => $modifiers['descending'],
        ];
    }

    /**
     * @return null|array{name:string,collation:string,descending:bool}
     */
    private static function parseLowerExpressionColumn(string $term): ?array
    {
        return self::parseUnaryColumnFunctionExpression($term, 'lower');
    }

    /**
     * @return null|array{name:string,collation:string,descending:bool}
     */
    private static function parseUpperExpressionColumn(string $term): ?array
    {
        return self::parseUnaryColumnFunctionExpression($term, 'upper');
    }

    /**
     * @return null|array{functionName:string,name:string,characters:?string,collation:string,descending:bool}
     */
    private static function parseTrimExpressionColumn(string $term): ?array
    {
        $term = self::normalizeExpressionIndexTerm($term);
        $function = self::readIdentifier($term, 0);
        if (
            $function === null
            || !in_array(strtolower($function[0]), ['trim', 'ltrim', 'rtrim'], true)
        ) {
            return null;
        }

        $offset = self::skipWhitespace($term, $function[1]);
        if (!isset($term[$offset]) || $term[$offset] !== '(') {
            return null;
        }

        $close = self::matchingParen($term, $offset);
        if ($close === null) {
            return null;
        }

        $arguments = self::topLevelTerms(substr($term, $offset + 1, $close - $offset - 1));
        if (count($arguments) < 1 || count($arguments) > 2) {
            return null;
        }

        $columnArgument = trim($arguments[0]);
        if ($columnArgument === '' || $columnArgument[0] === "'") {
            return null;
        }

        $column = self::readPossiblyQualifiedIdentifier($columnArgument, 0);
        if ($column === null || trim(substr($columnArgument, $column[1])) !== '') {
            return null;
        }

        $characters = null;
        if (count($arguments) === 2) {
            $literal = self::readLiteral($arguments[1], 0);
            if ($literal === null || trim(substr($arguments[1], $literal[1])) !== '' || !is_string($literal[0])) {
                return null;
            }
            $characters = $literal[0];
        }

        $modifiers = self::parseIndexTermModifiers($term, $close + 1);
        if ($modifiers === null) {
            return null;
        }

        return [
            'functionName' => strtolower($function[0]),
            'name' => $column[0],
            'characters' => $characters,
            'collation' => $modifiers['collation'],
            'descending' => $modifiers['descending'],
        ];
    }

    /**
     * @return null|array{name:string,collation:string,descending:bool}
     */
    private static function parseUnaryColumnFunctionExpression(string $term, string $functionName): ?array
    {
        $term = self::normalizeExpressionIndexTerm($term);
        $function = self::readIdentifier($term, 0);
        if ($function === null || strcasecmp($function[0], $functionName) !== 0) {
            return null;
        }

        $offset = self::skipWhitespace($term, $function[1]);
        if (!isset($term[$offset]) || $term[$offset] !== '(') {
            return null;
        }

        $close = self::matchingParen($term, $offset);
        if ($close === null) {
            return null;
        }

        $argument = trim(substr($term, $offset + 1, $close - $offset - 1));
        if ($argument === '' || $argument[0] === "'") {
            return null;
        }

        $column = self::readPossiblyQualifiedIdentifier($argument, 0);
        if ($column === null || trim(substr($argument, $column[1])) !== '') {
            return null;
        }

        $modifiers = self::parseIndexTermModifiers($term, $close + 1);
        if ($modifiers === null) {
            return null;
        }

        return [
            'name' => $column[0],
            'collation' => $modifiers['collation'],
            'descending' => $modifiers['descending'],
        ];
    }

    /**
     * @return null|array{name:string,start:int,length:?int,collation:string,descending:bool}
     */
    private static function parseSubstringExpressionColumn(string $term): ?array
    {
        $term = self::normalizeExpressionIndexTerm($term);
        $function = self::readIdentifier($term, 0);
        if (
            $function === null
            || (
                strcasecmp($function[0], 'substr') !== 0
                && strcasecmp($function[0], 'substring') !== 0
            )
        ) {
            return null;
        }

        $offset = self::skipWhitespace($term, $function[1]);
        if (!isset($term[$offset]) || $term[$offset] !== '(') {
            return null;
        }

        $close = self::matchingParen($term, $offset);
        if ($close === null) {
            return null;
        }

        $arguments = self::topLevelTerms(substr($term, $offset + 1, $close - $offset - 1));
        if (count($arguments) < 2 || count($arguments) > 3) {
            return null;
        }

        $columnArgument = trim($arguments[0]);
        $column = self::readPossiblyQualifiedIdentifier($columnArgument, 0);
        if ($column === null || trim(substr($columnArgument, $column[1])) !== '') {
            return null;
        }

        $start = self::readIntegerOnlyLiteral($arguments[1]);
        $length = count($arguments) === 3 ? self::readIntegerOnlyLiteral($arguments[2]) : null;
        if ($start === null || $start === 0 || (count($arguments) === 3 && ($length === null || $length < 0))) {
            return null;
        }

        $modifiers = self::parseIndexTermModifiers($term, $close + 1);
        if ($modifiers === null) {
            return null;
        }

        return [
            'name' => $column[0],
            'start' => $start,
            'length' => $length,
            'collation' => $modifiers['collation'],
            'descending' => $modifiers['descending'],
        ];
    }

    /**
     * @return null|array{name:string,collation:string,descending:bool}
     */
    private static function parseLengthExpressionColumn(string $term): ?array
    {
        $term = self::normalizeExpressionIndexTerm($term);
        $function = self::readIdentifier($term, 0);
        if ($function === null || strcasecmp($function[0], 'length') !== 0) {
            return null;
        }

        $offset = self::skipWhitespace($term, $function[1]);
        if (!isset($term[$offset]) || $term[$offset] !== '(') {
            return null;
        }

        $close = self::matchingParen($term, $offset);
        if ($close === null) {
            return null;
        }

        $argument = trim(substr($term, $offset + 1, $close - $offset - 1));
        if ($argument === '' || $argument[0] === "'") {
            return null;
        }

        $column = self::readPossiblyQualifiedIdentifier($argument, 0);
        if ($column === null || trim(substr($argument, $column[1])) !== '') {
            return null;
        }

        $modifiers = self::parseIndexTermModifiers($term, $close + 1);
        if ($modifiers === null) {
            return null;
        }

        return [
            'name' => $column[0],
            'collation' => $modifiers['collation'],
            'descending' => $modifiers['descending'],
        ];
    }

    /**
     * @return null|array{name:string,collation:string,descending:bool}
     */
    private static function parseIntegerCastExpressionColumn(string $term): ?array
    {
        $term = self::normalizeExpressionIndexTerm($term);
        $function = self::readIdentifier($term, 0);
        if ($function === null || strcasecmp($function[0], 'cast') !== 0) {
            return null;
        }

        $offset = self::skipWhitespace($term, $function[1]);
        if (!isset($term[$offset]) || $term[$offset] !== '(') {
            return null;
        }

        $close = self::matchingParen($term, $offset);
        if ($close === null) {
            return null;
        }

        $argument = trim(substr($term, $offset + 1, $close - $offset - 1));
        $column = self::readPossiblyQualifiedIdentifier($argument, 0);
        if ($column === null) {
            return null;
        }

        $as = self::readIdentifier($argument, $column[1]);
        if ($as === null || strcasecmp($as[0], 'as') !== 0) {
            return null;
        }

        $type = self::readIdentifier($argument, $as[1]);
        if ($type === null || strcasecmp($type[0], 'integer') !== 0) {
            return null;
        }
        if (trim(substr($argument, $type[1])) !== '') {
            return null;
        }

        $modifiers = self::parseIndexTermModifiers($term, $close + 1);
        if ($modifiers === null) {
            return null;
        }

        return [
            'name' => $column[0],
            'collation' => $modifiers['collation'],
            'descending' => $modifiers['descending'],
        ];
    }

    /**
     * @return null|array{name:string,path:string,collation:string,descending:bool,functionName:string}
     */
    private static function parseJsonExtractExpressionColumn(string $term): ?array
    {
        $term = self::normalizeExpressionIndexTerm($term);
        $function = self::readIdentifier($term, 0);
        if ($function === null || !in_array(strtolower($function[0]), ['json_extract', 'jsonb_extract'], true)) {
            return null;
        }

        $offset = self::skipWhitespace($term, $function[1]);
        if (!isset($term[$offset]) || $term[$offset] !== '(') {
            return null;
        }

        $close = self::matchingParen($term, $offset);
        if ($close === null) {
            return null;
        }

        $arguments = self::topLevelTerms(substr($term, $offset + 1, $close - $offset - 1));
        if (count($arguments) !== 2) {
            return null;
        }

        $columnArgument = trim($arguments[0]);
        if ($columnArgument === '' || $columnArgument[0] === "'") {
            return null;
        }

        $column = self::readPossiblyQualifiedIdentifier($columnArgument, 0);
        if ($column === null || trim(substr($columnArgument, $column[1])) !== '') {
            return null;
        }

        $path = self::readLiteral($arguments[1], 0);
        if ($path === null || trim(substr($arguments[1], $path[1])) !== '' || !is_string($path[0])) {
            return null;
        }
        if (!SQLiteJsonPath::isWellFormed($path[0])) {
            return null;
        }

        $modifiers = self::parseIndexTermModifiers($term, $close + 1);
        if ($modifiers === null) {
            return null;
        }

        return [
            'name' => $column[0],
            'path' => $path[0],
            'collation' => $modifiers['collation'],
            'descending' => $modifiers['descending'],
            'functionName' => strtolower($function[0]),
        ];
    }

    /**
     * @return null|array{name:string,path:string,collation:string,descending:bool}
     */
    private static function parseJsonTextOperatorExpressionColumn(string $term): ?array
    {
        $term = self::normalizeExpressionIndexTerm($term);
        $column = self::readPossiblyQualifiedIdentifier($term, 0);
        if ($column === null) {
            return null;
        }

        $offset = self::skipWhitespace($term, $column[1]);
        if (substr($term, $offset, 3) !== '->>') {
            return null;
        }

        $path = self::readLiteral($term, $offset + 3);
        if ($path === null) {
            return null;
        }

        $normalizedPath = self::normalizeJsonTextOperatorPath($path[0]);
        if ($normalizedPath === null) {
            return null;
        }

        $modifiers = self::parseIndexTermModifiers($term, $path[1]);
        if ($modifiers === null) {
            return null;
        }

        return [
            'name' => $column[0],
            'path' => $normalizedPath,
            'collation' => $modifiers['collation'],
            'descending' => $modifiers['descending'],
        ];
    }

    /**
     * @return null|array{name:string,path:string,collation:string,descending:bool}
     */
    private static function parseJsonValueOperatorExpressionColumn(string $term): ?array
    {
        $term = self::normalizeExpressionIndexTerm($term);
        $column = self::readPossiblyQualifiedIdentifier($term, 0);
        if ($column === null) {
            return null;
        }

        $offset = self::skipWhitespace($term, $column[1]);
        if (substr($term, $offset, 2) !== '->' || substr($term, $offset, 3) === '->>') {
            return null;
        }

        $path = self::readLiteral($term, $offset + 2);
        if ($path === null) {
            return null;
        }

        $normalizedPath = self::normalizeJsonTextOperatorPath($path[0]);
        if ($normalizedPath === null) {
            return null;
        }

        $modifiers = self::parseIndexTermModifiers($term, $path[1]);
        if ($modifiers === null) {
            return null;
        }

        return [
            'name' => $column[0],
            'path' => $normalizedPath,
            'collation' => $modifiers['collation'],
            'descending' => $modifiers['descending'],
        ];
    }

    private static function normalizeJsonTextOperatorPath(mixed $operand): ?string
    {
        if (!is_int($operand) && !is_string($operand)) {
            return null;
        }

        return SQLiteJsonPath::normalizeOperatorPath($operand);
    }

    private static function readIntegerOnlyLiteral(string $text): ?int
    {
        $literal = self::readLiteral($text, 0);
        if ($literal === null || trim(substr($text, $literal[1])) !== '' || !is_int($literal[0])) {
            return null;
        }

        return $literal[0];
    }

    /**
     * @return null|array{collation:string,descending:bool}
     */
    private static function parseIndexTermModifiers(string $term, int $offset): ?array
    {
        $collation = 'BINARY';
        $descending = false;
        $offset = self::skipWhitespace($term, $offset);
        while ($offset < strlen($term)) {
            $token = self::readIdentifier($term, $offset);
            if ($token === null) {
                return null;
            }
            $keyword = strtoupper($token[0]);
            $offset = self::skipWhitespace($term, $token[1]);

            if ($keyword === 'COLLATE') {
                $collationToken = self::readIdentifier($term, $offset);
                if ($collationToken === null) {
                    return null;
                }
                $collation = strtoupper($collationToken[0]);
                $offset = self::skipWhitespace($term, $collationToken[1]);
                continue;
            }
            if ($keyword === 'ASC') {
                $descending = false;
                continue;
            }
            if ($keyword === 'DESC') {
                $descending = true;
                continue;
            }

            return null;
        }

        return [
            'collation' => $collation,
            'descending' => $descending,
        ];
    }

    private static function normalizeExpressionIndexTerm(string $term): string
    {
        $term = trim($term);
        while (($term[0] ?? null) === '(') {
            $close = self::matchingParen($term, 0);
            if ($close === null) {
                return $term;
            }

            $inner = trim(substr($term, 1, $close - 1));
            $suffix = trim(substr($term, $close + 1));
            if ($suffix !== '') {
                $inner .= ' ' . $suffix;
            }
            if ($inner === $term) {
                return $term;
            }
            $term = $inner;
        }

        return $term;
    }

    /**
     * @return list<string>
     */
    private static function topLevelTerms(string $text): array
    {
        $terms = [];
        $start = 0;
        $depth = 0;
        $length = strlen($text);
        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];
            if ($char === "'" || $char === '"' || $char === '`') {
                $i = self::skipQuoted($text, $i, $char);
                continue;
            }
            if ($char === '[') {
                $i = self::skipBracketQuoted($text, $i);
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')' && $depth > 0) {
                $depth--;
                continue;
            }
            if ($char === ',' && $depth === 0) {
                $terms[] = substr($text, $start, $i - $start);
                $start = $i + 1;
            }
        }
        $terms[] = substr($text, $start);

        return $terms;
    }

    private static function parsePartialPredicate(string $where): ?SQLiteIndexPredicate
    {
        $where = trim(self::stripOuterParens($where));
        $orTerms = self::splitTopLevelKeyword($where, 'OR');
        if (count($orTerms) > 1) {
            $predicates = [];
            foreach ($orTerms as $term) {
                $predicate = self::parsePartialPredicate($term);
                if ($predicate === null) {
                    return null;
                }
                $predicates[] = $predicate;
            }

            return new SQLiteIndexPredicate('', SQLiteIndexPredicate::OR, $predicates);
        }

        $andTerms = self::splitTopLevelKeyword($where, 'AND');
        if (count($andTerms) > 1) {
            $predicates = [];
            foreach ($andTerms as $term) {
                $predicate = self::parseSinglePartialPredicate($term);
                if ($predicate === null) {
                    return null;
                }
                $predicates[] = $predicate;
            }

            return new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, $predicates);
        }

        return self::parseSinglePartialPredicate($where);
    }

    private static function parseSinglePartialPredicate(string $where): ?SQLiteIndexPredicate
    {
        $where = trim(self::stripOuterParens($where));
        $operand = self::readPartialPredicateOperand($where, 0);
        if ($operand === null) {
            return null;
        }

        $offset = self::skipWhitespace($where, $operand[1]);
        $is = self::readIdentifier($where, $offset);
        if ($is !== null && strcasecmp($is[0], 'IS') === 0) {
            $not = self::readIdentifier($where, $is[1]);
            if ($not === null || strcasecmp($not[0], 'NOT') !== 0) {
                return null;
            }

            $null = self::readIdentifier($where, $not[1]);
            if ($null === null || strcasecmp($null[0], 'NULL') !== 0) {
                return null;
            }

            if (trim(substr($where, $null[1])) !== '') {
                return null;
            }

            return new SQLiteIndexPredicate($operand[0], SQLiteIndexPredicate::IS_NOT_NULL);
        }

        $not = self::readIdentifier($where, $offset);
        if ($not !== null && strcasecmp($not[0], 'NOT') === 0) {
            $null = self::readIdentifier($where, $not[1]);
            if ($null === null || strcasecmp($null[0], 'NULL') !== 0) {
                return null;
            }

            if (trim(substr($where, $null[1])) !== '') {
                return null;
            }

            return new SQLiteIndexPredicate($operand[0], SQLiteIndexPredicate::IS_NOT_NULL);
        }

        $in = self::readIdentifier($where, $offset);
        if ($in !== null && strcasecmp($in[0], 'IN') === 0) {
            $listOffset = self::skipWhitespace($where, $in[1]);
            if (!isset($where[$listOffset]) || $where[$listOffset] !== '(') {
                return null;
            }

            $close = self::matchingParen($where, $listOffset);
            if ($close === null || trim(substr($where, $close + 1)) !== '') {
                return null;
            }

            $listBody = substr($where, $listOffset + 1, $close - $listOffset - 1);
            $values = [];
            foreach (self::topLevelTerms($listBody) as $term) {
                if (trim($term) === '') {
                    return null;
                }
                $literal = self::readLiteral($term, 0);
                if ($literal === null || trim(substr($term, $literal[1])) !== '') {
                    return null;
                }
                $values[] = $literal[0];
            }

            return new SQLiteIndexPredicate($operand[0], SQLiteIndexPredicate::IN_LIST, $values);
        }

        $between = self::readIdentifier($where, $offset);
        if ($between !== null && strcasecmp($between[0], 'BETWEEN') === 0) {
            $lower = self::readLiteral($where, $between[1]);
            if ($lower === null) {
                return null;
            }

            $and = self::readIdentifier($where, $lower[1]);
            if ($and === null || strcasecmp($and[0], 'AND') !== 0) {
                return null;
            }

            $upper = self::readLiteral($where, $and[1]);
            if ($upper === null || trim(substr($where, $upper[1])) !== '') {
                return null;
            }

            return new SQLiteIndexPredicate($operand[0], SQLiteIndexPredicate::BETWEEN, [
                'lower' => $lower[0],
                'upper' => $upper[0],
            ]);
        }

        $comparison = self::readComparisonOperator($where, $offset);
        if ($comparison === null) {
            return null;
        }
        $literal = self::readLiteral($where, $comparison[1]);
        if ($literal === null || trim(substr($where, $literal[1])) !== '') {
            return null;
        }

        return new SQLiteIndexPredicate($operand[0], $comparison[0], $literal[0]);
    }

    /**
     * @return null|array{0:string,1:int}
     */
    private static function readPartialPredicateOperand(string $where, int $offset): ?array
    {
        $expression = self::readPartialPredicateExpressionOperand($where, $offset);
        if ($expression !== null) {
            return $expression;
        }

        return self::readPossiblyQualifiedIdentifier($where, $offset);
    }

    /**
     * @return null|array{0:string,1:int}
     */
    private static function readPartialPredicateExpressionOperand(string $where, int $offset): ?array
    {
        $function = self::readIdentifier($where, $offset);
        if ($function === null) {
            return null;
        }

        $functionName = strtolower($function[0]);
        $open = self::skipWhitespace($where, $function[1]);
        if (!isset($where[$open]) || $where[$open] !== '(') {
            return null;
        }

        $close = self::matchingParen($where, $open);
        if ($close === null) {
            return null;
        }

        $body = trim(substr($where, $open + 1, $close - $open - 1));
        if (in_array($functionName, ['lower', 'upper', 'length'], true)) {
            $column = self::readPossiblyQualifiedIdentifier($body, 0);
            if ($column === null || trim(substr($body, $column[1])) !== '') {
                return null;
            }

            return [self::partialExpressionKey($functionName, $column[0]), $close + 1];
        }

        if ($functionName !== 'cast') {
            return null;
        }

        $column = self::readPossiblyQualifiedIdentifier($body, 0);
        if ($column === null) {
            return null;
        }
        $as = self::readIdentifier($body, $column[1]);
        if ($as === null || strcasecmp($as[0], 'as') !== 0) {
            return null;
        }
        $type = self::readIdentifier($body, $as[1]);
        if ($type === null || strcasecmp($type[0], 'integer') !== 0 || trim(substr($body, $type[1])) !== '') {
            return null;
        }

        return [self::partialExpressionKey('integer-cast', $column[0]), $close + 1];
    }

    private static function partialExpressionKey(string $type, string $column): string
    {
        return '__expr__:' . $type . ':' . strtolower($column);
    }

    /**
     * @return non-empty-list<string>
     */
    private static function splitTopLevelKeyword(string $text, string $keyword): array
    {
        $terms = [];
        $start = 0;
        $depth = 0;
        $length = strlen($text);
        $keywordLength = strlen($keyword);
        $skipNextAndForBetween = false;
        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];
            if ($char === "'" || $char === '"' || $char === '`') {
                $i = self::skipQuoted($text, $i, $char);
                continue;
            }
            if ($char === '[') {
                $i = self::skipBracketQuoted($text, $i);
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')' && $depth > 0) {
                $depth--;
                continue;
            }
            if ($depth === 0 && $keyword === 'AND' && self::isKeywordAt($text, $i, 'BETWEEN')) {
                $skipNextAndForBetween = true;
                $i += strlen('BETWEEN') - 1;
                continue;
            }
            if (
                $depth === 0
                && self::isKeywordAt($text, $i, $keyword)
            ) {
                if ($keyword === 'AND' && $skipNextAndForBetween) {
                    $skipNextAndForBetween = false;
                    $i += $keywordLength - 1;
                    continue;
                }
                $terms[] = substr($text, $start, $i - $start);
                $start = $i + $keywordLength;
                $i += $keywordLength - 1;
            }
        }
        $terms[] = substr($text, $start);

        return array_map(trim(...), $terms);
    }

    /**
     * @return null|array{0:string,1:int}
     */
    private static function readComparisonOperator(string $text, int $offset): ?array
    {
        $offset = self::skipWhitespace($text, $offset);
        if (substr($text, $offset, 2) === '==') {
            return [SQLiteIndexPredicate::EQUALS, $offset + 2];
        }
        if (substr($text, $offset, 2) === '<>') {
            return [SQLiteIndexPredicate::NOT_EQUALS, $offset + 2];
        }
        if (substr($text, $offset, 2) === '!=') {
            return [SQLiteIndexPredicate::NOT_EQUALS, $offset + 2];
        }
        if (substr($text, $offset, 2) === '<=') {
            return [SQLiteIndexPredicate::LESS_THAN_OR_EQUAL, $offset + 2];
        }
        if (substr($text, $offset, 2) === '>=') {
            return [SQLiteIndexPredicate::GREATER_THAN_OR_EQUAL, $offset + 2];
        }
        if (($text[$offset] ?? null) === '=') {
            return [SQLiteIndexPredicate::EQUALS, $offset + 1];
        }
        if (($text[$offset] ?? null) === '<') {
            return [SQLiteIndexPredicate::LESS_THAN, $offset + 1];
        }
        if (($text[$offset] ?? null) === '>') {
            return [SQLiteIndexPredicate::GREATER_THAN, $offset + 1];
        }

        return null;
    }

    /**
     * @return null|array{0:mixed,1:int}
     */
    private static function readLiteral(string $text, int $offset): ?array
    {
        $offset = self::skipWhitespace($text, $offset);
        if ($offset >= strlen($text)) {
            return null;
        }
        $parenthesized = self::readParenthesizedLiteral($text, $offset);
        if ($parenthesized !== null) {
            return $parenthesized;
        }
        $minMax = self::readMinMaxLiteral($text, $offset);
        if ($minMax !== null) {
            return $minMax;
        }
        $jsonQuote = self::readJsonQuoteLiteral($text, $offset);
        if ($jsonQuote !== null) {
            return $jsonQuote;
        }
        if ($text[$offset] === "'") {
            $end = self::skipQuoted($text, $offset, "'");
            if ($end <= $offset || $text[$end] !== "'") {
                return null;
            }

            return [str_replace("''", "'", substr($text, $offset + 1, $end - $offset - 1)), $end + 1];
        }
        if (preg_match('/[+-]?(?:\d+\.\d*|\.\d+)(?:[eE][+-]?\d+)?/A', substr($text, $offset), $matches)) {
            return [(float) $matches[0], $offset + strlen($matches[0])];
        }
        if (preg_match('/[+-]?\d+/A', substr($text, $offset), $matches)) {
            return [(int) $matches[0], $offset + strlen($matches[0])];
        }

        return null;
    }

    /**
     * @return null|array{0:mixed,1:int}
     */
    private static function readParenthesizedLiteral(string $text, int $offset): ?array
    {
        if (($text[$offset] ?? null) !== '(') {
            return null;
        }

        $close = self::matchingParen($text, $offset);
        if ($close === null) {
            return null;
        }

        $body = substr($text, $offset + 1, $close - $offset - 1);
        $literal = self::readLiteral($body, 0);
        if ($literal === null) {
            return null;
        }

        $tailOffset = self::skipCollateClause($body, $literal[1]);
        if (trim(substr($body, $tailOffset)) !== '') {
            return null;
        }

        return [$literal[0], $close + 1];
    }

    private static function skipCollateClause(string $text, int $offset): int
    {
        $offset = self::skipWhitespace($text, $offset);
        $collate = self::readIdentifier($text, $offset);
        if ($collate === null || strcasecmp($collate[0], 'collate') !== 0) {
            return $offset;
        }

        $name = self::readIdentifier($text, $collate[1]);
        if ($name === null) {
            return $offset;
        }

        return self::skipWhitespace($text, $name[1]);
    }

    /**
     * @return null|array{0:int|float|string,1:int}
     */
    private static function readMinMaxLiteral(string $text, int $offset): ?array
    {
        $function = self::readIdentifier($text, $offset);
        if ($function === null) {
            return null;
        }

        $functionName = strtolower($function[0]);
        if ($functionName !== 'min' && $functionName !== 'max') {
            return null;
        }

        $open = self::skipWhitespace($text, $function[1]);
        if (($text[$open] ?? null) !== '(') {
            return null;
        }

        $close = self::matchingParen($text, $open);
        if ($close === null) {
            return null;
        }

        $body = substr($text, $open + 1, $close - $open - 1);
        $terms = trim($body) === '' ? [] : self::topLevelTerms($body);
        if (count($terms) < 2) {
            return null;
        }

        $values = [];
        foreach ($terms as $term) {
            $literal = self::readMinMaxArgumentLiteral($term, 0);
            if ($literal === null || trim(substr($term, $literal[1])) !== '') {
                return null;
            }
            $values[] = $literal[0];
        }

        $first = $values[0];
        if (is_string($first)) {
            if (array_filter($values, static fn (mixed $value): bool => !is_string($value)) !== []) {
                return null;
            }
            sort($values, SORT_STRING);
        } elseif (is_int($first) || is_float($first)) {
            if (array_filter($values, static fn (mixed $value): bool => !is_int($value) && !is_float($value)) !== []) {
                return null;
            }
            sort($values, SORT_NUMERIC);
        } else {
            return null;
        }

        return [$functionName === 'min' ? $values[0] : $values[count($values) - 1], $close + 1];
    }

    /**
     * @return null|array{0:int|float|string,1:int}
     */
    private static function readMinMaxArgumentLiteral(string $text, int $offset): ?array
    {
        $offset = self::skipWhitespace($text, $offset);
        if ($offset >= strlen($text)) {
            return null;
        }
        if ($text[$offset] === "'") {
            $end = self::skipQuoted($text, $offset, "'");
            if ($end <= $offset || $text[$end] !== "'") {
                return null;
            }

            return [str_replace("''", "'", substr($text, $offset + 1, $end - $offset - 1)), $end + 1];
        }
        if (
            preg_match(
                '/[+-]?(?:(?:\d+\.\d*|\.\d+)(?:[eE][+-]?\d+)?|\d+[eE][+-]?\d+)/A',
                substr($text, $offset),
                $matches,
            ) === 1
        ) {
            return [(float) $matches[0], $offset + strlen($matches[0])];
        }
        if (preg_match('/[+-]?\d+/A', substr($text, $offset), $matches) === 1) {
            return [(int) $matches[0], $offset + strlen($matches[0])];
        }

        return null;
    }

    /**
     * @return null|array{0:string,1:int}
     */
    private static function readJsonQuoteLiteral(string $text, int $offset): ?array
    {
        $function = self::readIdentifier($text, $offset);
        if ($function === null || strcasecmp($function[0], 'json_quote') !== 0) {
            return null;
        }

        $open = self::skipWhitespace($text, $function[1]);
        if (($text[$open] ?? null) !== '(') {
            return null;
        }

        $close = self::matchingParen($text, $open);
        if ($close === null) {
            return null;
        }

        $body = substr($text, $open + 1, $close - $open - 1);
        $terms = trim($body) === '' ? [] : self::topLevelTerms($body);
        if (count($terms) !== 1) {
            return null;
        }

        $argument = self::readJsonQuoteArgumentLiteral($terms[0], 0);
        if ($argument === null || trim(substr($terms[0], $argument[1])) !== '') {
            return null;
        }

        $quoted = self::renderJsonQuoteLiteral($argument[0]);
        if ($quoted === null) {
            return null;
        }

        return [$quoted, $close + 1];
    }

    /**
     * @return null|array{0:mixed,1:int}
     */
    private static function readJsonQuoteArgumentLiteral(string $text, int $offset): ?array
    {
        $offset = self::skipWhitespace($text, $offset);
        if ($offset >= strlen($text)) {
            return null;
        }
        if (self::isKeywordAt($text, $offset, 'NULL')) {
            return [null, $offset + strlen('NULL')];
        }
        if ($text[$offset] === "'") {
            $end = self::skipQuoted($text, $offset, "'");
            if ($end <= $offset || $text[$end] !== "'") {
                return null;
            }

            return [str_replace("''", "'", substr($text, $offset + 1, $end - $offset - 1)), $end + 1];
        }
        if (
            preg_match(
                '/[+-]?(?:(?:\d+\.\d*|\.\d+)(?:[eE][+-]?\d+)?|\d+[eE][+-]?\d+)/A',
                substr($text, $offset),
                $matches,
            ) === 1
        ) {
            return [(float) $matches[0], $offset + strlen($matches[0])];
        }
        if (preg_match('/[+-]?\d+/A', substr($text, $offset), $matches) === 1) {
            return [(int) $matches[0], $offset + strlen($matches[0])];
        }

        return null;
    }

    private static function renderJsonQuoteLiteral(mixed $value): ?string
    {
        if ($value === null || is_int($value) || is_float($value)) {
            return SQLiteJsonQuote::jsonQuote($value);
        }

        return null;
    }

    private static function stripOuterParens(string $text): string
    {
        $text = trim($text);
        while ($text !== '' && $text[0] === '(') {
            $close = self::matchingParen($text, 0);
            if ($close !== strlen($text) - 1) {
                return $text;
            }
            $text = trim(substr($text, 1, -1));
        }

        return $text;
    }

    /**
     * @return null|array{0:string,1:int}
     */
    private static function readPossiblyQualifiedIdentifier(string $text, int $offset): ?array
    {
        $identifier = self::readIdentifier($text, $offset);
        if ($identifier === null) {
            return null;
        }

        $lastName = $identifier[0];
        $offset = self::skipWhitespace($text, $identifier[1]);
        while (isset($text[$offset]) && $text[$offset] === '.') {
            $next = self::readIdentifier($text, $offset + 1);
            if ($next === null) {
                return null;
            }
            $lastName = $next[0];
            $offset = self::skipWhitespace($text, $next[1]);
        }

        return [$lastName, $offset];
    }

    private static function findTopLevelKeyword(string $text, string $keyword): ?int
    {
        $depth = 0;
        $length = strlen($text);
        $keywordLength = strlen($keyword);
        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];
            if ($char === "'" || $char === '"' || $char === '`') {
                $i = self::skipQuoted($text, $i, $char);
                continue;
            }
            if ($char === '[') {
                $i = self::skipBracketQuoted($text, $i);
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')' && $depth > 0) {
                $depth--;
                continue;
            }
            if (
                $depth === 0
                && strncasecmp(substr($text, $i, $keywordLength), $keyword, $keywordLength) === 0
                && ($i === 0 || !self::isIdentifierChar($text[$i - 1]))
                && (!isset($text[$i + $keywordLength]) || !self::isIdentifierChar($text[$i + $keywordLength]))
            ) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @return null|array{0:string,1:int}
     */
    private static function readIdentifier(string $text, int $offset): ?array
    {
        $offset = self::skipWhitespace($text, $offset);
        if ($offset >= strlen($text)) {
            return null;
        }

        $quote = $text[$offset];
        if ($quote === '"' || $quote === '`' || $quote === "'") {
            $end = self::skipQuoted($text, $offset, $quote);

            return [str_replace($quote . $quote, $quote, substr($text, $offset + 1, $end - $offset - 1)), $end + 1];
        }
        if ($quote === '[') {
            $end = self::skipBracketQuoted($text, $offset);

            return [substr($text, $offset + 1, $end - $offset - 1), $end + 1];
        }
        if (!preg_match('/[A-Za-z_][A-Za-z0-9_$]*/A', substr($text, $offset), $matches)) {
            return null;
        }

        return [$matches[0], $offset + strlen($matches[0])];
    }

    private static function matchingParen(string $text, int $openOffset): ?int
    {
        $depth = 0;
        $length = strlen($text);
        for ($i = $openOffset; $i < $length; $i++) {
            $char = $text[$i];
            if ($char === "'" || $char === '"' || $char === '`') {
                $i = self::skipQuoted($text, $i, $char);
                continue;
            }
            if ($char === '[') {
                $i = self::skipBracketQuoted($text, $i);
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    private static function skipWhitespace(string $text, int $offset): int
    {
        while (isset($text[$offset]) && ctype_space($text[$offset])) {
            $offset++;
        }

        return $offset;
    }

    private static function skipQuoted(string $text, int $offset, string $quote): int
    {
        $length = strlen($text);
        for ($i = $offset + 1; $i < $length; $i++) {
            if ($text[$i] !== $quote) {
                continue;
            }
            if (isset($text[$i + 1]) && $text[$i + 1] === $quote) {
                $i++;
                continue;
            }

            return $i;
        }

        return $length - 1;
    }

    private static function skipBracketQuoted(string $text, int $offset): int
    {
        $end = strpos($text, ']', $offset + 1);

        return $end === false ? strlen($text) - 1 : $end;
    }

    private static function isIdentifierChar(string $char): bool
    {
        return ctype_alnum($char) || $char === '_' || $char === '$';
    }

    private static function isKeywordAt(string $text, int $offset, string $keyword): bool
    {
        $keywordLength = strlen($keyword);

        return strncasecmp(substr($text, $offset, $keywordLength), $keyword, $keywordLength) === 0
            && ($offset === 0 || !self::isIdentifierChar($text[$offset - 1]))
            && (!isset($text[$offset + $keywordLength]) || !self::isIdentifierChar($text[$offset + $keywordLength]));
    }
}
