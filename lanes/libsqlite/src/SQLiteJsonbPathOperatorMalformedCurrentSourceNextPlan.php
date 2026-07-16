<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonbPathOperatorMalformedCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function compare(
        array $currentRows,
        array $nextRows,
        string $path,
        string $operator = '->>',
        string $jsonColumn = 'key_value',
        string $rowidColumn = 'setting_id',
    ): array {
        if ($operator !== '->' && $operator !== '->>') {
            throw new \InvalidArgumentException('SQLite JSONB path operator current/next plan requires -> or ->>');
        }
        if (!SQLiteJsonPath::isWellFormed($path)) {
            throw new \InvalidArgumentException('SQLite JSONB path operator current/next path is malformed');
        }

        $current = self::evaluateRows($currentRows, $path, $operator, $jsonColumn, $rowidColumn);
        $next = self::evaluateRows($nextRows, $path, $operator, $jsonColumn, $rowidColumn);
        $currentSignature = self::signature($current['rows']);
        $nextSignature = self::signature($next['rows']);
        $malformedChanged = $current['malformedRowids'] !== $next['malformedRowids'];
        $valueChanged = $currentSignature !== $nextSignature;

        return [
            'operator' => $operator,
            'path' => $path,
            'jsonColumn' => $jsonColumn,
            'rowidColumn' => $rowidColumn,
            'current' => $current,
            'next' => $next,
            'currentRowCount' => count($currentRows),
            'nextRowCount' => count($nextRows),
            'currentValidRowCount' => count($current['validRowids']),
            'nextValidRowCount' => count($next['validRowids']),
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentMissingPathRowids' => $current['missingPathRowids'],
            'nextMissingPathRowids' => $next['missingPathRowids'],
            'currentSignature' => $currentSignature,
            'nextSignature' => $nextSignature,
            'valueChanged' => $valueChanged,
            'malformedChanged' => $malformedChanged,
            'reprepareRequired' => $malformedChanged || $valueChanged,
            'reprepareReason' => self::reprepareReason($malformedChanged, $valueChanged),
            'currentReaderPolicy' => 'keep-current-jsonb-operator-source-until-statement-reset',
            'nextReaderPolicy' => $next['malformedRowids'] === []
                ? 'next-jsonb-operator-source-is-runnable'
                : 'next-jsonb-operator-source-errors-before-row-yield',
            'statementWouldAbort' => $next['malformedRowids'] !== [],
            'dependencies' => [
                'SQLiteJsonB',
                'SQLiteJsonInspection',
                'SQLiteJsonPath',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:list<array<string,mixed>>,validRowids:list<int>,malformedRowids:list<int>,missingPathRowids:list<int>,diagnostics:array<int,array<string,mixed>>}
     */
    private static function evaluateRows(array $rows, string $path, string $operator, string $jsonColumn, string $rowidColumn): array
    {
        $evaluated = [];
        $valid = [];
        $malformed = [];
        $missing = [];
        $diagnostics = [];

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite JSONB path operator current/next row is malformed');
            }
            if (!array_key_exists($jsonColumn, $row)) {
                throw new \InvalidArgumentException("SQLite JSONB path operator row is missing {$jsonColumn}");
            }

            $rowid = self::rowid($row, $rowidColumn, $index);
            $value = $row[$jsonColumn];
            self::jsonSource($value);
            $diagnostic = [
                'rowid' => $rowid,
                'sourceKind' => self::sourceKind($value),
                'found' => false,
                'value' => null,
                'result' => null,
                'error' => null,
            ];

            try {
                $located = SQLiteJsonInspection::locatePath(self::jsonSource($value), $path);
                $diagnostic['found'] = $located['found'];
                $diagnostic['value'] = $located['found'] ? self::diagnosticValue($located['value']) : null;
                $diagnostic['result'] = $located['found'] ? self::operatorResult($located['value'], $operator) : null;
                if ($located['found']) {
                    $valid[] = $rowid;
                } else {
                    $missing[] = $rowid;
                }
            } catch (\InvalidArgumentException $exception) {
                $diagnostic['sourceKind'] = self::isMalformedJsonbSource($value)
                    ? 'malformed-jsonb'
                    : 'malformed-json';
                $diagnostic['error'] = $exception->getMessage();
                $malformed[] = $rowid;
            }

            $evaluated[] = $diagnostic;
            $diagnostics[$rowid] = $diagnostic;
        }

        return [
            'rows' => $evaluated,
            'validRowids' => $valid,
            'malformedRowids' => $malformed,
            'missingPathRowids' => $missing,
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowid(array $row, string $rowidColumn, int $index): int
    {
        $rowid = $row[$rowidColumn] ?? ($index + 1);
        if (!is_int($rowid)) {
            throw new \InvalidArgumentException('SQLite JSONB path operator rowid must be an integer');
        }

        return $rowid;
    }

    private static function jsonSource(mixed $value): string|SQLiteBlobValue|SQLiteJsonSubtypeValue|null
    {
        if ($value === null || is_string($value) || $value instanceof SQLiteBlobValue || $value instanceof SQLiteJsonSubtypeValue) {
            return $value;
        }

        throw new \InvalidArgumentException('SQLite JSONB path operator source must be text, JSONB, JSON subtype, or NULL');
    }

    private static function sourceKind(mixed $value): string
    {
        if ($value === null) {
            return 'sql-null';
        }
        if ($value instanceof SQLiteJsonSubtypeValue) {
            return 'json-subtype';
        }
        if ($value instanceof SQLiteBlobValue) {
            if (SQLiteJsonB::isStrictlyWellFormed($value->bytes)) {
                return 'jsonb';
            }
            if (SQLiteJsonB::isSuperficiallyJsonB($value->bytes)) {
                return 'jsonb-superficial';
            }

            return 'blob-text';
        }

        return 'json-text';
    }

    private static function isMalformedJsonbSource(mixed $value): bool
    {
        if (!$value instanceof SQLiteBlobValue) {
            return false;
        }
        if (SQLiteJsonB::isStrictlyWellFormed($value->bytes)) {
            return false;
        }
        if (SQLiteJsonB::isSuperficiallyJsonB($value->bytes)) {
            return true;
        }

        try {
            json_decode($value->bytes, false, 1001, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);

            return false;
        } catch (\JsonException) {
            return true;
        }
    }

    private static function operatorResult(mixed $value, string $operator): mixed
    {
        if ($operator === '->>') {
            if ($value === true) {
                return 1;
            }
            if ($value === false) {
                return 0;
            }
            if ($value === null || is_int($value) || is_float($value) || is_string($value)) {
                return $value;
            }

            return SQLiteJsonCanonical::encodeDecodedJson($value);
        }

        return SQLiteJsonCanonical::encodeDecodedJson($value);
    }

    private static function diagnosticValue(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            return json_decode(SQLiteJsonCanonical::encodeDecodedJson($value), true, 512, JSON_THROW_ON_ERROR);
        }
        if (is_array($value)) {
            return json_decode(SQLiteJsonCanonical::encodeDecodedJson($value), true, 512, JSON_THROW_ON_ERROR);
        }

        return $value;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array{rowid:int,found:bool,sourceKind:string,result:mixed,error:?string}>
     */
    private static function signature(array $rows): array
    {
        return array_map(
            static fn (array $row): array => [
                'rowid' => $row['rowid'],
                'found' => $row['found'],
                'sourceKind' => $row['sourceKind'],
                'result' => $row['result'],
                'error' => $row['error'],
            ],
            $rows,
        );
    }

    private static function reprepareReason(bool $malformedChanged, bool $valueChanged): string
    {
        if ($malformedChanged) {
            return 'jsonb-operator-malformed-source-tape-changed';
        }
        if ($valueChanged) {
            return 'jsonb-operator-path-result-changed';
        }

        return 'stable-jsonb-operator-source';
    }
}
