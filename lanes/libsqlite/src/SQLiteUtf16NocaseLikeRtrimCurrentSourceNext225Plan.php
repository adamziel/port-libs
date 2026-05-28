<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext225Plan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameSourceBytePlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_cache%',
        ?string $escape = '!',
        string $currentSource = 'main.wp_options@224',
        string $nextSource = 'main.wp_options@225',
        int $currentSchemaCookie = 224,
        int $nextSchemaCookie = 225,
    ): array {
        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNext219Plan::wordpressOptionNameSupplementaryWildcardPlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escape,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $raw = self::rawSourceChanges($currentRows, $nextRows);
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
            'status' => 'utf16-nocase-like-rtrim-current-source-next225',
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
            'stableDecodedChangedSourceRowids' => self::stableDecodedChangedSourceRowids($raw, $base),
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
                'sqlite-current-source-next225',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, ASCII NOCASE LIKE prefix planning, RTRIM expression keys, and current-source raw byte diagnostics',
            'non_overlap' => 'next225 adds raw UTF-16 source-byte and endian-change cursor fencing when decoded NOCASE/RTRIM LIKE results remain stable; avoids accepted next219 supplementary wildcard matching, next217 pattern-space handling, next213 Unicode ESCAPE, next210 embedded NUL, Unicode GLOB ranges, and malformed UTF-16 insert guards',
        ];
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    private static function rawSourceChanges(array $currentRows, array $nextRows): array
    {
        $current = self::rawRowsById($currentRows);
        $next = self::rawRowsById($nextRows);
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
            'currentTextEncodings' => self::mapRaw($current, 'encodingName'),
            'nextTextEncodings' => self::mapRaw($next, 'encodingName'),
            'currentSourceBytesHex' => self::mapRaw($current, 'bytesHex'),
            'nextSourceBytesHex' => self::mapRaw($next, 'bytesHex'),
            'currentByteOrders' => self::mapRaw($current, 'byteOrder'),
            'nextByteOrders' => self::mapRaw($next, 'byteOrder'),
            'encodingChangedRowids' => $encodingChanged,
            'sourceBytesChangedRowids' => $bytesChanged,
            'byteOrderChangedRowids' => $byteOrderChanged,
        ];
    }

    /** @param list<array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function rawRowsById(array $rows): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            if (!array_key_exists('option_id', $row) || !is_int($row['option_id'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next225 rows require integer option_id');
            }
            if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next225 rows require option_name_bytes');
            }
            if (!array_key_exists('text_encoding', $row) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next225 rows require integer text_encoding');
            }
            $encoding = $row['text_encoding'];
            $mapped[$row['option_id']] = [
                'encoding' => $encoding,
                'encodingName' => self::encodingName($encoding),
                'byteOrder' => self::byteOrder($encoding),
                'bytesHex' => bin2hex($row['option_name_bytes']),
            ];
        }
        ksort($mapped);

        return $mapped;
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,mixed> */
    private static function mapRaw(array $rows, string $key): array
    {
        $mapped = [];
        foreach ($rows as $rowid => $row) {
            $mapped[$rowid] = $row[$key];
        }

        return $mapped;
    }

    /** @param array<string,mixed> $raw @param array<string,mixed> $base @return list<int> */
    private static function stableDecodedChangedSourceRowids(array $raw, array $base): array
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

    private static function encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next225 encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function byteOrder(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'little-endian',
            3 => 'big-endian',
            default => throw new \InvalidArgumentException('SQLite UTF-16 NOCASE LIKE RTRIM next225 encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }
}
