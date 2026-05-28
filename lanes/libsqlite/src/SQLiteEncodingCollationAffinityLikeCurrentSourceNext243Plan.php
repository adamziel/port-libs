<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteEncodingCollationAffinityLikeCurrentSourceNext243Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressRtrimLikeResidualPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'cache_%',
        ?string $escape = null,
        string $collation = 'RTRIM',
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.wp_options@242',
        string $nextSource = 'main.wp_options@243',
        int $currentSchemaCookie = 242,
        int $nextSchemaCookie = 243,
    ): array {
        $collation = strtoupper($collation);
        if (!in_array($collation, ['BINARY', 'NOCASE', 'RTRIM'], true)) {
            throw new \InvalidArgumentException('SQLite LIKE current-source next243 collation must be BINARY, NOCASE, or RTRIM');
        }

        $like = SQLiteLikeCollationPlan::plan($pattern, $collation, $escape, $caseSensitiveLike);
        $current = self::scan($currentRows, $pattern, $escape, $collation, $caseSensitiveLike);
        $next = self::scan($nextRows, $pattern, $escape, $collation, $caseSensitiveLike);
        $currentMatched = self::rowids($current['matched']);
        $nextMatched = self::rowids($next['matched']);
        $currentRtrimCandidates = self::rowids($current['rtrimPrefixCandidates']);
        $nextRtrimCandidates = self::rowids($next['rtrimPrefixCandidates']);
        $changes = self::changes($current['trace'], $next['trace']);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        foreach ([
            'storage-class' => $changes['storageRowids'],
            'like-text' => $changes['likeTextRowids'],
            'rtrim-key' => $changes['rtrimKeyRowids'],
            'rtrim-prefix-candidates' => $currentRtrimCandidates === $nextRtrimCandidates ? [] : self::uniqueSortedInts(array_merge($currentRtrimCandidates, $nextRtrimCandidates)),
            'like-residual-result' => $changes['residualRowids'],
            'matched-rowset' => $currentMatched === $nextMatched ? [] : self::uniqueSortedInts(array_merge($currentMatched, $nextMatched)),
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-next243',
            'operator' => 'LIKE',
            'expression' => 'option_value COLLATE ' . $collation . ' LIKE ? /* RTRIM collation does not trim LIKE residual */',
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => $collation,
            'caseSensitiveLike' => $caseSensitiveLike,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'prefix' => $like['prefix'],
            'prefixCharacters' => $like['prefixCharacters'],
            'prefixIsAscii' => $like['prefixIsAscii'],
            'indexUsable' => $like['indexUsable'],
            'rangeRejectedReason' => $like['rejectedReason'],
            'rangeLowerInclusive' => $like['range']['lowerInclusive'] ?? null,
            'rangeUpperBound' => $like['range']['upperBound'] ?? null,
            'currentTrace' => $current['trace'],
            'nextTrace' => $next['trace'],
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'retainedRowids' => array_values(array_intersect($currentMatched, $nextMatched)),
            'enteredRowids' => array_values(array_diff($nextMatched, $currentMatched)),
            'exitedRowids' => array_values(array_diff($currentMatched, $nextMatched)),
            'currentRtrimPrefixCandidateRowids' => $currentRtrimCandidates,
            'nextRtrimPrefixCandidateRowids' => $nextRtrimCandidates,
            'currentUnknownRowids' => $current['unknownRowids'],
            'nextUnknownRowids' => $next['unknownRowids'],
            'changedStorageRowids' => $changes['storageRowids'],
            'changedLikeTextRowids' => $changes['likeTextRowids'],
            'changedRtrimKeyRowids' => $changes['rtrimKeyRowids'],
            'changedResidualRowids' => $changes['residualRowids'],
            'rtrimCollationTrimsSpacesForKeyOnly' => true,
            'likeResidualSeesTrailingSpaces' => true,
            'likeResidualDoesNotUseRtrimEquality' => true,
            'nocaseLikeFoldsAsciiOnly' => true,
            'textAffinityBeforeLike' => true,
            'nullAndBlobLikeAreUnknown' => true,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => array_values(array_unique($reasons)),
            'dependencies' => [
                'sqlite-like-collation-prefix-range',
                'sqlite-rtrim-collation-key',
                'sqlite-text-affinity-like',
                'sqlite-current-source-next243',
            ],
            'dependency_closure' => 'no new support component needed; reuses native LIKE planning, scalar text-affinity coercion, RTRIM key normalization, and current-source cursor invalidation diagnostics',
            'non_overlap' => 'next243 covers RTRIM collation key versus LIKE residual behavior over current/next option_value scans; avoids accepted Unicode GLOB ranges, UTF-16 malformed guards, UTF-16 NOCASE/RTRIM cursor fences, REAL LIKE next238, escaped option_name LIKE next236, malformed-byte LIKE/NOT LIKE, and SQL/VFS/WAL/B-tree/JSON clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{trace:list<array<string,mixed>>,matched:list<array<string,mixed>>,rtrimPrefixCandidates:list<array<string,mixed>>,unknownRowids:list<int>}
     */
    private static function scan(array $rows, string $pattern, ?string $escape, string $collation, bool $caseSensitiveLike): array
    {
        $trace = [];
        $matched = [];
        $rtrimPrefixCandidates = [];
        $unknown = [];
        $prefix = SQLiteDatabase::likePatternPlan($pattern, $escape)['prefix'];

        foreach ($rows as $index => $row) {
            if (!array_key_exists('option_value', $row)) {
                throw new \InvalidArgumentException('SQLite LIKE current-source next243 row requires option_value');
            }
            $rowid = is_int($row['option_id'] ?? null) ? $row['option_id'] : $index + 1;
            $likeText = self::likeText($row['option_value']);
            if ($likeText === null) {
                $unknown[] = $rowid;
                continue;
            }

            $rtrimKey = rtrim($likeText, ' ');
            $collationKey = self::collationKey($likeText, $collation);
            $rtrimPrefix = self::startsWith($rtrimKey, $prefix, $caseSensitiveLike);
            $residual = SQLiteDatabase::likeMatches($likeText, $pattern, $escape, $caseSensitiveLike);
            $entry = [
                'rowid' => $rowid,
                'optionName' => (string) ($row['option_name'] ?? ''),
                'storage' => SQLiteAffinityComparison::storageClass($row['option_value']),
                'likeText' => $likeText,
                'likeTextHex' => strtoupper(bin2hex($likeText)),
                'rtrimKey' => $rtrimKey,
                'rtrimKeyHex' => strtoupper(bin2hex($rtrimKey)),
                'collationKey' => $collationKey,
                'collationKeyHex' => strtoupper(bin2hex($collationKey)),
                'rtrimPrefixCandidate' => $rtrimPrefix,
                'residualMatch' => $residual,
                'matched' => $residual === true,
            ];
            $trace[] = $entry;
            if ($rtrimPrefix) {
                $rtrimPrefixCandidates[] = $entry;
            }
            if ($entry['matched']) {
                $matched[] = $entry;
            }
        }

        usort($trace, self::sortTrace(...));
        usort($matched, self::sortTrace(...));
        usort($rtrimPrefixCandidates, self::sortTrace(...));
        sort($unknown);

        return [
            'trace' => $trace,
            'matched' => $matched,
            'rtrimPrefixCandidates' => $rtrimPrefixCandidates,
            'unknownRowids' => $unknown,
        ];
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

        throw new \InvalidArgumentException('SQLite LIKE current-source next243 option_value must be scalar text-affinity input');
    }

    private static function collationKey(string $text, string $collation): string
    {
        return match ($collation) {
            'NOCASE' => self::asciiLower($text),
            'RTRIM' => rtrim($text, ' '),
            default => $text,
        };
    }

    private static function startsWith(string $text, string $prefix, bool $caseInsensitiveAscii): bool
    {
        if ($caseInsensitiveAscii) {
            $text = self::asciiLower($text);
            $prefix = self::asciiLower($prefix);
        }

        return strncmp($text, $prefix, strlen($prefix)) === 0;
    }

    private static function asciiLower(string $text): string
    {
        return strtr($text, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /** @param array<string,mixed> $left @param array<string,mixed> $right */
    private static function sortTrace(array $left, array $right): int
    {
        return strcmp((string) $left['collationKey'], (string) $right['collationKey']) ?: $left['rowid'] <=> $right['rowid'];
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function rowids(array $rows): array
    {
        return array_values(array_map(static fn (array $row): int => $row['rowid'], $rows));
    }

    /**
     * @param list<array<string,mixed>> $current
     * @param list<array<string,mixed>> $next
     * @return array{storageRowids:list<int>,likeTextRowids:list<int>,rtrimKeyRowids:list<int>,residualRowids:list<int>}
     */
    private static function changes(array $current, array $next): array
    {
        $currentByRowid = self::byRowid($current);
        $nextByRowid = self::byRowid($next);
        $storage = [];
        $text = [];
        $rtrim = [];
        $residual = [];

        foreach (array_intersect(array_keys($currentByRowid), array_keys($nextByRowid)) as $rowid) {
            if ($currentByRowid[$rowid]['storage'] !== $nextByRowid[$rowid]['storage']) {
                $storage[] = (int) $rowid;
            }
            if ($currentByRowid[$rowid]['likeText'] !== $nextByRowid[$rowid]['likeText']) {
                $text[] = (int) $rowid;
            }
            if ($currentByRowid[$rowid]['rtrimKey'] !== $nextByRowid[$rowid]['rtrimKey']) {
                $rtrim[] = (int) $rowid;
            }
            if ($currentByRowid[$rowid]['residualMatch'] !== $nextByRowid[$rowid]['residualMatch']) {
                $residual[] = (int) $rowid;
            }
        }

        return [
            'storageRowids' => self::uniqueSortedInts($storage),
            'likeTextRowids' => self::uniqueSortedInts($text),
            'rtrimKeyRowids' => self::uniqueSortedInts($rtrim),
            'residualRowids' => self::uniqueSortedInts($residual),
        ];
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function byRowid(array $rows): array
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
        $values = array_values(array_unique(array_map('intval', $values)));
        sort($values);

        return $values;
    }
}
