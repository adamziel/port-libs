<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteEncodingCollationAffinityLikeCurrentSourceNext246Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressDynamicEscapeLikePlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        mixed $currentEscape,
        mixed $nextEscape,
        string $collation = 'NOCASE',
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.wp_options@245',
        string $nextSource = 'main.wp_options@246',
        int $currentSchemaCookie = 245,
        int $nextSchemaCookie = 246,
    ): array {
        $collation = strtoupper($collation);
        if (!in_array($collation, ['BINARY', 'NOCASE', 'RTRIM'], true)) {
            throw new \InvalidArgumentException('SQLite dynamic ESCAPE LIKE next246 collation must be BINARY, NOCASE, or RTRIM');
        }

        $currentEscapePlan = self::escapePlan($currentEscape, 'current');
        $nextEscapePlan = self::escapePlan($nextEscape, 'next');
        $current = self::scan($currentRows, $pattern, $currentEscapePlan['escape'], $collation, $caseSensitiveLike);
        $next = self::scan($nextRows, $pattern, $nextEscapePlan['escape'], $collation, $caseSensitiveLike);
        $currentMatched = self::rowids($current['matched']);
        $nextMatched = self::rowids($next['matched']);
        $changes = self::changes($current['trace'], $next['trace']);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($currentEscapePlan['escapeHex'] !== $nextEscapePlan['escapeHex'] || $currentEscapePlan['storage'] !== $nextEscapePlan['storage']) {
            $reasons[] = 'escape-affinity';
        }
        if ($currentEscapePlan['error'] !== null || $nextEscapePlan['error'] !== null) {
            $reasons[] = 'escape-malformed';
        }
        foreach ([
            'storage-class' => $changes['storageRowids'],
            'like-text' => $changes['likeTextRowids'],
            'collation-key' => $changes['collationKeyRowids'],
            'residual-result' => $changes['residualRowids'],
            'matched-rowset' => $currentMatched === $nextMatched ? [] : self::uniqueSortedInts(array_merge($currentMatched, $nextMatched)),
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-next246',
            'operator' => 'LIKE',
            'expression' => 'option_value COLLATE ' . $collation . ' LIKE ? ESCAPE dynamic_escape /* ESCAPE text affinity current-source fence */',
            'pattern' => $pattern,
            'patternHex' => strtoupper(bin2hex($pattern)),
            'collation' => $collation,
            'caseSensitiveLike' => $caseSensitiveLike,
            'currentEscape' => $currentEscapePlan,
            'nextEscape' => $nextEscapePlan,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentTrace' => $current['trace'],
            'nextTrace' => $next['trace'],
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'retainedRowids' => array_values(array_intersect($currentMatched, $nextMatched)),
            'enteredRowids' => array_values(array_diff($nextMatched, $currentMatched)),
            'exitedRowids' => array_values(array_diff($currentMatched, $nextMatched)),
            'currentUnknownRowids' => $current['unknownRowids'],
            'nextUnknownRowids' => $next['unknownRowids'],
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'changedStorageRowids' => $changes['storageRowids'],
            'changedLikeTextRowids' => $changes['likeTextRowids'],
            'changedCollationKeyRowids' => $changes['collationKeyRowids'],
            'changedResidualRowids' => $changes['residualRowids'],
            'dynamicEscapeUsesTextAffinity' => true,
            'escapeMustBeOneSqlCharacter' => true,
            'escapeRebindInvalidatesCursor' => true,
            'nullEscapeMakesLikeUnknown' => true,
            'blobEscapeIsNotTextAffinityInput' => true,
            'nocaseFoldsAsciiOnly' => true,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => array_values(array_unique($reasons)),
            'dependencies' => [
                'sqlite-like-dynamic-escape-affinity',
                'sqlite-like-residual',
                'sqlite-nocase-ascii-collation',
                'sqlite-current-source-next246',
            ],
            'dependency_closure' => 'no new support component needed; reuses native LIKE residual matching, scalar text-affinity conversion, ASCII NOCASE collation keys, and current-source invalidation diagnostics',
            'non_overlap' => 'next246 covers dynamic ESCAPE operand affinity and rebind fencing; avoids fixed escaped wildcard next236/next237, malformed-byte LIKE/NOT LIKE, UTF-16 NOCASE/RTRIM cursor fences, Unicode GLOB ranges, and VFS/WAL/B-tree/JSON/SQL executor clusters',
        ];
    }

    /** @return array{storage:string,escape:?string,escapeHex:?string,error:?string,unknown:bool} */
    private static function escapePlan(mixed $value, string $label): array
    {
        $storage = SQLiteAffinityComparison::storageClass($value);
        if ($value === null) {
            return ['storage' => $storage, 'escape' => null, 'escapeHex' => null, 'error' => null, 'unknown' => true];
        }
        if ($value instanceof SQLiteBlobValue) {
            return ['storage' => $storage, 'escape' => null, 'escapeHex' => strtoupper(bin2hex($value->bytes)), 'error' => "SQLite dynamic ESCAPE LIKE next246 {$label} ESCAPE is BLOB, not text", 'unknown' => true];
        }
        $escape = self::likeText($value);
        if ($escape === null) {
            return ['storage' => $storage, 'escape' => null, 'escapeHex' => null, 'error' => "SQLite dynamic ESCAPE LIKE next246 {$label} ESCAPE is not scalar text", 'unknown' => true];
        }
        if (self::sqlitePatternLength($escape) !== 1) {
            return ['storage' => $storage, 'escape' => null, 'escapeHex' => strtoupper(bin2hex($escape)), 'error' => "SQLite dynamic ESCAPE LIKE next246 {$label} ESCAPE must be one SQLite character after affinity", 'unknown' => true];
        }

        return ['storage' => $storage, 'escape' => $escape, 'escapeHex' => strtoupper(bin2hex($escape)), 'error' => null, 'unknown' => false];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{trace:list<array<string,mixed>>,matched:list<array<string,mixed>>,unknownRowids:list<int>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function scan(array $rows, string $pattern, ?string $escape, string $collation, bool $caseSensitiveLike): array
    {
        $trace = [];
        $matched = [];
        $unknown = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $index => $row) {
            if (!array_key_exists('option_value', $row)) {
                throw new \InvalidArgumentException('SQLite dynamic ESCAPE LIKE next246 row requires option_value');
            }
            $rowid = is_int($row['option_id'] ?? null) ? $row['option_id'] : $index + 1;
            try {
                $likeText = self::likeText($row['option_value']);
                if ($likeText === null || $escape === null) {
                    $unknown[] = $rowid;
                    continue;
                }
                if (preg_match('//u', $likeText) !== 1) {
                    throw new \InvalidArgumentException('SQLite dynamic ESCAPE LIKE next246 option_value text is malformed UTF-8');
                }
                $residual = SQLiteDatabase::likeMatches($likeText, $pattern, $escape, $caseSensitiveLike);
                $entry = [
                    'rowid' => $rowid,
                    'optionName' => (string) ($row['option_name'] ?? ''),
                    'storage' => SQLiteAffinityComparison::storageClass($row['option_value']),
                    'likeText' => $likeText,
                    'likeTextHex' => strtoupper(bin2hex($likeText)),
                    'collationKey' => self::collationKey($likeText, $collation),
                    'residualMatch' => $residual,
                    'matched' => $residual,
                ];
                $trace[] = $entry;
                if ($residual) {
                    $matched[] = $entry;
                }
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $rowid;
                $errors[$rowid] = $exception->getMessage();
            }
        }

        usort($trace, self::sortTrace(...));
        usort($matched, self::sortTrace(...));
        sort($unknown);
        sort($malformed);
        ksort($errors);

        return ['trace' => $trace, 'matched' => $matched, 'unknownRowids' => $unknown, 'malformedRowids' => $malformed, 'errors' => $errors];
    }

    private static function likeText(mixed $value): ?string
    {
        if ($value === null || $value instanceof SQLiteBlobValue) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            $text = sprintf('%.15g', $value);
            return str_contains($text, '.') || stripos($text, 'e') !== false ? $text : $text . '.0';
        }
        if (is_string($value)) {
            return $value;
        }

        throw new \InvalidArgumentException('SQLite dynamic ESCAPE LIKE next246 option_value must be scalar text-affinity input');
    }

    private static function collationKey(string $text, string $collation): string
    {
        return match ($collation) {
            'NOCASE' => strtr($text, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'),
            'RTRIM' => rtrim($text, ' '),
            default => $text,
        };
    }

    private static function sortTrace(array $left, array $right): int
    {
        return strcmp($left['collationKey'], $right['collationKey']) ?: $left['rowid'] <=> $right['rowid'];
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function rowids(array $rows): array
    {
        return array_values(array_map(static fn (array $row): int => $row['rowid'], $rows));
    }

    /**
     * @param list<array<string,mixed>> $current
     * @param list<array<string,mixed>> $next
     * @return array{storageRowids:list<int>,likeTextRowids:list<int>,collationKeyRowids:list<int>,residualRowids:list<int>}
     */
    private static function changes(array $current, array $next): array
    {
        $currentByRowid = self::rowsByRowid($current);
        $nextByRowid = self::rowsByRowid($next);
        $rowids = self::uniqueSortedInts(array_merge(array_keys($currentByRowid), array_keys($nextByRowid)));
        $storage = [];
        $text = [];
        $key = [];
        $residual = [];
        foreach ($rowids as $rowid) {
            $left = $currentByRowid[$rowid] ?? null;
            $right = $nextByRowid[$rowid] ?? null;
            if ($left === null || $right === null) {
                $storage[] = $rowid;
                $text[] = $rowid;
                $key[] = $rowid;
                $residual[] = $rowid;
                continue;
            }
            if ($left['storage'] !== $right['storage']) {
                $storage[] = $rowid;
            }
            if ($left['likeText'] !== $right['likeText']) {
                $text[] = $rowid;
            }
            if ($left['collationKey'] !== $right['collationKey']) {
                $key[] = $rowid;
            }
            if ($left['residualMatch'] !== $right['residualMatch']) {
                $residual[] = $rowid;
            }
        }

        return [
            'storageRowids' => self::uniqueSortedInts($storage),
            'likeTextRowids' => self::uniqueSortedInts($text),
            'collationKeyRowids' => self::uniqueSortedInts($key),
            'residualRowids' => self::uniqueSortedInts($residual),
        ];
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function rowsByRowid(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['rowid']] = $row;
        }

        return $indexed;
    }

    /** @param list<int> $values @return list<int> */
    private static function uniqueSortedInts(array $values): array
    {
        $values = array_values(array_unique($values));
        sort($values);

        return $values;
    }

    private static function sqlitePatternLength(string $text): int
    {
        if ($text === '') {
            return 0;
        }
        if (preg_match_all('/./us', $text, $matches) === false || implode('', $matches[0]) !== $text) {
            return strlen($text);
        }

        return count($matches[0]);
    }
}
