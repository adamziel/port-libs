<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteLikeGlobCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @param array<string,mixed> $currentStatement
     * @param array<string,mixed> $nextStatement
     * @return array<string,mixed>
     */
    public static function keyValueRowKeyStatement(
        array $currentRows,
        array $nextRows,
        array $currentStatement,
        array $nextStatement,
    ): array {
        $current = self::normalizeStatement($currentStatement, 'current');
        $next = self::normalizeStatement($nextStatement, 'next');

        $currentMatches = SQLiteEncodingCollationSourceCursor::keyValueRowKeyScan(
            $currentRows,
            $current['pattern'],
            $current['operator'],
            $current['collation'],
            $current['escape'],
            $current['caseSensitiveLike'],
        );
        $currentCandidates = SQLiteEncodingCollationSourceCursor::keyValueRowKeyRangeScan(
            $currentRows,
            $current['pattern'],
            $current['operator'],
            $current['collation'],
            $current['escape'],
            $current['caseSensitiveLike'],
        );
        $nextMatches = SQLiteEncodingCollationSourceCursor::keyValueRowKeyScan(
            $nextRows,
            $next['pattern'],
            $next['operator'],
            $next['collation'],
            $next['escape'],
            $next['caseSensitiveLike'],
        );
        $nextCandidates = SQLiteEncodingCollationSourceCursor::keyValueRowKeyRangeScan(
            $nextRows,
            $next['pattern'],
            $next['operator'],
            $next['collation'],
            $next['escape'],
            $next['caseSensitiveLike'],
        );

        $currentRowids = self::rowids($currentMatches);
        $nextRowids = self::rowids($nextMatches);
        $currentCandidateRowids = self::rowids($currentCandidates);
        $nextCandidateRowids = self::rowids($nextCandidates);
        $currentFalsePositiveRowids = array_values(array_diff($currentCandidateRowids, $currentRowids));
        $nextFalsePositiveRowids = array_values(array_diff($nextCandidateRowids, $nextRowids));
        $retained = array_values(array_intersect($currentRowids, $nextRowids));
        $exited = array_values(array_diff($currentRowids, $nextRowids));
        $entered = array_values(array_diff($nextRowids, $currentRowids));

        $currentByRowid = self::byRowid($currentRows);
        $nextByRowid = self::byRowid($nextRows);
        $changedEncodings = [];
        $changedBytes = [];
        $candidateChangedEncodings = [];
        $candidateChangedBytes = [];
        $candidateUniverse = array_unique(array_merge($currentCandidateRowids, $nextCandidateRowids));
        foreach (array_intersect(array_keys($currentByRowid), array_keys($nextByRowid)) as $rowid) {
            $encodingChanged = $currentByRowid[$rowid]['text_encoding'] !== $nextByRowid[$rowid]['text_encoding'];
            $bytesChanged = $currentByRowid[$rowid]['key_name_bytes'] !== $nextByRowid[$rowid]['key_name_bytes'];
            if (in_array($rowid, array_unique(array_merge($currentRowids, $nextRowids)), true)) {
                if ($encodingChanged) {
                    $changedEncodings[] = $rowid;
                }
                if ($bytesChanged) {
                    $changedBytes[] = $rowid;
                }
            }
            if (in_array($rowid, $candidateUniverse, true)) {
                if ($encodingChanged) {
                    $candidateChangedEncodings[] = $rowid;
                }
                if ($bytesChanged) {
                    $candidateChangedBytes[] = $rowid;
                }
            }
        }
        sort($changedEncodings);
        sort($changedBytes);
        sort($candidateChangedEncodings);
        sort($candidateChangedBytes);

        $reasons = self::statementReasons($current, $next);
        if ($changedEncodings !== []) {
            $reasons[] = 'text-encoding';
        }
        if ($changedBytes !== []) {
            $reasons[] = 'key-bytes';
        }
        if ($candidateChangedEncodings !== [] && $candidateChangedEncodings !== $changedEncodings) {
            $reasons[] = 'candidate-text-encoding';
        }
        if ($candidateChangedBytes !== [] && $candidateChangedBytes !== $changedBytes) {
            $reasons[] = 'candidate-key-bytes';
        }
        if ($entered !== [] || $exited !== []) {
            $reasons[] = 'matched-rowset';
        }

        return [
            'status' => $reasons === [] ? 'cursor-reusable' : 'reprepare-required',
            'reprepareRequired' => $reasons !== [],
            'reprepareReasons' => $reasons,
            'current' => [
                'source' => $current['source'],
                'operator' => $current['operator'],
                'pattern' => $current['pattern'],
                'collation' => $current['collation'],
                'escape' => $current['escape'],
                'caseSensitiveLike' => $current['caseSensitiveLike'],
                'range' => self::range($current),
                'candidateRowids' => $currentCandidateRowids,
                'falsePositiveRowids' => $currentFalsePositiveRowids,
                'rowids' => $currentRowids,
                'encodings' => self::encodingMap($currentMatches),
                'candidateEncodings' => self::encodingMap($currentCandidates),
                'bytesHex' => self::bytesMap($currentMatches),
                'candidateBytesHex' => self::bytesMap($currentCandidates),
            ],
            'next' => [
                'source' => $next['source'],
                'operator' => $next['operator'],
                'pattern' => $next['pattern'],
                'collation' => $next['collation'],
                'escape' => $next['escape'],
                'caseSensitiveLike' => $next['caseSensitiveLike'],
                'range' => self::range($next),
                'candidateRowids' => $nextCandidateRowids,
                'falsePositiveRowids' => $nextFalsePositiveRowids,
                'rowids' => $nextRowids,
                'encodings' => self::encodingMap($nextMatches),
                'candidateEncodings' => self::encodingMap($nextCandidates),
                'bytesHex' => self::bytesMap($nextMatches),
                'candidateBytesHex' => self::bytesMap($nextCandidates),
            ],
            'retainedRowids' => $retained,
            'exitedRowids' => $exited,
            'enteredRowids' => $entered,
            'changedEncodingRowids' => $changedEncodings,
            'changedBytesRowids' => $changedBytes,
            'candidateChangedEncodingRowids' => $candidateChangedEncodings,
            'candidateChangedBytesRowids' => $candidateChangedBytes,
            'dependencies' => [
                'sqlite-encoding-source-cursor',
                'sqlite-like-glob-collation',
                'sqlite-current-source-next-statement-reprepare',
                'sqlite-like-glob-range-candidates',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $statement
     * @return array{source:string,operator:string,pattern:string,collation:string,escape:?string,caseSensitiveLike:bool}
     */
    private static function normalizeStatement(array $statement, string $label): array
    {
        foreach (['source', 'operator', 'pattern'] as $key) {
            if (!array_key_exists($key, $statement) || !is_string($statement[$key]) || $statement[$key] === '') {
                throw new \InvalidArgumentException("SQLite {$label} LIKE/GLOB statement requires non-empty {$key}");
            }
        }

        $operator = strtoupper($statement['operator']);
        if (!in_array($operator, ['LIKE', 'GLOB'], true)) {
            throw new \InvalidArgumentException("SQLite {$label} LIKE/GLOB statement operator must be LIKE or GLOB");
        }

        $collation = strtoupper(is_string($statement['collation'] ?? null) ? $statement['collation'] : 'BINARY');
        if (!in_array($collation, ['BINARY', 'NOCASE', 'RTRIM'], true)) {
            throw new \InvalidArgumentException("SQLite {$label} LIKE/GLOB statement collation must be BINARY, NOCASE, or RTRIM");
        }

        $escape = $statement['escape'] ?? null;
        if ($escape !== null && !is_string($escape)) {
            throw new \InvalidArgumentException("SQLite {$label} LIKE/GLOB statement ESCAPE must be text or null");
        }
        if ($operator === 'GLOB') {
            $escape = null;
        }

        $caseSensitiveLike = (bool) ($statement['caseSensitiveLike'] ?? false);

        return [
            'source' => $statement['source'],
            'operator' => $operator,
            'pattern' => $statement['pattern'],
            'collation' => $collation,
            'escape' => $escape,
            'caseSensitiveLike' => $caseSensitiveLike,
        ];
    }

    /**
     * @param array<string,mixed> $current
     * @param array<string,mixed> $next
     * @return list<string>
     */
    private static function statementReasons(array $current, array $next): array
    {
        $reasons = [];
        foreach ([
            'source' => 'source-name',
            'operator' => 'operator',
            'pattern' => 'pattern',
            'collation' => 'collation',
            'escape' => 'escape',
            'caseSensitiveLike' => 'case-sensitive-like',
        ] as $key => $reason) {
            if ($current[$key] !== $next[$key]) {
                $reasons[] = $reason;
            }
        }

        return $reasons;
    }

    /**
     * @param array<string,mixed> $statement
     * @return null|array{lowerInclusive:string,upperBound:?string}
     */
    private static function range(array $statement): ?array
    {
        return $statement['operator'] === 'LIKE'
            ? SQLiteLikeCollationPlan::plan(
                $statement['pattern'],
                $statement['collation'],
                $statement['escape'],
                $statement['caseSensitiveLike'],
            )['range']
            : SQLiteDatabase::globPrefixRangeBounds($statement['pattern']);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int,array{key_name_bytes:string,text_encoding:int}>
     */
    private static function byRowid(array $rows): array
    {
        $byRowid = [];
        foreach ($rows as $row) {
            if (!isset($row['setting_id']) || !is_int($row['setting_id'])) {
                throw new \InvalidArgumentException('SQLite LIKE/GLOB current-source next plan requires integer setting_id');
            }
            if (!array_key_exists('key_name_bytes', $row) || !is_string($row['key_name_bytes'])) {
                throw new \InvalidArgumentException('SQLite LIKE/GLOB current-source next plan requires key_name_bytes');
            }
            if (!isset($row['text_encoding']) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite LIKE/GLOB current-source next plan requires integer text_encoding');
            }
            $byRowid[$row['setting_id']] = [
                'key_name_bytes' => $row['key_name_bytes'],
                'text_encoding' => $row['text_encoding'],
            ];
        }

        return $byRowid;
    }

    /**
     * @param list<array{rowid:int}> $rows
     * @return list<int>
     */
    private static function rowids(array $rows): array
    {
        return array_values(array_map(static fn (array $row): int => $row['rowid'], $rows));
    }

    /**
     * @param list<array{rowid:int,textEncoding:string}> $rows
     * @return array<int,string>
     */
    private static function encodingMap(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $map[$row['rowid']] = $row['textEncoding'];
        }

        return $map;
    }

    /**
     * @param list<array{rowid:int,keyBytesHex:string}> $rows
     * @return array<int,string>
     */
    private static function bytesMap(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $map[$row['rowid']] = $row['keyBytesHex'];
        }

        return $map;
    }
}
