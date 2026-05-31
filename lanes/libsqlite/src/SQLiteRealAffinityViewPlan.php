<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRealAffinityViewPlan
{
    /**
     * @return list<array<string,mixed>>
     */
    public static function affinity3RealViewCases(int $count = 1200): array
    {
        if ($count < 1) {
            throw new \InvalidArgumentException('SQLite affinity3 REAL view corpus case count must be positive');
        }

        $aprValues = [
            'integer-literal' => '12',
            'fraction-literal' => '12.01',
            'leading-zero-real' => '0012.500',
            'exponent-integer' => '1.2e1',
            'negative-real' => '-12.25',
            'zero-real' => '0.0',
            'integer-value' => 12,
            'real-value' => 12.01,
            'large-real' => '123456789.125',
            'small-exponent' => '1.25e-1',
        ];
        $idValues = [
            'integer-id' => 1,
            'text-id' => '1',
            'padded-text-id' => '01',
            'real-id' => 1.0,
            'unmatched-text-id' => 'missing',
        ];
        $viewShapes = ['left-view', 'right-view', 'nested-left-view', 'nested-right-view', 'materialized-union'];
        $automaticIndexModes = [true, false];
        $comparisonAffinities = ['INTEGER', 'TEXT', 'NUMERIC', 'REAL', 'NONE'];

        $cases = [];
        $caseNumber = 1;
        while (count($cases) < $count) {
            foreach ($aprValues as $aprLabel => $aprValue) {
                foreach ($idValues as $idLabel => $idValue) {
                    foreach ($viewShapes as $viewShape) {
                        foreach ($automaticIndexModes as $automaticIndex) {
                            foreach ($comparisonAffinities as $comparisonAffinity) {
                                $cases[] = self::caseRow(
                                    $caseNumber++,
                                    $aprLabel,
                                    $aprValue,
                                    $idLabel,
                                    $idValue,
                                    $viewShape,
                                    $automaticIndex,
                                    $comparisonAffinity
                                );
                                if (count($cases) >= $count) {
                                    return $cases;
                                }
                            }
                        }
                    }
                }
            }
        }

        return $cases;
    }

    /**
     * @return array<string,mixed>
     */
    private static function caseRow(
        int $caseNumber,
        string $aprLabel,
        mixed $aprValue,
        string $idLabel,
        mixed $idValue,
        string $viewShape,
        bool $automaticIndex,
        string $comparisonAffinity
    ): array {
        $storedApr = SQLiteAffinityComparison::applyAffinity($aprValue, 'REAL');
        $idMatches = SQLiteAffinityComparison::equals($idValue, 1, $comparisonAffinity, 'INTEGER');
        $projectedApr = $idMatches ? $storedApr : null;
        $division = $projectedApr === null ? null : $projectedApr / 100.0;

        return [
            'source' => 'affinity3.test affinity3-100 through affinity3-142',
            'ticket' => '91e2e8ba6ff2e2 automatic index preserves REAL affinity through views',
            'case' => $caseNumber,
            'upstream_section' => self::upstreamSection($viewShape, $automaticIndex),
            'scenario' => "{$viewShape}/{$aprLabel}/{$idLabel}/" . ($automaticIndex ? 'auto-index-on' : 'auto-index-off') . "/{$comparisonAffinity}",
            'view_shape' => $viewShape,
            'automatic_index' => $automaticIndex,
            'apr_label' => $aprLabel,
            'apr_input' => $aprValue,
            'apr_storage' => SQLiteAffinityComparison::storageClass($storedApr),
            'apr_value' => $storedApr,
            'id_label' => $idLabel,
            'id_input' => $idValue,
            'comparison_affinity' => $comparisonAffinity,
            'id_matches' => $idMatches,
            'projected_storage' => SQLiteAffinityComparison::storageClass($projectedApr),
            'projected_apr' => $projectedApr,
            'division_storage' => SQLiteAffinityComparison::storageClass($division),
            'division_value' => $division,
            'result_row' => $projectedApr === null
                ? []
                : [1, $division, SQLiteAffinityComparison::storageClass($projectedApr)],
            'dependencies' => [
                'sqlite-real-affinity-storage',
                'sqlite-view-column-affinity-preservation',
                'sqlite-automatic-index-affinity',
            ],
        ];
    }

    private static function upstreamSection(string $viewShape, bool $automaticIndex): string
    {
        return match ($viewShape) {
            'left-view' => $automaticIndex ? 'affinity3-110' : 'affinity3-130',
            'right-view' => $automaticIndex ? 'affinity3-111' : 'affinity3-131',
            'nested-left-view' => $automaticIndex ? 'affinity3-120' : 'affinity3-140',
            'nested-right-view' => $automaticIndex ? 'affinity3-121' : 'affinity3-141',
            'materialized-union' => $automaticIndex ? 'affinity3-122' : 'affinity3-142',
            default => throw new \InvalidArgumentException("SQLite affinity3 view shape {$viewShape} is not supported"),
        };
    }
}
