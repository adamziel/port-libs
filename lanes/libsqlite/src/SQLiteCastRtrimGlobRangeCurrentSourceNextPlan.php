<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCastRtrimGlobRangeCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function optionRowValuePlan(
        array $currentRows,
        array $nextRows,
        string $castTarget,
        string $pattern,
        string $currentSource = 'main.wp_options@126',
        string $nextSource = 'main.wp_options@127',
        int $currentSchemaCookie = 126,
        int $nextSchemaCookie = 127,
    ): array {
        $range = SQLiteDatabase::globPrefixRangeBounds($pattern);
        $currentTrace = self::traceRows($currentRows, $castTarget, $pattern, $range);
        $nextTrace = self::traceRows($nextRows, $castTarget, $pattern, $range);
        $currentByRowid = self::byRowid($currentTrace);
        $nextByRowid = self::byRowid($nextTrace);

        $currentCandidates = self::rowidsWhere($currentTrace, 'candidate');
        $nextCandidates = self::rowidsWhere($nextTrace, 'candidate');
        $currentMatched = self::rowidsWhere($currentTrace, 'matched');
        $nextMatched = self::rowidsWhere($nextTrace, 'matched');
        $changedCast = [];
        $changedCandidate = [];
        $changedMatch = [];
        $changedRtrimKey = [];
        foreach (array_unique(array_merge(array_keys($currentByRowid), array_keys($nextByRowid))) as $rowid) {
            $current = $currentByRowid[$rowid] ?? null;
            $next = $nextByRowid[$rowid] ?? null;
            if ($current === null || $next === null || $current['castStorage'] !== $next['castStorage'] || $current['castText'] !== $next['castText']) {
                $changedCast[] = (int) $rowid;
            }
            if ($current === null || $next === null || $current['candidate'] !== $next['candidate']) {
                $changedCandidate[] = (int) $rowid;
            }
            if ($current === null || $next === null || $current['matched'] !== $next['matched']) {
                $changedMatch[] = (int) $rowid;
            }
            if ($current === null || $next === null || $current['rtrimKey'] !== $next['rtrimKey']) {
                $changedRtrimKey[] = (int) $rowid;
            }
        }
        sort($changedCast);
        sort($changedCandidate);
        sort($changedMatch);
        sort($changedRtrimKey);

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
        if ($changedRtrimKey !== []) {
            $reasons[] = 'rtrim-key';
        }
        if ($changedCandidate !== []) {
            $reasons[] = 'candidate-rowset';
        }
        if ($changedMatch !== []) {
            $reasons[] = 'matched-rowset';
        }

        return [
            'operator' => 'GLOB',
            'collation' => 'RTRIM',
            'castTarget' => trim($castTarget),
            'pattern' => $pattern,
            'range' => $range,
            'indexUsable' => $range !== null,
            'residualScan' => true,
            'globDoesNotTrimTrailingSpaces' => true,
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
            'changedRtrimKeyRowids' => $changedRtrimKey,
            'changedCandidateRowids' => $changedCandidate,
            'changedMatchRowids' => $changedMatch,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-select-cast-expression',
                'sqlite-rtrim-glob-prefix-range',
                'sqlite-glob-binary-residual',
                'sqlite-current-source-next127',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param null|array{lowerInclusive:string,upperBound:?string} $range
     * @return list<array<string,mixed>>
     */
    private static function traceRows(array $rows, string $castTarget, string $pattern, ?array $range): array
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite CAST RTRIM GLOB rows must be arrays');
            }
            foreach (['option_id', 'option_value'] as $column) {
                if (!array_key_exists($column, $row)) {
                    throw new \InvalidArgumentException("SQLite CAST RTRIM GLOB row is missing {$column}");
                }
            }
            if (!is_int($row['option_id'])) {
                throw new \InvalidArgumentException('SQLite CAST RTRIM GLOB option_id must be an integer');
            }
        }

        $castTarget = self::castTargetSql($castTarget);
        $castRows = SQLiteSelectSql::execute(
            "SELECT option_id, option_value, CAST(option_value AS {$castTarget}) AS cast_value FROM wp_options ORDER BY option_id",
            ['wp_options' => $rows],
        );

        $trace = [];
        foreach ($castRows as $row) {
            $castValue = $row['cast_value'];
            $castText = self::textValue($castValue);
            $rtrimKey = rtrim($castText, ' ');
            $candidate = $range !== null && strcmp($rtrimKey, $range['lowerInclusive']) >= 0
                && ($range['upperBound'] === null || strcmp($rtrimKey, $range['upperBound']) < 0);
            $matched = $candidate && SQLiteDatabase::globMatches($castText, $pattern);
            $trace[] = [
                'rowid' => $row['option_id'],
                'originalStorage' => self::storageClass($row['option_value']),
                'castStorage' => self::storageClass($castValue),
                'castValue' => $castValue instanceof SQLiteBlobValue ? $castValue->bytes : $castValue,
                'castText' => $castText,
                'castTextHex' => strtoupper(bin2hex($castText)),
                'rtrimKey' => $rtrimKey,
                'rtrimKeyHex' => strtoupper(bin2hex($rtrimKey)),
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
            throw new \InvalidArgumentException('SQLite CAST RTRIM GLOB target affinity is malformed');
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

        throw new \InvalidArgumentException('SQLite CAST RTRIM GLOB values must be scalar, BLOB, or NULL');
    }

    private static function storageClass(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            $value instanceof SQLiteBlobValue => 'blob',
            is_int($value), is_bool($value) => 'integer',
            is_float($value) => 'real',
            is_string($value) => 'text',
            default => throw new \InvalidArgumentException('SQLite CAST RTRIM GLOB values must be scalar, BLOB, or NULL'),
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
