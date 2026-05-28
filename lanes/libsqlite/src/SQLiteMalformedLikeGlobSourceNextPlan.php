<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteMalformedLikeGlobSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array{
     *   pattern:string,
     *   operator:string,
     *   collation:string,
     *   currentSource:string,
     *   nextSource:string,
     *   reprepareRequired:bool,
     *   reprepareReasons:list<string>,
     *   currentRowids:list<int>,
     *   nextRowids:list<int>,
     *   currentValidRowids:list<int>,
     *   nextValidRowids:list<int>,
     *   enteredRowids:list<int>,
     *   exitedRowids:list<int>,
     *   retainedRowids:list<int>,
     *   currentMalformedRowids:list<int>,
     *   nextMalformedRowids:list<int>,
     *   repairedRowids:list<int>,
     *   newlyMalformedRowids:list<int>,
     *   currentErrors:array<int,string>,
     *   nextErrors:array<int,string>,
     *   currentBytesHex:array<int,string>,
     *   nextBytesHex:array<int,string>,
     *   currentRange:?array{lowerInclusive:string,upperBound:?string},
     *   nextRange:?array{lowerInclusive:string,upperBound:?string},
     *   dependencies:list<string>
     * }
     */
    public static function wordpressOptionNameCurrentNext(
        array $currentRows,
        array $nextRows,
        string $pattern,
        string $operator = 'LIKE',
        string $collation = 'BINARY',
        ?string $escape = null,
        bool $caseSensitiveLike = false,
        string $currentSource = 'current',
        string $nextSource = 'next',
    ): array {
        $operator = strtoupper($operator);
        if (!in_array($operator, ['LIKE', 'GLOB'], true)) {
            throw new \InvalidArgumentException('SQLite malformed LIKE/GLOB source plan operator must be LIKE or GLOB');
        }

        $current = self::classifyRows($currentRows, $pattern, $operator, $collation, $escape, $caseSensitiveLike);
        $next = self::classifyRows($nextRows, $pattern, $operator, $collation, $escape, $caseSensitiveLike);

        $currentRowids = array_column($current['matched'], 'rowid');
        $nextRowids = array_column($next['matched'], 'rowid');
        $currentMalformed = array_keys($current['errors']);
        $nextMalformed = array_keys($next['errors']);
        sort($currentMalformed);
        sort($nextMalformed);

        $entered = array_values(array_diff($nextRowids, $currentRowids));
        $exited = array_values(array_diff($currentRowids, $nextRowids));
        $retained = array_values(array_intersect($currentRowids, $nextRowids));
        $repaired = array_values(array_diff($currentMalformed, $nextMalformed));
        $newlyMalformed = array_values(array_diff($nextMalformed, $currentMalformed));

        $reasons = [];
        if ($currentSource !== $nextSource) {
            $reasons[] = 'source-name';
        }
        if ($repaired !== [] || $newlyMalformed !== [] || self::errorsChanged($current['errors'], $next['errors'])) {
            $reasons[] = 'malformed-text';
        }
        if ($entered !== [] || $exited !== []) {
            $reasons[] = 'matched-rowset';
        }

        return [
            'pattern' => $pattern,
            'operator' => $operator,
            'collation' => strtoupper($collation),
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'reprepareRequired' => $reasons !== [],
            'reprepareReasons' => $reasons,
            'currentRowids' => $currentRowids,
            'nextRowids' => $nextRowids,
            'currentValidRowids' => $current['validRowids'],
            'nextValidRowids' => $next['validRowids'],
            'enteredRowids' => $entered,
            'exitedRowids' => $exited,
            'retainedRowids' => $retained,
            'currentMalformedRowids' => $currentMalformed,
            'nextMalformedRowids' => $nextMalformed,
            'repairedRowids' => $repaired,
            'newlyMalformedRowids' => $newlyMalformed,
            'currentErrors' => $current['errors'],
            'nextErrors' => $next['errors'],
            'currentBytesHex' => $current['bytesHex'],
            'nextBytesHex' => $next['bytesHex'],
            'currentRange' => $current['range'],
            'nextRange' => $next['range'],
            'dependencies' => [
                'sqlite-encoding-source-cursor',
                'sqlite-like-glob-collation',
                'sqlite-malformed-current-source-next',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{
     *   matched:list<array{rowid:int,key:string,keyBytesHex:string,textEncoding:string,payload:array<string,mixed>,position:int}>,
     *   validRowids:list<int>,
     *   errors:array<int,string>,
     *   bytesHex:array<int,string>,
     *   range:?array{lowerInclusive:string,upperBound:?string}
     * }
     */
    private static function classifyRows(
        array $rows,
        string $pattern,
        string $operator,
        string $collation,
        ?string $escape,
        bool $caseSensitiveLike,
    ): array {
        $validRows = [];
        $validRowids = [];
        $errors = [];
        $bytesHex = [];
        $range = null;

        foreach ($rows as $row) {
            $rowid = self::rowid($row);
            if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
                throw new \InvalidArgumentException('SQLite malformed LIKE/GLOB source plan requires option_name_bytes');
            }
            if (!isset($row['text_encoding']) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite malformed LIKE/GLOB source plan requires integer text_encoding');
            }

            $bytesHex[$rowid] = bin2hex($row['option_name_bytes']);
            try {
                $cursor = new SQLiteEncodingCollationSourceCursor([
                    [
                        'keyBytes' => $row['option_name_bytes'],
                        'textEncoding' => $row['text_encoding'],
                        'rowid' => $rowid,
                        'payload' => $row,
                    ],
                ], $pattern, $operator, $collation, $escape, $caseSensitiveLike);
                $range ??= $cursor->currentNextPlan()['range'];
            } catch (\InvalidArgumentException $exception) {
                $errors[$rowid] = $exception->getMessage();
                continue;
            }

            $validRows[] = $row;
            $validRowids[] = $rowid;
        }

        ksort($errors);
        ksort($bytesHex);

        return [
            'matched' => SQLiteEncodingCollationSourceCursor::wordpressOptionNameScan(
                $validRows,
                $pattern,
                $operator,
                $collation,
                $escape,
                $caseSensitiveLike,
            ),
            'validRowids' => $validRowids,
            'errors' => $errors,
            'bytesHex' => $bytesHex,
            'range' => $range,
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowid(array $row): int
    {
        if (!isset($row['option_id']) || !is_int($row['option_id'])) {
            throw new \InvalidArgumentException('SQLite malformed LIKE/GLOB source plan requires integer option_id');
        }

        return $row['option_id'];
    }

    /**
     * @param array<int,string> $current
     * @param array<int,string> $next
     */
    private static function errorsChanged(array $current, array $next): bool
    {
        if (array_keys($current) !== array_keys($next)) {
            return true;
        }

        foreach ($current as $rowid => $message) {
            if (($next[$rowid] ?? null) !== $message) {
                return true;
            }
        }

        return false;
    }
}
