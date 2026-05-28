<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext202Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $whereTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materialize(
        array $preparedSource,
        array $currentSource,
        array $whereTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext196Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $selectedName = (string) (($base['selectedPlan']['name'] ?? ''));
        $preparedIndex = self::indexByName($preparedSource, $selectedName);
        $currentIndex = self::indexByName($currentSource, $selectedName);
        $predicateFence = self::predicateDefinitionFence(
            self::partialPredicateTerms($preparedIndex),
            self::partialPredicateTerms($currentIndex),
        );
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next196-ready'
            && $predicateFence['partialPredicateDefinitionMatches'] === true
            && $predicateFence['changedTerms'] === [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next202-ready' : 'requires-current-source-partial-definition-reprepare',
            'partialPredicateDefinitionFence' => $predicateFence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next202Ready' => $ready,
                'next202PartialPredicateTerms' => $predicateFence['termCount'],
                'next202ChangedTerms' => $predicateFence['changedTerms'],
                'next202PartialPredicateSignature' => $predicateFence['signature'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next202PartialPredicateSignature' => $predicateFence['signature'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $predicateFence
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT202 PARTIAL PREDICATE DEFINITION FENCE '
                . $selectedName
                . ($ready ? ' CURRENT PARTIAL PREDICATE MATCHES PREPARED' : ' REQUIRES CURRENT SOURCE PARTIAL DEFINITION REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext196Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next202',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next202 reuses current-source STAT4 expression partial peer/covering fences and adds prepared-vs-current partial predicate definition fingerprinting',
            'non_overlap' => 'avoids accepted next196 duplicate peer order, next192 covering-column admission, next191 payload expression-key rechecks, next189 row-level payload partial predicate checks, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, and UTF clusters; this slice only admits current-source STAT4 partial expression windows when the partial-index predicate definition still matches the prepared statement',
        ]);
    }

    /**
     * @param array<string,mixed> $source
     * @return array<string,mixed>
     */
    private static function indexByName(array $source, string $name): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite next202 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next202 index entries must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next202 selected index missing from current source');
    }

    /**
     * @param array<string,mixed> $index
     * @return list<array<string,mixed>>
     */
    private static function partialPredicateTerms(array $index): array
    {
        $terms = $index['partialPredicateTerms'] ?? null;
        if (!is_array($terms) || !array_is_list($terms) || $terms === []) {
            throw new \InvalidArgumentException('SQLite next202 needs partial predicate terms');
        }
        foreach ($terms as $term) {
            if (!is_array($term)) {
                throw new \InvalidArgumentException('SQLite next202 partial predicate terms must be arrays');
            }
        }

        return $terms;
    }

    /**
     * @param list<array<string,mixed>> $preparedTerms
     * @param list<array<string,mixed>> $currentTerms
     * @return array<string,mixed>
     */
    private static function predicateDefinitionFence(array $preparedTerms, array $currentTerms): array
    {
        $prepared = self::normalizedTerms($preparedTerms);
        $current = self::normalizedTerms($currentTerms);
        $checks = [];
        $changed = [];
        $max = max(count($prepared), count($current));
        for ($i = 0; $i < $max; $i++) {
            $preparedTerm = $prepared[$i] ?? null;
            $currentTerm = $current[$i] ?? null;
            $matches = $preparedTerm === $currentTerm;
            if (!$matches) {
                $changed[] = $i;
            }
            $checks[] = [
                'position' => $i,
                'preparedTerm' => $preparedTerm,
                'currentTerm' => $currentTerm,
                'matches' => $matches,
            ];
        }

        return [
            'termCount' => count($currentTerms),
            'preparedTermCount' => count($preparedTerms),
            'currentTermCount' => count($currentTerms),
            'preparedTerms' => $prepared,
            'currentTerms' => $current,
            'termChecks' => $checks,
            'changedTerms' => $changed,
            'partialPredicateDefinitionMatches' => $changed === [],
            'signature' => self::signature([$prepared, $current, $checks]),
        ];
    }

    /**
     * @param list<array<string,mixed>> $terms
     * @return list<array<string,mixed>>
     */
    private static function normalizedTerms(array $terms): array
    {
        $out = [];
        foreach ($terms as $term) {
            $left = $term['left'] ?? null;
            if (!is_array($left)) {
                throw new \InvalidArgumentException('SQLite next202 partial predicate needs left operand');
            }
            $leftKind = isset($left['expression']) ? 'expression' : 'column';
            $leftValue = (string) ($left[$leftKind] ?? '');
            if ($leftValue === '') {
                throw new \InvalidArgumentException('SQLite next202 partial predicate left operand must be named');
            }
            $out[] = [
                'leftKind' => $leftKind,
                'left' => strtolower(preg_replace('/\s+/', '', $leftValue) ?? ''),
                'operator' => strtoupper((string) ($term['operator'] ?? '')),
                'right' => $term['right'] ?? null,
                'lower' => $term['lower'] ?? null,
                'upper' => $term['upper'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $program
     * @param array<string,mixed> $predicateFence
     * @return list<array<string,mixed>>
     */
    private static function cursorProgram(array $program, bool $ready, array $predicateFence): array
    {
        if (!$ready) {
            return $program;
        }
        $program[] = [
            'opcode' => 'Stat4PartialPredicateDefinitionFence',
            'mode' => 'next202-current-source-stat4-expression-partial-definition',
            'termCount' => $predicateFence['termCount'],
            'signature' => $predicateFence['signature'],
        ];

        return $program;
    }

    /** @param mixed $value */
    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
