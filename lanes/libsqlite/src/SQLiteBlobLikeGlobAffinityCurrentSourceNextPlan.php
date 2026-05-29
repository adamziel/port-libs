<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteBlobLikeGlobAffinityCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionValuePlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        string $operator = 'LIKE',
        string $collation = 'BINARY',
        ?string $escape = null,
        bool $caseSensitiveLike = false,
        bool $explicitCastToText = false,
        string $currentSource = 'main.wp_options@233',
        string $nextSource = 'main.wp_options@234',
        int $currentSchemaCookie = 233,
        int $nextSchemaCookie = 234,
    ): array {
        $operator = strtoupper($operator);
        if (!in_array($operator, ['LIKE', 'GLOB'], true)) {
            throw new \InvalidArgumentException('SQLite BLOB LIKE/GLOB affinity next234 operator must be LIKE or GLOB');
        }
        if ($operator === 'GLOB' && $escape !== null) {
            throw new \InvalidArgumentException('SQLite BLOB LIKE/GLOB affinity next234 GLOB does not accept ESCAPE');
        }

        $collation = strtoupper($collation);
        if (!in_array($collation, ['BINARY', 'NOCASE', 'RTRIM'], true)) {
            throw new \InvalidArgumentException('SQLite BLOB LIKE/GLOB affinity next234 collation must be BINARY, NOCASE, or RTRIM');
        }

        $current = self::scan($currentRows, $pattern, $operator, $collation, $escape, $caseSensitiveLike, $explicitCastToText);
        $next = self::scan($nextRows, $pattern, $operator, $collation, $escape, $caseSensitiveLike, $explicitCastToText);
        $currentRowids = self::rowidsWhere($current['trace'], 'matched');
        $nextRowids = self::rowidsWhere($next['trace'], 'matched');
        $currentBlobSkipped = self::rowidsWhere($current['trace'], 'blobLikeSkipped');
        $nextBlobSkipped = self::rowidsWhere($next['trace'], 'blobLikeSkipped');
        $changes = self::changes($current['trace'], $next['trace']);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        foreach ([
            'value-storage' => $changes['storageRowids'],
            'value-bytes' => $changes['bytesRowids'],
            'like-text' => $changes['likeTextRowids'],
            'blob-skip-state' => $changes['blobSkipRowids'],
            'matched-rowset' => $currentRowids === $nextRowids ? [] : array_values(array_unique(array_merge($currentRowids, $nextRowids))),
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }

        return [
            'status' => 'blob-like-glob-affinity-current-source-next234',
            'operator' => $operator,
            'pattern' => $pattern,
            'collation' => $collation,
            'escape' => $escape,
            'caseSensitiveLike' => $caseSensitiveLike,
            'explicitCastToText' => $explicitCastToText,
            'likeDoesNotMatchBlobs' => true,
            'globDoesNotMatchBlobs' => true,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentTrace' => $current['trace'],
            'nextTrace' => $next['trace'],
            'currentRowids' => $currentRowids,
            'nextRowids' => $nextRowids,
            'retainedRowids' => array_values(array_intersect($currentRowids, $nextRowids)),
            'enteredRowids' => array_values(array_diff($nextRowids, $currentRowids)),
            'exitedRowids' => array_values(array_diff($currentRowids, $nextRowids)),
            'currentBlobSkippedRowids' => $currentBlobSkipped,
            'nextBlobSkippedRowids' => $nextBlobSkipped,
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'changedStorageRowids' => $changes['storageRowids'],
            'changedBytesRowids' => $changes['bytesRowids'],
            'changedLikeTextRowids' => $changes['likeTextRowids'],
            'changedBlobSkipRowids' => $changes['blobSkipRowids'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-like-does-not-match-blobs',
                'sqlite-explicit-cast-text-admission',
                'sqlite-like-glob-collation',
                'sqlite-current-source-next234',
            ],
            'dependency_closure' => 'no new support component needed; reuses native scalar storage classification, SQLiteBlobValue bytes, LIKE/GLOB residual matching, and current-source invalidation diagnostics',
            'non_overlap' => 'covers implicit BLOB exclusion versus explicit CAST admission for LIKE/GLOB option_value scans; avoids accepted UTF-16 NOCASE/LIKE/RTRIM, Unicode GLOB range, malformed UTF-16 insert guard, and scalar-only cast/collation clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{trace:list<array<string,mixed>>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function scan(array $rows, string $pattern, string $operator, string $collation, ?string $escape, bool $caseSensitiveLike, bool $explicitCastToText): array
    {
        $trace = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            self::assertRow($row);
            $rowid = $row['option_id'];
            try {
                $decoded = self::decodeValue($row['option_value']);
                $storage = SQLiteAffinityComparison::storageClass($decoded);
                $blobSkipped = $decoded instanceof SQLiteBlobValue && !$explicitCastToText;
                $likeText = $blobSkipped ? null : self::likeText($decoded, $explicitCastToText);
                $collationKey = $likeText === null ? null : self::collationKey($likeText, $collation);
                $matched = $likeText !== null && ($operator === 'LIKE'
                    ? SQLiteDatabase::likeMatches($likeText, $pattern, $escape, $caseSensitiveLike)
                    : SQLiteDatabase::globMatches($likeText, $pattern));
                $trace[] = [
                    'rowid' => $rowid,
                    'optionName' => (string) ($row['option_name'] ?? ''),
                    'storage' => $storage,
                    'bytesHex' => self::bytesHex($decoded),
                    'likeText' => $likeText,
                    'likeTextHex' => $likeText === null ? null : strtoupper(bin2hex($likeText)),
                    'collationKey' => $collationKey,
                    'blobLikeSkipped' => $blobSkipped,
                    'matched' => $matched,
                    'autoload' => $row['autoload'] ?? null,
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $rowid;
                $errors[$rowid] = $exception->getMessage();
            }
        }

        usort($trace, static function (array $left, array $right) use ($collation): int {
            $leftText = $left['likeText'] ?? '';
            $rightText = $right['likeText'] ?? '';
            $comparison = SQLiteAffinityComparison::compare($leftText, $rightText, 'TEXT', 'TEXT', $collation);

            return $comparison !== null && $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
        });
        sort($malformed);
        ksort($errors);

        return ['trace' => $trace, 'malformedRowids' => $malformed, 'errors' => $errors];
    }

    /** @param array<string,mixed> $row */
    private static function assertRow(array $row): void
    {
        if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite BLOB LIKE/GLOB affinity next234 rows require integer option_id');
        }
        if (!array_key_exists('option_value', $row)) {
            throw new \InvalidArgumentException('SQLite BLOB LIKE/GLOB affinity next234 rows require option_value');
        }
    }

    private static function decodeValue(mixed $value): mixed
    {
        if ($value instanceof SQLiteBlobValue || $value === null || is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
            return $value;
        }

        throw new \InvalidArgumentException('SQLite BLOB LIKE/GLOB affinity next234 rows require scalar option_value values');
    }

    private static function likeText(mixed $value, bool $explicitCastToText): string
    {
        if ($value instanceof SQLiteBlobValue) {
            if ($explicitCastToText && preg_match('//u', $value->bytes) !== 1) {
                throw new \InvalidArgumentException('SQLite BLOB LIKE/GLOB affinity next234 explicit CAST blob bytes are malformed UTF-8');
            }

            return $value->bytes;
        }
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_float($value)) {
            return rtrim(rtrim(sprintf('%.15G', $value), '0'), '.');
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (preg_match('//u', $value) !== 1) {
            throw new \InvalidArgumentException('SQLite BLOB LIKE/GLOB affinity next234 text value is malformed UTF-8');
        }

        return $value;
    }

    private static function collationKey(string $text, string $collation): string
    {
        return match ($collation) {
            'BINARY' => $text,
            'NOCASE' => strtr($text, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'),
            'RTRIM' => rtrim($text, ' '),
        };
    }

    private static function bytesHex(mixed $value): ?string
    {
        if ($value instanceof SQLiteBlobValue) {
            return strtoupper(bin2hex($value->bytes));
        }
        if (is_string($value)) {
            return strtoupper(bin2hex($value));
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<int>
     */
    private static function rowidsWhere(array $rows, string $field): array
    {
        $rowids = [];
        foreach ($rows as $row) {
            if (($row[$field] ?? false) === true) {
                $rowids[] = $row['rowid'];
            }
        }

        return $rowids;
    }

    /**
     * @param list<array<string,mixed>> $current
     * @param list<array<string,mixed>> $next
     * @return array{storageRowids:list<int>,bytesRowids:list<int>,likeTextRowids:list<int>,blobSkipRowids:list<int>}
     */
    private static function changes(array $current, array $next): array
    {
        $currentByRowid = [];
        foreach ($current as $row) {
            $currentByRowid[$row['rowid']] = $row;
        }

        $storage = [];
        $bytes = [];
        $text = [];
        $blobSkip = [];
        foreach ($next as $row) {
            $rowid = $row['rowid'];
            if (!isset($currentByRowid[$rowid])) {
                $storage[] = $rowid;
                $bytes[] = $rowid;
                $text[] = $rowid;
                $blobSkip[] = $rowid;
                continue;
            }
            $currentRow = $currentByRowid[$rowid];
            if ($currentRow['storage'] !== $row['storage']) {
                $storage[] = $rowid;
            }
            if ($currentRow['bytesHex'] !== $row['bytesHex']) {
                $bytes[] = $rowid;
            }
            if ($currentRow['likeText'] !== $row['likeText']) {
                $text[] = $rowid;
            }
            if ($currentRow['blobLikeSkipped'] !== $row['blobLikeSkipped']) {
                $blobSkip[] = $rowid;
            }
        }
        $nextRowids = array_column($next, 'rowid');
        foreach ($currentByRowid as $rowid => $_row) {
            if (!in_array($rowid, $nextRowids, true)) {
                $storage[] = $rowid;
                $bytes[] = $rowid;
                $text[] = $rowid;
                $blobSkip[] = $rowid;
            }
        }

        return [
            'storageRowids' => self::uniqueSortedInts($storage),
            'bytesRowids' => self::uniqueSortedInts($bytes),
            'likeTextRowids' => self::uniqueSortedInts($text),
            'blobSkipRowids' => self::uniqueSortedInts($blobSkip),
        ];
    }

    /** @param list<int> $values @return list<int> */
    private static function uniqueSortedInts(array $values): array
    {
        $values = array_values(array_unique($values));
        sort($values);

        return $values;
    }
}
