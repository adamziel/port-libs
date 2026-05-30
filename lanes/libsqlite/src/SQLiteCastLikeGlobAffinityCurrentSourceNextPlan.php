<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCastLikeGlobAffinityCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowValuePlan(
        array $currentRows,
        array $nextRows,
        string $castTarget,
        string $pattern,
        string $operator = 'LIKE',
        ?string $escape = null,
        string $currentSource = 'main.app_settings@132',
        string $nextSource = 'main.app_settings@133',
        int $currentSchemaCookie = 132,
        int $nextSchemaCookie = 133,
    ): array {
        $operator = strtoupper($operator);
        if (!in_array($operator, ['LIKE', 'GLOB'], true)) {
            throw new \InvalidArgumentException('SQLite CAST LIKE/GLOB current-source operator must be LIKE or GLOB');
        }
        if ($operator === 'GLOB' && $escape !== null) {
            throw new \InvalidArgumentException('SQLite CAST GLOB current-source does not accept ESCAPE');
        }

        $range = self::prefixRange($operator, $pattern, $escape);
        $currentTrace = self::traceRows($currentRows, $castTarget, $operator, $pattern, $escape, $range);
        $nextTrace = self::traceRows($nextRows, $castTarget, $operator, $pattern, $escape, $range);
        $currentByRowid = self::byRowid($currentTrace);
        $nextByRowid = self::byRowid($nextTrace);

        $currentCandidates = self::rowidsWhere($currentTrace, 'candidate');
        $nextCandidates = self::rowidsWhere($nextTrace, 'candidate');
        $currentMatched = self::rowidsWhere($currentTrace, 'matched');
        $nextMatched = self::rowidsWhere($nextTrace, 'matched');
        $changedCast = [];
        $changedText = [];
        $changedBytes = [];
        $changedCandidate = [];
        $changedMatch = [];
        foreach (array_unique(array_merge(array_keys($currentByRowid), array_keys($nextByRowid))) as $rowid) {
            $current = $currentByRowid[$rowid] ?? null;
            $next = $nextByRowid[$rowid] ?? null;
            if ($current === null || $next === null || $current['castStorage'] !== $next['castStorage'] || $current['castValue'] !== $next['castValue']) {
                $changedCast[] = (int) $rowid;
            }
            if ($current === null || $next === null || $current['castText'] !== $next['castText']) {
                $changedText[] = (int) $rowid;
            }
            if ($current === null || $next === null || $current['castTextHex'] !== $next['castTextHex']) {
                $changedBytes[] = (int) $rowid;
            }
            if ($current === null || $next === null || $current['candidate'] !== $next['candidate']) {
                $changedCandidate[] = (int) $rowid;
            }
            if ($current === null || $next === null || $current['matched'] !== $next['matched']) {
                $changedMatch[] = (int) $rowid;
            }
        }
        sort($changedCast);
        sort($changedText);
        sort($changedBytes);
        sort($changedCandidate);
        sort($changedMatch);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($range === null) {
            $reasons[] = 'no-prefix-range';
        }
        if ($changedCast !== []) {
            $reasons[] = 'cast-result';
        }
        if ($changedText !== []) {
            $reasons[] = 'text-affinity';
        }
        if ($changedBytes !== []) {
            $reasons[] = 'encoded-bytes';
        }
        if ($changedCandidate !== []) {
            $reasons[] = 'candidate-rowset';
        }
        if ($changedMatch !== []) {
            $reasons[] = 'matched-rowset';
        }

        return [
            'operator' => $operator,
            'collation' => 'BINARY',
            'castTarget' => trim($castTarget),
            'pattern' => $pattern,
            'escape' => $escape,
            'range' => $range,
            'indexUsable' => $range !== null,
            'residualScan' => true,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentTrace' => $currentTrace,
            'nextTrace' => $nextTrace,
            'currentCandidateRowids' => $currentCandidates,
            'nextCandidateRowids' => $nextCandidates,
            'currentResidualRejectedRowids' => array_values(array_diff($currentCandidates, $currentMatched)),
            'nextResidualRejectedRowids' => array_values(array_diff($nextCandidates, $nextMatched)),
            'currentRowids' => $currentMatched,
            'nextRowids' => $nextMatched,
            'retainedRowids' => array_values(array_intersect($currentMatched, $nextMatched)),
            'enteredRowids' => array_values(array_diff($nextMatched, $currentMatched)),
            'exitedRowids' => array_values(array_diff($currentMatched, $nextMatched)),
            'changedCastRowids' => $changedCast,
            'changedTextRowids' => $changedText,
            'changedBytesRowids' => $changedBytes,
            'changedCandidateRowids' => $changedCandidate,
            'changedMatchRowids' => $changedMatch,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-select-cast-expression',
                'sqlite-binary-like-glob-prefix-range',
                'sqlite-like-glob-text-affinity-residual',
                'sqlite-current-source-next133',
            ],
        ];
    }

    /**
     * @return null|array{lowerInclusive:string,upperBound:?string}
     */
    private static function prefixRange(string $operator, string $pattern, ?string $escape): ?array
    {
        if ($operator === 'LIKE') {
            $plan = SQLiteDatabase::likePatternPlan($pattern, $escape);

            return $plan['prefix'] === '' ? null : $plan['binaryRange'];
        }

        return SQLiteDatabase::globPrefixRangeBounds($pattern);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param null|array{lowerInclusive:string,upperBound:?string} $range
     * @return list<array<string,mixed>>
     */
    private static function traceRows(array $rows, string $castTarget, string $operator, string $pattern, ?string $escape, ?array $range): array
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite CAST LIKE/GLOB rows must be arrays');
            }
            foreach (['setting_id', 'key_value'] as $column) {
                if (!array_key_exists($column, $row)) {
                    throw new \InvalidArgumentException("SQLite CAST LIKE/GLOB row is missing {$column}");
                }
            }
            if (!is_int($row['setting_id'])) {
                throw new \InvalidArgumentException('SQLite CAST LIKE/GLOB setting_id must be an integer');
            }
        }

        $castTarget = self::castTargetSql($castTarget);
        $castRows = SQLiteSelectSql::execute(
            "SELECT setting_id, key_value, CAST(key_value AS {$castTarget}) AS cast_value FROM app_settings ORDER BY setting_id",
            ['app_settings' => $rows],
        );

        $trace = [];
        foreach ($castRows as $row) {
            $castValue = $row['cast_value'];
            $castText = self::textValue($castValue);
            $candidate = $range !== null && strcmp($castText, $range['lowerInclusive']) >= 0
                && ($range['upperBound'] === null || strcmp($castText, $range['upperBound']) < 0);
            $matched = $candidate && ($operator === 'LIKE'
                ? SQLiteDatabase::likeMatches($castText, $pattern, $escape, true)
                : SQLiteDatabase::globMatches($castText, $pattern));
            $trace[] = [
                'rowid' => $row['setting_id'],
                'originalStorage' => self::storageClass($row['key_value']),
                'castStorage' => self::storageClass($castValue),
                'castValue' => $castValue instanceof SQLiteBlobValue ? $castValue->bytes : $castValue,
                'castText' => $castText,
                'castTextHex' => strtoupper(bin2hex($castText)),
                'candidate' => $candidate,
                'matched' => $matched,
            ];
        }

        return $trace;
    }

    private static function castTargetSql(string $target): string
    {
        $target = trim($target);
        if (preg_match('/^[A-Za-z][A-Za-z0-9_ ]*(?:\([0-9 ,]+\))?$/', $target) !== 1) {
            throw new \InvalidArgumentException('SQLite CAST LIKE/GLOB current-source target affinity is malformed');
        }

        return $target;
    }

    private static function textValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if ($value instanceof SQLiteBlobValue) {
            return $value->bytes;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value) || is_string($value)) {
            return (string) $value;
        }

        throw new \InvalidArgumentException('SQLite CAST LIKE/GLOB values must be scalar, BLOB, or NULL');
    }

    private static function storageClass(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            $value instanceof SQLiteBlobValue => 'blob',
            is_int($value), is_bool($value) => 'integer',
            is_float($value) => 'real',
            is_string($value) => 'text',
            default => throw new \InvalidArgumentException('SQLite CAST LIKE/GLOB values must be scalar, BLOB, or NULL'),
        };
    }

    /**
     * @param list<array<string,mixed>> $trace
     * @return array<int,array<string,mixed>>
     */
    private static function byRowid(array $trace): array
    {
        $indexed = [];
        foreach ($trace as $entry) {
            $indexed[$entry['rowid']] = $entry;
        }

        return $indexed;
    }

    /**
     * @param list<array<string,mixed>> $trace
     * @return list<int>
     */
    private static function rowidsWhere(array $trace, string $flag): array
    {
        return array_values(array_map(
            static fn (array $entry): int => $entry['rowid'],
            array_filter($trace, static fn (array $entry): bool => $entry[$flag] === true),
        ));
    }
}
