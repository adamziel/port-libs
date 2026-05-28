<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext166Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @param array<string,mixed>|null $nextSource
     * @return array<string,mixed>
     */
    public static function materialize(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        ?array $nextSource = null
    ): array {
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext154Plan::materialize(
            $preparedSource,
            $currentSource,
            $queryTerms,
            $neededColumns,
        );

        $selected = self::arrayValue($base, 'selectedPlan');
        $matchedRows = self::listValue($base, 'matchedRows');
        $constraint = self::arrayValue($selected, 'constraint');
        $selectedIndex = self::selectedIndex($base['selectedSource'] === 'current' ? $currentSource : $preparedSource, (string) ($selected['name'] ?? ''));
        $preparedIndex = self::selectedIndex($preparedSource, (string) ($selected['name'] ?? ''));
        $currentIndex = self::selectedIndex($currentSource, (string) ($selected['name'] ?? ''));
        $partialDelta = self::partialPredicateDelta($preparedIndex, $currentIndex);
        $values = self::constraintValues($constraint);
        $buckets = self::inBuckets($matchedRows, $values, self::listValue($selected, 'stat4MatchedCurrentNext'));
        $missing = self::missingStat4Values($values, $buckets);
        $nextSummary = $nextSource === null ? null : self::nextSourceSummary($currentSource, $nextSource);
        $nextAdmitted = $nextSummary === null || $nextSummary['replanReasons'] === [];
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next154-ready'
            && ($constraint['operator'] ?? null) === 'IN'
            && count($values) >= 2
            && $partialDelta['changed'] === true
            && $missing === []
            && $buckets !== []
            && $nextAdmitted;

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next166-ready' : 'requires-current-source-reprepare',
            'nextSourceAdmitted' => $nextAdmitted,
            'nextSource' => $nextSummary,
            'inValues' => $values,
            'inValueCount' => count($values),
            'inBuckets' => $buckets,
            'inBucketCount' => count($buckets),
            'inBucketRowids' => self::bucketRowids($buckets),
            'missingStat4InValues' => $missing,
            'partialPredicateDelta' => $partialDelta,
            'partialPredicateChanged' => $partialDelta['changed'],
            'currentSourceOnlyRowids' => self::currentOnlyRowids($preparedSource, $currentSource),
            'stalePreparedRowidsBlockedByPartialDelta' => self::blockedPreparedRowids($preparedSource, $queryTerms),
            'cursorProgram' => self::cursorProgram($selected, $buckets, $neededColumns, $ready),
            'selectedPlan' => array_replace($selected, [
                'next166Ready' => $ready,
                'next166InValues' => $values,
                'next166InBucketCount' => count($buckets),
                'next166InBucketRowids' => self::bucketRowids($buckets),
                'next166MissingStat4InValues' => $missing,
                'next166NextSourceAdmitted' => $nextAdmitted,
                'next166PartialPredicateChanged' => $partialDelta['changed'],
                'next166PartialPredicateSignature' => self::signature($selectedIndex['partialPredicateTerms'] ?? []),
            ]),
            'stat4Fence' => array_replace(
                self::arrayValue($base, 'stat4Fence'),
                [
                    'next166PartialPredicateDeltaSignature' => self::signature($partialDelta),
                    'next166InValueSignature' => self::signature($values),
                    'next166InBucketSignature' => self::signature($buckets),
                    'next166NextSourceSignature' => $nextSummary['sourceSignature'] ?? null,
                ],
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT166 '
                . (string) ($selected['name'] ?? 'NO INDEX')
                . ($ready ? ' MULTI-IN BUCKETS WITH PARTIAL DELTA' : ' REQUIRES CURRENT SOURCE REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext154Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next166',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next166 reuses native STAT4 expression partial planning and adds multi-value IN bucket fencing',
            'non_overlap' => 'avoids accepted STAT4 equality-bucket, equality+range, covering row-stream, skip-scan, expression ORDER BY, range-cost, JSON, WAL, VFS, and B-tree clusters; this slice covers multi-key IN admission only when the current partial predicate changed',
        ]);
    }

    /**
     * @param array<string,mixed> $base
     * @return array<string,mixed>
     */
    private static function arrayValue(array $base, string $key): array
    {
        $value = $base[$key] ?? null;
        if (!is_array($value)) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source next166 needs array ' . $key);
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $base
     * @return list<array<string,mixed>>
     */
    private static function listValue(array $base, string $key): array
    {
        $value = $base[$key] ?? null;
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source next166 needs list ' . $key);
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $constraint
     * @return list<mixed>
     */
    private static function constraintValues(array $constraint): array
    {
        $values = $constraint['values'] ?? null;
        if (!is_array($values) || !array_is_list($values)) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source next166 needs IN values');
        }

        return array_values(array_unique($values, SORT_REGULAR));
    }

    /**
     * @param array<string,mixed> $source
     * @return array<string,mixed>
     */
    private static function selectedIndex(array $source, string $name): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source next166 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source next166 index must be an array');
            }
            if (($index['name'] ?? null) === $name) {
                return $index;
            }
        }

        return is_array($indexes[0] ?? null) ? $indexes[0] : [];
    }

    /**
     * @param array<string,mixed> $preparedIndex
     * @param array<string,mixed> $currentIndex
     * @return array<string,mixed>
     */
    private static function partialPredicateDelta(array $preparedIndex, array $currentIndex): array
    {
        $prepared = self::termsBySignature(self::termList($preparedIndex['partialPredicateTerms'] ?? []));
        $current = self::termsBySignature(self::termList($currentIndex['partialPredicateTerms'] ?? []));

        return [
            'changed' => $prepared !== $current,
            'preparedTermCount' => count($prepared),
            'currentTermCount' => count($current),
            'addedTerms' => array_values(array_diff_key($current, $prepared)),
            'removedTerms' => array_values(array_diff_key($prepared, $current)),
            'preparedSignature' => self::signature(array_values($prepared)),
            'currentSignature' => self::signature(array_values($current)),
        ];
    }

    /**
     * @param mixed $terms
     * @return list<array<string,mixed>>
     */
    private static function termList(mixed $terms): array
    {
        if (!is_array($terms) || !array_is_list($terms)) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source next166 partial terms must be a list');
        }
        foreach ($terms as $term) {
            if (!is_array($term)) {
                throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source next166 partial term must be an array');
            }
        }

        return $terms;
    }

    /**
     * @param list<array<string,mixed>> $terms
     * @return array<string,array<string,mixed>>
     */
    private static function termsBySignature(array $terms): array
    {
        $out = [];
        foreach ($terms as $term) {
            $out[self::signature($term)] = $term;
        }
        ksort($out);

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<mixed> $values
     * @param list<array<string,mixed>> $stat4Pairs
     * @return list<array{key:mixed,rowids:list<int>,rowCount:int,stat4Rowid:mixed,nextKey:mixed,exact:bool}>
     */
    private static function inBuckets(array $rows, array $values, array $stat4Pairs): array
    {
        $wanted = array_fill_keys(array_map(self::valueSignature(...), $values), true);
        $nextByKey = [];
        $stat4RowidByKey = [];
        foreach ($stat4Pairs as $pair) {
            if (!is_array($pair) || !is_array($pair['current'] ?? null)) {
                continue;
            }
            $key = $pair['current']['key'] ?? null;
            $signature = self::valueSignature($key);
            if (!isset($wanted[$signature])) {
                continue;
            }
            $nextByKey[$signature] = is_array($pair['next'] ?? null) ? ($pair['next']['key'] ?? null) : null;
            $stat4RowidByKey[$signature] = $pair['current']['rowid'] ?? null;
        }

        $grouped = [];
        foreach ($rows as $row) {
            $key = $row['expressionKey'] ?? null;
            $signature = self::valueSignature($key);
            if (!isset($wanted[$signature])) {
                continue;
            }
            $grouped[$signature]['key'] = $key;
            $grouped[$signature]['rowids'][] = (int) ($row['rowid'] ?? 0);
        }

        $buckets = [];
        foreach ($grouped as $signature => $group) {
            sort($group['rowids']);
            $buckets[] = [
                'key' => $group['key'],
                'rowids' => $group['rowids'],
                'rowCount' => count($group['rowids']),
                'stat4Rowid' => $stat4RowidByKey[$signature] ?? null,
                'nextKey' => $nextByKey[$signature] ?? null,
                'exact' => array_key_exists($signature, $stat4RowidByKey),
            ];
        }
        usort($buckets, static fn (array $left, array $right): int => strcmp((string) $left['key'], (string) $right['key']));

        return $buckets;
    }

    /**
     * @param list<mixed> $values
     * @param list<array<string,mixed>> $buckets
     * @return list<mixed>
     */
    private static function missingStat4Values(array $values, array $buckets): array
    {
        $present = array_fill_keys(array_map(static fn (array $bucket): string => self::valueSignature($bucket['key'] ?? null), $buckets), true);
        $missing = [];
        foreach ($values as $value) {
            if (!isset($present[self::valueSignature($value)])) {
                $missing[] = $value;
            }
        }

        return $missing;
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @return list<int>
     */
    private static function currentOnlyRowids(array $preparedSource, array $currentSource): array
    {
        $prepared = array_flip(array_map('intval', array_column(self::sourceRows($preparedSource), 'rowid')));
        $out = [];
        foreach (self::sourceRows($currentSource) as $row) {
            $rowid = (int) ($row['rowid'] ?? 0);
            if (!array_key_exists($rowid, $prepared)) {
                $out[] = $rowid;
            }
        }
        sort($out);

        return $out;
    }

    /**
     * @param array<string,mixed> $preparedSource
     * @param list<array<string,mixed>> $queryTerms
     * @return list<int>
     */
    private static function blockedPreparedRowids(array $preparedSource, array $queryTerms): array
    {
        $blocked = [];
        foreach (self::sourceRows($preparedSource) as $row) {
            if (self::rowMatchesTerms($row, $queryTerms)) {
                continue;
            }
            $rowid = (int) ($row['rowid'] ?? 0);
            if ($rowid > 0) {
                $blocked[] = $rowid;
            }
        }
        sort($blocked);

        return $blocked;
    }

    /**
     * @param array<string,mixed> $row
     * @param list<array<string,mixed>> $queryTerms
     */
    private static function rowMatchesTerms(array $row, array $queryTerms): bool
    {
        foreach ($queryTerms as $term) {
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            $left = $term['left'] ?? null;
            if (!is_array($left) || !isset($left['column'])) {
                continue;
            }
            $column = (string) $left['column'];
            $value = $row[$column] ?? null;
            if ($operator === '=' && $value !== ($term['right'] ?? null)) {
                return false;
            }
            if ($operator === 'IS NOT NULL' && $value === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string,mixed> $source
     * @return list<array<string,mixed>>
     */
    private static function sourceRows(array $source): array
    {
        $rows = $source['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source next166 needs row list');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source next166 rows must be arrays');
            }
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $nextSource
     * @return array<string,mixed>
     */
    private static function nextSourceSummary(array $currentSource, array $nextSource): array
    {
        $reasons = [];
        foreach (['schemaCookie' => 'schema-cookie', 'stat4Generation' => 'stat4-generation'] as $key => $reason) {
            if ((int) ($currentSource[$key] ?? -1) !== (int) ($nextSource[$key] ?? -2)) {
                $reasons[] = $reason;
            }
        }
        if (self::signature($currentSource['indexes'] ?? []) !== self::signature($nextSource['indexes'] ?? [])) {
            $reasons[] = 'index-signature';
        }
        if (self::signature($currentSource['rows'] ?? []) !== self::signature($nextSource['rows'] ?? [])) {
            $reasons[] = 'row-signature';
        }

        return [
            'name' => (string) ($nextSource['name'] ?? ''),
            'schemaCookie' => (int) ($nextSource['schemaCookie'] ?? -1),
            'stat4Generation' => (int) ($nextSource['stat4Generation'] ?? -1),
            'sourceSignature' => self::signature([
                'schemaCookie' => $nextSource['schemaCookie'] ?? null,
                'stat4Generation' => $nextSource['stat4Generation'] ?? null,
                'indexes' => $nextSource['indexes'] ?? [],
                'rows' => $nextSource['rows'] ?? [],
            ]),
            'replanReasons' => $reasons,
        ];
    }

    /**
     * @param array<string,mixed> $selected
     * @param list<array<string,mixed>> $buckets
     * @param list<string> $neededColumns
     * @return list<array<string,mixed>>
     */
    private static function cursorProgram(array $selected, array $buckets, array $neededColumns, bool $ready): array
    {
        $program = [
            ['opcode' => 'OpenRead', 'source' => 'partial-expression-index', 'rootPage' => $selected['rootPage'] ?? null],
            ['opcode' => 'FencePartialPredicateDelta', 'signature' => self::signature($selected['partialPredicateTerms'] ?? [])],
            ['opcode' => 'RewindInList', 'values' => array_column($buckets, 'key')],
            ['opcode' => 'SeekStat4In', 'rowids' => self::bucketRowids($buckets)],
            ['opcode' => $ready ? 'DeferredSeek' : 'Reprepare', 'source' => $ready ? 'table' : 'planner'],
        ];
        foreach ($neededColumns as $column) {
            $program[] = ['opcode' => 'Column', 'source' => 'table', 'column' => $column];
        }
        $program[] = ['opcode' => 'ResultRow', 'rowCount' => array_sum(array_column($buckets, 'rowCount'))];
        $program[] = ['opcode' => 'NextInList', 'source' => 'in-list'];
        $program[] = ['opcode' => 'Next', 'source' => 'index'];

        return $program;
    }

    /**
     * @param list<array<string,mixed>> $buckets
     * @return list<int>
     */
    private static function bucketRowids(array $buckets): array
    {
        $rowids = [];
        foreach ($buckets as $bucket) {
            foreach (($bucket['rowids'] ?? []) as $rowid) {
                $rowids[] = (int) $rowid;
            }
        }
        $rowids = array_values(array_unique($rowids));
        sort($rowids);

        return $rowids;
    }

    private static function valueSignature(mixed $value): string
    {
        return self::signature(['value' => $value]);
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
