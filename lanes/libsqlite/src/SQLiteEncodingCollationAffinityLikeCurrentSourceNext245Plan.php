<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteEncodingCollationAffinityLikeCurrentSourceNext245Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressDanglingEscapeLikePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_cache!',
        ?string $escape = '!',
        bool $caseSensitiveLike = false,
        string $currentSource = 'main.wp_options@244',
        string $nextSource = 'main.wp_options@245',
        int $currentSchemaCookie = 244,
        int $nextSchemaCookie = 245,
    ): array {
        if ($escape !== null && self::sqlitePatternLength($escape) !== 1) {
            throw new \InvalidArgumentException('SQLite dangling-escape LIKE next245 ESCAPE must be one SQLite pattern character');
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
        $retainedCandidates = array_values(array_intersect($currentCandidates, $nextCandidates));
        $exitedCandidates = array_values(array_diff($currentCandidates, $nextCandidates));
        $enteredCandidates = array_values(array_diff($nextCandidates, $currentCandidates));
        $changedBytes = [];
        $changedStorage = [];
        $currentByRowid = self::rowsByRowid($current['candidates']);
        $nextByRowid = self::rowsByRowid($next['candidates']);
        foreach ($retainedCandidates as $rowid) {
            if (($currentByRowid[$rowid]['nameHex'] ?? null) !== ($nextByRowid[$rowid]['nameHex'] ?? null)) {
                $changedBytes[] = $rowid;
            }
            if (($currentByRowid[$rowid]['storage'] ?? null) !== ($nextByRowid[$rowid]['storage'] ?? null)) {
                $changedStorage[] = $rowid;
            }
        }
        sort($changedBytes);
        sort($changedStorage);

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
        if ($enteredCandidates !== [] || $exitedCandidates !== []) {
            $reasons[] = 'candidate-rowset';
        }
        if ($changedBytes !== []) {
            $reasons[] = 'option-name-bytes';
        }
        if ($changedStorage !== []) {
            $reasons[] = 'storage-class';
        }
        if ($currentRejected !== [] || $nextRejected !== []) {
            $reasons[] = 'dangling-escape-residual';
        }

        return [
            'status' => 'encoding-collation-affinity-like-current-source-next245',
            'operator' => 'LIKE',
            'expression' => 'option_name COLLATE ' . ($caseSensitiveLike ? 'BINARY' : 'NOCASE') . ' LIKE ? ESCAPE ? /* dangling ESCAPE residual */',
            'pattern' => $pattern,
            'patternHex' => bin2hex($pattern),
            'patternTokenHex' => self::tokenHexList($pattern),
            'patternCharacters' => self::sqlitePatternLength($pattern),
            'escape' => $escape,
            'escapeHex' => $escape === null ? null : bin2hex($escape),
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
            'currentCandidateRowids' => $currentCandidates,
            'nextCandidateRowids' => $nextCandidates,
            'retainedCandidateRowids' => $retainedCandidates,
            'exitedCandidateRowids' => $exitedCandidates,
            'enteredCandidateRowids' => $enteredCandidates,
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'currentResidualRejectedRowids' => $currentRejected,
            'nextResidualRejectedRowids' => $nextRejected,
            'changedNameBytesRowids' => $changedBytes,
            'changedStorageRowids' => $changedStorage,
            'currentUnknownRowids' => $current['unknownRowids'],
            'nextUnknownRowids' => $next['unknownRowids'],
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentMalformedHex' => $current['malformedHex'],
            'nextMalformedHex' => $next['malformedHex'],
            'currentNames' => self::fieldByRowid($currentByRowid, 'name'),
            'nextNames' => self::fieldByRowid($nextByRowid, 'name'),
            'currentNameHex' => self::fieldByRowid($currentByRowid, 'nameHex'),
            'nextNameHex' => self::fieldByRowid($nextByRowid, 'nameHex'),
            'currentTokenHex' => self::fieldByRowid($currentByRowid, 'tokenHex'),
            'nextTokenHex' => self::fieldByRowid($nextByRowid, 'tokenHex'),
            'currentTokenCounts' => self::fieldByRowid($currentByRowid, 'tokenCount'),
            'nextTokenCounts' => self::fieldByRowid($nextByRowid, 'tokenCount'),
            'currentStorage' => self::fieldByRowid($currentByRowid, 'storage'),
            'nextStorage' => self::fieldByRowid($nextByRowid, 'storage'),
            'danglingEscapeMakesResidualFalse' => true,
            'rangeMayAdmitResidualRejectedRows' => true,
            'escapedUnderscoreIsPrefixLiteral' => true,
            'nocaseFoldsAsciiOnly' => true,
            'blobAndNullRemainUnknown' => true,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-like-dangling-escape-residual',
                'sqlite-like-escape-prefix-range',
                'sqlite-text-affinity',
                'sqlite-current-source-next245',
            ],
            'dependency_closure' => 'no new support component needed; reuses native LIKE tokenization, ESCAPE prefix planning, text-affinity coercion, and current-source invalidation diagnostics',
            'non_overlap' => 'next245 covers dangling ESCAPE LIKE residual rejection after prefix range admission; avoids accepted next242 embedded-NUL value LIKE, next241 byte-aware option_name LIKE, Unicode GLOB ranges, UTF-16 malformed guards, and SQL/VFS/WAL/B-tree/JSON clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array{lowerInclusive:string,upperBound:?string} $range
     * @return array{candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,rejected:list<array<string,mixed>>,unknownRowids:list<int>,malformedRowids:list<int>,malformedHex:array<int,string>}
     */
    private static function scanRows(array $rows, string $pattern, ?string $escape, bool $caseSensitiveLike, array $range): array
    {
        $candidates = [];
        $matched = [];
        $rejected = [];
        $unknown = [];
        $malformedRowids = [];
        $malformedHex = [];

        foreach ($rows as $index => $row) {
            if (!array_key_exists('option_name', $row)) {
                throw new \InvalidArgumentException('SQLite dangling-escape LIKE next245 row requires option_name');
            }
            $rowid = is_int($row['option_id'] ?? null) ? $row['option_id'] : $index + 1;
            $coerced = self::coerceText($row['option_name']);
            if ($coerced === null) {
                $unknown[] = $rowid;
                continue;
            }
            [$name, $storage] = $coerced;
            if (preg_match('//u', $name) !== 1) {
                $malformedRowids[] = $rowid;
                $malformedHex[$rowid] = bin2hex($name);
            }
            if (!self::withinRange($name, $range, $caseSensitiveLike)) {
                continue;
            }
            $entry = [
                'rowid' => $rowid,
                'name' => $name,
                'nameHex' => bin2hex($name),
                'tokenHex' => self::tokenHexList($name),
                'tokenCount' => self::sqlitePatternLength($name),
                'storage' => $storage,
            ];
            $candidates[] = $entry;
            if (SQLiteDatabase::likeMatches($name, $pattern, $escape, $caseSensitiveLike)) {
                $matched[] = $entry;
            } else {
                $rejected[] = $entry;
            }
        }

        $sort = static fn (array $left, array $right): int => strcmp(self::asciiLower($left['name']), self::asciiLower($right['name'])) ?: $left['rowid'] <=> $right['rowid'];
        usort($candidates, $sort);
        usort($matched, $sort);
        usort($rejected, $sort);
        sort($unknown);
        sort($malformedRowids);
        ksort($malformedHex);

        return [
            'candidates' => $candidates,
            'matched' => $matched,
            'rejected' => $rejected,
            'unknownRowids' => $unknown,
            'malformedRowids' => $malformedRowids,
            'malformedHex' => $malformedHex,
        ];
    }

    /** @return null|array{0:string,1:string} */
    private static function coerceText(mixed $value): ?array
    {
        if ($value === null || $value instanceof SQLiteBlobValue) {
            return null;
        }
        if (is_string($value)) {
            return [$value, 'text'];
        }
        if (is_int($value)) {
            return [(string) $value, 'integer'];
        }
        if (is_float($value)) {
            return [rtrim(rtrim(sprintf('%.15G', $value), '0'), '.'), 'real'];
        }
        if (is_bool($value)) {
            return [$value ? '1' : '0', 'integer'];
        }

        throw new \InvalidArgumentException('SQLite dangling-escape LIKE next245 option_name must be scalar text-affinity input');
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
}
