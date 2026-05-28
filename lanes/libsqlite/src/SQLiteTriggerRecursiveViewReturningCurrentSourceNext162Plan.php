<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext162Plan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentRoots
     * @param list<array<string,mixed>> $firstNextRoots
     * @param list<array<string,mixed>> $secondNextRoots
     * @param array<string,mixed> $currentView
     * @param array<string,mixed> $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,string,int):mixed> $returning
     * @param array{release_staged_sources?:int,max_depth?:int,savepoint?:string,current_generation?:string,first_next_generation?:string,second_next_generation?:string} $options
     * @return array<string,mixed>
     */
    public static function execute(
        array $rows,
        array $currentRoots,
        array $firstNextRoots,
        array $secondNextRoots,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $releaseCount = self::releaseCount($options['release_staged_sources'] ?? 0);
        $currentGeneration = self::token((string) ($options['current_generation'] ?? 'wp-import-current-162'), 'current generation');
        $firstNextGeneration = self::token((string) ($options['first_next_generation'] ?? 'wp-import-next-162-a'), 'first next generation');
        $secondNextGeneration = self::token((string) ($options['second_next_generation'] ?? 'wp-import-next-162-b'), 'second next generation');
        $savepoint = self::token((string) ($options['savepoint'] ?? 'wp_recursive_view_next162'), 'savepoint');
        $maxDepth = $options['max_depth'] ?? 8;

        $first = SQLiteTriggerRecursiveViewReturningCurrentSourceNext160Plan::execute(
            $rows,
            $currentRoots,
            $firstNextRoots,
            $currentView,
            $nextView,
            $returning,
            [
                'release_next_source' => $releaseCount >= 1,
                'max_depth' => $maxDepth,
                'savepoint' => $savepoint . '_first',
                'current_generation' => $currentGeneration,
                'next_generation' => $firstNextGeneration,
            ],
        );

        $secondBaseRows = $releaseCount >= 1 ? $first['after_savepoint'] : $rows;
        $second = SQLiteTriggerRecursiveViewReturningCurrentSourceNext160Plan::execute(
            $secondBaseRows,
            $currentRoots,
            $secondNextRoots,
            $currentView,
            $nextView,
            $returning,
            [
                'release_next_source' => $releaseCount >= 2,
                'max_depth' => $maxDepth,
                'savepoint' => $savepoint . '_second',
                'current_generation' => $currentGeneration,
                'next_generation' => $secondNextGeneration,
            ],
        );

        $firstVisible = $releaseCount >= 1;
        $secondVisible = $releaseCount >= 2;
        $visibleRows = array_merge(
            $first['current_returning_rows'],
            $firstVisible ? $first['attempted_next_returning_rows'] : [],
            $secondVisible ? $second['attempted_next_returning_rows'] : [],
        );
        $suppressedRows = array_merge(
            $firstVisible ? [] : $first['attempted_next_returning_rows'],
            $secondVisible ? [] : $second['attempted_next_returning_rows'],
        );

        return [
            'status' => match ($releaseCount) {
                0 => 'trigger-recursive-view-returning-current-source-queue-held-next162',
                1 => 'trigger-recursive-view-returning-current-source-first-next-released-next162',
                default => 'trigger-recursive-view-returning-current-source-all-next-released-next162',
            },
            'savepoint' => $savepoint,
            'current_generation' => $currentGeneration,
            'staged_generations' => [$firstNextGeneration, $secondNextGeneration],
            'visible_generation' => $releaseCount === 0 ? $currentGeneration : ($releaseCount === 1 ? $firstNextGeneration : $secondNextGeneration),
            'current_source' => $first['source_barrier']['current_source'],
            'next_source' => $first['source_barrier']['next_source'],
            'first_stage' => $first,
            'second_stage' => $second,
            'next_source_queue' => [
                [
                    'generation' => $firstNextGeneration,
                    'roots' => array_values($firstNextRoots),
                    'attempted_returning' => count($first['attempted_next_returning_rows']),
                    'attempted_recursive' => count($first['attempted_next_recursive_rows']),
                    'visible' => $firstVisible,
                    'visibility_keys' => array_column($first['attempted_next_returning_rows'], 'visibility_key'),
                ],
                [
                    'generation' => $secondNextGeneration,
                    'roots' => array_values($secondNextRoots),
                    'attempted_returning' => count($second['attempted_next_returning_rows']),
                    'attempted_recursive' => count($second['attempted_next_recursive_rows']),
                    'visible' => $secondVisible,
                    'visibility_keys' => array_column($second['attempted_next_returning_rows'], 'visibility_key'),
                ],
            ],
            'returning_visibility' => [
                'visible' => array_column($visibleRows, 'visibility_key'),
                'suppressed' => array_column($suppressedRows, 'visibility_key'),
                'visible_count' => count($visibleRows),
                'suppressed_count' => count($suppressedRows),
            ],
            'statement_rows' => count($visibleRows),
            'attempted_statement_rows' => count($first['current_returning_rows']) + count($first['attempted_next_returning_rows']) + count($second['attempted_next_returning_rows']),
            'changes' => $releaseCount === 0 ? 0 : ($releaseCount === 1 ? $first['changes'] : $first['changes'] + $second['next_changes']),
            'after_savepoint' => $releaseCount === 0 ? $rows : ($releaseCount === 1 ? $first['after_savepoint'] : $second['after_savepoint']),
            'yield_boundary' => match ($releaseCount) {
                0 => 'recursive-view-returning-current-source-two-next-yields-held-next162',
                1 => 'recursive-view-returning-current-source-first-next-yield-released-next162',
                default => 'recursive-view-returning-current-source-all-next-yields-released-next162',
            },
            'dependencies' => array_values(array_unique(array_merge($first['dependencies'], [
                'sqlite-trigger-recursive-view-returning-current-source-next162',
                'sqlite-recursive-view-returning-next-source-fifo-queue',
                'sqlite-current-source-retained-across-multiple-returning-yields',
            ]))),
            'dependency_closure' => 'reuses-native-recursive-view-returning-current-source-barriers',
        ];
    }

    private static function releaseCount(mixed $value): int
    {
        $count = (int) $value;
        if ($count < 0 || $count > 2) {
            throw new InvalidArgumentException('SQLite trigger recursive view next162 release count must be 0, 1, or 2');
        }

        return $count;
    }

    private static function token(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite trigger recursive view next162 {$label} is malformed");
        }

        return $value;
    }
}
