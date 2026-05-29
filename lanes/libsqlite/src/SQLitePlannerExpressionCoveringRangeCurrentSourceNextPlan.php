<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerExpressionCoveringRangeCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLitePlannerExpressionCoveringRangeCurrentSourceNextPlan. */

    /**
         * @param array<string,mixed> $preparedSource
         * @param array<string,mixed> $currentSource
         * @param array<string,mixed>|null $nextSource
         * @param array<string,mixed> $preparedPredicate
         * @param array<string,mixed> $currentPredicate
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,string>> $orderBy
         * @param list<string> $neededColumns
         * @param list<array<string,string>> $neededExpressions
         * @return array<string,mixed>
         */
        public static function materializeNext146(
            array $preparedSource,
            array $currentSource,
            ?array $nextSource,
            array $preparedPredicate,
            array $currentPredicate,
            array $currentRows,
            array $orderBy,
            array $neededColumns,
            array $neededExpressions = []
        ): array {
            $base = SQLitePlannerCoveringExpressionRangeCurrentSourceNextPlan::materializeNext134(
                $preparedSource,
                $currentSource,
                $preparedPredicate,
                $currentPredicate,
                $currentRows,
                $orderBy,
                $neededColumns,
                $neededExpressions,
            );

            $currentSignature = self::sourceSignatureNext146($currentSource, $currentRows);
            $nextSummary = $nextSource === null ? null : self::nextSummaryNext146($currentSource, $nextSource, $currentRows);
            $nextAdmitted = $nextSummary === null || $nextSummary['replanReasons'] === [];
            $ready = ($base['status'] ?? null) === 'covering-expression-range-current-source-next134-ready'
                && ($base['tableLookupElided'] ?? false) === true
                && $nextAdmitted;

            $coveringRows = self::coveringRowsNext146($base);
            $rowids = array_column($coveringRows, 'rowid');
            $payloadSignature = hash('sha256', json_encode($coveringRows, JSON_THROW_ON_ERROR));

            return array_replace($base, [
                'status' => $ready ? 'expression-covering-range-current-source-next146-ready' : 'requires-current-source-reprepare',
                'nextSourceAdmitted' => $nextAdmitted,
                'nextSource' => $nextSummary,
                'coveringRangeRows' => $coveringRows,
                'coveringRangeRowids' => $rowids,
                'coveringRangePayloadSignature' => $payloadSignature,
                'currentSourceFence' => array_replace(
                    is_array($base['currentSourceFence'] ?? null) ? $base['currentSourceFence'] : [],
                    [
                        'next146SourceSignature' => $currentSignature,
                        'next146PayloadSignature' => $payloadSignature,
                        'next146OrderSignature' => self::orderSignatureNext146($orderBy),
                        'next146CoveringColumns' => $neededColumns,
                        'next146CoveringExpressionCount' => count($neededExpressions),
                    ],
                ),
                'cursorTape' => self::cursorTapeNext146($base, $ready, $currentSignature, $payloadSignature),
                'selectedPlan' => array_replace(
                    is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [],
                    [
                        'next146Ready' => $ready,
                        'next146SourceSignature' => $currentSignature,
                        'next146PayloadSignature' => $payloadSignature,
                        'next146Rowids' => $rowids,
                        'next146CoveringRowCount' => count($coveringRows),
                        'next146NextSourceAdmitted' => $nextAdmitted,
                    ],
                ),
                'detail' => (($base['stalePreparedStatement'] ?? false) ? 'REPREPARE' : 'REUSE')
                    . ' EXPRESSION COVERING RANGE CURRENT-SOURCE next146 '
                    . ($nextAdmitted ? 'NEXT-SOURCE FENCED' : 'NEXT-SOURCE STALE'),
                'dependencies' => array_values(array_unique(array_merge(
                    is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                    [
                        'SQLitePlannerCoveringExpressionRangeCurrentSourceNextPlan',
                        'sqlite-sqlplanner-expression-covering-range-current-source-next146',
                    ],
                ))),
                'dependency_closure' => 'no new support component needed; next146 reuses native expression covering range materialization and adds current/next source fencing',
                'non_overlap' => 'avoids accepted next128 range recheck, next134 descending range stream, next138 non-expression STAT4 range, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, and B-tree clusters; this slice fences the current covering expression range payload against an optional next source before table seeks remain elided',
            ]);
        }

        /**
         * @param array<string,mixed> $base
         * @return list<array<string,mixed>>
         */
        private static function coveringRowsNext146(array $base): array
        {
            $rows = [];
            foreach (($base['currentNextRows'] ?? []) as $pair) {
                if (!is_array($pair) || !isset($pair['current']) || !is_array($pair['current'])) {
                    continue;
                }
                $current = $pair['current'];
                $rows[] = [
                    'rowid' => $current['rowid'] ?? null,
                    'key' => $current['key'] ?? null,
                    'covering' => is_array($current['covering'] ?? null) ? $current['covering'] : [],
                    'nextRowid' => is_array($pair['next'] ?? null) ? ($pair['next']['rowid'] ?? null) : null,
                ];
            }

            return $rows;
        }

        /**
         * @param array<string,mixed> $currentSource
         * @param array<string,mixed> $nextSource
         * @param list<array<string,mixed>> $currentRows
         * @return array<string,mixed>
         */
        private static function nextSummaryNext146(array $currentSource, array $nextSource, array $currentRows): array
        {
            $nextRows = $nextSource['rows'] ?? $currentRows;
            if (!is_array($nextRows) || !array_is_list($nextRows)) {
                throw new \InvalidArgumentException('SQLite expression covering range next source rows must be a list');
            }
            foreach ($nextRows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite expression covering range next source rows must be arrays');
                }
            }

            $currentSignature = self::sourceSignatureNext146($currentSource, $currentRows);
            $nextSignature = self::sourceSignatureNext146($nextSource, $nextRows);
            $reasons = [];
            foreach (['schemaCookie' => 'schema-cookie', 'stat4Generation' => 'stat4-generation'] as $key => $reason) {
                if (($currentSource[$key] ?? null) !== ($nextSource[$key] ?? null)) {
                    $reasons[] = $reason;
                }
            }
            if (self::indexSignatureNext146($currentSource) !== self::indexSignatureNext146($nextSource)) {
                $reasons[] = 'index-signature';
            }
            if (hash('sha256', json_encode($currentRows, JSON_THROW_ON_ERROR)) !== hash('sha256', json_encode($nextRows, JSON_THROW_ON_ERROR))) {
                $reasons[] = 'row-stream';
            }

            return [
                'name' => (string) ($nextSource['name'] ?? ''),
                'schemaCookie' => $nextSource['schemaCookie'] ?? null,
                'stat4Generation' => $nextSource['stat4Generation'] ?? null,
                'sourceSignature' => $nextSignature,
                'matchesCurrentSource' => $currentSignature === $nextSignature && $reasons === [],
                'replanReasons' => $reasons,
            ];
        }

        /**
         * @param array<string,mixed> $source
         * @param list<array<string,mixed>> $rows
         */
        private static function sourceSignatureNext146(array $source, array $rows): string
        {
            return hash('sha256', json_encode([
                'name' => $source['name'] ?? null,
                'schemaCookie' => $source['schemaCookie'] ?? null,
                'stat4Generation' => $source['stat4Generation'] ?? null,
                'indexes' => self::indexSignatureNext146($source),
                'rows' => $rows,
            ], JSON_THROW_ON_ERROR));
        }

        /**
         * @param array<string,mixed> $source
         */
        private static function indexSignatureNext146(array $source): string
        {
            return hash('sha256', serialize($source['indexes'] ?? []));
        }

        /**
         * @param list<array<string,string>> $orderBy
         */
        private static function orderSignatureNext146(array $orderBy): string
        {
            $parts = [];
            foreach ($orderBy as $term) {
                $parts[] = ($term['function'] ?? $term['column'] ?? $term['expression'] ?? '')
                    . ' '
                    . strtoupper((string) ($term['direction'] ?? 'ASC'));
            }

            return implode(', ', $parts);
        }

        /**
         * @param array<string,mixed> $base
         * @return array<string,mixed>
         */
        private static function cursorTapeNext146(array $base, bool $ready, string $sourceSignature, string $payloadSignature): array
        {
            $tape = is_array($base['cursorTape'] ?? null) ? $base['cursorTape'] : [];
            $program = is_array($tape['program'] ?? null) ? $tape['program'] : [];
            array_unshift($program, [
                'opcode' => 'FenceCurrentSource',
                'source' => 'current',
                'sourceSignature' => $sourceSignature,
                'payloadSignature' => $payloadSignature,
                'tableLookupElided' => $ready,
            ]);

            return array_replace($tape, [
                'program' => $program,
                'currentSourceFenceOpcode' => 'FenceCurrentSource',
                'next146SourceSignature' => $sourceSignature,
                'next146PayloadSignature' => $payloadSignature,
                'tableLookupElidedAfterNextFence' => $ready,
                'deferredSeekOpcode' => $ready ? null : 'DeferredSeek',
            ]);
        }

}
