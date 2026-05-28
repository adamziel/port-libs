<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteEncodingCollationAffinityLikeCurrentSourceNext248Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressNonAsciiEscapeLikePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'pluginé_cacheé%%',
        ?string $escape = 'é',
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.wp_options@247',
        string $nextSource = 'main.wp_options@248',
        int $currentSchemaCookie = 247,
        int $nextSchemaCookie = 248,
    ): array {
        if ($escape !== null && self::sqlitePatternLength($escape) !== 1) {
            throw new \InvalidArgumentException('SQLite non-ASCII ESCAPE LIKE next248 ESCAPE must be one SQLite pattern character');
        }

        $patternPlan = SQLiteDatabase::likePatternPlan($pattern, $escape);
        $range = $caseSensitiveLike ? $patternPlan['binaryRange'] : $patternPlan['noCaseRange'];
        $current = self::scanRows($currentRows, $pattern, $escape, $caseSensitiveLike, $range);
        $next = self::scanRows($nextRows, $pattern, $escape, $caseSensitiveLike, $range);
        $currentCandidates = self::rowids($current['candidates']);
        $nextCandidates = self::rowids($next['candidates']);
        $currentMatched = self::rowids($current['matched']);
        $nextMatched = self::rowids($next['matched']);
        $currentRejected = self::rowids($current['rejected']);
        $nextRejected = self::rowids($next['rejected']);
        $retainedMatched = array_values(array_intersect($currentMatched, $nextMatched));
        $exitedMatched = array_values(array_diff($currentMatched, $nextMatched));
        $enteredMatched = array_values(array_diff($nextMatched, $currentMatched));
        $currentByRowid = self::rowsByRowid($current['trace']);
        $nextByRowid = self::rowsByRowid($next['trace']);
        $changedDecoded = [];
        $changedEncoding = [];
        $changedResidual = [];
        foreach (array_intersect(array_keys($currentByRowid), array_keys($nextByRowid)) as $rowid) {
            if (($currentByRowid[$rowid]['decodedHex'] ?? null) !== ($nextByRowid[$rowid]['decodedHex'] ?? null)) {
                $changedDecoded[] = $rowid;
            }
            if (($currentByRowid[$rowid]['textEncoding'] ?? null) !== ($nextByRowid[$rowid]['textEncoding'] ?? null)) {
                $changedEncoding[] = $rowid;
            }
            if (($currentByRowid[$rowid]['residualMatch'] ?? null) !== ($nextByRowid[$rowid]['residualMatch'] ?? null)) {
                $changedResidual[] = $rowid;
            }
        }
        sort($changedDecoded);
        sort($changedEncoding);
        sort($changedResidual);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
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
        if ($changedDecoded !== []) {
            $reasons[] = 'decoded-text';
        }
        if ($changedEncoding !== []) {
            $reasons[] = 'text-encoding';
        }
        if ($changedResidual !== []) {
            $reasons[] = 'like-residual-result';
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-next248',
            'operator' => 'LIKE',
            'expression' => 'option_name COLLATE ' . ($caseSensitiveLike ? 'BINARY' : 'NOCASE') . ' LIKE ? ESCAPE ? /* non-ASCII ESCAPE */',
            'pattern' => $pattern,
            'patternHex' => bin2hex($pattern),
            'patternTokenHex' => self::tokenHexList($pattern),
            'patternCharacters' => self::sqlitePatternLength($pattern),
            'escape' => $escape,
            'escapeHex' => $escape === null ? null : bin2hex($escape),
            'escapeTokenHex' => $escape === null ? null : self::tokenHexList($escape),
            'caseSensitiveLike' => $caseSensitiveLike,
            'collation' => $caseSensitiveLike ? 'BINARY' : 'NOCASE',
            'prefix' => $patternPlan['prefix'],
            'prefixHex' => bin2hex($patternPlan['prefix']),
            'prefixTokenHex' => self::tokenHexList($patternPlan['prefix']),
            'prefixCharacters' => $patternPlan['prefixCharacters'],
            'prefixIsAscii' => $patternPlan['prefixIsAscii'],
            'hasWildcard' => $patternPlan['hasWildcard'],
            'rangeLowerInclusive' => $range['lowerInclusive'],
            'rangeUpperBound' => $range['upperBound'],
            'rangeLowerInclusiveHex' => bin2hex($range['lowerInclusive']),
            'rangeUpperBoundHex' => $range['upperBound'] === null ? null : bin2hex($range['upperBound']),
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentTrace' => $current['trace'],
            'nextTrace' => $next['trace'],
            'currentCandidateRowids' => $currentCandidates,
            'nextCandidateRowids' => $nextCandidates,
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'retainedMatchedRowids' => $retainedMatched,
            'exitedMatchedRowids' => $exitedMatched,
            'enteredMatchedRowids' => $enteredMatched,
            'currentResidualRejectedRowids' => $currentRejected,
            'nextResidualRejectedRowids' => $nextRejected,
            'currentUnknownRowids' => $current['unknownRowids'],
            'nextUnknownRowids' => $next['unknownRowids'],
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'currentDecodedHex' => self::fieldByRowid($currentByRowid, 'decodedHex'),
            'nextDecodedHex' => self::fieldByRowid($nextByRowid, 'decodedHex'),
            'currentTokenHex' => self::fieldByRowid($currentByRowid, 'tokenHex'),
            'nextTokenHex' => self::fieldByRowid($nextByRowid, 'tokenHex'),
            'currentTextEncoding' => self::fieldByRowid($currentByRowid, 'textEncoding'),
            'nextTextEncoding' => self::fieldByRowid($nextByRowid, 'textEncoding'),
            'currentResidualMatches' => self::fieldByRowid($currentByRowid, 'residualMatch'),
            'nextResidualMatches' => self::fieldByRowid($nextByRowid, 'residualMatch'),
            'changedDecodedRowids' => $changedDecoded,
            'changedEncodingRowids' => $changedEncoding,
            'changedResidualRowids' => $changedResidual,
            'nonAsciiEscapeIsSinglePatternCharacter' => true,
            'escapedUnderscoreAndPercentAreLiterals' => true,
            'prefixRangeUsesDecodedTextNotEncodedBytes' => true,
            'nocaseFoldsAsciiOnlyAfterUtf16Decode' => true,
            'malformedUtf16RowsDoNotEnterRange' => true,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-like-non-ascii-escape-tokenizer',
                'sqlite-utf16-decode',
                'sqlite-like-escape-prefix-range',
                'sqlite-nocase-ascii-collation',
                'sqlite-current-source-next248',
            ],
            'dependency_closure' => 'no new support component needed; reuses native LIKE tokenization, UTF-16 decode guards, escaped-prefix range planning, ASCII-only NOCASE comparison, and current-source invalidation diagnostics',
            'non_overlap' => 'next248 covers non-ASCII single-character ESCAPE handling for UTF-8/UTF-16 option_name LIKE scans; avoids accepted next245 dangling ASCII ESCAPE residuals, next242 embedded-NUL value LIKE, Unicode GLOB ranges, UTF-16 malformed insert guards, and SQL/VFS/WAL/B-tree/JSON clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array{lowerInclusive:string,upperBound:?string} $range
     * @return array{trace:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,rejected:list<array<string,mixed>>,unknownRowids:list<int>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function scanRows(array $rows, string $pattern, ?string $escape, bool $caseSensitiveLike, array $range): array
    {
        $trace = [];
        $candidates = [];
        $matched = [];
        $rejected = [];
        $unknown = [];
        $malformed = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            if (!array_key_exists('option_name_bytes', $row)) {
                throw new \InvalidArgumentException('SQLite non-ASCII ESCAPE LIKE next248 row requires option_name_bytes');
            }
            if (!isset($row['text_encoding']) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite non-ASCII ESCAPE LIKE next248 row requires integer text_encoding');
            }
            $rowid = is_int($row['option_id'] ?? null) ? $row['option_id'] : $index + 1;
            if ($row['option_name_bytes'] === null || $row['option_name_bytes'] instanceof SQLiteBlobValue) {
                $unknown[] = $rowid;
                continue;
            }
            if (!is_string($row['option_name_bytes'])) {
                throw new \InvalidArgumentException('SQLite non-ASCII ESCAPE LIKE next248 option_name_bytes must be text bytes');
            }

            try {
                $decoded = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
            } catch (\InvalidArgumentException $exception) {
                if (str_contains($exception->getMessage(), 'SQLite text encoding must be')) {
                    throw $exception;
                }
                $malformed[] = $rowid;
                $errors[$rowid] = $exception->getMessage();
                continue;
            }

            $residual = SQLiteDatabase::likeMatches($decoded, $pattern, $escape, $caseSensitiveLike);
            $entry = [
                'rowid' => $rowid,
                'decoded' => $decoded,
                'decodedHex' => bin2hex($decoded),
                'tokenHex' => self::tokenHexList($decoded),
                'textEncoding' => self::encodingName($row['text_encoding']),
                'rangeCandidate' => self::withinRange($decoded, $range, $caseSensitiveLike),
                'residualMatch' => $residual,
            ];
            $trace[] = $entry;
            if (!$entry['rangeCandidate']) {
                continue;
            }
            $candidates[] = $entry;
            if ($residual) {
                $matched[] = $entry;
            } else {
                $rejected[] = $entry;
            }
        }

        $sort = static fn (array $left, array $right): int => strcmp(self::asciiLower($left['decoded']), self::asciiLower($right['decoded'])) ?: $left['rowid'] <=> $right['rowid'];
        usort($trace, $sort);
        usort($candidates, $sort);
        usort($matched, $sort);
        usort($rejected, $sort);
        sort($unknown);
        sort($malformed);
        ksort($errors);

        return [
            'trace' => $trace,
            'candidates' => $candidates,
            'matched' => $matched,
            'rejected' => $rejected,
            'unknownRowids' => $unknown,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array{lowerInclusive:string,upperBound:?string} $range */
    private static function withinRange(string $value, array $range, bool $caseSensitiveLike): bool
    {
        $key = $caseSensitiveLike ? $value : self::asciiLower($value);
        if (strcmp($key, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0;
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
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

    /** @param array<int,array<string,mixed>> $rows @return array<int,mixed> */
    private static function fieldByRowid(array $rows, string $field): array
    {
        $values = [];
        foreach ($rows as $rowid => $row) {
            $values[$rowid] = $row[$field];
        }

        return $values;
    }

    private static function sqlitePatternLength(string $text): int
    {
        return count(self::sqlitePatternCharacters($text));
    }

    /** @return list<string> */
    private static function tokenHexList(string $text): array
    {
        return array_map('bin2hex', self::sqlitePatternCharacters($text));
    }

    /** @return list<string> */
    private static function sqlitePatternCharacters(string $text): array
    {
        if ($text === '') {
            return [];
        }
        if (preg_match('//u', $text) === 1) {
            $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
            if ($characters !== false) {
                return $characters;
            }
        }

        $characters = [];
        $length = strlen($text);
        for ($offset = 0; $offset < $length;) {
            $byte = ord($text[$offset]);
            $sequenceLength = match (true) {
                $byte < 0x80 => 1,
                $byte >= 0xc2 && $byte <= 0xdf => 2,
                $byte >= 0xe0 && $byte <= 0xef => 3,
                $byte >= 0xf0 && $byte <= 0xf4 => 4,
                default => 1,
            };
            $sequence = substr($text, $offset, $sequenceLength);
            if ($sequenceLength > 1 && strlen($sequence) === $sequenceLength && preg_match('//u', $sequence) === 1) {
                $characters[] = $sequence;
                $offset += $sequenceLength;
                continue;
            }
            $characters[] = $text[$offset];
            $offset++;
        }

        return $characters;
    }

    private static function asciiLower(string $text): string
    {
        return strtr($text, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    private static function encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }
}
