<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext215Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array{key:string,rowid:int}|null $resumeToken
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameEmbeddedNulTokenPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_cache%',
        ?string $escape = '!',
        ?array $resumeToken = ['key' => "plugin_cache\0shadow", 'rowid' => 4],
        string $currentSource = 'main.wp_options@214',
        string $nextSource = 'main.wp_options@215',
        int $currentSchemaCookie = 214,
        int $nextSchemaCookie = 215,
    ): array {
        $current = self::scan($currentRows, $pattern, $escape);
        $next = self::scan($nextRows, $pattern, $escape);
        $token = self::normalizeToken($resumeToken);

        $currentMatched = self::rowids($current['matched']);
        $nextMatched = self::rowids($next['matched']);
        $matchedExited = array_values(array_diff($currentMatched, $nextMatched));
        $matchedEntered = array_values(array_diff($nextMatched, $currentMatched));
        sort($matchedExited);
        sort($matchedEntered);

        $currentBefore = self::beforeOrAtToken($current['candidates'], $token);
        $nextBefore = self::beforeOrAtToken($next['candidates'], $token);
        $currentAfter = self::afterToken($current['candidates'], $token);
        $nextAfter = self::afterToken($next['candidates'], $token);
        $currentMatchedBefore = self::beforeOrAtToken($current['matched'], $token);
        $nextMatchedBefore = self::beforeOrAtToken($next['matched'], $token);
        $truncatedCollisions = self::truncatedKeyCollisions($current['decoded'], $next['decoded']);

        $unsafe = [];
        if ($currentSource !== $nextSource || $currentSchemaCookie !== $nextSchemaCookie) {
            $unsafe[] = 'source-or-schema-changed';
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $unsafe[] = 'malformed-text';
        }
        if (self::rowids($currentBefore) !== self::rowids($nextBefore)) {
            $unsafe[] = 'candidate-before-token-changed';
        }
        if (self::rowids($currentMatchedBefore) !== self::rowids($nextMatchedBefore)) {
            $unsafe[] = 'matched-before-token-changed';
        }
        if ($truncatedCollisions !== []) {
            $unsafe[] = 'embedded-nul-truncated-key-collision';
        }
        if (($token['normalizationReasons'] ?? []) !== []) {
            $unsafe[] = 'yield-token-not-canonical';
        }
        $unsafe = array_values(array_unique($unsafe));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-next215',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* embedded NUL token fence */',
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => 'NOCASE',
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'prefix' => $current['likePlan']['prefix'],
            'rangeLowerInclusive' => $current['likePlan']['range']['lowerInclusive'] ?? null,
            'rangeUpperBound' => $current['likePlan']['range']['upperBound'] ?? null,
            'currentCandidateRowids' => self::rowids($current['candidates']),
            'nextCandidateRowids' => self::rowids($next['candidates']),
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'matchedExitedRowids' => $matchedExited,
            'matchedEnteredRowids' => $matchedEntered,
            'currentFalsePositiveRowids' => self::rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::rowids($next['falsePositive']),
            'currentCandidateBeforeOrAtTokenRowids' => self::rowids($currentBefore),
            'nextCandidateBeforeOrAtTokenRowids' => self::rowids($nextBefore),
            'currentReplayAfterTokenRowids' => self::rowids($currentAfter),
            'nextReplayAfterTokenRowids' => self::rowids($nextAfter),
            'currentMatchedBeforeTokenRowids' => self::rowids($currentMatchedBefore),
            'nextMatchedBeforeTokenRowids' => self::rowids($nextMatchedBefore),
            'currentEmbeddedNulRowids' => $current['embeddedNulRowids'],
            'nextEmbeddedNulRowids' => $next['embeddedNulRowids'],
            'embeddedNulTruncatedKeyCollisionRowids' => $truncatedCollisions,
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'currentRtrimTexts' => self::map($current['decoded'], 'rtrimText'),
            'nextRtrimTexts' => self::map($next['decoded'], 'rtrimText'),
            'currentNocaseKeysHex' => self::map($current['decoded'], 'nocaseKeyHex'),
            'nextNocaseKeysHex' => self::map($next['decoded'], 'nocaseKeyHex'),
            'currentTruncatedNocaseKeys' => self::map($current['decoded'], 'truncatedNocaseKey'),
            'nextTruncatedNocaseKeys' => self::map($next['decoded'], 'truncatedNocaseKey'),
            'resumeToken' => $token,
            'candidateTokenUnsafeReasons' => $unsafe,
            'candidateTokenResumeSafe' => $unsafe === [],
            'mustReprepareBeforeCandidateTokenResume' => $unsafe !== [],
            'replayPlanMode' => $unsafe === [] ? 'continue-after-embedded-nul-safe-token' : 'reprepare-from-range-start',
            'replayPlanRowids' => $unsafe === [] ? self::rowids($nextAfter) : self::rowids($next['candidates']),
            'embeddedNulPreservedInTextKeys' => true,
            'embeddedNulNotCStringTerminator' => true,
            'likeResidualChecksFullSqlText' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-embedded-nul-text-token',
                'sqlite-current-source-next215',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, NOCASE LIKE range planning, RTRIM expression keys, and current-source token replay diagnostics while preserving embedded NUL bytes as SQLite text',
            'non_overlap' => 'next215 covers embedded-NUL UTF-16 RTRIM/NOCASE LIKE current-source replay token fencing; avoids accepted Unicode GLOB ranges, UTF-16 malformed insert guards, ESCAPE/rtrim rebind slices, JSON/VFS/WAL/B-tree clusters, and storage durability work',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{likePlan:array<string,mixed>,decoded:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,embeddedNulRowids:list<int>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function scan(array $rows, string $pattern, ?string $escape): array
    {
        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $decoded = [];
        $malformed = [];
        $errors = [];
        $embedded = [];

        foreach ($rows as $row) {
            self::assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
                $rtrim = rtrim($text, ' ');
                $key = self::asciiLower($rtrim);
                if (str_contains($key, "\0")) {
                    $embedded[] = $row['option_id'];
                }
                $decoded[] = [
                    'rowid' => $row['option_id'],
                    'text' => $text,
                    'rtrimText' => $rtrim,
                    'nocaseKey' => $key,
                    'nocaseKeyHex' => bin2hex($key),
                    'truncatedNocaseKey' => self::truncateAtNul($key),
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['option_id'];
                $errors[$row['option_id']] = $exception->getMessage();
            }
        }

        usort($decoded, self::sortRows(...));
        sort($embedded);
        sort($malformed);
        ksort($errors);

        $candidates = [];
        $matched = [];
        $falsePositive = [];
        foreach ($decoded as $entry) {
            if (!self::inRange($entry['nocaseKey'], $like['range'])) {
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
            'likePlan' => $like,
            'decoded' => $decoded,
            'candidates' => $candidates,
            'matched' => $matched,
            'falsePositive' => $falsePositive,
            'embeddedNulRowids' => $embedded,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row */
    private static function assertRow(array $row): void
    {
        if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next215 rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next215 rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next215 rows require integer text_encoding');
        }
    }

    /** @param array{key:string,rowid:int}|null $token @return array{key:string,rowid:int,normalizationReasons:list<string>}|null */
    private static function normalizeToken(?array $token): ?array
    {
        if ($token === null) {
            return null;
        }
        $key = self::asciiLower(rtrim($token['key'], ' '));
        $reasons = [];
        if ($key !== $token['key']) {
            $reasons[] = 'token-key-not-canonical';
        }

        return ['key' => $key, 'rowid' => $token['rowid'], 'normalizationReasons' => $reasons];
    }

    /** @param list<array<string,mixed>> $rows @param array{key:string,rowid:int,normalizationReasons:list<string>}|null $token @return list<array<string,mixed>> */
    private static function beforeOrAtToken(array $rows, ?array $token): array
    {
        if ($token === null) {
            return [];
        }

        return array_values(array_filter($rows, static fn (array $row): bool => self::compareToken($row, $token) <= 0));
    }

    /** @param list<array<string,mixed>> $rows @param array{key:string,rowid:int,normalizationReasons:list<string>}|null $token @return list<array<string,mixed>> */
    private static function afterToken(array $rows, ?array $token): array
    {
        if ($token === null) {
            return $rows;
        }

        return array_values(array_filter($rows, static fn (array $row): bool => self::compareToken($row, $token) > 0));
    }

    /** @param array<string,mixed> $row @param array{key:string,rowid:int,normalizationReasons:list<string>} $token */
    private static function compareToken(array $row, array $token): int
    {
        $comparison = strcmp($row['nocaseKey'], $token['key']);

        return $comparison !== 0 ? $comparison : $row['rowid'] <=> $token['rowid'];
    }

    /** @param list<array<string,mixed>> $current @param list<array<string,mixed>> $next @return list<int> */
    private static function truncatedKeyCollisions(array $current, array $next): array
    {
        $byTruncated = [];
        foreach (array_merge($current, $next) as $row) {
            if ($row['truncatedNocaseKey'] === $row['nocaseKey']) {
                continue;
            }
            $byTruncated[$row['truncatedNocaseKey']][] = $row['rowid'];
        }

        $collisions = [];
        foreach ($byTruncated as $rowids) {
            if (count(array_unique($rowids)) > 1) {
                array_push($collisions, ...$rowids);
            }
        }
        $collisions = array_values(array_unique($collisions));
        sort($collisions);

        return $collisions;
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

    private static function truncateAtNul(string $value): string
    {
        $position = strpos($value, "\0");

        return $position === false ? $value : substr($value, 0, $position);
    }

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }
}
