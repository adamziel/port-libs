<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext231Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameAsciiOnlyNocasePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin_cafÉ%',
        ?string $escape = null,
        string $currentSource = 'main.wp_options@230',
        string $nextSource = 'main.wp_options@231',
        int $currentSchemaCookie = 230,
        int $nextSchemaCookie = 231,
    ): array {
        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $current = self::scan($currentRows, $pattern, $escape, $like['range']);
        $next = self::scan($nextRows, $pattern, $escape, $like['range']);
        $changes = self::changes($current['decoded'], $next['decoded']);
        $currentMatched = self::rowids($current['matched']);
        $nextMatched = self::rowids($next['matched']);
        $currentCandidates = self::rowids($current['candidates']);
        $nextCandidates = self::rowids($next['candidates']);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        foreach ([
            'decoded-text' => $changes['textChangedRowids'],
            'rtrim-expression' => $changes['rtrimChangedRowids'],
            'nocase-key' => $changes['nocaseKeyChangedRowids'],
            'non-ascii-case-class' => $changes['nonAsciiCaseClassChangedRowids'],
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        if ($currentCandidates !== $nextCandidates) {
            $reasons[] = 'candidate-rowset';
        }
        if ($currentMatched !== $nextMatched) {
            $reasons[] = 'matched-rowset';
        }
        if ($changes['nonAsciiCaseClassChangedRowids'] !== []) {
            $reasons[] = 'ascii-only-nocase-boundary';
        }

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next231',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? /* ASCII-only NOCASE boundary */',
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => 'NOCASE',
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'prefix' => $like['prefix'],
            'rangeLowerInclusive' => $like['range']['lowerInclusive'] ?? null,
            'rangeUpperBound' => $like['range']['upperBound'] ?? null,
            'indexUsable' => $like['indexUsable'],
            'currentCandidateRowids' => $currentCandidates,
            'nextCandidateRowids' => $nextCandidates,
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'matchedRetainedRowids' => self::sortedIntersect($currentMatched, $nextMatched),
            'matchedExitedRowids' => self::sortedDiff($currentMatched, $nextMatched),
            'matchedEnteredRowids' => self::sortedDiff($nextMatched, $currentMatched),
            'currentFalsePositiveRowids' => self::rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::rowids($next['falsePositive']),
            'currentNonAsciiCaseVariantRowids' => $current['nonAsciiCaseVariantRowids'],
            'nextNonAsciiCaseVariantRowids' => $next['nonAsciiCaseVariantRowids'],
            'currentAsciiFoldedRowids' => $current['asciiFoldedRowids'],
            'nextAsciiFoldedRowids' => $next['asciiFoldedRowids'],
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'currentDecodedTexts' => self::map($current['decoded'], 'text'),
            'nextDecodedTexts' => self::map($next['decoded'], 'text'),
            'currentRtrimTexts' => self::map($current['decoded'], 'rtrimText'),
            'nextRtrimTexts' => self::map($next['decoded'], 'rtrimText'),
            'currentNocaseKeys' => self::map($current['decoded'], 'nocaseKey'),
            'nextNocaseKeys' => self::map($next['decoded'], 'nocaseKey'),
            'currentNonAsciiCaseClasses' => self::map($current['decoded'], 'nonAsciiCaseClass'),
            'nextNonAsciiCaseClasses' => self::map($next['decoded'], 'nonAsciiCaseClass'),
            'currentResidualMatches' => self::map($current['candidates'], 'residualMatch'),
            'nextResidualMatches' => self::map($next['candidates'], 'residualMatch'),
            'changedTextRowids' => $changes['textChangedRowids'],
            'changedRtrimRowids' => $changes['rtrimChangedRowids'],
            'changedNocaseKeyRowids' => $changes['nocaseKeyChangedRowids'],
            'changedNonAsciiCaseClassRowids' => $changes['nonAsciiCaseClassChangedRowids'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'asciiLettersFoldUnderNocase' => true,
            'nonAsciiLettersDoNotFoldUnderNocase' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'likeResidualRunsAfterRtrim' => true,
            'invalidationReasons' => array_values(array_unique($reasons)),
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-ascii-only-nocase',
                'sqlite-current-source-next231',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE prefix planning, RTRIM expression keys, and residual matching',
            'non_overlap' => 'next231 covers non-ASCII case variants that remain distinct under UTF-16 NOCASE LIKE after RTRIM; avoids accepted next227 ASCII-space RTRIM boundary, next226 combining-mark normalization, next225 raw source bytes, next219 supplementary wildcard matching, Unicode GLOB ranges, and malformed UTF-16 insert guards',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{decoded:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,nonAsciiCaseVariantRowids:list<int>,asciiFoldedRowids:list<int>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function scan(array $rows, string $pattern, ?string $escape, ?array $range): array
    {
        $decoded = [];
        $nonAsciiVariants = [];
        $asciiFolded = [];
        $malformed = [];
        $errors = [];

        foreach ($rows as $row) {
            self::assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
                $rtrim = rtrim($text, ' ');
                $nocaseKey = self::asciiLower($rtrim);
                $class = self::nonAsciiCaseClass($rtrim);
                if ($class !== 'none') {
                    $nonAsciiVariants[] = $row['option_id'];
                }
                if ($nocaseKey !== $rtrim) {
                    $asciiFolded[] = $row['option_id'];
                }
                $decoded[] = [
                    'rowid' => $row['option_id'],
                    'text' => $text,
                    'rtrimText' => $rtrim,
                    'nocaseKey' => $nocaseKey,
                    'nonAsciiCaseClass' => $class,
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['option_id'];
                $errors[$row['option_id']] = $exception->getMessage();
            }
        }

        usort($decoded, self::sortRows(...));
        sort($nonAsciiVariants);
        sort($asciiFolded);
        sort($malformed);
        ksort($errors);

        $candidates = [];
        $matched = [];
        $falsePositive = [];
        foreach ($decoded as $entry) {
            if (!self::inRange($entry['nocaseKey'], $range)) {
                continue;
            }
            $entry['residualMatch'] = SQLiteDatabase::likeMatches($entry['rtrimText'], $pattern, $escape, false);
            $candidates[] = $entry;
            if ($entry['residualMatch']) {
                $matched[] = $entry;
            } else {
                $falsePositive[] = $entry;
            }
        }

        return [
            'decoded' => $decoded,
            'candidates' => $candidates,
            'matched' => $matched,
            'falsePositive' => $falsePositive,
            'nonAsciiCaseVariantRowids' => $nonAsciiVariants,
            'asciiFoldedRowids' => $asciiFolded,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row */
    private static function assertRow(array $row): void
    {
        if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next231 rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next231 rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next231 rows require integer text_encoding');
        }
    }

    private static function nonAsciiCaseClass(string $text): string
    {
        $hasLower = str_contains($text, "\xc3\xa9") || str_contains($text, "\xce\xbc");
        $hasUpper = str_contains($text, "\xc3\x89") || str_contains($text, "\xce\x9c");

        if ($hasLower && $hasUpper) {
            return 'mixed-non-ascii-case';
        }
        if ($hasUpper) {
            return 'upper-non-ascii-case';
        }
        if ($hasLower) {
            return 'lower-non-ascii-case';
        }

        return 'none';
    }

    /** @param ?array{lowerInclusive:string,upperBound:?string} $range */
    private static function inRange(string $key, ?array $range): bool
    {
        if ($range === null || strcmp($key, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0;
    }

    /** @param array{nocaseKey:string,rowid:int} $left @param array{nocaseKey:string,rowid:int} $right */
    private static function sortRows(array $left, array $right): int
    {
        $comparison = strcmp($left['nocaseKey'], $right['nocaseKey']);

        return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
    }

    /** @param list<array{rowid:int}> $rows @return list<int> */
    private static function rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $rows @return array<int,mixed> */
    private static function map(array $rows, string $key): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[$row['rowid']] = $row[$key];
        }

        return $mapped;
    }

    /**
     * @param list<array<string,mixed>> $current
     * @param list<array<string,mixed>> $next
     * @return array{textChangedRowids:list<int>,rtrimChangedRowids:list<int>,nocaseKeyChangedRowids:list<int>,nonAsciiCaseClassChangedRowids:list<int>}
     */
    private static function changes(array $current, array $next): array
    {
        $currentById = self::byRowid($current);
        $nextById = self::byRowid($next);
        $rowids = array_values(array_unique(array_merge(array_keys($currentById), array_keys($nextById))));
        sort($rowids);
        $changes = [
            'textChangedRowids' => [],
            'rtrimChangedRowids' => [],
            'nocaseKeyChangedRowids' => [],
            'nonAsciiCaseClassChangedRowids' => [],
        ];
        foreach ($rowids as $rowid) {
            foreach ([
                'text' => 'textChangedRowids',
                'rtrimText' => 'rtrimChangedRowids',
                'nocaseKey' => 'nocaseKeyChangedRowids',
                'nonAsciiCaseClass' => 'nonAsciiCaseClassChangedRowids',
            ] as $field => $key) {
                if (($currentById[$rowid][$field] ?? null) !== ($nextById[$rowid][$field] ?? null)) {
                    $changes[$key][] = $rowid;
                }
            }
        }

        return $changes;
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

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function sortedIntersect(array $left, array $right): array
    {
        $values = array_values(array_intersect($left, $right));
        sort($values);

        return $values;
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function sortedDiff(array $left, array $right): array
    {
        $values = array_values(array_diff($left, $right));
        sort($values);

        return $values;
    }

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }
}
