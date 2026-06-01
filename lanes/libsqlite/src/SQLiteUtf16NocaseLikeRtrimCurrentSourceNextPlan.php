<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

require_once __DIR__ . '/SQLiteUtf16NoCaseLikeRtrimCurrentSourceNextPlan.php';

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan
{
    public static function keyValueRowKeyNoCasePlan(mixed ...$args): array
    {
        return SQLiteUtf16NoCaseLikeRtrimCurrentSourceNextBasicImpl::keyValueRowKeyPlan(...$args);
    }

    public static function keyValueRowKeyUtf16NocaseRtrimPlan(mixed ...$args): array
    {
        return self::keyValueRowKeyNoCasePlan(...$args);
    }

    public static function keyValueRowKeyNormalizedPatternPlan(mixed ...$args): array
    {
        return SQLiteUtf16NoCaseLikeRtrimCurrentSourceNextNormalizedPatternImpl::keyValueRowKeyNormalizedPatternPlan(...$args);
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyPlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        string $currentSource = 'main.app_settings@156',
        string $nextSource = 'main.app_settings@157',
        int $currentSchemaCookie = 156,
        int $nextSchemaCookie = 157,
    ): array {
        self::assertUtf16ByteOrderRows($currentRows);
        self::assertUtf16ByteOrderRows($nextRows);

        $plan = SQLiteNocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escape,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $currentByteOrders = self::byteOrdersByRowid($plan['currentDecoded']);
        $nextByteOrders = self::byteOrdersByRowid($plan['nextDecoded']);
        $changedByteOrders = self::changedByteOrderRowids($currentByteOrders, $nextByteOrders);

        $reasons = $plan['invalidationReasons'];
        if ($changedByteOrders !== [] && !in_array('utf16-byte-order', $reasons, true)) {
            $reasons[] = 'utf16-byte-order';
        }

        $plan['expression'] = 'rtrim(option_name) COLLATE NOCASE /* UTF-16 source */';
        $plan['sourceTextEncoding'] = 'UTF-16';
        $plan['acceptedTextEncodings'] = ['UTF-16LE', 'UTF-16BE'];
        $plan['utf16ByteOrderSensitive'] = true;
        $plan['currentByteOrders'] = $currentByteOrders;
        $plan['nextByteOrders'] = $nextByteOrders;
        $plan['changedByteOrderRowids'] = $changedByteOrders;
        $plan['cursorInvalidated'] = $reasons !== [];
        $plan['cursorReusable'] = $reasons === [] && $plan['rangeUsable'];
        $plan['invalidationReasons'] = $reasons;
        $plan['dependencies'] = [
            'sqlite-utf16-source-decode',
            'sqlite-like-nocase-prefix-range',
            'sqlite-rtrim-expression-index',
            'sqlite-current-source-nextoneFiveSeven',
        ];
        $plan['dependency_closure'] = 'no new support component needed; nextOneFiveSeven composes native UTF-16 decode validation, ASCII NOCASE LIKE prefix planning, RTRIM index-key normalization, and current-source cursor invalidation';
        $plan['non_overlap'] = 'avoids accepted generic NOCASE/RTRIM LIKE nextOneFourSix and UTF-16 RTRIM/NOCASE/GLOB slices by requiring UTF-16-only source rows and asserting byte-order invalidation for NOCASE LIKE over RTRIM keys';

        return $plan;
    }

    /** @param list<array<string,mixed>> $rows */
    private static function assertUtf16ByteOrderRows(array $rows): void
    {
        foreach ($rows as $row) {
            if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneFiveSeven rows require integer text_encoding');
            }
            if (!in_array($row['text_encoding'], [2, 3], true)) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneFiveSeven rows must use UTF-16LE or UTF-16BE text_encoding');
            }
        }
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int,string>
     */
    private static function byteOrdersByRowid(array $rows): array
    {
        $orders = [];
        foreach ($rows as $row) {
            $orders[$row['rowid']] = $row['encoding'] === 'UTF-16LE' ? 'little' : 'big';
        }

        return $orders;
    }

    /**
     * @param array<int,string> $current
     * @param array<int,string> $next
     * @return list<int>
     */
    private static function changedByteOrderRowids(array $current, array $next): array
    {
        $changed = [];
        foreach ($next as $rowid => $order) {
            if (!isset($current[$rowid]) || $current[$rowid] !== $order) {
                $changed[] = $rowid;
            }
        }
        foreach ($current as $rowid => $_order) {
            if (!isset($next[$rowid])) {
                $changed[] = $rowid;
            }
        }
        $changed = array_values(array_unique($changed));
        sort($changed);

        return $changed;
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeySourceDeltaPlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        string $currentSource = 'main.app_settings',
        string $nextSource = 'main.app_settings@158',
        int $currentSchemaCookie = 157,
        int $nextSchemaCookie = 158,
    ): array {
        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $current = self::sourceDeltaScan($currentRows, $pattern, $escape, $like['range']);
        $next = self::sourceDeltaScan($nextRows, $pattern, $escape, $like['range']);
        $currentCandidates = self::sourceDeltaRowids($current['candidates']);
        $nextCandidates = self::sourceDeltaRowids($next['candidates']);
        $currentMatched = self::sourceDeltaRowids($current['matched']);
        $nextMatched = self::sourceDeltaRowids($next['matched']);
        $changes = self::sourceDeltaChanges($current['decoded'], $next['decoded']);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if (!$like['indexUsable']) {
            $reasons[] = 'no-nocase-prefix-range';
        }
        foreach ([
            'decoded-text' => $changes['textChangedRowids'],
            'rtrim-expression' => $changes['rtrimChangedRowids'],
            'nocase-key' => $changes['nocaseKeyChangedRowids'],
            'text-encoding' => $changes['encodingChangedRowids'],
            'encoded-bytes' => $changes['bytesChangedRowids'],
            'residual-result' => $changes['matchChangedRowids'],
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

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nextoneFiveEight',
            'operator' => 'LIKE',
            'expression' => 'rtrim(key_name) COLLATE NOCASE',
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => 'NOCASE',
            'caseSensitiveLike' => false,
            'prefix' => $like['prefix'],
            'prefixCharacters' => $like['prefixCharacters'],
            'prefixIsAscii' => $like['prefixIsAscii'],
            'indexUsable' => $like['indexUsable'],
            'rejectedReason' => $like['rejectedReason'],
            'range' => $like['range'],
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentOrderRowids' => self::sourceDeltaRowids($current['decoded']),
            'nextOrderRowids' => self::sourceDeltaRowids($next['decoded']),
            'currentCandidateRowids' => $currentCandidates,
            'nextCandidateRowids' => $nextCandidates,
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'currentFalsePositiveRowids' => self::sourceDeltaRowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::sourceDeltaRowids($next['falsePositive']),
            'retainedMatchedRowids' => array_values(array_intersect($currentMatched, $nextMatched)),
            'enteredMatchedRowids' => array_values(array_diff($nextMatched, $currentMatched)),
            'exitedMatchedRowids' => array_values(array_diff($currentMatched, $nextMatched)),
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'currentTexts' => self::sourceDeltaMap($current['decoded'], 'text'),
            'nextTexts' => self::sourceDeltaMap($next['decoded'], 'text'),
            'currentRtrimTexts' => self::sourceDeltaMap($current['decoded'], 'rtrimText'),
            'nextRtrimTexts' => self::sourceDeltaMap($next['decoded'], 'rtrimText'),
            'currentNocaseKeys' => self::sourceDeltaMap($current['decoded'], 'nocaseKey'),
            'nextNocaseKeys' => self::sourceDeltaMap($next['decoded'], 'nocaseKey'),
            'currentEncodings' => self::sourceDeltaMap($current['decoded'], 'encoding'),
            'nextEncodings' => self::sourceDeltaMap($next['decoded'], 'encoding'),
            'currentBytesHex' => self::sourceDeltaMap($current['decoded'], 'bytesHex'),
            'nextBytesHex' => self::sourceDeltaMap($next['decoded'], 'bytesHex'),
            'currentResidualMatches' => self::sourceDeltaMap($current['candidates'], 'residualMatch'),
            'nextResidualMatches' => self::sourceDeltaMap($next['candidates'], 'residualMatch'),
            'changedTextRowids' => $changes['textChangedRowids'],
            'changedRtrimRowids' => $changes['rtrimChangedRowids'],
            'changedNocaseKeyRowids' => $changes['nocaseKeyChangedRowids'],
            'changedEncodingRowids' => $changes['encodingChangedRowids'],
            'changedBytesRowids' => $changes['bytesChangedRowids'],
            'changedResidualRowids' => $changes['matchChangedRowids'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-rtrim-expression',
                'sqlite-like-nocase-prefix-range',
                'sqlite-current-source-nextoneFiveEight',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, RTRIM expression keys, ASCII NOCASE LIKE prefix planning, and current-source invalidation metadata',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{decoded:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function sourceDeltaScan(array $rows, string $pattern, ?string $escape, ?array $range): array
    {
        $decoded = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            self::assertSourceDeltaRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['key_name_bytes'], $row['text_encoding']);
                $rtrim = rtrim($text, ' ');
                $decoded[] = [
                    'rowid' => $row['setting_id'],
                    'text' => $text,
                    'rtrimText' => $rtrim,
                    'nocaseKey' => self::asciiLowerSourceDeltaKey($rtrim),
                    'encoding' => self::sourceDeltaEncodingName($row['text_encoding']),
                    'bytesHex' => bin2hex($row['key_name_bytes']),
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['setting_id'];
                $errors[$row['setting_id']] = $exception->getMessage();
            }
        }

        usort($decoded, self::compareSourceDeltaRows(...));
        sort($malformed);
        ksort($errors);

        $candidates = [];
        $matched = [];
        $falsePositive = [];
        foreach ($decoded as $entry) {
            if (!self::sourceDeltaKeyInRange($entry['nocaseKey'], $range)) {
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
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row */
    private static function assertSourceDeltaRow(array $row): void
    {
        if (!array_key_exists('setting_id', $row) || !is_int($row['setting_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM current-source nextOneFiveEight rows require integer setting_id');
        }
        if (!array_key_exists('key_name_bytes', $row) || !is_string($row['key_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM current-source nextOneFiveEight rows require key_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM current-source nextOneFiveEight rows require integer text_encoding');
        }
    }

    /** @param ?array{lowerInclusive:string,upperBound:?string} $range */
    private static function sourceDeltaKeyInRange(string $key, ?array $range): bool
    {
        if ($range === null || strcmp($key, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0;
    }

    /** @param array{nocaseKey:string,rowid:int} $left @param array{nocaseKey:string,rowid:int} $right */
    private static function compareSourceDeltaRows(array $left, array $right): int
    {
        $comparison = strcmp($left['nocaseKey'], $right['nocaseKey']);

        return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
    }

    /** @param list<array{rowid:int}> $rows @return list<int> */
    private static function sourceDeltaRowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $rows @return array<int,mixed> */
    private static function sourceDeltaMap(array $rows, string $key): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[$row['rowid']] = $row[$key];
        }

        return $mapped;
    }

    /**
     * @param list<array{rowid:int,text:string,rtrimText:string,nocaseKey:string,encoding:string,bytesHex:string,residualMatch?:bool}> $currentRows
     * @param list<array{rowid:int,text:string,rtrimText:string,nocaseKey:string,encoding:string,bytesHex:string,residualMatch?:bool}> $nextRows
     * @return array{textChangedRowids:list<int>,rtrimChangedRowids:list<int>,nocaseKeyChangedRowids:list<int>,encodingChangedRowids:list<int>,bytesChangedRowids:list<int>,matchChangedRowids:list<int>}
     */
    private static function sourceDeltaChanges(array $currentRows, array $nextRows): array
    {
        $current = [];
        foreach ($currentRows as $row) {
            $current[$row['rowid']] = $row;
        }

        $changes = [
            'textChangedRowids' => [],
            'rtrimChangedRowids' => [],
            'nocaseKeyChangedRowids' => [],
            'encodingChangedRowids' => [],
            'bytesChangedRowids' => [],
            'matchChangedRowids' => [],
        ];
        foreach ($nextRows as $row) {
            $rowid = $row['rowid'];
            if (!isset($current[$rowid])) {
                continue;
            }
            if ($current[$rowid]['text'] !== $row['text']) {
                $changes['textChangedRowids'][] = $rowid;
            }
            if ($current[$rowid]['rtrimText'] !== $row['rtrimText']) {
                $changes['rtrimChangedRowids'][] = $rowid;
            }
            if ($current[$rowid]['nocaseKey'] !== $row['nocaseKey']) {
                $changes['nocaseKeyChangedRowids'][] = $rowid;
            }
            if ($current[$rowid]['encoding'] !== $row['encoding']) {
                $changes['encodingChangedRowids'][] = $rowid;
            }
            if ($current[$rowid]['bytesHex'] !== $row['bytesHex']) {
                $changes['bytesChangedRowids'][] = $rowid;
            }
            if (($current[$rowid]['residualMatch'] ?? null) !== ($row['residualMatch'] ?? null)) {
                $changes['matchChangedRowids'][] = $rowid;
            }
        }
        foreach ($changes as &$rowids) {
            sort($rowids);
        }

        return $changes;
    }

    private static function sourceDeltaEncodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function asciiLowerSourceDeltaKey(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyGenerationPlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        string $currentSource = 'main.app_settings',
        string $nextSource = 'main.app_settings@161',
        int $currentSchemaCookie = 160,
        int $nextSchemaCookie = 161,
        string $currentCollationGeneration = 'NOCASE/RTRIM@160',
        string $nextCollationGeneration = 'NOCASE/RTRIM@161',
        string $currentLikeGeneration = 'like@160',
        string $nextLikeGeneration = 'like@161',
    ): array {
        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeySourceDeltaPlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escape,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $retained = array_values(array_intersect($base['currentMatchedRowids'], $base['nextMatchedRowids']));
        $retainedChanges = self::v161_retainedChanges($retained, $base);
        $reasons = $base['invalidationReasons'];

        if ($currentCollationGeneration !== $nextCollationGeneration) {
            $reasons[] = 'collation-generation';
        }
        if ($currentLikeGeneration !== $nextLikeGeneration) {
            $reasons[] = 'like-generation';
        }
        foreach ([
            'retained-rtrim-key' => $retainedChanges['rtrim'],
            'retained-nocase-key' => $retainedChanges['nocase'],
            'retained-encoding' => $retainedChanges['encoding'],
            'retained-bytes' => $retainedChanges['bytes'],
        ] as $reason => $rowids) {
            if ($rowids !== [] && !in_array($reason, $reasons, true)) {
                $reasons[] = $reason;
            }
        }

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nextoneSixOne',
            'operator' => 'LIKE',
            'expression' => 'rtrim(key_name) COLLATE NOCASE',
            'pattern' => $pattern,
            'escape' => $escape,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentCollationGeneration' => $currentCollationGeneration,
            'nextCollationGeneration' => $nextCollationGeneration,
            'currentLikeGeneration' => $currentLikeGeneration,
            'nextLikeGeneration' => $nextLikeGeneration,
            'baseStatus' => $base['status'],
            'indexUsable' => $base['indexUsable'],
            'prefix' => $base['prefix'],
            'range' => $base['range'],
            'currentOrderRowids' => $base['currentOrderRowids'],
            'nextOrderRowids' => $base['nextOrderRowids'],
            'currentCandidateRowids' => $base['currentCandidateRowids'],
            'nextCandidateRowids' => $base['nextCandidateRowids'],
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'retainedMatchedRowids' => $retained,
            'enteredMatchedRowids' => $base['enteredMatchedRowids'],
            'exitedMatchedRowids' => $base['exitedMatchedRowids'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'currentErrors' => $base['currentErrors'],
            'nextErrors' => $base['nextErrors'],
            'currentRtrimTexts' => $base['currentRtrimTexts'],
            'nextRtrimTexts' => $base['nextRtrimTexts'],
            'currentNocaseKeys' => $base['currentNocaseKeys'],
            'nextNocaseKeys' => $base['nextNocaseKeys'],
            'currentEncodings' => $base['currentEncodings'],
            'nextEncodings' => $base['nextEncodings'],
            'currentBytesHex' => $base['currentBytesHex'],
            'nextBytesHex' => $base['nextBytesHex'],
            'retainedChangedRtrimRowids' => $retainedChanges['rtrim'],
            'retainedChangedNocaseRowids' => $retainedChanges['nocase'],
            'retainedChangedEncodingRowids' => $retainedChanges['encoding'],
            'retainedChangedBytesRowids' => $retainedChanges['bytes'],
            'sameSourceToken' => $currentSource === $nextSource && $currentSchemaCookie === $nextSchemaCookie,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [] && $base['cursorReusable'],
            'invalidationReasons' => array_values(array_unique($reasons)),
            'currentSourceMayReuseStatement' => false,
            'reprepareRequired' => $reasons !== [],
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-rtrim-expression',
                'sqlite-like-nocase-prefix-range',
                'sqlite-collation-generation',
                'sqlite-current-source-nextoneSixOne',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 row decode, RTRIM/NOCASE LIKE current-source scans, and adds statement invalidation metadata for collation/LIKE generation changes',
        ];
    }

    /**
     * @param list<int> $retained
     * @param array<string,mixed> $base
     * @return array{rtrim:list<int>,nocase:list<int>,encoding:list<int>,bytes:list<int>}
     */
    private static function v161_retainedChanges(array $retained, array $base): array
    {
        $changes = ['rtrim' => [], 'nocase' => [], 'encoding' => [], 'bytes' => []];
        foreach ($retained as $rowid) {
            if (($base['currentRtrimTexts'][$rowid] ?? null) !== ($base['nextRtrimTexts'][$rowid] ?? null)) {
                $changes['rtrim'][] = $rowid;
            }
            if (($base['currentNocaseKeys'][$rowid] ?? null) !== ($base['nextNocaseKeys'][$rowid] ?? null)) {
                $changes['nocase'][] = $rowid;
            }
            if (($base['currentEncodings'][$rowid] ?? null) !== ($base['nextEncodings'][$rowid] ?? null)) {
                $changes['encoding'][] = $rowid;
            }
            if (($base['currentBytesHex'][$rowid] ?? null) !== ($base['nextBytesHex'][$rowid] ?? null)) {
                $changes['bytes'][] = $rowid;
            }
        }

        return $changes;
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyYieldPlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        string $currentSource = 'main.app_settings@163',
        string $nextSource = 'main.app_settings@164',
        int $currentSchemaCookie = 163,
        int $nextSchemaCookie = 164,
        string $currentCollationGeneration = 'NOCASE/RTRIM@163',
        string $nextCollationGeneration = 'NOCASE/RTRIM@164',
        string $currentLikeGeneration = 'like@163',
        string $nextLikeGeneration = 'like@164',
        string $currentPreparedStatement = 'select-rtrim-nocase-like@163',
        string $nextPreparedStatement = 'select-rtrim-nocase-like@164',
    ): array {
        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyGenerationPlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escape,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
            $currentCollationGeneration,
            $nextCollationGeneration,
            $currentLikeGeneration,
            $nextLikeGeneration,
        );

        $retainedCandidates = array_values(array_intersect($base['currentCandidateRowids'], $base['nextCandidateRowids']));
        $retainedMatched = $base['retainedMatchedRowids'];
        $stableRows = [];
        $recheckRows = [];
        $resumeKeys = [];

        foreach ($retainedCandidates as $rowid) {
            $stable = self::v164_same($base, 'currentRtrimTexts', 'nextRtrimTexts', $rowid)
                && self::v164_same($base, 'currentNocaseKeys', 'nextNocaseKeys', $rowid)
                && self::v164_same($base, 'currentEncodings', 'nextEncodings', $rowid)
                && self::v164_same($base, 'currentBytesHex', 'nextBytesHex', $rowid);

            if ($stable) {
                $stableRows[] = $rowid;
            } else {
                $recheckRows[] = $rowid;
            }

            $resumeKeys[$rowid] = self::v164_rowFingerprint($base, $rowid);
        }

        $rangeFingerprint = self::v164_rangeFingerprint($base['range']);
        $currentStatementFingerprint = self::v164_statementFingerprint(
            $currentPreparedStatement,
            $currentSource,
            $currentSchemaCookie,
            $currentCollationGeneration,
            $currentLikeGeneration,
            $rangeFingerprint,
        );
        $nextStatementFingerprint = self::v164_statementFingerprint(
            $nextPreparedStatement,
            $nextSource,
            $nextSchemaCookie,
            $nextCollationGeneration,
            $nextLikeGeneration,
            $rangeFingerprint,
        );

        $reasons = $base['invalidationReasons'];
        if ($currentPreparedStatement !== $nextPreparedStatement) {
            $reasons[] = 'prepared-statement-token';
        }
        if ($currentStatementFingerprint !== $nextStatementFingerprint) {
            $reasons[] = 'statement-fingerprint';
        }
        if ($recheckRows !== []) {
            $reasons[] = 'yield-retained-row-recheck';
        }
        if ($base['currentCandidateRowids'] !== $base['nextCandidateRowids']) {
            $reasons[] = 'yield-candidate-position';
        }
        if ($base['currentMatchedRowids'] !== $base['nextMatchedRowids']) {
            $reasons[] = 'yield-output-position';
        }

        $resumeSafe = $reasons === []
            && $base['cursorReusable'] === true
            && $stableRows === $retainedCandidates
            && $base['currentMalformedRowids'] === []
            && $base['nextMalformedRowids'] === [];

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nextoneSixFour',
            'operator' => 'LIKE',
            'expression' => 'rtrim(key_name) COLLATE NOCASE',
            'pattern' => $pattern,
            'escape' => $escape,
            'baseStatus' => $base['status'],
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentCollationGeneration' => $currentCollationGeneration,
            'nextCollationGeneration' => $nextCollationGeneration,
            'currentLikeGeneration' => $currentLikeGeneration,
            'nextLikeGeneration' => $nextLikeGeneration,
            'currentPreparedStatement' => $currentPreparedStatement,
            'nextPreparedStatement' => $nextPreparedStatement,
            'range' => $base['range'],
            'rangeFingerprint' => $rangeFingerprint,
            'currentStatementFingerprint' => $currentStatementFingerprint,
            'nextStatementFingerprint' => $nextStatementFingerprint,
            'indexUsable' => $base['indexUsable'],
            'prefix' => $base['prefix'],
            'currentCandidateRowids' => $base['currentCandidateRowids'],
            'nextCandidateRowids' => $base['nextCandidateRowids'],
            'retainedCandidateRowids' => $retainedCandidates,
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'retainedMatchedRowids' => $retainedMatched,
            'enteredMatchedRowids' => $base['enteredMatchedRowids'],
            'exitedMatchedRowids' => $base['exitedMatchedRowids'],
            'yieldStableRetainedRowids' => $stableRows,
            'yieldRecheckRetainedRowids' => $recheckRows,
            'yieldSkippedCurrentRowids' => array_values(array_diff($base['currentCandidateRowids'], $retainedCandidates)),
            'yieldNewNextRowids' => array_values(array_diff($base['nextCandidateRowids'], $retainedCandidates)),
            'yieldResumeKeyFingerprints' => $resumeKeys,
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'currentRtrimTexts' => $base['currentRtrimTexts'],
            'nextRtrimTexts' => $base['nextRtrimTexts'],
            'currentNocaseKeys' => $base['currentNocaseKeys'],
            'nextNocaseKeys' => $base['nextNocaseKeys'],
            'currentEncodings' => $base['currentEncodings'],
            'nextEncodings' => $base['nextEncodings'],
            'currentBytesHex' => $base['currentBytesHex'],
            'nextBytesHex' => $base['nextBytesHex'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $resumeSafe,
            'yieldResumeSafe' => $resumeSafe,
            'yieldResumeRequiresReprepare' => $reasons !== [],
            'yieldResumeRequiresResidualRecheck' => $recheckRows !== [],
            'invalidationReasons' => array_values(array_unique($reasons)),
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-rtrim-expression',
                'sqlite-like-nocase-prefix-range',
                'sqlite-current-source-nextoneSixOne',
                'sqlite-yield-current-source-nextoneSixFour',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, RTRIM/NOCASE LIKE scans, and adds yield/resume statement fingerprints for current-source transitions',
        ];
    }

    /** @param array<string,mixed> $base */
    private static function v164_same(array $base, string $currentKey, string $nextKey, int $rowid): bool
    {
        return ($base[$currentKey][$rowid] ?? null) === ($base[$nextKey][$rowid] ?? null);
    }

    /** @param array<string,mixed> $base */
    private static function v164_rowFingerprint(array $base, int $rowid): string
    {
        return hash('sha256', json_encode([
            'rowid' => $rowid,
            'rtrim' => $base['nextRtrimTexts'][$rowid] ?? null,
            'nocase' => $base['nextNocaseKeys'][$rowid] ?? null,
            'encoding' => $base['nextEncodings'][$rowid] ?? null,
            'bytes' => $base['nextBytesHex'][$rowid] ?? null,
        ], JSON_THROW_ON_ERROR));
    }

    /** @param ?array{lowerInclusive:string,upperBound:?string} $range */
    private static function v164_rangeFingerprint(?array $range): string
    {
        return hash('sha256', json_encode($range, JSON_THROW_ON_ERROR));
    }

    private static function v164_statementFingerprint(
        string $statement,
        string $source,
        int $schemaCookie,
        string $collationGeneration,
        string $likeGeneration,
        string $rangeFingerprint,
    ): string {
        return hash('sha256', json_encode([
            'statement' => $statement,
            'source' => $source,
            'schemaCookie' => $schemaCookie,
            'collationGeneration' => $collationGeneration,
            'likeGeneration' => $likeGeneration,
            'rangeFingerprint' => $rangeFingerprint,
        ], JSON_THROW_ON_ERROR));
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array{key:string,rowid:int}|null $lastYielded
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyResumePlan(
        array $currentRows,
        array $nextRows,
        string $currentPatternBytes,
        int $currentPatternEncoding,
        string $nextPatternBytes,
        int $nextPatternEncoding,
        ?string $currentEscapeBytes = null,
        int $currentEscapeEncoding = 1,
        ?string $nextEscapeBytes = null,
        int $nextEscapeEncoding = 1,
        ?array $lastYielded = null,
        string $currentSource = 'main.app_settings@164',
        string $nextSource = 'main.app_settings@165',
        int $currentSchemaCookie = 164,
        int $nextSchemaCookie = 165,
    ): array {
        self::v165_assertLastYielded($lastYielded);

        $base = self::keyValueRowKeyNormalizedPatternPlan(
            $currentRows,
            $nextRows,
            $currentPatternBytes,
            $currentPatternEncoding,
            $nextPatternBytes,
            $nextPatternEncoding,
            $currentEscapeBytes,
            $currentEscapeEncoding,
            $nextEscapeBytes,
            $nextEscapeEncoding,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $currentKeys = self::v165_keys(self::v165_decodedRtrimTexts($currentRows), $base['currentMatchedRowids']);
        $nextKeys = self::v165_keys(self::v165_decodedRtrimTexts($nextRows), $base['nextMatchedRowids']);
        $currentAfter = self::v165_afterToken($currentKeys, $lastYielded);
        $nextAfter = self::v165_afterToken($nextKeys, $lastYielded);
        $nextBeforeOrAt = self::v165_beforeOrAtToken($nextKeys, $lastYielded);
        $retainedAfter = array_values(array_intersect($currentAfter, $nextAfter));
        $enteredAfter = array_values(array_diff($nextAfter, $currentAfter));
        $exitedAfter = array_values(array_diff($currentAfter, $nextAfter));
        $movedBeforeToken = array_values(array_intersect($currentAfter, $nextBeforeOrAt));
        $newBeforeToken = array_values(array_diff($nextBeforeOrAt, $base['currentMatchedRowids']));

        $resumeReasons = [];
        if ($lastYielded === null) {
            $resumeReasons[] = 'no-yield-token';
        }
        $structuralSemanticReasons = array_values(array_diff(
            $base['semanticInvalidationReasons'],
            ['candidate-rowset', 'matched-rowset', 'rtrim-false-positive-rowset'],
        ));
        if ($structuralSemanticReasons !== []) {
            $resumeReasons[] = 'semantic-invalidation';
        }
        if ($base['currentMalformedRowids'] !== [] || $base['nextMalformedRowids'] !== []) {
            $resumeReasons[] = 'malformed-text';
        }
        if ($newBeforeToken !== []) {
            $resumeReasons[] = 'entered-before-token';
        }
        if ($movedBeforeToken !== []) {
            $resumeReasons[] = 'retained-moved-across-token';
        }
        if ($base['currentIndexUsable'] === false || $base['nextIndexUsable'] === false) {
            $resumeReasons[] = 'unusable-prefix-range';
        }

        $mustReprepare = $resumeReasons !== [];

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nextoneSixFive',
            'operator' => 'LIKE',
            'expression' => 'rtrim(key_name) COLLATE NOCASE',
            'caseSensitiveLike' => false,
            'asciiNocaseOnly' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'normalizesPreparedPatternBytes' => true,
            'baseStatus' => $base['status'],
            'currentSource' => $base['currentSource'],
            'nextSource' => $base['nextSource'],
            'currentSchemaCookie' => $base['currentSchemaCookie'],
            'nextSchemaCookie' => $base['nextSchemaCookie'],
            'currentPattern' => $base['currentPattern'],
            'nextPattern' => $base['nextPattern'],
            'sameDecodedPattern' => $base['sameDecodedPattern'],
            'currentEscape' => $base['currentEscape'],
            'nextEscape' => $base['nextEscape'],
            'sameDecodedEscape' => $base['sameDecodedEscape'],
            'currentPrefix' => $base['currentPrefix'],
            'nextPrefix' => $base['nextPrefix'],
            'currentRange' => $base['currentRtrimRange'],
            'nextRange' => $base['nextRtrimRange'],
            'currentIndexUsable' => $base['currentIndexUsable'],
            'nextIndexUsable' => $base['nextIndexUsable'],
            'lastYielded' => $lastYielded,
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'currentMatchedKeys' => $currentKeys,
            'nextMatchedKeys' => $nextKeys,
            'currentAfterTokenRowids' => $currentAfter,
            'nextAfterTokenRowids' => $nextAfter,
            'nextBeforeOrAtTokenRowids' => $nextBeforeOrAt,
            'retainedAfterTokenRowids' => $retainedAfter,
            'enteredAfterTokenRowids' => $enteredAfter,
            'exitedAfterTokenRowids' => $exitedAfter,
            'newBeforeTokenRowids' => $newBeforeToken,
            'retainedMovedAcrossTokenRowids' => $movedBeforeToken,
            'byteReprepareReasons' => $base['byteReprepareReasons'],
            'semanticInvalidationReasons' => $base['semanticInvalidationReasons'],
            'baseInvalidationReasons' => $base['baseInvalidationReasons'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'currentErrors' => $base['currentErrors'],
            'nextErrors' => $base['nextErrors'],
            'resumeReasons' => array_values(array_unique($resumeReasons)),
            'mustReprepareBeforeResume' => $mustReprepare,
            'safeToResumeFromToken' => !$mustReprepare,
            'resumePlanRowids' => $mustReprepare ? $base['nextMatchedRowids'] : $nextAfter,
            'resumePlanMode' => $mustReprepare ? 'reprepare-from-range-start' : 'continue-after-last-yielded-key-rowid',
            'dependencies' => [
                'sqlite-utf16-pattern-normalization',
                'sqlite-nocase-like-rtrim-resume-cursor',
                'sqlite-current-source-nextoneSixFive',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode/pattern normalization, ASCII NOCASE LIKE matching, RTRIM expression keys, and adds current-source resume-token diagnostics',
        ];
    }

    /** @param array{key:string,rowid:int}|null $lastYielded */
    private static function v165_assertLastYielded(?array $lastYielded): void
    {
        if ($lastYielded === null) {
            return;
        }
        if (!array_key_exists('key', $lastYielded) || !is_string($lastYielded['key'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneSixFive resume token requires string key');
        }
        if (!array_key_exists('rowid', $lastYielded) || !is_int($lastYielded['rowid'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneSixFive resume token requires integer rowid');
        }
    }

    /** @param array<int,string> $texts @param list<int> $rowids @return array<int,string> */
    private static function v165_keys(array $texts, array $rowids): array
    {
        $keys = [];
        foreach ($rowids as $rowid) {
            $keys[$rowid] = self::v165_asciiLower($texts[$rowid]);
        }
        uasort($keys, static fn (string $left, string $right): int => strcmp($left, $right));

        return $keys;
    }

    /** @param list<array<string,mixed>> $rows @return array<int,string> */
    private static function v165_decodedRtrimTexts(array $rows): array
    {
        $texts = [];
        foreach ($rows as $row) {
            if (!array_key_exists('setting_id', $row) || !is_int($row['setting_id'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneSixFive rows require integer setting_id');
            }
            if (!array_key_exists('key_name_bytes', $row) || !is_string($row['key_name_bytes'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneSixFive rows require key_name_bytes');
            }
            if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneSixFive rows require integer text_encoding');
            }

            try {
                $texts[$row['setting_id']] = rtrim(SQLiteEncodingCollationSourceCursor::decodeText($row['key_name_bytes'], $row['text_encoding']), ' ');
            } catch (\InvalidArgumentException) {
            }
        }

        return $texts;
    }

    /** @param array<int,string> $keys @param array{key:string,rowid:int}|null $token @return list<int> */
    private static function v165_afterToken(array $keys, ?array $token): array
    {
        if ($token === null) {
            return array_map('intval', array_keys($keys));
        }

        $rowids = [];
        foreach ($keys as $rowid => $key) {
            if (strcmp($key, $token['key']) > 0 || ($key === $token['key'] && $rowid > $token['rowid'])) {
                $rowids[] = (int) $rowid;
            }
        }

        return $rowids;
    }

    /** @param array<int,string> $keys @param array{key:string,rowid:int}|null $token @return list<int> */
    private static function v165_beforeOrAtToken(array $keys, ?array $token): array
    {
        if ($token === null) {
            return [];
        }

        $rowids = [];
        foreach ($keys as $rowid => $key) {
            if (strcmp($key, $token['key']) < 0 || ($key === $token['key'] && $rowid <= $token['rowid'])) {
                $rowids[] = (int) $rowid;
            }
        }

        return $rowids;
    }

    private static function v165_asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyFallbackPlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        string $currentSource = 'main.app_settings@166',
        string $nextSource = 'main.app_settings@167',
        int $currentSchemaCookie = 166,
        int $nextSchemaCookie = 167,
    ): array {
        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $current = self::v167_scan($currentRows, $pattern, $escape, $like['range']);
        $next = self::v167_scan($nextRows, $pattern, $escape, $like['range']);
        $currentCandidates = self::v167_rowids($current['candidates']);
        $nextCandidates = self::v167_rowids($next['candidates']);
        $currentMatched = self::v167_rowids($current['matched']);
        $nextMatched = self::v167_rowids($next['matched']);
        $changes = self::v167_changes($current['decoded'], $next['decoded']);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if (!$like['indexUsable']) {
            $reasons[] = 'full-scan-like-residual';
        }
        foreach ([
            'decoded-text' => $changes['textChangedRowids'],
            'rtrim-expression' => $changes['rtrimChangedRowids'],
            'nocase-key' => $changes['nocaseKeyChangedRowids'],
            'text-encoding' => $changes['encodingChangedRowids'],
            'encoded-bytes' => $changes['bytesChangedRowids'],
            'residual-result' => $changes['matchChangedRowids'],
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

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nextoneSixSeven',
            'operator' => 'LIKE',
            'expression' => 'rtrim(key_name) COLLATE NOCASE',
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => 'NOCASE',
            'caseSensitiveLike' => false,
            'prefix' => $like['prefix'],
            'prefixCharacters' => $like['prefixCharacters'],
            'prefixIsAscii' => $like['prefixIsAscii'],
            'indexUsable' => $like['indexUsable'],
            'scanMode' => $like['indexUsable'] ? 'nocase-range' : 'full-residual-scan',
            'rejectedReason' => $like['rejectedReason'],
            'range' => $like['range'],
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentOrderRowids' => self::v167_rowids($current['decoded']),
            'nextOrderRowids' => self::v167_rowids($next['decoded']),
            'currentCandidateRowids' => $currentCandidates,
            'nextCandidateRowids' => $nextCandidates,
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'currentFalsePositiveRowids' => self::v167_rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::v167_rowids($next['falsePositive']),
            'retainedMatchedRowids' => array_values(array_intersect($currentMatched, $nextMatched)),
            'enteredMatchedRowids' => array_values(array_diff($nextMatched, $currentMatched)),
            'exitedMatchedRowids' => array_values(array_diff($currentMatched, $nextMatched)),
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'currentTexts' => self::v167_map($current['decoded'], 'text'),
            'nextTexts' => self::v167_map($next['decoded'], 'text'),
            'currentRtrimTexts' => self::v167_map($current['decoded'], 'rtrimText'),
            'nextRtrimTexts' => self::v167_map($next['decoded'], 'rtrimText'),
            'currentNocaseKeys' => self::v167_map($current['decoded'], 'nocaseKey'),
            'nextNocaseKeys' => self::v167_map($next['decoded'], 'nocaseKey'),
            'currentEncodings' => self::v167_map($current['decoded'], 'encoding'),
            'nextEncodings' => self::v167_map($next['decoded'], 'encoding'),
            'currentBytesHex' => self::v167_map($current['decoded'], 'bytesHex'),
            'nextBytesHex' => self::v167_map($next['decoded'], 'bytesHex'),
            'currentResidualMatches' => self::v167_map($current['candidates'], 'residualMatch'),
            'nextResidualMatches' => self::v167_map($next['candidates'], 'residualMatch'),
            'changedTextRowids' => $changes['textChangedRowids'],
            'changedRtrimRowids' => $changes['rtrimChangedRowids'],
            'changedNocaseKeyRowids' => $changes['nocaseKeyChangedRowids'],
            'changedEncodingRowids' => $changes['encodingChangedRowids'],
            'changedBytesRowids' => $changes['bytesChangedRowids'],
            'changedResidualRowids' => $changes['matchChangedRowids'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => array_values(array_unique($reasons)),
            'fullScanFallbackPreservesResidualLike' => !$like['indexUsable'],
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-rtrim-expression',
                'sqlite-like-nocase-full-scan-fallback',
                'sqlite-current-source-nextoneSixSeven',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, RTRIM expression keys, ASCII NOCASE LIKE residual matching, and current-source invalidation metadata',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{decoded:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function v167_scan(array $rows, string $pattern, ?string $escape, ?array $range): array
    {
        $decoded = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            self::v167_assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['key_name_bytes'], $row['text_encoding']);
                $rtrim = rtrim($text, ' ');
                $decoded[] = [
                    'rowid' => $row['setting_id'],
                    'text' => $text,
                    'rtrimText' => $rtrim,
                    'nocaseKey' => self::v167_asciiLower($rtrim),
                    'encoding' => self::v167_encodingName($row['text_encoding']),
                    'bytesHex' => bin2hex($row['key_name_bytes']),
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['setting_id'];
                $errors[$row['setting_id']] = $exception->getMessage();
            }
        }

        usort($decoded, self::v167_sortRows(...));
        sort($malformed);
        ksort($errors);

        $candidates = [];
        $matched = [];
        $falsePositive = [];
        foreach ($decoded as $entry) {
            if ($range !== null && !self::v167_inRange($entry['nocaseKey'], $range)) {
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
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row */
    private static function v167_assertRow(array $row): void
    {
        if (!array_key_exists('setting_id', $row) || !is_int($row['setting_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM current-source nextOneSixSeven rows require integer setting_id');
        }
        if (!array_key_exists('key_name_bytes', $row) || !is_string($row['key_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM current-source nextOneSixSeven rows require key_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM current-source nextOneSixSeven rows require integer text_encoding');
        }
    }

    /** @param ?array{lowerInclusive:string,upperBound:?string} $range */
    private static function v167_inRange(string $key, ?array $range): bool
    {
        if ($range === null || strcmp($key, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0;
    }

    /** @param array{nocaseKey:string,rowid:int} $left @param array{nocaseKey:string,rowid:int} $right */
    private static function v167_sortRows(array $left, array $right): int
    {
        $comparison = strcmp($left['nocaseKey'], $right['nocaseKey']);

        return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
    }

    /** @param list<array{rowid:int}> $rows @return list<int> */
    private static function v167_rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $rows @return array<int,mixed> */
    private static function v167_map(array $rows, string $key): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[$row['rowid']] = $row[$key];
        }

        return $mapped;
    }

    /**
     * @param list<array{rowid:int,text:string,rtrimText:string,nocaseKey:string,encoding:string,bytesHex:string,residualMatch?:bool}> $currentRows
     * @param list<array{rowid:int,text:string,rtrimText:string,nocaseKey:string,encoding:string,bytesHex:string,residualMatch?:bool}> $nextRows
     * @return array{textChangedRowids:list<int>,rtrimChangedRowids:list<int>,nocaseKeyChangedRowids:list<int>,encodingChangedRowids:list<int>,bytesChangedRowids:list<int>,matchChangedRowids:list<int>}
     */
    private static function v167_changes(array $currentRows, array $nextRows): array
    {
        $current = [];
        foreach ($currentRows as $row) {
            $current[$row['rowid']] = $row;
        }

        $changes = [
            'textChangedRowids' => [],
            'rtrimChangedRowids' => [],
            'nocaseKeyChangedRowids' => [],
            'encodingChangedRowids' => [],
            'bytesChangedRowids' => [],
            'matchChangedRowids' => [],
        ];
        foreach ($nextRows as $row) {
            $rowid = $row['rowid'];
            if (!isset($current[$rowid])) {
                continue;
            }
            if ($current[$rowid]['text'] !== $row['text']) {
                $changes['textChangedRowids'][] = $rowid;
            }
            if ($current[$rowid]['rtrimText'] !== $row['rtrimText']) {
                $changes['rtrimChangedRowids'][] = $rowid;
            }
            if ($current[$rowid]['nocaseKey'] !== $row['nocaseKey']) {
                $changes['nocaseKeyChangedRowids'][] = $rowid;
            }
            if ($current[$rowid]['encoding'] !== $row['encoding']) {
                $changes['encodingChangedRowids'][] = $rowid;
            }
            if ($current[$rowid]['bytesHex'] !== $row['bytesHex']) {
                $changes['bytesChangedRowids'][] = $rowid;
            }
            if (($current[$rowid]['residualMatch'] ?? null) !== ($row['residualMatch'] ?? null)) {
                $changes['matchChangedRowids'][] = $rowid;
            }
        }
        foreach ($changes as &$rowids) {
            sort($rowids);
        }

        return $changes;
    }

    private static function v167_encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function v167_asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyCaseSensitiveLikePlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        string $currentSource = 'main.app_settings@167',
        string $nextSource = 'main.app_settings@168',
        int $currentSchemaCookie = 167,
        int $nextSchemaCookie = 168,
        bool $currentCaseSensitiveLike = false,
        bool $nextCaseSensitiveLike = true,
    ): array {
        $currentLike = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, $currentCaseSensitiveLike);
        $nextLike = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, $nextCaseSensitiveLike);
        $current = self::v168_scan($currentRows, $pattern, $escape, $currentLike['range'], $currentCaseSensitiveLike);
        $next = self::v168_scan($nextRows, $pattern, $escape, $currentLike['range'], $nextCaseSensitiveLike);
        $nextFull = self::v168_scan($nextRows, $pattern, $escape, null, $nextCaseSensitiveLike, true);

        $currentCandidateRowids = self::v168_rowids($current['candidates']);
        $nextCandidateRowids = self::v168_rowids($next['candidates']);
        $currentMatchedRowids = self::v168_rowids($current['matched']);
        $nextMatchedRowids = self::v168_rowids($next['matched']);
        $nextFullMatchedRowids = self::v168_rowids($nextFull['matched']);
        $changes = self::v168_changes($current['decoded'], $next['decoded']);
        $changes['matchChangedRowids'] = self::v168_residualChanges($current['candidates'], $next['candidates']);
        $retainedCandidateRowids = array_values(array_intersect($currentCandidateRowids, $nextCandidateRowids));
        $retainedMatchedRowids = array_values(array_intersect($currentMatchedRowids, $nextMatchedRowids));

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($currentCaseSensitiveLike !== $nextCaseSensitiveLike) {
            $reasons[] = 'case-sensitive-like';
        }
        if (!$currentLike['indexUsable']) {
            $reasons[] = 'current-no-nocase-prefix-range';
        }
        if (!$nextLike['indexUsable']) {
            $reasons[] = 'next-nocase-index-unusable';
        }
        foreach ([
            'decoded-text' => $changes['textChangedRowids'],
            'rtrim-expression' => $changes['rtrimChangedRowids'],
            'nocase-key' => $changes['nocaseKeyChangedRowids'],
            'text-encoding' => $changes['encodingChangedRowids'],
            'encoded-bytes' => $changes['bytesChangedRowids'],
            'residual-result' => $changes['matchChangedRowids'],
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        if ($currentCandidateRowids !== $nextCandidateRowids) {
            $reasons[] = 'candidate-rowset';
        }
        if ($currentMatchedRowids !== $nextMatchedRowids) {
            $reasons[] = 'matched-rowset';
        }
        if ($nextCandidateRowids !== $nextFullMatchedRowids) {
            $reasons[] = 'case-sensitive-fullscan-required';
        }

        $caseSensitiveDrops = array_values(array_diff($currentMatchedRowids, $nextMatchedRowids));
        $caseSensitiveKeeps = array_values(array_intersect($currentMatchedRowids, $nextMatchedRowids));
        $caseSensitiveEnters = array_values(array_diff($nextMatchedRowids, $currentMatchedRowids));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nextoneSixEight',
            'operator' => 'LIKE',
            'expression' => 'rtrim(key_name) COLLATE NOCASE',
            'pattern' => $pattern,
            'escape' => $escape,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentCaseSensitiveLike' => $currentCaseSensitiveLike,
            'nextCaseSensitiveLike' => $nextCaseSensitiveLike,
            'currentLikePlan' => $currentLike,
            'nextLikePlan' => $nextLike,
            'prefix' => $currentLike['prefix'],
            'currentRange' => $currentLike['range'],
            'nextRange' => $nextLike['range'],
            'currentIndexUsable' => $currentLike['indexUsable'],
            'nextIndexUsable' => $nextLike['indexUsable'],
            'nextRejectedReason' => $nextLike['rejectedReason'],
            'currentCandidateRowids' => $currentCandidateRowids,
            'nextCandidateRowidsUsingCurrentNocaseRange' => $nextCandidateRowids,
            'retainedCandidateRowids' => $retainedCandidateRowids,
            'currentMatchedRowids' => $currentMatchedRowids,
            'nextMatchedRowids' => $nextMatchedRowids,
            'nextFullScanMatchedRowids' => $nextFullMatchedRowids,
            'retainedMatchedRowids' => $retainedMatchedRowids,
            'caseSensitiveDroppedRowids' => $caseSensitiveDrops,
            'caseSensitiveKeptRowids' => $caseSensitiveKeeps,
            'caseSensitiveEnteredRowids' => $caseSensitiveEnters,
            'currentFalsePositiveRowids' => self::v168_rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::v168_rowids($next['falsePositive']),
            'caseSensitiveRangeFalsePositiveRowids' => array_values(array_diff($nextCandidateRowids, $nextMatchedRowids)),
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'currentTexts' => self::v168_map($current['decoded'], 'text'),
            'nextTexts' => self::v168_map($next['decoded'], 'text'),
            'currentRtrimTexts' => self::v168_map($current['decoded'], 'rtrimText'),
            'nextRtrimTexts' => self::v168_map($next['decoded'], 'rtrimText'),
            'currentNocaseKeys' => self::v168_map($current['decoded'], 'nocaseKey'),
            'nextNocaseKeys' => self::v168_map($next['decoded'], 'nocaseKey'),
            'currentEncodings' => self::v168_map($current['decoded'], 'encoding'),
            'nextEncodings' => self::v168_map($next['decoded'], 'encoding'),
            'currentBytesHex' => self::v168_map($current['decoded'], 'bytesHex'),
            'nextBytesHex' => self::v168_map($next['decoded'], 'bytesHex'),
            'currentResidualMatches' => self::v168_map($current['candidates'], 'residualMatch'),
            'nextResidualMatches' => self::v168_map($next['candidates'], 'residualMatch'),
            'changedTextRowids' => $changes['textChangedRowids'],
            'changedRtrimRowids' => $changes['rtrimChangedRowids'],
            'changedNocaseKeyRowids' => $changes['nocaseKeyChangedRowids'],
            'changedEncodingRowids' => $changes['encodingChangedRowids'],
            'changedBytesRowids' => $changes['bytesChangedRowids'],
            'changedResidualRowids' => $changes['matchChangedRowids'],
            'currentNocaseRangeCanSeedRecheck' => $currentLike['indexUsable'],
            'nextRequiresBinaryLikeScan' => !$nextLike['indexUsable'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => array_values(array_unique($reasons)),
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'caseSensitiveLikeHonorsAsciiCase' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-rtrim-expression',
                'sqlite-like-nocase-prefix-range',
                'sqlite-case-sensitive-like',
                'sqlite-current-source-nextoneSixEight',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, RTRIM expression keys, NOCASE LIKE prefix planning, and adds case-sensitive LIKE residual recheck diagnostics for current-source transitions',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{decoded:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function v168_scan(array $rows, string $pattern, ?string $escape, ?array $range, bool $caseSensitiveLike, bool $fullScan = false): array
    {
        $decoded = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            self::v168_assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['key_name_bytes'], $row['text_encoding']);
                $rtrim = rtrim($text, ' ');
                $decoded[] = [
                    'rowid' => $row['setting_id'],
                    'text' => $text,
                    'rtrimText' => $rtrim,
                    'nocaseKey' => self::v168_asciiLower($rtrim),
                    'encoding' => self::v168_encodingName($row['text_encoding']),
                    'bytesHex' => bin2hex($row['key_name_bytes']),
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['setting_id'];
                $errors[$row['setting_id']] = $exception->getMessage();
            }
        }

        usort($decoded, self::v168_sortRows(...));
        sort($malformed);
        ksort($errors);

        $candidates = [];
        $matched = [];
        $falsePositive = [];
        foreach ($decoded as $entry) {
            if (!$fullScan && !self::v168_inRange($entry['nocaseKey'], $range)) {
                continue;
            }
            $entry['residualMatch'] = SQLiteDatabase::likeMatches($entry['rtrimText'], $pattern, $escape, $caseSensitiveLike);
            if (!$fullScan) {
                $candidates[] = $entry;
            }
            if ($entry['residualMatch']) {
                $matched[] = $entry;
            } elseif (!$fullScan) {
                $falsePositive[] = $entry;
            }
        }

        return [
            'decoded' => $decoded,
            'candidates' => $fullScan ? $matched : $candidates,
            'matched' => $matched,
            'falsePositive' => $falsePositive,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row */
    private static function v168_assertRow(array $row): void
    {
        if (!array_key_exists('setting_id', $row) || !is_int($row['setting_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM current-source nextOneSixEight rows require integer setting_id');
        }
        if (!array_key_exists('key_name_bytes', $row) || !is_string($row['key_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM current-source nextOneSixEight rows require key_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM current-source nextOneSixEight rows require integer text_encoding');
        }
    }

    /** @param ?array{lowerInclusive:string,upperBound:?string} $range */
    private static function v168_inRange(string $key, ?array $range): bool
    {
        if ($range === null || strcmp($key, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0;
    }

    /** @param array{nocaseKey:string,rowid:int} $left @param array{nocaseKey:string,rowid:int} $right */
    private static function v168_sortRows(array $left, array $right): int
    {
        $comparison = strcmp($left['nocaseKey'], $right['nocaseKey']);

        return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
    }

    /** @param list<array{rowid:int}> $rows @return list<int> */
    private static function v168_rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $rows @return array<int,mixed> */
    private static function v168_map(array $rows, string $key): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[$row['rowid']] = $row[$key];
        }

        return $mapped;
    }

    /**
     * @param list<array{rowid:int,text:string,rtrimText:string,nocaseKey:string,encoding:string,bytesHex:string,residualMatch?:bool}> $currentRows
     * @param list<array{rowid:int,text:string,rtrimText:string,nocaseKey:string,encoding:string,bytesHex:string,residualMatch?:bool}> $nextRows
     * @return array{textChangedRowids:list<int>,rtrimChangedRowids:list<int>,nocaseKeyChangedRowids:list<int>,encodingChangedRowids:list<int>,bytesChangedRowids:list<int>,matchChangedRowids:list<int>}
     */
    private static function v168_changes(array $currentRows, array $nextRows): array
    {
        $current = [];
        foreach ($currentRows as $row) {
            $current[$row['rowid']] = $row;
        }

        $changes = [
            'textChangedRowids' => [],
            'rtrimChangedRowids' => [],
            'nocaseKeyChangedRowids' => [],
            'encodingChangedRowids' => [],
            'bytesChangedRowids' => [],
            'matchChangedRowids' => [],
        ];
        foreach ($nextRows as $row) {
            $rowid = $row['rowid'];
            if (!isset($current[$rowid])) {
                continue;
            }
            if ($current[$rowid]['text'] !== $row['text']) {
                $changes['textChangedRowids'][] = $rowid;
            }
            if ($current[$rowid]['rtrimText'] !== $row['rtrimText']) {
                $changes['rtrimChangedRowids'][] = $rowid;
            }
            if ($current[$rowid]['nocaseKey'] !== $row['nocaseKey']) {
                $changes['nocaseKeyChangedRowids'][] = $rowid;
            }
            if ($current[$rowid]['encoding'] !== $row['encoding']) {
                $changes['encodingChangedRowids'][] = $rowid;
            }
            if ($current[$rowid]['bytesHex'] !== $row['bytesHex']) {
                $changes['bytesChangedRowids'][] = $rowid;
            }
            if (($current[$rowid]['residualMatch'] ?? null) !== ($row['residualMatch'] ?? null)) {
                $changes['matchChangedRowids'][] = $rowid;
            }
        }
        foreach ($changes as &$rowids) {
            sort($rowids);
        }

        return $changes;
    }

    /**
     * @param list<array{rowid:int,residualMatch?:bool}> $currentRows
     * @param list<array{rowid:int,residualMatch?:bool}> $nextRows
     * @return list<int>
     */
    private static function v168_residualChanges(array $currentRows, array $nextRows): array
    {
        $current = [];
        foreach ($currentRows as $row) {
            $current[$row['rowid']] = (bool) ($row['residualMatch'] ?? false);
        }

        $changed = [];
        foreach ($nextRows as $row) {
            $rowid = $row['rowid'];
            if (array_key_exists($rowid, $current) && $current[$rowid] !== (bool) ($row['residualMatch'] ?? false)) {
                $changed[] = $rowid;
            }
        }
        sort($changed);

        return $changed;
    }

    private static function v168_encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function v168_asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array{key:string,rowid:int}|null $lastYielded
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyYieldReplayPlan(
        array $currentRows,
        array $nextRows,
        string $currentPatternBytes,
        int $currentPatternEncoding,
        string $nextPatternBytes,
        int $nextPatternEncoding,
        ?string $currentEscapeBytes = null,
        int $currentEscapeEncoding = 1,
        ?string $nextEscapeBytes = null,
        int $nextEscapeEncoding = 1,
        ?array $lastYielded = null,
        int $pageSize = 3,
        string $currentSource = 'main.app_settings@168',
        string $nextSource = 'main.app_settings@169',
        int $currentSchemaCookie = 168,
        int $nextSchemaCookie = 169,
    ): array {
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneSixNine yield page size must be positive');
        }

        $resume = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyResumePlan(
            $currentRows,
            $nextRows,
            $currentPatternBytes,
            $currentPatternEncoding,
            $nextPatternBytes,
            $nextPatternEncoding,
            $currentEscapeBytes,
            $currentEscapeEncoding,
            $nextEscapeBytes,
            $nextEscapeEncoding,
            $lastYielded,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $nextKeys = self::v169_sortedKeys($nextRows, $resume['nextMatchedRowids']);
        $yieldRowids = array_slice($resume['resumePlanRowids'], 0, $pageSize);
        $deferredRowids = array_slice($resume['resumePlanRowids'], $pageSize);
        $yieldKeys = self::v169_subset($nextKeys, $yieldRowids);
        $deferredKeys = self::v169_subset($nextKeys, $deferredRowids);
        $highWaterToken = self::v169_tokenForLast($yieldKeys);

        $previouslyYielded = self::v169_beforeOrAtToken($nextKeys, $lastYielded);
        $wouldDuplicate = array_values(array_intersect($yieldRowids, $previouslyYielded));
        $staleRetained = array_values(array_intersect($resume['currentAfterTokenRowids'], $previouslyYielded));

        $restartReasons = $resume['resumeReasons'];
        if ($wouldDuplicate !== []) {
            $restartReasons[] = 'would-duplicate-yield';
        }
        if ($staleRetained !== []) {
            $restartReasons[] = 'retained-row-became-before-token';
        }

        $mustRestart = $resume['mustReprepareBeforeResume'] || $wouldDuplicate !== [] || $staleRetained !== [];

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nextoneSixNine',
            'operator' => 'LIKE',
            'expression' => 'rtrim(key_name) COLLATE NOCASE',
            'caseSensitiveLike' => false,
            'asciiNocaseOnly' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'baseStatus' => $resume['status'],
            'currentSource' => $resume['currentSource'],
            'nextSource' => $resume['nextSource'],
            'currentSchemaCookie' => $resume['currentSchemaCookie'],
            'nextSchemaCookie' => $resume['nextSchemaCookie'],
            'currentPattern' => $resume['currentPattern'],
            'nextPattern' => $resume['nextPattern'],
            'sameDecodedPattern' => $resume['sameDecodedPattern'],
            'currentEscape' => $resume['currentEscape'],
            'nextEscape' => $resume['nextEscape'],
            'sameDecodedEscape' => $resume['sameDecodedEscape'],
            'currentRange' => $resume['currentRange'],
            'nextRange' => $resume['nextRange'],
            'currentIndexUsable' => $resume['currentIndexUsable'],
            'nextIndexUsable' => $resume['nextIndexUsable'],
            'lastYielded' => $lastYielded,
            'pageSize' => $pageSize,
            'currentMatchedRowids' => $resume['currentMatchedRowids'],
            'nextMatchedRowids' => $resume['nextMatchedRowids'],
            'currentAfterTokenRowids' => $resume['currentAfterTokenRowids'],
            'nextAfterTokenRowids' => $resume['nextAfterTokenRowids'],
            'resumePlanMode' => $resume['resumePlanMode'],
            'resumePlanRowids' => $resume['resumePlanRowids'],
            'yieldMode' => $mustRestart ? 'restart-then-yield-page' : 'continue-yield-page',
            'yieldedRowids' => $yieldRowids,
            'yieldedKeys' => $yieldKeys,
            'deferredRowids' => $deferredRowids,
            'deferredKeys' => $deferredKeys,
            'highWaterToken' => $highWaterToken,
            'hasMore' => $deferredRowids !== [],
            'previouslyYieldedRowids' => $previouslyYielded,
            'wouldDuplicateRowids' => $wouldDuplicate,
            'staleRetainedBeforeTokenRowids' => $staleRetained,
            'newBeforeTokenRowids' => $resume['newBeforeTokenRowids'],
            'retainedMovedAcrossTokenRowids' => $resume['retainedMovedAcrossTokenRowids'],
            'byteReprepareReasons' => $resume['byteReprepareReasons'],
            'semanticInvalidationReasons' => $resume['semanticInvalidationReasons'],
            'baseInvalidationReasons' => $resume['baseInvalidationReasons'],
            'resumeReasons' => $resume['resumeReasons'],
            'restartReasons' => array_values(array_unique($restartReasons)),
            'mustRestartBeforeYield' => $mustRestart,
            'safeToContinueYield' => !$mustRestart,
            'currentMalformedRowids' => $resume['currentMalformedRowids'],
            'nextMalformedRowids' => $resume['nextMalformedRowids'],
            'currentErrors' => $resume['currentErrors'],
            'nextErrors' => $resume['nextErrors'],
            'dependencies' => [
                'sqlite-utf16-pattern-normalization',
                'sqlite-nocase-like-rtrim-resume-cursor',
                'sqlite-yield-high-water-token',
                'sqlite-current-source-nextoneSixNine',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode/pattern normalization, ASCII NOCASE LIKE matching, RTRIM expression keys, and adds bounded yield-page/high-water-token diagnostics',
        ];
    }

    /** @param list<array<string,mixed>> $rows @param list<int> $matchedRowids @return array<int,string> */
    private static function v169_sortedKeys(array $rows, array $matchedRowids): array
    {
        $wanted = array_fill_keys($matchedRowids, true);
        $keys = [];
        foreach ($rows as $row) {
            if (!array_key_exists('setting_id', $row) || !is_int($row['setting_id'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneSixNine rows require integer setting_id');
            }
            if (!isset($wanted[$row['setting_id']])) {
                continue;
            }
            if (!array_key_exists('key_name_bytes', $row) || !is_string($row['key_name_bytes'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneSixNine rows require key_name_bytes');
            }
            if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneSixNine rows require integer text_encoding');
            }

            try {
                $keys[$row['setting_id']] = self::v169_asciiLower(rtrim(SQLiteEncodingCollationSourceCursor::decodeText($row['key_name_bytes'], $row['text_encoding']), ' '));
            } catch (\InvalidArgumentException) {
            }
        }
        uasort($keys, static fn (string $left, string $right): int => strcmp($left, $right));

        return $keys;
    }

    /** @param array<int,string> $keys @param list<int> $rowids @return array<int,string> */
    private static function v169_subset(array $keys, array $rowids): array
    {
        $subset = [];
        foreach ($rowids as $rowid) {
            if (array_key_exists($rowid, $keys)) {
                $subset[$rowid] = $keys[$rowid];
            }
        }

        return $subset;
    }

    /** @param array<int,string> $keys @return array{key:string,rowid:int}|null */
    private static function v169_tokenForLast(array $keys): ?array
    {
        if ($keys === []) {
            return null;
        }
        $rowid = (int) array_key_last($keys);

        return [
            'key' => $keys[$rowid],
            'rowid' => $rowid,
        ];
    }

    /** @param array<int,string> $keys @param array{key:string,rowid:int}|null $token @return list<int> */
    private static function v169_beforeOrAtToken(array $keys, ?array $token): array
    {
        if ($token === null) {
            return [];
        }

        $rowids = [];
        foreach ($keys as $rowid => $key) {
            if (strcmp($key, $token['key']) < 0 || ($key === $token['key'] && $rowid <= $token['rowid'])) {
                $rowids[] = (int) $rowid;
            }
        }

        return $rowids;
    }

    private static function v169_asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array{key:string,rowid:int,bytesHex?:string,encoding?:string}|null $lastYielded
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyDuplicateKeyReplayPlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        ?array $lastYielded = null,
        string $currentSource = 'main.app_settings@170',
        string $nextSource = 'main.app_settings@171',
        int $currentSchemaCookie = 170,
        int $nextSchemaCookie = 171,
    ): array {
        self::v171_assertToken($lastYielded);

        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $current = self::v171_scan($currentRows, $pattern, $escape, $like['range']);
        $next = self::v171_scan($nextRows, $pattern, $escape, $like['range']);

        $currentMatched = self::v171_rowids($current['matched']);
        $nextMatched = self::v171_rowids($next['matched']);
        $currentAfter = self::v171_afterToken($current['matched'], $lastYielded);
        $nextAfter = self::v171_afterToken($next['matched'], $lastYielded);
        $nextBeforeOrAt = self::v171_beforeOrAtToken($next['matched'], $lastYielded);
        $duplicateKeys = self::v171_duplicateKeys($next['matched']);
        $sameRowChanges = self::v171_sameRowChanges($current['matched'], $next['matched']);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($like['range'] === null) {
            $reasons[] = 'full-scan-like-residual';
        }
        if ($currentMatched !== $nextMatched) {
            $reasons[] = 'matched-rowset';
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        foreach ($sameRowChanges as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }
        if ($duplicateKeys !== []) {
            $reasons[] = 'duplicate-rtrim-nocase-key';
        }
        if ($lastYielded === null) {
            $reasons[] = 'no-yield-token';
        }
        if ($lastYielded !== null) {
            foreach ($nextBeforeOrAt as $row) {
                if (!in_array($row['rowid'], $currentMatched, true)) {
                    $reasons[] = 'entered-before-token';
                    break;
                }
            }
        }

        $requiresReprepare = $reasons !== [];

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nextoneSevenOne',
            'operator' => 'LIKE',
            'expression' => 'rtrim(key_name) COLLATE NOCASE',
            'pattern' => $pattern,
            'escape' => $escape,
            'caseSensitiveLike' => false,
            'asciiNocaseOnly' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'prefix' => $like['prefix'],
            'prefixIsAscii' => $like['prefixIsAscii'],
            'indexUsable' => $like['indexUsable'],
            'range' => $like['range'],
            'lastYielded' => $lastYielded,
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'currentMatchedKeys' => self::v171_map($current['matched'], 'key'),
            'nextMatchedKeys' => self::v171_map($next['matched'], 'key'),
            'currentMatchedBytesHex' => self::v171_map($current['matched'], 'bytesHex'),
            'nextMatchedBytesHex' => self::v171_map($next['matched'], 'bytesHex'),
            'currentMatchedEncodings' => self::v171_map($current['matched'], 'encoding'),
            'nextMatchedEncodings' => self::v171_map($next['matched'], 'encoding'),
            'currentAfterTokenRowids' => self::v171_rowids($currentAfter),
            'nextAfterTokenRowids' => self::v171_rowids($nextAfter),
            'nextBeforeOrAtTokenRowids' => self::v171_rowids($nextBeforeOrAt),
            'duplicateRtrimNocaseKeys' => $duplicateKeys,
            'changedKeyRowids' => $sameRowChanges['key-changed'],
            'changedEncodingRowids' => $sameRowChanges['encoding-changed'],
            'changedBytesRowids' => $sameRowChanges['bytes-changed'],
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'replayInvalidationReasons' => array_values(array_unique($reasons)),
            'mustReprepareBeforeReplay' => $requiresReprepare,
            'safeToReplayFromToken' => !$requiresReprepare,
            'replayPlanRowids' => $requiresReprepare ? $nextMatched : self::v171_rowids($nextAfter),
            'replayPlanMode' => $requiresReprepare ? 'reprepare-from-range-start' : 'continue-after-key-rowid-token',
            'tokenIncludesRowidTieBreaker' => true,
            'tokenIncludesByteFingerprint' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-rtrim-expression',
                'sqlite-nocase-like-duplicate-key-replay',
                'sqlite-current-source-nextoneSevenOne',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE matching, RTRIM expression keys, and current-source key/rowid/byte replay diagnostics',
            'non_overlap' => 'adds duplicate RTRIM/NOCASE key replay and byte-fingerprint invalidation after nextOneSixSeven fallback/residual behavior; does not repeat Unicode GLOB ranges, UTF-16 malformed insert guard, RHS pattern trimming, or generic LIKE prefix planning',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{matched:list<array<string,mixed>>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function v171_scan(array $rows, string $pattern, ?string $escape, ?array $range): array
    {
        $matched = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            self::v171_assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['key_name_bytes'], $row['text_encoding']);
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['setting_id'];
                $errors[$row['setting_id']] = $exception->getMessage();
                continue;
            }

            $rtrim = rtrim($text, ' ');
            $key = self::v171_asciiLower($rtrim);
            if ($range !== null && !self::v171_inRange($key, $range)) {
                continue;
            }
            if (!SQLiteDatabase::likeMatches($rtrim, $pattern, $escape, false)) {
                continue;
            }
            $matched[] = [
                'rowid' => $row['setting_id'],
                'text' => $text,
                'rtrimText' => $rtrim,
                'key' => $key,
                'encoding' => self::v171_encodingName($row['text_encoding']),
                'bytesHex' => bin2hex($row['key_name_bytes']),
            ];
        }

        usort($matched, self::v171_sortRows(...));
        sort($malformed);
        ksort($errors);

        return ['matched' => $matched, 'malformedRowids' => $malformed, 'errors' => $errors];
    }

    /** @param array<string,mixed> $row */
    private static function v171_assertRow(array $row): void
    {
        if (!array_key_exists('setting_id', $row) || !is_int($row['setting_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneSevenOne rows require integer setting_id');
        }
        if (!array_key_exists('key_name_bytes', $row) || !is_string($row['key_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneSevenOne rows require key_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneSevenOne rows require integer text_encoding');
        }
    }

    /** @param array{key:string,rowid:int,bytesHex?:string,encoding?:string}|null $token */
    private static function v171_assertToken(?array $token): void
    {
        if ($token === null) {
            return;
        }
        if (!array_key_exists('key', $token) || !is_string($token['key'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneSevenOne token requires string key');
        }
        if (!array_key_exists('rowid', $token) || !is_int($token['rowid'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneSevenOne token requires integer rowid');
        }
    }

    /** @param array{lowerInclusive:string,upperBound:?string} $range */
    private static function v171_inRange(string $key, array $range): bool
    {
        return strcmp($key, $range['lowerInclusive']) >= 0
            && ($range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0);
    }

    /** @param array{key:string,rowid:int} $left @param array{key:string,rowid:int} $right */
    private static function v171_sortRows(array $left, array $right): int
    {
        $comparison = strcmp($left['key'], $right['key']);

        return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
    }

    /** @param list<array{rowid:int}> $rows @return list<int> */
    private static function v171_rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $rows @return array<int,mixed> */
    private static function v171_map(array $rows, string $key): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[$row['rowid']] = $row[$key];
        }

        return $mapped;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array{key:string,rowid:int,bytesHex?:string,encoding?:string}|null $token
     * @return list<array<string,mixed>>
     */
    private static function v171_afterToken(array $rows, ?array $token): array
    {
        if ($token === null) {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => strcmp($row['key'], $token['key']) > 0
                || ($row['key'] === $token['key'] && $row['rowid'] > $token['rowid'])
        ));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array{key:string,rowid:int,bytesHex?:string,encoding?:string}|null $token
     * @return list<array<string,mixed>>
     */
    private static function v171_beforeOrAtToken(array $rows, ?array $token): array
    {
        if ($token === null) {
            return [];
        }

        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => strcmp($row['key'], $token['key']) < 0
                || ($row['key'] === $token['key'] && $row['rowid'] <= $token['rowid'])
        ));
    }

    /** @param list<array{key:string,rowid:int}> $rows @return array<string,list<int>> */
    private static function v171_duplicateKeys(array $rows): array
    {
        $keys = [];
        foreach ($rows as $row) {
            $keys[$row['key']][] = $row['rowid'];
        }

        return array_filter($keys, static fn (array $rowids): bool => count($rowids) > 1);
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array{key-changed:list<int>,encoding-changed:list<int>,bytes-changed:list<int>}
     */
    private static function v171_sameRowChanges(array $currentRows, array $nextRows): array
    {
        $current = [];
        foreach ($currentRows as $row) {
            $current[$row['rowid']] = $row;
        }
        $changes = ['key-changed' => [], 'encoding-changed' => [], 'bytes-changed' => []];
        foreach ($nextRows as $row) {
            $rowid = $row['rowid'];
            if (!isset($current[$rowid])) {
                continue;
            }
            foreach (['key' => 'key-changed', 'encoding' => 'encoding-changed', 'bytesHex' => 'bytes-changed'] as $field => $reason) {
                if ($current[$rowid][$field] !== $row[$field]) {
                    $changes[$reason][] = $rowid;
                }
            }
        }

        foreach ($changes as &$rowids) {
            sort($rowids);
        }

        return $changes;
    }

    private static function v171_encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function v171_asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array{key:string,rowid:int}|null $lastYielded
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyYieldTokenPlan(
        array $currentRows,
        array $nextRows,
        string $currentPatternBytes,
        int $currentPatternEncoding,
        string $nextPatternBytes,
        int $nextPatternEncoding,
        ?string $currentEscapeBytes = null,
        int $currentEscapeEncoding = 1,
        ?string $nextEscapeBytes = null,
        int $nextEscapeEncoding = 1,
        ?array $lastYielded = null,
        string $currentSource = 'main.app_settings@171',
        string $nextSource = 'main.app_settings@172',
        int $currentSchemaCookie = 171,
        int $nextSchemaCookie = 172,
    ): array {
        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyResumePlan(
            $currentRows,
            $nextRows,
            $currentPatternBytes,
            $currentPatternEncoding,
            $nextPatternBytes,
            $nextPatternEncoding,
            $currentEscapeBytes,
            $currentEscapeEncoding,
            $nextEscapeBytes,
            $nextEscapeEncoding,
            $lastYielded,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $currentToken = self::v172_tokenRow($base['currentMatchedKeys'], $lastYielded);
        $nextToken = self::v172_tokenRow($base['nextMatchedKeys'], $lastYielded);
        $yieldedReenteredAfterToken = $lastYielded !== null
            && in_array($lastYielded['rowid'], $base['nextAfterTokenRowids'], true);
        $yieldedMissingInNext = $lastYielded !== null
            && array_key_exists($lastYielded['rowid'], $base['currentMatchedKeys'])
            && !array_key_exists($lastYielded['rowid'], $base['nextMatchedKeys']);

        $yieldTokenReasons = [];
        if ($yieldedReenteredAfterToken) {
            $yieldTokenReasons[] = 'yielded-token-reentered-after-token';
        }
        if ($currentToken !== null && $nextToken !== null && $currentToken['key'] !== $nextToken['key']) {
            $yieldTokenReasons[] = 'yielded-key-changed';
        }
        if ($yieldedMissingInNext) {
            $yieldTokenReasons[] = 'yielded-row-exited';
        }

        $resumeReasons = $base['resumeReasons'];
        if ($yieldedReenteredAfterToken && !in_array('yielded-token-reentered-after-token', $resumeReasons, true)) {
            $resumeReasons[] = 'yielded-token-reentered-after-token';
        }
        $mustReprepare = $resumeReasons !== [];
        $resumeRows = $mustReprepare ? $base['nextMatchedRowids'] : self::v172_withoutRowid($base['nextAfterTokenRowids'], $lastYielded['rowid'] ?? null);

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nextoneSevenTwo',
            'operator' => 'LIKE',
            'expression' => 'rtrim(key_name) COLLATE NOCASE',
            'baseStatus' => $base['status'],
            'currentSource' => $base['currentSource'],
            'nextSource' => $base['nextSource'],
            'currentSchemaCookie' => $base['currentSchemaCookie'],
            'nextSchemaCookie' => $base['nextSchemaCookie'],
            'lastYielded' => $lastYielded,
            'currentTokenRow' => $currentToken,
            'nextTokenRow' => $nextToken,
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'currentAfterTokenRowids' => $base['currentAfterTokenRowids'],
            'nextAfterTokenRowids' => $base['nextAfterTokenRowids'],
            'nextBeforeOrAtTokenRowids' => $base['nextBeforeOrAtTokenRowids'],
            'yieldedReenteredAfterToken' => $yieldedReenteredAfterToken,
            'yieldedMissingInNext' => $yieldedMissingInNext,
            'yieldTokenReasons' => array_values(array_unique($yieldTokenReasons)),
            'baseResumeReasons' => $base['resumeReasons'],
            'resumeReasons' => array_values(array_unique($resumeReasons)),
            'semanticInvalidationReasons' => $base['semanticInvalidationReasons'],
            'byteReprepareReasons' => $base['byteReprepareReasons'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'mustReprepareBeforeResume' => $mustReprepare,
            'safeToResumeFromToken' => !$mustReprepare,
            'resumePlanRowids' => $resumeRows,
            'resumePlanMode' => $mustReprepare ? 'reprepare-from-range-start' : 'continue-after-last-yielded-key-rowid',
            'avoidsDuplicateYieldOfTokenRowid' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'dependencies' => [
                'sqlite-utf16-pattern-normalization',
                'sqlite-nocase-like-rtrim-resume-cursor',
                'sqlite-current-source-yield-token',
                'sqlite-current-source-nextoneSevenTwo',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE/RTRIM resume ordering, and adds yielded-token duplicate prevention diagnostics',
        ];
    }

    /** @param array<int,string> $keys @param array{key:string,rowid:int}|null $token @return array{rowid:int,key:string}|null */
    private static function v172_tokenRow(array $keys, ?array $token): ?array
    {
        if ($token === null || !array_key_exists($token['rowid'], $keys)) {
            return null;
        }

        return [
            'rowid' => $token['rowid'],
            'key' => $keys[$token['rowid']],
        ];
    }

    /** @param list<int> $rowids @return list<int> */
    private static function v172_withoutRowid(array $rowids, ?int $rowid): array
    {
        if ($rowid === null) {
            return $rowids;
        }

        return array_values(array_filter($rowids, static fn (int $candidate): bool => $candidate !== $rowid));
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeySourcePlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        string $currentSource = 'main.app_settings@172',
        string $nextSource = 'main.app_settings@173',
        int $currentSchemaCookie = 172,
        int $nextSchemaCookie = 173,
    ): array {
        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $current = self::v173_scan($currentRows, $pattern, $escape, $like['range']);
        $next = self::v173_scan($nextRows, $pattern, $escape, $like['range']);
        $changes = self::v173_changes($current['decoded'], $next['decoded']);

        $currentCandidateRowids = self::v173_rowids($current['candidates']);
        $nextCandidateRowids = self::v173_rowids($next['candidates']);
        $currentMatchedRowids = self::v173_rowids($current['matched']);
        $nextMatchedRowids = self::v173_rowids($next['matched']);

        $semanticReasons = [];
        if ($currentSource !== $nextSource) {
            $semanticReasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $semanticReasons[] = 'schema-cookie';
        }
        if (!$like['indexUsable']) {
            $semanticReasons[] = 'full-scan-like-residual';
        }
        foreach ([
            'rtrim-key' => $changes['rtrimChangedRowids'],
            'nocase-key' => $changes['nocaseKeyChangedRowids'],
            'residual-result' => $changes['matchChangedRowids'],
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $semanticReasons[] = $reason;
            }
        }
        if ($currentCandidateRowids !== $nextCandidateRowids) {
            $semanticReasons[] = 'candidate-rowset';
        }
        if ($currentMatchedRowids !== $nextMatchedRowids) {
            $semanticReasons[] = 'matched-rowset';
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $semanticReasons[] = 'malformed-text';
        }

        $byteReasons = [];
        foreach ([
            'decoded-text' => $changes['textChangedRowids'],
            'trailing-space-bytes' => $changes['trailingSpaceOnlyRowids'],
            'text-encoding' => $changes['encodingChangedRowids'],
            'encoded-bytes' => $changes['bytesChangedRowids'],
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $byteReasons[] = $reason;
            }
        }

        $byteOnlyReprepare = $byteReasons !== [] && $semanticReasons === [];

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nextoneSevenThree',
            'operator' => 'LIKE',
            'expression' => 'rtrim(key_name) COLLATE NOCASE',
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => 'NOCASE',
            'caseSensitiveLike' => false,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'prefix' => $like['prefix'],
            'prefixIsAscii' => $like['prefixIsAscii'],
            'range' => $like['range'],
            'indexUsable' => $like['indexUsable'],
            'scanMode' => $like['indexUsable'] ? 'nocase-rtrim-range' : 'full-residual-scan',
            'currentOrderRowids' => self::v173_rowids($current['decoded']),
            'nextOrderRowids' => self::v173_rowids($next['decoded']),
            'currentCandidateRowids' => $currentCandidateRowids,
            'nextCandidateRowids' => $nextCandidateRowids,
            'currentMatchedRowids' => $currentMatchedRowids,
            'nextMatchedRowids' => $nextMatchedRowids,
            'currentFalsePositiveRowids' => self::v173_rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::v173_rowids($next['falsePositive']),
            'retainedMatchedRowids' => array_values(array_intersect($currentMatchedRowids, $nextMatchedRowids)),
            'enteredMatchedRowids' => array_values(array_diff($nextMatchedRowids, $currentMatchedRowids)),
            'exitedMatchedRowids' => array_values(array_diff($currentMatchedRowids, $nextMatchedRowids)),
            'currentTexts' => self::v173_map($current['decoded'], 'text'),
            'nextTexts' => self::v173_map($next['decoded'], 'text'),
            'currentRtrimKeys' => self::v173_map($current['decoded'], 'rtrimKey'),
            'nextRtrimKeys' => self::v173_map($next['decoded'], 'rtrimKey'),
            'currentNocaseKeys' => self::v173_map($current['decoded'], 'nocaseKey'),
            'nextNocaseKeys' => self::v173_map($next['decoded'], 'nocaseKey'),
            'currentEncodings' => self::v173_map($current['decoded'], 'encoding'),
            'nextEncodings' => self::v173_map($next['decoded'], 'encoding'),
            'currentBytesHex' => self::v173_map($current['decoded'], 'bytesHex'),
            'nextBytesHex' => self::v173_map($next['decoded'], 'bytesHex'),
            'changedTextRowids' => $changes['textChangedRowids'],
            'changedTrailingSpaceOnlyRowids' => $changes['trailingSpaceOnlyRowids'],
            'changedRtrimKeyRowids' => $changes['rtrimChangedRowids'],
            'changedNocaseKeyRowids' => $changes['nocaseKeyChangedRowids'],
            'changedEncodingRowids' => $changes['encodingChangedRowids'],
            'changedBytesRowids' => $changes['bytesChangedRowids'],
            'changedResidualRowids' => $changes['matchChangedRowids'],
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'byteReprepareReasons' => $byteReasons,
            'semanticInvalidationReasons' => $semanticReasons,
            'byteOnlyReprepare' => $byteOnlyReprepare,
            'cursorInvalidated' => $semanticReasons !== [],
            'cursorReusable' => $byteOnlyReprepare || ($semanticReasons === [] && $like['indexUsable']),
            'safeToKeepYieldOrder' => $semanticReasons === [],
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-rtrim-expression-key',
                'sqlite-like-nocase-residual',
                'sqlite-current-source-nextoneSevenThree',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII-only RTRIM/NOCASE LIKE matching, and current-source byte-vs-semantic invalidation diagnostics',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{decoded:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function v173_scan(array $rows, string $pattern, ?string $escape, ?array $range): array
    {
        $decoded = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            self::v173_assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['key_name_bytes'], $row['text_encoding']);
                $rtrim = rtrim($text, ' ');
                $decoded[] = [
                    'rowid' => $row['setting_id'],
                    'text' => $text,
                    'rtrimKey' => $rtrim,
                    'nocaseKey' => self::v173_asciiLower($rtrim),
                    'encoding' => self::v173_encodingName($row['text_encoding']),
                    'bytesHex' => bin2hex($row['key_name_bytes']),
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['setting_id'];
                $errors[$row['setting_id']] = $exception->getMessage();
            }
        }

        usort($decoded, self::v173_sortRows(...));
        sort($malformed);
        ksort($errors);

        $candidates = [];
        $matched = [];
        $falsePositive = [];
        foreach ($decoded as $entry) {
            if ($range !== null && !self::v173_inRange($entry['nocaseKey'], $range)) {
                continue;
            }
            $entry['residualMatch'] = SQLiteDatabase::likeMatches($entry['rtrimKey'], $pattern, $escape, false);
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
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row */
    private static function v173_assertRow(array $row): void
    {
        if (!array_key_exists('setting_id', $row) || !is_int($row['setting_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneSevenThree rows require integer setting_id');
        }
        if (!array_key_exists('key_name_bytes', $row) || !is_string($row['key_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneSevenThree rows require key_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneSevenThree rows require integer text_encoding');
        }
    }

    /** @param ?array{lowerInclusive:string,upperBound:?string} $range */
    private static function v173_inRange(string $key, ?array $range): bool
    {
        if ($range === null || strcmp($key, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0;
    }

    /** @param array{nocaseKey:string,rowid:int} $left @param array{nocaseKey:string,rowid:int} $right */
    private static function v173_sortRows(array $left, array $right): int
    {
        $comparison = strcmp($left['nocaseKey'], $right['nocaseKey']);

        return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
    }

    /** @param list<array{rowid:int}> $rows @return list<int> */
    private static function v173_rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $rows @return array<int,mixed> */
    private static function v173_map(array $rows, string $key): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[$row['rowid']] = $row[$key];
        }

        return $mapped;
    }

    /**
     * @param list<array{rowid:int,text:string,rtrimKey:string,nocaseKey:string,encoding:string,bytesHex:string,residualMatch?:bool}> $currentRows
     * @param list<array{rowid:int,text:string,rtrimKey:string,nocaseKey:string,encoding:string,bytesHex:string,residualMatch?:bool}> $nextRows
     * @return array{textChangedRowids:list<int>,trailingSpaceOnlyRowids:list<int>,rtrimChangedRowids:list<int>,nocaseKeyChangedRowids:list<int>,encodingChangedRowids:list<int>,bytesChangedRowids:list<int>,matchChangedRowids:list<int>}
     */
    private static function v173_changes(array $currentRows, array $nextRows): array
    {
        $current = [];
        foreach ($currentRows as $row) {
            $current[$row['rowid']] = $row;
        }

        $changes = [
            'textChangedRowids' => [],
            'trailingSpaceOnlyRowids' => [],
            'rtrimChangedRowids' => [],
            'nocaseKeyChangedRowids' => [],
            'encodingChangedRowids' => [],
            'bytesChangedRowids' => [],
            'matchChangedRowids' => [],
        ];
        foreach ($nextRows as $row) {
            $rowid = $row['rowid'];
            if (!isset($current[$rowid])) {
                continue;
            }
            if ($current[$rowid]['text'] !== $row['text']) {
                $changes['textChangedRowids'][] = $rowid;
                if ($current[$rowid]['rtrimKey'] === $row['rtrimKey']) {
                    $changes['trailingSpaceOnlyRowids'][] = $rowid;
                }
            }
            if ($current[$rowid]['rtrimKey'] !== $row['rtrimKey']) {
                $changes['rtrimChangedRowids'][] = $rowid;
            }
            if ($current[$rowid]['nocaseKey'] !== $row['nocaseKey']) {
                $changes['nocaseKeyChangedRowids'][] = $rowid;
            }
            if ($current[$rowid]['encoding'] !== $row['encoding']) {
                $changes['encodingChangedRowids'][] = $rowid;
            }
            if ($current[$rowid]['bytesHex'] !== $row['bytesHex']) {
                $changes['bytesChangedRowids'][] = $rowid;
            }
            if (($current[$rowid]['residualMatch'] ?? null) !== ($row['residualMatch'] ?? null)) {
                $changes['matchChangedRowids'][] = $rowid;
            }
        }

        foreach ($changes as &$rowids) {
            $rowids = array_values(array_unique($rowids));
            sort($rowids);
        }
        unset($rowids);

        return $changes;
    }

    private static function v173_asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    private static function v173_encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array{key:string,rowid:int,bytesHex?:string,encoding?:string}|null $lastYielded
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyTokenFingerprintPlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        ?array $lastYielded = null,
        string $currentSource = 'main.app_settings@174',
        string $nextSource = 'main.app_settings@175',
        int $currentSchemaCookie = 174,
        int $nextSchemaCookie = 175,
    ): array {
        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyDuplicateKeyReplayPlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escape,
            $lastYielded,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $currentToken = self::v175_tokenFingerprint($base['currentMatchedBytesHex'], $base['currentMatchedEncodings'], $lastYielded);
        $nextToken = self::v175_tokenFingerprint($base['nextMatchedBytesHex'], $base['nextMatchedEncodings'], $lastYielded);
        $tokenFingerprintReasons = [];
        if ($lastYielded !== null && $nextToken === null) {
            $tokenFingerprintReasons[] = 'yielded-token-row-exited';
        }
        if ($lastYielded !== null && $nextToken !== null) {
            if (array_key_exists('bytesHex', $lastYielded) && $lastYielded['bytesHex'] !== $nextToken['bytesHex']) {
                $tokenFingerprintReasons[] = 'yielded-token-bytes-changed';
            }
            if (array_key_exists('encoding', $lastYielded) && $lastYielded['encoding'] !== $nextToken['encoding']) {
                $tokenFingerprintReasons[] = 'yielded-token-encoding-changed';
            }
        }
        if ($currentToken !== null && $nextToken !== null && $currentToken['bytesHex'] !== $nextToken['bytesHex']) {
            $tokenFingerprintReasons[] = 'current-next-token-bytes-changed';
        }
        if ($currentToken !== null && $nextToken !== null && $currentToken['encoding'] !== $nextToken['encoding']) {
            $tokenFingerprintReasons[] = 'current-next-token-encoding-changed';
        }

        $replayInvalidationReasons = array_values(array_unique(array_merge(
            $base['replayInvalidationReasons'],
            $tokenFingerprintReasons,
        )));
        $mustReprepare = $replayInvalidationReasons !== [];

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nextoneSevenFive',
            'operator' => 'LIKE',
            'expression' => 'rtrim(key_name) COLLATE NOCASE',
            'baseStatus' => $base['status'],
            'pattern' => $base['pattern'],
            'escape' => $base['escape'],
            'currentSource' => $base['currentSource'],
            'nextSource' => $base['nextSource'],
            'currentSchemaCookie' => $base['currentSchemaCookie'],
            'nextSchemaCookie' => $base['nextSchemaCookie'],
            'prefix' => $base['prefix'],
            'range' => $base['range'],
            'lastYielded' => $lastYielded,
            'currentTokenFingerprint' => $currentToken,
            'nextTokenFingerprint' => $nextToken,
            'tokenFingerprintReasons' => array_values(array_unique($tokenFingerprintReasons)),
            'baseReplayInvalidationReasons' => $base['replayInvalidationReasons'],
            'replayInvalidationReasons' => $replayInvalidationReasons,
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'currentMatchedBytesHex' => $base['currentMatchedBytesHex'],
            'nextMatchedBytesHex' => $base['nextMatchedBytesHex'],
            'currentMatchedEncodings' => $base['currentMatchedEncodings'],
            'nextMatchedEncodings' => $base['nextMatchedEncodings'],
            'duplicateRtrimNocaseKeys' => $base['duplicateRtrimNocaseKeys'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'mustReprepareBeforeReplay' => $mustReprepare,
            'safeToReplayFromToken' => !$mustReprepare,
            'replayPlanRowids' => $mustReprepare ? $base['nextMatchedRowids'] : $base['nextAfterTokenRowids'],
            'replayPlanMode' => $mustReprepare ? 'reprepare-from-range-start' : 'continue-after-key-rowid-byte-token',
            'tokenIncludesRowidTieBreaker' => true,
            'tokenIncludesByteFingerprint' => true,
            'tokenFingerprintVerifiedAgainstNextSource' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-nocase-like-rtrim-replay',
                'sqlite-yield-token-byte-fingerprint',
                'sqlite-current-source-nextoneSevenFive',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, NOCASE LIKE/RTRIM replay diagnostics, and adds byte-fingerprint validation for yielded tokens',
            'non_overlap' => 'adds yielded-token byte/encoding fingerprint validation on top of nextOneSevenOne duplicate-key replay; avoids accepted UTF-16 row matching, pattern-byte decoding, RHS RTRIM, malformed insert guards, Unicode GLOB ranges, and storage/planner clusters',
        ];
    }

    /**
     * @param array<int,string> $bytesByRowid
     * @param array<int,string> $encodingByRowid
     * @param array{key:string,rowid:int,bytesHex?:string,encoding?:string}|null $token
     * @return array{rowid:int,bytesHex:string,encoding:string}|null
     */
    private static function v175_tokenFingerprint(array $bytesByRowid, array $encodingByRowid, ?array $token): ?array
    {
        if ($token === null || !array_key_exists($token['rowid'], $bytesByRowid)) {
            return null;
        }

        return [
            'rowid' => $token['rowid'],
            'bytesHex' => $bytesByRowid[$token['rowid']],
            'encoding' => $encodingByRowid[$token['rowid']],
        ];
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array{key:string,rowid:int}|null $lastYielded
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyPeerYieldPlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        ?array $lastYielded = null,
        int $pageSize = 4,
        string $currentSource = 'main.app_settings@175',
        string $nextSource = 'main.app_settings@176',
        int $currentSchemaCookie = 175,
        int $nextSchemaCookie = 176,
    ): array {
        self::v176_assertLastYielded($lastYielded);
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneSevenSix yield page size must be positive');
        }

        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $current = self::v176_scan($currentRows, $pattern, $escape, $like['range']);
        $next = self::v176_scan($nextRows, $pattern, $escape, $like['range']);

        $currentMatched = self::v176_matchedIndex($current['matched']);
        $nextMatched = self::v176_matchedIndex($next['matched']);
        $currentMatchedRowids = array_keys($currentMatched);
        $nextMatchedRowids = array_keys($nextMatched);
        $nextAfterToken = self::v176_afterToken($nextMatched, $lastYielded);
        $yieldedRowids = array_slice($nextAfterToken, 0, $pageSize);
        $deferredRowids = array_slice($nextAfterToken, $pageSize);
        $highWaterToken = self::v176_tokenForLast($nextMatched, $yieldedRowids);
        $duplicatePeerGroups = self::v176_peerGroups($nextMatched);
        $peerGroupsStraddlingToken = self::v176_straddlingPeerGroups($duplicatePeerGroups, $lastYielded);
        $peerGroupsStraddlingYield = self::v176_straddlingYieldGroups($duplicatePeerGroups, $yieldedRowids, $deferredRowids);
        $currentPeerGroups = self::v176_peerGroups($currentMatched);
        $peerGroupChanges = self::v176_peerGroupChanges($currentPeerGroups, $duplicatePeerGroups);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if (!$like['indexUsable']) {
            $reasons[] = 'unusable-nocase-prefix-range';
        }
        if ($currentMatchedRowids !== $nextMatchedRowids) {
            $reasons[] = 'matched-rowset';
        }
        if ($peerGroupChanges !== []) {
            $reasons[] = 'peer-group-rowid-order';
        }
        if ($peerGroupsStraddlingToken !== []) {
            $reasons[] = 'peer-group-straddles-resume-token';
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nextoneSevenSix',
            'operator' => 'LIKE',
            'expression' => 'rtrim(key_name) COLLATE NOCASE',
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => 'NOCASE',
            'caseSensitiveLike' => false,
            'asciiNocaseOnly' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'prefix' => $like['prefix'],
            'range' => $like['range'],
            'indexUsable' => $like['indexUsable'],
            'currentOrderRowids' => self::v176_rowids($current['decoded']),
            'nextOrderRowids' => self::v176_rowids($next['decoded']),
            'currentMatchedRowids' => $currentMatchedRowids,
            'nextMatchedRowids' => $nextMatchedRowids,
            'currentMatchedKeys' => self::v176_keys($currentMatched),
            'nextMatchedKeys' => self::v176_keys($nextMatched),
            'currentDuplicatePeerGroups' => $currentPeerGroups,
            'nextDuplicatePeerGroups' => $duplicatePeerGroups,
            'peerGroupChanges' => $peerGroupChanges,
            'lastYielded' => $lastYielded,
            'nextAfterTokenRowids' => $nextAfterToken,
            'pageSize' => $pageSize,
            'yieldedRowids' => $yieldedRowids,
            'deferredRowids' => $deferredRowids,
            'highWaterToken' => $highWaterToken,
            'hasMore' => $deferredRowids !== [],
            'peerGroupsStraddlingToken' => $peerGroupsStraddlingToken,
            'peerGroupsStraddlingYieldPage' => $peerGroupsStraddlingYield,
            'usesRowidTieBreaker' => true,
            'safeToResumeInsidePeerGroup' => $peerGroupsStraddlingToken === [],
            'safeToPersistHighWaterToken' => $highWaterToken !== null && $peerGroupsStraddlingYield === [],
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => array_values(array_unique($reasons)),
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-rtrim-expression-key',
                'sqlite-nocase-rowid-tiebreaker',
                'sqlite-current-source-nextoneSevenSix',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE matching, RTRIM expression keys, and adds rowid-tied duplicate peer yield diagnostics',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{decoded:list<array<string,mixed>>,matched:list<array<string,mixed>>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function v176_scan(array $rows, string $pattern, ?string $escape, ?array $range): array
    {
        $decoded = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            self::v176_assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['key_name_bytes'], $row['text_encoding']);
                $rtrim = rtrim($text, ' ');
                $decoded[] = [
                    'rowid' => $row['setting_id'],
                    'text' => $text,
                    'rtrimKey' => $rtrim,
                    'nocaseKey' => self::v176_asciiLower($rtrim),
                    'encoding' => self::v176_encodingName($row['text_encoding']),
                    'bytesHex' => bin2hex($row['key_name_bytes']),
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['setting_id'];
                $errors[$row['setting_id']] = $exception->getMessage();
            }
        }

        usort($decoded, self::v176_sortRows(...));
        sort($malformed);
        ksort($errors);

        $matched = [];
        foreach ($decoded as $entry) {
            if (!self::v176_inRange($entry['nocaseKey'], $range)) {
                continue;
            }
            if (!SQLiteDatabase::likeMatches($entry['rtrimKey'], $pattern, $escape, false)) {
                continue;
            }
            $matched[] = $entry;
        }

        return [
            'decoded' => $decoded,
            'matched' => $matched,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row */
    private static function v176_assertRow(array $row): void
    {
        if (!array_key_exists('setting_id', $row) || !is_int($row['setting_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneSevenSix rows require integer setting_id');
        }
        if (!array_key_exists('key_name_bytes', $row) || !is_string($row['key_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneSevenSix rows require key_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneSevenSix rows require integer text_encoding');
        }
    }

    /** @param array{key:string,rowid:int}|null $lastYielded */
    private static function v176_assertLastYielded(?array $lastYielded): void
    {
        if ($lastYielded === null) {
            return;
        }
        if (!array_key_exists('key', $lastYielded) || !is_string($lastYielded['key'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneSevenSix resume token requires string key');
        }
        if (!array_key_exists('rowid', $lastYielded) || !is_int($lastYielded['rowid'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneSevenSix resume token requires integer rowid');
        }
    }

    /** @param ?array{lowerInclusive:string,upperBound:?string} $range */
    private static function v176_inRange(string $key, ?array $range): bool
    {
        if ($range === null || strcmp($key, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0;
    }

    /** @param array{nocaseKey:string,rowid:int} $left @param array{nocaseKey:string,rowid:int} $right */
    private static function v176_sortRows(array $left, array $right): int
    {
        $comparison = strcmp($left['nocaseKey'], $right['nocaseKey']);

        return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
    }

    /** @param list<array{rowid:int}> $rows @return list<int> */
    private static function v176_rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $matched @return array<int,array{key:string,rowid:int,encoding:string,bytesHex:string}> */
    private static function v176_matchedIndex(array $matched): array
    {
        $indexed = [];
        foreach ($matched as $row) {
            $indexed[$row['rowid']] = [
                'key' => $row['nocaseKey'],
                'rowid' => $row['rowid'],
                'encoding' => $row['encoding'],
                'bytesHex' => $row['bytesHex'],
            ];
        }

        return $indexed;
    }

    /** @param array<int,array{key:string}> $matched @return array<int,string> */
    private static function v176_keys(array $matched): array
    {
        $keys = [];
        foreach ($matched as $rowid => $row) {
            $keys[$rowid] = $row['key'];
        }

        return $keys;
    }

    /** @param array<int,array{key:string,rowid:int}> $matched @param array{key:string,rowid:int}|null $token @return list<int> */
    private static function v176_afterToken(array $matched, ?array $token): array
    {
        if ($token === null) {
            return array_map('intval', array_keys($matched));
        }

        $rowids = [];
        foreach ($matched as $rowid => $entry) {
            if (strcmp($entry['key'], $token['key']) > 0 || ($entry['key'] === $token['key'] && $entry['rowid'] > $token['rowid'])) {
                $rowids[] = (int) $rowid;
            }
        }

        return $rowids;
    }

    /** @param array<int,array{key:string,rowid:int}> $matched @param list<int> $rowids @return array{key:string,rowid:int}|null */
    private static function v176_tokenForLast(array $matched, array $rowids): ?array
    {
        if ($rowids === []) {
            return null;
        }
        $rowid = $rowids[array_key_last($rowids)];

        return [
            'key' => $matched[$rowid]['key'],
            'rowid' => $rowid,
        ];
    }

    /** @param array<int,array{key:string,rowid:int,encoding:string,bytesHex:string}> $matched @return array<string,array{key:string,rowids:list<int>,encodings:array<int,string>,bytesHex:array<int,string>}> */
    private static function v176_peerGroups(array $matched): array
    {
        $groups = [];
        foreach ($matched as $rowid => $entry) {
            $key = $entry['key'];
            $groups[$key]['key'] = $key;
            $groups[$key]['rowids'][] = (int) $rowid;
            $groups[$key]['encodings'][(int) $rowid] = $entry['encoding'];
            $groups[$key]['bytesHex'][(int) $rowid] = $entry['bytesHex'];
        }

        return array_filter($groups, static fn (array $group): bool => count($group['rowids']) > 1);
    }

    /**
     * @param array<string,array{rowids:list<int>}> $current
     * @param array<string,array{rowids:list<int>}> $next
     * @return array<string,array{currentRowids:list<int>,nextRowids:list<int>}>
     */
    private static function v176_peerGroupChanges(array $current, array $next): array
    {
        $changes = [];
        foreach (array_unique(array_merge(array_keys($current), array_keys($next))) as $key) {
            $currentRowids = $current[$key]['rowids'] ?? [];
            $nextRowids = $next[$key]['rowids'] ?? [];
            if ($currentRowids !== $nextRowids) {
                $changes[$key] = [
                    'currentRowids' => $currentRowids,
                    'nextRowids' => $nextRowids,
                ];
            }
        }

        return $changes;
    }

    /**
     * @param array<string,array{key:string,rowids:list<int>}> $groups
     * @param array{key:string,rowid:int}|null $token
     * @return array<string,array{beforeOrAt:list<int>,after:list<int>}>
     */
    private static function v176_straddlingPeerGroups(array $groups, ?array $token): array
    {
        if ($token === null) {
            return [];
        }

        $straddling = [];
        foreach ($groups as $key => $group) {
            if ($key !== $token['key']) {
                continue;
            }
            $before = array_values(array_filter($group['rowids'], static fn (int $rowid): bool => $rowid <= $token['rowid']));
            $after = array_values(array_filter($group['rowids'], static fn (int $rowid): bool => $rowid > $token['rowid']));
            if ($before !== [] && $after !== []) {
                $straddling[$key] = [
                    'beforeOrAt' => $before,
                    'after' => $after,
                ];
            }
        }

        return $straddling;
    }

    /**
     * @param array<string,array{rowids:list<int>}> $groups
     * @param list<int> $yielded
     * @param list<int> $deferred
     * @return array<string,array{yielded:list<int>,deferred:list<int>}>
     */
    private static function v176_straddlingYieldGroups(array $groups, array $yielded, array $deferred): array
    {
        $straddling = [];
        foreach ($groups as $key => $group) {
            $yieldedPeers = array_values(array_intersect($group['rowids'], $yielded));
            $deferredPeers = array_values(array_intersect($group['rowids'], $deferred));
            if ($yieldedPeers !== [] && $deferredPeers !== []) {
                $straddling[$key] = [
                    'yielded' => $yieldedPeers,
                    'deferred' => $deferredPeers,
                ];
            }
        }

        return $straddling;
    }

    private static function v176_encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function v176_asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyUnicodeWildcardPlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        string $currentSource = 'main.app_settings@176',
        string $nextSource = 'main.app_settings@177',
        int $currentSchemaCookie = 176,
        int $nextSchemaCookie = 177,
    ): array {
        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $current = self::v177_scan($currentRows, $pattern, $escape, $like['range']);
        $next = self::v177_scan($nextRows, $pattern, $escape, $like['range']);

        $currentCandidateRowids = self::v177_rowids($current['candidates']);
        $nextCandidateRowids = self::v177_rowids($next['candidates']);
        $currentMatchedRowids = self::v177_rowids($current['matched']);
        $nextMatchedRowids = self::v177_rowids($next['matched']);
        $changes = self::v177_changes($current['decoded'], $next['decoded']);
        $changes['residualChangedRowids'] = self::v177_residualChanges($current['candidates'], $next['candidates']);

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
            'character-count' => $changes['characterCountChangedRowids'],
            'utf16-code-unit-count' => $changes['utf16CodeUnitCountChangedRowids'],
            'encoded-bytes' => $changes['bytesChangedRowids'],
            'residual-result' => $changes['residualChangedRowids'],
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        if ($currentCandidateRowids !== $nextCandidateRowids) {
            $reasons[] = 'candidate-rowset';
        }
        if ($currentMatchedRowids !== $nextMatchedRowids) {
            $reasons[] = 'matched-rowset';
        }
        if ($current['byteWildcardMismatchRowids'] !== [] || $next['byteWildcardMismatchRowids'] !== []) {
            $reasons[] = 'unicode-wildcard-recheck';
        }

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nextoneSevenSeven',
            'operator' => 'LIKE',
            'expression' => 'rtrim(key_name) COLLATE NOCASE LIKE ?',
            'pattern' => $pattern,
            'escape' => $escape,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'likePlan' => $like,
            'prefix' => $like['prefix'],
            'range' => $like['range'],
            'indexUsable' => $like['indexUsable'],
            'currentCandidateRowids' => $currentCandidateRowids,
            'nextCandidateRowids' => $nextCandidateRowids,
            'currentMatchedRowids' => $currentMatchedRowids,
            'nextMatchedRowids' => $nextMatchedRowids,
            'currentFalsePositiveRowids' => self::v177_rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::v177_rowids($next['falsePositive']),
            'currentUnicodeWildcardRowids' => $current['unicodeWildcardRowids'],
            'nextUnicodeWildcardRowids' => $next['unicodeWildcardRowids'],
            'currentByteWildcardMismatchRowids' => $current['byteWildcardMismatchRowids'],
            'nextByteWildcardMismatchRowids' => $next['byteWildcardMismatchRowids'],
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'currentTexts' => self::v177_map($current['decoded'], 'text'),
            'nextTexts' => self::v177_map($next['decoded'], 'text'),
            'currentRtrimTexts' => self::v177_map($current['decoded'], 'rtrimText'),
            'nextRtrimTexts' => self::v177_map($next['decoded'], 'rtrimText'),
            'currentNocaseKeys' => self::v177_map($current['decoded'], 'nocaseKey'),
            'nextNocaseKeys' => self::v177_map($next['decoded'], 'nocaseKey'),
            'currentCharacterCounts' => self::v177_map($current['decoded'], 'characterCount'),
            'nextCharacterCounts' => self::v177_map($next['decoded'], 'characterCount'),
            'currentUtf16CodeUnitCounts' => self::v177_map($current['decoded'], 'utf16CodeUnitCount'),
            'nextUtf16CodeUnitCounts' => self::v177_map($next['decoded'], 'utf16CodeUnitCount'),
            'currentResidualMatches' => self::v177_map($current['candidates'], 'residualMatch'),
            'nextResidualMatches' => self::v177_map($next['candidates'], 'residualMatch'),
            'currentByteWildcardMatches' => self::v177_map($current['candidates'], 'byteWildcardMatch'),
            'nextByteWildcardMatches' => self::v177_map($next['candidates'], 'byteWildcardMatch'),
            'changedTextRowids' => $changes['textChangedRowids'],
            'changedRtrimRowids' => $changes['rtrimChangedRowids'],
            'changedNocaseKeyRowids' => $changes['nocaseKeyChangedRowids'],
            'changedCharacterCountRowids' => $changes['characterCountChangedRowids'],
            'changedUtf16CodeUnitCountRowids' => $changes['utf16CodeUnitCountChangedRowids'],
            'changedBytesRowids' => $changes['bytesChangedRowids'],
            'changedResidualRowids' => $changes['residualChangedRowids'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => array_values(array_unique($reasons)),
            'likeUnderscoreConsumesOneDecodedCharacter' => true,
            'utf16SurrogatePairIsOneLikeCharacter' => true,
            'byteLengthCannotDriveLikeUnderscore' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-unicode-character-wildcard',
                'sqlite-like-nocase-prefix-range',
                'sqlite-current-source-nextoneSevenSeven',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, LIKE character matching, NOCASE/RTRIM prefix planning, and adds Unicode wildcard recheck diagnostics for current-source cursor transitions',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{decoded:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,unicodeWildcardRowids:list<int>,byteWildcardMismatchRowids:list<int>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function v177_scan(array $rows, string $pattern, ?string $escape, ?array $range): array
    {
        $decoded = [];
        $unicodeWildcard = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            self::v177_assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['key_name_bytes'], $row['text_encoding']);
                $rtrim = rtrim($text, ' ');
                $characterCount = self::v177_characterCount($rtrim);
                $utf16CodeUnitCount = strlen(SQLiteEncodingCollationSourceCursor::encodeText($rtrim, $row['text_encoding'])) / 2;
                if ($characterCount !== $utf16CodeUnitCount) {
                    $unicodeWildcard[] = $row['setting_id'];
                }
                $decoded[] = [
                    'rowid' => $row['setting_id'],
                    'text' => $text,
                    'rtrimText' => $rtrim,
                    'nocaseKey' => self::v177_asciiLower($rtrim),
                    'characterCount' => $characterCount,
                    'utf16CodeUnitCount' => (int) $utf16CodeUnitCount,
                    'bytesHex' => bin2hex($row['key_name_bytes']),
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['setting_id'];
                $errors[$row['setting_id']] = $exception->getMessage();
            }
        }

        usort($decoded, self::v177_sortRows(...));
        sort($unicodeWildcard);
        sort($malformed);
        ksort($errors);

        $candidates = [];
        $matched = [];
        $falsePositive = [];
        $byteWildcardMismatch = [];
        foreach ($decoded as $entry) {
            if (!self::v177_inRange($entry['nocaseKey'], $range)) {
                continue;
            }
            $entry['residualMatch'] = SQLiteDatabase::likeMatches($entry['rtrimText'], $pattern, $escape, false);
            $entry['byteWildcardMatch'] = self::v177_byteWildcardLikeMatches($entry['rtrimText'], $pattern, $escape);
            $candidates[] = $entry;
            if ($entry['residualMatch']) {
                $matched[] = $entry;
            } else {
                $falsePositive[] = $entry;
            }
            if ($entry['residualMatch'] !== $entry['byteWildcardMatch']) {
                $byteWildcardMismatch[] = $entry['rowid'];
            }
        }
        sort($byteWildcardMismatch);

        return [
            'decoded' => $decoded,
            'candidates' => $candidates,
            'matched' => $matched,
            'falsePositive' => $falsePositive,
            'unicodeWildcardRowids' => $unicodeWildcard,
            'byteWildcardMismatchRowids' => $byteWildcardMismatch,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row */
    private static function v177_assertRow(array $row): void
    {
        if (!array_key_exists('setting_id', $row) || !is_int($row['setting_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneSevenSeven rows require integer setting_id');
        }
        if (!array_key_exists('key_name_bytes', $row) || !is_string($row['key_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneSevenSeven rows require key_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneSevenSeven rows require integer text_encoding');
        }
    }

    /** @param ?array{lowerInclusive:string,upperBound:?string} $range */
    private static function v177_inRange(string $key, ?array $range): bool
    {
        if ($range === null || strcmp($key, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0;
    }

    /** @param array{nocaseKey:string,rowid:int} $left @param array{nocaseKey:string,rowid:int} $right */
    private static function v177_sortRows(array $left, array $right): int
    {
        $comparison = strcmp($left['nocaseKey'], $right['nocaseKey']);

        return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
    }

    /** @param list<array{rowid:int}> $rows @return list<int> */
    private static function v177_rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $rows @return array<int,mixed> */
    private static function v177_map(array $rows, string $key): array
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
     * @return array<string,list<int>>
     */
    private static function v177_changes(array $current, array $next): array
    {
        $currentByRowid = self::v177_byRowid($current);
        $nextByRowid = self::v177_byRowid($next);

        return [
            'textChangedRowids' => self::v177_changed($currentByRowid, $nextByRowid, 'text'),
            'rtrimChangedRowids' => self::v177_changed($currentByRowid, $nextByRowid, 'rtrimText'),
            'nocaseKeyChangedRowids' => self::v177_changed($currentByRowid, $nextByRowid, 'nocaseKey'),
            'characterCountChangedRowids' => self::v177_changed($currentByRowid, $nextByRowid, 'characterCount'),
            'utf16CodeUnitCountChangedRowids' => self::v177_changed($currentByRowid, $nextByRowid, 'utf16CodeUnitCount'),
            'bytesChangedRowids' => self::v177_changed($currentByRowid, $nextByRowid, 'bytesHex'),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private static function v177_byRowid(array $rows): array
    {
        $byRowid = [];
        foreach ($rows as $row) {
            $byRowid[$row['rowid']] = $row;
        }

        return $byRowid;
    }

    /** @param array<int,array<string,mixed>> $current @param array<int,array<string,mixed>> $next @return list<int> */
    private static function v177_changed(array $current, array $next, string $key): array
    {
        $rowids = array_values(array_intersect(array_keys($current), array_keys($next)));
        sort($rowids);
        $changed = [];
        foreach ($rowids as $rowid) {
            if (($current[$rowid][$key] ?? null) !== ($next[$rowid][$key] ?? null)) {
                $changed[] = (int) $rowid;
            }
        }

        return $changed;
    }

    /** @param list<array<string,mixed>> $current @param list<array<string,mixed>> $next @return list<int> */
    private static function v177_residualChanges(array $current, array $next): array
    {
        $currentByRowid = self::v177_byRowid($current);
        $nextByRowid = self::v177_byRowid($next);
        $rowids = array_values(array_intersect(array_keys($currentByRowid), array_keys($nextByRowid)));
        sort($rowids);
        $changed = [];
        foreach ($rowids as $rowid) {
            if (($currentByRowid[$rowid]['residualMatch'] ?? null) !== ($nextByRowid[$rowid]['residualMatch'] ?? null)) {
                $changed[] = (int) $rowid;
            }
        }

        return $changed;
    }

    private static function v177_characterCount(string $value): int
    {
        return count(self::v177_characters($value));
    }

    /** @return list<string> */
    private static function v177_characters(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($characters)) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneSevenSeven decoded text must be valid UTF-8');
        }

        return $characters;
    }

    private static function v177_byteWildcardLikeMatches(string $value, string $pattern, ?string $escape): bool
    {
        if ($escape !== null && strlen($escape) !== 1) {
            return SQLiteDatabase::likeMatches($value, $pattern, $escape, false);
        }

        $regex = '';
        $length = strlen($pattern);
        for ($offset = 0; $offset < $length; $offset++) {
            $character = $pattern[$offset];
            if ($escape !== null && $character === $escape) {
                $offset++;
                if ($offset >= $length) {
                    return false;
                }
                $regex .= preg_quote($pattern[$offset], '/');
                continue;
            }
            if ($character === '%') {
                $regex .= '.*';
                continue;
            }
            if ($character === '_') {
                $regex .= '.';
                continue;
            }
            $regex .= preg_quote($character, '/');
        }

        return preg_match('/^' . $regex . '$/s', self::v177_asciiLower($value)) === 1;
    }

    private static function v177_asciiLower(string $value): string
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

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array{key:string,rowid:int,bytesHex?:string,encoding?:string,keyBytes?:string,keyEncoding?:int|string}|null $lastYielded
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyCanonicalTokenPlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        ?array $lastYielded = null,
        string $currentSource = 'main.app_settings@177',
        string $nextSource = 'main.app_settings@178',
        int $currentSchemaCookie = 177,
        int $nextSchemaCookie = 178,
    ): array {
        $token = self::v178_canonicalizeToken($lastYielded);
        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyTokenFingerprintPlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escape,
            $token['normalized'],
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $tokenReasons = $token['reasons'];
        $replayInvalidationReasons = array_values(array_unique(array_merge(
            $base['replayInvalidationReasons'],
            $tokenReasons,
        )));
        $mustReprepare = $replayInvalidationReasons !== [];

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nextoneSevenEight',
            'operator' => 'LIKE',
            'expression' => 'rtrim(key_name) COLLATE NOCASE',
            'baseStatus' => $base['status'],
            'pattern' => $base['pattern'],
            'escape' => $base['escape'],
            'currentSource' => $base['currentSource'],
            'nextSource' => $base['nextSource'],
            'currentSchemaCookie' => $base['currentSchemaCookie'],
            'nextSchemaCookie' => $base['nextSchemaCookie'],
            'prefix' => $base['prefix'],
            'range' => $base['range'],
            'rawLastYielded' => $lastYielded,
            'normalizedLastYielded' => $token['normalized'],
            'tokenRawText' => $token['rawText'],
            'tokenRtrimText' => $token['rtrimText'],
            'tokenCanonicalKey' => $token['canonicalKey'],
            'tokenCanonicalEncoding' => $token['canonicalEncoding'],
            'tokenCanonicalBytesHex' => $token['canonicalBytesHex'],
            'tokenNormalizationReasons' => $tokenReasons,
            'baseReplayInvalidationReasons' => $base['replayInvalidationReasons'],
            'replayInvalidationReasons' => $replayInvalidationReasons,
            'currentTokenFingerprint' => $base['currentTokenFingerprint'],
            'nextTokenFingerprint' => $base['nextTokenFingerprint'],
            'tokenFingerprintReasons' => $base['tokenFingerprintReasons'],
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'currentMatchedBytesHex' => $base['currentMatchedBytesHex'],
            'nextMatchedBytesHex' => $base['nextMatchedBytesHex'],
            'currentMatchedEncodings' => $base['currentMatchedEncodings'],
            'nextMatchedEncodings' => $base['nextMatchedEncodings'],
            'duplicateRtrimNocaseKeys' => $base['duplicateRtrimNocaseKeys'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'mustReprepareBeforeReplay' => $mustReprepare,
            'safeToReplayFromToken' => !$mustReprepare,
            'replayPlanRowids' => $mustReprepare ? $base['nextMatchedRowids'] : $base['replayPlanRowids'],
            'replayPlanMode' => $mustReprepare ? 'reprepare-from-range-start' : $base['replayPlanMode'],
            'tokenIncludesRowidTieBreaker' => true,
            'tokenIncludesByteFingerprint' => true,
            'tokenCanonicalizesRawUtf16Key' => true,
            'tokenRtrimTrimsOnlyAsciiSpace' => true,
            'tokenNocaseFoldsAsciiOnly' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-rtrim-expression',
                'sqlite-nocase-like-rtrim-token-canonicalization',
                'sqlite-current-source-nextoneSevenEight',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, RTRIM key construction, ASCII NOCASE folding, and nextOneSevenFive byte-fingerprint replay diagnostics',
            'non_overlap' => 'adds raw yielded-token canonicalization for UTF-16 RTRIM/NOCASE LIKE replay; avoids accepted nextOneSevenFive byte-fingerprint validation, nextOneSevenFour embedded-NUL residuals, nextOneSevenOne duplicate-key replay, Unicode GLOB ranges, and malformed UTF-16 insert guards',
        ];
    }

    /**
     * @param array{key:string,rowid:int,bytesHex?:string,encoding?:string,keyBytes?:string,keyEncoding?:int|string}|null $token
     * @return array{normalized:array{key:string,rowid:int,bytesHex?:string,encoding?:string}|null,rawText:?string,rtrimText:?string,canonicalKey:?string,canonicalEncoding:?string,canonicalBytesHex:?string,reasons:list<string>}
     */
    private static function v178_canonicalizeToken(?array $token): array
    {
        if ($token === null) {
            return [
                'normalized' => null,
                'rawText' => null,
                'rtrimText' => null,
                'canonicalKey' => null,
                'canonicalEncoding' => null,
                'canonicalBytesHex' => null,
                'reasons' => ['no-yield-token'],
            ];
        }
        if (!array_key_exists('key', $token) || !is_string($token['key'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneSevenEight token requires string key');
        }
        if (!array_key_exists('rowid', $token) || !is_int($token['rowid'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneSevenEight token requires integer rowid');
        }

        $normalized = [
            'key' => $token['key'],
            'rowid' => $token['rowid'],
        ];
        if (array_key_exists('bytesHex', $token)) {
            if (!is_string($token['bytesHex'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneSevenEight token bytesHex must be a string');
            }
            $normalized['bytesHex'] = $token['bytesHex'];
        }
        if (array_key_exists('encoding', $token)) {
            if (!is_string($token['encoding'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneSevenEight token encoding must be a string');
            }
            $normalized['encoding'] = $token['encoding'];
        }

        $rawText = null;
        $rtrimText = null;
        $canonicalKey = null;
        $canonicalEncoding = null;
        $canonicalBytesHex = null;
        $reasons = [];
        if (array_key_exists('keyBytes', $token) || array_key_exists('keyEncoding', $token)) {
            if (!array_key_exists('keyBytes', $token) || !is_string($token['keyBytes'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneSevenEight token keyBytes must be a string when keyEncoding is present');
            }
            if (!array_key_exists('keyEncoding', $token) || (!is_int($token['keyEncoding']) && !is_string($token['keyEncoding']))) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneSevenEight token keyEncoding must be UTF-8, UTF-16LE, or UTF-16BE');
            }
            $encoding = self::v178_normalizeEncoding($token['keyEncoding']);
            $rawText = SQLiteEncodingCollationSourceCursor::decodeText($token['keyBytes'], $encoding);
            $rtrimText = rtrim($rawText, ' ');
            $canonicalKey = self::v178_asciiLower($rtrimText);
            $canonicalEncoding = self::v178_encodingName($encoding);
            $canonicalBytesHex = bin2hex($token['keyBytes']);

            if ($normalized['key'] !== $canonicalKey) {
                $reasons[] = 'token-key-not-canonical';
                $normalized['key'] = $canonicalKey;
            }
            if (!array_key_exists('bytesHex', $normalized)) {
                $normalized['bytesHex'] = $canonicalBytesHex;
            } elseif ($normalized['bytesHex'] !== $canonicalBytesHex) {
                $reasons[] = 'token-raw-bytes-fingerprint-mismatch';
            }
            if (!array_key_exists('encoding', $normalized)) {
                $normalized['encoding'] = $canonicalEncoding;
            } elseif ($normalized['encoding'] !== $canonicalEncoding) {
                $reasons[] = 'token-raw-encoding-mismatch';
            }
        }

        return [
            'normalized' => $normalized,
            'rawText' => $rawText,
            'rtrimText' => $rtrimText,
            'canonicalKey' => $canonicalKey,
            'canonicalEncoding' => $canonicalEncoding,
            'canonicalBytesHex' => $canonicalBytesHex,
            'reasons' => array_values(array_unique($reasons)),
        ];
    }

    private static function v178_normalizeEncoding(int|string $encoding): int
    {
        if (is_int($encoding)) {
            if (in_array($encoding, [1, 2, 3], true)) {
                return $encoding;
            }
            throw new \InvalidArgumentException('SQLite text encoding must be UTF-8, UTF-16LE, or UTF-16BE');
        }

        return match (strtoupper(str_replace('_', '-', $encoding))) {
            'UTF-8', 'UTF8' => 1,
            'UTF-16LE', 'UTF16LE' => 2,
            'UTF-16BE', 'UTF16BE' => 3,
            default => throw new \InvalidArgumentException('SQLite text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function v178_encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function v178_asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyNonAsciiPrefixPlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        string $currentSource = 'main.app_settings@179',
        string $nextSource = 'main.app_settings@180',
        int $currentSchemaCookie = 179,
        int $nextSchemaCookie = 180,
    ): array {
        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $usesFullScan = !$like['indexUsable'] && $like['rejectedReason'] === 'nocase_like_prefix_must_be_ascii_for_range';
        $current = self::v180_scan($currentRows, $pattern, $escape, $like['range'], $usesFullScan);
        $next = self::v180_scan($nextRows, $pattern, $escape, $like['range'], $usesFullScan);

        $currentMatched = self::v180_rowids($current['matched']);
        $nextMatched = self::v180_rowids($next['matched']);
        $changes = self::v180_changes($current['decoded'], $next['decoded']);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($usesFullScan) {
            $reasons[] = 'non-ascii-nocase-prefix-full-scan';
        }
        foreach ([
            'decoded-text' => $changes['textChangedRowids'],
            'rtrim-expression' => $changes['rtrimChangedRowids'],
            'nocase-key' => $changes['nocaseKeyChangedRowids'],
            'encoded-bytes' => $changes['bytesChangedRowids'],
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        if ($currentMatched !== $nextMatched) {
            $reasons[] = 'matched-rowset';
        }

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nextoneEightZero',
            'operator' => 'LIKE',
            'expression' => 'rtrim(key_name) COLLATE NOCASE LIKE ?',
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => 'NOCASE',
            'caseSensitiveLike' => false,
            'asciiNocaseOnly' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'likePlan' => $like,
            'prefix' => $like['prefix'],
            'prefixIsAscii' => $like['prefixIsAscii'],
            'rejectedReason' => $like['rejectedReason'],
            'indexUsable' => $like['indexUsable'],
            'usesFullScanFallback' => $usesFullScan,
            'range' => $like['range'],
            'currentDecodedRowids' => self::v180_rowids($current['decoded']),
            'nextDecodedRowids' => self::v180_rowids($next['decoded']),
            'currentCandidateRowids' => self::v180_rowids($current['candidates']),
            'nextCandidateRowids' => self::v180_rowids($next['candidates']),
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'currentFalsePositiveRowids' => self::v180_rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::v180_rowids($next['falsePositive']),
            'currentNonAsciiPrefixRowids' => $current['nonAsciiPrefixRowids'],
            'nextNonAsciiPrefixRowids' => $next['nonAsciiPrefixRowids'],
            'currentAsciiFoldedRowids' => $current['asciiFoldedRowids'],
            'nextAsciiFoldedRowids' => $next['asciiFoldedRowids'],
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'currentTexts' => self::v180_map($current['decoded'], 'text'),
            'nextTexts' => self::v180_map($next['decoded'], 'text'),
            'currentRtrimTexts' => self::v180_map($current['decoded'], 'rtrimText'),
            'nextRtrimTexts' => self::v180_map($next['decoded'], 'rtrimText'),
            'currentNocaseKeys' => self::v180_map($current['decoded'], 'nocaseKey'),
            'nextNocaseKeys' => self::v180_map($next['decoded'], 'nocaseKey'),
            'changedTextRowids' => $changes['textChangedRowids'],
            'changedRtrimRowids' => $changes['rtrimChangedRowids'],
            'changedNocaseKeyRowids' => $changes['nocaseKeyChangedRowids'],
            'changedBytesRowids' => $changes['bytesChangedRowids'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => array_values(array_unique($reasons)),
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-nocase-full-scan',
                'sqlite-rtrim-expression-key',
                'sqlite-current-source-nextoneEightZero',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE matching, RTRIM expression keys, and current-source invalidation diagnostics',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{decoded:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,nonAsciiPrefixRowids:list<int>,asciiFoldedRowids:list<int>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function v180_scan(array $rows, string $pattern, ?string $escape, ?array $range, bool $usesFullScan): array
    {
        $decoded = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            self::v180_assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['key_name_bytes'], $row['text_encoding']);
                $rtrim = rtrim($text, ' ');
                $decoded[] = [
                    'rowid' => $row['setting_id'],
                    'text' => $text,
                    'rtrimText' => $rtrim,
                    'nocaseKey' => self::v180_asciiLower($rtrim),
                    'bytesHex' => bin2hex($row['key_name_bytes']),
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['setting_id'];
                $errors[$row['setting_id']] = $exception->getMessage();
            }
        }

        usort($decoded, self::v180_sortRows(...));
        sort($malformed);
        ksort($errors);

        $candidates = [];
        $matched = [];
        $falsePositive = [];
        $nonAsciiPrefix = [];
        $asciiFolded = [];
        foreach ($decoded as $entry) {
            if (!$usesFullScan && !self::v180_inRange($entry['nocaseKey'], $range)) {
                continue;
            }
            $entry['residualMatch'] = SQLiteDatabase::likeMatches($entry['rtrimText'], $pattern, $escape, false);
            $candidates[] = $entry;
            if (self::v180_hasNonAscii($entry['rtrimText'])) {
                $nonAsciiPrefix[] = $entry['rowid'];
            }
            if ($entry['rtrimText'] !== $entry['nocaseKey']) {
                $asciiFolded[] = $entry['rowid'];
            }
            if ($entry['residualMatch']) {
                $matched[] = $entry;
            } else {
                $falsePositive[] = $entry;
            }
        }
        sort($nonAsciiPrefix);
        sort($asciiFolded);

        return [
            'decoded' => $decoded,
            'candidates' => $candidates,
            'matched' => $matched,
            'falsePositive' => $falsePositive,
            'nonAsciiPrefixRowids' => $nonAsciiPrefix,
            'asciiFoldedRowids' => $asciiFolded,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row */
    private static function v180_assertRow(array $row): void
    {
        if (!array_key_exists('setting_id', $row) || !is_int($row['setting_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneEightZero rows require integer setting_id');
        }
        if (!array_key_exists('key_name_bytes', $row) || !is_string($row['key_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneEightZero rows require key_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneEightZero rows require integer text_encoding');
        }
    }

    /** @param ?array{lowerInclusive:string,upperBound:?string} $range */
    private static function v180_inRange(string $key, ?array $range): bool
    {
        if ($range === null || strcmp($key, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0;
    }

    /** @param array{nocaseKey:string,rowid:int} $left @param array{nocaseKey:string,rowid:int} $right */
    private static function v180_sortRows(array $left, array $right): int
    {
        $comparison = strcmp($left['nocaseKey'], $right['nocaseKey']);

        return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
    }

    /** @param list<array{rowid:int}> $rows @return list<int> */
    private static function v180_rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $rows @return array<int,mixed> */
    private static function v180_map(array $rows, string $key): array
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
     * @return array<string,list<int>>
     */
    private static function v180_changes(array $current, array $next): array
    {
        $currentByRowid = self::v180_byRowid($current);
        $nextByRowid = self::v180_byRowid($next);

        return [
            'textChangedRowids' => self::v180_changed($currentByRowid, $nextByRowid, 'text'),
            'rtrimChangedRowids' => self::v180_changed($currentByRowid, $nextByRowid, 'rtrimText'),
            'nocaseKeyChangedRowids' => self::v180_changed($currentByRowid, $nextByRowid, 'nocaseKey'),
            'bytesChangedRowids' => self::v180_changed($currentByRowid, $nextByRowid, 'bytesHex'),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private static function v180_byRowid(array $rows): array
    {
        $byRowid = [];
        foreach ($rows as $row) {
            $byRowid[$row['rowid']] = $row;
        }

        return $byRowid;
    }

    /** @param array<int,array<string,mixed>> $current @param array<int,array<string,mixed>> $next @return list<int> */
    private static function v180_changed(array $current, array $next, string $key): array
    {
        $rowids = array_values(array_intersect(array_keys($current), array_keys($next)));
        sort($rowids);
        $changed = [];
        foreach ($rowids as $rowid) {
            if (($current[$rowid][$key] ?? null) !== ($next[$rowid][$key] ?? null)) {
                $changed[] = (int) $rowid;
            }
        }

        return $changed;
    }

    private static function v180_hasNonAscii(string $value): bool
    {
        $length = strlen($value);
        for ($offset = 0; $offset < $length; $offset++) {
            if (ord($value[$offset]) > 0x7f) {
                return true;
            }
        }

        return false;
    }

    private static function v180_asciiLower(string $value): string
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

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array{key:string,rowid:int,bytesHex?:string,encoding?:string,keyBytes?:string,keyEncoding?:int|string}|null $lastYielded
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyPeerReplayPlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        ?array $lastYielded = null,
        string $currentSource = 'main.app_settings@180',
        string $nextSource = 'main.app_settings@181',
        int $currentSchemaCookie = 180,
        int $nextSchemaCookie = 181,
    ): array {
        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyCanonicalTokenPlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escape,
            $lastYielded,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $currentRowsByRowid = self::v181_scanByRowid($currentRows, $pattern, $escape, $base['range']);
        $nextRowsByRowid = self::v181_scanByRowid($nextRows, $pattern, $escape, $base['range']);
        $token = is_array($base['normalizedLastYielded']) ? $base['normalizedLastYielded'] : null;
        $peerKey = $token['key'] ?? null;
        $currentPeerRowids = $peerKey === null ? [] : self::v181_peerRowids($currentRowsByRowid, $peerKey);
        $nextPeerRowids = $peerKey === null ? [] : self::v181_peerRowids($nextRowsByRowid, $peerKey);
        $peerAfterTokenRowids = $token === null ? [] : array_values(array_filter(
            $nextPeerRowids,
            static fn (int $rowid): bool => $rowid > $token['rowid']
        ));
        $peerBeforeOrAtTokenRowids = $token === null ? [] : array_values(array_filter(
            $nextPeerRowids,
            static fn (int $rowid): bool => $rowid <= $token['rowid']
        ));
        $afterPeerRowids = $peerKey === null ? $base['nextMatchedRowids'] : array_values(array_filter(
            $base['nextMatchedRowids'],
            static fn (int $rowid): bool => isset($nextRowsByRowid[$rowid]) && strcmp($nextRowsByRowid[$rowid]['key'], $peerKey) > 0
        ));

        $sameSource = $base['currentSource'] === $base['nextSource'] && $base['currentSchemaCookie'] === $base['nextSchemaCookie'];
        $stableToken = $token !== null && $base['tokenNormalizationReasons'] === [] && $base['tokenFingerprintReasons'] === [];
        $stablePeer = $currentPeerRowids === $nextPeerRowids;
        $peerSafeReasons = [];
        if (!$sameSource) {
            $peerSafeReasons[] = 'source-or-schema-changed';
        }
        if (!$stableToken) {
            $peerSafeReasons[] = 'yield-token-not-stable';
        }
        if (!$stablePeer) {
            $peerSafeReasons[] = 'peer-rowset-changed';
        }
        if ($base['currentMalformedRowids'] !== [] || $base['nextMalformedRowids'] !== []) {
            $peerSafeReasons[] = 'malformed-text';
        }

        $peerContinuationSafe = $peerSafeReasons === [];
        $sameKeyReplayRowids = $peerContinuationSafe ? $peerAfterTokenRowids : [];
        $remainingAfterPeerRowids = $peerContinuationSafe ? $afterPeerRowids : [];
        $replayPlanRowids = $peerContinuationSafe
            ? array_values(array_merge($sameKeyReplayRowids, $remainingAfterPeerRowids))
            : $base['replayPlanRowids'];

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nextoneEightOne',
            'operator' => 'LIKE',
            'expression' => 'rtrim(key_name) COLLATE NOCASE',
            'baseStatus' => $base['status'],
            'pattern' => $base['pattern'],
            'escape' => $base['escape'],
            'currentSource' => $base['currentSource'],
            'nextSource' => $base['nextSource'],
            'currentSchemaCookie' => $base['currentSchemaCookie'],
            'nextSchemaCookie' => $base['nextSchemaCookie'],
            'prefix' => $base['prefix'],
            'range' => $base['range'],
            'normalizedLastYielded' => $token,
            'peerKey' => $peerKey,
            'currentPeerRowids' => $currentPeerRowids,
            'nextPeerRowids' => $nextPeerRowids,
            'peerBeforeOrAtTokenRowids' => $peerBeforeOrAtTokenRowids,
            'peerAfterTokenRowids' => $peerAfterTokenRowids,
            'sameKeyReplayRowids' => $sameKeyReplayRowids,
            'remainingAfterPeerRowids' => $remainingAfterPeerRowids,
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'nextMatchedKeys' => self::v181_map($nextRowsByRowid, 'key'),
            'nextMatchedRtrimText' => self::v181_map($nextRowsByRowid, 'rtrimText'),
            'nextMatchedEncodings' => self::v181_map($nextRowsByRowid, 'encoding'),
            'duplicateRtrimNocaseKeys' => self::v181_duplicateKeys($nextRowsByRowid),
            'tokenNormalizationReasons' => $base['tokenNormalizationReasons'],
            'tokenFingerprintReasons' => $base['tokenFingerprintReasons'],
            'baseReplayInvalidationReasons' => $base['baseReplayInvalidationReasons'],
            'peerReplayUnsafeReasons' => $peerSafeReasons,
            'peerContinuationSafe' => $peerContinuationSafe,
            'mustReprepareBeforePeerReplay' => !$peerContinuationSafe,
            'safeToContinueWithinPeerGroup' => $peerContinuationSafe,
            'replayPlanMode' => $peerContinuationSafe ? 'continue-after-key-rowid-peer-token' : 'reprepare-from-range-start',
            'replayPlanRowids' => $replayPlanRowids,
            'tokenUsesRowidTieBreakerForEqualRtrimNocaseKeys' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'likeResidualAppliesAfterRtrim' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-rtrim-expression',
                'sqlite-nocase-like-peer-replay',
                'sqlite-current-source-nextoneEightOne',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, RTRIM expression keys, ASCII NOCASE LIKE matching, and nextOneSevenEight canonical byte-token validation',
            'non_overlap' => 'adds same-key peer replay for stable UTF-16 RTRIM/NOCASE LIKE cursors; avoids accepted nextOneSevenEight canonical token validation, nextOneSevenSeven Unicode wildcard residuals, nextOneSevenOne duplicate-key invalidation, Unicode GLOB ranges, and UTF-16 malformed insert guards',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array<int,array{rowid:int,text:string,rtrimText:string,key:string,encoding:string,bytesHex:string}>
     */
    private static function v181_scanByRowid(array $rows, string $pattern, ?string $escape, ?array $range): array
    {
        $matched = [];
        foreach ($rows as $row) {
            self::v181_assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['key_name_bytes'], $row['text_encoding']);
            } catch (\InvalidArgumentException) {
                continue;
            }

            $rtrim = rtrim($text, ' ');
            $key = self::v181_asciiLower($rtrim);
            if ($range !== null && (strcmp($key, $range['lowerInclusive']) < 0 || ($range['upperBound'] !== null && strcmp($key, $range['upperBound']) >= 0))) {
                continue;
            }
            if (!SQLiteDatabase::likeMatches($rtrim, $pattern, $escape, false)) {
                continue;
            }
            $matched[$row['setting_id']] = [
                'rowid' => $row['setting_id'],
                'text' => $text,
                'rtrimText' => $rtrim,
                'key' => $key,
                'encoding' => self::v181_encodingName($row['text_encoding']),
                'bytesHex' => bin2hex($row['key_name_bytes']),
            ];
        }

        uasort($matched, static function (array $left, array $right): int {
            $comparison = strcmp($left['key'], $right['key']);

            return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
        });

        return $matched;
    }

    /** @param array<string,mixed> $row */
    private static function v181_assertRow(array $row): void
    {
        if (!array_key_exists('setting_id', $row) || !is_int($row['setting_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneEightOne rows require integer setting_id');
        }
        if (!array_key_exists('key_name_bytes', $row) || !is_string($row['key_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneEightOne rows require key_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneEightOne rows require integer text_encoding');
        }
    }

    /** @param array<int,array{key:string,rowid:int}> $rows @return list<int> */
    private static function v181_peerRowids(array $rows, string $key): array
    {
        $rowids = [];
        foreach ($rows as $row) {
            if ($row['key'] === $key) {
                $rowids[] = $row['rowid'];
            }
        }
        sort($rowids);

        return $rowids;
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,mixed> */
    private static function v181_map(array $rows, string $field): array
    {
        $mapped = [];
        foreach ($rows as $rowid => $row) {
            $mapped[$rowid] = $row[$field];
        }

        return $mapped;
    }

    /** @param array<int,array{key:string,rowid:int}> $rows @return array<string,list<int>> */
    private static function v181_duplicateKeys(array $rows): array
    {
        $keys = [];
        foreach ($rows as $row) {
            $keys[$row['key']][] = $row['rowid'];
        }

        return array_filter($keys, static fn (array $rowids): bool => count($rowids) > 1);
    }

    private static function v181_encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function v181_asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyAsciiPrefixRangePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_cache',
        ?string $escape = '!',
        string $currentSource = 'main.app_settings@182',
        string $nextSource = 'main.app_settings@183',
        int $currentSchemaCookie = 182,
        int $nextSchemaCookie = 183,
    ): array {
        $plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyNonAsciiPrefixPlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escape,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $currentCandidates = $plan['currentCandidateRowids'];
        $nextCandidates = $plan['nextCandidateRowids'];
        $currentMatched = $plan['currentMatchedRowids'];
        $nextMatched = $plan['nextMatchedRowids'];

        $plan['status'] = 'utf16-nocase-like-rtrim-current-source-nextoneEightThree';
        $plan['expression'] = 'rtrim(key_name) COLLATE NOCASE LIKE ? ESCAPE ?';
        $plan['rangeLowerInclusive'] = $plan['range']['lowerInclusive'] ?? null;
        $plan['rangeUpperBound'] = $plan['range']['upperBound'] ?? null;
        $plan['usesPrefixRangeCursor'] = $plan['indexUsable'] && !$plan['usesFullScanFallback'];
        $plan['usesFullScanFallback'] = false;
        $plan['rejectedReason'] = null;
        $plan['currentRangeRetainedRowids'] = array_values(array_intersect($currentCandidates, $nextCandidates));
        $plan['currentRangeExitedRowids'] = array_values(array_diff($currentCandidates, $nextCandidates));
        $plan['nextRangeEnteredRowids'] = array_values(array_diff($nextCandidates, $currentCandidates));
        $plan['matchedRetainedRowids'] = array_values(array_intersect($currentMatched, $nextMatched));
        $plan['matchedExitedRowids'] = array_values(array_diff($currentMatched, $nextMatched));
        $plan['matchedEnteredRowids'] = array_values(array_diff($nextMatched, $currentMatched));
        $plan['currentRangeFalsePositiveRowids'] = $plan['currentFalsePositiveRowids'];
        $plan['nextRangeFalsePositiveRowids'] = $plan['nextFalsePositiveRowids'];
        $plan['currentExcludedDecodedRowids'] = array_values(array_diff($plan['currentDecodedRowids'], $currentCandidates));
        $plan['nextExcludedDecodedRowids'] = array_values(array_diff($plan['nextDecodedRowids'], $nextCandidates));
        $plan['currentMatchedTexts'] = self::v183_selectMap($plan['currentRtrimTexts'], $currentMatched);
        $plan['nextMatchedTexts'] = self::v183_selectMap($plan['nextRtrimTexts'], $nextMatched);
        $plan['rtrimResidualChangedRowids'] = array_values(array_unique(array_merge(
            $plan['changedRtrimRowids'],
            $plan['matchedEnteredRowids'],
            $plan['matchedExitedRowids'],
        )));
        sort($plan['rtrimResidualChangedRowids']);
        $plan['staleRangeCursorRisk'] = $plan['cursorInvalidated'] && (
            $plan['currentRangeExitedRowids'] !== []
            || $plan['nextRangeEnteredRowids'] !== []
            || $plan['rtrimResidualChangedRowids'] !== []
        );
        $plan['dependencies'] = [
            'sqlite-utf16-decode',
            'sqlite-like-nocase-prefix-range',
            'sqlite-rtrim-residual-match',
            'sqlite-current-source-nextoneEightThree',
        ];
        $plan['dependency_closure'] = 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE prefix range planning, RTRIM residual matching, and current-source cursor invalidation';

        return $plan;
    }

    /** @param array<int,string> $values @param list<int> $rowids @return array<int,string> */
    private static function v183_selectMap(array $values, array $rowids): array
    {
        $selected = [];
        foreach ($rowids as $rowid) {
            if (array_key_exists($rowid, $values)) {
                $selected[$rowid] = $values[$rowid];
            }
        }

        return $selected;
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array{key:string,rowid:int,bytesHex?:string,encoding?:string,keyBytes?:string,keyEncoding?:int|string}|null $lastYielded
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyEscapedPeerReplayPlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape,
        ?array $lastYielded,
        string $currentSource = 'main.app_settings@183',
        string $nextSource = 'main.app_settings@184',
        int $currentSchemaCookie = 183,
        int $nextSchemaCookie = 184,
    ): array {
        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPeerReplayPlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escape,
            $lastYielded,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $token = is_array($base['normalizedLastYielded']) ? $base['normalizedLastYielded'] : null;
        $tokenCheck = self::v184_tokenResidualCheck($token, $pattern, $escape);
        $unsafe = $base['peerReplayUnsafeReasons'];
        if (!$tokenCheck['matchesResidual']) {
            $unsafe[] = 'yield-token-like-residual-mismatch';
        }
        if ($tokenCheck['decodeError'] !== null) {
            $unsafe[] = 'yield-token-decode-error';
        }
        $unsafe = array_values(array_unique($unsafe));
        $safe = $unsafe === [];
        $sameKeyReplay = $safe ? $base['sameKeyReplayRowids'] : [];
        $remainingAfterPeer = $safe ? $base['remainingAfterPeerRowids'] : [];

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nextoneEightFour',
            'operator' => 'LIKE',
            'expression' => 'rtrim(key_name) COLLATE NOCASE',
            'pattern' => $base['pattern'],
            'escape' => $base['escape'],
            'baseStatus' => $base['status'],
            'baseReplayPlanMode' => $base['replayPlanMode'],
            'currentSource' => $base['currentSource'],
            'nextSource' => $base['nextSource'],
            'currentSchemaCookie' => $base['currentSchemaCookie'],
            'nextSchemaCookie' => $base['nextSchemaCookie'],
            'prefix' => $base['prefix'],
            'range' => $base['range'],
            'normalizedLastYielded' => $token,
            'tokenDecodedText' => $tokenCheck['decodedText'],
            'tokenRtrimText' => $tokenCheck['rtrimText'],
            'tokenNocaseKey' => $tokenCheck['nocaseKey'],
            'tokenMatchesEscapedLikeResidual' => $tokenCheck['matchesResidual'],
            'tokenDecodeError' => $tokenCheck['decodeError'],
            'tokenEscapePreservesLiteralPercent' => $escape !== null && str_contains($pattern, $escape . '%'),
            'tokenEscapePreservesLiteralUnderscore' => $escape !== null && str_contains($pattern, $escape . '_'),
            'peerKey' => $base['peerKey'],
            'currentPeerRowids' => $base['currentPeerRowids'],
            'nextPeerRowids' => $base['nextPeerRowids'],
            'peerBeforeOrAtTokenRowids' => $base['peerBeforeOrAtTokenRowids'],
            'peerAfterTokenRowids' => $base['peerAfterTokenRowids'],
            'sameKeyReplayRowids' => $sameKeyReplay,
            'remainingAfterPeerRowids' => $remainingAfterPeer,
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'nextMatchedKeys' => $base['nextMatchedKeys'],
            'nextMatchedRtrimText' => $base['nextMatchedRtrimText'],
            'nextMatchedEncodings' => $base['nextMatchedEncodings'],
            'duplicateRtrimNocaseKeys' => $base['duplicateRtrimNocaseKeys'],
            'basePeerReplayUnsafeReasons' => $base['peerReplayUnsafeReasons'],
            'peerReplayUnsafeReasons' => $unsafe,
            'peerContinuationSafe' => $safe,
            'mustReprepareBeforePeerReplay' => !$safe,
            'safeToContinueWithinPeerGroup' => $safe,
            'replayPlanMode' => $safe ? 'continue-after-escaped-like-peer-token' : 'reprepare-from-range-start',
            'replayPlanRowids' => $safe ? array_values(array_merge($sameKeyReplay, $remainingAfterPeer)) : $base['nextMatchedRowids'],
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'escapedLikeResidualAppliesAfterRtrim' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-escape-residual',
                'sqlite-rtrim-expression',
                'sqlite-nocase-like-peer-replay',
                'sqlite-current-source-nextoneEightFour',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, escaped LIKE residual matching, RTRIM expression keys, and nextOneEightOne peer replay diagnostics',
            'non_overlap' => 'adds escaped LIKE residual validation for yielded UTF-16 RTRIM/NOCASE peer tokens; avoids accepted nextOneEightOne peer replay, nextOneEightZero non-ASCII full-scan, nextOneSevenEight canonical token validation, Unicode GLOB ranges, and UTF-16 malformed insert guards',
        ];
    }

    /**
     * @param array{key:string,rowid:int,bytesHex?:string,encoding?:string,keyBytes?:string,keyEncoding?:int|string}|null $token
     * @return array{decodedText:?string,rtrimText:?string,nocaseKey:?string,matchesResidual:bool,decodeError:?string}
     */
    private static function v184_tokenResidualCheck(?array $token, string $pattern, ?string $escape): array
    {
        if ($token === null) {
            return [
                'decodedText' => null,
                'rtrimText' => null,
                'nocaseKey' => null,
                'matchesResidual' => false,
                'decodeError' => null,
            ];
        }

        try {
            $decoded = self::v184_decodeTokenText($token);
            $rtrim = rtrim($decoded, ' ');

            return [
                'decodedText' => $decoded,
                'rtrimText' => $rtrim,
                'nocaseKey' => self::v184_asciiLower($rtrim),
                'matchesResidual' => SQLiteDatabase::likeMatches($rtrim, $pattern, $escape, false),
                'decodeError' => null,
            ];
        } catch (\InvalidArgumentException $exception) {
            return [
                'decodedText' => null,
                'rtrimText' => null,
                'nocaseKey' => null,
                'matchesResidual' => false,
                'decodeError' => $exception->getMessage(),
            ];
        }
    }

    /** @param array<string,mixed> $token */
    private static function v184_decodeTokenText(array $token): string
    {
        if (isset($token['keyBytes'])) {
            $encoding = self::v184_encodingId($token['keyEncoding'] ?? $token['encoding'] ?? 1);

            return SQLiteEncodingCollationSourceCursor::decodeText($token['keyBytes'], $encoding);
        }
        if (isset($token['bytesHex'])) {
            $bytes = hex2bin((string) $token['bytesHex']);
            if ($bytes === false) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneEightFour yielded token bytesHex is not valid hex');
            }
            $encoding = self::v184_encodingId($token['encoding'] ?? 1);

            return SQLiteEncodingCollationSourceCursor::decodeText($bytes, $encoding);
        }

        return (string) $token['key'];
    }

    private static function v184_asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    private static function v184_encodingId(int|string $encoding): int
    {
        return match ($encoding) {
            1, 'UTF-8' => 1,
            2, 'UTF-16LE' => 2,
            3, 'UTF-16BE' => 3,
            default => throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneEightFour token encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array{key:string,rowid:int,bytesHex?:string,encoding?:string}|null $lastYielded
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyDeletedTokenResumePlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        ?array $lastYielded = null,
        string $currentSource = 'main.app_settings@184',
        string $nextSource = 'main.app_settings@185',
        int $currentSchemaCookie = 184,
        int $nextSchemaCookie = 185,
    ): array {
        $rangePlan = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $range = $rangePlan['range'];
        $current = self::v185_scan($currentRows, $pattern, $escape, $range);
        $next = self::v185_scan($nextRows, $pattern, $escape, $range);
        $token = self::v185_normalizeToken($lastYielded);
        $tokenKey = $token['key'] ?? null;
        $tokenRowid = $token['rowid'] ?? null;

        $currentPeerRowids = $tokenKey === null ? [] : self::v185_peerRowids($current['matched'], $tokenKey);
        $nextPeerRowids = $tokenKey === null ? [] : self::v185_peerRowids($next['matched'], $tokenKey);
        $currentBeforeOrAt = $tokenKey === null || $tokenRowid === null ? [] : self::v185_peerBeforeOrAt($currentPeerRowids, $tokenRowid);
        $nextBeforeOrAt = $tokenKey === null || $tokenRowid === null ? [] : self::v185_peerBeforeOrAt($nextPeerRowids, $tokenRowid);
        $currentAfter = $tokenKey === null || $tokenRowid === null ? [] : self::v185_peerAfter($currentPeerRowids, $tokenRowid);
        $nextAfter = $tokenKey === null || $tokenRowid === null ? [] : self::v185_peerAfter($nextPeerRowids, $tokenRowid);
        $tokenExited = $tokenRowid !== null && !isset($next['matched'][$tokenRowid]);

        $expectedNextBeforeOrAt = array_values(array_diff($currentBeforeOrAt, $tokenRowid === null ? [] : [$tokenRowid]));
        $unsafe = [];
        if ($currentSource !== $nextSource || $currentSchemaCookie !== $nextSchemaCookie) {
            $unsafe[] = 'source-or-schema-changed';
        }
        if ($token === null) {
            $unsafe[] = 'yield-token-missing';
        } elseif ($token['normalizationReasons'] !== []) {
            $unsafe[] = 'yield-token-not-canonical';
        }
        if (!$tokenExited) {
            $unsafe[] = 'yield-token-not-deleted';
        }
        if ($current['errors'] !== [] || $next['errors'] !== []) {
            $unsafe[] = 'malformed-text';
        }
        if ($tokenExited && $tokenKey !== null && $nextBeforeOrAt !== $expectedNextBeforeOrAt) {
            $unsafe[] = 'peer-before-token-changed';
        }
        if ($tokenExited && $tokenKey !== null && count($nextAfter) < count($currentAfter)) {
            $unsafe[] = 'peer-after-token-lost-row';
        }

        $safe = $unsafe === [];
        $samePeerReplay = $safe && $tokenKey !== null && $tokenRowid !== null ? $nextAfter : [];
        $afterPeerReplay = $safe && $tokenKey !== null ? self::v185_afterKeyRowids($next['matched'], $tokenKey) : [];
        $replayRowids = $safe ? array_values(array_merge($samePeerReplay, $afterPeerReplay)) : self::v185_rowids($next['matched']);

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nextoneEightFive',
            'operator' => 'LIKE',
            'expression' => 'rtrim(key_name) COLLATE NOCASE',
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => 'NOCASE',
            'range' => $range,
            'prefix' => $rangePlan['prefix'],
            'indexUsable' => $rangePlan['indexUsable'],
            'rejectedReason' => $rangePlan['rejectedReason'],
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'normalizedLastYielded' => $token === null ? null : [
                'key' => $token['key'],
                'rowid' => $token['rowid'],
                'bytesHex' => $token['bytesHex'],
                'encoding' => $token['encoding'],
            ],
            'tokenNormalizationReasons' => $token['normalizationReasons'] ?? ['yield-token-missing'],
            'deletedTokenRowid' => $tokenExited ? $tokenRowid : null,
            'currentMatchedRowids' => self::v185_rowids($current['matched']),
            'nextMatchedRowids' => self::v185_rowids($next['matched']),
            'currentMatchedKeys' => self::v185_map($current['matched'], 'key'),
            'nextMatchedKeys' => self::v185_map($next['matched'], 'key'),
            'currentMatchedRtrimText' => self::v185_map($current['matched'], 'rtrimText'),
            'nextMatchedRtrimText' => self::v185_map($next['matched'], 'rtrimText'),
            'currentMatchedEncodings' => self::v185_map($current['matched'], 'encoding'),
            'nextMatchedEncodings' => self::v185_map($next['matched'], 'encoding'),
            'currentMatchedBytesHex' => self::v185_map($current['matched'], 'bytesHex'),
            'nextMatchedBytesHex' => self::v185_map($next['matched'], 'bytesHex'),
            'currentPeerRowids' => $currentPeerRowids,
            'nextPeerRowids' => $nextPeerRowids,
            'currentPeerBeforeOrAtTokenRowids' => $currentBeforeOrAt,
            'nextPeerBeforeOrAtTokenRowids' => $nextBeforeOrAt,
            'expectedNextPeerBeforeOrAtTokenRowids' => $expectedNextBeforeOrAt,
            'currentPeerAfterTokenRowids' => $currentAfter,
            'nextPeerAfterTokenRowids' => $nextAfter,
            'samePeerReplayRowids' => $samePeerReplay,
            'afterPeerReplayRowids' => $afterPeerReplay,
            'currentMalformedRowids' => array_keys($current['errors']),
            'nextMalformedRowids' => array_keys($next['errors']),
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'resumeUnsafeReasons' => $unsafe,
            'deletedTokenResumeSafe' => $safe,
            'mustReprepareBeforeDeletedTokenResume' => !$safe,
            'safeToResumeAfterDeletedToken' => $safe,
            'replayPlanMode' => $safe ? 'continue-after-deleted-key-rowid-token' : 'reprepare-from-range-start',
            'replayPlanRowids' => $replayRowids,
            'tokenUsesRowidBoundaryAfterDeletion' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'likeResidualAppliesAfterRtrim' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-rtrim-expression',
                'sqlite-nocase-like-deleted-token-resume',
                'sqlite-current-source-nextoneEightFive',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, RTRIM expression keys, ASCII NOCASE LIKE matching, and key/rowid yield-token replay diagnostics',
            'non_overlap' => 'adds deleted yielded-token resume checks for UTF-16 RTRIM/NOCASE LIKE cursors; avoids accepted ESCAPE operand validation nextOneEightTwo, equal-peer replay nextOneEightOne, canonical token fingerprint nextOneSevenFive, Unicode GLOB ranges, UTF-16 malformed insert guards, and storage/planner clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{matched:array<int,array{rowid:int,text:string,rtrimText:string,key:string,encoding:string,bytesHex:string}>,errors:array<int,string>}
     */
    private static function v185_scan(array $rows, string $pattern, ?string $escape, ?array $range): array
    {
        $matched = [];
        $errors = [];
        foreach ($rows as $row) {
            self::v185_assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['key_name_bytes'], $row['text_encoding']);
            } catch (\InvalidArgumentException $exception) {
                $errors[$row['setting_id']] = $exception->getMessage();
                continue;
            }

            $rtrim = rtrim($text, ' ');
            $key = self::v185_asciiLower($rtrim);
            if ($range !== null && (strcmp($key, $range['lowerInclusive']) < 0 || ($range['upperBound'] !== null && strcmp($key, $range['upperBound']) >= 0))) {
                continue;
            }
            if (!SQLiteDatabase::likeMatches($rtrim, $pattern, $escape, false)) {
                continue;
            }
            $matched[$row['setting_id']] = [
                'rowid' => $row['setting_id'],
                'text' => $text,
                'rtrimText' => $rtrim,
                'key' => $key,
                'encoding' => self::v185_encodingName($row['text_encoding']),
                'bytesHex' => bin2hex($row['key_name_bytes']),
            ];
        }

        uasort($matched, static function (array $left, array $right): int {
            $comparison = strcmp($left['key'], $right['key']);

            return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
        });

        return ['matched' => $matched, 'errors' => $errors];
    }

    /** @param array<string,mixed> $row */
    private static function v185_assertRow(array $row): void
    {
        if (!array_key_exists('setting_id', $row) || !is_int($row['setting_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneEightFive rows require integer setting_id');
        }
        if (!array_key_exists('key_name_bytes', $row) || !is_string($row['key_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneEightFive rows require key_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneEightFive rows require integer text_encoding');
        }
    }

    /** @param array{key:string,rowid:int,bytesHex?:string,encoding?:string}|null $token @return array{key:string,rowid:int,bytesHex:?string,encoding:?string,normalizationReasons:list<string>}|null */
    private static function v185_normalizeToken(?array $token): ?array
    {
        if ($token === null) {
            return null;
        }

        $key = self::v185_asciiLower(rtrim($token['key'], ' '));
        $reasons = [];
        if ($key !== $token['key']) {
            $reasons[] = 'token-key-not-canonical';
        }

        return [
            'key' => $key,
            'rowid' => $token['rowid'],
            'bytesHex' => $token['bytesHex'] ?? null,
            'encoding' => $token['encoding'] ?? null,
            'normalizationReasons' => $reasons,
        ];
    }

    /** @param array<int,array{key:string,rowid:int}> $rows @return list<int> */
    private static function v185_peerRowids(array $rows, string $key): array
    {
        $rowids = [];
        foreach ($rows as $row) {
            if ($row['key'] === $key) {
                $rowids[] = $row['rowid'];
            }
        }
        sort($rowids);

        return $rowids;
    }

    /** @param list<int> $rowids @return list<int> */
    private static function v185_peerBeforeOrAt(array $rowids, int $tokenRowid): array
    {
        return array_values(array_filter($rowids, static fn (int $rowid): bool => $rowid <= $tokenRowid));
    }

    /** @param list<int> $rowids @return list<int> */
    private static function v185_peerAfter(array $rowids, int $tokenRowid): array
    {
        return array_values(array_filter($rowids, static fn (int $rowid): bool => $rowid > $tokenRowid));
    }

    /** @param array<int,array{key:string,rowid:int}> $rows @return list<int> */
    private static function v185_afterKeyRowids(array $rows, string $key): array
    {
        $rowids = [];
        foreach ($rows as $row) {
            if (strcmp($row['key'], $key) > 0) {
                $rowids[] = $row['rowid'];
            }
        }

        return $rowids;
    }

    /** @param array<int,array{rowid:int}> $rows @return list<int> */
    private static function v185_rowids(array $rows): array
    {
        return array_values(array_map(static fn (array $row): int => $row['rowid'], $rows));
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,mixed> */
    private static function v185_map(array $rows, string $field): array
    {
        $mapped = [];
        foreach ($rows as $rowid => $row) {
            $mapped[$rowid] = $row[$field];
        }

        return $mapped;
    }

    private static function v185_encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function v185_asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyResumeBoundaryPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_cache%',
        ?string $escape = '!',
        string $currentSource = 'main.app_settings@185',
        string $nextSource = 'main.app_settings@186',
        int $currentSchemaCookie = 185,
        int $nextSchemaCookie = 186,
        ?array $resumeToken = null,
    ): array {
        $plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyAsciiPrefixRangePlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escape,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $currentResume = self::v186_resumeRows($plan['currentNocaseKeys'], $plan['currentRtrimTexts'], $plan['currentCandidateRowids'], $resumeToken);
        $nextResume = self::v186_resumeRows($plan['nextNocaseKeys'], $plan['nextRtrimTexts'], $plan['nextCandidateRowids'], $resumeToken);
        $semanticStable = self::v186_semanticStableRowids($plan);
        $semanticChanged = self::v186_semanticChangedRowids($plan);
        $resumeBoundaryChanged = self::v186_resumeBoundaryChangedRowids($currentResume, $nextResume);
        $byteOnlyChanged = array_values(array_diff($plan['changedBytesRowids'], $semanticChanged));
        sort($byteOnlyChanged);

        $reasons = $plan['invalidationReasons'];
        foreach ([
            'resume-boundary-rowset' => $resumeBoundaryChanged,
            'byte-order-only-source-refresh' => $byteOnlyChanged,
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }

        $plan['status'] = 'utf16-nocase-like-rtrim-current-source-nextoneEightSix';
        $plan['expression'] = 'rtrim(key_name) COLLATE NOCASE LIKE ? ESCAPE ? /* resume boundary */';
        $plan['resumeToken'] = $resumeToken;
        $plan['currentResumeRowids'] = self::v186_rowids($currentResume);
        $plan['nextResumeRowids'] = self::v186_rowids($nextResume);
        $plan['currentResumeKeys'] = self::v186_keysByRowid($currentResume);
        $plan['nextResumeKeys'] = self::v186_keysByRowid($nextResume);
        $plan['resumeBoundaryChangedRowids'] = $resumeBoundaryChanged;
        $plan['semanticStableRowids'] = $semanticStable;
        $plan['semanticChangedRowids'] = $semanticChanged;
        $plan['byteOrderOnlyChangedRowids'] = $byteOnlyChanged;
        $plan['safeToResumeAfterToken'] = $resumeBoundaryChanged === [] && $plan['currentMalformedRowids'] === [] && $plan['nextMalformedRowids'] === [];
        $plan['mustReopenSourceCursor'] = !$plan['safeToResumeAfterToken'] || $plan['staleRangeCursorRisk'];
        $plan['resumeKeepsRtrimAsciiOnly'] = true;
        $plan['resumeKeepsNocaseAsciiOnly'] = true;
        $plan['utf16ByteOrderCanChangeWithoutSemanticKeyChange'] = true;
        $plan['invalidationReasons'] = array_values(array_unique($reasons));
        $plan['cursorInvalidated'] = $plan['invalidationReasons'] !== [];
        $plan['cursorReusable'] = $plan['invalidationReasons'] === [];
        $plan['dependencies'] = [
            'sqlite-utf16-decode',
            'sqlite-like-nocase-prefix-range',
            'sqlite-rtrim-residual-match',
            'sqlite-current-source-nextoneEightSix',
            'sqlite-utf16-resume-boundary',
        ];
        $plan['dependency_closure'] = 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE prefix range planning, RTRIM residual matching, and adds resume-boundary diagnostics for current-source cursor refresh';
        $plan['non_overlap'] = 'nextOneEightSix adds resume-boundary and byte-order-only source refresh diagnostics over UTF-16 NOCASE LIKE RTRIM range scans; it avoids accepted nextOneSevenSeven Unicode wildcard, nextOneEightZero non-ASCII prefix fallback, nextOneEightThree basic ASCII prefix range, and Unicode GLOB behavior';

        return $plan;
    }

    /** @param array<int,string> $keys @param array<int,string> $texts @param list<int> $candidateRowids @param ?array{key:string,rowid:int} $resumeToken @return list<array{rowid:int,key:string,text:string}> */
    private static function v186_resumeRows(array $keys, array $texts, array $candidateRowids, ?array $resumeToken): array
    {
        if ($resumeToken !== null) {
            if (!isset($resumeToken['key']) || !is_string($resumeToken['key'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneEightSix resume token requires string key');
            }
            if (!isset($resumeToken['rowid']) || !is_int($resumeToken['rowid'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneEightSix resume token requires integer rowid');
            }
        }

        $rows = [];
        foreach ($candidateRowids as $rowid) {
            $key = $keys[$rowid] ?? null;
            if (!is_string($key)) {
                continue;
            }
            if ($resumeToken !== null) {
                $comparison = strcmp($key, $resumeToken['key']);
                if ($comparison < 0 || ($comparison === 0 && $rowid <= $resumeToken['rowid'])) {
                    continue;
                }
            }
            $text = $texts[$rowid] ?? '';
            $rows[] = ['rowid' => $rowid, 'key' => $key, 'text' => is_string($text) ? $text : ''];
        }

        return $rows;
    }

    /** @param array<string,mixed> $plan @return list<int> */
    private static function v186_semanticStableRowids(array $plan): array
    {
        $current = array_keys($plan['currentNocaseKeys']);
        $next = array_keys($plan['nextNocaseKeys']);
        $rowids = array_values(array_intersect($current, $next));
        sort($rowids);
        $stable = [];
        foreach ($rowids as $rowid) {
            if (($plan['currentRtrimTexts'][$rowid] ?? null) === ($plan['nextRtrimTexts'][$rowid] ?? null)
                && ($plan['currentNocaseKeys'][$rowid] ?? null) === ($plan['nextNocaseKeys'][$rowid] ?? null)) {
                $stable[] = (int) $rowid;
            }
        }

        return $stable;
    }

    /** @param array<string,mixed> $plan @return list<int> */
    private static function v186_semanticChangedRowids(array $plan): array
    {
        $changed = array_values(array_unique(array_merge(
            $plan['changedTextRowids'],
            $plan['changedRtrimRowids'],
            $plan['changedNocaseKeyRowids'],
            $plan['rtrimResidualChangedRowids'],
            $plan['matchedEnteredRowids'],
            $plan['matchedExitedRowids'],
            $plan['currentRangeExitedRowids'],
            $plan['nextRangeEnteredRowids'],
        )));
        sort($changed);

        return $changed;
    }

    /** @param list<array{rowid:int,key:string,text:string}> $current @param list<array{rowid:int,key:string,text:string}> $next @return list<int> */
    private static function v186_resumeBoundaryChangedRowids(array $current, array $next): array
    {
        $changed = array_values(array_unique(array_merge(
            array_diff(self::v186_rowids($current), self::v186_rowids($next)),
            array_diff(self::v186_rowids($next), self::v186_rowids($current)),
        )));
        sort($changed);

        return $changed;
    }

    /** @param list<array{rowid:int,key:string,text:string}> $rows @return list<int> */
    private static function v186_rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array{rowid:int,key:string,text:string}> $rows @return array<int,string> */
    private static function v186_keysByRowid(array $rows): array
    {
        $keys = [];
        foreach ($rows as $row) {
            $keys[$row['rowid']] = $row['key'];
        }

        return $keys;
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyDanglingEscapePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!',
        ?string $escape = '!',
        string $currentSource = 'main.app_settings@186',
        string $nextSource = 'main.app_settings@187',
        int $currentSchemaCookie = 186,
        int $nextSchemaCookie = 187,
    ): array {
        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyAsciiPrefixRangePlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escape,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $danglingEscape = self::v187_hasDanglingEscape($pattern, $escape);
        $currentResidualMisses = $danglingEscape ? $base['currentCandidateRowids'] : $base['currentRangeFalsePositiveRowids'];
        $nextResidualMisses = $danglingEscape ? $base['nextCandidateRowids'] : $base['nextRangeFalsePositiveRowids'];
        $reasons = $base['invalidationReasons'];
        if ($danglingEscape) {
            $reasons[] = 'dangling-like-escape-residual';
        }
        if ($currentResidualMisses !== [] || $nextResidualMisses !== []) {
            $reasons[] = 'residual-recheck-required';
        }
        $reasons = array_values(array_unique($reasons));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nextoneEightSeven',
            'operator' => 'LIKE',
            'expression' => 'rtrim(key_name) COLLATE NOCASE LIKE ? ESCAPE ?',
            'baseStatus' => $base['status'],
            'pattern' => $base['pattern'],
            'escape' => $base['escape'],
            'prefix' => $base['prefix'],
            'rangeLowerInclusive' => $base['rangeLowerInclusive'],
            'rangeUpperBound' => $base['rangeUpperBound'],
            'patternEndsWithEscape' => $danglingEscape,
            'sqliteDanglingEscapeMatchesNoRows' => $danglingEscape,
            'usesPrefixRangeCursor' => $base['usesPrefixRangeCursor'],
            'currentSource' => $base['currentSource'],
            'nextSource' => $base['nextSource'],
            'currentSchemaCookie' => $base['currentSchemaCookie'],
            'nextSchemaCookie' => $base['nextSchemaCookie'],
            'currentCandidateRowids' => $base['currentCandidateRowids'],
            'nextCandidateRowids' => $base['nextCandidateRowids'],
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'currentDanglingEscapeResidualMissRowids' => $currentResidualMisses,
            'nextDanglingEscapeResidualMissRowids' => $nextResidualMisses,
            'currentMatchedTexts' => $base['currentMatchedTexts'],
            'nextMatchedTexts' => $base['nextMatchedTexts'],
            'currentRtrimTexts' => $base['currentRtrimTexts'],
            'nextRtrimTexts' => $base['nextRtrimTexts'],
            'currentNocaseKeys' => $base['currentNocaseKeys'],
            'nextNocaseKeys' => $base['nextNocaseKeys'],
            'currentExcludedDecodedRowids' => $base['currentExcludedDecodedRowids'],
            'nextExcludedDecodedRowids' => $base['nextExcludedDecodedRowids'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'currentErrors' => $base['currentErrors'],
            'nextErrors' => $base['nextErrors'],
            'rangeRetainedRowids' => $base['currentRangeRetainedRowids'],
            'rangeExitedRowids' => $base['currentRangeExitedRowids'],
            'rangeEnteredRowids' => $base['nextRangeEnteredRowids'],
            'candidateRowsetChanged' => $base['currentCandidateRowids'] !== $base['nextCandidateRowids'],
            'matchedRowsetChanged' => $base['currentMatchedRowids'] !== $base['nextMatchedRowids'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'staleRangeCursorRisk' => $base['staleRangeCursorRisk'] || $currentResidualMisses !== [] || $nextResidualMisses !== [],
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-dangling-escape-residual',
                'sqlite-nocase-prefix-range-recheck',
                'sqlite-rtrim-expression-key',
                'sqlite-current-source-nextoneEightSeven',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE prefix planning, RTRIM keys, and SQLite LIKE residual matching',
            'non_overlap' => 'adds dangling ESCAPE residual recheck behavior for UTF-16 NOCASE/RTRIM LIKE current-source prefix cursors; avoids accepted nextOneEightThree prefix reuse, nextOneEightFour escaped peer replay, prepared-pattern byte normalization, Unicode GLOB, and malformed UTF-16 insert guards',
        ];
    }

    private static function v187_hasDanglingEscape(string $pattern, ?string $escape): bool
    {
        $escapeCharacters = $escape === null ? [] : self::v187_characters($escape);
        if (count($escapeCharacters) !== 1) {
            return false;
        }

        $patternCharacters = self::v187_characters($pattern);
        if ($patternCharacters === []) {
            return false;
        }

        $escapeCharacter = $escapeCharacters[0];
        $escaped = false;
        foreach ($patternCharacters as $character) {
            if ($escaped) {
                $escaped = false;
                continue;
            }
            if ($character === $escapeCharacter) {
                $escaped = true;
            }
        }

        return $escaped;
    }

    /** @return list<string> */
    private static function v187_characters(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if (is_array($characters)) {
            return array_values($characters);
        }

        return str_split($value);
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array{key:string,rowid:int,bytesHex?:string,encoding?:string}|null $lastYielded
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyReusedRowidResumePlan(
        array $currentRows,
        array $nextRows,
        string $pattern,
        ?string $escape = null,
        ?array $lastYielded = null,
        string $currentSource = 'main.app_settings@187',
        string $nextSource = 'main.app_settings@188',
        int $currentSchemaCookie = 187,
        int $nextSchemaCookie = 188,
    ): array {
        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyDeletedTokenResumePlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escape,
            $lastYielded,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $token = is_array($base['normalizedLastYielded']) ? $base['normalizedLastYielded'] : null;
        $rowidProbe = $token === null ? null : self::v188_probeNextRowid($nextRows, $token['rowid'], $pattern, $escape, $base['range']);
        $unsafe = $base['resumeUnsafeReasons'];
        if ($rowidProbe !== null && !$rowidProbe['sameToken']) {
            $unsafe[] = 'yield-token-rowid-reused';
            if (!$rowidProbe['insideRange']) {
                $unsafe[] = 'yield-token-rowid-reused-outside-range';
            }
            if (!$rowidProbe['matchesResidual']) {
                $unsafe[] = 'yield-token-rowid-reused-outside-like-residual';
            }
            if ($rowidProbe['decodeError'] !== null) {
                $unsafe[] = 'yield-token-rowid-reused-malformed';
            }
        }
        $unsafe = array_values(array_unique($unsafe));
        $safe = $unsafe === [];

        $base['status'] = 'utf16-nocase-like-rtrim-current-source-nextoneEightEight';
        $base['baseStatus'] = 'utf16-nocase-like-rtrim-current-source-nextoneEightFive';
        $base['nextRowidProbe'] = $rowidProbe;
        $base['rowidReuseDetected'] = $rowidProbe !== null && !$rowidProbe['sameToken'];
        $base['rowidReuseSafeForDeletedTokenResume'] = $safe;
        $base['resumeUnsafeReasons'] = $unsafe;
        $base['deletedTokenResumeSafe'] = $safe;
        $base['mustReprepareBeforeDeletedTokenResume'] = !$safe;
        $base['safeToResumeAfterDeletedToken'] = $safe;
        $base['replayPlanMode'] = $safe ? $base['replayPlanMode'] : 'reprepare-from-range-start-after-rowid-reuse';
        $base['replayPlanRowids'] = $safe ? $base['replayPlanRowids'] : $base['nextMatchedRowids'];
        $base['rowidReuseInvalidatesBeforeKeyBoundary'] = true;
        $base['rowidReuseCheckedBeforeDeletedTokenResume'] = true;
        $base['dependencies'] = [
            'sqlite-utf16-decode',
            'sqlite-rtrim-expression',
            'sqlite-nocase-like-deleted-token-resume',
            'sqlite-rowid-reuse-current-source-fence',
            'sqlite-current-source-nextoneEightEight',
        ];
        $base['dependency_closure'] = 'no new support component needed; reuses native UTF-16 decode, RTRIM expression keys, ASCII NOCASE LIKE matching, deleted-token replay diagnostics, and rowid source-fence checks';
        $base['non_overlap'] = 'adds next-source rowid reuse fencing before deleted-token resume for UTF-16 RTRIM/NOCASE LIKE cursors; avoids accepted nextOneEightFive deleted-token replay, nextOneEightFour escaped token residual validation, nextOneEightOne peer replay, Unicode GLOB ranges, UTF-16 malformed insert guards, and storage/planner clusters';

        return $base;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{rowid:int,text:?string,rtrimText:?string,key:?string,encoding:?string,bytesHex:string,insideRange:bool,matchesResidual:bool,sameToken:bool,decodeError:?string}|null
     */
    private static function v188_probeNextRowid(array $rows, int $rowid, string $pattern, ?string $escape, ?array $range): ?array
    {
        foreach ($rows as $row) {
            self::v188_assertRow($row);
            if ($row['setting_id'] !== $rowid) {
                continue;
            }

            $bytesHex = bin2hex($row['key_name_bytes']);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['key_name_bytes'], $row['text_encoding']);
                $rtrim = rtrim($text, ' ');
                $key = self::v188_asciiLower($rtrim);
                $insideRange = $range === null || (strcmp($key, $range['lowerInclusive']) >= 0 && ($range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0));
                $matchesResidual = SQLiteDatabase::likeMatches($rtrim, $pattern, $escape, false);

                return [
                    'rowid' => $rowid,
                    'text' => $text,
                    'rtrimText' => $rtrim,
                    'key' => $key,
                    'encoding' => self::v188_encodingName($row['text_encoding']),
                    'bytesHex' => $bytesHex,
                    'insideRange' => $insideRange,
                    'matchesResidual' => $matchesResidual,
                    'sameToken' => $insideRange && $matchesResidual,
                    'decodeError' => null,
                ];
            } catch (\InvalidArgumentException $exception) {
                return [
                    'rowid' => $rowid,
                    'text' => null,
                    'rtrimText' => null,
                    'key' => null,
                    'encoding' => self::v188_encodingName($row['text_encoding']),
                    'bytesHex' => $bytesHex,
                    'insideRange' => false,
                    'matchesResidual' => false,
                    'sameToken' => false,
                    'decodeError' => $exception->getMessage(),
                ];
            }
        }

        return null;
    }

    /** @param array<string,mixed> $row */
    private static function v188_assertRow(array $row): void
    {
        if (!array_key_exists('setting_id', $row) || !is_int($row['setting_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneEightEight rows require integer setting_id');
        }
        if (!array_key_exists('key_name_bytes', $row) || !is_string($row['key_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneEightEight rows require key_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneEightEight rows require integer text_encoding');
        }
    }

    private static function v188_asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    private static function v188_encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => 'unknown',
        };
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array{key:string,rowid:int}|null $resumeToken
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyPeerWindowPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_cache%',
        ?string $escape = '!',
        ?array $resumeToken = ['key' => 'plugin_cache', 'rowid' => 2],
        string $currentSource = 'main.app_settings@188',
        string $nextSource = 'main.app_settings@189',
        int $currentSchemaCookie = 188,
        int $nextSchemaCookie = 189,
    ): array {
        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyAsciiPrefixRangePlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escape,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $token = self::v189_normalizeToken($resumeToken);
        $peerKey = $token['key'] ?? null;
        $currentPeers = $peerKey === null ? [] : self::v189_rowidsForKey($base['currentNocaseKeys'], $peerKey, $base['currentMatchedRowids']);
        $nextPeers = $peerKey === null ? [] : self::v189_rowidsForKey($base['nextNocaseKeys'], $peerKey, $base['nextMatchedRowids']);
        $currentBeforeOrAt = $token === null ? [] : self::v189_beforeOrAt($currentPeers, $token['rowid']);
        $nextBeforeOrAt = $token === null ? [] : self::v189_beforeOrAt($nextPeers, $token['rowid']);
        $currentAfter = $token === null ? [] : self::v189_after($currentPeers, $token['rowid']);
        $nextAfter = $token === null ? [] : self::v189_after($nextPeers, $token['rowid']);
        $peerDeleted = array_values(array_diff($currentPeers, $nextPeers));
        $peerInserted = array_values(array_diff($nextPeers, $currentPeers));
        $paddingOnly = self::v189_paddingOnlyRowids($base);
        $residualChanged = self::v189_residualChangedRowids($base);

        $unsafe = [];
        if ($currentSource !== $nextSource || $currentSchemaCookie !== $nextSchemaCookie) {
            $unsafe[] = 'source-or-schema-changed';
        }
        if ($token === null) {
            $unsafe[] = 'yield-token-missing';
        } elseif ($token['normalizationReasons'] !== []) {
            $unsafe[] = 'yield-token-not-canonical';
        }
        if ($base['currentMalformedRowids'] !== [] || $base['nextMalformedRowids'] !== []) {
            $unsafe[] = 'malformed-text';
        }
        if ($nextBeforeOrAt !== $currentBeforeOrAt) {
            $unsafe[] = 'peer-before-token-changed';
        }
        if ($residualChanged !== []) {
            $unsafe[] = 'like-residual-rowset-changed';
        }

        $safe = $unsafe === [];
        $replay = $safe ? array_values(array_merge($nextAfter, self::v189_afterKeyRowids($base['nextNocaseKeys'], $base['nextMatchedRowids'], $peerKey))) : $base['nextMatchedRowids'];

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nextoneEightNine',
            'operator' => 'LIKE',
            'expression' => 'rtrim(key_name) COLLATE NOCASE LIKE ? ESCAPE ? /* peer window */',
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => 'NOCASE',
            'baseStatus' => $base['status'],
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'prefix' => $base['prefix'],
            'rangeLowerInclusive' => $base['rangeLowerInclusive'],
            'rangeUpperBound' => $base['rangeUpperBound'],
            'indexUsable' => $base['indexUsable'],
            'usesPrefixRangeCursor' => $base['usesPrefixRangeCursor'],
            'resumeToken' => $token,
            'peerKey' => $peerKey,
            'currentPeerRowids' => $currentPeers,
            'nextPeerRowids' => $nextPeers,
            'currentPeerBeforeOrAtTokenRowids' => $currentBeforeOrAt,
            'nextPeerBeforeOrAtTokenRowids' => $nextBeforeOrAt,
            'currentPeerAfterTokenRowids' => $currentAfter,
            'nextPeerAfterTokenRowids' => $nextAfter,
            'peerDeletedRowids' => $peerDeleted,
            'peerInsertedRowids' => $peerInserted,
            'paddingOnlyStableRowids' => $paddingOnly,
            'residualChangedRowids' => $residualChanged,
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'currentMatchedTexts' => $base['currentMatchedTexts'],
            'nextMatchedTexts' => $base['nextMatchedTexts'],
            'currentNocaseKeys' => $base['currentNocaseKeys'],
            'nextNocaseKeys' => $base['nextNocaseKeys'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'peerWindowUnsafeReasons' => array_values(array_unique($unsafe)),
            'peerWindowResumeSafe' => $safe,
            'mustReprepareBeforePeerWindowResume' => !$safe,
            'replayPlanMode' => $safe ? 'continue-after-rtrim-nocase-peer-window' : 'reprepare-from-range-start',
            'replayPlanRowids' => $replay,
            'rtrimPaddingOnlyKeepsPeerKey' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'likeResidualAppliesAfterRtrim' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-peer-window',
                'sqlite-current-source-nextoneEightNine',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE prefix range planning, RTRIM expression keys, and current-source peer-window replay diagnostics',
            'non_overlap' => 'nextOneEightNine adds peer-window rowid tie-breaker diagnostics for UTF-16 RTRIM/NOCASE LIKE cursors; avoids accepted deleted-token resume nextOneEightFive, resume-boundary nextOneEightSix, escaped residual token nextOneEightFour, Unicode GLOB ranges, UTF-16 malformed insert guards, and storage/planner clusters',
        ];
    }

    /** @param array{key:string,rowid:int}|null $token @return array{key:string,rowid:int,normalizationReasons:list<string>}|null */
    private static function v189_normalizeToken(?array $token): ?array
    {
        if ($token === null) {
            return null;
        }
        if (!isset($token['key']) || !is_string($token['key'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneEightNine resume token requires string key');
        }
        if (!isset($token['rowid']) || !is_int($token['rowid'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneEightNine resume token requires integer rowid');
        }

        $key = self::v189_asciiLower(rtrim($token['key'], ' '));

        return [
            'key' => $key,
            'rowid' => $token['rowid'],
            'normalizationReasons' => $key === $token['key'] ? [] : ['token-key-not-canonical'],
        ];
    }

    /** @param array<int,string> $keys @param list<int> $matchedRowids @return list<int> */
    private static function v189_rowidsForKey(array $keys, string $key, array $matchedRowids): array
    {
        return array_values(array_filter($matchedRowids, static fn (int $rowid): bool => ($keys[$rowid] ?? null) === $key));
    }

    /** @param list<int> $rowids @return list<int> */
    private static function v189_beforeOrAt(array $rowids, int $tokenRowid): array
    {
        return array_values(array_filter($rowids, static fn (int $rowid): bool => $rowid <= $tokenRowid));
    }

    /** @param list<int> $rowids @return list<int> */
    private static function v189_after(array $rowids, int $tokenRowid): array
    {
        return array_values(array_filter($rowids, static fn (int $rowid): bool => $rowid > $tokenRowid));
    }

    /** @param array<string,mixed> $base @return list<int> */
    private static function v189_paddingOnlyRowids(array $base): array
    {
        $stable = [];
        foreach ($base['changedTextRowids'] as $rowid) {
            if (($base['currentRtrimTexts'][$rowid] ?? null) === ($base['nextRtrimTexts'][$rowid] ?? null)
                && ($base['currentNocaseKeys'][$rowid] ?? null) === ($base['nextNocaseKeys'][$rowid] ?? null)) {
                $stable[] = (int) $rowid;
            }
        }

        return $stable;
    }

    /** @param array<string,mixed> $base @return list<int> */
    private static function v189_residualChangedRowids(array $base): array
    {
        $changed = array_values(array_unique(array_merge(
            $base['matchedEnteredRowids'],
            $base['matchedExitedRowids'],
            $base['currentRangeFalsePositiveRowids'],
            $base['nextRangeFalsePositiveRowids'],
        )));
        sort($changed);

        return $changed;
    }

    /** @param array<int,string> $keys @param list<int> $matchedRowids @return list<int> */
    private static function v189_afterKeyRowids(array $keys, array $matchedRowids, ?string $key): array
    {
        if ($key === null) {
            return [];
        }

        return array_values(array_filter($matchedRowids, static fn (int $rowid): bool => isset($keys[$rowid]) && strcmp($keys[$rowid], $key) > 0));
    }

    private static function v189_asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyAsciiSpaceTrimBoundaryPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin%',
        ?string $escape = null,
        string $currentSource = 'main.app_settings@189',
        string $nextSource = 'main.app_settings@190',
        int $currentSchemaCookie = 189,
        int $nextSchemaCookie = 190,
    ): array {
        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyAsciiPrefixRangePlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escape,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $currentWhitespace = self::v190_classifyWhitespaceSuffixes($base['currentTexts'], $base['currentRtrimTexts']);
        $nextWhitespace = self::v190_classifyWhitespaceSuffixes($base['nextTexts'], $base['nextRtrimTexts']);
        $changedWhitespace = self::v190_changedWhitespaceClasses($currentWhitespace, $nextWhitespace);
        $asciiTrimOnlyChanged = self::v190_asciiTrimOnlyChangedRowids($base, $changedWhitespace);
        $rangeRetainedChanged = array_values(array_intersect($base['currentRangeRetainedRowids'], $asciiTrimOnlyChanged));
        $matchedRetainedChanged = array_values(array_intersect($base['matchedRetainedRowids'], $asciiTrimOnlyChanged));

        $reasons = $base['invalidationReasons'];
        if ($changedWhitespace !== []) {
            $reasons[] = 'trailing-whitespace-class';
        }
        if ($rangeRetainedChanged !== []) {
            $reasons[] = 'retained-prefix-rtrim-key';
        }
        $reasons = array_values(array_unique($reasons));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nextoneNineZero',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ?',
            'baseStatus' => $base['status'],
            'pattern' => $base['pattern'],
            'escape' => $base['escape'],
            'prefix' => $base['prefix'],
            'rangeLowerInclusive' => $base['rangeLowerInclusive'],
            'rangeUpperBound' => $base['rangeUpperBound'],
            'usesPrefixRangeCursor' => $base['usesPrefixRangeCursor'],
            'currentSource' => $base['currentSource'],
            'nextSource' => $base['nextSource'],
            'currentSchemaCookie' => $base['currentSchemaCookie'],
            'nextSchemaCookie' => $base['nextSchemaCookie'],
            'currentCandidateRowids' => $base['currentCandidateRowids'],
            'nextCandidateRowids' => $base['nextCandidateRowids'],
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'currentMatchedTexts' => $base['currentMatchedTexts'],
            'nextMatchedTexts' => $base['nextMatchedTexts'],
            'currentRtrimTexts' => $base['currentRtrimTexts'],
            'nextRtrimTexts' => $base['nextRtrimTexts'],
            'currentNocaseKeys' => $base['currentNocaseKeys'],
            'nextNocaseKeys' => $base['nextNocaseKeys'],
            'currentTrailingWhitespace' => $currentWhitespace,
            'nextTrailingWhitespace' => $nextWhitespace,
            'changedTrailingWhitespaceClassRowids' => $changedWhitespace,
            'asciiSpaceTrimBoundaryChangedRowids' => $asciiTrimOnlyChanged,
            'retainedRangeRtrimKeyChangedRowids' => $rangeRetainedChanged,
            'retainedMatchRtrimKeyChangedRowids' => $matchedRetainedChanged,
            'rangeRetainedRowids' => $base['currentRangeRetainedRowids'],
            'rangeExitedRowids' => $base['currentRangeExitedRowids'],
            'rangeEnteredRowids' => $base['nextRangeEnteredRowids'],
            'matchedRetainedRowids' => $base['matchedRetainedRowids'],
            'matchedExitedRowids' => $base['matchedExitedRowids'],
            'matchedEnteredRowids' => $base['matchedEnteredRowids'],
            'currentExcludedDecodedRowids' => $base['currentExcludedDecodedRowids'],
            'nextExcludedDecodedRowids' => $base['nextExcludedDecodedRowids'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'currentErrors' => $base['currentErrors'],
            'nextErrors' => $base['nextErrors'],
            'candidateRowsetChanged' => $base['currentCandidateRowids'] !== $base['nextCandidateRowids'],
            'matchedRowsetChanged' => $base['currentMatchedRowids'] !== $base['nextMatchedRowids'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'staleRangeCursorRisk' => $base['staleRangeCursorRisk'] || $rangeRetainedChanged !== [],
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nonBreakingSpaceNotTrimmed' => true,
            'tabNotTrimmed' => true,
            'nocaseFoldsAsciiOnly' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-ascii-space-boundary',
                'sqlite-current-source-nextoneNineZero',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE prefix planning, RTRIM expression keys, and current-source invalidation diagnostics',
            'non_overlap' => 'adds ASCII-space-only RTRIM boundary diagnostics for retained UTF-16 NOCASE LIKE prefix cursor rows; avoids accepted nextOneEightSeven dangling ESCAPE residual checks, nextOneEightThree prefix range reuse, nextOneEightFour escaped peer replay, Unicode GLOB ranges, and malformed UTF-16 insert guards',
        ];
    }

    /** @param array<int,string> $texts @param array<int,string> $rtrimTexts @return array<int,array<string,mixed>> */
    private static function v190_classifyWhitespaceSuffixes(array $texts, array $rtrimTexts): array
    {
        $classes = [];
        foreach ($texts as $rowid => $text) {
            $suffix = substr($text, strlen($rtrimTexts[$rowid] ?? $text));
            $classes[(int) $rowid] = [
                'suffix' => $suffix,
                'suffixHex' => bin2hex($suffix),
                'asciiSpaceCount' => substr_count($suffix, ' '),
                'hasTabSuffix' => str_ends_with($text, "\t"),
                'hasNewlineSuffix' => str_ends_with($text, "\n"),
                'hasNonBreakingSpaceSuffix' => str_ends_with($text, "\u{00a0}"),
                'trimmedByRtrim' => $suffix !== '',
            ];
        }
        ksort($classes);

        return $classes;
    }

    /** @param array<int,array<string,mixed>> $current @param array<int,array<string,mixed>> $next @return list<int> */
    private static function v190_changedWhitespaceClasses(array $current, array $next): array
    {
        $rowids = array_values(array_intersect(array_keys($current), array_keys($next)));
        sort($rowids);
        $changed = [];
        foreach ($rowids as $rowid) {
            if (($current[$rowid]['suffixHex'] ?? null) !== ($next[$rowid]['suffixHex'] ?? null)) {
                $changed[] = (int) $rowid;
            }
        }

        return $changed;
    }

    /** @param array<string,mixed> $base @param list<int> $changedWhitespace @return list<int> */
    private static function v190_asciiTrimOnlyChangedRowids(array $base, array $changedWhitespace): array
    {
        $changed = array_values(array_intersect(
            $changedWhitespace,
            $base['changedRtrimRowids'],
            $base['changedNocaseKeyRowids'],
        ));
        sort($changed);

        return $changed;
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyPreparedPatternRebindPlan(
        array $currentRows,
        array $nextRows,
        string $currentPatternBytes,
        int|string $currentPatternEncoding,
        string $nextPatternBytes,
        int|string $nextPatternEncoding,
        ?string $currentEscapeBytes = null,
        int|string|null $currentEscapeEncoding = null,
        ?string $nextEscapeBytes = null,
        int|string|null $nextEscapeEncoding = null,
        string $currentSource = 'main.app_settings@190',
        string $nextSource = 'main.app_settings@191',
        int $currentSchemaCookie = 190,
        int $nextSchemaCookie = 191,
    ): array {
        $currentPattern = self::v191_decodePreparedText($currentPatternBytes, $currentPatternEncoding, 'pattern');
        $nextPattern = self::v191_decodePreparedText($nextPatternBytes, $nextPatternEncoding, 'pattern');
        $currentEscape = $currentEscapeBytes === null
            ? null
            : self::v191_decodePreparedText($currentEscapeBytes, $currentEscapeEncoding ?? $currentPatternEncoding, 'escape');
        $nextEscape = $nextEscapeBytes === null
            ? null
            : self::v191_decodePreparedText($nextEscapeBytes, $nextEscapeEncoding ?? $nextPatternEncoding, 'escape');

        $current = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyAsciiPrefixRangePlan(
            $currentRows,
            $currentRows,
            $currentPattern,
            $currentEscape,
            $currentSource,
            $currentSource,
            $currentSchemaCookie,
            $currentSchemaCookie,
        );
        $next = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyAsciiPrefixRangePlan(
            $nextRows,
            $nextRows,
            $nextPattern,
            $nextEscape,
            $nextSource,
            $nextSource,
            $nextSchemaCookie,
            $nextSchemaCookie,
        );

        $currentCandidateRowids = $current['currentCandidateRowids'];
        $nextCandidateRowids = $next['currentCandidateRowids'];
        $currentMatchedRowids = $current['currentMatchedRowids'];
        $nextMatchedRowids = $next['currentMatchedRowids'];
        $sameDecodedPattern = $currentPattern === $nextPattern && $currentEscape === $nextEscape;
        $samePreparedBytes = $currentPatternBytes === $nextPatternBytes
            && $currentPatternEncoding === $nextPatternEncoding
            && $currentEscapeBytes === $nextEscapeBytes
            && $currentEscapeEncoding === $nextEscapeEncoding;
        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if (!$sameDecodedPattern) {
            $reasons[] = 'decoded-pattern-or-escape';
        } elseif (!$samePreparedBytes) {
            $reasons[] = 'prepared-pattern-byte-order-refresh';
        }
        foreach ([
            'range-bound' => self::v191_rangeChanged($current, $next),
            'candidate-rowset' => $currentCandidateRowids !== $nextCandidateRowids,
            'matched-rowset' => $currentMatchedRowids !== $nextMatchedRowids,
            'range-false-positive-rowset' => $current['currentRangeFalsePositiveRowids'] !== $next['currentRangeFalsePositiveRowids'],
        ] as $reason => $changed) {
            if ($changed) {
                $reasons[] = $reason;
            }
        }
        if ($current['currentMalformedRowids'] !== [] || $next['currentMalformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nextoneNineOne',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* prepared UTF-16 rebind */',
            'currentPattern' => $currentPattern,
            'nextPattern' => $nextPattern,
            'currentEscape' => $currentEscape,
            'nextEscape' => $nextEscape,
            'currentPatternEncoding' => self::v191_encodingName($currentPatternEncoding),
            'nextPatternEncoding' => self::v191_encodingName($nextPatternEncoding),
            'currentEscapeEncoding' => $currentEscapeBytes === null ? null : self::v191_encodingName($currentEscapeEncoding ?? $currentPatternEncoding),
            'nextEscapeEncoding' => $nextEscapeBytes === null ? null : self::v191_encodingName($nextEscapeEncoding ?? $nextPatternEncoding),
            'currentPatternBytesHex' => bin2hex($currentPatternBytes),
            'nextPatternBytesHex' => bin2hex($nextPatternBytes),
            'currentEscapeBytesHex' => $currentEscapeBytes === null ? null : bin2hex($currentEscapeBytes),
            'nextEscapeBytesHex' => $nextEscapeBytes === null ? null : bin2hex($nextEscapeBytes),
            'sameDecodedPatternAndEscape' => $sameDecodedPattern,
            'samePreparedPatternBytes' => $samePreparedBytes,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentPrefix' => $current['prefix'],
            'nextPrefix' => $next['prefix'],
            'currentRangeLowerInclusive' => $current['rangeLowerInclusive'],
            'nextRangeLowerInclusive' => $next['rangeLowerInclusive'],
            'currentRangeUpperBound' => $current['rangeUpperBound'],
            'nextRangeUpperBound' => $next['rangeUpperBound'],
            'currentIndexUsable' => $current['indexUsable'],
            'nextIndexUsable' => $next['indexUsable'],
            'currentCandidateRowids' => $currentCandidateRowids,
            'nextCandidateRowids' => $nextCandidateRowids,
            'candidateRetainedRowids' => self::v191_retained($currentCandidateRowids, $nextCandidateRowids),
            'candidateExitedRowids' => self::v191_exited($currentCandidateRowids, $nextCandidateRowids),
            'candidateEnteredRowids' => self::v191_entered($currentCandidateRowids, $nextCandidateRowids),
            'currentMatchedRowids' => $currentMatchedRowids,
            'nextMatchedRowids' => $nextMatchedRowids,
            'matchedRetainedRowids' => self::v191_retained($currentMatchedRowids, $nextMatchedRowids),
            'matchedExitedRowids' => self::v191_exited($currentMatchedRowids, $nextMatchedRowids),
            'matchedEnteredRowids' => self::v191_entered($currentMatchedRowids, $nextMatchedRowids),
            'currentRangeFalsePositiveRowids' => $current['currentRangeFalsePositiveRowids'],
            'nextRangeFalsePositiveRowids' => $next['currentRangeFalsePositiveRowids'],
            'currentRtrimTexts' => $current['currentRtrimTexts'],
            'nextRtrimTexts' => $next['currentRtrimTexts'],
            'currentNocaseKeys' => $current['currentNocaseKeys'],
            'nextNocaseKeys' => $next['currentNocaseKeys'],
            'currentMatchedTexts' => $current['currentMatchedTexts'],
            'nextMatchedTexts' => $next['currentMatchedTexts'],
            'currentMalformedRowids' => $current['currentMalformedRowids'],
            'nextMalformedRowids' => $next['currentMalformedRowids'],
            'currentErrors' => $current['currentErrors'],
            'nextErrors' => $next['currentErrors'],
            'mustReprepareForPatternChange' => !$sameDecodedPattern,
            'canReuseResidualForByteOrderOnlyRebind' => $sameDecodedPattern,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => array_values(array_unique($reasons)),
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-prepared-like-pattern-rebind',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-current-source-nextoneNineOne',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, prepared LIKE pattern normalization, ASCII NOCASE prefix ranges, RTRIM keys, and current-source diagnostics',
            'non_overlap' => 'adds prepared UTF-16 pattern rebind diagnostics where decoded LIKE pattern or escape changes between current and next sources; avoids accepted stable byte-order normalization, resume-token, dangling-escape, NUL, case_sensitive_like, Unicode GLOB, and malformed insert guard clusters',
        ];
    }

    private static function v191_decodePreparedText(string $bytes, int|string $encoding, string $label): string
    {
        try {
            return SQLiteEncodingCollationSourceCursor::decodeText($bytes, self::v191_encodingId($encoding));
        } catch (\InvalidArgumentException $exception) {
            throw new \InvalidArgumentException("SQLite UTF-16 NOCASE LIKE RTRIM nextOneNineOne prepared {$label} is malformed: " . $exception->getMessage());
        }
    }

    /** @param array<string,mixed> $current @param array<string,mixed> $next */
    private static function v191_rangeChanged(array $current, array $next): bool
    {
        return ($current['rangeLowerInclusive'] ?? null) !== ($next['rangeLowerInclusive'] ?? null)
            || ($current['rangeUpperBound'] ?? null) !== ($next['rangeUpperBound'] ?? null);
    }

    /** @param list<int> $current @param list<int> $next @return list<int> */
    private static function v191_retained(array $current, array $next): array
    {
        return array_values(array_intersect($current, $next));
    }

    /** @param list<int> $current @param list<int> $next @return list<int> */
    private static function v191_exited(array $current, array $next): array
    {
        return array_values(array_diff($current, $next));
    }

    /** @param list<int> $current @param list<int> $next @return list<int> */
    private static function v191_entered(array $current, array $next): array
    {
        return array_values(array_diff($next, $current));
    }

    private static function v191_encodingId(int|string $encoding): int
    {
        return match ($encoding) {
            1, 'UTF-8' => 1,
            2, 'UTF-16LE' => 2,
            3, 'UTF-16BE' => 3,
            default => throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneNineOne encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function v191_encodingName(int|string $encoding): string
    {
        return match (self::v191_encodingId($encoding)) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
        };
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array{key:string,rowid:int}|null $resumeToken
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyCandidateTokenPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_cache',
        ?string $escape = '!',
        ?array $resumeToken = ['key' => 'plugin_cache_old', 'rowid' => 6],
        string $currentSource = 'main.app_settings@191',
        string $nextSource = 'main.app_settings@192',
        int $currentSchemaCookie = 191,
        int $nextSchemaCookie = 192,
    ): array {
        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyAsciiPrefixRangePlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escape,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $token = self::v192_normalizeToken($resumeToken);
        $currentCandidateBefore = self::v192_candidateBeforeOrAt($base['currentNocaseKeys'], $base['currentCandidateRowids'], $token);
        $nextCandidateBefore = self::v192_candidateBeforeOrAt($base['nextNocaseKeys'], $base['nextCandidateRowids'], $token);
        $currentFalseBefore = array_values(array_intersect($currentCandidateBefore, $base['currentRangeFalsePositiveRowids']));
        $nextFalseBefore = array_values(array_intersect($nextCandidateBefore, $base['nextRangeFalsePositiveRowids']));
        $currentMatchedBefore = array_values(array_intersect($currentCandidateBefore, $base['currentMatchedRowids']));
        $nextMatchedBefore = array_values(array_intersect($nextCandidateBefore, $base['nextMatchedRowids']));
        $nextReplayAfter = self::v192_candidateAfter($base['nextNocaseKeys'], $base['nextCandidateRowids'], $token);

        $unsafe = [];
        if ($currentSource !== $nextSource || $currentSchemaCookie !== $nextSchemaCookie) {
            $unsafe[] = 'source-or-schema-changed';
        }
        if ($token === null) {
            $unsafe[] = 'yield-token-missing';
        } elseif ($token['normalizationReasons'] !== []) {
            $unsafe[] = 'yield-token-not-canonical';
        }
        if ($base['currentMalformedRowids'] !== [] || $base['nextMalformedRowids'] !== []) {
            $unsafe[] = 'malformed-text';
        }
        if ($currentCandidateBefore !== $nextCandidateBefore) {
            $unsafe[] = 'candidate-before-token-changed';
        }
        if ($currentFalseBefore !== $nextFalseBefore) {
            $unsafe[] = 'false-positive-before-token-changed';
        }
        if ($currentMatchedBefore !== $nextMatchedBefore) {
            $unsafe[] = 'matched-before-token-changed';
        }
        if ($token !== null
            && !in_array($token['rowid'], $base['nextCandidateRowids'], true)
            && !in_array($token['rowid'], $base['nextMatchedRowids'], true)) {
            $unsafe[] = 'yield-token-row-missing';
        }

        $safe = $unsafe === [];

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nextoneNineTwo',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* candidate residual token */',
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => 'NOCASE',
            'baseStatus' => $base['status'],
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'prefix' => $base['prefix'],
            'rangeLowerInclusive' => $base['rangeLowerInclusive'],
            'rangeUpperBound' => $base['rangeUpperBound'],
            'usesPrefixRangeCursor' => $base['usesPrefixRangeCursor'],
            'resumeToken' => $token,
            'currentCandidateRowids' => $base['currentCandidateRowids'],
            'nextCandidateRowids' => $base['nextCandidateRowids'],
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'currentRangeFalsePositiveRowids' => $base['currentRangeFalsePositiveRowids'],
            'nextRangeFalsePositiveRowids' => $base['nextRangeFalsePositiveRowids'],
            'currentCandidateBeforeOrAtTokenRowids' => $currentCandidateBefore,
            'nextCandidateBeforeOrAtTokenRowids' => $nextCandidateBefore,
            'currentFalsePositiveBeforeOrAtTokenRowids' => $currentFalseBefore,
            'nextFalsePositiveBeforeOrAtTokenRowids' => $nextFalseBefore,
            'currentMatchedBeforeOrAtTokenRowids' => $currentMatchedBefore,
            'nextMatchedBeforeOrAtTokenRowids' => $nextMatchedBefore,
            'nextReplayCandidateRowidsAfterToken' => $nextReplayAfter,
            'currentRtrimTexts' => $base['currentRtrimTexts'],
            'nextRtrimTexts' => $base['nextRtrimTexts'],
            'currentNocaseKeys' => $base['currentNocaseKeys'],
            'nextNocaseKeys' => $base['nextNocaseKeys'],
            'currentMatchedTexts' => $base['currentMatchedTexts'],
            'nextMatchedTexts' => $base['nextMatchedTexts'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'candidateTokenUnsafeReasons' => array_values(array_unique($unsafe)),
            'candidateTokenResumeSafe' => $safe,
            'mustReprepareBeforeCandidateTokenResume' => !$safe,
            'replayPlanMode' => $safe ? 'continue-after-candidate-token' : 'reprepare-from-range-start',
            'replayPlanRowids' => $safe ? $nextReplayAfter : $base['nextCandidateRowids'],
            'residualRecheckRequiredForCandidates' => $base['currentRangeFalsePositiveRowids'] !== [] || $base['nextRangeFalsePositiveRowids'] !== [],
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'likeResidualAppliesAfterRtrim' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-candidate-token',
                'sqlite-like-residual-recheck',
                'sqlite-current-source-nextoneNineTwo',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE prefix range planning, RTRIM expression keys, and residual LIKE rechecks',
            'non_overlap' => 'nextOneNineTwo adds candidate-token resume safety when UTF-16 RTRIM/NOCASE LIKE range rows include residual false positives; avoids accepted nextOneEightThree prefix reuse, nextOneEightSeven dangling ESCAPE, nextOneEightNine peer-window matched-row resume, Unicode GLOB ranges, UTF-16 malformed insert guards, and storage/planner clusters',
        ];
    }

    /** @param array{key:string,rowid:int}|null $token @return array{key:string,rowid:int,normalizationReasons:list<string>}|null */
    private static function v192_normalizeToken(?array $token): ?array
    {
        if ($token === null) {
            return null;
        }
        if (!isset($token['key']) || !is_string($token['key'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneNineTwo resume token requires string key');
        }
        if (!isset($token['rowid']) || !is_int($token['rowid'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneNineTwo resume token requires integer rowid');
        }

        $key = self::v192_asciiLower(rtrim($token['key'], ' '));

        return [
            'key' => $key,
            'rowid' => $token['rowid'],
            'normalizationReasons' => $key === $token['key'] ? [] : ['token-key-not-canonical'],
        ];
    }

    /**
     * @param array<int,string> $keys
     * @param list<int> $rowids
     * @param array{key:string,rowid:int,normalizationReasons:list<string>}|null $token
     * @return list<int>
     */
    private static function v192_candidateBeforeOrAt(array $keys, array $rowids, ?array $token): array
    {
        if ($token === null) {
            return [];
        }

        return array_values(array_filter($rowids, static function (int $rowid) use ($keys, $token): bool {
            $key = $keys[$rowid] ?? null;
            if ($key === null) {
                return false;
            }
            $comparison = strcmp($key, $token['key']);

            return $comparison < 0 || ($comparison === 0 && $rowid <= $token['rowid']);
        }));
    }

    /**
     * @param array<int,string> $keys
     * @param list<int> $rowids
     * @param array{key:string,rowid:int,normalizationReasons:list<string>}|null $token
     * @return list<int>
     */
    private static function v192_candidateAfter(array $keys, array $rowids, ?array $token): array
    {
        if ($token === null) {
            return [];
        }

        return array_values(array_filter($rowids, static function (int $rowid) use ($keys, $token): bool {
            $key = $keys[$rowid] ?? null;
            if ($key === null) {
                return false;
            }
            $comparison = strcmp($key, $token['key']);

            return $comparison > 0 || ($comparison === 0 && $rowid > $token['rowid']);
        }));
    }

    private static function v192_asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyLimitOffsetPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_cache%',
        ?string $escape = '!',
        int $limit = 3,
        int $offset = 2,
        string $currentSource = 'main.app_settings@192',
        string $nextSource = 'main.app_settings@193',
        int $currentSchemaCookie = 192,
        int $nextSchemaCookie = 193,
    ): array {
        if ($limit < 0) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneNineThree LIMIT must be non-negative');
        }
        if ($offset < 0) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneNineThree OFFSET must be non-negative');
        }

        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyAsciiPrefixRangePlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escape,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $currentOrdered = self::v193_orderedRows($base['currentNocaseKeys'], $base['currentMatchedRowids']);
        $nextOrdered = self::v193_orderedRows($base['nextNocaseKeys'], $base['nextMatchedRowids']);
        $currentSkipped = array_slice($currentOrdered, 0, $offset);
        $nextSkipped = array_slice($nextOrdered, 0, $offset);
        $currentWindow = array_slice($currentOrdered, $offset, $limit);
        $nextWindow = array_slice($nextOrdered, $offset, $limit);
        $currentAfterWindow = array_slice($currentOrdered, $offset + $limit);
        $nextAfterWindow = array_slice($nextOrdered, $offset + $limit);

        $windowEntered = array_values(array_diff($nextWindow, $currentWindow));
        $windowExited = array_values(array_diff($currentWindow, $nextWindow));
        $skippedEntered = array_values(array_diff($nextSkipped, $currentSkipped));
        $skippedExited = array_values(array_diff($currentSkipped, $nextSkipped));
        $beforeWindowChanged = $currentSkipped !== $nextSkipped;

        $reasons = [];
        if ($currentSource !== $nextSource || $currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'source-or-schema-changed';
        }
        if ($base['currentMalformedRowids'] !== [] || $base['nextMalformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        if (!$base['indexUsable'] || !$base['usesPrefixRangeCursor']) {
            $reasons[] = 'prefix-range-unusable';
        }
        if ($beforeWindowChanged) {
            $reasons[] = 'offset-prefix-rowset-changed';
        }
        if ($windowEntered !== [] || $windowExited !== []) {
            $reasons[] = 'limit-window-rowset-changed';
        }
        if ($base['rtrimResidualChangedRowids'] !== []) {
            $reasons[] = 'rtrim-like-residual-changed';
        }

        $resumeSafe = $reasons === [];

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nextoneNineThree',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? LIMIT ? OFFSET ?',
            'pattern' => $pattern,
            'escape' => $escape,
            'limit' => $limit,
            'offset' => $offset,
            'collation' => 'NOCASE',
            'baseStatus' => $base['status'],
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'prefix' => $base['prefix'],
            'rangeLowerInclusive' => $base['rangeLowerInclusive'],
            'rangeUpperBound' => $base['rangeUpperBound'],
            'usesPrefixRangeCursor' => $base['usesPrefixRangeCursor'],
            'currentOrderedMatchedRowids' => $currentOrdered,
            'nextOrderedMatchedRowids' => $nextOrdered,
            'currentSkippedRowids' => $currentSkipped,
            'nextSkippedRowids' => $nextSkipped,
            'currentLimitWindowRowids' => $currentWindow,
            'nextLimitWindowRowids' => $nextWindow,
            'currentAfterWindowRowids' => $currentAfterWindow,
            'nextAfterWindowRowids' => $nextAfterWindow,
            'skippedEnteredRowids' => $skippedEntered,
            'skippedExitedRowids' => $skippedExited,
            'limitWindowEnteredRowids' => $windowEntered,
            'limitWindowExitedRowids' => $windowExited,
            'offsetPrefixChanged' => $beforeWindowChanged,
            'currentWindowTexts' => self::v193_selectMap($base['currentMatchedTexts'], $currentWindow),
            'nextWindowTexts' => self::v193_selectMap($base['nextMatchedTexts'], $nextWindow),
            'currentSkippedTexts' => self::v193_selectMap($base['currentMatchedTexts'], $currentSkipped),
            'nextSkippedTexts' => self::v193_selectMap($base['nextMatchedTexts'], $nextSkipped),
            'currentNocaseKeys' => $base['currentNocaseKeys'],
            'nextNocaseKeys' => $base['nextNocaseKeys'],
            'currentRtrimTexts' => $base['currentRtrimTexts'],
            'nextRtrimTexts' => $base['nextRtrimTexts'],
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'baseInvalidationReasons' => $base['invalidationReasons'],
            'limitOffsetUnsafeReasons' => array_values(array_unique($reasons)),
            'limitOffsetResumeSafe' => $resumeSafe,
            'mustReprepareBeforeLimitOffsetResume' => !$resumeSafe,
            'replayPlanMode' => $resumeSafe ? 'continue-after-limit-window' : 'recompute-limit-offset-window',
            'replayPlanRowids' => $resumeSafe ? $nextAfterWindow : $nextWindow,
            'offsetCountsDecodedRowsNotBytes' => true,
            'limitWindowUsesRtrimNocaseOrder' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'likeResidualAppliesBeforeLimitOffset' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-limit-offset-window',
                'sqlite-current-source-nextoneNineThree',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE prefix ranges, RTRIM expression keys, and adds LIMIT/OFFSET current-source replay diagnostics',
            'non_overlap' => 'nextOneNineThree adds LIMIT/OFFSET window replay diagnostics for UTF-16 RTRIM/NOCASE LIKE cursors; avoids accepted nextOneEightNine peer-window rowid tie-breakers, deleted-token resume, escaped residual token, case-sensitive LIKE, Unicode GLOB ranges, UTF-16 malformed insert guards, and storage/planner clusters',
        ];
    }

    /** @param array<int,string> $keys @param list<int> $rowids @return list<int> */
    private static function v193_orderedRows(array $keys, array $rowids): array
    {
        $ordered = $rowids;
        usort($ordered, static function (int $left, int $right) use ($keys): int {
            $keyCompare = strcmp($keys[$left] ?? '', $keys[$right] ?? '');
            if ($keyCompare !== 0) {
                return $keyCompare;
            }

            return $left <=> $right;
        });

        return $ordered;
    }

    /** @param array<int,string> $values @param list<int> $rowids @return array<int,string> */
    private static function v193_selectMap(array $values, array $rowids): array
    {
        $selected = [];
        foreach ($rowids as $rowid) {
            if (array_key_exists($rowid, $values)) {
                $selected[$rowid] = $values[$rowid];
            }
        }

        return $selected;
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyEscapedWildcardPrefixPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!%%',
        ?string $escape = '!',
        string $currentSource = 'main.app_settings@193',
        string $nextSource = 'main.app_settings@194',
        int $currentSchemaCookie = 193,
        int $nextSchemaCookie = 194,
    ): array {
        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyAsciiPrefixRangePlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escape,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $literalWildcards = self::v194_literalWildcardCharactersInPrefix($pattern, $escape, (string) $base['prefix']);
        $currentLiteralPrefixFalsePositive = self::v194_literalPrefixFalsePositiveRowids($base['currentRtrimTexts'], $base['currentCandidateRowids'], (string) $base['prefix']);
        $nextLiteralPrefixFalsePositive = self::v194_literalPrefixFalsePositiveRowids($base['nextRtrimTexts'], $base['nextCandidateRowids'], (string) $base['prefix']);
        $matchedChanged = array_values(array_unique(array_merge(
            $base['matchedExitedRowids'],
            $base['matchedEnteredRowids'],
            $base['changedRtrimRowids'],
            $currentLiteralPrefixFalsePositive,
            $nextLiteralPrefixFalsePositive,
        )));
        sort($matchedChanged);

        $reasons = $base['invalidationReasons'];
        if ($literalWildcards !== []) {
            $reasons[] = 'escaped-like-wildcard-literal-prefix';
        }
        if ($currentLiteralPrefixFalsePositive !== [] || $nextLiteralPrefixFalsePositive !== []) {
            $reasons[] = 'literal-prefix-residual-recheck';
        }
        if ($matchedChanged !== []) {
            $reasons[] = 'matched-literal-prefix-rowset';
        }
        $reasons = array_values(array_unique($reasons));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nextoneNineFour',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* escaped wildcard literal prefix */',
            'pattern' => $base['pattern'],
            'escape' => $base['escape'],
            'collation' => 'NOCASE',
            'baseStatus' => $base['status'],
            'currentSource' => $base['currentSource'],
            'nextSource' => $base['nextSource'],
            'currentSchemaCookie' => $base['currentSchemaCookie'],
            'nextSchemaCookie' => $base['nextSchemaCookie'],
            'prefix' => $base['prefix'],
            'rangeLowerInclusive' => $base['rangeLowerInclusive'],
            'rangeUpperBound' => $base['rangeUpperBound'],
            'indexUsable' => $base['indexUsable'],
            'usesPrefixRangeCursor' => $base['usesPrefixRangeCursor'],
            'escapedWildcardLiteralsInPrefix' => $literalWildcards,
            'escapedPercentIsLiteralPrefixByte' => in_array('%', $literalWildcards, true),
            'escapedUnderscoreIsLiteralPrefixByte' => in_array('_', $literalWildcards, true),
            'currentCandidateRowids' => $base['currentCandidateRowids'],
            'nextCandidateRowids' => $base['nextCandidateRowids'],
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'currentRangeFalsePositiveRowids' => $base['currentRangeFalsePositiveRowids'],
            'nextRangeFalsePositiveRowids' => $base['nextRangeFalsePositiveRowids'],
            'currentLiteralPrefixFalsePositiveRowids' => $currentLiteralPrefixFalsePositive,
            'nextLiteralPrefixFalsePositiveRowids' => $nextLiteralPrefixFalsePositive,
            'currentMatchedTexts' => $base['currentMatchedTexts'],
            'nextMatchedTexts' => $base['nextMatchedTexts'],
            'currentRtrimTexts' => $base['currentRtrimTexts'],
            'nextRtrimTexts' => $base['nextRtrimTexts'],
            'currentNocaseKeys' => $base['currentNocaseKeys'],
            'nextNocaseKeys' => $base['nextNocaseKeys'],
            'currentExcludedDecodedRowids' => $base['currentExcludedDecodedRowids'],
            'nextExcludedDecodedRowids' => $base['nextExcludedDecodedRowids'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'currentErrors' => $base['currentErrors'],
            'nextErrors' => $base['nextErrors'],
            'rangeRetainedRowids' => $base['currentRangeRetainedRowids'],
            'rangeExitedRowids' => $base['currentRangeExitedRowids'],
            'rangeEnteredRowids' => $base['nextRangeEnteredRowids'],
            'matchedLiteralPrefixChangedRowids' => $matchedChanged,
            'candidateRowsetChanged' => $base['currentCandidateRowids'] !== $base['nextCandidateRowids'],
            'matchedRowsetChanged' => $base['currentMatchedRowids'] !== $base['nextMatchedRowids'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'staleRangeCursorRisk' => $base['staleRangeCursorRisk'] || $matchedChanged !== [],
            'likeResidualAppliesAfterRtrim' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-escaped-wildcard-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-current-source-nextoneNineFour',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE prefix planning, RTRIM keys, and LIKE residual matching',
            'non_overlap' => 'nextOneNineFour adds escaped percent/underscore literal-prefix range diagnostics for UTF-16 NOCASE/RTRIM LIKE current-source cursors; avoids accepted dangling ESCAPE nextOneEightSeven, peer-window nextOneEightNine, Unicode GLOB ranges, and malformed UTF-16 insert guards',
        ];
    }

    /** @return list<string> */
    private static function v194_literalWildcardCharactersInPrefix(string $pattern, ?string $escape, string $prefix): array
    {
        if ($escape === null || $prefix === '') {
            return [];
        }

        $escapeCharacters = self::v194_characters($escape);
        if (count($escapeCharacters) !== 1) {
            return [];
        }

        $escapeCharacter = $escapeCharacters[0];
        $characters = self::v194_characters($pattern);
        $prefixCharacters = self::v194_characters($prefix);
        $literalWildcards = [];
        $prefixPosition = 0;
        $escaped = false;
        foreach ($characters as $character) {
            if ($escaped) {
                if (($prefixCharacters[$prefixPosition] ?? null) === $character) {
                    if ($character === '%' || $character === '_') {
                        $literalWildcards[] = $character;
                    }
                    $prefixPosition++;
                    $escaped = false;
                    continue;
                }
                break;
            }
            if ($character === $escapeCharacter) {
                $escaped = true;
                continue;
            }
            if ($character === '%' || $character === '_') {
                break;
            }
            if (($prefixCharacters[$prefixPosition] ?? null) !== $character) {
                break;
            }
            $prefixPosition++;
        }

        return array_values(array_unique($literalWildcards));
    }

    /** @param array<int,string> $rtrimTexts @param list<int> $candidateRowids @return list<int> */
    private static function v194_literalPrefixFalsePositiveRowids(array $rtrimTexts, array $candidateRowids, string $prefix): array
    {
        $rowids = [];
        $prefixLength = strlen($prefix);
        foreach ($candidateRowids as $rowid) {
            $text = $rtrimTexts[$rowid] ?? null;
            if (!is_string($text)) {
                continue;
            }
            if (self::v194_asciiLower(substr($text, 0, $prefixLength)) !== self::v194_asciiLower($prefix)) {
                $rowids[] = $rowid;
            }
        }
        sort($rowids);

        return $rowids;
    }

    /** @return list<string> */
    private static function v194_characters(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if (is_array($characters)) {
            return array_values($characters);
        }

        return str_split($value);
    }

    private static function v194_asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyEscapedLiteralTailPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_!%!_cache',
        ?string $escape = '!',
        string $currentSource = 'main.app_settings@194',
        string $nextSource = 'main.app_settings@195',
        int $currentSchemaCookie = 194,
        int $nextSchemaCookie = 195,
    ): array {
        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyAsciiPrefixRangePlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escape,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $currentFalse = $base['currentRangeFalsePositiveRowids'];
        $nextFalse = $base['nextRangeFalsePositiveRowids'];
        $falseRetained = self::v195_retained($currentFalse, $nextFalse);
        $falseExited = self::v195_exited($currentFalse, $nextFalse);
        $falseEntered = self::v195_entered($currentFalse, $nextFalse);
        $promoted = array_values(array_intersect($falseExited, $base['nextMatchedRowids']));
        $demoted = array_values(array_intersect($base['matchedExitedRowids'], $nextFalse));
        $literalTailReasons = [];
        if (($base['rangeLowerInclusive'] ?? null) !== null && $currentFalse !== $nextFalse) {
            $literalTailReasons[] = 'range-residual-false-positive-rowset';
        }
        if ($promoted !== []) {
            $literalTailReasons[] = 'false-positive-promoted-to-match';
        }
        if ($demoted !== []) {
            $literalTailReasons[] = 'match-demoted-to-false-positive';
        }

        $reasons = array_values(array_unique(array_merge($base['invalidationReasons'], $literalTailReasons)));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nextoneNineFive',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* escaped literal tail */',
            'baseStatus' => $base['status'],
            'pattern' => $pattern,
            'escape' => $escape,
            'currentSource' => $base['currentSource'],
            'nextSource' => $base['nextSource'],
            'currentSchemaCookie' => $base['currentSchemaCookie'],
            'nextSchemaCookie' => $base['nextSchemaCookie'],
            'prefix' => $base['prefix'],
            'prefixCharacters' => $base['likePlan']['prefixCharacters'],
            'prefixIsAscii' => $base['prefixIsAscii'],
            'rangeLowerInclusive' => $base['rangeLowerInclusive'],
            'rangeUpperBound' => $base['rangeUpperBound'],
            'indexUsable' => $base['indexUsable'],
            'usesPrefixRangeCursor' => $base['usesPrefixRangeCursor'],
            'usesFullScanFallback' => $base['usesFullScanFallback'],
            'currentCandidateRowids' => $base['currentCandidateRowids'],
            'nextCandidateRowids' => $base['nextCandidateRowids'],
            'candidateRetainedRowids' => $base['currentRangeRetainedRowids'],
            'candidateExitedRowids' => $base['currentRangeExitedRowids'],
            'candidateEnteredRowids' => $base['nextRangeEnteredRowids'],
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'matchedRetainedRowids' => $base['matchedRetainedRowids'],
            'matchedExitedRowids' => $base['matchedExitedRowids'],
            'matchedEnteredRowids' => $base['matchedEnteredRowids'],
            'currentRangeFalsePositiveRowids' => $currentFalse,
            'nextRangeFalsePositiveRowids' => $nextFalse,
            'falsePositiveRetainedRowids' => $falseRetained,
            'falsePositiveExitedRowids' => $falseExited,
            'falsePositiveEnteredRowids' => $falseEntered,
            'falsePositivePromotedRowids' => $promoted,
            'matchedDemotedToFalsePositiveRowids' => $demoted,
            'currentRtrimTexts' => $base['currentRtrimTexts'],
            'nextRtrimTexts' => $base['nextRtrimTexts'],
            'currentNocaseKeys' => $base['currentNocaseKeys'],
            'nextNocaseKeys' => $base['nextNocaseKeys'],
            'currentMatchedTexts' => $base['currentMatchedTexts'],
            'nextMatchedTexts' => $base['nextMatchedTexts'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'currentErrors' => $base['currentErrors'],
            'nextErrors' => $base['nextErrors'],
            'changedRtrimRowids' => $base['changedRtrimRowids'],
            'changedNocaseKeyRowids' => $base['changedNocaseKeyRowids'],
            'changedBytesRowids' => $base['changedBytesRowids'],
            'literalTailInvalidationReasons' => $literalTailReasons,
            'baseInvalidationReasons' => $base['invalidationReasons'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'mustRecheckResidualForRangeCandidates' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-escaped-literal-prefix-range',
                'sqlite-rtrim-residual-match',
                'sqlite-current-source-nextoneNineFive',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, escaped LIKE prefix ranges, RTRIM residual matching, and current-source cursor invalidation',
            'non_overlap' => 'covers escaped literal LIKE tails that create prefix-range false positives over UTF-16 RTRIM NOCASE keys; avoids accepted prepared pattern rebind, escape replay, resume-token, Unicode GLOB, and malformed UTF-16 insert guard clusters',
        ];
    }

    /** @param list<int> $current @param list<int> $next @return list<int> */
    private static function v195_retained(array $current, array $next): array
    {
        return array_values(array_intersect($current, $next));
    }

    /** @param list<int> $current @param list<int> $next @return list<int> */
    private static function v195_exited(array $current, array $next): array
    {
        return array_values(array_diff($current, $next));
    }

    /** @param list<int> $current @param list<int> $next @return list<int> */
    private static function v195_entered(array $current, array $next): array
    {
        return array_values(array_diff($next, $current));
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array{key:string,rowid:int}|null $resumeToken
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyDuplicatePeerResumePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_cache',
        ?string $escape = '!',
        ?array $resumeToken = ['key' => 'plugin_cache', 'rowid' => 6],
        string $currentSource = 'main.app_settings@195',
        string $nextSource = 'main.app_settings@196',
        int $currentSchemaCookie = 195,
        int $nextSchemaCookie = 196,
    ): array {
        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyCandidateTokenPlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escape,
            $resumeToken,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $token = self::v196_normalizeToken($resumeToken);
        $currentPeers = self::v196_sameKeyPeers($base['currentNocaseKeys'], $base['currentCandidateRowids'], $token);
        $nextPeers = self::v196_sameKeyPeers($base['nextNocaseKeys'], $base['nextCandidateRowids'], $token);
        $currentPeersBefore = self::v196_sameKeyPeersBeforeOrAt($base['currentNocaseKeys'], $base['currentCandidateRowids'], $token);
        $nextPeersBefore = self::v196_sameKeyPeersBeforeOrAt($base['nextNocaseKeys'], $base['nextCandidateRowids'], $token);
        $currentPeerMatches = array_values(array_intersect($currentPeers, $base['currentMatchedRowids']));
        $nextPeerMatches = array_values(array_intersect($nextPeers, $base['nextMatchedRowids']));
        $currentPeerFalse = array_values(array_intersect($currentPeers, $base['currentRangeFalsePositiveRowids']));
        $nextPeerFalse = array_values(array_intersect($nextPeers, $base['nextRangeFalsePositiveRowids']));
        $nextPeersAfter = self::v196_sameKeyPeersAfter($base['nextNocaseKeys'], $base['nextCandidateRowids'], $token);

        $peerReasons = [];
        if ($currentPeersBefore !== $nextPeersBefore) {
            $peerReasons[] = 'duplicate-key-peers-before-token-changed';
        }
        if ($currentPeerMatches !== $nextPeerMatches) {
            $peerReasons[] = 'duplicate-key-matched-peers-changed';
        }
        if ($currentPeerFalse !== $nextPeerFalse) {
            $peerReasons[] = 'duplicate-key-false-positive-peers-changed';
        }
        if ($token !== null && !in_array($token['rowid'], $nextPeers, true)) {
            $peerReasons[] = 'duplicate-key-token-row-missing';
        }

        $unsafe = array_values(array_unique(array_merge($base['candidateTokenUnsafeReasons'], $peerReasons)));
        $safe = $unsafe === [];

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nextoneNineSix',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* duplicate comparison-key peers */',
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => 'NOCASE',
            'baseStatus' => $base['status'],
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'prefix' => $base['prefix'],
            'rangeLowerInclusive' => $base['rangeLowerInclusive'],
            'rangeUpperBound' => $base['rangeUpperBound'],
            'usesPrefixRangeCursor' => $base['usesPrefixRangeCursor'],
            'resumeToken' => $token,
            'currentCandidateRowids' => $base['currentCandidateRowids'],
            'nextCandidateRowids' => $base['nextCandidateRowids'],
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'currentRangeFalsePositiveRowids' => $base['currentRangeFalsePositiveRowids'],
            'nextRangeFalsePositiveRowids' => $base['nextRangeFalsePositiveRowids'],
            'currentDuplicatePeerRowids' => $currentPeers,
            'nextDuplicatePeerRowids' => $nextPeers,
            'currentDuplicatePeersBeforeOrAtTokenRowids' => $currentPeersBefore,
            'nextDuplicatePeersBeforeOrAtTokenRowids' => $nextPeersBefore,
            'currentDuplicatePeerMatchedRowids' => $currentPeerMatches,
            'nextDuplicatePeerMatchedRowids' => $nextPeerMatches,
            'currentDuplicatePeerFalsePositiveRowids' => $currentPeerFalse,
            'nextDuplicatePeerFalsePositiveRowids' => $nextPeerFalse,
            'nextDuplicatePeersAfterTokenRowids' => $nextPeersAfter,
            'duplicatePeerUnsafeReasons' => $peerReasons,
            'candidateTokenUnsafeReasons' => $unsafe,
            'candidateTokenResumeSafe' => $safe,
            'mustReprepareBeforeCandidateTokenResume' => !$safe,
            'replayPlanMode' => $safe ? 'continue-after-duplicate-peer-token' : 'reprepare-from-range-start',
            'replayPlanRowids' => $safe ? array_values(array_merge($nextPeersAfter, self::v196_strictlyAfterKey($base['nextNocaseKeys'], $base['nextCandidateRowids'], $token))) : $base['nextCandidateRowids'],
            'currentNocaseKeys' => $base['currentNocaseKeys'],
            'nextNocaseKeys' => $base['nextNocaseKeys'],
            'currentRtrimTexts' => $base['currentRtrimTexts'],
            'nextRtrimTexts' => $base['nextRtrimTexts'],
            'currentMatchedTexts' => $base['currentMatchedTexts'],
            'nextMatchedTexts' => $base['nextMatchedTexts'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'duplicatePeersOrderedByRowidWithinComparisonKey' => true,
            'residualRecheckRequiredForDuplicatePeers' => $currentPeerFalse !== [] || $nextPeerFalse !== [],
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-duplicate-peer-key',
                'sqlite-like-residual-recheck',
                'sqlite-current-source-nextoneNineSix',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE range planning, RTRIM expression keys, and candidate-token residual rechecks',
            'non_overlap' => 'nextOneNineSix adds duplicate comparison-key peer safety for yielded UTF-16 RTRIM/NOCASE LIKE scans; it avoids accepted nextOneNineTwo false-positive token replay, nextOneNineOne prepared pattern rebind, nextOneEightThree prefix reuse, malformed UTF-16 guards, Unicode GLOB ranges, and storage/planner clusters',
        ];
    }

    /** @param array{key:string,rowid:int}|null $token @return array{key:string,rowid:int,normalizationReasons:list<string>}|null */
    private static function v196_normalizeToken(?array $token): ?array
    {
        if ($token === null) {
            return null;
        }
        if (!isset($token['key']) || !is_string($token['key'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneNineSix resume token requires string key');
        }
        if (!isset($token['rowid']) || !is_int($token['rowid'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextOneNineSix resume token requires integer rowid');
        }

        $key = self::v196_asciiLower(rtrim($token['key'], ' '));

        return [
            'key' => $key,
            'rowid' => $token['rowid'],
            'normalizationReasons' => $key === $token['key'] ? [] : ['token-key-not-canonical'],
        ];
    }

    /**
     * @param array<int,string> $keys
     * @param list<int> $rowids
     * @param array{key:string,rowid:int,normalizationReasons:list<string>}|null $token
     * @return list<int>
     */
    private static function v196_sameKeyPeers(array $keys, array $rowids, ?array $token): array
    {
        if ($token === null) {
            return [];
        }

        return array_values(array_filter($rowids, static fn (int $rowid): bool => ($keys[$rowid] ?? null) === $token['key']));
    }

    /**
     * @param array<int,string> $keys
     * @param list<int> $rowids
     * @param array{key:string,rowid:int,normalizationReasons:list<string>}|null $token
     * @return list<int>
     */
    private static function v196_sameKeyPeersBeforeOrAt(array $keys, array $rowids, ?array $token): array
    {
        if ($token === null) {
            return [];
        }

        return array_values(array_filter(
            $rowids,
            static fn (int $rowid): bool => ($keys[$rowid] ?? null) === $token['key'] && $rowid <= $token['rowid'],
        ));
    }

    /**
     * @param array<int,string> $keys
     * @param list<int> $rowids
     * @param array{key:string,rowid:int,normalizationReasons:list<string>}|null $token
     * @return list<int>
     */
    private static function v196_sameKeyPeersAfter(array $keys, array $rowids, ?array $token): array
    {
        if ($token === null) {
            return [];
        }

        return array_values(array_filter(
            $rowids,
            static fn (int $rowid): bool => ($keys[$rowid] ?? null) === $token['key'] && $rowid > $token['rowid'],
        ));
    }

    /**
     * @param array<int,string> $keys
     * @param list<int> $rowids
     * @param array{key:string,rowid:int,normalizationReasons:list<string>}|null $token
     * @return list<int>
     */
    private static function v196_strictlyAfterKey(array $keys, array $rowids, ?array $token): array
    {
        if ($token === null) {
            return [];
        }

        return array_values(array_filter(
            $rowids,
            static fn (int $rowid): bool => isset($keys[$rowid]) && strcmp($keys[$rowid], $token['key']) > 0,
        ));
    }

    private static function v196_asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyEscapeRebindPlan(
        array $currentRows,
        array $nextRows,
        string $currentPattern = 'plugin!_%',
        ?string $currentEscape = '!',
        string $nextPattern = 'plugin!_%',
        ?string $nextEscape = '~',
        string $currentSource = 'main.app_settings@199',
        string $nextSource = 'main.app_settings@200',
        int $currentSchemaCookie = 199,
        int $nextSchemaCookie = 200,
    ): array {
        $current = self::v200_scan($currentRows, $currentPattern, $currentEscape);
        $next = self::v200_scan($nextRows, $nextPattern, $nextEscape);
        $nextWithCurrentEscape = self::v200_scan($nextRows, $currentPattern, $currentEscape);
        $currentWithNextEscape = self::v200_scan($currentRows, $nextPattern, $nextEscape);

        $currentMatched = self::v200_rowids($current['matched']);
        $nextMatched = self::v200_rowids($next['matched']);
        $nextMatchedWithCurrentEscape = self::v200_rowids($nextWithCurrentEscape['matched']);
        $currentMatchedWithNextEscape = self::v200_rowids($currentWithNextEscape['matched']);
        $escapeResidualFlip = self::v200_symmetricDifference($nextMatchedWithCurrentEscape, $nextMatched);
        $currentEscapeResidualFlip = self::v200_symmetricDifference($currentMatched, $currentMatchedWithNextEscape);
        $matchedExited = array_values(array_diff($currentMatched, $nextMatched));
        $matchedEntered = array_values(array_diff($nextMatched, $currentMatched));
        sort($matchedExited);
        sort($matchedEntered);

        $currentLike = $current['likePlan'];
        $nextLike = $next['likePlan'];
        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($currentPattern !== $nextPattern) {
            $reasons[] = 'pattern';
        }
        if ($currentEscape !== $nextEscape) {
            $reasons[] = 'escape-rebound';
        }
        if (($currentLike['prefix'] ?? null) !== ($nextLike['prefix'] ?? null)) {
            $reasons[] = 'like-prefix';
        }
        if (($currentLike['range'] ?? null) !== ($nextLike['range'] ?? null)) {
            $reasons[] = 'like-range';
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        if ($escapeResidualFlip !== [] || $currentEscapeResidualFlip !== []) {
            $reasons[] = 'escape-residual-rowset';
        }
        if ($currentMatched !== $nextMatched) {
            $reasons[] = 'matched-rowset';
        }
        $reasons = array_values(array_unique($reasons));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nexttwoZeroZero',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* escape rebind */',
            'currentPattern' => $currentPattern,
            'nextPattern' => $nextPattern,
            'currentEscape' => $currentEscape,
            'nextEscape' => $nextEscape,
            'collation' => 'NOCASE',
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentPrefix' => $currentLike['prefix'],
            'nextPrefix' => $nextLike['prefix'],
            'currentRangeLowerInclusive' => $currentLike['range']['lowerInclusive'] ?? null,
            'currentRangeUpperBound' => $currentLike['range']['upperBound'] ?? null,
            'nextRangeLowerInclusive' => $nextLike['range']['lowerInclusive'] ?? null,
            'nextRangeUpperBound' => $nextLike['range']['upperBound'] ?? null,
            'currentIndexUsable' => $currentLike['indexUsable'],
            'nextIndexUsable' => $nextLike['indexUsable'],
            'currentCandidateRowids' => self::v200_rowids($current['candidates']),
            'nextCandidateRowids' => self::v200_rowids($next['candidates']),
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'nextMatchedWithCurrentEscapeRowids' => $nextMatchedWithCurrentEscape,
            'currentMatchedWithNextEscapeRowids' => $currentMatchedWithNextEscape,
            'escapeResidualFlipRowids' => $escapeResidualFlip,
            'currentEscapeResidualFlipRowids' => $currentEscapeResidualFlip,
            'matchedExitedRowids' => $matchedExited,
            'matchedEnteredRowids' => $matchedEntered,
            'currentFalsePositiveRowids' => self::v200_rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::v200_rowids($next['falsePositive']),
            'currentExcludedDecodedRowids' => array_values(array_diff(self::v200_rowids($current['decoded']), self::v200_rowids($current['candidates']))),
            'nextExcludedDecodedRowids' => array_values(array_diff(self::v200_rowids($next['decoded']), self::v200_rowids($next['candidates']))),
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'currentRtrimTexts' => self::v200_map($current['decoded'], 'rtrimText'),
            'nextRtrimTexts' => self::v200_map($next['decoded'], 'rtrimText'),
            'currentNocaseKeys' => self::v200_map($current['decoded'], 'nocaseKey'),
            'nextNocaseKeys' => self::v200_map($next['decoded'], 'nocaseKey'),
            'currentMatchedTexts' => self::v200_selectMap(self::v200_map($current['decoded'], 'rtrimText'), $currentMatched),
            'nextMatchedTexts' => self::v200_selectMap(self::v200_map($next['decoded'], 'rtrimText'), $nextMatched),
            'escapeChanged' => $currentEscape !== $nextEscape,
            'prefixChangedByEscape' => ($currentLike['prefix'] ?? null) !== ($nextLike['prefix'] ?? null),
            'rangeChangedByEscape' => ($currentLike['range'] ?? null) !== ($nextLike['range'] ?? null),
            'residualChangedByEscape' => $escapeResidualFlip !== [] || $currentEscapeResidualFlip !== [],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'mustReprepareForEscapeRebind' => $currentEscape !== $nextEscape,
            'staleRangeCursorRisk' => $currentEscape !== $nextEscape && (($currentLike['range'] ?? null) !== ($nextLike['range'] ?? null) || $escapeResidualFlip !== []),
            'invalidationReasons' => $reasons,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'escapeRebindCheckedBeforeRangeReuse' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-escape-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-current-source-nexttwoZeroZero',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, LIKE ESCAPE prefix planning, RTRIM keys, NOCASE residual matching, and current-source cursor invalidation diagnostics',
            'non_overlap' => 'nextTwoZeroZero adds ESCAPE rebind fencing for UTF-16 RTRIM/NOCASE LIKE current-source cursors; avoids accepted escaped literal wildcard nextOneNineFour, deleted-token/rowid replay, Unicode GLOB ranges, malformed UTF-16 insert guards, and storage/planner clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{likePlan:array<string,mixed>,decoded:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function v200_scan(array $rows, string $pattern, ?string $escape): array
    {
        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $decoded = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            self::v200_assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
                $rtrim = rtrim($text, ' ');
                $decoded[] = [
                    'rowid' => $row['option_id'],
                    'text' => $text,
                    'rtrimText' => $rtrim,
                    'nocaseKey' => self::v200_asciiLower($rtrim),
                    'bytesHex' => bin2hex($row['option_name_bytes']),
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['option_id'];
                $errors[$row['option_id']] = $exception->getMessage();
            }
        }

        usort($decoded, self::v200_sortRows(...));
        sort($malformed);
        ksort($errors);

        $candidates = [];
        $matched = [];
        $falsePositive = [];
        foreach ($decoded as $entry) {
            if (!self::v200_inRange($entry['nocaseKey'], $like['range'])) {
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
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row */
    private static function v200_assertRow(array $row): void
    {
        if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoZeroZero rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoZeroZero rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoZeroZero rows require integer text_encoding');
        }
    }

    /** @param ?array{lowerInclusive:string,upperBound:?string} $range */
    private static function v200_inRange(string $key, ?array $range): bool
    {
        if ($range === null || strcmp($key, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0;
    }

    /** @param array{nocaseKey:string,rowid:int} $left @param array{nocaseKey:string,rowid:int} $right */
    private static function v200_sortRows(array $left, array $right): int
    {
        $comparison = strcmp($left['nocaseKey'], $right['nocaseKey']);

        return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
    }

    /** @param list<array{rowid:int}> $rows @return list<int> */
    private static function v200_rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function v200_symmetricDifference(array $left, array $right): array
    {
        $diff = array_values(array_unique(array_merge(array_diff($left, $right), array_diff($right, $left))));
        sort($diff);

        return $diff;
    }

    /** @param list<array<string,mixed>> $rows @return array<int,mixed> */
    private static function v200_map(array $rows, string $key): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[$row['rowid']] = $row[$key];
        }

        return $mapped;
    }

    /** @param array<int,string> $values @param list<int> $rowids @return array<int,string> */
    private static function v200_selectMap(array $values, array $rowids): array
    {
        $selected = [];
        foreach ($rowids as $rowid) {
            if (array_key_exists($rowid, $values)) {
                $selected[$rowid] = $values[$rowid];
            }
        }

        return $selected;
    }

    private static function v200_asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array<string,mixed> $currentPatternRow
     * @param array<string,mixed> $nextPatternRow
     * @return array<string,mixed>
     */
    public static function keyValueRowKeySourcePatternPlan(
        array $currentRows,
        array $nextRows,
        array $currentPatternRow,
        array $nextPatternRow,
        ?string $escape = '!',
        string $currentSource = 'main.app_settings@201',
        string $nextSource = 'main.app_settings@202',
        int $currentSchemaCookie = 201,
        int $nextSchemaCookie = 202,
    ): array {
        $currentPattern = self::v202_decodePatternRow($currentPatternRow, 'current');
        $nextPattern = self::v202_decodePatternRow($nextPatternRow, 'next');

        $current = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyAsciiPrefixRangePlan(
            $currentRows,
            $currentRows,
            $currentPattern,
            $escape,
            $currentSource,
            $currentSource,
            $currentSchemaCookie,
            $currentSchemaCookie,
        );
        $next = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyAsciiPrefixRangePlan(
            $nextRows,
            $nextRows,
            $nextPattern,
            $escape,
            $nextSource,
            $nextSource,
            $nextSchemaCookie,
            $nextSchemaCookie,
        );

        $sourceReasons = [];
        if ($currentSource !== $nextSource || $currentSchemaCookie !== $nextSchemaCookie) {
            $sourceReasons[] = 'source-or-schema-changed';
        }
        if (($currentPatternRow['setting_id'] ?? null) !== ($nextPatternRow['setting_id'] ?? null)) {
            $sourceReasons[] = 'rhs-pattern-source-setting-id-changed';
        }
        if (($currentPatternRow['text_encoding'] ?? null) !== ($nextPatternRow['text_encoding'] ?? null)
            || ($currentPatternRow['key_value_bytes'] ?? null) !== ($nextPatternRow['key_value_bytes'] ?? null)) {
            $sourceReasons[] = 'rhs-pattern-source-bytes-changed';
        }
        if ($currentPattern !== $nextPattern) {
            $sourceReasons[] = 'decoded-rhs-pattern-changed';
        }
        foreach ([
            'range-bound' => self::v202_rangeChanged($current, $next),
            'candidate-rowset' => $current['currentCandidateRowids'] !== $next['currentCandidateRowids'],
            'matched-rowset' => $current['currentMatchedRowids'] !== $next['currentMatchedRowids'],
            'range-false-positive-rowset' => $current['currentRangeFalsePositiveRowids'] !== $next['currentRangeFalsePositiveRowids'],
        ] as $reason => $changed) {
            if ($changed) {
                $sourceReasons[] = $reason;
            }
        }
        if ($current['currentMalformedRowids'] !== [] || $next['currentMalformedRowids'] !== []) {
            $sourceReasons[] = 'malformed-text';
        }
        $sourceReasons = array_values(array_unique($sourceReasons));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nexttwoZeroTwo',
            'operator' => 'LIKE',
            'expression' => 'rtrim(key_name) COLLATE NOCASE LIKE (SELECT key_value FROM app_settings WHERE key_name = ?)',
            'escape' => $escape,
            'collation' => 'NOCASE',
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentPatternSourceSettingId' => (int) $currentPatternRow['setting_id'],
            'nextPatternSourceSettingId' => (int) $nextPatternRow['setting_id'],
            'currentPatternEncoding' => self::v202_encodingName((int) $currentPatternRow['text_encoding']),
            'nextPatternEncoding' => self::v202_encodingName((int) $nextPatternRow['text_encoding']),
            'currentPatternBytesHex' => bin2hex((string) $currentPatternRow['key_value_bytes']),
            'nextPatternBytesHex' => bin2hex((string) $nextPatternRow['key_value_bytes']),
            'currentPattern' => $currentPattern,
            'nextPattern' => $nextPattern,
            'sameDecodedPattern' => $currentPattern === $nextPattern,
            'sameSourcePatternBytes' => ($currentPatternRow['key_value_bytes'] ?? null) === ($nextPatternRow['key_value_bytes'] ?? null),
            'currentPrefix' => $current['prefix'],
            'nextPrefix' => $next['prefix'],
            'currentRangeLowerInclusive' => $current['rangeLowerInclusive'],
            'nextRangeLowerInclusive' => $next['rangeLowerInclusive'],
            'currentRangeUpperBound' => $current['rangeUpperBound'],
            'nextRangeUpperBound' => $next['rangeUpperBound'],
            'currentUsesPrefixRangeCursor' => $current['usesPrefixRangeCursor'],
            'nextUsesPrefixRangeCursor' => $next['usesPrefixRangeCursor'],
            'currentCandidateRowids' => $current['currentCandidateRowids'],
            'nextCandidateRowids' => $next['currentCandidateRowids'],
            'candidateRetainedRowids' => self::v202_retained($current['currentCandidateRowids'], $next['currentCandidateRowids']),
            'candidateExitedRowids' => self::v202_exited($current['currentCandidateRowids'], $next['currentCandidateRowids']),
            'candidateEnteredRowids' => self::v202_entered($current['currentCandidateRowids'], $next['currentCandidateRowids']),
            'currentMatchedRowids' => $current['currentMatchedRowids'],
            'nextMatchedRowids' => $next['currentMatchedRowids'],
            'matchedRetainedRowids' => self::v202_retained($current['currentMatchedRowids'], $next['currentMatchedRowids']),
            'matchedExitedRowids' => self::v202_exited($current['currentMatchedRowids'], $next['currentMatchedRowids']),
            'matchedEnteredRowids' => self::v202_entered($current['currentMatchedRowids'], $next['currentMatchedRowids']),
            'currentRangeFalsePositiveRowids' => $current['currentRangeFalsePositiveRowids'],
            'nextRangeFalsePositiveRowids' => $next['currentRangeFalsePositiveRowids'],
            'currentRtrimTexts' => $current['currentRtrimTexts'],
            'nextRtrimTexts' => $next['currentRtrimTexts'],
            'currentNocaseKeys' => $current['currentNocaseKeys'],
            'nextNocaseKeys' => $next['currentNocaseKeys'],
            'currentMatchedTexts' => $current['currentMatchedTexts'],
            'nextMatchedTexts' => $next['currentMatchedTexts'],
            'currentMalformedRowids' => $current['currentMalformedRowids'],
            'nextMalformedRowids' => $next['currentMalformedRowids'],
            'currentErrors' => $current['currentErrors'],
            'nextErrors' => $next['currentErrors'],
            'rhsPatternInvalidationReasons' => $sourceReasons,
            'cursorInvalidated' => $sourceReasons !== [],
            'cursorReusable' => $sourceReasons === [],
            'mustReprepareForSourcePatternChange' => $currentPattern !== $nextPattern || $sourceReasons !== [],
            'canReuseResidualForStableSourcePattern' => $sourceReasons === [],
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'rhsPatternComesFromCurrentSourceRow' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-source-row-like-pattern',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-current-source-nexttwoZeroTwo',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, source-row pattern extraction, ASCII NOCASE LIKE prefix ranges, RTRIM expression keys, and current-source diagnostics',
            'non_overlap' => 'nextTwoZeroTwo covers UTF-16 LIKE patterns read from current/next source rows; it avoids accepted prepared-pattern byte rebind nextOneNineOne, duplicate peer resume nextOneNineSix, escaped literal tail nextOneNineFive, Unicode GLOB ranges, and malformed insert guards',
        ];
    }

    /** @param array<string,mixed> $row */
    private static function v202_decodePatternRow(array $row, string $label): string
    {
        foreach (['setting_id', 'key_value_bytes', 'text_encoding'] as $key) {
            if (!array_key_exists($key, $row)) {
                throw new \InvalidArgumentException("SQLite UTF-16 NOCASE LIKE RTRIM nextTwoZeroTwo {$label} pattern row missing {$key}");
            }
        }
        if (!is_int($row['setting_id'])) {
            throw new \InvalidArgumentException("SQLite UTF-16 NOCASE LIKE RTRIM nextTwoZeroTwo {$label} pattern row setting_id must be integer");
        }
        if (!is_string($row['key_value_bytes'])) {
            throw new \InvalidArgumentException("SQLite UTF-16 NOCASE LIKE RTRIM nextTwoZeroTwo {$label} pattern bytes must be string");
        }
        if (!is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException("SQLite UTF-16 NOCASE LIKE RTRIM nextTwoZeroTwo {$label} pattern encoding must be integer");
        }

        try {
            return SQLiteEncodingCollationSourceCursor::decodeText($row['key_value_bytes'], $row['text_encoding']);
        } catch (\InvalidArgumentException $exception) {
            throw new \InvalidArgumentException("SQLite UTF-16 NOCASE LIKE RTRIM nextTwoZeroTwo {$label} pattern row is malformed: " . $exception->getMessage());
        }
    }

    /** @param array<string,mixed> $current @param array<string,mixed> $next */
    private static function v202_rangeChanged(array $current, array $next): bool
    {
        return ($current['rangeLowerInclusive'] ?? null) !== ($next['rangeLowerInclusive'] ?? null)
            || ($current['rangeUpperBound'] ?? null) !== ($next['rangeUpperBound'] ?? null);
    }

    /** @param list<int> $current @param list<int> $next @return list<int> */
    private static function v202_retained(array $current, array $next): array
    {
        return array_values(array_intersect($current, $next));
    }

    /** @param list<int> $current @param list<int> $next @return list<int> */
    private static function v202_exited(array $current, array $next): array
    {
        return array_values(array_diff($current, $next));
    }

    /** @param list<int> $current @param list<int> $next @return list<int> */
    private static function v202_entered(array $current, array $next): array
    {
        return array_values(array_diff($next, $current));
    }

    private static function v202_encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoZeroTwo encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyFullScanPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = '%cache',
        ?string $escape = null,
        string $currentSource = 'main.app_settings@202',
        string $nextSource = 'main.app_settings@203',
        int $currentSchemaCookie = 202,
        int $nextSchemaCookie = 203,
    ): array {
        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        if ($like['rejectedReason'] !== 'no_fixed_prefix') {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoZeroThree expects a no-fixed-prefix LIKE pattern');
        }

        $current = self::v203_scan($currentRows, $pattern, $escape);
        $next = self::v203_scan($nextRows, $pattern, $escape);
        $currentMatched = self::v203_rowids($current['matched']);
        $nextMatched = self::v203_rowids($next['matched']);
        $changes = self::v203_changes($current['decoded'], $next['decoded']);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        $reasons[] = 'no-fixed-prefix-full-scan';
        foreach ([
            'decoded-text' => $changes['textChangedRowids'],
            'rtrim-expression' => $changes['rtrimChangedRowids'],
            'nocase-key' => $changes['nocaseKeyChangedRowids'],
            'encoded-bytes' => $changes['bytesChangedRowids'],
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        if ($currentMatched !== $nextMatched) {
            $reasons[] = 'matched-rowset';
        }

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nexttwoZeroThree',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? /* no fixed prefix */',
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => 'NOCASE',
            'caseSensitiveLike' => false,
            'likePlan' => $like,
            'prefix' => $like['prefix'],
            'prefixIsAscii' => $like['prefixIsAscii'],
            'rangeLowerInclusive' => null,
            'rangeUpperBound' => null,
            'indexUsable' => false,
            'usesPrefixRangeCursor' => false,
            'usesFullScanFallback' => true,
            'rejectedReason' => $like['rejectedReason'],
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentDecodedRowids' => self::v203_rowids($current['decoded']),
            'nextDecodedRowids' => self::v203_rowids($next['decoded']),
            'currentCandidateRowids' => self::v203_rowids($current['decoded']),
            'nextCandidateRowids' => self::v203_rowids($next['decoded']),
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'currentFullScanRejectedRowids' => self::v203_rowids($current['rejected']),
            'nextFullScanRejectedRowids' => self::v203_rowids($next['rejected']),
            'matchedRetainedRowids' => array_values(array_intersect($currentMatched, $nextMatched)),
            'matchedExitedRowids' => array_values(array_diff($currentMatched, $nextMatched)),
            'matchedEnteredRowids' => array_values(array_diff($nextMatched, $currentMatched)),
            'currentTexts' => self::v203_map($current['decoded'], 'text'),
            'nextTexts' => self::v203_map($next['decoded'], 'text'),
            'currentRtrimTexts' => self::v203_map($current['decoded'], 'rtrimText'),
            'nextRtrimTexts' => self::v203_map($next['decoded'], 'rtrimText'),
            'currentNocaseKeys' => self::v203_map($current['decoded'], 'nocaseKey'),
            'nextNocaseKeys' => self::v203_map($next['decoded'], 'nocaseKey'),
            'currentMatchedTexts' => self::v203_map($current['matched'], 'rtrimText'),
            'nextMatchedTexts' => self::v203_map($next['matched'], 'rtrimText'),
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'changedTextRowids' => $changes['textChangedRowids'],
            'changedRtrimRowids' => $changes['rtrimChangedRowids'],
            'changedNocaseKeyRowids' => $changes['nocaseKeyChangedRowids'],
            'changedBytesRowids' => $changes['bytesChangedRowids'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => false,
            'invalidationReasons' => array_values(array_unique($reasons)),
            'likeResidualAppliesAfterRtrim' => true,
            'noFixedPrefixRequiresFullScan' => true,
            'malformedRowsDoNotAbortFullScan' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-no-fixed-prefix-full-scan',
                'sqlite-rtrim-residual-match',
                'sqlite-current-source-nexttwoZeroThree',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE residual matching, RTRIM keys, and current-source diagnostics',
            'non_overlap' => 'nextTwoZeroThree covers no-fixed-prefix UTF-16 RTRIM/NOCASE LIKE full-scan current-source invalidation; avoids accepted escaped-prefix/tail, resume-token, duplicate-peer, Unicode GLOB, and malformed UTF-16 insert guard clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{decoded:list<array<string,mixed>>,matched:list<array<string,mixed>>,rejected:list<array<string,mixed>>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function v203_scan(array $rows, string $pattern, ?string $escape): array
    {
        $decoded = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            self::v203_assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
                $rtrim = rtrim($text, ' ');
                $decoded[] = [
                    'rowid' => $row['option_id'],
                    'text' => $text,
                    'rtrimText' => $rtrim,
                    'nocaseKey' => self::v203_asciiLower($rtrim),
                    'bytesHex' => bin2hex($row['option_name_bytes']),
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['option_id'];
                $errors[$row['option_id']] = $exception->getMessage();
            }
        }

        usort($decoded, self::v203_sortRows(...));
        sort($malformed);
        ksort($errors);

        $matched = [];
        $rejected = [];
        foreach ($decoded as $entry) {
            if (SQLiteDatabase::likeMatches($entry['rtrimText'], $pattern, $escape, false)) {
                $matched[] = $entry;
            } else {
                $rejected[] = $entry;
            }
        }

        return [
            'decoded' => $decoded,
            'matched' => $matched,
            'rejected' => $rejected,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row */
    private static function v203_assertRow(array $row): void
    {
        if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoZeroThree rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoZeroThree rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoZeroThree rows require integer text_encoding');
        }
    }

    /** @param array{nocaseKey:string,rowid:int} $left @param array{nocaseKey:string,rowid:int} $right */
    private static function v203_sortRows(array $left, array $right): int
    {
        $comparison = strcmp($left['nocaseKey'], $right['nocaseKey']);

        return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
    }

    /** @param list<array{rowid:int}> $rows @return list<int> */
    private static function v203_rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $rows @return array<int,mixed> */
    private static function v203_map(array $rows, string $key): array
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
     * @return array<string,list<int>>
     */
    private static function v203_changes(array $current, array $next): array
    {
        $currentByRowid = self::v203_byRowid($current);
        $nextByRowid = self::v203_byRowid($next);

        return [
            'textChangedRowids' => self::v203_changed($currentByRowid, $nextByRowid, 'text'),
            'rtrimChangedRowids' => self::v203_changed($currentByRowid, $nextByRowid, 'rtrimText'),
            'nocaseKeyChangedRowids' => self::v203_changed($currentByRowid, $nextByRowid, 'nocaseKey'),
            'bytesChangedRowids' => self::v203_changed($currentByRowid, $nextByRowid, 'bytesHex'),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private static function v203_byRowid(array $rows): array
    {
        $byRowid = [];
        foreach ($rows as $row) {
            $byRowid[$row['rowid']] = $row;
        }

        return $byRowid;
    }

    /** @param array<int,array<string,mixed>> $current @param array<int,array<string,mixed>> $next @return list<int> */
    private static function v203_changed(array $current, array $next, string $key): array
    {
        $rowids = array_values(array_intersect(array_keys($current), array_keys($next)));
        sort($rowids);
        $changed = [];
        foreach ($rowids as $rowid) {
            if (($current[$rowid][$key] ?? null) !== ($next[$rowid][$key] ?? null)) {
                $changed[] = (int) $rowid;
            }
        }

        return $changed;
    }

    private static function v203_asciiLower(string $value): string
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

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyNonAsciiFullScanPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plüg!_%',
        ?string $escape = '!',
        string $currentSource = 'main.app_settings@204',
        string $nextSource = 'main.app_settings@205',
        int $currentSchemaCookie = 204,
        int $nextSchemaCookie = 205,
    ): array {
        $current = self::v205_scan($currentRows, $pattern, $escape);
        $next = self::v205_scan($nextRows, $pattern, $escape);
        $currentMatched = self::v205_rowids($current['matched']);
        $nextMatched = self::v205_rowids($next['matched']);
        $currentFullScan = !$current['likePlan']['indexUsable'];
        $nextFullScan = !$next['likePlan']['indexUsable'];
        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($currentFullScan || $nextFullScan) {
            $reasons[] = 'non-ascii-nocase-prefix-full-scan';
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        if ($currentMatched !== $nextMatched) {
            $reasons[] = 'matched-rowset';
        }

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nexttwoZeroFive',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* non-ASCII prefix fallback */',
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => 'NOCASE',
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'prefix' => $current['likePlan']['prefix'],
            'prefixCharacters' => $current['likePlan']['prefixCharacters'],
            'prefixIsAscii' => $current['likePlan']['prefixIsAscii'],
            'rangeRejectedReason' => $current['likePlan']['rejectedReason'],
            'currentIndexUsable' => $current['likePlan']['indexUsable'],
            'nextIndexUsable' => $next['likePlan']['indexUsable'],
            'currentScanMode' => $currentFullScan ? 'full-residual-scan' : 'nocase-rtrim-range',
            'nextScanMode' => $nextFullScan ? 'full-residual-scan' : 'nocase-rtrim-range',
            'currentCandidateRowids' => self::v205_rowids($current['candidates']),
            'nextCandidateRowids' => self::v205_rowids($next['candidates']),
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'matchedRetainedRowids' => array_values(array_intersect($currentMatched, $nextMatched)),
            'matchedExitedRowids' => array_values(array_diff($currentMatched, $nextMatched)),
            'matchedEnteredRowids' => array_values(array_diff($nextMatched, $currentMatched)),
            'currentFalsePositiveRowids' => self::v205_rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::v205_rowids($next['falsePositive']),
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'currentRtrimTexts' => self::v205_map($current['decoded'], 'rtrimText'),
            'nextRtrimTexts' => self::v205_map($next['decoded'], 'rtrimText'),
            'currentNocaseKeys' => self::v205_map($current['decoded'], 'nocaseKey'),
            'nextNocaseKeys' => self::v205_map($next['decoded'], 'nocaseKey'),
            'currentMatchedTexts' => self::v205_selectMap(self::v205_map($current['decoded'], 'rtrimText'), $currentMatched),
            'nextMatchedTexts' => self::v205_selectMap(self::v205_map($next['decoded'], 'rtrimText'), $nextMatched),
            'rangeCursorSuppressedForNonAsciiPrefix' => $currentFullScan && $nextFullScan,
            'residualScanRequired' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => array_values(array_unique($reasons)),
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-nocase-non-ascii-prefix',
                'sqlite-rtrim-expression-key',
                'sqlite-current-source-nexttwoZeroFive',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, LIKE prefix planning, RTRIM keys, ASCII NOCASE residual matching, and current-source diagnostics',
            'non_overlap' => 'nextTwoZeroFive covers non-ASCII NOCASE LIKE prefix fallback to full residual scans for UTF-16 RTRIM current-source cursors; avoids accepted ESCAPE rebind, escaped literal tails, Unicode GLOB ranges, malformed UTF-16 insert guards, and storage/planner clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{likePlan:array<string,mixed>,decoded:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function v205_scan(array $rows, string $pattern, ?string $escape): array
    {
        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $decoded = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            self::v205_assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
                $rtrim = rtrim($text, ' ');
                $decoded[] = [
                    'rowid' => $row['option_id'],
                    'text' => $text,
                    'rtrimText' => $rtrim,
                    'nocaseKey' => self::v205_asciiLower($rtrim),
                    'bytesHex' => bin2hex($row['option_name_bytes']),
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['option_id'];
                $errors[$row['option_id']] = $exception->getMessage();
            }
        }

        usort($decoded, self::v205_sortRows(...));
        sort($malformed);
        ksort($errors);

        $candidates = [];
        $matched = [];
        $falsePositive = [];
        foreach ($decoded as $entry) {
            if ($like['indexUsable'] && !self::v205_inRange($entry['nocaseKey'], $like['range'])) {
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
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row */
    private static function v205_assertRow(array $row): void
    {
        if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoZeroFive rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoZeroFive rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoZeroFive rows require integer text_encoding');
        }
    }

    /** @param ?array{lowerInclusive:string,upperBound:?string} $range */
    private static function v205_inRange(string $key, ?array $range): bool
    {
        if ($range === null || strcmp($key, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0;
    }

    /** @param array{nocaseKey:string,rowid:int} $left @param array{nocaseKey:string,rowid:int} $right */
    private static function v205_sortRows(array $left, array $right): int
    {
        $comparison = strcmp($left['nocaseKey'], $right['nocaseKey']);

        return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
    }

    /** @param list<array{rowid:int}> $rows @return list<int> */
    private static function v205_rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $rows @return array<int,mixed> */
    private static function v205_map(array $rows, string $key): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[$row['rowid']] = $row[$key];
        }

        return $mapped;
    }

    /** @param array<int,string> $values @param list<int> $rowids @return array<int,string> */
    private static function v205_selectMap(array $values, array $rowids): array
    {
        $selected = [];
        foreach ($rowids as $rowid) {
            if (array_key_exists($rowid, $values)) {
                $selected[$rowid] = $values[$rowid];
            }
        }

        return $selected;
    }

    private static function v205_asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyPreparedBomPatternPlan(
        array $currentRows,
        array $nextRows,
        string $currentPatternBytes,
        int|string $currentPatternEncoding,
        string $nextPatternBytes,
        int|string $nextPatternEncoding,
        ?string $escape = '!',
        string $currentSource = 'main.app_settings@205',
        string $nextSource = 'main.app_settings@206',
        int $currentSchemaCookie = 205,
        int $nextSchemaCookie = 206,
    ): array {
        $currentDecoded = self::v206_decodePreparedText($currentPatternBytes, $currentPatternEncoding, 'current pattern');
        $nextDecoded = self::v206_decodePreparedText($nextPatternBytes, $nextPatternEncoding, 'next pattern');
        $currentPattern = self::v206_stripLeadingBom($currentDecoded);
        $nextPattern = self::v206_stripLeadingBom($nextDecoded);
        $currentHadBom = $currentDecoded !== $currentPattern;
        $nextHadBom = $nextDecoded !== $nextPattern;

        $current = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyEscapeRebindPlan(
            $currentRows,
            $currentRows,
            $currentPattern,
            $escape,
            $currentPattern,
            $escape,
            $currentSource,
            $currentSource,
            $currentSchemaCookie,
            $currentSchemaCookie,
        );
        $next = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyEscapeRebindPlan(
            $nextRows,
            $nextRows,
            $nextPattern,
            $escape,
            $nextPattern,
            $escape,
            $nextSource,
            $nextSource,
            $nextSchemaCookie,
            $nextSchemaCookie,
        );
        $nextWithoutBomStrip = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyEscapeRebindPlan(
            $nextRows,
            $nextRows,
            $nextDecoded,
            $escape,
            $nextDecoded,
            $escape,
            $nextSource . '#raw-bom',
            $nextSource . '#raw-bom',
            $nextSchemaCookie,
            $nextSchemaCookie,
        );

        $currentMatched = $current['currentMatchedRowids'];
        $nextMatched = $next['currentMatchedRowids'];
        $nextRawMatched = $nextWithoutBomStrip['currentMatchedRowids'];
        $bomRescued = array_values(array_diff($nextMatched, $nextRawMatched));
        sort($bomRescued);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($currentHadBom || $nextHadBom) {
            $reasons[] = 'prepared-pattern-bom';
        }
        if ($currentPattern !== $nextPattern) {
            $reasons[] = 'decoded-pattern';
        }
        if ($current['currentCandidateRowids'] !== $next['currentCandidateRowids']) {
            $reasons[] = 'candidate-rowset';
        }
        if ($currentMatched !== $nextMatched) {
            $reasons[] = 'matched-rowset';
        }
        if ($bomRescued !== []) {
            $reasons[] = 'bom-prefix-residual-rowset';
        }
        if ($current['currentMalformedRowids'] !== [] || $next['currentMalformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        $reasons = array_values(array_unique($reasons));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nexttwoZeroSix',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* prepared UTF-16 BOM pattern */',
            'currentPatternDecoded' => $currentDecoded,
            'nextPatternDecoded' => $nextDecoded,
            'currentPattern' => $currentPattern,
            'nextPattern' => $nextPattern,
            'escape' => $escape,
            'collation' => 'NOCASE',
            'currentPatternHadBom' => $currentHadBom,
            'nextPatternHadBom' => $nextHadBom,
            'currentPatternEncoding' => self::v206_encodingName($currentPatternEncoding),
            'nextPatternEncoding' => self::v206_encodingName($nextPatternEncoding),
            'currentPatternBytesHex' => bin2hex($currentPatternBytes),
            'nextPatternBytesHex' => bin2hex($nextPatternBytes),
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentPrefix' => $current['currentPrefix'],
            'nextPrefix' => $next['currentPrefix'],
            'rawBomPrefix' => $nextWithoutBomStrip['currentPrefix'],
            'currentRangeLowerInclusive' => $current['currentRangeLowerInclusive'],
            'nextRangeLowerInclusive' => $next['currentRangeLowerInclusive'],
            'rawBomRangeLowerInclusive' => $nextWithoutBomStrip['currentRangeLowerInclusive'],
            'currentRangeUpperBound' => $current['currentRangeUpperBound'],
            'nextRangeUpperBound' => $next['currentRangeUpperBound'],
            'rawBomRangeUpperBound' => $nextWithoutBomStrip['currentRangeUpperBound'],
            'currentIndexUsable' => $current['currentIndexUsable'],
            'nextIndexUsable' => $next['currentIndexUsable'],
            'rawBomIndexUsable' => $nextWithoutBomStrip['currentIndexUsable'],
            'currentCandidateRowids' => $current['currentCandidateRowids'],
            'nextCandidateRowids' => $next['currentCandidateRowids'],
            'rawBomCandidateRowids' => $nextWithoutBomStrip['currentCandidateRowids'],
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'rawBomMatchedRowids' => $nextRawMatched,
            'bomRescuedMatchedRowids' => $bomRescued,
            'matchedExitedRowids' => array_values(array_diff($currentMatched, $nextMatched)),
            'matchedEnteredRowids' => array_values(array_diff($nextMatched, $currentMatched)),
            'currentFalsePositiveRowids' => $current['currentFalsePositiveRowids'],
            'nextFalsePositiveRowids' => $next['currentFalsePositiveRowids'],
            'rawBomFalsePositiveRowids' => $nextWithoutBomStrip['currentFalsePositiveRowids'],
            'currentExcludedDecodedRowids' => $current['currentExcludedDecodedRowids'],
            'nextExcludedDecodedRowids' => $next['currentExcludedDecodedRowids'],
            'rawBomExcludedDecodedRowids' => $nextWithoutBomStrip['currentExcludedDecodedRowids'],
            'currentRtrimTexts' => $current['currentRtrimTexts'],
            'nextRtrimTexts' => $next['currentRtrimTexts'],
            'currentNocaseKeys' => $current['currentNocaseKeys'],
            'nextNocaseKeys' => $next['currentNocaseKeys'],
            'nextMatchedTexts' => $next['currentMatchedTexts'],
            'currentMalformedRowids' => $current['currentMalformedRowids'],
            'nextMalformedRowids' => $next['currentMalformedRowids'],
            'currentErrors' => $current['currentErrors'],
            'nextErrors' => $next['currentErrors'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'mustReprepareForPreparedBom' => $currentHadBom || $nextHadBom || $currentPattern !== $nextPattern,
            'bomStrippedBeforePrefixPlanning' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-prepared-like-pattern-bom-normalization',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-current-source-nexttwoZeroSix',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, prepared LIKE RHS normalization, ASCII NOCASE prefix ranges, RTRIM keys, and current-source cursor diagnostics',
            'non_overlap' => 'nextTwoZeroSix covers UTF-16 prepared LIKE pattern BOM normalization before NOCASE/RTRIM prefix planning; avoids accepted escape rebind nextTwoZeroZero, no-prefix nextTwoZeroThree, escaped literal nextOneNineFour/195, dangling ESCAPE nextOneEightSeven, Unicode GLOB ranges, and malformed UTF-16 insert guards',
        ];
    }

    private static function v206_decodePreparedText(string $bytes, int|string $encoding, string $label): string
    {
        try {
            return SQLiteEncodingCollationSourceCursor::decodeText($bytes, self::v206_encodingId($encoding));
        } catch (\InvalidArgumentException $exception) {
            throw new \InvalidArgumentException("SQLite UTF-16 NOCASE LIKE RTRIM nextTwoZeroSix prepared {$label} is malformed: " . $exception->getMessage());
        }
    }

    private static function v206_stripLeadingBom(string $value): string
    {
        return str_starts_with($value, "\xef\xbb\xbf") ? substr($value, 3) : $value;
    }

    private static function v206_encodingId(int|string $encoding): int
    {
        return match ($encoding) {
            1, 'UTF-8' => 1,
            2, 'UTF-16LE' => 2,
            3, 'UTF-16BE' => 3,
            default => throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoZeroSix encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function v206_encodingName(int|string $encoding): string
    {
        return match (self::v206_encodingId($encoding)) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
        };
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyRtrimCollationRebindPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_cache%',
        ?string $escape = '!',
        bool $currentUsesRtrim = true,
        bool $nextUsesRtrim = false,
        string $currentSource = 'main.app_settings@206',
        string $nextSource = 'main.app_settings@207',
        int $currentSchemaCookie = 206,
        int $nextSchemaCookie = 207,
    ): array {
        $current = self::v207_scan($currentRows, $pattern, $escape, $currentUsesRtrim);
        $next = self::v207_scan($nextRows, $pattern, $escape, $nextUsesRtrim);
        $currentWithNextRtrim = self::v207_scan($currentRows, $pattern, $escape, $nextUsesRtrim);
        $nextWithCurrentRtrim = self::v207_scan($nextRows, $pattern, $escape, $currentUsesRtrim);

        $currentMatched = self::v207_rowids($current['matched']);
        $nextMatched = self::v207_rowids($next['matched']);
        $currentMatchedWithNextRtrim = self::v207_rowids($currentWithNextRtrim['matched']);
        $nextMatchedWithCurrentRtrim = self::v207_rowids($nextWithCurrentRtrim['matched']);
        $rtrimResidualFlip = self::v207_symmetricDifference($currentMatched, $currentMatchedWithNextRtrim);
        $nextRtrimResidualFlip = self::v207_symmetricDifference($nextMatched, $nextMatchedWithCurrentRtrim);
        $matchedExited = array_values(array_diff($currentMatched, $nextMatched));
        $matchedEntered = array_values(array_diff($nextMatched, $currentMatched));
        sort($matchedExited);
        sort($matchedEntered);

        $currentLike = $current['likePlan'];
        $nextLike = $next['likePlan'];
        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($currentUsesRtrim !== $nextUsesRtrim) {
            $reasons[] = 'rtrim-collation-rebound';
        }
        if (($currentLike['prefix'] ?? null) !== ($nextLike['prefix'] ?? null)) {
            $reasons[] = 'like-prefix';
        }
        if (($currentLike['range'] ?? null) !== ($nextLike['range'] ?? null)) {
            $reasons[] = 'like-range';
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        if ($rtrimResidualFlip !== [] || $nextRtrimResidualFlip !== []) {
            $reasons[] = 'rtrim-residual-rowset';
        }
        if ($currentMatched !== $nextMatched) {
            $reasons[] = 'matched-rowset';
        }
        $reasons = array_values(array_unique($reasons));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nexttwoZeroSeven',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* rtrim collation rebind */',
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => 'NOCASE',
            'currentUsesRtrim' => $currentUsesRtrim,
            'nextUsesRtrim' => $nextUsesRtrim,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentPrefix' => $currentLike['prefix'],
            'nextPrefix' => $nextLike['prefix'],
            'currentRangeLowerInclusive' => $currentLike['range']['lowerInclusive'] ?? null,
            'currentRangeUpperBound' => $currentLike['range']['upperBound'] ?? null,
            'nextRangeLowerInclusive' => $nextLike['range']['lowerInclusive'] ?? null,
            'nextRangeUpperBound' => $nextLike['range']['upperBound'] ?? null,
            'currentCandidateRowids' => self::v207_rowids($current['candidates']),
            'nextCandidateRowids' => self::v207_rowids($next['candidates']),
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'currentMatchedWithNextRtrimRowids' => $currentMatchedWithNextRtrim,
            'nextMatchedWithCurrentRtrimRowids' => $nextMatchedWithCurrentRtrim,
            'rtrimResidualFlipRowids' => $rtrimResidualFlip,
            'nextRtrimResidualFlipRowids' => $nextRtrimResidualFlip,
            'matchedExitedRowids' => $matchedExited,
            'matchedEnteredRowids' => $matchedEntered,
            'currentFalsePositiveRowids' => self::v207_rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::v207_rowids($next['falsePositive']),
            'currentDecodedRowids' => self::v207_rowids($current['decoded']),
            'nextDecodedRowids' => self::v207_rowids($next['decoded']),
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'currentTexts' => self::v207_map($current['decoded'], 'text'),
            'nextTexts' => self::v207_map($next['decoded'], 'text'),
            'currentProbeTexts' => self::v207_map($current['decoded'], 'probeText'),
            'nextProbeTexts' => self::v207_map($next['decoded'], 'probeText'),
            'currentNocaseKeys' => self::v207_map($current['decoded'], 'nocaseKey'),
            'nextNocaseKeys' => self::v207_map($next['decoded'], 'nocaseKey'),
            'rtrimChanged' => $currentUsesRtrim !== $nextUsesRtrim,
            'prefixChangedByRtrim' => ($currentLike['prefix'] ?? null) !== ($nextLike['prefix'] ?? null),
            'rangeChangedByRtrim' => ($currentLike['range'] ?? null) !== ($nextLike['range'] ?? null),
            'residualChangedByRtrim' => $rtrimResidualFlip !== [] || $nextRtrimResidualFlip !== [],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'mustReprepareForRtrimRebind' => $currentUsesRtrim !== $nextUsesRtrim,
            'staleRangeCursorRisk' => $currentUsesRtrim !== $nextUsesRtrim && ($rtrimResidualFlip !== [] || $nextRtrimResidualFlip !== []),
            'invalidationReasons' => $reasons,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'rtrimRebindCheckedBeforeRangeReuse' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-current-source-nexttwoZeroSeven',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, LIKE prefix planning, RTRIM expression keys, NOCASE residual matching, and current-source cursor invalidation diagnostics',
            'non_overlap' => 'nextTwoZeroSeven adds rtrim/no-rtrim expression rebind fencing for UTF-16 NOCASE LIKE current-source cursors; avoids accepted nextTwoZeroZero ESCAPE rebind, nextTwoZeroSix integrated encoding batch, Unicode GLOB ranges, malformed UTF-16 insert guards, and storage/planner clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{likePlan:array<string,mixed>,decoded:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function v207_scan(array $rows, string $pattern, ?string $escape, bool $usesRtrim): array
    {
        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $decoded = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            self::v207_assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
                $probe = $usesRtrim ? rtrim($text, ' ') : $text;
                $decoded[] = [
                    'rowid' => $row['option_id'],
                    'text' => $text,
                    'probeText' => $probe,
                    'nocaseKey' => self::v207_asciiLower($probe),
                    'bytesHex' => bin2hex($row['option_name_bytes']),
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['option_id'];
                $errors[$row['option_id']] = $exception->getMessage();
            }
        }

        usort($decoded, self::v207_sortRows(...));
        sort($malformed);
        ksort($errors);

        $candidates = [];
        $matched = [];
        $falsePositive = [];
        foreach ($decoded as $entry) {
            if (!self::v207_inRange($entry['nocaseKey'], $like['range'])) {
                continue;
            }
            $entry['residualMatch'] = SQLiteDatabase::likeMatches($entry['probeText'], $pattern, $escape, false);
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
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row */
    private static function v207_assertRow(array $row): void
    {
        if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoZeroSeven rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoZeroSeven rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoZeroSeven rows require integer text_encoding');
        }
    }

    /** @param ?array{lowerInclusive:string,upperBound:?string} $range */
    private static function v207_inRange(string $key, ?array $range): bool
    {
        if ($range === null || strcmp($key, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0;
    }

    /** @param array{nocaseKey:string,rowid:int} $left @param array{nocaseKey:string,rowid:int} $right */
    private static function v207_sortRows(array $left, array $right): int
    {
        $comparison = strcmp($left['nocaseKey'], $right['nocaseKey']);

        return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
    }

    /** @param list<array{rowid:int}> $rows @return list<int> */
    private static function v207_rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function v207_symmetricDifference(array $left, array $right): array
    {
        $diff = array_values(array_unique(array_merge(array_diff($left, $right), array_diff($right, $left))));
        sort($diff);

        return $diff;
    }

    /** @param list<array<string,mixed>> $rows @return array<int,mixed> */
    private static function v207_map(array $rows, string $key): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[$row['rowid']] = $row[$key];
        }

        return $mapped;
    }

    private static function v207_asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyPreparedEscapePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_cache%',
        string $currentEscapeBytes = "!\0",
        int|string $currentEscapeEncoding = 'UTF-16LE',
        string $nextEscapeBytes = "\xfe\xff\x00~",
        int|string $nextEscapeEncoding = 'UTF-16BE',
        string $currentSource = 'main.app_settings@207',
        string $nextSource = 'main.app_settings@208',
        int $currentSchemaCookie = 207,
        int $nextSchemaCookie = 208,
    ): array {
        $currentDecoded = self::v208_decodePreparedEscape($currentEscapeBytes, $currentEscapeEncoding, 'current escape');
        $nextDecoded = self::v208_decodePreparedEscape($nextEscapeBytes, $nextEscapeEncoding, 'next escape');
        $currentEscape = self::v208_stripLeadingBom($currentDecoded);
        $nextEscape = self::v208_stripLeadingBom($nextDecoded);
        self::v208_assertSingleCharacter($currentEscape, 'current escape');
        self::v208_assertSingleCharacter($nextEscape, 'next escape');

        $currentHadBom = $currentDecoded !== $currentEscape;
        $nextHadBom = $nextDecoded !== $nextEscape;
        $currentPattern = self::v208_rewritePatternEscape($pattern, '!', $currentEscape);
        $nextPattern = self::v208_rewritePatternEscape($pattern, '!', $nextEscape);

        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyEscapeRebindPlan(
            $currentRows,
            $nextRows,
            $currentPattern,
            $currentEscape,
            $nextPattern,
            $nextEscape,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $rawNext = null;
        $rawNextError = null;
        try {
            $rawNext = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyEscapeRebindPlan(
                $nextRows,
                $nextRows,
                $nextPattern,
                $nextDecoded,
                $nextPattern,
                $nextDecoded,
                $nextSource . '#raw-escape-bom',
                $nextSource . '#raw-escape-bom',
                $nextSchemaCookie,
                $nextSchemaCookie,
            );
        } catch (\InvalidArgumentException $exception) {
            $rawNextError = $exception->getMessage();
        }

        $reasons = $base['invalidationReasons'];
        if ($currentHadBom || $nextHadBom) {
            $reasons[] = 'prepared-escape-bom';
        }
        if ($currentEscapeBytes !== $nextEscapeBytes || self::v208_encodingName($currentEscapeEncoding) !== self::v208_encodingName($nextEscapeEncoding)) {
            $reasons[] = 'prepared-escape-bytes';
        }
        $reasons = array_values(array_unique($reasons));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nexttwoZeroEight',
            'baseStatus' => $base['status'],
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* prepared UTF-16 escape */',
            'patternTemplate' => $pattern,
            'currentPattern' => $currentPattern,
            'nextPattern' => $nextPattern,
            'currentEscapeDecoded' => $currentDecoded,
            'nextEscapeDecoded' => $nextDecoded,
            'currentEscape' => $currentEscape,
            'nextEscape' => $nextEscape,
            'currentEscapeHadBom' => $currentHadBom,
            'nextEscapeHadBom' => $nextHadBom,
            'currentEscapeEncoding' => self::v208_encodingName($currentEscapeEncoding),
            'nextEscapeEncoding' => self::v208_encodingName($nextEscapeEncoding),
            'currentEscapeBytesHex' => bin2hex($currentEscapeBytes),
            'nextEscapeBytesHex' => bin2hex($nextEscapeBytes),
            'preparedEscapeChanged' => $currentEscape !== $nextEscape,
            'preparedEscapeBytesChanged' => $currentEscapeBytes !== $nextEscapeBytes,
            'preparedEscapeBomStrippedBeforeValidation' => $currentHadBom || $nextHadBom,
            'rawBomEscapeRejected' => $rawNext === null,
            'rawBomEscapeError' => $rawNextError,
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'collation' => $base['collation'],
            'currentPrefix' => $base['currentPrefix'],
            'nextPrefix' => $base['nextPrefix'],
            'currentRangeLowerInclusive' => $base['currentRangeLowerInclusive'],
            'nextRangeLowerInclusive' => $base['nextRangeLowerInclusive'],
            'currentRangeUpperBound' => $base['currentRangeUpperBound'],
            'nextRangeUpperBound' => $base['nextRangeUpperBound'],
            'currentIndexUsable' => $base['currentIndexUsable'],
            'nextIndexUsable' => $base['nextIndexUsable'],
            'currentCandidateRowids' => $base['currentCandidateRowids'],
            'nextCandidateRowids' => $base['nextCandidateRowids'],
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'nextMatchedWithCurrentEscapeRowids' => $base['nextMatchedWithCurrentEscapeRowids'],
            'currentMatchedWithNextEscapeRowids' => $base['currentMatchedWithNextEscapeRowids'],
            'escapeResidualFlipRowids' => $base['escapeResidualFlipRowids'],
            'currentEscapeResidualFlipRowids' => $base['currentEscapeResidualFlipRowids'],
            'matchedExitedRowids' => $base['matchedExitedRowids'],
            'matchedEnteredRowids' => $base['matchedEnteredRowids'],
            'currentFalsePositiveRowids' => $base['currentFalsePositiveRowids'],
            'nextFalsePositiveRowids' => $base['nextFalsePositiveRowids'],
            'currentExcludedDecodedRowids' => $base['currentExcludedDecodedRowids'],
            'nextExcludedDecodedRowids' => $base['nextExcludedDecodedRowids'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'currentErrors' => $base['currentErrors'],
            'nextErrors' => $base['nextErrors'],
            'currentRtrimTexts' => $base['currentRtrimTexts'],
            'nextRtrimTexts' => $base['nextRtrimTexts'],
            'currentNocaseKeys' => $base['currentNocaseKeys'],
            'nextNocaseKeys' => $base['nextNocaseKeys'],
            'currentMatchedTexts' => $base['currentMatchedTexts'],
            'nextMatchedTexts' => $base['nextMatchedTexts'],
            'escapeChanged' => $base['escapeChanged'],
            'prefixChangedByEscape' => $base['prefixChangedByEscape'],
            'rangeChangedByEscape' => $base['rangeChangedByEscape'],
            'residualChangedByEscape' => $base['residualChangedByEscape'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'mustReprepareForPreparedEscape' => $currentEscape !== $nextEscape || $currentHadBom || $nextHadBom,
            'staleRangeCursorRisk' => $base['staleRangeCursorRisk'],
            'invalidationReasons' => $reasons,
            'rtrimTrimsOnlyAsciiSpace' => $base['rtrimTrimsOnlyAsciiSpace'],
            'nocaseFoldsAsciiOnly' => $base['nocaseFoldsAsciiOnly'],
            'escapeDecodedBeforeRangePlanning' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-prepared-like-escape-decode',
                'sqlite-like-escape-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-current-source-nexttwoZeroEight',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, prepared LIKE ESCAPE byte normalization, LIKE ESCAPE prefix planning, RTRIM keys, and current-source cursor diagnostics',
            'non_overlap' => 'nextTwoZeroEight covers prepared UTF-16 ESCAPE parameter decoding and BOM stripping before NOCASE/RTRIM LIKE range planning; avoids accepted prepared-pattern BOM nextTwoZeroSix, escape rebind nextTwoZeroZero, no-prefix nextTwoZeroThree, escaped literal nextOneNineFour/195, Unicode GLOB ranges, and malformed UTF-16 insert guards',
        ];
    }

    private static function v208_decodePreparedEscape(string $bytes, int|string $encoding, string $label): string
    {
        try {
            return SQLiteEncodingCollationSourceCursor::decodeText($bytes, self::v208_encodingId($encoding));
        } catch (\InvalidArgumentException $exception) {
            throw new \InvalidArgumentException("SQLite UTF-16 NOCASE LIKE RTRIM nextTwoZeroEight prepared {$label} is malformed: " . $exception->getMessage());
        }
    }

    private static function v208_stripLeadingBom(string $value): string
    {
        return str_starts_with($value, "\xef\xbb\xbf") ? substr($value, 3) : $value;
    }

    private static function v208_assertSingleCharacter(string $value, string $label): void
    {
        if ($value === '' || preg_match('//u', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite UTF-16 NOCASE LIKE RTRIM nextTwoZeroEight prepared {$label} must be one UTF-8 character");
        }
        if (count(preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: []) !== 1) {
            throw new \InvalidArgumentException("SQLite UTF-16 NOCASE LIKE RTRIM nextTwoZeroEight prepared {$label} must be one UTF-8 character");
        }
    }

    private static function v208_rewritePatternEscape(string $pattern, string $from, string $to): string
    {
        return $from === $to ? $pattern : str_replace($from, $to, $pattern);
    }

    private static function v208_encodingId(int|string $encoding): int
    {
        return match ($encoding) {
            1, 'UTF-8' => 1,
            2, 'UTF-16LE' => 2,
            3, 'UTF-16BE' => 3,
            default => throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoZeroEight encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function v208_encodingName(int|string $encoding): string
    {
        return match (self::v208_encodingId($encoding)) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
        };
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyAsciiSpaceRtrimPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin%',
        ?string $escape = '!',
        string $currentSource = 'main.app_settings@208',
        string $nextSource = 'main.app_settings@209',
        int $currentSchemaCookie = 208,
        int $nextSchemaCookie = 209,
    ): array {
        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyEscapeRebindPlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escape,
            $pattern,
            $escape,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $currentDecoded = self::v209_decodeRows($currentRows);
        $nextDecoded = self::v209_decodeRows($nextRows);
        $currentDiagnostics = self::v209_suffixDiagnostics($currentDecoded);
        $nextDiagnostics = self::v209_suffixDiagnostics($nextDecoded);
        $currentUnicodeCase = self::v209_unicodeCaseDiagnostics($currentDecoded, $pattern, $escape);
        $nextUnicodeCase = self::v209_unicodeCaseDiagnostics($nextDecoded, $pattern, $escape);

        $reasons = $base['invalidationReasons'];
        if ($currentDiagnostics['asciiSpaceTrimmedRowids'] !== $nextDiagnostics['asciiSpaceTrimmedRowids']) {
            $reasons[] = 'ascii-space-rtrim-rowset';
        }
        if ($currentDiagnostics['nonAsciiWhitespacePreservedRowids'] !== $nextDiagnostics['nonAsciiWhitespacePreservedRowids']) {
            $reasons[] = 'non-ascii-whitespace-rtrim-preserved';
        }
        if ($currentUnicodeCase['unicodeCaseVariantRowids'] !== [] || $nextUnicodeCase['unicodeCaseVariantRowids'] !== []) {
            $reasons[] = 'unicode-case-not-folded';
        }
        if ($currentDiagnostics['malformedRowids'] !== [] || $nextDiagnostics['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        $reasons = array_values(array_unique($reasons));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nexttwoZeroNine',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* ASCII-space RTRIM only */',
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => 'NOCASE',
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'prefix' => $base['currentPrefix'],
            'rangeLowerInclusive' => $base['currentRangeLowerInclusive'],
            'rangeUpperBound' => $base['currentRangeUpperBound'],
            'currentIndexUsable' => $base['currentIndexUsable'],
            'nextIndexUsable' => $base['nextIndexUsable'],
            'currentCandidateRowids' => $base['currentCandidateRowids'],
            'nextCandidateRowids' => $base['nextCandidateRowids'],
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'matchedExitedRowids' => $base['matchedExitedRowids'],
            'matchedEnteredRowids' => $base['matchedEnteredRowids'],
            'currentRtrimTexts' => $base['currentRtrimTexts'],
            'nextRtrimTexts' => $base['nextRtrimTexts'],
            'currentNocaseKeys' => $base['currentNocaseKeys'],
            'nextNocaseKeys' => $base['nextNocaseKeys'],
            'currentAsciiSpaceTrimmedRowids' => $currentDiagnostics['asciiSpaceTrimmedRowids'],
            'nextAsciiSpaceTrimmedRowids' => $nextDiagnostics['asciiSpaceTrimmedRowids'],
            'currentNonAsciiWhitespacePreservedRowids' => $currentDiagnostics['nonAsciiWhitespacePreservedRowids'],
            'nextNonAsciiWhitespacePreservedRowids' => $nextDiagnostics['nonAsciiWhitespacePreservedRowids'],
            'currentTabPreservedRowids' => $currentDiagnostics['tabPreservedRowids'],
            'nextTabPreservedRowids' => $nextDiagnostics['tabPreservedRowids'],
            'currentNbspPreservedRowids' => $currentDiagnostics['nbspPreservedRowids'],
            'nextNbspPreservedRowids' => $nextDiagnostics['nbspPreservedRowids'],
            'currentUnicodeCaseVariantRowids' => $currentUnicodeCase['unicodeCaseVariantRowids'],
            'nextUnicodeCaseVariantRowids' => $nextUnicodeCase['unicodeCaseVariantRowids'],
            'currentUnicodeCaseVariantTexts' => $currentUnicodeCase['unicodeCaseVariantTexts'],
            'nextUnicodeCaseVariantTexts' => $nextUnicodeCase['unicodeCaseVariantTexts'],
            'currentExcludedDecodedRowids' => $base['currentExcludedDecodedRowids'],
            'nextExcludedDecodedRowids' => $base['nextExcludedDecodedRowids'],
            'currentFalsePositiveRowids' => $base['currentFalsePositiveRowids'],
            'nextFalsePositiveRowids' => $base['nextFalsePositiveRowids'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'currentErrors' => $base['currentErrors'],
            'nextErrors' => $base['nextErrors'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'rtrimTrimsOnlyAsciiSpace' => true,
            'tabSuffixPreservedByRtrim' => true,
            'nbspSuffixPreservedByRtrim' => true,
            'nocaseFoldsAsciiOnly' => true,
            'unicodeCaseVariantsRequireResidualCheck' => true,
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-ascii-space-only',
                'sqlite-nocase-ascii-only',
                'sqlite-current-source-nexttwoZeroNine',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE range planning, RTRIM expression keys, and residual LIKE matching',
            'non_overlap' => 'nextTwoZeroNine covers ASCII-space-only RTRIM and ASCII-only NOCASE diagnostics for UTF-16 LIKE current-source reuse; avoids accepted BOM normalization nextTwoZeroSix, escape rebind nextTwoZeroZero, escaped literal/dangling ESCAPE slices, Unicode GLOB ranges, and malformed UTF-16 insert guards',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int,array{text:string,rtrimText:string,nocaseKey:string}>
     */
    private static function v209_decodeRows(array $rows): array
    {
        $decoded = [];
        foreach ($rows as $row) {
            if (!isset($row['option_id']) || !is_int($row['option_id'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoZeroNine rows require integer option_id');
            }
            if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoZeroNine rows require option_name_bytes');
            }
            if (!isset($row['text_encoding']) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoZeroNine rows require integer text_encoding');
            }

            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
                $rtrim = rtrim($text, ' ');
                $decoded[$row['option_id']] = [
                    'text' => $text,
                    'rtrimText' => $rtrim,
                    'nocaseKey' => self::v209_asciiLower($rtrim),
                ];
            } catch (\InvalidArgumentException) {
                continue;
            }
        }
        ksort($decoded);

        return $decoded;
    }

    /**
     * @param array<int,array{text:string,rtrimText:string,nocaseKey:string}> $decoded
     * @return array{asciiSpaceTrimmedRowids:list<int>,nonAsciiWhitespacePreservedRowids:list<int>,tabPreservedRowids:list<int>,nbspPreservedRowids:list<int>,malformedRowids:list<int>}
     */
    private static function v209_suffixDiagnostics(array $decoded): array
    {
        $asciiSpace = [];
        $nonAsciiWhitespace = [];
        $tabs = [];
        $nbsp = [];
        foreach ($decoded as $rowid => $entry) {
            if ($entry['text'] !== $entry['rtrimText']) {
                $asciiSpace[] = $rowid;
            }
            if (str_ends_with($entry['rtrimText'], "\t")) {
                $tabs[] = $rowid;
                $nonAsciiWhitespace[] = $rowid;
            }
            if (str_ends_with($entry['rtrimText'], "\xc2\xa0")) {
                $nbsp[] = $rowid;
                $nonAsciiWhitespace[] = $rowid;
            }
        }
        $nonAsciiWhitespace = array_values(array_unique($nonAsciiWhitespace));
        sort($asciiSpace);
        sort($nonAsciiWhitespace);
        sort($tabs);
        sort($nbsp);

        return [
            'asciiSpaceTrimmedRowids' => $asciiSpace,
            'nonAsciiWhitespacePreservedRowids' => $nonAsciiWhitespace,
            'tabPreservedRowids' => $tabs,
            'nbspPreservedRowids' => $nbsp,
            'malformedRowids' => [],
        ];
    }

    /**
     * @param array<int,array{text:string,rtrimText:string,nocaseKey:string}> $decoded
     * @return array{unicodeCaseVariantRowids:list<int>,unicodeCaseVariantTexts:array<int,string>}
     */
    private static function v209_unicodeCaseDiagnostics(array $decoded, string $pattern, ?string $escape): array
    {
        $prefix = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false)['prefix'] ?? '';
        $asciiPrefix = self::v209_asciiLower((string) $prefix);
        $rowids = [];
        $texts = [];
        foreach ($decoded as $rowid => $entry) {
            if (str_starts_with(self::v209_unicodeCasePrefixApprox($entry['rtrimText']), $asciiPrefix)
                && !str_starts_with($entry['nocaseKey'], $asciiPrefix)) {
                $rowids[] = $rowid;
                $texts[$rowid] = $entry['rtrimText'];
            }
        }
        sort($rowids);
        ksort($texts);

        return [
            'unicodeCaseVariantRowids' => $rowids,
            'unicodeCaseVariantTexts' => $texts,
        ];
    }

    private static function v209_asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    private static function v209_unicodeCasePrefixApprox(string $value): string
    {
        $latinCapitalIWithDot = "\xc4\xb0";
        $greekCapitalSigma = "\xce\xa3";
        $greekSmallSigma = "\xcf\x83";

        if (str_starts_with($value, $latinCapitalIWithDot)) {
            return 'i' . self::v209_asciiLower(substr($value, strlen($latinCapitalIWithDot)));
        }
        if (str_starts_with($value, $greekCapitalSigma)) {
            return $greekSmallSigma . self::v209_asciiLower(substr($value, strlen($greekCapitalSigma)));
        }

        return self::v209_asciiLower($value);
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyEmbeddedNulPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = "plugin\0cache%",
        ?string $escape = null,
        string $currentSource = 'main.app_settings@209',
        string $nextSource = 'main.app_settings@210',
        int $currentSchemaCookie = 209,
        int $nextSchemaCookie = 210,
    ): array {
        if (!str_contains($pattern, "\0")) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoOneZero expects an embedded-NUL LIKE pattern');
        }

        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyEscapeRebindPlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escape,
            $pattern,
            $escape,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $currentDecoded = self::v210_decodeRows($currentRows);
        $nextDecoded = self::v210_decodeRows($nextRows);
        $currentNul = self::v210_nulDiagnostics($currentDecoded, $base['currentMatchedRowids'], $base['currentFalsePositiveRowids']);
        $nextNul = self::v210_nulDiagnostics($nextDecoded, $base['nextMatchedRowids'], $base['nextFalsePositiveRowids']);

        $reasons = $base['invalidationReasons'];
        if ($currentNul['nulRowids'] !== $nextNul['nulRowids']) {
            $reasons[] = 'embedded-nul-rowset';
        }
        if ($currentNul['nulMatchedRowids'] !== $nextNul['nulMatchedRowids']) {
            $reasons[] = 'embedded-nul-matched-rowset';
        }
        if ($currentNul['nulFalsePositiveRowids'] !== $nextNul['nulFalsePositiveRowids']) {
            $reasons[] = 'embedded-nul-false-positive-rowset';
        }
        $reasons = array_values(array_unique($reasons));

        $prefix = (string) $base['currentPrefix'];

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nexttwoOneZero',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? /* embedded NUL */',
            'pattern' => $pattern,
            'patternHex' => bin2hex($pattern),
            'escape' => $escape,
            'collation' => 'NOCASE',
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'prefix' => $prefix,
            'prefixHex' => bin2hex($prefix),
            'prefixContainsNul' => str_contains($prefix, "\0"),
            'rangeLowerInclusive' => $base['currentRangeLowerInclusive'],
            'rangeLowerInclusiveHex' => bin2hex((string) $base['currentRangeLowerInclusive']),
            'rangeUpperBound' => $base['currentRangeUpperBound'],
            'rangeUpperBoundHex' => bin2hex((string) $base['currentRangeUpperBound']),
            'nulBytePositionInPrefix' => strpos($prefix, "\0"),
            'currentIndexUsable' => $base['currentIndexUsable'],
            'nextIndexUsable' => $base['nextIndexUsable'],
            'usesPrefixRangeCursor' => $base['currentIndexUsable'] && $base['nextIndexUsable'],
            'currentCandidateRowids' => $base['currentCandidateRowids'],
            'nextCandidateRowids' => $base['nextCandidateRowids'],
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'matchedRetainedRowids' => self::v210_retained($base['currentMatchedRowids'], $base['nextMatchedRowids']),
            'matchedExitedRowids' => self::v210_exited($base['currentMatchedRowids'], $base['nextMatchedRowids']),
            'matchedEnteredRowids' => self::v210_entered($base['currentMatchedRowids'], $base['nextMatchedRowids']),
            'currentFalsePositiveRowids' => $base['currentFalsePositiveRowids'],
            'nextFalsePositiveRowids' => $base['nextFalsePositiveRowids'],
            'currentExcludedDecodedRowids' => $base['currentExcludedDecodedRowids'],
            'nextExcludedDecodedRowids' => $base['nextExcludedDecodedRowids'],
            'currentRtrimTexts' => $base['currentRtrimTexts'],
            'nextRtrimTexts' => $base['nextRtrimTexts'],
            'currentRtrimHex' => self::v210_hexMap($base['currentRtrimTexts']),
            'nextRtrimHex' => self::v210_hexMap($base['nextRtrimTexts']),
            'currentNocaseKeys' => $base['currentNocaseKeys'],
            'nextNocaseKeys' => $base['nextNocaseKeys'],
            'currentNocaseKeyHex' => self::v210_hexMap($base['currentNocaseKeys']),
            'nextNocaseKeyHex' => self::v210_hexMap($base['nextNocaseKeys']),
            'currentMatchedTexts' => $base['currentMatchedTexts'],
            'nextMatchedTexts' => $base['nextMatchedTexts'],
            'currentMatchedHex' => self::v210_hexMap($base['currentMatchedTexts']),
            'nextMatchedHex' => self::v210_hexMap($base['nextMatchedTexts']),
            'currentEmbeddedNulRowids' => $currentNul['nulRowids'],
            'nextEmbeddedNulRowids' => $nextNul['nulRowids'],
            'currentEmbeddedNulMatchedRowids' => $currentNul['nulMatchedRowids'],
            'nextEmbeddedNulMatchedRowids' => $nextNul['nulMatchedRowids'],
            'currentEmbeddedNulFalsePositiveRowids' => $currentNul['nulFalsePositiveRowids'],
            'nextEmbeddedNulFalsePositiveRowids' => $nextNul['nulFalsePositiveRowids'],
            'currentEmbeddedNulPositions' => $currentNul['nulPositions'],
            'nextEmbeddedNulPositions' => $nextNul['nulPositions'],
            'currentTextAfterNul' => $currentNul['textAfterNul'],
            'nextTextAfterNul' => $nextNul['textAfterNul'],
            'currentTextAfterNulHex' => self::v210_hexMap($currentNul['textAfterNul']),
            'nextTextAfterNulHex' => self::v210_hexMap($nextNul['textAfterNul']),
            'currentTruncatedPrefixWouldMatchRowids' => $currentNul['truncatedPrefixWouldMatchRowids'],
            'nextTruncatedPrefixWouldMatchRowids' => $nextNul['truncatedPrefixWouldMatchRowids'],
            'currentTruncatedPrefixFalsePositiveRowids' => array_values(array_diff($currentNul['truncatedPrefixWouldMatchRowids'], $base['currentMatchedRowids'])),
            'nextTruncatedPrefixFalsePositiveRowids' => array_values(array_diff($nextNul['truncatedPrefixWouldMatchRowids'], $base['nextMatchedRowids'])),
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'currentErrors' => $base['currentErrors'],
            'nextErrors' => $base['nextErrors'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => $reasons,
            'embeddedNulDoesNotTerminateText' => true,
            'likeResidualSeesBytesAfterNul' => true,
            'rtrimTrimsOnlyAsciiSpaceAfterNulAwareDecode' => true,
            'nocaseFoldsAsciiOnlyAcrossNul' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-embedded-nul-text',
                'sqlite-current-source-nexttwoOneZero',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE range planning, RTRIM expression keys, and binary-safe PHP string residual matching',
            'non_overlap' => 'nextTwoOneZero covers embedded NUL text and pattern bytes in UTF-16 NOCASE/RTRIM LIKE current-source scans; avoids accepted ASCII-space RTRIM nextTwoZeroNine, BOM normalization, escape rebind, no-prefix scans, Unicode GLOB ranges, and malformed UTF-16 insert guards',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int,string>
     */
    private static function v210_decodeRows(array $rows): array
    {
        $decoded = [];
        foreach ($rows as $row) {
            if (!isset($row['option_id']) || !is_int($row['option_id'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoOneZero rows require integer option_id');
            }
            if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoOneZero rows require option_name_bytes');
            }
            if (!isset($row['text_encoding']) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoOneZero rows require integer text_encoding');
            }

            try {
                $decoded[$row['option_id']] = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
            } catch (\InvalidArgumentException) {
                continue;
            }
        }
        ksort($decoded);

        return $decoded;
    }

    /**
     * @param array<int,string> $decoded
     * @param list<int> $matchedRowids
     * @param list<int> $falsePositiveRowids
     * @return array{nulRowids:list<int>,nulMatchedRowids:list<int>,nulFalsePositiveRowids:list<int>,nulPositions:array<int,int>,textAfterNul:array<int,string>,truncatedPrefixWouldMatchRowids:list<int>}
     */
    private static function v210_nulDiagnostics(array $decoded, array $matchedRowids, array $falsePositiveRowids): array
    {
        $nulRowids = [];
        $positions = [];
        $afterNul = [];
        $truncatedPrefixWouldMatch = [];
        foreach ($decoded as $rowid => $text) {
            $position = strpos($text, "\0");
            if ($position === false) {
                continue;
            }
            $nulRowids[] = $rowid;
            $positions[$rowid] = $position;
            $afterNul[$rowid] = substr($text, $position + 1);
            if (str_starts_with(strtolower(substr($text, 0, $position)), 'plugin')) {
                $truncatedPrefixWouldMatch[] = $rowid;
            }
        }

        sort($nulRowids);
        sort($truncatedPrefixWouldMatch);
        ksort($positions);
        ksort($afterNul);

        return [
            'nulRowids' => $nulRowids,
            'nulMatchedRowids' => array_values(array_intersect($nulRowids, $matchedRowids)),
            'nulFalsePositiveRowids' => array_values(array_intersect($nulRowids, $falsePositiveRowids)),
            'nulPositions' => $positions,
            'textAfterNul' => $afterNul,
            'truncatedPrefixWouldMatchRowids' => $truncatedPrefixWouldMatch,
        ];
    }

    /** @param array<int,string> $values @return array<int,string> */
    private static function v210_hexMap(array $values): array
    {
        $hex = [];
        foreach ($values as $rowid => $value) {
            $hex[$rowid] = bin2hex($value);
        }

        return $hex;
    }

    /** @param list<int> $current @param list<int> $next @return list<int> */
    private static function v210_retained(array $current, array $next): array
    {
        return array_values(array_intersect($current, $next));
    }

    /** @param list<int> $current @param list<int> $next @return list<int> */
    private static function v210_exited(array $current, array $next): array
    {
        return array_values(array_diff($current, $next));
    }

    /** @param list<int> $current @param list<int> $next @return list<int> */
    private static function v210_entered(array $current, array $next): array
    {
        return array_values(array_diff($next, $current));
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeySourceRefreshPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_cache%',
        ?string $escape = '!',
        string $currentSource = 'main.app_settings@210',
        string $nextSource = 'main.app_settings@211',
        int $currentSchemaCookie = 210,
        int $nextSchemaCookie = 211,
    ): array {
        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $current = self::v211_scan($currentRows, $like, $pattern, $escape);
        $next = self::v211_scan($nextRows, $like, $pattern, $escape);

        $currentMatched = self::v211_rowids($current['matched']);
        $nextMatched = self::v211_rowids($next['matched']);
        $currentCandidates = self::v211_rowids($current['candidates']);
        $nextCandidates = self::v211_rowids($next['candidates']);
        $currentFalsePositive = self::v211_rowids($current['falsePositive']);
        $nextFalsePositive = self::v211_rowids($next['falsePositive']);
        $byteOrderOnlyRowids = self::v211_byteOrderOnlyRowids($current['decoded'], $next['decoded']);
        $textChangedRowids = self::v211_changedRowids($current['decoded'], $next['decoded'], 'rtrimText');
        $encodingChangedRowids = self::v211_changedRowids($current['decoded'], $next['decoded'], 'encodingName');
        $matchedExited = self::v211_sortedDiff($currentMatched, $nextMatched);
        $matchedEntered = self::v211_sortedDiff($nextMatched, $currentMatched);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($currentCandidates !== $nextCandidates) {
            $reasons[] = 'candidate-rowset';
        }
        if ($currentMatched !== $nextMatched) {
            $reasons[] = 'matched-rowset';
        }
        if ($currentFalsePositive !== $nextFalsePositive) {
            $reasons[] = 'range-false-positive-rowset';
        }
        if ($textChangedRowids !== []) {
            $reasons[] = 'decoded-rtrim-text';
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        if ($byteOrderOnlyRowids !== [] && $reasons === []) {
            $reasons[] = 'byte-order-only-refresh';
        }
        $reasons = array_values(array_unique($reasons));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nexttwoOneOne',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* current-source refresh */',
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
            'candidateRetainedRowids' => self::v211_sortedIntersect($currentCandidates, $nextCandidates),
            'candidateExitedRowids' => self::v211_sortedDiff($currentCandidates, $nextCandidates),
            'candidateEnteredRowids' => self::v211_sortedDiff($nextCandidates, $currentCandidates),
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'matchedRetainedRowids' => self::v211_sortedIntersect($currentMatched, $nextMatched),
            'matchedExitedRowids' => $matchedExited,
            'matchedEnteredRowids' => $matchedEntered,
            'currentFalsePositiveRowids' => $currentFalsePositive,
            'nextFalsePositiveRowids' => $nextFalsePositive,
            'currentExcludedDecodedRowids' => self::v211_sortedDiff(self::v211_rowids($current['decoded']), $currentCandidates),
            'nextExcludedDecodedRowids' => self::v211_sortedDiff(self::v211_rowids($next['decoded']), $nextCandidates),
            'currentRtrimTexts' => self::v211_mapByRowid($current['decoded'], 'rtrimText'),
            'nextRtrimTexts' => self::v211_mapByRowid($next['decoded'], 'rtrimText'),
            'currentNocaseKeys' => self::v211_mapByRowid($current['decoded'], 'nocaseKey'),
            'nextNocaseKeys' => self::v211_mapByRowid($next['decoded'], 'nocaseKey'),
            'currentEncodings' => self::v211_mapByRowid($current['decoded'], 'encodingName'),
            'nextEncodings' => self::v211_mapByRowid($next['decoded'], 'encodingName'),
            'byteOrderOnlyRowids' => $byteOrderOnlyRowids,
            'encodingChangedRowids' => $encodingChangedRowids,
            'decodedRtrimTextChangedRowids' => $textChangedRowids,
            'currentMatchedTexts' => self::v211_selectMap(self::v211_mapByRowid($current['decoded'], 'rtrimText'), $currentMatched),
            'nextMatchedTexts' => self::v211_selectMap(self::v211_mapByRowid($next['decoded'], 'rtrimText'), $nextMatched),
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'cursorInvalidated' => array_diff($reasons, ['byte-order-only-refresh']) !== [],
            'cursorReusable' => array_diff($reasons, ['byte-order-only-refresh']) === [],
            'byteOrderOnlyRefreshReusable' => $byteOrderOnlyRowids !== [] && array_diff($reasons, ['byte-order-only-refresh']) === [],
            'invalidationReasons' => $reasons,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'residualCheckedAfterRtrim' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-current-source-nexttwoOneOne',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, NOCASE LIKE prefix ranges, RTRIM expression keys, and current-source cursor diagnostics',
            'non_overlap' => 'nextTwoOneOne audits source-refresh rowset changes after UTF-16 decode, RTRIM keys, NOCASE range matching, and LIKE residuals; it avoids accepted BOM normalization, ESCAPE rebind, Unicode GLOB, malformed insert guard, and nextTwoZeroNine coverage',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $like
     * @return array{decoded:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function v211_scan(array $rows, array $like, string $pattern, ?string $escape): array
    {
        $decoded = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            self::v211_assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
                $rtrim = rtrim($text, ' ');
                $decoded[] = [
                    'rowid' => $row['option_id'],
                    'text' => $text,
                    'rtrimText' => $rtrim,
                    'nocaseKey' => self::v211_asciiLower($rtrim),
                    'encodingName' => self::v211_encodingName($row['text_encoding']),
                    'bytesHex' => bin2hex($row['option_name_bytes']),
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['option_id'];
                $errors[$row['option_id']] = $exception->getMessage();
            }
        }

        usort($decoded, self::v211_sortRows(...));
        sort($malformed);
        ksort($errors);

        $candidates = [];
        $matched = [];
        $falsePositive = [];
        foreach ($decoded as $entry) {
            if (!self::v211_inRange($entry['nocaseKey'], $like['range'] ?? null)) {
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
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row */
    private static function v211_assertRow(array $row): void
    {
        if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoOneOne rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoOneOne rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoOneOne rows require integer text_encoding');
        }
    }

    /** @param ?array{lowerInclusive:string,upperBound:?string} $range */
    private static function v211_inRange(string $key, ?array $range): bool
    {
        if ($range === null || strcmp($key, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0;
    }

    /** @param array{nocaseKey:string,rowid:int} $left @param array{nocaseKey:string,rowid:int} $right */
    private static function v211_sortRows(array $left, array $right): int
    {
        $comparison = strcmp($left['nocaseKey'], $right['nocaseKey']);

        return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
    }

    /** @param list<array{rowid:int}> $rows @return list<int> */
    private static function v211_rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function v211_sortedDiff(array $left, array $right): array
    {
        $diff = array_values(array_diff($left, $right));
        sort($diff);

        return $diff;
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function v211_sortedIntersect(array $left, array $right): array
    {
        $intersect = array_values(array_intersect($left, $right));
        sort($intersect);

        return $intersect;
    }

    /** @param list<array<string,mixed>> $rows @return array<int,mixed> */
    private static function v211_mapByRowid(array $rows, string $key): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[$row['rowid']] = $row[$key];
        }

        ksort($mapped);

        return $mapped;
    }

    /** @param array<int,string> $values @param list<int> $rowids @return array<int,string> */
    private static function v211_selectMap(array $values, array $rowids): array
    {
        $selected = [];
        foreach ($rowids as $rowid) {
            if (array_key_exists($rowid, $values)) {
                $selected[$rowid] = $values[$rowid];
            }
        }

        return $selected;
    }

    /** @param list<array<string,mixed>> $current @param list<array<string,mixed>> $next @return list<int> */
    private static function v211_byteOrderOnlyRowids(array $current, array $next): array
    {
        $currentByRowid = self::v211_byRowid($current);
        $rowids = [];
        foreach (self::v211_byRowid($next) as $rowid => $entry) {
            if (!isset($currentByRowid[$rowid])) {
                continue;
            }
            if ($currentByRowid[$rowid]['rtrimText'] === $entry['rtrimText']
                && $currentByRowid[$rowid]['encodingName'] !== $entry['encodingName']) {
                $rowids[] = $rowid;
            }
        }
        sort($rowids);

        return $rowids;
    }

    /** @param list<array<string,mixed>> $current @param list<array<string,mixed>> $next @return list<int> */
    private static function v211_changedRowids(array $current, array $next, string $key): array
    {
        $currentByRowid = self::v211_byRowid($current);
        $rowids = [];
        foreach (self::v211_byRowid($next) as $rowid => $entry) {
            if (isset($currentByRowid[$rowid]) && $currentByRowid[$rowid][$key] !== $entry[$key]) {
                $rowids[] = $rowid;
            }
        }
        sort($rowids);

        return $rowids;
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function v211_byRowid(array $rows): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[$row['rowid']] = $row;
        }

        return $mapped;
    }

    private static function v211_encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoOneOne encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function v211_asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyUnicodeEscapePlan(
        array $currentRows,
        array $nextRows,
        string $currentPatternBytes,
        int|string $currentPatternEncoding,
        string $nextPatternBytes,
        int|string $nextPatternEncoding,
        string $currentEscapeBytes,
        int|string $currentEscapeEncoding,
        string $nextEscapeBytes,
        int|string $nextEscapeEncoding,
        string $currentSource = 'main.app_settings@211',
        string $nextSource = 'main.app_settings@212',
        int $currentSchemaCookie = 211,
        int $nextSchemaCookie = 212,
    ): array {
        $currentPattern = self::v212_decodePreparedText($currentPatternBytes, $currentPatternEncoding, 'current pattern');
        $nextPattern = self::v212_decodePreparedText($nextPatternBytes, $nextPatternEncoding, 'next pattern');
        $currentEscape = self::v212_decodePreparedText($currentEscapeBytes, $currentEscapeEncoding, 'current escape');
        $nextEscape = self::v212_decodePreparedText($nextEscapeBytes, $nextEscapeEncoding, 'next escape');

        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyEscapeRebindPlan(
            $currentRows,
            $nextRows,
            $currentPattern,
            $currentEscape,
            $nextPattern,
            $nextEscape,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $currentAsciiEscape = self::v212_replaceEscapeCharacter($currentPattern, $currentEscape, '!');
        $nextAsciiEscape = self::v212_replaceEscapeCharacter($nextPattern, $nextEscape, '!');
        $currentAscii = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyEscapeRebindPlan(
            $currentRows,
            $currentRows,
            $currentAsciiEscape,
            '!',
            $currentAsciiEscape,
            '!',
            $currentSource . '#ascii-escape',
            $currentSource . '#ascii-escape',
            $currentSchemaCookie,
            $currentSchemaCookie,
        );
        $nextAscii = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyEscapeRebindPlan(
            $nextRows,
            $nextRows,
            $nextAsciiEscape,
            '!',
            $nextAsciiEscape,
            '!',
            $nextSource . '#ascii-escape',
            $nextSource . '#ascii-escape',
            $nextSchemaCookie,
            $nextSchemaCookie,
        );

        $unicodeEscape = self::v212_sqliteTextLength($currentEscape) === 1
            && self::v212_sqliteTextLength($nextEscape) === 1
            && (!self::v212_isAscii($currentEscape) || !self::v212_isAscii($nextEscape));
        $normalizedCurrentEquivalent = $base['currentMatchedRowids'] === $currentAscii['currentMatchedRowids']
            && $base['currentCandidateRowids'] === $currentAscii['currentCandidateRowids'];
        $normalizedNextEquivalent = $base['nextMatchedRowids'] === $nextAscii['currentMatchedRowids']
            && $base['nextCandidateRowids'] === $nextAscii['currentCandidateRowids'];

        $reasons = $base['invalidationReasons'];
        if ($unicodeEscape) {
            $reasons[] = 'unicode-escape-character';
        }
        if (!$normalizedCurrentEquivalent || !$normalizedNextEquivalent) {
            $reasons[] = 'unicode-escape-normalization-mismatch';
        }
        if ($currentPattern !== $nextPattern) {
            $reasons[] = 'decoded-pattern';
        }
        if ($currentEscape !== $nextEscape) {
            $reasons[] = 'decoded-escape';
        }
        $reasons = array_values(array_unique($reasons));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nexttwoOneTwo',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* UTF-16 Unicode ESCAPE */',
            'currentPattern' => $currentPattern,
            'nextPattern' => $nextPattern,
            'currentEscape' => $currentEscape,
            'nextEscape' => $nextEscape,
            'currentPatternEncoding' => self::v212_encodingName($currentPatternEncoding),
            'nextPatternEncoding' => self::v212_encodingName($nextPatternEncoding),
            'currentEscapeEncoding' => self::v212_encodingName($currentEscapeEncoding),
            'nextEscapeEncoding' => self::v212_encodingName($nextEscapeEncoding),
            'currentPatternBytesHex' => bin2hex($currentPatternBytes),
            'nextPatternBytesHex' => bin2hex($nextPatternBytes),
            'currentEscapeBytesHex' => bin2hex($currentEscapeBytes),
            'nextEscapeBytesHex' => bin2hex($nextEscapeBytes),
            'unicodeEscapeCharacter' => $unicodeEscape,
            'currentEscapeTextLength' => self::v212_sqliteTextLength($currentEscape),
            'nextEscapeTextLength' => self::v212_sqliteTextLength($nextEscape),
            'currentAsciiEquivalentPattern' => $currentAsciiEscape,
            'nextAsciiEquivalentPattern' => $nextAsciiEscape,
            'currentSource' => $base['currentSource'],
            'nextSource' => $base['nextSource'],
            'currentSchemaCookie' => $base['currentSchemaCookie'],
            'nextSchemaCookie' => $base['nextSchemaCookie'],
            'prefix' => $base['currentPrefix'],
            'nextPrefix' => $base['nextPrefix'],
            'rangeLowerInclusive' => $base['currentRangeLowerInclusive'],
            'rangeUpperBound' => $base['currentRangeUpperBound'],
            'nextRangeLowerInclusive' => $base['nextRangeLowerInclusive'],
            'nextRangeUpperBound' => $base['nextRangeUpperBound'],
            'currentIndexUsable' => $base['currentIndexUsable'],
            'nextIndexUsable' => $base['nextIndexUsable'],
            'currentCandidateRowids' => $base['currentCandidateRowids'],
            'nextCandidateRowids' => $base['nextCandidateRowids'],
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'currentAsciiEquivalentMatchedRowids' => $currentAscii['currentMatchedRowids'],
            'nextAsciiEquivalentMatchedRowids' => $nextAscii['currentMatchedRowids'],
            'currentAsciiEquivalentCandidateRowids' => $currentAscii['currentCandidateRowids'],
            'nextAsciiEquivalentCandidateRowids' => $nextAscii['currentCandidateRowids'],
            'unicodeEscapeNormalizedCurrentEquivalent' => $normalizedCurrentEquivalent,
            'unicodeEscapeNormalizedNextEquivalent' => $normalizedNextEquivalent,
            'matchedExitedRowids' => $base['matchedExitedRowids'],
            'matchedEnteredRowids' => $base['matchedEnteredRowids'],
            'currentFalsePositiveRowids' => $base['currentFalsePositiveRowids'],
            'nextFalsePositiveRowids' => $base['nextFalsePositiveRowids'],
            'currentExcludedDecodedRowids' => $base['currentExcludedDecodedRowids'],
            'nextExcludedDecodedRowids' => $base['nextExcludedDecodedRowids'],
            'currentRtrimTexts' => $base['currentRtrimTexts'],
            'nextRtrimTexts' => $base['nextRtrimTexts'],
            'currentNocaseKeys' => $base['currentNocaseKeys'],
            'nextNocaseKeys' => $base['nextNocaseKeys'],
            'currentMatchedTexts' => $base['currentMatchedTexts'],
            'nextMatchedTexts' => $base['nextMatchedTexts'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'currentErrors' => $base['currentErrors'],
            'nextErrors' => $base['nextErrors'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'mustNormalizeEscapeBeforePrefixPlanning' => true,
            'mustReprepareForUnicodeEscapeChange' => $currentEscape !== $nextEscape || $currentPattern !== $nextPattern,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-prepared-like-unicode-escape',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-current-source-nexttwoOneTwo',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, SQLite LIKE ESCAPE character splitting, ASCII NOCASE prefix planning, RTRIM expression keys, and residual matching',
            'non_overlap' => 'nextTwoOneTwo covers UTF-16 prepared non-ASCII single-character ESCAPE normalization before NOCASE/RTRIM LIKE prefix planning; avoids accepted ASCII escape rebind nextTwoZeroZero, BOM nextTwoZeroSix, no-prefix nextTwoZeroThree, ASCII-space RTRIM nextTwoZeroNine, Unicode GLOB, and malformed UTF-16 insert guards',
        ];
    }

    private static function v212_decodePreparedText(string $bytes, int|string $encoding, string $label): string
    {
        try {
            return SQLiteEncodingCollationSourceCursor::decodeText($bytes, self::v212_encodingId($encoding));
        } catch (\InvalidArgumentException $exception) {
            throw new \InvalidArgumentException("SQLite UTF-16 NOCASE LIKE RTRIM nextTwoOneTwo prepared {$label} is malformed: " . $exception->getMessage());
        }
    }

    private static function v212_replaceEscapeCharacter(string $pattern, string $escape, string $replacement): string
    {
        return implode('', array_map(
            static fn (string $character): string => $character === $escape ? $replacement : $character,
            self::v212_characters($pattern),
        ));
    }

    /** @return list<string> */
    private static function v212_characters(string $value): array
    {
        if ($value === '') {
            return [];
        }
        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);

        return is_array($characters) ? array_values($characters) : str_split($value);
    }

    private static function v212_sqliteTextLength(string $value): int
    {
        return count(self::v212_characters($value));
    }

    private static function v212_isAscii(string $value): bool
    {
        return preg_match('/^[\x00-\x7f]*$/', $value) === 1;
    }

    private static function v212_encodingId(int|string $encoding): int
    {
        return match ($encoding) {
            1, 'UTF-8' => 1,
            2, 'UTF-16LE' => 2,
            3, 'UTF-16BE' => 3,
            default => throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoOneTwo encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function v212_encodingName(int|string $encoding): string
    {
        return match (self::v212_encodingId($encoding)) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
        };
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeySelfEscapedEscapePlan(
        array $currentRows,
        array $nextRows,
        string $currentPatternBytes,
        int|string $currentPatternEncoding,
        string $nextPatternBytes,
        int|string $nextPatternEncoding,
        string $escapeBytes,
        int|string $escapeEncoding,
        string $currentSource = 'main.app_settings@212',
        string $nextSource = 'main.app_settings@213',
        int $currentSchemaCookie = 212,
        int $nextSchemaCookie = 213,
    ): array {
        $escape = self::decodePreparedEscapeText($escapeBytes, $escapeEncoding, 'escape');
        if (self::sqliteLikeTextLength($escape) !== 1) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoOneThree ESCAPE must decode to one SQLite text character');
        }

        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyUnicodeEscapePlan(
            $currentRows,
            $nextRows,
            $currentPatternBytes,
            $currentPatternEncoding,
            $nextPatternBytes,
            $nextPatternEncoding,
            $escapeBytes,
            $escapeEncoding,
            $escapeBytes,
            $escapeEncoding,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $currentTokens = self::likeEscapeTokens($base['currentPattern'], $escape);
        $nextTokens = self::likeEscapeTokens($base['nextPattern'], $escape);
        $currentEscapedEscapeOffsets = self::likeEscapeEscapedEscapeOffsets($currentTokens, $escape);
        $nextEscapedEscapeOffsets = self::likeEscapeEscapedEscapeOffsets($nextTokens, $escape);
        $currentEscapedWildcardOffsets = self::likeEscapeEscapedWildcardOffsets($currentTokens);
        $nextEscapedWildcardOffsets = self::likeEscapeEscapedWildcardOffsets($nextTokens);
        $currentFirstWildcardOffset = self::likeEscapeFirstWildcardOffset($currentTokens);
        $nextFirstWildcardOffset = self::likeEscapeFirstWildcardOffset($nextTokens);

        $reasons = $base['invalidationReasons'];
        if ($currentEscapedEscapeOffsets !== $nextEscapedEscapeOffsets) {
            $reasons[] = 'escaped-escape-prefix';
        }
        if ($currentEscapedWildcardOffsets !== $nextEscapedWildcardOffsets) {
            $reasons[] = 'escaped-wildcard-prefix';
        }
        $reasons = array_values(array_unique($reasons));

        return array_replace($base, [
            'status' => 'utf16-nocase-like-rtrim-current-source-nexttwoOneThree',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* UTF-16 self-escaped Unicode ESCAPE */',
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'selfEscapedEscapeCharacter' => true,
            'escape' => $escape,
            'escapeEncoding' => self::preparedEscapeEncodingName($escapeEncoding),
            'escapeBytesHex' => bin2hex($escapeBytes),
            'currentTokens' => $currentTokens,
            'nextTokens' => $nextTokens,
            'currentEscapedEscapeOffsets' => $currentEscapedEscapeOffsets,
            'nextEscapedEscapeOffsets' => $nextEscapedEscapeOffsets,
            'currentEscapedWildcardOffsets' => $currentEscapedWildcardOffsets,
            'nextEscapedWildcardOffsets' => $nextEscapedWildcardOffsets,
            'currentFirstWildcardOffset' => $currentFirstWildcardOffset,
            'nextFirstWildcardOffset' => $nextFirstWildcardOffset,
            'currentPrefixCharacters' => self::sqliteLikeTextLength($base['prefix']),
            'nextPrefixCharacters' => self::sqliteLikeTextLength($base['nextPrefix']),
            'currentPrefixContainsEscapeLiteral' => str_contains($base['prefix'], $escape),
            'nextPrefixContainsEscapeLiteral' => str_contains($base['nextPrefix'], $escape),
            'currentPrefixContainsEscapedWildcardLiteral' => str_contains($base['prefix'], '_') || str_contains($base['prefix'], '%'),
            'nextPrefixContainsEscapedWildcardLiteral' => str_contains($base['nextPrefix'], '_') || str_contains($base['nextPrefix'], '%'),
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'mustDecodeEscapeBeforeSelfEscapePlanning' => true,
            'mustKeepEscapedEscapeInPrefix' => true,
            'mustKeepEscapedWildcardInPrefix' => true,
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-prepared-like-self-escaped-unicode-escape',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-current-source-nexttwoOneThree',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, LIKE ESCAPE tokenization, ASCII NOCASE prefix ranges, RTRIM expression keys, and current-source cursor invalidation diagnostics',
            'non_overlap' => 'nextTwoOneThree covers UTF-16 prepared non-ASCII ESCAPE characters that escape themselves before escaped wildcard literals; avoids nextTwoOneTwo single Unicode ESCAPE normalization, accepted Unicode GLOB ranges, malformed UTF-16 insert guards, and storage/planner clusters',
        ]);
    }

    /**
     * @return list<array{offset:int,character:string,escaped:bool,kind:string}>
     */
    private static function likeEscapeTokens(string $pattern, string $escape): array
    {
        $characters = self::likeEscapeCharacters($pattern);
        $tokens = [];
        $count = count($characters);
        for ($offset = 0; $offset < $count; $offset++) {
            $character = $characters[$offset];
            if ($character === $escape) {
                $offset++;
                if ($offset >= $count) {
                    $tokens[] = ['offset' => $offset - 1, 'character' => $escape, 'escaped' => false, 'kind' => 'dangling-escape'];
                    break;
                }
                $escaped = $characters[$offset];
                $tokens[] = ['offset' => $offset, 'character' => $escaped, 'escaped' => true, 'kind' => self::likeEscapeTokenKind($escaped, $escape)];
                continue;
            }

            $tokens[] = ['offset' => $offset, 'character' => $character, 'escaped' => false, 'kind' => self::likeEscapeTokenKind($character, $escape)];
        }

        return $tokens;
    }

    private static function likeEscapeTokenKind(string $character, string $escape): string
    {
        if ($character === $escape) {
            return 'escape-literal';
        }
        if ($character === '%' || $character === '_') {
            return 'wildcard';
        }

        return 'literal';
    }

    /** @param list<array{offset:int,character:string,escaped:bool,kind:string}> $tokens @return list<int> */
    private static function likeEscapeEscapedEscapeOffsets(array $tokens, string $escape): array
    {
        return self::likeEscapeTokenOffsets($tokens, static fn (array $token): bool => $token['escaped'] && $token['character'] === $escape);
    }

    /** @param list<array{offset:int,character:string,escaped:bool,kind:string}> $tokens @return list<int> */
    private static function likeEscapeEscapedWildcardOffsets(array $tokens): array
    {
        return self::likeEscapeTokenOffsets($tokens, static fn (array $token): bool => $token['escaped'] && ($token['character'] === '%' || $token['character'] === '_'));
    }

    /** @param list<array{offset:int,character:string,escaped:bool,kind:string}> $tokens */
    private static function likeEscapeFirstWildcardOffset(array $tokens): ?int
    {
        foreach ($tokens as $token) {
            if (!$token['escaped'] && ($token['character'] === '%' || $token['character'] === '_')) {
                return $token['offset'];
            }
        }

        return null;
    }

    /**
     * @param list<array{offset:int,character:string,escaped:bool,kind:string}> $tokens
     * @param callable(array{offset:int,character:string,escaped:bool,kind:string}):bool $filter
     * @return list<int>
     */
    private static function likeEscapeTokenOffsets(array $tokens, callable $filter): array
    {
        $offsets = [];
        foreach ($tokens as $token) {
            if ($filter($token)) {
                $offsets[] = $token['offset'];
            }
        }

        return $offsets;
    }

    private static function decodePreparedEscapeText(string $bytes, int|string $encoding, string $label): string
    {
        try {
            return SQLiteEncodingCollationSourceCursor::decodeText($bytes, self::preparedEscapeEncodingId($encoding));
        } catch (\InvalidArgumentException $exception) {
            throw new \InvalidArgumentException("SQLite UTF-16 NOCASE LIKE RTRIM nextTwoOneThree prepared {$label} is malformed: " . $exception->getMessage());
        }
    }

    /** @return list<string> */
    private static function likeEscapeCharacters(string $value): array
    {
        if ($value === '') {
            return [];
        }
        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);

        return is_array($characters) ? array_values($characters) : str_split($value);
    }

    private static function sqliteLikeTextLength(string $value): int
    {
        return count(self::likeEscapeCharacters($value));
    }

    private static function preparedEscapeEncodingId(int|string $encoding): int
    {
        return match ($encoding) {
            1, 'UTF-8' => 1,
            2, 'UTF-16LE' => 2,
            3, 'UTF-16BE' => 3,
            default => throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoOneThree encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function preparedEscapeEncodingName(int|string $encoding): string
    {
        return match (self::preparedEscapeEncodingId($encoding)) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
        };
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyPreparedPatternSpacePlan(
        array $currentRows,
        array $nextRows,
        string $currentPatternBytes,
        int|string $currentPatternEncoding,
        string $nextPatternBytes,
        int|string $nextPatternEncoding,
        ?string $escape = '!',
        string $currentSource = 'main.app_settings@216',
        string $nextSource = 'main.app_settings@217',
        int $currentSchemaCookie = 216,
        int $nextSchemaCookie = 217,
    ): array {
        $currentPattern = self::v217_decodePreparedPattern($currentPatternBytes, $currentPatternEncoding, 'current pattern');
        $nextPattern = self::v217_decodePreparedPattern($nextPatternBytes, $nextPatternEncoding, 'next pattern');
        $currentSpace = self::v217_spaceBeforeFirstWildcard($currentPattern, $escape);
        $nextSpace = self::v217_spaceBeforeFirstWildcard($nextPattern, $escape);

        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyEscapeRebindPlan(
            $currentRows,
            $nextRows,
            $currentPattern,
            $escape,
            $nextPattern,
            $escape,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $currentWithoutSpace = self::v217_removeSpaceBeforeFirstWildcard($currentPattern, $escape);
        $nextWithoutSpace = self::v217_removeSpaceBeforeFirstWildcard($nextPattern, $escape);
        $currentWithoutSpacePlan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyEscapeRebindPlan(
            $currentRows,
            $currentRows,
            $currentWithoutSpace,
            $escape,
            $currentWithoutSpace,
            $escape,
            $currentSource . '#without-pattern-space',
            $currentSource . '#without-pattern-space',
            $currentSchemaCookie,
            $currentSchemaCookie,
        );
        $nextWithoutSpacePlan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyEscapeRebindPlan(
            $nextRows,
            $nextRows,
            $nextWithoutSpace,
            $escape,
            $nextWithoutSpace,
            $escape,
            $nextSource . '#without-pattern-space',
            $nextSource . '#without-pattern-space',
            $nextSchemaCookie,
            $nextSchemaCookie,
        );

        $currentSpaceFiltered = self::v217_sortedDiff($currentWithoutSpacePlan['currentMatchedRowids'], $base['currentMatchedRowids']);
        $nextSpaceFiltered = self::v217_sortedDiff($nextWithoutSpacePlan['currentMatchedRowids'], $base['nextMatchedRowids']);
        $currentRtrimHadSpace = self::v217_rowsWithTrimmedAsciiSpace($base['currentRtrimTexts'], self::v217_decodeRows($currentRows));
        $nextRtrimHadSpace = self::v217_rowsWithTrimmedAsciiSpace($base['nextRtrimTexts'], self::v217_decodeRows($nextRows));

        $reasons = $base['invalidationReasons'];
        if ($currentSpace['spaceCount'] !== $nextSpace['spaceCount']) {
            $reasons[] = 'prepared-pattern-space-count';
        }
        if ($currentSpaceFiltered !== $nextSpaceFiltered) {
            $reasons[] = 'prepared-pattern-space-rowset';
        }
        $reasons = array_values(array_unique($reasons));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nexttwoOneSeven',
            'baseStatus' => $base['status'],
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* prepared UTF-16 pattern space */',
            'currentPattern' => $currentPattern,
            'nextPattern' => $nextPattern,
            'currentPatternEncoding' => self::v217_encodingName($currentPatternEncoding),
            'nextPatternEncoding' => self::v217_encodingName($nextPatternEncoding),
            'currentPatternBytesHex' => bin2hex($currentPatternBytes),
            'nextPatternBytesHex' => bin2hex($nextPatternBytes),
            'escape' => $escape,
            'currentSource' => $base['currentSource'],
            'nextSource' => $base['nextSource'],
            'currentSchemaCookie' => $base['currentSchemaCookie'],
            'nextSchemaCookie' => $base['nextSchemaCookie'],
            'currentSpaceBeforeWildcardCount' => $currentSpace['spaceCount'],
            'nextSpaceBeforeWildcardCount' => $nextSpace['spaceCount'],
            'currentSpaceBeforeWildcardOffset' => $currentSpace['offset'],
            'nextSpaceBeforeWildcardOffset' => $nextSpace['offset'],
            'currentPatternWithoutSpaceBeforeWildcard' => $currentWithoutSpace,
            'nextPatternWithoutSpaceBeforeWildcard' => $nextWithoutSpace,
            'currentPrefix' => $base['currentPrefix'],
            'nextPrefix' => $base['nextPrefix'],
            'currentRangeLowerInclusive' => $base['currentRangeLowerInclusive'],
            'currentRangeUpperBound' => $base['currentRangeUpperBound'],
            'nextRangeLowerInclusive' => $base['nextRangeLowerInclusive'],
            'nextRangeUpperBound' => $base['nextRangeUpperBound'],
            'currentIndexUsable' => $base['currentIndexUsable'],
            'nextIndexUsable' => $base['nextIndexUsable'],
            'currentCandidateRowids' => $base['currentCandidateRowids'],
            'nextCandidateRowids' => $base['nextCandidateRowids'],
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'currentMatchedWithoutPatternSpaceRowids' => $currentWithoutSpacePlan['currentMatchedRowids'],
            'nextMatchedWithoutPatternSpaceRowids' => $nextWithoutSpacePlan['currentMatchedRowids'],
            'currentPatternSpaceFilteredRowids' => $currentSpaceFiltered,
            'nextPatternSpaceFilteredRowids' => $nextSpaceFiltered,
            'matchedExitedRowids' => $base['matchedExitedRowids'],
            'matchedEnteredRowids' => $base['matchedEnteredRowids'],
            'currentFalsePositiveRowids' => $base['currentFalsePositiveRowids'],
            'nextFalsePositiveRowids' => $base['nextFalsePositiveRowids'],
            'currentExcludedDecodedRowids' => $base['currentExcludedDecodedRowids'],
            'nextExcludedDecodedRowids' => $base['nextExcludedDecodedRowids'],
            'currentRtrimTexts' => $base['currentRtrimTexts'],
            'nextRtrimTexts' => $base['nextRtrimTexts'],
            'currentNocaseKeys' => $base['currentNocaseKeys'],
            'nextNocaseKeys' => $base['nextNocaseKeys'],
            'currentMatchedTexts' => $base['currentMatchedTexts'],
            'nextMatchedTexts' => $base['nextMatchedTexts'],
            'currentRowsWithTrimmedAsciiSpace' => $currentRtrimHadSpace,
            'nextRowsWithTrimmedAsciiSpace' => $nextRtrimHadSpace,
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'currentErrors' => $base['currentErrors'],
            'nextErrors' => $base['nextErrors'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'mustDecodePatternBeforePrefixPlanning' => true,
            'preparedPatternSpacesRemainSignificant' => true,
            'leftRtrimDoesNotTrimPattern' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-prepared-like-pattern-decode',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-current-source-nexttwoOneSeven',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, prepared LIKE pattern text, ASCII NOCASE prefix planning, RTRIM expression keys, and residual matching',
            'non_overlap' => 'nextTwoOneSeven covers decoded UTF-16 prepared LIKE pattern spaces before the first wildcard remaining significant while rtrim(option_name) trims only the left expression; avoids accepted embedded-NUL nextTwoOneZero, Unicode ESCAPE nextTwoOneTwo, source refresh nextTwoOneOne, ASCII-space row RTRIM nextTwoZeroNine, Unicode GLOB, and malformed UTF-16 insert guards',
        ];
    }

    private static function v217_decodePreparedPattern(string $bytes, int|string $encoding, string $label): string
    {
        try {
            return SQLiteEncodingCollationSourceCursor::decodeText($bytes, self::v217_encodingId($encoding));
        } catch (\InvalidArgumentException $exception) {
            throw new \InvalidArgumentException("SQLite UTF-16 NOCASE LIKE RTRIM nextTwoOneSeven prepared {$label} is malformed: " . $exception->getMessage());
        }
    }

    /** @return array{spaceCount:int,offset:?int} */
    private static function v217_spaceBeforeFirstWildcard(string $pattern, ?string $escape): array
    {
        $chars = self::v217_characters($pattern);
        $escaped = false;
        $firstWildcard = count($chars);
        foreach ($chars as $offset => $char) {
            if ($escape !== null && $char === $escape && !$escaped) {
                $escaped = true;
                continue;
            }
            if (!$escaped && ($char === '%' || $char === '_')) {
                $firstWildcard = $offset;
                break;
            }
            $escaped = false;
        }

        $count = 0;
        for ($i = $firstWildcard - 1; $i >= 0 && ($chars[$i] ?? null) === ' '; $i--) {
            $count++;
        }

        return [
            'spaceCount' => $count,
            'offset' => $count > 0 ? $firstWildcard - $count : null,
        ];
    }

    private static function v217_removeSpaceBeforeFirstWildcard(string $pattern, ?string $escape): string
    {
        $space = self::v217_spaceBeforeFirstWildcard($pattern, $escape);
        if ($space['spaceCount'] === 0 || $space['offset'] === null) {
            return $pattern;
        }

        $chars = self::v217_characters($pattern);
        array_splice($chars, $space['offset'], $space['spaceCount']);

        return implode('', $chars);
    }

    /**
     * @param array<int,string> $rtrimTexts
     * @param array<int,string> $decodedTexts
     * @return list<int>
     */
    private static function v217_rowsWithTrimmedAsciiSpace(array $rtrimTexts, array $decodedTexts): array
    {
        $rowids = [];
        foreach ($decodedTexts as $rowid => $text) {
            if (($rtrimTexts[$rowid] ?? null) !== $text) {
                $rowids[] = $rowid;
            }
        }
        sort($rowids);

        return $rowids;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int,string>
     */
    private static function v217_decodeRows(array $rows): array
    {
        $decoded = [];
        foreach ($rows as $row) {
            if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoOneSeven rows require integer option_id');
            }
            if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoOneSeven rows require option_name_bytes');
            }
            if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoOneSeven rows require integer text_encoding');
            }

            try {
                $decoded[$row['option_id']] = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
            } catch (\InvalidArgumentException) {
                continue;
            }
        }
        ksort($decoded);

        return $decoded;
    }

    /** @return list<string> */
    private static function v217_characters(string $value): array
    {
        if ($value === '') {
            return [];
        }
        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);

        return is_array($characters) ? array_values($characters) : str_split($value);
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function v217_sortedDiff(array $left, array $right): array
    {
        $diff = array_values(array_diff($left, $right));
        sort($diff);

        return $diff;
    }

    private static function v217_encodingId(int|string $encoding): int
    {
        return match ($encoding) {
            1, 'UTF-8' => 1,
            2, 'UTF-16LE' => 2,
            3, 'UTF-16BE' => 3,
            default => throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoOneSeven encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function v217_encodingName(int|string $encoding): string
    {
        return match (self::v217_encodingId($encoding)) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
        };
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array<string,mixed>|null $cursor
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyYieldPagePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_cache%',
        string $currentEscapeBytes = "!\0",
        int|string $currentEscapeEncoding = 'UTF-16LE',
        string $nextEscapeBytes = "!\0",
        int|string $nextEscapeEncoding = 'UTF-16LE',
        int $limit = 3,
        int $offset = 1,
        string $currentSource = 'main.app_settings@217',
        string $nextSource = 'main.app_settings@218',
        int $currentSchemaCookie = 217,
        int $nextSchemaCookie = 218,
        ?array $cursor = null,
    ): array {
        if ($limit < 1) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoOneEight LIMIT must be positive');
        }
        if ($offset < 0) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoOneEight OFFSET must be non-negative');
        }

        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPreparedEscapePlan(
            $currentRows,
            $nextRows,
            $pattern,
            $currentEscapeBytes,
            $currentEscapeEncoding,
            $nextEscapeBytes,
            $nextEscapeEncoding,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $currentOrdered = self::v218_orderedMatchedRows($base['currentMatchedRowids'], $base['currentRtrimTexts'], $base['currentNocaseKeys'], $base['currentMatchedTexts']);
        $nextOrdered = self::v218_orderedMatchedRows($base['nextMatchedRowids'], $base['nextRtrimTexts'], $base['nextNocaseKeys'], $base['nextMatchedTexts']);
        $currentPage = array_slice($currentOrdered, $offset, $limit);
        $nextPage = array_slice($nextOrdered, $offset, $limit);
        $currentBefore = array_slice($currentOrdered, 0, $offset);
        $nextBefore = array_slice($nextOrdered, 0, $offset);
        $currentAfter = array_slice($currentOrdered, $offset + $limit);
        $nextAfter = array_slice($nextOrdered, $offset + $limit);
        $currentToken = self::v218_pageToken($currentSource, $currentSchemaCookie, $pattern, $base['currentEscape'], $offset, $limit, $currentPage);

        if ($cursor !== null) {
            self::v218_assertCursor($cursor, $currentToken);
        }

        $pageExited = array_values(array_diff(self::v218_rowids($currentPage), self::v218_rowids($nextPage)));
        $pageEntered = array_values(array_diff(self::v218_rowids($nextPage), self::v218_rowids($currentPage)));
        $beforeChanged = self::v218_rowids($currentBefore) !== self::v218_rowids($nextBefore);
        $pageChanged = self::v218_rowids($currentPage) !== self::v218_rowids($nextPage);
        $afterChanged = self::v218_rowids($currentAfter) !== self::v218_rowids($nextAfter);
        $reasons = $base['invalidationReasons'];
        if ($beforeChanged) {
            $reasons[] = 'rows-before-limit-window';
        }
        if ($pageChanged) {
            $reasons[] = 'limit-window-rowset';
        }
        if ($afterChanged) {
            $reasons[] = 'rows-after-limit-window';
        }
        if ($base['currentEscape'] !== $base['nextEscape'] || $base['currentEscapeBytesHex'] !== $base['nextEscapeBytesHex']) {
            $reasons[] = 'yield-escape-fence';
        }
        $reasons = array_values(array_unique($reasons));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nexttwoOneEight',
            'baseStatus' => $base['status'],
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? ORDER BY rtrim(option_name) COLLATE NOCASE, rowid LIMIT ? OFFSET ?',
            'pattern' => $pattern,
            'currentEscape' => $base['currentEscape'],
            'nextEscape' => $base['nextEscape'],
            'currentEscapeBytesHex' => $base['currentEscapeBytesHex'],
            'nextEscapeBytesHex' => $base['nextEscapeBytesHex'],
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'collation' => $base['collation'],
            'limit' => $limit,
            'offset' => $offset,
            'currentPrefix' => $base['currentPrefix'],
            'nextPrefix' => $base['nextPrefix'],
            'currentRangeLowerInclusive' => $base['currentRangeLowerInclusive'],
            'nextRangeLowerInclusive' => $base['nextRangeLowerInclusive'],
            'currentRangeUpperBound' => $base['currentRangeUpperBound'],
            'nextRangeUpperBound' => $base['nextRangeUpperBound'],
            'currentIndexUsable' => $base['currentIndexUsable'],
            'nextIndexUsable' => $base['nextIndexUsable'],
            'currentCandidateRowids' => $base['currentCandidateRowids'],
            'nextCandidateRowids' => $base['nextCandidateRowids'],
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'currentOrderedRowids' => self::v218_rowids($currentOrdered),
            'nextOrderedRowids' => self::v218_rowids($nextOrdered),
            'currentBeforeWindowRowids' => self::v218_rowids($currentBefore),
            'nextBeforeWindowRowids' => self::v218_rowids($nextBefore),
            'currentPageRowids' => self::v218_rowids($currentPage),
            'nextPageRowids' => self::v218_rowids($nextPage),
            'currentAfterWindowRowids' => self::v218_rowids($currentAfter),
            'nextAfterWindowRowids' => self::v218_rowids($nextAfter),
            'pageRetainedRowids' => array_values(array_intersect(self::v218_rowids($currentPage), self::v218_rowids($nextPage))),
            'pageExitedRowids' => $pageExited,
            'pageEnteredRowids' => $pageEntered,
            'rowsBeforeWindowChanged' => $beforeChanged,
            'limitWindowChanged' => $pageChanged,
            'rowsAfterWindowChanged' => $afterChanged,
            'currentPageRows' => $currentPage,
            'nextPageRows' => $nextPage,
            'currentPageTail' => $currentPage === [] ? null : $currentPage[array_key_last($currentPage)],
            'nextPageTail' => $nextPage === [] ? null : $nextPage[array_key_last($nextPage)],
            'currentPageToken' => $currentToken,
            'nextPageToken' => self::v218_pageToken($nextSource, $nextSchemaCookie, $pattern, $base['nextEscape'], $offset, $limit, $nextPage),
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'staleYieldPageRisk' => $beforeChanged || $pageChanged || $base['cursorInvalidated'],
            'invalidationReasons' => $reasons,
            'baseInvalidationReasons' => $base['invalidationReasons'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'currentErrors' => $base['currentErrors'],
            'nextErrors' => $base['nextErrors'],
            'currentRtrimTexts' => $base['currentRtrimTexts'],
            'nextRtrimTexts' => $base['nextRtrimTexts'],
            'currentNocaseKeys' => $base['currentNocaseKeys'],
            'nextNocaseKeys' => $base['nextNocaseKeys'],
            'rtrimTrimsOnlyAsciiSpace' => $base['rtrimTrimsOnlyAsciiSpace'],
            'nocaseFoldsAsciiOnly' => $base['nocaseFoldsAsciiOnly'],
            'escapeDecodedBeforeRangePlanning' => $base['escapeDecodedBeforeRangePlanning'],
            'limitWindowAppliedAfterResidual' => true,
            'orderUsesRtrimNocaseKeyThenRowid' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-escape-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-nocase-limit-yield-window',
                'sqlite-current-source-nexttwoOneEight',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, prepared LIKE ESCAPE handling, RTRIM/NOCASE keys, residual matching, and current-source yield cursor diagnostics',
            'non_overlap' => 'nextTwoOneEight adds LIMIT/OFFSET yield-window fencing after UTF-16 NOCASE/RTRIM LIKE residual matching; avoids accepted nextTwoZeroEight prepared ESCAPE decode, nextTwoZeroThree no-prefix full scans, nextTwoZeroZero escape rebinding, nextOneEightFive/170 resume-token replay, Unicode GLOB ranges, and UTF-16 malformed insert guards',
        ];
    }

    /**
     * @param list<int> $rowids
     * @param array<int,string> $rtrimTexts
     * @param array<int,string> $nocaseKeys
     * @param array<int,string> $matchedTexts
     * @return list<array{rowid:int,rtrimText:string,nocaseKey:string,matchedText:string}>
     */
    private static function v218_orderedMatchedRows(array $rowids, array $rtrimTexts, array $nocaseKeys, array $matchedTexts): array
    {
        $rows = [];
        foreach ($rowids as $rowid) {
            if (!isset($rtrimTexts[$rowid], $nocaseKeys[$rowid], $matchedTexts[$rowid])) {
                continue;
            }
            $rows[] = [
                'rowid' => $rowid,
                'rtrimText' => $rtrimTexts[$rowid],
                'nocaseKey' => $nocaseKeys[$rowid],
                'matchedText' => $matchedTexts[$rowid],
            ];
        }
        usort($rows, static function (array $left, array $right): int {
            $comparison = strcmp($left['nocaseKey'], $right['nocaseKey']);

            return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
        });

        return $rows;
    }

    /** @param list<array{rowid:int}> $rows @return list<int> */
    private static function v218_rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $page */
    private static function v218_pageToken(string $source, int $schemaCookie, string $pattern, string $escape, int $offset, int $limit, array $page): array
    {
        $tail = $page === [] ? null : $page[array_key_last($page)];

        return [
            'source' => $source,
            'schemaCookie' => $schemaCookie,
            'patternHash' => substr(hash('sha256', $pattern), 0, 16),
            'escapeHash' => substr(hash('sha256', $escape), 0, 16),
            'offset' => $offset,
            'limit' => $limit,
            'pageRowids' => self::v218_rowids($page),
            'tailRowid' => is_array($tail) ? $tail['rowid'] : null,
            'tailKey' => is_array($tail) ? $tail['nocaseKey'] : null,
        ];
    }

    /** @param array<string,mixed> $cursor @param array<string,mixed> $token */
    private static function v218_assertCursor(array $cursor, array $token): void
    {
        foreach (['source', 'schemaCookie', 'patternHash', 'escapeHash', 'offset', 'limit', 'tailRowid', 'tailKey'] as $key) {
            if (($cursor[$key] ?? null) !== $token[$key]) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoOneEight cursor does not match current source yield page');
            }
        }
        if (($cursor['pageRowids'] ?? null) !== $token['pageRowids']) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoOneEight cursor does not match current source yield page');
        }
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeySupplementaryWildcardPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_cache_',
        ?string $escape = '!',
        string $currentSource = 'main.app_settings@218',
        string $nextSource = 'main.app_settings@219',
        int $currentSchemaCookie = 218,
        int $nextSchemaCookie = 219,
    ): array {
        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $current = self::supplementaryWildcardScan($currentRows, $pattern, $escape, $like['range']);
        $next = self::supplementaryWildcardScan($nextRows, $pattern, $escape, $like['range']);

        $currentCandidates = self::supplementaryWildcardRowids($current['candidates']);
        $nextCandidates = self::supplementaryWildcardRowids($next['candidates']);
        $currentMatched = self::supplementaryWildcardRowids($current['matched']);
        $nextMatched = self::supplementaryWildcardRowids($next['matched']);
        $changes = self::supplementaryWildcardChanges($current['decoded'], $next['decoded']);
        $changes['residualChangedRowids'] = self::supplementaryWildcardResidualChanges($current['candidates'], $next['candidates']);

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
            'supplementary-character' => $changes['supplementaryChangedRowids'],
            'utf16-code-units' => $changes['utf16CodeUnitChangedRowids'],
            'residual-result' => $changes['residualChangedRowids'],
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
        if ($current['codeUnitWildcardTrapRowids'] !== [] || $next['codeUnitWildcardTrapRowids'] !== []) {
            $reasons[] = 'utf16-code-unit-wildcard-trap';
        }

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nexttwoOneNine',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* supplementary wildcard */',
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
            'matchedRetainedRowids' => self::supplementaryWildcardSortedIntersect($currentMatched, $nextMatched),
            'matchedExitedRowids' => self::supplementaryWildcardSortedDiff($currentMatched, $nextMatched),
            'matchedEnteredRowids' => self::supplementaryWildcardSortedDiff($nextMatched, $currentMatched),
            'currentFalsePositiveRowids' => self::supplementaryWildcardRowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::supplementaryWildcardRowids($next['falsePositive']),
            'currentCodeUnitWildcardTrapRowids' => $current['codeUnitWildcardTrapRowids'],
            'nextCodeUnitWildcardTrapRowids' => $next['codeUnitWildcardTrapRowids'],
            'currentSupplementaryRowids' => $current['supplementaryRowids'],
            'nextSupplementaryRowids' => $next['supplementaryRowids'],
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'currentRtrimTexts' => self::supplementaryWildcardMap($current['decoded'], 'rtrimText'),
            'nextRtrimTexts' => self::supplementaryWildcardMap($next['decoded'], 'rtrimText'),
            'currentNocaseKeys' => self::supplementaryWildcardMap($current['decoded'], 'nocaseKey'),
            'nextNocaseKeys' => self::supplementaryWildcardMap($next['decoded'], 'nocaseKey'),
            'currentCharacterCounts' => self::supplementaryWildcardMap($current['decoded'], 'characterCount'),
            'nextCharacterCounts' => self::supplementaryWildcardMap($next['decoded'], 'characterCount'),
            'currentUtf16CodeUnitCounts' => self::supplementaryWildcardMap($current['decoded'], 'utf16CodeUnits'),
            'nextUtf16CodeUnitCounts' => self::supplementaryWildcardMap($next['decoded'], 'utf16CodeUnits'),
            'currentSupplementaryCounts' => self::supplementaryWildcardMap($current['decoded'], 'supplementaryCount'),
            'nextSupplementaryCounts' => self::supplementaryWildcardMap($next['decoded'], 'supplementaryCount'),
            'currentResidualMatches' => self::supplementaryWildcardMap($current['candidates'], 'residualMatch'),
            'nextResidualMatches' => self::supplementaryWildcardMap($next['candidates'], 'residualMatch'),
            'currentCodeUnitTrapMatches' => self::supplementaryWildcardMap($current['candidates'], 'codeUnitTrapMatch'),
            'nextCodeUnitTrapMatches' => self::supplementaryWildcardMap($next['candidates'], 'codeUnitTrapMatch'),
            'changedTextRowids' => $changes['textChangedRowids'],
            'changedRtrimRowids' => $changes['rtrimChangedRowids'],
            'changedNocaseKeyRowids' => $changes['nocaseKeyChangedRowids'],
            'changedSupplementaryRowids' => $changes['supplementaryChangedRowids'],
            'changedUtf16CodeUnitRowids' => $changes['utf16CodeUnitChangedRowids'],
            'changedResidualRowids' => $changes['residualChangedRowids'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'likeUnderscoreConsumesUnicodeCharacter' => true,
            'utf16SurrogatePairIsOneLikeCharacter' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'invalidationReasons' => array_values(array_unique($reasons)),
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-supplementary-plane-like-character',
                'sqlite-current-source-nexttwoOneNine',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE prefix planning, RTRIM expression keys, and binary-safe Unicode character splitting',
            'non_overlap' => 'nextTwoOneNine covers supplementary-plane UTF-16 decoded characters consumed by one LIKE underscore wildcard; avoids accepted embedded-NUL nextTwoOneZero, Unicode ESCAPE nextTwoOneTwo/213, source refresh nextTwoOneOne, pattern-space nextTwoOneSeven, Unicode GLOB ranges, and malformed UTF-16 insert guards',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{decoded:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,supplementaryRowids:list<int>,codeUnitWildcardTrapRowids:list<int>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function supplementaryWildcardScan(array $rows, string $pattern, ?string $escape, ?array $range): array
    {
        $decoded = [];
        $supplementary = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            self::supplementaryWildcardAssertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
                $rtrim = rtrim($text, ' ');
                $supplementaryCount = self::supplementaryWildcardSupplementaryCount($rtrim);
                if ($supplementaryCount > 0) {
                    $supplementary[] = $row['option_id'];
                }
                $decoded[] = [
                    'rowid' => $row['option_id'],
                    'text' => $text,
                    'rtrimText' => $rtrim,
                    'nocaseKey' => self::supplementaryWildcardAsciiLower($rtrim),
                    'characterCount' => self::supplementaryWildcardCharacterCount($rtrim),
                    'utf16CodeUnits' => self::supplementaryWildcardUtf16CodeUnits($rtrim),
                    'supplementaryCount' => $supplementaryCount,
                    'bytesHex' => bin2hex($row['option_name_bytes']),
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['option_id'];
                $errors[$row['option_id']] = $exception->getMessage();
            }
        }

        usort($decoded, self::supplementaryWildcardSortRows(...));
        sort($supplementary);
        sort($malformed);
        ksort($errors);

        $candidates = [];
        $matched = [];
        $falsePositive = [];
        $traps = [];
        foreach ($decoded as $entry) {
            if (!self::supplementaryWildcardInRange($entry['nocaseKey'], $range)) {
                continue;
            }
            $entry['residualMatch'] = SQLiteDatabase::likeMatches($entry['rtrimText'], $pattern, $escape, false);
            $entry['codeUnitTrapMatch'] = $entry['residualMatch'] && $entry['supplementaryCount'] > 0;
            $candidates[] = $entry;
            if ($entry['residualMatch']) {
                $matched[] = $entry;
            } else {
                $falsePositive[] = $entry;
            }
            if ($entry['residualMatch'] && $entry['codeUnitTrapMatch']) {
                $traps[] = $entry['rowid'];
            }
        }
        sort($traps);

        return [
            'decoded' => $decoded,
            'candidates' => $candidates,
            'matched' => $matched,
            'falsePositive' => $falsePositive,
            'supplementaryRowids' => $supplementary,
            'codeUnitWildcardTrapRowids' => $traps,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row */
    private static function supplementaryWildcardAssertRow(array $row): void
    {
        if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoOneNine rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoOneNine rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoOneNine rows require integer text_encoding');
        }
    }

    /** @param ?array{lowerInclusive:string,upperBound:?string} $range */
    private static function supplementaryWildcardInRange(string $key, ?array $range): bool
    {
        if ($range === null || strcmp($key, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0;
    }

    /** @param array{nocaseKey:string,rowid:int} $left @param array{nocaseKey:string,rowid:int} $right */
    private static function supplementaryWildcardSortRows(array $left, array $right): int
    {
        $comparison = strcmp($left['nocaseKey'], $right['nocaseKey']);

        return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
    }

    /** @param list<array{rowid:int}> $rows @return list<int> */
    private static function supplementaryWildcardRowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $rows @return array<int,mixed> */
    private static function supplementaryWildcardMap(array $rows, string $key): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[$row['rowid']] = $row[$key];
        }

        return $mapped;
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function supplementaryWildcardSortedDiff(array $left, array $right): array
    {
        $diff = array_values(array_diff($left, $right));
        sort($diff);

        return $diff;
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function supplementaryWildcardSortedIntersect(array $left, array $right): array
    {
        $intersect = array_values(array_intersect($left, $right));
        sort($intersect);

        return $intersect;
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array{textChangedRowids:list<int>,rtrimChangedRowids:list<int>,nocaseKeyChangedRowids:list<int>,supplementaryChangedRowids:list<int>,utf16CodeUnitChangedRowids:list<int>,residualChangedRowids:list<int>}
     */
    private static function supplementaryWildcardChanges(array $currentRows, array $nextRows): array
    {
        $current = self::supplementaryWildcardByRowid($currentRows);
        $result = [
            'textChangedRowids' => [],
            'rtrimChangedRowids' => [],
            'nocaseKeyChangedRowids' => [],
            'supplementaryChangedRowids' => [],
            'utf16CodeUnitChangedRowids' => [],
            'residualChangedRowids' => [],
        ];
        foreach (self::supplementaryWildcardByRowid($nextRows) as $rowid => $entry) {
            if (!isset($current[$rowid])) {
                continue;
            }
            foreach ([
                'textChangedRowids' => 'text',
                'rtrimChangedRowids' => 'rtrimText',
                'nocaseKeyChangedRowids' => 'nocaseKey',
                'supplementaryChangedRowids' => 'supplementaryCount',
                'utf16CodeUnitChangedRowids' => 'utf16CodeUnits',
            ] as $target => $key) {
                if ($current[$rowid][$key] !== $entry[$key]) {
                    $result[$target][] = $rowid;
                }
            }
        }
        foreach ($result as $key => $rowids) {
            sort($rowids);
            $result[$key] = $rowids;
        }

        return $result;
    }

    /** @param list<array<string,mixed>> $currentRows @param list<array<string,mixed>> $nextRows @return list<int> */
    private static function supplementaryWildcardResidualChanges(array $currentRows, array $nextRows): array
    {
        $current = self::supplementaryWildcardByRowid($currentRows);
        $rowids = [];
        foreach (self::supplementaryWildcardByRowid($nextRows) as $rowid => $entry) {
            if (isset($current[$rowid]) && ($current[$rowid]['residualMatch'] ?? null) !== ($entry['residualMatch'] ?? null)) {
                $rowids[] = $rowid;
            }
        }
        sort($rowids);

        return $rowids;
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function supplementaryWildcardByRowid(array $rows): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[$row['rowid']] = $row;
        }

        return $mapped;
    }

    private static function supplementaryWildcardCharacterCount(string $value): int
    {
        return count(self::supplementaryWildcardCharacters($value));
    }

    private static function supplementaryWildcardUtf16CodeUnits(string $value): int
    {
        $bytes = SQLiteEncodingCollationSourceCursor::encodeText($value, 2);

        return intdiv(strlen($bytes), 2);
    }

    private static function supplementaryWildcardSupplementaryCount(string $value): int
    {
        $count = 0;
        foreach (self::supplementaryWildcardCharacters($value) as $character) {
            if (self::supplementaryWildcardUtf16CodeUnits($character) === 2) {
                $count++;
            }
        }

        return $count;
    }

    /** @return list<string> */
    private static function supplementaryWildcardCharacters(string $value): array
    {
        if ($value === '') {
            return [];
        }
        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);

        return is_array($characters) ? array_values($characters) : str_split($value);
    }

    private static function supplementaryWildcardAsciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyPreparedByteSignaturePlan(
        array $currentRows,
        array $nextRows,
        string $currentPatternBytes,
        int|string $currentPatternEncoding,
        string $nextPatternBytes,
        int|string $nextPatternEncoding,
        string $currentEscapeBytes,
        int|string $currentEscapeEncoding,
        string $nextEscapeBytes,
        int|string $nextEscapeEncoding,
        string $currentSource = 'main.app_settings@220',
        string $nextSource = 'main.app_settings@221',
        int $currentSchemaCookie = 220,
        int $nextSchemaCookie = 221,
    ): array {
        $currentPattern = self::decodePreparedLikeText($currentPatternBytes, $currentPatternEncoding, 'current pattern');
        $nextPattern = self::decodePreparedLikeText($nextPatternBytes, $nextPatternEncoding, 'next pattern');
        $currentEscape = self::decodePreparedLikeText($currentEscapeBytes, $currentEscapeEncoding, 'current escape');
        $nextEscape = self::decodePreparedLikeText($nextEscapeBytes, $nextEscapeEncoding, 'next escape');

        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyEscapeRebindPlan(
            $currentRows,
            $nextRows,
            $currentPattern,
            $currentEscape,
            $nextPattern,
            $nextEscape,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $currentSignature = self::preparedLikeByteSignature($currentPatternBytes, $currentPatternEncoding, $currentEscapeBytes, $currentEscapeEncoding);
        $nextSignature = self::preparedLikeByteSignature($nextPatternBytes, $nextPatternEncoding, $nextEscapeBytes, $nextEscapeEncoding);
        $sameDecodedSql = $currentPattern === $nextPattern && $currentEscape === $nextEscape;
        $samePreparedBytes = $currentSignature === $nextSignature;

        $stableSourceReasons = array_values(array_diff($base['invalidationReasons'], ['source-name', 'schema-cookie']));
        $reasons = $base['invalidationReasons'];
        if (!$samePreparedBytes) {
            $reasons[] = 'prepared-byte-signature';
        }
        if ($sameDecodedSql && !$samePreparedBytes) {
            $reasons[] = 'decoded-sql-byte-signature';
        }
        if ($currentPatternEncoding !== $nextPatternEncoding || $currentEscapeEncoding !== $nextEscapeEncoding) {
            $reasons[] = 'prepared-encoding';
        }
        $reasons = array_values(array_unique($reasons));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nexttwoTwoOne',
            'baseStatus' => $base['status'],
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* prepared UTF-16 byte signature */',
            'currentPattern' => $currentPattern,
            'nextPattern' => $nextPattern,
            'currentEscape' => $currentEscape,
            'nextEscape' => $nextEscape,
            'sameDecodedSql' => $sameDecodedSql,
            'samePreparedBytes' => $samePreparedBytes,
            'currentPatternEncoding' => self::preparedTextEncodingName($currentPatternEncoding),
            'nextPatternEncoding' => self::preparedTextEncodingName($nextPatternEncoding),
            'currentEscapeEncoding' => self::preparedTextEncodingName($currentEscapeEncoding),
            'nextEscapeEncoding' => self::preparedTextEncodingName($nextEscapeEncoding),
            'currentPatternBytesHex' => bin2hex($currentPatternBytes),
            'nextPatternBytesHex' => bin2hex($nextPatternBytes),
            'currentEscapeBytesHex' => bin2hex($currentEscapeBytes),
            'nextEscapeBytesHex' => bin2hex($nextEscapeBytes),
            'currentPreparedSignature' => $currentSignature,
            'nextPreparedSignature' => $nextSignature,
            'currentSource' => $base['currentSource'],
            'nextSource' => $base['nextSource'],
            'currentSchemaCookie' => $base['currentSchemaCookie'],
            'nextSchemaCookie' => $base['nextSchemaCookie'],
            'prefix' => $base['currentPrefix'],
            'nextPrefix' => $base['nextPrefix'],
            'rangeLowerInclusive' => $base['currentRangeLowerInclusive'],
            'rangeUpperBound' => $base['currentRangeUpperBound'],
            'nextRangeLowerInclusive' => $base['nextRangeLowerInclusive'],
            'nextRangeUpperBound' => $base['nextRangeUpperBound'],
            'currentIndexUsable' => $base['currentIndexUsable'],
            'nextIndexUsable' => $base['nextIndexUsable'],
            'currentCandidateRowids' => $base['currentCandidateRowids'],
            'nextCandidateRowids' => $base['nextCandidateRowids'],
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'matchedExitedRowids' => $base['matchedExitedRowids'],
            'matchedEnteredRowids' => $base['matchedEnteredRowids'],
            'currentFalsePositiveRowids' => $base['currentFalsePositiveRowids'],
            'nextFalsePositiveRowids' => $base['nextFalsePositiveRowids'],
            'currentExcludedDecodedRowids' => $base['currentExcludedDecodedRowids'],
            'nextExcludedDecodedRowids' => $base['nextExcludedDecodedRowids'],
            'currentRtrimTexts' => $base['currentRtrimTexts'],
            'nextRtrimTexts' => $base['nextRtrimTexts'],
            'currentNocaseKeys' => $base['currentNocaseKeys'],
            'nextNocaseKeys' => $base['nextNocaseKeys'],
            'currentMatchedTexts' => $base['currentMatchedTexts'],
            'nextMatchedTexts' => $base['nextMatchedTexts'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'currentErrors' => $base['currentErrors'],
            'nextErrors' => $base['nextErrors'],
            'stableSourceInvalidationReasons' => $stableSourceReasons,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'mustDecodePatternBeforePrefixPlanning' => true,
            'mustReprepareForPreparedByteSignature' => !$samePreparedBytes,
            'decodedSqlCanStillShareRange' => $sameDecodedSql && $base['currentPrefix'] === $base['nextPrefix'],
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'invalidationReasons' => $reasons,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-prepared-like-byte-signature',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-current-source-nexttwoTwoOne',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, prepared LIKE byte metadata, ASCII NOCASE prefix planning, RTRIM expression keys, and residual matching',
            'non_overlap' => 'nextTwoTwoOne covers prepared UTF-16 pattern/escape byte-signature invalidation when decoded SQL text is stable; avoids accepted BOM normalization nextTwoZeroSix, Unicode ESCAPE nextTwoOneTwo, pattern-space nextTwoOneSeven, ASCII RTRIM nextTwoZeroNine, escaped literal nextOneNineFour/195, Unicode GLOB, and malformed UTF-16 insert guards',
        ];
    }

    private static function decodePreparedLikeText(string $bytes, int|string $encoding, string $label): string
    {
        try {
            return SQLiteEncodingCollationSourceCursor::decodeText($bytes, self::preparedTextEncodingId($encoding));
        } catch (\InvalidArgumentException $exception) {
            throw new \InvalidArgumentException("SQLite UTF-16 NOCASE LIKE RTRIM nextTwoTwoOne prepared {$label} is malformed: " . $exception->getMessage());
        }
    }

    /** @return array{patternEncoding:string,patternBytesHex:string,escapeEncoding:string,escapeBytesHex:string} */
    private static function preparedLikeByteSignature(
        string $patternBytes,
        int|string $patternEncoding,
        string $escapeBytes,
        int|string $escapeEncoding,
    ): array {
        return [
            'patternEncoding' => self::preparedTextEncodingName($patternEncoding),
            'patternBytesHex' => bin2hex($patternBytes),
            'escapeEncoding' => self::preparedTextEncodingName($escapeEncoding),
            'escapeBytesHex' => bin2hex($escapeBytes),
        ];
    }

    private static function preparedTextEncodingId(int|string $encoding): int
    {
        return match ($encoding) {
            1, 'UTF-8' => 1,
            2, 'UTF-16LE' => 2,
            3, 'UTF-16BE' => 3,
            default => throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoTwoOne encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function preparedTextEncodingName(int|string $encoding): string
    {
        return match (self::preparedTextEncodingId($encoding)) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
        };
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array<string,mixed>|null $cursor
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyDescYieldPagePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_cache%',
        string $currentEscapeBytes = "!\0",
        int|string $currentEscapeEncoding = 'UTF-16LE',
        string $nextEscapeBytes = "!\0",
        int|string $nextEscapeEncoding = 'UTF-16LE',
        int $limit = 3,
        int $offset = 1,
        string $currentSource = 'main.app_settings@222',
        string $nextSource = 'main.app_settings@223',
        int $currentSchemaCookie = 222,
        int $nextSchemaCookie = 223,
        ?array $cursor = null,
    ): array {
        if ($limit < 1) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoTwoThree LIMIT must be positive');
        }
        if ($offset < 0) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoTwoThree OFFSET must be non-negative');
        }

        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyYieldPagePlan(
            $currentRows,
            $nextRows,
            $pattern,
            $currentEscapeBytes,
            $currentEscapeEncoding,
            $nextEscapeBytes,
            $nextEscapeEncoding,
            max($limit + $offset, 1),
            0,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $currentOrdered = self::descendingRtrimNocaseRows($base['currentMatchedRowids'], $base['currentRtrimTexts'], $base['currentNocaseKeys']);
        $nextOrdered = self::descendingRtrimNocaseRows($base['nextMatchedRowids'], $base['nextRtrimTexts'], $base['nextNocaseKeys']);
        $currentPage = array_slice($currentOrdered, $offset, $limit);
        $nextPage = array_slice($nextOrdered, $offset, $limit);
        $currentBefore = array_slice($currentOrdered, 0, $offset);
        $nextBefore = array_slice($nextOrdered, 0, $offset);
        $currentAfter = array_slice($currentOrdered, $offset + $limit);
        $nextAfter = array_slice($nextOrdered, $offset + $limit);
        $currentToken = self::descendingYieldPageToken($currentSource, $currentSchemaCookie, $pattern, $base['currentEscape'], $offset, $limit, $currentPage);

        if ($cursor !== null) {
            self::assertDescendingYieldCursor($cursor, $currentToken);
        }

        $beforeChanged = self::orderedRowids($currentBefore) !== self::orderedRowids($nextBefore);
        $pageChanged = self::orderedRowids($currentPage) !== self::orderedRowids($nextPage);
        $afterChanged = self::orderedRowids($currentAfter) !== self::orderedRowids($nextAfter);
        $reasons = $base['baseInvalidationReasons'];
        if ($beforeChanged) {
            $reasons[] = 'desc-rows-before-limit-window';
        }
        if ($pageChanged) {
            $reasons[] = 'desc-limit-window-rowset';
        }
        if ($afterChanged) {
            $reasons[] = 'desc-rows-after-limit-window';
        }
        if ($base['currentEscape'] !== $base['nextEscape'] || $base['currentEscapeBytesHex'] !== $base['nextEscapeBytesHex']) {
            $reasons[] = 'desc-yield-escape-fence';
        }
        $reasons = array_values(array_unique($reasons));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nexttwoTwoThree',
            'baseStatus' => $base['status'],
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? ORDER BY rtrim(option_name) COLLATE NOCASE DESC, rowid DESC LIMIT ? OFFSET ?',
            'pattern' => $pattern,
            'currentEscape' => $base['currentEscape'],
            'nextEscape' => $base['nextEscape'],
            'currentEscapeBytesHex' => $base['currentEscapeBytesHex'],
            'nextEscapeBytesHex' => $base['nextEscapeBytesHex'],
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'collation' => $base['collation'],
            'limit' => $limit,
            'offset' => $offset,
            'currentPrefix' => $base['currentPrefix'],
            'nextPrefix' => $base['nextPrefix'],
            'currentRangeLowerInclusive' => $base['currentRangeLowerInclusive'],
            'nextRangeLowerInclusive' => $base['nextRangeLowerInclusive'],
            'currentRangeUpperBound' => $base['currentRangeUpperBound'],
            'nextRangeUpperBound' => $base['nextRangeUpperBound'],
            'currentIndexUsable' => $base['currentIndexUsable'],
            'nextIndexUsable' => $base['nextIndexUsable'],
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'currentDescOrderedRowids' => self::orderedRowids($currentOrdered),
            'nextDescOrderedRowids' => self::orderedRowids($nextOrdered),
            'currentBeforeWindowRowids' => self::orderedRowids($currentBefore),
            'nextBeforeWindowRowids' => self::orderedRowids($nextBefore),
            'currentPageRowids' => self::orderedRowids($currentPage),
            'nextPageRowids' => self::orderedRowids($nextPage),
            'currentAfterWindowRowids' => self::orderedRowids($currentAfter),
            'nextAfterWindowRowids' => self::orderedRowids($nextAfter),
            'pageRetainedRowids' => array_values(array_intersect(self::orderedRowids($currentPage), self::orderedRowids($nextPage))),
            'pageExitedRowids' => self::sortedRowidDiff(self::orderedRowids($currentPage), self::orderedRowids($nextPage)),
            'pageEnteredRowids' => self::sortedRowidDiff(self::orderedRowids($nextPage), self::orderedRowids($currentPage)),
            'rowsBeforeWindowChanged' => $beforeChanged,
            'limitWindowChanged' => $pageChanged,
            'rowsAfterWindowChanged' => $afterChanged,
            'currentPageRows' => $currentPage,
            'nextPageRows' => $nextPage,
            'currentPageHead' => $currentPage[0] ?? null,
            'nextPageHead' => $nextPage[0] ?? null,
            'currentPageTail' => $currentPage === [] ? null : $currentPage[array_key_last($currentPage)],
            'nextPageTail' => $nextPage === [] ? null : $nextPage[array_key_last($nextPage)],
            'currentPageToken' => $currentToken,
            'nextPageToken' => self::descendingYieldPageToken($nextSource, $nextSchemaCookie, $pattern, $base['nextEscape'], $offset, $limit, $nextPage),
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'staleDescYieldPageRisk' => $beforeChanged || $pageChanged || $base['cursorInvalidated'],
            'invalidationReasons' => $reasons,
            'baseInvalidationReasons' => $base['baseInvalidationReasons'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'currentErrors' => $base['currentErrors'],
            'nextErrors' => $base['nextErrors'],
            'currentRtrimTexts' => $base['currentRtrimTexts'],
            'nextRtrimTexts' => $base['nextRtrimTexts'],
            'currentNocaseKeys' => $base['currentNocaseKeys'],
            'nextNocaseKeys' => $base['nextNocaseKeys'],
            'rtrimTrimsOnlyAsciiSpace' => $base['rtrimTrimsOnlyAsciiSpace'],
            'nocaseFoldsAsciiOnly' => $base['nocaseFoldsAsciiOnly'],
            'escapeDecodedBeforeRangePlanning' => $base['escapeDecodedBeforeRangePlanning'],
            'descLimitWindowAppliedAfterResidual' => true,
            'descOrderUsesRtrimNocaseKeyThenRowid' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-escape-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-nocase-desc-limit-yield-window',
                'sqlite-current-source-nexttwoTwoThree',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, prepared LIKE ESCAPE handling, RTRIM/NOCASE keys, residual matching, and descending current-source yield cursor diagnostics',
            'non_overlap' => 'nextTwoTwoThree adds DESC ordered LIMIT/OFFSET yield-window fencing after UTF-16 NOCASE/RTRIM LIKE residual matching; avoids accepted nextTwoOneEight ascending yield pages, nextTwoZeroEight prepared ESCAPE decode, nextTwoZeroZero escape rebinding, Unicode GLOB ranges, and UTF-16 malformed insert guards',
        ];
    }

    /**
     * @param list<int> $rowids
     * @param array<int,string> $rtrimTexts
     * @param array<int,string> $nocaseKeys
     * @return list<array{rowid:int,rtrimText:string,nocaseKey:string,matchedText:string}>
     */
    private static function descendingRtrimNocaseRows(array $rowids, array $rtrimTexts, array $nocaseKeys): array
    {
        $rows = [];
        foreach ($rowids as $rowid) {
            if (!isset($rtrimTexts[$rowid], $nocaseKeys[$rowid])) {
                continue;
            }
            $rows[] = [
                'rowid' => $rowid,
                'rtrimText' => $rtrimTexts[$rowid],
                'nocaseKey' => $nocaseKeys[$rowid],
                'matchedText' => $rtrimTexts[$rowid],
            ];
        }
        usort($rows, static function (array $left, array $right): int {
            $comparison = strcmp($right['nocaseKey'], $left['nocaseKey']);

            return $comparison !== 0 ? $comparison : $right['rowid'] <=> $left['rowid'];
        });

        return $rows;
    }

    /** @param list<array{rowid:int}> $rows @return list<int> */
    private static function orderedRowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function sortedRowidDiff(array $left, array $right): array
    {
        $diff = array_values(array_diff($left, $right));
        sort($diff);

        return $diff;
    }

    /** @param list<array<string,mixed>> $page */
    private static function descendingYieldPageToken(string $source, int $schemaCookie, string $pattern, string $escape, int $offset, int $limit, array $page): array
    {
        $head = $page[0] ?? null;
        $tail = $page === [] ? null : $page[array_key_last($page)];

        return [
            'source' => $source,
            'schemaCookie' => $schemaCookie,
            'patternHash' => substr(hash('sha256', $pattern), 0, 16),
            'escapeHash' => substr(hash('sha256', $escape), 0, 16),
            'offset' => $offset,
            'limit' => $limit,
            'order' => 'DESC',
            'pageRowids' => self::orderedRowids($page),
            'headRowid' => is_array($head) ? $head['rowid'] : null,
            'headKey' => is_array($head) ? $head['nocaseKey'] : null,
            'tailRowid' => is_array($tail) ? $tail['rowid'] : null,
            'tailKey' => is_array($tail) ? $tail['nocaseKey'] : null,
        ];
    }

    /** @param array<string,mixed> $cursor @param array<string,mixed> $token */
    private static function assertDescendingYieldCursor(array $cursor, array $token): void
    {
        foreach (['source', 'schemaCookie', 'patternHash', 'escapeHash', 'offset', 'limit', 'order', 'headRowid', 'headKey', 'tailRowid', 'tailKey'] as $key) {
            if (($cursor[$key] ?? null) !== $token[$key]) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoTwoThree cursor does not match current source DESC yield page');
            }
        }
        if (($cursor['pageRowids'] ?? null) !== $token['pageRowids']) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoTwoThree cursor does not match current source DESC yield page');
        }
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array<string,mixed>|null $resumeToken
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyKeysetResumePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_cache%',
        string $currentEscapeBytes = "!\0",
        int|string $currentEscapeEncoding = 'UTF-16LE',
        string $nextEscapeBytes = "!\0",
        int|string $nextEscapeEncoding = 'UTF-16LE',
        int $pageSize = 3,
        int $lastRowid = 0,
        ?string $lastKey = null,
        string $currentSource = 'main.app_settings@223',
        string $nextSource = 'main.app_settings@224',
        int $currentSchemaCookie = 223,
        int $nextSchemaCookie = 224,
        ?array $resumeToken = null,
    ): array {
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoTwoFour page size must be positive');
        }

        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPreparedEscapePlan(
            $currentRows,
            $nextRows,
            $pattern,
            $currentEscapeBytes,
            $currentEscapeEncoding,
            $nextEscapeBytes,
            $nextEscapeEncoding,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $currentOrdered = self::v224_orderedMatchedRows($base['currentMatchedRowids'], $base['currentRtrimTexts'], $base['currentNocaseKeys'], $base['currentMatchedTexts']);
        $nextOrdered = self::v224_orderedMatchedRows($base['nextMatchedRowids'], $base['nextRtrimTexts'], $base['nextNocaseKeys'], $base['nextMatchedTexts']);
        if ($lastKey === null) {
            $tail = $currentOrdered === [] ? null : $currentOrdered[0];
            $lastKey = is_array($tail) ? $tail['nocaseKey'] : '';
            $lastRowid = is_array($tail) ? $tail['rowid'] : 0;
        }
        $currentBefore = self::v224_rowsAtOrBefore($currentOrdered, $lastKey, $lastRowid);
        $nextBefore = self::v224_rowsAtOrBefore($nextOrdered, $lastKey, $lastRowid);
        $currentRemaining = self::v224_rowsAfter($currentOrdered, $lastKey, $lastRowid);
        $nextRemaining = self::v224_rowsAfter($nextOrdered, $lastKey, $lastRowid);
        $currentPage = array_slice($currentRemaining, 0, $pageSize);
        $nextPage = array_slice($nextRemaining, 0, $pageSize);
        $currentToken = self::v224_resumeToken($currentSource, $currentSchemaCookie, $pattern, $base['currentEscape'], $lastKey, $lastRowid, $pageSize, $currentPage);

        if ($resumeToken !== null) {
            self::v224_assertResumeToken($resumeToken, $currentToken);
        }

        $reasons = $base['invalidationReasons'];
        if (self::v224_rowids($currentBefore) !== self::v224_rowids($nextBefore)) {
            $reasons[] = 'resume-prefix-rowset';
        }
        if (self::v224_rowids($currentPage) !== self::v224_rowids($nextPage)) {
            $reasons[] = 'resume-page-rowset';
        }
        if (self::v224_rowids($currentRemaining) !== self::v224_rowids($nextRemaining)) {
            $reasons[] = 'resume-tail-rowset';
        }
        if ($base['currentEscape'] !== $base['nextEscape'] || $base['currentEscapeBytesHex'] !== $base['nextEscapeBytesHex']) {
            $reasons[] = 'resume-escape-fence';
        }
        $reasons = array_values(array_unique($reasons));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nexttwoTwoFour',
            'baseStatus' => $base['status'],
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? AND (rtrim(option_name) COLLATE NOCASE, rowid) > (?, ?) ORDER BY rtrim(option_name) COLLATE NOCASE, rowid LIMIT ?',
            'pattern' => $pattern,
            'currentEscape' => $base['currentEscape'],
            'nextEscape' => $base['nextEscape'],
            'currentEscapeBytesHex' => $base['currentEscapeBytesHex'],
            'nextEscapeBytesHex' => $base['nextEscapeBytesHex'],
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'collation' => $base['collation'],
            'pageSize' => $pageSize,
            'lastKey' => $lastKey,
            'lastRowid' => $lastRowid,
            'currentPrefix' => $base['currentPrefix'],
            'nextPrefix' => $base['nextPrefix'],
            'currentRangeLowerInclusive' => $base['currentRangeLowerInclusive'],
            'nextRangeLowerInclusive' => $base['nextRangeLowerInclusive'],
            'currentRangeUpperBound' => $base['currentRangeUpperBound'],
            'nextRangeUpperBound' => $base['nextRangeUpperBound'],
            'currentIndexUsable' => $base['currentIndexUsable'],
            'nextIndexUsable' => $base['nextIndexUsable'],
            'currentCandidateRowids' => $base['currentCandidateRowids'],
            'nextCandidateRowids' => $base['nextCandidateRowids'],
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'currentOrderedRowids' => self::v224_rowids($currentOrdered),
            'nextOrderedRowids' => self::v224_rowids($nextOrdered),
            'currentRowsAtOrBeforeResume' => self::v224_rowids($currentBefore),
            'nextRowsAtOrBeforeResume' => self::v224_rowids($nextBefore),
            'currentRemainingRowids' => self::v224_rowids($currentRemaining),
            'nextRemainingRowids' => self::v224_rowids($nextRemaining),
            'currentResumePageRowids' => self::v224_rowids($currentPage),
            'nextResumePageRowids' => self::v224_rowids($nextPage),
            'resumePageRetainedRowids' => array_values(array_intersect(self::v224_rowids($currentPage), self::v224_rowids($nextPage))),
            'resumePageExitedRowids' => array_values(array_diff(self::v224_rowids($currentPage), self::v224_rowids($nextPage))),
            'resumePageEnteredRowids' => array_values(array_diff(self::v224_rowids($nextPage), self::v224_rowids($currentPage))),
            'currentResumePageRows' => $currentPage,
            'nextResumePageRows' => $nextPage,
            'currentResumeToken' => $currentToken,
            'nextResumeToken' => self::v224_resumeToken($nextSource, $nextSchemaCookie, $pattern, $base['nextEscape'], $lastKey, $lastRowid, $pageSize, $nextPage),
            'resumePrefixChanged' => self::v224_rowids($currentBefore) !== self::v224_rowids($nextBefore),
            'resumePageChanged' => self::v224_rowids($currentPage) !== self::v224_rowids($nextPage),
            'resumeTailChanged' => self::v224_rowids($currentRemaining) !== self::v224_rowids($nextRemaining),
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'staleKeysetResumeRisk' => $reasons !== [],
            'invalidationReasons' => $reasons,
            'baseInvalidationReasons' => $base['invalidationReasons'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'currentErrors' => $base['currentErrors'],
            'nextErrors' => $base['nextErrors'],
            'currentRtrimTexts' => $base['currentRtrimTexts'],
            'nextRtrimTexts' => $base['nextRtrimTexts'],
            'currentNocaseKeys' => $base['currentNocaseKeys'],
            'nextNocaseKeys' => $base['nextNocaseKeys'],
            'rtrimTrimsOnlyAsciiSpace' => $base['rtrimTrimsOnlyAsciiSpace'],
            'nocaseFoldsAsciiOnly' => $base['nocaseFoldsAsciiOnly'],
            'escapeDecodedBeforeRangePlanning' => $base['escapeDecodedBeforeRangePlanning'],
            'keysetResumeAppliedAfterResidual' => true,
            'orderUsesRtrimNocaseKeyThenRowid' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-escape-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-nocase-keyset-resume',
                'sqlite-current-source-nexttwoTwoFour',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, prepared LIKE ESCAPE handling, RTRIM/NOCASE keys, residual matching, and current-source keyset cursor diagnostics',
            'non_overlap' => 'nextTwoTwoFour adds keyset resume fencing for UTF-16 NOCASE/RTRIM LIKE cursors after a saved (rtrim-nocase-key,rowid) tail; avoids accepted nextTwoOneEight LIMIT/OFFSET yield-window, nextTwoZeroEight prepared ESCAPE decode, nextTwoZeroThree no-prefix full scans, nextTwoZeroZero escape rebinding, Unicode GLOB ranges, and UTF-16 malformed insert guards',
        ];
    }

    /**
     * @param list<int> $rowids
     * @param array<int,string> $rtrimTexts
     * @param array<int,string> $nocaseKeys
     * @param array<int,string> $matchedTexts
     * @return list<array{rowid:int,rtrimText:string,nocaseKey:string,matchedText:string}>
     */
    private static function v224_orderedMatchedRows(array $rowids, array $rtrimTexts, array $nocaseKeys, array $matchedTexts): array
    {
        $rows = [];
        foreach ($rowids as $rowid) {
            if (!isset($rtrimTexts[$rowid], $nocaseKeys[$rowid], $matchedTexts[$rowid])) {
                continue;
            }
            $rows[] = [
                'rowid' => $rowid,
                'rtrimText' => $rtrimTexts[$rowid],
                'nocaseKey' => $nocaseKeys[$rowid],
                'matchedText' => $matchedTexts[$rowid],
            ];
        }
        usort($rows, static function (array $left, array $right): int {
            $comparison = strcmp($left['nocaseKey'], $right['nocaseKey']);

            return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
        });

        return $rows;
    }

    /** @param list<array{rowid:int,nocaseKey:string}> $rows @return list<array{rowid:int,nocaseKey:string}> */
    private static function v224_rowsAtOrBefore(array $rows, string $lastKey, int $lastRowid): array
    {
        return array_values(array_filter($rows, static fn (array $row): bool => self::v224_compareKeyset($row, $lastKey, $lastRowid) <= 0));
    }

    /** @param list<array{rowid:int,nocaseKey:string}> $rows @return list<array{rowid:int,nocaseKey:string}> */
    private static function v224_rowsAfter(array $rows, string $lastKey, int $lastRowid): array
    {
        return array_values(array_filter($rows, static fn (array $row): bool => self::v224_compareKeyset($row, $lastKey, $lastRowid) > 0));
    }

    /** @param array{rowid:int,nocaseKey:string} $row */
    private static function v224_compareKeyset(array $row, string $lastKey, int $lastRowid): int
    {
        $key = strcmp($row['nocaseKey'], $lastKey);

        return $key !== 0 ? $key : $row['rowid'] <=> $lastRowid;
    }

    /** @param list<array{rowid:int}> $rows @return list<int> */
    private static function v224_rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $page */
    private static function v224_resumeToken(string $source, int $schemaCookie, string $pattern, string $escape, string $lastKey, int $lastRowid, int $pageSize, array $page): array
    {
        $tail = $page === [] ? null : $page[array_key_last($page)];

        return [
            'source' => $source,
            'schemaCookie' => $schemaCookie,
            'patternHash' => substr(hash('sha256', $pattern), 0, 16),
            'escapeHash' => substr(hash('sha256', $escape), 0, 16),
            'lastKeyHash' => substr(hash('sha256', $lastKey), 0, 16),
            'lastKey' => $lastKey,
            'lastRowid' => $lastRowid,
            'pageSize' => $pageSize,
            'pageRowids' => self::v224_rowids($page),
            'tailRowid' => is_array($tail) ? $tail['rowid'] : null,
            'tailKey' => is_array($tail) ? $tail['nocaseKey'] : null,
        ];
    }

    /** @param array<string,mixed> $cursor @param array<string,mixed> $token */
    private static function v224_assertResumeToken(array $cursor, array $token): void
    {
        foreach (['source', 'schemaCookie', 'patternHash', 'escapeHash', 'lastKeyHash', 'lastKey', 'lastRowid', 'pageSize', 'tailRowid', 'tailKey'] as $key) {
            if (($cursor[$key] ?? null) !== $token[$key]) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoTwoFour resume token does not match current source keyset page');
            }
        }
        if (($cursor['pageRowids'] ?? null) !== $token['pageRowids']) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoTwoFour resume token does not match current source keyset page');
        }
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeySourceBytePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_cache%',
        ?string $escape = '!',
        string $currentSource = 'main.app_settings@224',
        string $nextSource = 'main.app_settings@225',
        int $currentSchemaCookie = 224,
        int $nextSchemaCookie = 225,
    ): array {
        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeySupplementaryWildcardPlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escape,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $raw = self::v225_rawSourceChanges($currentRows, $nextRows);
        $reasons = $base['invalidationReasons'];
        if ($raw['encodingChangedRowids'] !== []) {
            $reasons[] = 'text-encoding';
        }
        if ($raw['sourceBytesChangedRowids'] !== []) {
            $reasons[] = 'source-bytes';
        }
        if ($raw['byteOrderChangedRowids'] !== []) {
            $reasons[] = 'utf16-byte-order';
        }
        $reasons = array_values(array_unique($reasons));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nexttwoTwoFive',
            'baseStatus' => $base['status'],
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* source-byte fence */',
            'pattern' => $base['pattern'],
            'escape' => $base['escape'],
            'collation' => $base['collation'],
            'currentSource' => $base['currentSource'],
            'nextSource' => $base['nextSource'],
            'currentSchemaCookie' => $base['currentSchemaCookie'],
            'nextSchemaCookie' => $base['nextSchemaCookie'],
            'prefix' => $base['prefix'],
            'rangeLowerInclusive' => $base['rangeLowerInclusive'],
            'rangeUpperBound' => $base['rangeUpperBound'],
            'indexUsable' => $base['indexUsable'],
            'currentCandidateRowids' => $base['currentCandidateRowids'],
            'nextCandidateRowids' => $base['nextCandidateRowids'],
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'matchedRetainedRowids' => $base['matchedRetainedRowids'],
            'matchedExitedRowids' => $base['matchedExitedRowids'],
            'matchedEnteredRowids' => $base['matchedEnteredRowids'],
            'currentFalsePositiveRowids' => $base['currentFalsePositiveRowids'],
            'nextFalsePositiveRowids' => $base['nextFalsePositiveRowids'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'currentErrors' => $base['currentErrors'],
            'nextErrors' => $base['nextErrors'],
            'currentRtrimTexts' => $base['currentRtrimTexts'],
            'nextRtrimTexts' => $base['nextRtrimTexts'],
            'currentNocaseKeys' => $base['currentNocaseKeys'],
            'nextNocaseKeys' => $base['nextNocaseKeys'],
            'currentTextEncodings' => $raw['currentTextEncodings'],
            'nextTextEncodings' => $raw['nextTextEncodings'],
            'currentSourceBytesHex' => $raw['currentSourceBytesHex'],
            'nextSourceBytesHex' => $raw['nextSourceBytesHex'],
            'currentByteOrders' => $raw['currentByteOrders'],
            'nextByteOrders' => $raw['nextByteOrders'],
            'changedEncodingRowids' => $raw['encodingChangedRowids'],
            'changedSourceByteRowids' => $raw['sourceBytesChangedRowids'],
            'changedByteOrderRowids' => $raw['byteOrderChangedRowids'],
            'stableDecodedChangedSourceRowids' => self::v225_stableDecodedChangedSourceRowids($raw, $base),
            'changedTextRowids' => $base['changedTextRowids'],
            'changedRtrimRowids' => $base['changedRtrimRowids'],
            'changedNocaseKeyRowids' => $base['changedNocaseKeyRowids'],
            'changedResidualRowids' => $base['changedResidualRowids'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'baseCursorReusable' => $base['cursorReusable'],
            'sourceByteFenceAppliedAfterDecode' => true,
            'decodedComparisonCanRemainStableAcrossEndianRewrite' => true,
            'rtrimTrimsOnlyAsciiSpace' => $base['rtrimTrimsOnlyAsciiSpace'],
            'nocaseFoldsAsciiOnly' => $base['nocaseFoldsAsciiOnly'],
            'invalidationReasons' => $reasons,
            'baseInvalidationReasons' => $base['invalidationReasons'],
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-current-source-byte-fence',
                'sqlite-current-source-nexttwoTwoFive',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE prefix planning, RTRIM expression keys, and current-source raw byte diagnostics',
            'non_overlap' => 'nextTwoTwoFive adds raw UTF-16 source-byte and endian-change cursor fencing when decoded NOCASE/RTRIM LIKE results remain stable; avoids accepted nextTwoOneNine supplementary wildcard matching, nextTwoOneSeven pattern-space handling, nextTwoOneThree Unicode ESCAPE, nextTwoOneZero embedded NUL, Unicode GLOB ranges, and malformed UTF-16 insert guards',
        ];
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    private static function v225_rawSourceChanges(array $currentRows, array $nextRows): array
    {
        $current = self::v225_rawRowsById($currentRows);
        $next = self::v225_rawRowsById($nextRows);
        $encodingChanged = [];
        $bytesChanged = [];
        $byteOrderChanged = [];
        foreach ($next as $rowid => $entry) {
            if (!isset($current[$rowid])) {
                continue;
            }
            if ($current[$rowid]['encoding'] !== $entry['encoding']) {
                $encodingChanged[] = $rowid;
            }
            if ($current[$rowid]['bytesHex'] !== $entry['bytesHex']) {
                $bytesChanged[] = $rowid;
            }
            if ($current[$rowid]['byteOrder'] !== $entry['byteOrder']) {
                $byteOrderChanged[] = $rowid;
            }
        }
        sort($encodingChanged);
        sort($bytesChanged);
        sort($byteOrderChanged);

        return [
            'currentTextEncodings' => self::v225_mapRaw($current, 'encodingName'),
            'nextTextEncodings' => self::v225_mapRaw($next, 'encodingName'),
            'currentSourceBytesHex' => self::v225_mapRaw($current, 'bytesHex'),
            'nextSourceBytesHex' => self::v225_mapRaw($next, 'bytesHex'),
            'currentByteOrders' => self::v225_mapRaw($current, 'byteOrder'),
            'nextByteOrders' => self::v225_mapRaw($next, 'byteOrder'),
            'encodingChangedRowids' => $encodingChanged,
            'sourceBytesChangedRowids' => $bytesChanged,
            'byteOrderChangedRowids' => $byteOrderChanged,
        ];
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function v225_rawRowsById(array $rows): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoTwoFive rows require integer option_id');
            }
            if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoTwoFive rows require option_name_bytes');
            }
            if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoTwoFive rows require integer text_encoding');
            }
            $encoding = $row['text_encoding'];
            $mapped[$row['option_id']] = [
                'encoding' => $encoding,
                'encodingName' => self::v225_encodingName($encoding),
                'byteOrder' => self::v225_byteOrder($encoding),
                'bytesHex' => bin2hex($row['option_name_bytes']),
            ];
        }
        ksort($mapped);

        return $mapped;
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,mixed> */
    private static function v225_mapRaw(array $rows, string $key): array
    {
        $mapped = [];
        foreach ($rows as $rowid => $row) {
            $mapped[$rowid] = $row[$key];
        }

        return $mapped;
    }

    /** @param array<string,mixed> $raw @param array<string,mixed> $base @return list<int> */
    private static function v225_stableDecodedChangedSourceRowids(array $raw, array $base): array
    {
        $changed = array_unique(array_merge($raw['encodingChangedRowids'], $raw['sourceBytesChangedRowids']));
        $stable = [];
        foreach ($changed as $rowid) {
            if (
                !in_array($rowid, $base['changedTextRowids'], true)
                && !in_array($rowid, $base['changedRtrimRowids'], true)
                && !in_array($rowid, $base['changedNocaseKeyRowids'], true)
                && !in_array($rowid, $base['changedResidualRowids'], true)
            ) {
                $stable[] = $rowid;
            }
        }
        sort($stable);

        return $stable;
    }

    private static function v225_encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoTwoFive encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function v225_byteOrder(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'little-endian',
            3 => 'big-endian',
            default => throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoTwoFive encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyCombiningMarkPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin_caf_',
        ?string $escape = null,
        string $currentSource = 'main.app_settings@225',
        string $nextSource = 'main.app_settings@226',
        int $currentSchemaCookie = 225,
        int $nextSchemaCookie = 226,
    ): array {
        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $current = self::v226_scan($currentRows, $pattern, $escape, $like['range']);
        $next = self::v226_scan($nextRows, $pattern, $escape, $like['range']);

        $currentMatched = self::v226_rowids($current['matched']);
        $nextMatched = self::v226_rowids($next['matched']);
        $changes = self::v226_changes($current['decoded'], $next['decoded']);

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
            'unicode-normalization-form' => $changes['normalizationChangedRowids'],
            'combining-mark-count' => $changes['combiningMarkChangedRowids'],
            'like-character-count' => $changes['characterCountChangedRowids'],
            'residual-result' => $changes['residualChangedRowids'],
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        if (self::v226_rowids($current['candidates']) !== self::v226_rowids($next['candidates'])) {
            $reasons[] = 'candidate-rowset';
        }
        if ($currentMatched !== $nextMatched) {
            $reasons[] = 'matched-rowset';
        }
        if ($current['normalizationTrapRowids'] !== [] || $next['normalizationTrapRowids'] !== []) {
            $reasons[] = 'unicode-normalization-not-applied';
        }

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nexttwoTwoSix',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? /* combining mark normalization boundary */',
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
            'currentCandidateRowids' => self::v226_rowids($current['candidates']),
            'nextCandidateRowids' => self::v226_rowids($next['candidates']),
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'matchedRetainedRowids' => self::v226_sortedIntersect($currentMatched, $nextMatched),
            'matchedExitedRowids' => self::v226_sortedDiff($currentMatched, $nextMatched),
            'matchedEnteredRowids' => self::v226_sortedDiff($nextMatched, $currentMatched),
            'currentFalsePositiveRowids' => self::v226_rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::v226_rowids($next['falsePositive']),
            'currentCombiningMarkRowids' => $current['combiningMarkRowids'],
            'nextCombiningMarkRowids' => $next['combiningMarkRowids'],
            'currentNormalizationTrapRowids' => $current['normalizationTrapRowids'],
            'nextNormalizationTrapRowids' => $next['normalizationTrapRowids'],
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'currentRtrimTexts' => self::v226_map($current['decoded'], 'rtrimText'),
            'nextRtrimTexts' => self::v226_map($next['decoded'], 'rtrimText'),
            'currentNocaseKeys' => self::v226_map($current['decoded'], 'nocaseKey'),
            'nextNocaseKeys' => self::v226_map($next['decoded'], 'nocaseKey'),
            'currentCharacterCounts' => self::v226_map($current['decoded'], 'characterCount'),
            'nextCharacterCounts' => self::v226_map($next['decoded'], 'characterCount'),
            'currentCombiningMarkCounts' => self::v226_map($current['decoded'], 'combiningMarkCount'),
            'nextCombiningMarkCounts' => self::v226_map($next['decoded'], 'combiningMarkCount'),
            'currentNormalizationForms' => self::v226_map($current['decoded'], 'normalizationForm'),
            'nextNormalizationForms' => self::v226_map($next['decoded'], 'normalizationForm'),
            'currentResidualMatches' => self::v226_map($current['candidates'], 'residualMatch'),
            'nextResidualMatches' => self::v226_map($next['candidates'], 'residualMatch'),
            'changedTextRowids' => $changes['textChangedRowids'],
            'changedRtrimRowids' => $changes['rtrimChangedRowids'],
            'changedNocaseKeyRowids' => $changes['nocaseKeyChangedRowids'],
            'changedNormalizationRowids' => $changes['normalizationChangedRowids'],
            'changedCombiningMarkRowids' => $changes['combiningMarkChangedRowids'],
            'changedCharacterCountRowids' => $changes['characterCountChangedRowids'],
            'changedResidualRowids' => $changes['residualChangedRowids'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'likeUnderscoreConsumesUnicodeCodepoint' => true,
            'combiningMarkRemainsSeparateLikeCharacter' => true,
            'unicodeNormalizationIsNotApplied' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'invalidationReasons' => array_values(array_unique($reasons)),
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-combining-mark-like-character',
                'sqlite-current-source-nexttwoTwoSix',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE range planning, RTRIM expression keys, and binary-safe Unicode code point splitting',
            'non_overlap' => 'nextTwoTwoSix covers composed versus decomposed Unicode combining-mark LIKE residual behavior without normalization; avoids accepted nextTwoOneNine supplementary-plane wildcard, nextTwoZeroNine ASCII-space RTRIM, Unicode GLOB ranges, escape rebind, and malformed UTF-16 insert guard clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{decoded:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,combiningMarkRowids:list<int>,normalizationTrapRowids:list<int>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function v226_scan(array $rows, string $pattern, ?string $escape, ?array $range): array
    {
        $decoded = [];
        $combining = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            self::v226_assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
                $rtrim = rtrim($text, ' ');
                $combiningCount = self::v226_combiningMarkCount($rtrim);
                if ($combiningCount > 0) {
                    $combining[] = $row['option_id'];
                }
                $decoded[] = [
                    'rowid' => $row['option_id'],
                    'text' => $text,
                    'rtrimText' => $rtrim,
                    'nocaseKey' => self::v226_asciiLower($rtrim),
                    'characterCount' => count(self::v226_characters($rtrim)),
                    'combiningMarkCount' => $combiningCount,
                    'normalizationForm' => self::v226_normalizationForm($rtrim),
                    'bytesHex' => bin2hex($row['option_name_bytes']),
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['option_id'];
                $errors[$row['option_id']] = $exception->getMessage();
            }
        }

        usort($decoded, self::v226_sortRows(...));
        sort($combining);
        sort($malformed);
        ksort($errors);

        $candidates = [];
        $matched = [];
        $falsePositive = [];
        $traps = [];
        foreach ($decoded as $entry) {
            if (!self::v226_inRange($entry['nocaseKey'], $range)) {
                continue;
            }
            $entry['residualMatch'] = SQLiteDatabase::likeMatches($entry['rtrimText'], $pattern, $escape, false);
            $candidates[] = $entry;
            if ($entry['residualMatch']) {
                $matched[] = $entry;
            } else {
                $falsePositive[] = $entry;
            }
            if (!$entry['residualMatch'] && $entry['combiningMarkCount'] > 0) {
                $traps[] = $entry['rowid'];
            }
        }
        sort($traps);

        return [
            'decoded' => $decoded,
            'candidates' => $candidates,
            'matched' => $matched,
            'falsePositive' => $falsePositive,
            'combiningMarkRowids' => $combining,
            'normalizationTrapRowids' => $traps,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row */
    private static function v226_assertRow(array $row): void
    {
        if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoTwoSix rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoTwoSix rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoTwoSix rows require integer text_encoding');
        }
    }

    /** @param ?array{lowerInclusive:string,upperBound:?string} $range */
    private static function v226_inRange(string $key, ?array $range): bool
    {
        if ($range === null || strcmp($key, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0;
    }

    /** @param array{nocaseKey:string,rowid:int} $left @param array{nocaseKey:string,rowid:int} $right */
    private static function v226_sortRows(array $left, array $right): int
    {
        $comparison = strcmp($left['nocaseKey'], $right['nocaseKey']);

        return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
    }

    /** @param list<array{rowid:int}> $rows @return list<int> */
    private static function v226_rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $rows @return array<int,mixed> */
    private static function v226_map(array $rows, string $key): array
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
     * @return array<string,list<int>>
     */
    private static function v226_changes(array $current, array $next): array
    {
        $currentByRowid = self::v226_byRowid($current);
        $nextByRowid = self::v226_byRowid($next);

        return [
            'textChangedRowids' => self::v226_changed($currentByRowid, $nextByRowid, 'text'),
            'rtrimChangedRowids' => self::v226_changed($currentByRowid, $nextByRowid, 'rtrimText'),
            'nocaseKeyChangedRowids' => self::v226_changed($currentByRowid, $nextByRowid, 'nocaseKey'),
            'normalizationChangedRowids' => self::v226_changed($currentByRowid, $nextByRowid, 'normalizationForm'),
            'combiningMarkChangedRowids' => self::v226_changed($currentByRowid, $nextByRowid, 'combiningMarkCount'),
            'characterCountChangedRowids' => self::v226_changed($currentByRowid, $nextByRowid, 'characterCount'),
            'residualChangedRowids' => self::v226_changed($currentByRowid, $nextByRowid, 'residualMatch'),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private static function v226_byRowid(array $rows): array
    {
        $byRowid = [];
        foreach ($rows as $row) {
            $byRowid[$row['rowid']] = $row;
        }

        return $byRowid;
    }

    /**
     * @param array<int,array<string,mixed>> $current
     * @param array<int,array<string,mixed>> $next
     * @return list<int>
     */
    private static function v226_changed(array $current, array $next, string $field): array
    {
        $rowids = array_values(array_unique(array_merge(array_keys($current), array_keys($next))));
        sort($rowids);
        $changed = [];
        foreach ($rowids as $rowid) {
            if (($current[$rowid][$field] ?? null) !== ($next[$rowid][$field] ?? null)) {
                $changed[] = $rowid;
            }
        }

        return $changed;
    }

    /** @return list<int> */
    private static function v226_sortedIntersect(array $left, array $right): array
    {
        $result = array_values(array_intersect($left, $right));
        sort($result);

        return $result;
    }

    /** @return list<int> */
    private static function v226_sortedDiff(array $left, array $right): array
    {
        $result = array_values(array_diff($left, $right));
        sort($result);

        return $result;
    }

    private static function v226_asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /** @return list<string> */
    private static function v226_characters(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $chars = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoTwoSix text is not valid UTF-8 after decode');
        }

        return $chars;
    }

    private static function v226_combiningMarkCount(string $value): int
    {
        $count = 0;
        foreach (self::v226_characters($value) as $char) {
            if (preg_match('/^\p{M}$/u', $char) === 1) {
                $count++;
            }
        }

        return $count;
    }

    private static function v226_normalizationForm(string $value): string
    {
        if (str_contains($value, "e\xcc\x81")) {
            return 'decomposed-combining-acute';
        }
        if (str_contains($value, "\xc3\xa9")) {
            return 'composed-latin-small-e-acute';
        }
        if (self::v226_combiningMarkCount($value) > 0) {
            return 'decomposed-combining-mark';
        }

        return 'plain';
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyAsciiSpaceBoundaryPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin_cache',
        ?string $escape = null,
        string $currentSource = 'main.app_settings@226',
        string $nextSource = 'main.app_settings@227',
        int $currentSchemaCookie = 226,
        int $nextSchemaCookie = 227,
    ): array {
        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $current = self::v227_scan($currentRows, $pattern, $escape, $like['range']);
        $next = self::v227_scan($nextRows, $pattern, $escape, $like['range']);
        $changes = self::v227_changes($current['decoded'], $next['decoded']);
        $currentMatched = self::v227_rowids($current['matched']);
        $nextMatched = self::v227_rowids($next['matched']);
        $currentCandidates = self::v227_rowids($current['candidates']);
        $nextCandidates = self::v227_rowids($next['candidates']);

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
            'suffix-class' => $changes['suffixClassChangedRowids'],
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
        if ($current['nbspSuffixRowids'] !== [] || $next['nbspSuffixRowids'] !== []) {
            $reasons[] = 'non-ascii-space-rtrim-boundary';
        }

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nexttwoTwoSeven',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? /* ASCII-space RTRIM boundary */',
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
            'usesEqualityPrefixRange' => true,
            'currentCandidateRowids' => $currentCandidates,
            'nextCandidateRowids' => $nextCandidates,
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'matchedRetainedRowids' => self::v227_sortedIntersect($currentMatched, $nextMatched),
            'matchedExitedRowids' => self::v227_sortedDiff($currentMatched, $nextMatched),
            'matchedEnteredRowids' => self::v227_sortedDiff($nextMatched, $currentMatched),
            'currentFalsePositiveRowids' => self::v227_rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::v227_rowids($next['falsePositive']),
            'currentAsciiSpaceSuffixRowids' => $current['asciiSpaceSuffixRowids'],
            'nextAsciiSpaceSuffixRowids' => $next['asciiSpaceSuffixRowids'],
            'currentNbspSuffixRowids' => $current['nbspSuffixRowids'],
            'nextNbspSuffixRowids' => $next['nbspSuffixRowids'],
            'currentTabSuffixRowids' => $current['tabSuffixRowids'],
            'nextTabSuffixRowids' => $next['tabSuffixRowids'],
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'currentDecodedTexts' => self::v227_map($current['decoded'], 'text'),
            'nextDecodedTexts' => self::v227_map($next['decoded'], 'text'),
            'currentRtrimTexts' => self::v227_map($current['decoded'], 'rtrimText'),
            'nextRtrimTexts' => self::v227_map($next['decoded'], 'rtrimText'),
            'currentNocaseKeys' => self::v227_map($current['decoded'], 'nocaseKey'),
            'nextNocaseKeys' => self::v227_map($next['decoded'], 'nocaseKey'),
            'currentSuffixClasses' => self::v227_map($current['decoded'], 'suffixClass'),
            'nextSuffixClasses' => self::v227_map($next['decoded'], 'suffixClass'),
            'currentResidualMatches' => self::v227_map($current['candidates'], 'residualMatch'),
            'nextResidualMatches' => self::v227_map($next['candidates'], 'residualMatch'),
            'changedTextRowids' => $changes['textChangedRowids'],
            'changedRtrimRowids' => $changes['rtrimChangedRowids'],
            'changedNocaseKeyRowids' => $changes['nocaseKeyChangedRowids'],
            'changedSuffixClassRowids' => $changes['suffixClassChangedRowids'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'asciiSpaceSuffixMatchesAfterRtrim' => true,
            'nonAsciiSpaceSuffixDoesNotRtrim' => true,
            'tabSuffixDoesNotRtrim' => true,
            'nocaseFoldsAsciiOnly' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'invalidationReasons' => array_values(array_unique($reasons)),
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-current-source-nexttwoTwoSeven',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE prefix planning, and RTRIM expression keys',
            'non_overlap' => 'nextTwoTwoSeven covers ASCII-space-only RTRIM residual equality under UTF-16 NOCASE LIKE; avoids accepted escaped wildcard nextOneNineFour, supplementary wildcard nextTwoOneNine, Unicode GLOB ranges, malformed UTF-16 insert guards, and storage/planner clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{decoded:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,asciiSpaceSuffixRowids:list<int>,nbspSuffixRowids:list<int>,tabSuffixRowids:list<int>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function v227_scan(array $rows, string $pattern, ?string $escape, ?array $range): array
    {
        $decoded = [];
        $asciiSpace = [];
        $nbsp = [];
        $tabs = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            self::v227_assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
                $rtrim = rtrim($text, ' ');
                $suffixClass = self::v227_suffixClass($text);
                if ($suffixClass === 'ascii-space') {
                    $asciiSpace[] = $row['option_id'];
                } elseif ($suffixClass === 'nbsp') {
                    $nbsp[] = $row['option_id'];
                } elseif ($suffixClass === 'tab') {
                    $tabs[] = $row['option_id'];
                }
                $decoded[] = [
                    'rowid' => $row['option_id'],
                    'text' => $text,
                    'rtrimText' => $rtrim,
                    'nocaseKey' => self::v227_asciiLower($rtrim),
                    'suffixClass' => $suffixClass,
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['option_id'];
                $errors[$row['option_id']] = $exception->getMessage();
            }
        }

        usort($decoded, self::v227_sortRows(...));
        sort($asciiSpace);
        sort($nbsp);
        sort($tabs);
        sort($malformed);
        ksort($errors);

        $candidates = [];
        $matched = [];
        $falsePositive = [];
        foreach ($decoded as $entry) {
            if (!self::v227_inRange($entry['nocaseKey'], $range)) {
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
            'asciiSpaceSuffixRowids' => $asciiSpace,
            'nbspSuffixRowids' => $nbsp,
            'tabSuffixRowids' => $tabs,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row */
    private static function v227_assertRow(array $row): void
    {
        if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoTwoSeven rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoTwoSeven rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoTwoSeven rows require integer text_encoding');
        }
    }

    private static function v227_suffixClass(string $text): string
    {
        if (str_ends_with($text, ' ')) {
            return 'ascii-space';
        }
        if (str_ends_with($text, "\xc2\xa0")) {
            return 'nbsp';
        }
        if (str_ends_with($text, "\t")) {
            return 'tab';
        }

        return 'none';
    }

    /** @param ?array{lowerInclusive:string,upperBound:?string} $range */
    private static function v227_inRange(string $key, ?array $range): bool
    {
        if ($range === null || strcmp($key, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0;
    }

    /** @param array{nocaseKey:string,rowid:int} $left @param array{nocaseKey:string,rowid:int} $right */
    private static function v227_sortRows(array $left, array $right): int
    {
        $comparison = strcmp($left['nocaseKey'], $right['nocaseKey']);

        return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
    }

    /** @param list<array{rowid:int}> $rows @return list<int> */
    private static function v227_rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $rows @return array<int,mixed> */
    private static function v227_map(array $rows, string $key): array
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
     * @return array{textChangedRowids:list<int>,rtrimChangedRowids:list<int>,nocaseKeyChangedRowids:list<int>,suffixClassChangedRowids:list<int>}
     */
    private static function v227_changes(array $current, array $next): array
    {
        $currentById = self::v227_byRowid($current);
        $nextById = self::v227_byRowid($next);
        $rowids = array_values(array_unique(array_merge(array_keys($currentById), array_keys($nextById))));
        sort($rowids);
        $changes = [
            'textChangedRowids' => [],
            'rtrimChangedRowids' => [],
            'nocaseKeyChangedRowids' => [],
            'suffixClassChangedRowids' => [],
        ];
        foreach ($rowids as $rowid) {
            foreach ([
                'text' => 'textChangedRowids',
                'rtrimText' => 'rtrimChangedRowids',
                'nocaseKey' => 'nocaseKeyChangedRowids',
                'suffixClass' => 'suffixClassChangedRowids',
            ] as $field => $key) {
                if (($currentById[$rowid][$field] ?? null) !== ($nextById[$rowid][$field] ?? null)) {
                    $changes[$key][] = $rowid;
                }
            }
        }

        return $changes;
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function v227_byRowid(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['rowid']] = $row;
        }

        return $indexed;
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function v227_sortedIntersect(array $left, array $right): array
    {
        $values = array_values(array_intersect($left, $right));
        sort($values);

        return $values;
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function v227_sortedDiff(array $left, array $right): array
    {
        $values = array_values(array_diff($left, $right));
        sort($values);

        return $values;
    }

    private static function v227_asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyHeaderEncodingFencePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_cache%',
        ?string $escape = '!',
        int|string $currentDatabaseEncoding = 'UTF-16LE',
        int|string $nextDatabaseEncoding = 'UTF-16BE',
        int|string $preparedEncoding = 'UTF-16LE',
        string $currentSource = 'main.app_settings@227',
        string $nextSource = 'main.app_settings@228',
        int $currentSchemaCookie = 227,
        int $nextSchemaCookie = 228,
    ): array {
        $currentEncoding = self::v228_encodingName($currentDatabaseEncoding);
        $nextEncoding = self::v228_encodingName($nextDatabaseEncoding);
        $statementEncoding = self::v228_encodingName($preparedEncoding);

        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeySourceRefreshPlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escape,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $headerEncodingChanged = $currentEncoding !== $nextEncoding;
        $preparedMatchesCurrent = $statementEncoding === $currentEncoding;
        $preparedMatchesNext = $statementEncoding === $nextEncoding;
        $logicalRowsetStable = $base['currentCandidateRowids'] === $base['nextCandidateRowids']
            && $base['currentMatchedRowids'] === $base['nextMatchedRowids']
            && $base['decodedRtrimTextChangedRowids'] === []
            && $base['currentMalformedRowids'] === []
            && $base['nextMalformedRowids'] === [];

        $reasons = $base['invalidationReasons'];
        if ($headerEncodingChanged) {
            $reasons[] = 'database-text-encoding';
        }
        if ($preparedMatchesCurrent && !$preparedMatchesNext) {
            $reasons[] = 'prepared-encoding-stale';
        }
        if ($headerEncodingChanged && $logicalRowsetStable) {
            $reasons[] = 'logical-rowset-stable-header-fence';
        }
        $reasons = array_values(array_unique($reasons));

        $baseReasonsWithoutByteOrderOnly = array_values(array_diff($base['invalidationReasons'], ['byte-order-only-refresh']));
        $requiresReprepare = $baseReasonsWithoutByteOrderOnly !== []
            || $headerEncodingChanged
            || ($preparedMatchesCurrent && !$preparedMatchesNext);

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nexttwoTwoEight',
            'baseStatus' => $base['status'],
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* database text-encoding fence */',
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => $base['collation'],
            'currentSource' => $base['currentSource'],
            'nextSource' => $base['nextSource'],
            'currentSchemaCookie' => $base['currentSchemaCookie'],
            'nextSchemaCookie' => $base['nextSchemaCookie'],
            'currentDatabaseEncoding' => $currentEncoding,
            'nextDatabaseEncoding' => $nextEncoding,
            'preparedEncoding' => $statementEncoding,
            'headerEncodingChanged' => $headerEncodingChanged,
            'preparedEncodingMatchesCurrentHeader' => $preparedMatchesCurrent,
            'preparedEncodingMatchesNextHeader' => $preparedMatchesNext,
            'logicalRowsetStable' => $logicalRowsetStable,
            'baseByteOrderOnlyRefreshReusable' => $base['byteOrderOnlyRefreshReusable'],
            'baseCursorReusable' => $base['cursorReusable'],
            'currentCandidateRowids' => $base['currentCandidateRowids'],
            'nextCandidateRowids' => $base['nextCandidateRowids'],
            'currentMatchedRowids' => $base['currentMatchedRowids'],
            'nextMatchedRowids' => $base['nextMatchedRowids'],
            'matchedRetainedRowids' => $base['matchedRetainedRowids'],
            'matchedExitedRowids' => $base['matchedExitedRowids'],
            'matchedEnteredRowids' => $base['matchedEnteredRowids'],
            'byteOrderOnlyRowids' => $base['byteOrderOnlyRowids'],
            'encodingChangedRowids' => $base['encodingChangedRowids'],
            'decodedRtrimTextChangedRowids' => $base['decodedRtrimTextChangedRowids'],
            'currentRtrimTexts' => $base['currentRtrimTexts'],
            'nextRtrimTexts' => $base['nextRtrimTexts'],
            'currentNocaseKeys' => $base['currentNocaseKeys'],
            'nextNocaseKeys' => $base['nextNocaseKeys'],
            'currentMalformedRowids' => $base['currentMalformedRowids'],
            'nextMalformedRowids' => $base['nextMalformedRowids'],
            'currentErrors' => $base['currentErrors'],
            'nextErrors' => $base['nextErrors'],
            'baseInvalidationReasons' => $base['invalidationReasons'],
            'invalidationReasons' => $reasons,
            'cursorInvalidated' => $requiresReprepare,
            'cursorReusable' => !$requiresReprepare,
            'mustReprepareForHeaderEncoding' => $headerEncodingChanged,
            'mustRepreparePreparedUtf16Statement' => $preparedMatchesCurrent && !$preparedMatchesNext,
            'canRetainRowsetButNotPreparedCursor' => $logicalRowsetStable && $requiresReprepare,
            'rtrimTrimsOnlyAsciiSpace' => $base['rtrimTrimsOnlyAsciiSpace'],
            'nocaseFoldsAsciiOnly' => $base['nocaseFoldsAsciiOnly'],
            'residualCheckedAfterRtrim' => $base['residualCheckedAfterRtrim'],
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-database-text-encoding-header',
                'sqlite-prepared-statement-encoding-fence',
                'sqlite-rtrim-expression-key',
                'sqlite-current-source-nexttwoTwoEight',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, accepted NOCASE/RTRIM LIKE residuals, and adds a bounded database text-encoding header fence for prepared cursor reuse',
            'non_overlap' => 'nextTwoTwoEight layers a database text-encoding header/prepared-statement fence on top of accepted nextTwoOneOne byte-order-only refresh; it does not repeat nextTwoTwoFour keyset resume, nextTwoTwoThree DESC LIMIT windows, nextTwoTwoOne prepared byte signatures, nextTwoZeroEight ESCAPE decoding, Unicode GLOB ranges, or UTF-16 malformed insert guards',
        ];
    }

    private static function v228_encodingName(int|string $encoding): string
    {
        return match ($encoding) {
            1, 'UTF-8' => 'UTF-8',
            2, 'UTF-16LE' => 'UTF-16LE',
            3, 'UTF-16BE' => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoTwoEight encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    private const NON_ASCII_SPACE_NAMES = [
        "\u{00a0}" => 'NO-BREAK SPACE',
        "\u{1680}" => 'OGHAM SPACE MARK',
        "\u{2000}" => 'EN QUAD',
        "\u{2001}" => 'EM QUAD',
        "\u{2002}" => 'EN SPACE',
        "\u{2003}" => 'EM SPACE',
        "\u{2004}" => 'THREE-PER-EM SPACE',
        "\u{2005}" => 'FOUR-PER-EM SPACE',
        "\u{2006}" => 'SIX-PER-EM SPACE',
        "\u{2007}" => 'FIGURE SPACE',
        "\u{2008}" => 'PUNCTUATION SPACE',
        "\u{2009}" => 'THIN SPACE',
        "\u{200a}" => 'HAIR SPACE',
        "\u{202f}" => 'NARROW NO-BREAK SPACE',
        "\u{205f}" => 'MEDIUM MATHEMATICAL SPACE',
        "\u{3000}" => 'IDEOGRAPHIC SPACE',
    ];

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyUnicodeSpaceRtrimPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_cache%',
        string $escapeBytes = "!\0",
        int|string $escapeEncoding = 'UTF-16LE',
        int $pageSize = 4,
        int $lastRowid = 0,
        ?string $lastKey = null,
        string $currentSource = 'main.app_settings@228',
        string $nextSource = 'main.app_settings@229',
        int $currentSchemaCookie = 228,
        int $nextSchemaCookie = 229,
    ): array {
        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyKeysetResumePlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escapeBytes,
            $escapeEncoding,
            $escapeBytes,
            $escapeEncoding,
            $pageSize,
            $lastRowid,
            $lastKey,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $currentUnicode = self::v229_unicodeSpaceRows($base['currentRtrimTexts']);
        $nextUnicode = self::v229_unicodeSpaceRows($base['nextRtrimTexts']);
        $currentTexts = self::v229_decodedTexts($currentRows);
        $nextTexts = self::v229_decodedTexts($nextRows);
        $currentAscii = self::v229_asciiSpaceTrimmedRows($currentTexts, $base['currentRtrimTexts']);
        $nextAscii = self::v229_asciiSpaceTrimmedRows($nextTexts, $base['nextRtrimTexts']);
        $currentVisual = self::v229_visualKeys($base['currentRtrimTexts']);
        $nextVisual = self::v229_visualKeys($base['nextRtrimTexts']);
        $currentUnicodeMatched = array_values(array_intersect($base['currentMatchedRowids'], array_keys($currentUnicode)));
        $nextUnicodeMatched = array_values(array_intersect($base['nextMatchedRowids'], array_keys($nextUnicode)));
        $currentVisualPeers = self::v229_visualPeerRowsets($currentVisual);
        $nextVisualPeers = self::v229_visualPeerRowsets($nextVisual);

        $reasons = $base['invalidationReasons'];
        if ($currentUnicodeMatched !== $nextUnicodeMatched) {
            $reasons[] = 'unicode-space-rowset';
        }
        if ($currentAscii !== $nextAscii) {
            $reasons[] = 'ascii-space-rtrim-rowset';
        }
        if (self::v229_rowsetMap($currentVisualPeers) !== self::v229_rowsetMap($nextVisualPeers)) {
            $reasons[] = 'visual-space-peer-rowset';
        }
        $reasons = array_values(array_unique($reasons));

        return array_replace($base, [
            'status' => 'utf16-nocase-like-rtrim-current-source-nexttwoTwoNine',
            'baseStatus' => $base['status'],
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* UTF-16 non-ASCII spaces are not RTRIM spaces */',
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentUnicodeSpaceRowids' => array_keys($currentUnicode),
            'nextUnicodeSpaceRowids' => array_keys($nextUnicode),
            'currentDecodedTexts' => $currentTexts,
            'nextDecodedTexts' => $nextTexts,
            'currentUnicodeSpaceNames' => $currentUnicode,
            'nextUnicodeSpaceNames' => $nextUnicode,
            'currentUnicodeSpaceMatchedRowids' => $currentUnicodeMatched,
            'nextUnicodeSpaceMatchedRowids' => $nextUnicodeMatched,
            'currentAsciiSpaceTrimmedRowids' => $currentAscii,
            'nextAsciiSpaceTrimmedRowids' => $nextAscii,
            'currentVisualSpaceKeys' => $currentVisual,
            'nextVisualSpaceKeys' => $nextVisual,
            'currentVisualSpacePeerRowids' => $currentVisualPeers,
            'nextVisualSpacePeerRowids' => $nextVisualPeers,
            'unicodeSpacesRetainedByRtrim' => true,
            'asciiSpaceOnlyRtrim' => true,
            'likeResidualRunsAfterUnicodeSpaceRetention' => true,
            'nocaseFoldsUnicodeSpacesNever' => true,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'staleKeysetResumeRisk' => $reasons !== [],
            'invalidationReasons' => $reasons,
            'baseInvalidationReasons' => $base['invalidationReasons'],
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-escape-prefix-range',
                'sqlite-rtrim-ascii-space-only',
                'sqlite-nocase-keyset-resume',
                'sqlite-current-source-nexttwoTwoNine',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, LIKE ESCAPE prefix planning, ASCII-only RTRIM keys, NOCASE keyset resume, and current-source invalidation diagnostics',
            'non_overlap' => 'nextTwoTwoNine covers UTF-16 non-ASCII whitespace at the RTRIM/NOCASE LIKE current-source boundary; it avoids accepted nextTwoTwoFour keyset rowsets, nextTwoOneTwo/213 Unicode ESCAPE handling, nextOneNineZero ASCII-space trim boundaries, Unicode GLOB ranges, malformed UTF-16 guards, and VFS/WAL/B-tree/JSON/SQL clusters',
        ]);
    }

    /** @param array<int,string> $texts @return array<int,list<string>> */
    private static function v229_unicodeSpaceRows(array $texts): array
    {
        $rows = [];
        foreach ($texts as $rowid => $text) {
            $names = [];
            foreach (self::v229_characters($text) as $character) {
                if (isset(self::NON_ASCII_SPACE_NAMES[$character])) {
                    $names[] = self::NON_ASCII_SPACE_NAMES[$character];
                }
            }
            if ($names !== []) {
                $rows[(int) $rowid] = array_values(array_unique($names));
            }
        }

        ksort($rows);

        return $rows;
    }

    /** @param array<int,string> $matchedTexts @param array<int,string> $rtrimTexts @return list<int> */
    private static function v229_asciiSpaceTrimmedRows(array $matchedTexts, array $rtrimTexts): array
    {
        $rowids = [];
        foreach ($matchedTexts as $rowid => $text) {
            if (($rtrimTexts[$rowid] ?? $text) !== $text) {
                $rowids[] = (int) $rowid;
            }
        }
        sort($rowids);

        return $rowids;
    }

    /** @param array<int,string> $texts @return array<int,string> */
    private static function v229_visualKeys(array $texts): array
    {
        $keys = [];
        foreach ($texts as $rowid => $text) {
            $key = str_replace(array_keys(self::NON_ASCII_SPACE_NAMES), ' ', $text);
            $keys[(int) $rowid] = self::v229_asciiLower(rtrim($key, ' '));
        }
        ksort($keys);

        return $keys;
    }

    /** @param array<int,string> $visualKeys @return array<string,list<int>> */
    private static function v229_visualPeerRowsets(array $visualKeys): array
    {
        $peers = [];
        foreach ($visualKeys as $rowid => $key) {
            $peers[$key] ??= [];
            $peers[$key][] = (int) $rowid;
        }
        ksort($peers);

        return array_filter($peers, static fn (array $rowids): bool => count($rowids) > 1);
    }

    /** @param array<string,list<int>> $rowsets @return array<string,list<int>> */
    private static function v229_rowsetMap(array $rowsets): array
    {
        foreach ($rowsets as &$rowids) {
            sort($rowids);
        }
        unset($rowids);
        ksort($rowsets);

        return $rowsets;
    }

    /** @param list<array<string,mixed>> $rows @return array<int,string> */
    private static function v229_decodedTexts(array $rows): array
    {
        $texts = [];
        foreach ($rows as $row) {
            if (!isset($row['option_id'], $row['option_name_bytes'], $row['text_encoding'])) {
                continue;
            }
            try {
                $texts[(int) $row['option_id']] = SQLiteEncodingCollationSourceCursor::decodeText(
                    (string) $row['option_name_bytes'],
                    (int) $row['text_encoding'],
                );
            } catch (\InvalidArgumentException) {
                continue;
            }
        }
        ksort($texts);

        return $texts;
    }

    private static function v229_asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /** @return list<string> */
    private static function v229_characters(string $value): array
    {
        if ($value === '') {
            return [];
        }
        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);

        return is_array($characters) ? array_values($characters) : str_split($value);
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyLineBreakBoundaryPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin_cache',
        ?string $escape = null,
        string $currentSource = 'main.app_settings@229',
        string $nextSource = 'main.app_settings@230',
        int $currentSchemaCookie = 229,
        int $nextSchemaCookie = 230,
    ): array {
        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $current = self::v230_scan($currentRows, $pattern, $escape, $like['range']);
        $next = self::v230_scan($nextRows, $pattern, $escape, $like['range']);
        $changes = self::v230_changes($current['decoded'], $next['decoded']);
        $currentMatched = self::v230_rowids($current['matched']);
        $nextMatched = self::v230_rowids($next['matched']);
        $currentCandidates = self::v230_rowids($current['candidates']);
        $nextCandidates = self::v230_rowids($next['candidates']);

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
            'line-break-suffix' => $changes['lineBreakClassChangedRowids'],
            'residual-result' => $changes['residualChangedRowids'],
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
        if (
            ($current['lineBreakSuffixRowids'] !== []
                || $next['lineBreakSuffixRowids'] !== []
                || $current['formFeedSuffixRowids'] !== []
                || $next['formFeedSuffixRowids'] !== [])
            && (
                $changes['lineBreakClassChangedRowids'] !== []
                || $currentMatched !== $nextMatched
            )
        ) {
            $reasons[] = 'non-space-rtrim-line-boundary';
        }

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nexttwoThreeZero',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? /* line-break RTRIM boundary */',
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
            'matchedRetainedRowids' => self::v230_sortedIntersect($currentMatched, $nextMatched),
            'matchedExitedRowids' => self::v230_sortedDiff($currentMatched, $nextMatched),
            'matchedEnteredRowids' => self::v230_sortedDiff($nextMatched, $currentMatched),
            'currentFalsePositiveRowids' => self::v230_rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::v230_rowids($next['falsePositive']),
            'currentAsciiSpaceSuffixRowids' => $current['asciiSpaceSuffixRowids'],
            'nextAsciiSpaceSuffixRowids' => $next['asciiSpaceSuffixRowids'],
            'currentLineBreakSuffixRowids' => $current['lineBreakSuffixRowids'],
            'nextLineBreakSuffixRowids' => $next['lineBreakSuffixRowids'],
            'currentFormFeedSuffixRowids' => $current['formFeedSuffixRowids'],
            'nextFormFeedSuffixRowids' => $next['formFeedSuffixRowids'],
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'currentDecodedTexts' => self::v230_map($current['decoded'], 'text'),
            'nextDecodedTexts' => self::v230_map($next['decoded'], 'text'),
            'currentRtrimTexts' => self::v230_map($current['decoded'], 'rtrimText'),
            'nextRtrimTexts' => self::v230_map($next['decoded'], 'rtrimText'),
            'currentNocaseKeys' => self::v230_map($current['decoded'], 'nocaseKey'),
            'nextNocaseKeys' => self::v230_map($next['decoded'], 'nocaseKey'),
            'currentSuffixClasses' => self::v230_map($current['decoded'], 'suffixClass'),
            'nextSuffixClasses' => self::v230_map($next['decoded'], 'suffixClass'),
            'currentResidualMatches' => self::v230_map($current['candidates'], 'residualMatch'),
            'nextResidualMatches' => self::v230_map($next['candidates'], 'residualMatch'),
            'changedTextRowids' => $changes['textChangedRowids'],
            'changedRtrimRowids' => $changes['rtrimChangedRowids'],
            'changedNocaseKeyRowids' => $changes['nocaseKeyChangedRowids'],
            'changedLineBreakClassRowids' => $changes['lineBreakClassChangedRowids'],
            'changedResidualRowids' => $changes['residualChangedRowids'],
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'asciiSpaceSuffixMatchesAfterRtrim' => true,
            'lineBreakSuffixDoesNotRtrim' => true,
            'formFeedSuffixDoesNotRtrim' => true,
            'nocaseFoldsAsciiOnly' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'invalidationReasons' => array_values(array_unique($reasons)),
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-line-break-rtrim-boundary',
                'sqlite-current-source-nexttwoThreeZero',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE prefix planning, RTRIM expression keys, and binary-safe residual matching',
            'non_overlap' => 'nextTwoThreeZero covers CR/LF/form-feed suffixes that remain significant after RTRIM for UTF-16 NOCASE LIKE current-source cursors; avoids accepted nextTwoTwoSeven tab/NBSP boundary, nextTwoTwoSix combining-mark normalization, nextTwoTwoFive source-byte fencing, Unicode GLOB ranges, UTF-16 malformed insert guards, and storage/planner clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{decoded:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,asciiSpaceSuffixRowids:list<int>,lineBreakSuffixRowids:list<int>,formFeedSuffixRowids:list<int>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function v230_scan(array $rows, string $pattern, ?string $escape, ?array $range): array
    {
        $decoded = [];
        $asciiSpace = [];
        $lineBreaks = [];
        $formFeeds = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            self::v230_assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
                $rtrim = rtrim($text, ' ');
                $suffixClass = self::v230_suffixClass($text);
                if ($suffixClass === 'ascii-space') {
                    $asciiSpace[] = $row['option_id'];
                } elseif ($suffixClass === 'line-break') {
                    $lineBreaks[] = $row['option_id'];
                } elseif ($suffixClass === 'form-feed') {
                    $formFeeds[] = $row['option_id'];
                }
                $decoded[] = [
                    'rowid' => $row['option_id'],
                    'text' => $text,
                    'rtrimText' => $rtrim,
                    'nocaseKey' => self::v230_asciiLower($rtrim),
                    'suffixClass' => $suffixClass,
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['option_id'];
                $errors[$row['option_id']] = $exception->getMessage();
            }
        }

        usort($decoded, self::v230_sortRows(...));
        sort($asciiSpace);
        sort($lineBreaks);
        sort($formFeeds);
        sort($malformed);
        ksort($errors);

        $candidates = [];
        $matched = [];
        $falsePositive = [];
        foreach ($decoded as $entry) {
            if (!self::v230_inRange($entry['nocaseKey'], $range)) {
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
        $residualByRowid = [];
        foreach ($candidates as $entry) {
            $residualByRowid[$entry['rowid']] = $entry['residualMatch'];
        }
        foreach ($decoded as &$entry) {
            if (array_key_exists($entry['rowid'], $residualByRowid)) {
                $entry['residualMatch'] = $residualByRowid[$entry['rowid']];
            }
        }
        unset($entry);

        return [
            'decoded' => $decoded,
            'candidates' => $candidates,
            'matched' => $matched,
            'falsePositive' => $falsePositive,
            'asciiSpaceSuffixRowids' => $asciiSpace,
            'lineBreakSuffixRowids' => $lineBreaks,
            'formFeedSuffixRowids' => $formFeeds,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row */
    private static function v230_assertRow(array $row): void
    {
        if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoThreeZero rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoThreeZero rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoThreeZero rows require integer text_encoding');
        }
    }

    private static function v230_suffixClass(string $text): string
    {
        if (str_ends_with($text, ' ')) {
            return 'ascii-space';
        }
        if (str_ends_with($text, "\n") || str_ends_with($text, "\r")) {
            return 'line-break';
        }
        if (str_ends_with($text, "\f")) {
            return 'form-feed';
        }

        return 'none';
    }

    /** @param ?array{lowerInclusive:string,upperBound:?string} $range */
    private static function v230_inRange(string $key, ?array $range): bool
    {
        if ($range === null || strcmp($key, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0;
    }

    /** @param array{nocaseKey:string,rowid:int} $left @param array{nocaseKey:string,rowid:int} $right */
    private static function v230_sortRows(array $left, array $right): int
    {
        $comparison = strcmp($left['nocaseKey'], $right['nocaseKey']);

        return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
    }

    /** @param list<array{rowid:int}> $rows @return list<int> */
    private static function v230_rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $rows @return array<int,mixed> */
    private static function v230_map(array $rows, string $key): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[$row['rowid']] = $row[$key];
        }

        return $mapped;
    }

    /**
     * @param list<array{rowid:int,text:string,rtrimText:string,nocaseKey:string,suffixClass:string,residualMatch?:bool}> $currentRows
     * @param list<array{rowid:int,text:string,rtrimText:string,nocaseKey:string,suffixClass:string,residualMatch?:bool}> $nextRows
     * @return array{textChangedRowids:list<int>,rtrimChangedRowids:list<int>,nocaseKeyChangedRowids:list<int>,lineBreakClassChangedRowids:list<int>,residualChangedRowids:list<int>}
     */
    private static function v230_changes(array $currentRows, array $nextRows): array
    {
        $current = [];
        foreach ($currentRows as $row) {
            $current[$row['rowid']] = $row;
        }

        $changes = [
            'textChangedRowids' => [],
            'rtrimChangedRowids' => [],
            'nocaseKeyChangedRowids' => [],
            'lineBreakClassChangedRowids' => [],
            'residualChangedRowids' => [],
        ];
        foreach ($nextRows as $row) {
            $rowid = $row['rowid'];
            if (!isset($current[$rowid])) {
                continue;
            }
            if ($current[$rowid]['text'] !== $row['text']) {
                $changes['textChangedRowids'][] = $rowid;
            }
            if ($current[$rowid]['rtrimText'] !== $row['rtrimText']) {
                $changes['rtrimChangedRowids'][] = $rowid;
            }
            if ($current[$rowid]['nocaseKey'] !== $row['nocaseKey']) {
                $changes['nocaseKeyChangedRowids'][] = $rowid;
            }
            if ($current[$rowid]['suffixClass'] !== $row['suffixClass']) {
                $changes['lineBreakClassChangedRowids'][] = $rowid;
            }
            if (($current[$rowid]['residualMatch'] ?? null) !== ($row['residualMatch'] ?? null)) {
                $changes['residualChangedRowids'][] = $rowid;
            }
        }
        foreach ($changes as &$rowids) {
            sort($rowids);
        }

        return $changes;
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function v230_sortedIntersect(array $left, array $right): array
    {
        $values = array_values(array_intersect($left, $right));
        sort($values);

        return $values;
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function v230_sortedDiff(array $left, array $right): array
    {
        $values = array_values(array_diff($left, $right));
        sort($values);

        return $values;
    }

    private static function v230_asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyAsciiOnlyNocasePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin_cafÉ%',
        ?string $escape = null,
        string $currentSource = 'main.app_settings@230',
        string $nextSource = 'main.app_settings@231',
        int $currentSchemaCookie = 230,
        int $nextSchemaCookie = 231,
    ): array {
        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $current = self::v231_scan($currentRows, $pattern, $escape, $like['range']);
        $next = self::v231_scan($nextRows, $pattern, $escape, $like['range']);
        $changes = self::v231_changes($current['decoded'], $next['decoded']);
        $currentMatched = self::v231_rowids($current['matched']);
        $nextMatched = self::v231_rowids($next['matched']);
        $currentCandidates = self::v231_rowids($current['candidates']);
        $nextCandidates = self::v231_rowids($next['candidates']);

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
            'status' => 'utf16-nocase-like-rtrim-current-source-nexttwoThreeOne',
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
            'matchedRetainedRowids' => self::v231_sortedIntersect($currentMatched, $nextMatched),
            'matchedExitedRowids' => self::v231_sortedDiff($currentMatched, $nextMatched),
            'matchedEnteredRowids' => self::v231_sortedDiff($nextMatched, $currentMatched),
            'currentFalsePositiveRowids' => self::v231_rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::v231_rowids($next['falsePositive']),
            'currentNonAsciiCaseVariantRowids' => $current['nonAsciiCaseVariantRowids'],
            'nextNonAsciiCaseVariantRowids' => $next['nonAsciiCaseVariantRowids'],
            'currentAsciiFoldedRowids' => $current['asciiFoldedRowids'],
            'nextAsciiFoldedRowids' => $next['asciiFoldedRowids'],
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'currentDecodedTexts' => self::v231_map($current['decoded'], 'text'),
            'nextDecodedTexts' => self::v231_map($next['decoded'], 'text'),
            'currentRtrimTexts' => self::v231_map($current['decoded'], 'rtrimText'),
            'nextRtrimTexts' => self::v231_map($next['decoded'], 'rtrimText'),
            'currentNocaseKeys' => self::v231_map($current['decoded'], 'nocaseKey'),
            'nextNocaseKeys' => self::v231_map($next['decoded'], 'nocaseKey'),
            'currentNonAsciiCaseClasses' => self::v231_map($current['decoded'], 'nonAsciiCaseClass'),
            'nextNonAsciiCaseClasses' => self::v231_map($next['decoded'], 'nonAsciiCaseClass'),
            'currentResidualMatches' => self::v231_map($current['candidates'], 'residualMatch'),
            'nextResidualMatches' => self::v231_map($next['candidates'], 'residualMatch'),
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
                'sqlite-current-source-nexttwoThreeOne',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE prefix planning, RTRIM expression keys, and residual matching',
            'non_overlap' => 'nextTwoThreeOne covers non-ASCII case variants that remain distinct under UTF-16 NOCASE LIKE after RTRIM; avoids accepted nextTwoTwoSeven ASCII-space RTRIM boundary, nextTwoTwoSix combining-mark normalization, nextTwoTwoFive raw source bytes, nextTwoOneNine supplementary wildcard matching, Unicode GLOB ranges, and malformed UTF-16 insert guards',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{decoded:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,nonAsciiCaseVariantRowids:list<int>,asciiFoldedRowids:list<int>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function v231_scan(array $rows, string $pattern, ?string $escape, ?array $range): array
    {
        $decoded = [];
        $nonAsciiVariants = [];
        $asciiFolded = [];
        $malformed = [];
        $errors = [];

        foreach ($rows as $row) {
            self::v231_assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
                $rtrim = rtrim($text, ' ');
                $nocaseKey = self::v231_asciiLower($rtrim);
                $class = self::v231_nonAsciiCaseClass($rtrim);
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

        usort($decoded, self::v231_sortRows(...));
        sort($nonAsciiVariants);
        sort($asciiFolded);
        sort($malformed);
        ksort($errors);

        $candidates = [];
        $matched = [];
        $falsePositive = [];
        foreach ($decoded as $entry) {
            if (!self::v231_inRange($entry['nocaseKey'], $range)) {
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
    private static function v231_assertRow(array $row): void
    {
        if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoThreeOne rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoThreeOne rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoThreeOne rows require integer text_encoding');
        }
    }

    private static function v231_nonAsciiCaseClass(string $text): string
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
    private static function v231_inRange(string $key, ?array $range): bool
    {
        if ($range === null || strcmp($key, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0;
    }

    /** @param array{nocaseKey:string,rowid:int} $left @param array{nocaseKey:string,rowid:int} $right */
    private static function v231_sortRows(array $left, array $right): int
    {
        $comparison = strcmp($left['nocaseKey'], $right['nocaseKey']);

        return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
    }

    /** @param list<array{rowid:int}> $rows @return list<int> */
    private static function v231_rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $rows @return array<int,mixed> */
    private static function v231_map(array $rows, string $key): array
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
    private static function v231_changes(array $current, array $next): array
    {
        $currentById = self::v231_byRowid($current);
        $nextById = self::v231_byRowid($next);
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
    private static function v231_byRowid(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['rowid']] = $row;
        }

        return $indexed;
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function v231_sortedIntersect(array $left, array $right): array
    {
        $values = array_values(array_intersect($left, $right));
        sort($values);

        return $values;
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function v231_sortedDiff(array $left, array $right): array
    {
        $values = array_values(array_diff($left, $right));
        sort($values);

        return $values;
    }

    private static function v231_asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /* Consolidated from SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php. */

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyCanonicalUnicodePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_caf_',
        ?string $escape = '!',
        string $currentSource = 'main.app_settings@232',
        string $nextSource = 'main.app_settings@233',
        int $currentSchemaCookie = 232,
        int $nextSchemaCookie = 233,
    ): array {
        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $current = self::v233_scan($currentRows, $pattern, $escape, $like['range']);
        $next = self::v233_scan($nextRows, $pattern, $escape, $like['range']);
        $changes = self::v233_changes($current['decoded'], $next['decoded']);
        $changes['residualChangedRowids'] = self::v233_residualChanges($current['candidates'], $next['candidates']);

        $currentCanonicalPeers = self::v233_canonicalPeerRowsets($current['decoded']);
        $nextCanonicalPeers = self::v233_canonicalPeerRowsets($next['decoded']);
        $currentCombiningMatched = self::v233_intersectSorted(self::v233_rowids($current['matched']), $current['combiningMarkRowids']);
        $nextCombiningMatched = self::v233_intersectSorted(self::v233_rowids($next['matched']), $next['combiningMarkRowids']);

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
            'unicode-codepoint-count' => $changes['codepointChangedRowids'],
            'utf16-code-units' => $changes['utf16CodeUnitChangedRowids'],
            'residual-result' => $changes['residualChangedRowids'],
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }
        if ($current['canonicalEquivalentRowids'] !== $next['canonicalEquivalentRowids']) {
            $reasons[] = 'canonical-equivalent-rowset';
        }
        if ($current['combiningMarkRowids'] !== $next['combiningMarkRowids']) {
            $reasons[] = 'combining-mark-rowset';
        }
        if ($current['singleWildcardFalsePositiveRowids'] !== [] || $next['singleWildcardFalsePositiveRowids'] !== []) {
            $reasons[] = 'single-wildcard-codepoint-boundary';
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        if (self::v233_rowids($current['candidates']) !== self::v233_rowids($next['candidates'])) {
            $reasons[] = 'candidate-rowset';
        }
        if (self::v233_rowids($current['matched']) !== self::v233_rowids($next['matched'])) {
            $reasons[] = 'matched-rowset';
        }
        if (self::v233_rowsetMap($currentCanonicalPeers) !== self::v233_rowsetMap($nextCanonicalPeers)) {
            $reasons[] = 'canonical-peer-rowset';
        }

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nexttwoThreeThree',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* no Unicode normalization */',
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
            'currentCandidateRowids' => self::v233_rowids($current['candidates']),
            'nextCandidateRowids' => self::v233_rowids($next['candidates']),
            'currentMatchedRowids' => self::v233_rowids($current['matched']),
            'nextMatchedRowids' => self::v233_rowids($next['matched']),
            'matchedRetainedRowids' => self::v233_intersectSorted(self::v233_rowids($current['matched']), self::v233_rowids($next['matched'])),
            'matchedExitedRowids' => self::v233_diffSorted(self::v233_rowids($current['matched']), self::v233_rowids($next['matched'])),
            'matchedEnteredRowids' => self::v233_diffSorted(self::v233_rowids($next['matched']), self::v233_rowids($current['matched'])),
            'currentFalsePositiveRowids' => self::v233_rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::v233_rowids($next['falsePositive']),
            'currentCombiningMarkRowids' => $current['combiningMarkRowids'],
            'nextCombiningMarkRowids' => $next['combiningMarkRowids'],
            'currentPrecomposedAccentRowids' => $current['precomposedAccentRowids'],
            'nextPrecomposedAccentRowids' => $next['precomposedAccentRowids'],
            'currentCanonicalEquivalentRowids' => $current['canonicalEquivalentRowids'],
            'nextCanonicalEquivalentRowids' => $next['canonicalEquivalentRowids'],
            'currentCombiningMatchedRowids' => $currentCombiningMatched,
            'nextCombiningMatchedRowids' => $nextCombiningMatched,
            'currentSingleWildcardFalsePositiveRowids' => $current['singleWildcardFalsePositiveRowids'],
            'nextSingleWildcardFalsePositiveRowids' => $next['singleWildcardFalsePositiveRowids'],
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'currentRtrimTexts' => self::v233_map($current['decoded'], 'rtrimText'),
            'nextRtrimTexts' => self::v233_map($next['decoded'], 'rtrimText'),
            'currentNocaseKeys' => self::v233_map($current['decoded'], 'nocaseKey'),
            'nextNocaseKeys' => self::v233_map($next['decoded'], 'nocaseKey'),
            'currentCodepointCounts' => self::v233_map($current['decoded'], 'codepointCount'),
            'nextCodepointCounts' => self::v233_map($next['decoded'], 'codepointCount'),
            'currentUtf16CodeUnitCounts' => self::v233_map($current['decoded'], 'utf16CodeUnits'),
            'nextUtf16CodeUnitCounts' => self::v233_map($next['decoded'], 'utf16CodeUnits'),
            'currentCanonicalKeys' => self::v233_map($current['decoded'], 'canonicalKey'),
            'nextCanonicalKeys' => self::v233_map($next['decoded'], 'canonicalKey'),
            'currentResidualMatches' => self::v233_map($current['candidates'], 'residualMatch'),
            'nextResidualMatches' => self::v233_map($next['candidates'], 'residualMatch'),
            'currentCanonicalPeerRowids' => $currentCanonicalPeers,
            'nextCanonicalPeerRowids' => $nextCanonicalPeers,
            'changedTextRowids' => $changes['textChangedRowids'],
            'changedRtrimRowids' => $changes['rtrimChangedRowids'],
            'changedNocaseKeyRowids' => $changes['nocaseKeyChangedRowids'],
            'changedCodepointRowids' => $changes['codepointChangedRowids'],
            'changedUtf16CodeUnitRowids' => $changes['utf16CodeUnitChangedRowids'],
            'changedResidualRowids' => $changes['residualChangedRowids'],
            'likeUnderscoreConsumesOneUnicodeCodepoint' => true,
            'combiningMarkIsSeparateLikeCharacter' => true,
            'unicodeNormalizationApplied' => false,
            'canonicalEquivalentTextMayCompareDistinct' => true,
            'nocaseFoldsAsciiOnly' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'invalidationReasons' => array_values(array_unique($reasons)),
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-nocase-prefix-range',
                'sqlite-rtrim-expression-key',
                'sqlite-unicode-codepoint-like',
                'sqlite-current-source-nexttwoThreeThree',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE prefix planning, RTRIM expression keys, and Unicode codepoint splitting',
            'non_overlap' => 'nextTwoThreeThree covers canonical-equivalent precomposed/decomposed Unicode text under UTF-16 NOCASE/RTRIM LIKE without normalization; avoids accepted Unicode GLOB ranges, UTF-16 malformed insert guards, non-ASCII prefix fallback, non-ASCII whitespace RTRIM, supplementary-plane wildcard, and storage/JSON/SQL planner clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param ?array{lowerInclusive:string,upperBound:?string} $range
     * @return array{decoded:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,combiningMarkRowids:list<int>,precomposedAccentRowids:list<int>,canonicalEquivalentRowids:list<int>,singleWildcardFalsePositiveRowids:list<int>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function v233_scan(array $rows, string $pattern, ?string $escape, ?array $range): array
    {
        $decoded = [];
        $combining = [];
        $precomposed = [];
        $canonicalEquivalent = [];
        $malformed = [];
        $errors = [];

        foreach ($rows as $row) {
            self::v233_assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
                $rtrim = rtrim($text, ' ');
                $canonicalKey = self::v233_canonicalAccentKey($rtrim);
                $hasCombining = self::v233_hasCombiningMark($rtrim);
                $hasPrecomposed = str_contains($rtrim, "\u{00e9}") || str_contains($rtrim, "\u{00c9}");
                if ($hasCombining) {
                    $combining[] = $row['option_id'];
                }
                if ($hasPrecomposed) {
                    $precomposed[] = $row['option_id'];
                }
                if ($hasCombining || $hasPrecomposed) {
                    $canonicalEquivalent[] = $row['option_id'];
                }
                $decoded[] = [
                    'rowid' => $row['option_id'],
                    'text' => $text,
                    'rtrimText' => $rtrim,
                    'nocaseKey' => self::v233_asciiLower($rtrim),
                    'codepointCount' => self::v233_codepointCount($rtrim),
                    'utf16CodeUnits' => self::v233_utf16CodeUnits($rtrim),
                    'canonicalKey' => $canonicalKey,
                    'hasCombiningMark' => $hasCombining,
                    'hasPrecomposedAccent' => $hasPrecomposed,
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['option_id'];
                $errors[$row['option_id']] = $exception->getMessage();
            }
        }

        usort($decoded, self::v233_sortRows(...));
        sort($combining);
        sort($precomposed);
        sort($canonicalEquivalent);
        sort($malformed);
        ksort($errors);

        $candidates = [];
        $matched = [];
        $falsePositive = [];
        $singleWildcardFalsePositive = [];
        foreach ($decoded as $entry) {
            if (!self::v233_inRange($entry['nocaseKey'], $range)) {
                continue;
            }
            $entry['residualMatch'] = SQLiteDatabase::likeMatches($entry['rtrimText'], $pattern, $escape, false);
            $entry['singleWildcardFalsePositive'] = !$entry['residualMatch'] && $entry['hasCombiningMark'];
            $candidates[] = $entry;
            if ($entry['residualMatch']) {
                $matched[] = $entry;
            } else {
                $falsePositive[] = $entry;
                if ($entry['singleWildcardFalsePositive']) {
                    $singleWildcardFalsePositive[] = $entry['rowid'];
                }
            }
        }
        sort($singleWildcardFalsePositive);

        return [
            'decoded' => $decoded,
            'candidates' => $candidates,
            'matched' => $matched,
            'falsePositive' => $falsePositive,
            'combiningMarkRowids' => $combining,
            'precomposedAccentRowids' => $precomposed,
            'canonicalEquivalentRowids' => $canonicalEquivalent,
            'singleWildcardFalsePositiveRowids' => $singleWildcardFalsePositive,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row */
    private static function v233_assertRow(array $row): void
    {
        if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoThreeThree rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoThreeThree rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoThreeThree rows require integer text_encoding');
        }
    }

    /** @param ?array{lowerInclusive:string,upperBound:?string} $range */
    private static function v233_inRange(string $key, ?array $range): bool
    {
        if ($range === null || strcmp($key, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0;
    }

    /** @param array{nocaseKey:string,rowid:int} $left @param array{nocaseKey:string,rowid:int} $right */
    private static function v233_sortRows(array $left, array $right): int
    {
        $comparison = strcmp($left['nocaseKey'], $right['nocaseKey']);

        return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
    }

    /** @param list<array{rowid:int}> $rows @return list<int> */
    private static function v233_rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $rows @return array<int,mixed> */
    private static function v233_map(array $rows, string $key): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[$row['rowid']] = $row[$key];
        }

        return $mapped;
    }

    /** @param list<array<string,mixed>> $left @param list<array<string,mixed>> $right @return array<string,list<int>> */
    private static function v233_changes(array $left, array $right): array
    {
        $leftById = self::v233_byRowid($left);
        $rightById = self::v233_byRowid($right);
        $rowids = array_values(array_unique(array_merge(array_keys($leftById), array_keys($rightById))));
        sort($rowids);
        $changes = [
            'textChangedRowids' => [],
            'rtrimChangedRowids' => [],
            'nocaseKeyChangedRowids' => [],
            'codepointChangedRowids' => [],
            'utf16CodeUnitChangedRowids' => [],
        ];
        foreach ($rowids as $rowid) {
            $leftRow = $leftById[$rowid] ?? null;
            $rightRow = $rightById[$rowid] ?? null;
            foreach ([
                'textChangedRowids' => 'text',
                'rtrimChangedRowids' => 'rtrimText',
                'nocaseKeyChangedRowids' => 'nocaseKey',
                'codepointChangedRowids' => 'codepointCount',
                'utf16CodeUnitChangedRowids' => 'utf16CodeUnits',
            ] as $bucket => $key) {
                if (($leftRow[$key] ?? null) !== ($rightRow[$key] ?? null)) {
                    $changes[$bucket][] = (int) $rowid;
                }
            }
        }

        return $changes;
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function v233_byRowid(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['rowid']] = $row;
        }

        ksort($indexed);

        return $indexed;
    }

    /** @param list<array<string,mixed>> $left @param list<array<string,mixed>> $right @return list<int> */
    private static function v233_residualChanges(array $left, array $right): array
    {
        $leftById = self::v233_byRowid($left);
        $rightById = self::v233_byRowid($right);
        $rowids = array_values(array_unique(array_merge(array_keys($leftById), array_keys($rightById))));
        sort($rowids);
        $changed = [];
        foreach ($rowids as $rowid) {
            if (($leftById[$rowid]['residualMatch'] ?? null) !== ($rightById[$rowid]['residualMatch'] ?? null)) {
                $changed[] = (int) $rowid;
            }
        }

        return $changed;
    }

    private static function v233_asciiLower(string $text): string
    {
        return strtolower($text);
    }

    private static function v233_canonicalAccentKey(string $text): string
    {
        return str_replace(["\u{00c9}", "\u{00e9}", "E\u{0301}", "e\u{0301}"], ['e', 'e', 'e', 'e'], self::v233_asciiLower($text));
    }

    private static function v233_hasCombiningMark(string $text): bool
    {
        return preg_match('/\p{M}/u', $text) === 1;
    }

    private static function v233_codepointCount(string $text): int
    {
        return count(self::v233_characters($text));
    }

    private static function v233_utf16CodeUnits(string $text): int
    {
        return intdiv(strlen(SQLiteEncodingCollationSourceCursor::encodeText($text, 'UTF-16LE')), 2);
    }

    /** @return list<string> */
    private static function v233_characters(string $text): array
    {
        if ($text === '') {
            return [];
        }

        return preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /** @param list<array<string,mixed>> $rows @return array<string,list<int>> */
    private static function v233_canonicalPeerRowsets(array $rows): array
    {
        $peers = [];
        foreach ($rows as $row) {
            $peers[$row['canonicalKey']] ??= [];
            $peers[$row['canonicalKey']][] = (int) $row['rowid'];
        }
        foreach ($peers as &$rowids) {
            sort($rowids);
        }
        unset($rowids);
        ksort($peers);

        return array_filter($peers, static fn (array $rowids): bool => count($rowids) > 1);
    }

    /** @param array<string,list<int>> $rowsets @return array<string,list<int>> */
    private static function v233_rowsetMap(array $rowsets): array
    {
        foreach ($rowsets as &$rowids) {
            sort($rowids);
        }
        unset($rowids);
        ksort($rowsets);

        return $rowsets;
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function v233_intersectSorted(array $left, array $right): array
    {
        $values = array_values(array_intersect($left, $right));
        sort($values);

        return $values;
    }

    /** @param list<int> $left @param list<int> $right @return list<int> */
    private static function v233_diffSorted(array $left, array $right): array
    {
        $values = array_values(array_diff($left, $right));
        sort($values);

        return $values;
    }

    /* Consolidated into stable UTF-16 NOCASE LIKE RTRIM current-source helper. */
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyNullPatternRebindPlan(
        array $currentRows,
        array $nextRows,
        ?string $currentPattern = 'plugin!_cache%',
        ?string $nextPattern = null,
        ?string $escape = '!',
        string $currentSource = 'main.app_settings@200',
        string $nextSource = 'main.app_settings@201',
        int $currentSchemaCookie = 200,
        int $nextSchemaCookie = 201,
    ): array {
        $current = self::scanNullPatternRebindRows($currentRows, $currentPattern, $escape);
        $next = self::scanNullPatternRebindRows($nextRows, $nextPattern, $escape);
        $currentMatched = self::nullPatternRebindRowids($current['matched']);
        $nextMatched = self::nullPatternRebindRowids($next['matched']);
        $currentCandidates = self::nullPatternRebindRowids($current['candidates']);
        $nextCandidates = self::nullPatternRebindRowids($next['candidates']);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        if ($currentPattern !== $nextPattern) {
            $reasons[] = 'pattern-rebound';
        }
        if ($currentPattern !== $nextPattern && ($currentPattern === null || $nextPattern === null)) {
            $reasons[] = 'null-like-pattern';
        }
        if (($current['likePlan']['range'] ?? null) !== ($next['likePlan']['range'] ?? null)) {
            $reasons[] = 'like-range';
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
        $reasons = array_values(array_unique($reasons));

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nexttwoZeroOne',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* NULL pattern rebind */',
            'currentPattern' => $currentPattern,
            'nextPattern' => $nextPattern,
            'escape' => $escape,
            'collation' => 'NOCASE',
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentPatternIsSqlNull' => $currentPattern === null,
            'nextPatternIsSqlNull' => $nextPattern === null,
            'currentLikeResultIsNull' => $currentPattern === null,
            'nextLikeResultIsNull' => $nextPattern === null,
            'currentPrefix' => $current['likePlan']['prefix'],
            'nextPrefix' => $next['likePlan']['prefix'],
            'currentRangeLowerInclusive' => $current['likePlan']['range']['lowerInclusive'] ?? null,
            'currentRangeUpperBound' => $current['likePlan']['range']['upperBound'] ?? null,
            'nextRangeLowerInclusive' => $next['likePlan']['range']['lowerInclusive'] ?? null,
            'nextRangeUpperBound' => $next['likePlan']['range']['upperBound'] ?? null,
            'currentIndexUsable' => $current['likePlan']['indexUsable'],
            'nextIndexUsable' => $next['likePlan']['indexUsable'],
            'currentCandidateRowids' => $currentCandidates,
            'nextCandidateRowids' => $nextCandidates,
            'candidateExitedRowids' => array_values(array_diff($currentCandidates, $nextCandidates)),
            'candidateEnteredRowids' => array_values(array_diff($nextCandidates, $currentCandidates)),
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'matchedExitedRowids' => array_values(array_diff($currentMatched, $nextMatched)),
            'matchedEnteredRowids' => array_values(array_diff($nextMatched, $currentMatched)),
            'currentFalsePositiveRowids' => self::nullPatternRebindRowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::nullPatternRebindRowids($next['falsePositive']),
            'currentExcludedDecodedRowids' => array_values(array_diff(self::nullPatternRebindRowids($current['decoded']), $currentCandidates)),
            'nextExcludedDecodedRowids' => array_values(array_diff(self::nullPatternRebindRowids($next['decoded']), $nextCandidates)),
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'currentRtrimTexts' => self::mapNullPatternRebindRows($current['decoded'], 'rtrimText'),
            'nextRtrimTexts' => self::mapNullPatternRebindRows($next['decoded'], 'rtrimText'),
            'currentNocaseKeys' => self::mapNullPatternRebindRows($current['decoded'], 'nocaseKey'),
            'nextNocaseKeys' => self::mapNullPatternRebindRows($next['decoded'], 'nocaseKey'),
            'currentMatchedTexts' => self::selectNullPatternRebindMap(self::mapNullPatternRebindRows($current['decoded'], 'rtrimText'), $currentMatched),
            'nextMatchedTexts' => self::selectNullPatternRebindMap(self::mapNullPatternRebindRows($next['decoded'], 'rtrimText'), $nextMatched),
            'mustReprepareForNullPattern' => $currentPattern !== $nextPattern && ($currentPattern === null || $nextPattern === null),
            'nullPatternDisablesPrefixRange' => $currentPattern === null || $nextPattern === null,
            'nullPatternMatchesNoRows' => $currentPattern === null || $nextPattern === null,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'staleRangeCursorRisk' => $nextPattern === null && $currentCandidates !== [],
            'invalidationReasons' => $reasons,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-null-pattern-rebind',
                'sqlite-rtrim-expression-key',
                'sqlite-current-source-nexttwoZeroOne',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, LIKE NULL-result semantics, RTRIM keys, and current-source cursor invalidation diagnostics',
            'non_overlap' => 'nextTwoZeroOne adds prepared LIKE pattern SQL NULL rebind fencing for UTF-16 RTRIM/NOCASE current-source cursors; avoids accepted escape rebind nextTwoZeroZero, escaped wildcard nextOneNineFour, prepared byte rebind nextOneNineOne, Unicode GLOB ranges, malformed UTF-16 insert guards, and storage/planner clusters',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{likePlan:array<string,mixed>,decoded:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function scanNullPatternRebindRows(array $rows, ?string $pattern, ?string $escape): array
    {
        $like = $pattern === null
            ? [
                'prefix' => null,
                'range' => null,
                'indexUsable' => false,
                'rejectedReason' => 'null_like_pattern',
            ]
            : SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $decoded = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            self::assertNullPatternRebindRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
                $rtrim = rtrim($text, ' ');
                $decoded[] = [
                    'rowid' => $row['option_id'],
                    'text' => $text,
                    'rtrimText' => $rtrim,
                    'nocaseKey' => self::asciiLowerNullPatternRebindKey($rtrim),
                    'bytesHex' => bin2hex($row['option_name_bytes']),
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['option_id'];
                $errors[$row['option_id']] = $exception->getMessage();
            }
        }

        usort($decoded, self::sortNullPatternRebindRows(...));
        sort($malformed);
        ksort($errors);

        $candidates = [];
        $matched = [];
        $falsePositive = [];
        foreach ($decoded as $entry) {
            if ($pattern === null || !self::nullPatternRebindKeyInRange($entry['nocaseKey'], $like['range'])) {
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
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row */
    private static function assertNullPatternRebindRow(array $row): void
    {
        if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoZeroOne rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoZeroOne rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoZeroOne rows require integer text_encoding');
        }
    }

    /** @param ?array{lowerInclusive:string,upperBound:?string} $range */
    private static function nullPatternRebindKeyInRange(string $key, ?array $range): bool
    {
        if ($range === null || strcmp($key, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0;
    }

    /** @param array{nocaseKey:string,rowid:int} $left @param array{nocaseKey:string,rowid:int} $right */
    private static function sortNullPatternRebindRows(array $left, array $right): int
    {
        $comparison = strcmp($left['nocaseKey'], $right['nocaseKey']);

        return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
    }

    /** @param list<array{rowid:int}> $rows @return list<int> */
    private static function nullPatternRebindRowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $rows @return array<int,mixed> */
    private static function mapNullPatternRebindRows(array $rows, string $key): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[$row['rowid']] = $row[$key];
        }

        return $mapped;
    }

    /** @param array<int,string> $values @param list<int> $rowids @return array<int,string> */
    private static function selectNullPatternRebindMap(array $values, array $rowids): array
    {
        $selected = [];
        foreach ($rowids as $rowid) {
            if (array_key_exists($rowid, $values)) {
                $selected[$rowid] = $values[$rowid];
            }
        }

        return $selected;
    }

    private static function asciiLowerNullPatternRebindKey(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }


    /* Consolidated into stable UTF-16 NOCASE LIKE RTRIM current-source helper. */
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyNonAsciiPrefixFullScanPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_é%',
        ?string $escape = '!',
        string $currentSource = 'main.app_settings@203',
        string $nextSource = 'main.app_settings@204',
        int $currentSchemaCookie = 203,
        int $nextSchemaCookie = 204,
    ): array {
        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        if ($like['rejectedReason'] !== 'nocase_like_prefix_must_be_ascii_for_range') {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoZeroFour expects a non-ASCII NOCASE LIKE prefix');
        }

        $current = self::v204_scan($currentRows, $pattern, $escape);
        $next = self::v204_scan($nextRows, $pattern, $escape);
        $currentMatched = self::v204_rowids($current['matched']);
        $nextMatched = self::v204_rowids($next['matched']);
        $changes = self::v204_changes($current['decoded'], $next['decoded']);

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($currentSchemaCookie !== $nextSchemaCookie) {
            $reasons[] = 'schema-cookie';
        }
        $reasons[] = 'non-ascii-nocase-prefix-full-scan';
        foreach ([
            'decoded-text' => $changes['textChangedRowids'],
            'rtrim-expression' => $changes['rtrimChangedRowids'],
            'nocase-key' => $changes['nocaseKeyChangedRowids'],
            'encoded-bytes' => $changes['bytesChangedRowids'],
        ] as $reason => $rowids) {
            if ($rowids !== []) {
                $reasons[] = $reason;
            }
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $reasons[] = 'malformed-text';
        }
        if ($currentMatched !== $nextMatched) {
            $reasons[] = 'matched-rowset';
        }

        return [
            'status' => 'utf16-nocase-like-rtrim-current-source-nexttwoZeroFour',
            'operator' => 'LIKE',
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* non-ASCII prefix full scan */',
            'pattern' => $pattern,
            'escape' => $escape,
            'collation' => 'NOCASE',
            'caseSensitiveLike' => false,
            'likePlan' => $like,
            'prefix' => $like['prefix'],
            'prefixIsAscii' => $like['prefixIsAscii'],
            'rangeLowerInclusive' => null,
            'rangeUpperBound' => null,
            'indexUsable' => false,
            'usesPrefixRangeCursor' => false,
            'usesFullScanFallback' => true,
            'rejectedReason' => $like['rejectedReason'],
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentDecodedRowids' => self::v204_rowids($current['decoded']),
            'nextDecodedRowids' => self::v204_rowids($next['decoded']),
            'currentCandidateRowids' => self::v204_rowids($current['decoded']),
            'nextCandidateRowids' => self::v204_rowids($next['decoded']),
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'currentFullScanRejectedRowids' => self::v204_rowids($current['rejected']),
            'nextFullScanRejectedRowids' => self::v204_rowids($next['rejected']),
            'matchedRetainedRowids' => self::v204_retained($currentMatched, $nextMatched),
            'matchedExitedRowids' => self::v204_exited($currentMatched, $nextMatched),
            'matchedEnteredRowids' => self::v204_entered($currentMatched, $nextMatched),
            'currentTexts' => self::v204_map($current['decoded'], 'text'),
            'nextTexts' => self::v204_map($next['decoded'], 'text'),
            'currentRtrimTexts' => self::v204_map($current['decoded'], 'rtrimText'),
            'nextRtrimTexts' => self::v204_map($next['decoded'], 'rtrimText'),
            'currentNocaseKeys' => self::v204_map($current['decoded'], 'nocaseKey'),
            'nextNocaseKeys' => self::v204_map($next['decoded'], 'nocaseKey'),
            'currentMatchedTexts' => self::v204_map($current['matched'], 'rtrimText'),
            'nextMatchedTexts' => self::v204_map($next['matched'], 'rtrimText'),
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'changedTextRowids' => $changes['textChangedRowids'],
            'changedRtrimRowids' => $changes['rtrimChangedRowids'],
            'changedNocaseKeyRowids' => $changes['nocaseKeyChangedRowids'],
            'changedBytesRowids' => $changes['bytesChangedRowids'],
            'cursorInvalidated' => true,
            'cursorReusable' => false,
            'invalidationReasons' => array_values(array_unique($reasons)),
            'likeResidualAppliesAfterRtrim' => true,
            'nonAsciiPrefixRequiresFullScan' => true,
            'asciiNocaseOnlyKeepsAccentCaseDistinct' => true,
            'malformedRowsDoNotAbortFullScan' => true,
            'rtrimTrimsOnlyAsciiSpace' => true,
            'nocaseFoldsAsciiOnly' => true,
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-nocase-non-ascii-prefix-full-scan',
                'sqlite-rtrim-residual-match',
                'sqlite-current-source-nexttwoZeroFour',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII-only NOCASE LIKE residual matching, RTRIM keys, and current-source diagnostics',
            'non_overlap' => 'nextTwoZeroFour covers non-ASCII fixed-prefix NOCASE LIKE fallback over UTF-16 RTRIM rows; avoids nextTwoZeroThree no-fixed-prefix fallback, nextTwoZeroTwo source-row patterns, nextTwoZeroZero ESCAPE rebinds, Unicode GLOB ranges, and malformed UTF-16 insert guards',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{decoded:list<array<string,mixed>>,matched:list<array<string,mixed>>,rejected:list<array<string,mixed>>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function v204_scan(array $rows, string $pattern, ?string $escape): array
    {
        $decoded = [];
        $malformed = [];
        $errors = [];
        foreach ($rows as $row) {
            self::v204_assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
                $rtrim = rtrim($text, ' ');
                $decoded[] = [
                    'rowid' => $row['option_id'],
                    'text' => $text,
                    'rtrimText' => $rtrim,
                    'nocaseKey' => self::v204_asciiLower($rtrim),
                    'bytesHex' => bin2hex($row['option_name_bytes']),
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['option_id'];
                $errors[$row['option_id']] = $exception->getMessage();
            }
        }

        usort($decoded, self::v204_sortRows(...));
        sort($malformed);
        ksort($errors);

        $matched = [];
        $rejected = [];
        foreach ($decoded as $entry) {
            if (SQLiteDatabase::likeMatches($entry['rtrimText'], $pattern, $escape, false)) {
                $matched[] = $entry;
            } else {
                $rejected[] = $entry;
            }
        }

        return [
            'decoded' => $decoded,
            'matched' => $matched,
            'rejected' => $rejected,
            'malformedRowids' => $malformed,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $row */
    private static function v204_assertRow(array $row): void
    {
        if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoZeroFour rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoZeroFour rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoZeroFour rows require integer text_encoding');
        }
    }

    /** @param array{nocaseKey:string,rowid:int} $left @param array{nocaseKey:string,rowid:int} $right */
    private static function v204_sortRows(array $left, array $right): int
    {
        $comparison = strcmp($left['nocaseKey'], $right['nocaseKey']);

        return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
    }

    /** @param list<array{rowid:int}> $rows @return list<int> */
    private static function v204_rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $rows @return array<int,mixed> */
    private static function v204_map(array $rows, string $key): array
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
     * @return array<string,list<int>>
     */
    private static function v204_changes(array $current, array $next): array
    {
        $currentByRowid = self::v204_byRowid($current);
        $nextByRowid = self::v204_byRowid($next);

        return [
            'textChangedRowids' => self::v204_changed($currentByRowid, $nextByRowid, 'text'),
            'rtrimChangedRowids' => self::v204_changed($currentByRowid, $nextByRowid, 'rtrimText'),
            'nocaseKeyChangedRowids' => self::v204_changed($currentByRowid, $nextByRowid, 'nocaseKey'),
            'bytesChangedRowids' => self::v204_changed($currentByRowid, $nextByRowid, 'bytesHex'),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private static function v204_byRowid(array $rows): array
    {
        $byRowid = [];
        foreach ($rows as $row) {
            $byRowid[$row['rowid']] = $row;
        }

        return $byRowid;
    }

    /** @param array<int,array<string,mixed>> $current @param array<int,array<string,mixed>> $next @return list<int> */
    private static function v204_changed(array $current, array $next, string $key): array
    {
        $rowids = array_values(array_intersect(array_keys($current), array_keys($next)));
        sort($rowids);
        $changed = [];
        foreach ($rowids as $rowid) {
            if (($current[$rowid][$key] ?? null) !== ($next[$rowid][$key] ?? null)) {
                $changed[] = (int) $rowid;
            }
        }

        return $changed;
    }

    /** @param list<int> $current @param list<int> $next @return list<int> */
    private static function v204_retained(array $current, array $next): array
    {
        return array_values(array_intersect($current, $next));
    }

    /** @param list<int> $current @param list<int> $next @return list<int> */
    private static function v204_exited(array $current, array $next): array
    {
        return array_values(array_diff($current, $next));
    }

    /** @param list<int> $current @param list<int> $next @return list<int> */
    private static function v204_entered(array $current, array $next): array
    {
        return array_values(array_diff($next, $current));
    }

    private static function v204_asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }


    /* Consolidated into stable UTF-16 NOCASE LIKE RTRIM current-source helper. */
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array{key:string,rowid:int}|null $resumeToken
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyEmbeddedNulTokenPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_cache%',
        ?string $escape = '!',
        ?array $resumeToken = ['key' => "plugin_cache\0shadow", 'rowid' => 4],
        string $currentSource = 'main.app_settings@214',
        string $nextSource = 'main.app_settings@215',
        int $currentSchemaCookie = 214,
        int $nextSchemaCookie = 215,
    ): array {
        $current = self::v215_scan($currentRows, $pattern, $escape);
        $next = self::v215_scan($nextRows, $pattern, $escape);
        $token = self::v215_normalizeToken($resumeToken);

        $currentMatched = self::v215_rowids($current['matched']);
        $nextMatched = self::v215_rowids($next['matched']);
        $matchedExited = array_values(array_diff($currentMatched, $nextMatched));
        $matchedEntered = array_values(array_diff($nextMatched, $currentMatched));
        sort($matchedExited);
        sort($matchedEntered);

        $currentBefore = self::v215_beforeOrAtToken($current['candidates'], $token);
        $nextBefore = self::v215_beforeOrAtToken($next['candidates'], $token);
        $currentAfter = self::v215_afterToken($current['candidates'], $token);
        $nextAfter = self::v215_afterToken($next['candidates'], $token);
        $currentMatchedBefore = self::v215_beforeOrAtToken($current['matched'], $token);
        $nextMatchedBefore = self::v215_beforeOrAtToken($next['matched'], $token);
        $truncatedCollisions = self::v215_truncatedKeyCollisions($current['decoded'], $next['decoded']);

        $unsafe = [];
        if ($currentSource !== $nextSource || $currentSchemaCookie !== $nextSchemaCookie) {
            $unsafe[] = 'source-or-schema-changed';
        }
        if ($current['malformedRowids'] !== [] || $next['malformedRowids'] !== []) {
            $unsafe[] = 'malformed-text';
        }
        if (self::v215_rowids($currentBefore) !== self::v215_rowids($nextBefore)) {
            $unsafe[] = 'candidate-before-token-changed';
        }
        if (self::v215_rowids($currentMatchedBefore) !== self::v215_rowids($nextMatchedBefore)) {
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
            'status' => 'utf16-nocase-like-rtrim-current-source-nexttwoOneFive',
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
            'currentCandidateRowids' => self::v215_rowids($current['candidates']),
            'nextCandidateRowids' => self::v215_rowids($next['candidates']),
            'currentMatchedRowids' => $currentMatched,
            'nextMatchedRowids' => $nextMatched,
            'matchedExitedRowids' => $matchedExited,
            'matchedEnteredRowids' => $matchedEntered,
            'currentFalsePositiveRowids' => self::v215_rowids($current['falsePositive']),
            'nextFalsePositiveRowids' => self::v215_rowids($next['falsePositive']),
            'currentCandidateBeforeOrAtTokenRowids' => self::v215_rowids($currentBefore),
            'nextCandidateBeforeOrAtTokenRowids' => self::v215_rowids($nextBefore),
            'currentReplayAfterTokenRowids' => self::v215_rowids($currentAfter),
            'nextReplayAfterTokenRowids' => self::v215_rowids($nextAfter),
            'currentMatchedBeforeTokenRowids' => self::v215_rowids($currentMatchedBefore),
            'nextMatchedBeforeTokenRowids' => self::v215_rowids($nextMatchedBefore),
            'currentEmbeddedNulRowids' => $current['embeddedNulRowids'],
            'nextEmbeddedNulRowids' => $next['embeddedNulRowids'],
            'embeddedNulTruncatedKeyCollisionRowids' => $truncatedCollisions,
            'currentMalformedRowids' => $current['malformedRowids'],
            'nextMalformedRowids' => $next['malformedRowids'],
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'currentRtrimTexts' => self::v215_map($current['decoded'], 'rtrimText'),
            'nextRtrimTexts' => self::v215_map($next['decoded'], 'rtrimText'),
            'currentNocaseKeysHex' => self::v215_map($current['decoded'], 'nocaseKeyHex'),
            'nextNocaseKeysHex' => self::v215_map($next['decoded'], 'nocaseKeyHex'),
            'currentTruncatedNocaseKeys' => self::v215_map($current['decoded'], 'truncatedNocaseKey'),
            'nextTruncatedNocaseKeys' => self::v215_map($next['decoded'], 'truncatedNocaseKey'),
            'resumeToken' => $token,
            'candidateTokenUnsafeReasons' => $unsafe,
            'candidateTokenResumeSafe' => $unsafe === [],
            'mustReprepareBeforeCandidateTokenResume' => $unsafe !== [],
            'replayPlanMode' => $unsafe === [] ? 'continue-after-embedded-nul-safe-token' : 'reprepare-from-range-start',
            'replayPlanRowids' => $unsafe === [] ? self::v215_rowids($nextAfter) : self::v215_rowids($next['candidates']),
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
                'sqlite-current-source-nexttwoOneFive',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, NOCASE LIKE range planning, RTRIM expression keys, and current-source token replay diagnostics while preserving embedded NUL bytes as SQLite text',
            'non_overlap' => 'nextTwoOneFive covers embedded-NUL UTF-16 RTRIM/NOCASE LIKE current-source replay token fencing; avoids accepted Unicode GLOB ranges, UTF-16 malformed insert guards, ESCAPE/rtrim rebind slices, JSON/VFS/WAL/B-tree clusters, and storage durability work',
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{likePlan:array<string,mixed>,decoded:list<array<string,mixed>>,candidates:list<array<string,mixed>>,matched:list<array<string,mixed>>,falsePositive:list<array<string,mixed>>,embeddedNulRowids:list<int>,malformedRowids:list<int>,errors:array<int,string>}
     */
    private static function v215_scan(array $rows, string $pattern, ?string $escape): array
    {
        $like = SQLiteLikeCollationPlan::plan($pattern, 'NOCASE', $escape, false);
        $decoded = [];
        $malformed = [];
        $errors = [];
        $embedded = [];

        foreach ($rows as $row) {
            self::v215_assertRow($row);
            try {
                $text = SQLiteEncodingCollationSourceCursor::decodeText($row['option_name_bytes'], $row['text_encoding']);
                $rtrim = rtrim($text, ' ');
                $key = self::v215_asciiLower($rtrim);
                if (str_contains($key, "\0")) {
                    $embedded[] = $row['option_id'];
                }
                $decoded[] = [
                    'rowid' => $row['option_id'],
                    'text' => $text,
                    'rtrimText' => $rtrim,
                    'nocaseKey' => $key,
                    'nocaseKeyHex' => bin2hex($key),
                    'truncatedNocaseKey' => self::v215_truncateAtNul($key),
                ];
            } catch (\InvalidArgumentException $exception) {
                $malformed[] = $row['option_id'];
                $errors[$row['option_id']] = $exception->getMessage();
            }
        }

        usort($decoded, self::v215_sortRows(...));
        sort($embedded);
        sort($malformed);
        ksort($errors);

        $candidates = [];
        $matched = [];
        $falsePositive = [];
        foreach ($decoded as $entry) {
            if (!self::v215_inRange($entry['nocaseKey'], $like['range'])) {
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
    private static function v215_assertRow(array $row): void
    {
        if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoOneFive rows require integer option_id');
        }
        if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoOneFive rows require option_name_bytes');
        }
        if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
            throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM nextTwoOneFive rows require integer text_encoding');
        }
    }

    /** @param array{key:string,rowid:int}|null $token @return array{key:string,rowid:int,normalizationReasons:list<string>}|null */
    private static function v215_normalizeToken(?array $token): ?array
    {
        if ($token === null) {
            return null;
        }
        $key = self::v215_asciiLower(rtrim($token['key'], ' '));
        $reasons = [];
        if ($key !== $token['key']) {
            $reasons[] = 'token-key-not-canonical';
        }

        return ['key' => $key, 'rowid' => $token['rowid'], 'normalizationReasons' => $reasons];
    }

    /** @param list<array<string,mixed>> $rows @param array{key:string,rowid:int,normalizationReasons:list<string>}|null $token @return list<array<string,mixed>> */
    private static function v215_beforeOrAtToken(array $rows, ?array $token): array
    {
        if ($token === null) {
            return [];
        }

        return array_values(array_filter($rows, static fn (array $row): bool => self::v215_compareToken($row, $token) <= 0));
    }

    /** @param list<array<string,mixed>> $rows @param array{key:string,rowid:int,normalizationReasons:list<string>}|null $token @return list<array<string,mixed>> */
    private static function v215_afterToken(array $rows, ?array $token): array
    {
        if ($token === null) {
            return $rows;
        }

        return array_values(array_filter($rows, static fn (array $row): bool => self::v215_compareToken($row, $token) > 0));
    }

    /** @param array<string,mixed> $row @param array{key:string,rowid:int,normalizationReasons:list<string>} $token */
    private static function v215_compareToken(array $row, array $token): int
    {
        $comparison = strcmp($row['nocaseKey'], $token['key']);

        return $comparison !== 0 ? $comparison : $row['rowid'] <=> $token['rowid'];
    }

    /** @param list<array<string,mixed>> $current @param list<array<string,mixed>> $next @return list<int> */
    private static function v215_truncatedKeyCollisions(array $current, array $next): array
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
    private static function v215_inRange(string $key, ?array $range): bool
    {
        if ($range === null || strcmp($key, $range['lowerInclusive']) < 0) {
            return false;
        }

        return $range['upperBound'] === null || strcmp($key, $range['upperBound']) < 0;
    }

    /** @param array{nocaseKey:string,rowid:int} $left @param array{nocaseKey:string,rowid:int} $right */
    private static function v215_sortRows(array $left, array $right): int
    {
        $comparison = strcmp($left['nocaseKey'], $right['nocaseKey']);

        return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
    }

    /** @param list<array{rowid:int}> $rows @return list<int> */
    private static function v215_rowids(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['rowid'], $rows);
    }

    /** @param list<array<string,mixed>> $rows @return array<int,mixed> */
    private static function v215_map(array $rows, string $key): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[$row['rowid']] = $row[$key];
        }

        return $mapped;
    }

    private static function v215_truncateAtNul(string $value): string
    {
        $position = strpos($value, "\0");

        return $position === false ? $value : substr($value, 0, $position);
    }

    private static function v215_asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

}
