<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTriggerRecursiveViewReturningCurrentSourceNext167Plan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $currentInput
     * @param list<array<string,mixed>> $nextInput
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $currentView
     * @param array{name:string,source:string,trigger:string,trigger_source:string,columns:list<string>,mapping:array<string,string>,recursive_column?:string,recursive_suffix?:string,audit_label?:string} $nextView
     * @param list<string|array{expr:string,as?:string}|callable(array<string,mixed>,array<string,mixed>,?array<string,mixed>,string,int,int,string):mixed> $returning
     * @param array{key?:string,savepoint?:string,recursive_triggers?:bool,max_depth?:int,admit_next_source?:bool,skip_column?:string,skip_value?:mixed,conflict_action?:string,page_size?:int,drain_cursor?:string} $options
     * @return array<string,mixed>
     */
    public static function execute(
        array $rows,
        array $currentInput,
        array $nextInput,
        array $currentView,
        array $nextView,
        array $returning,
        array $options = [],
    ): array {
        $pageSize = (int) ($options['page_size'] ?? 2);
        if ($pageSize < 1) {
            throw new InvalidArgumentException('SQLite recursive view RETURNING next167 page size must be positive');
        }
        $drainCursor = self::token((string) ($options['drain_cursor'] ?? 'current-returning-drain-167'), 'drain cursor');

        $base = SQLiteTriggerRecursiveViewReturningCurrentSourceNext164Plan::execute(
            $rows,
            $currentInput,
            $nextInput,
            $currentView,
            $nextView,
            $returning,
            $options,
        );

        $currentPages = self::pages($base['current_returning_rows'], $pageSize, 'current', $drainCursor);
        $attemptedNextPages = self::pages($base['attempted_next_returning_rows'], $pageSize, 'attempted-next', $drainCursor);
        $nextPages = self::pages($base['next_returning_rows'], $pageSize, 'next', $drainCursor);
        $currentComplete = self::pagesDrained($currentPages);
        $nextSourceAdmitted = (bool) $base['next_source_admitted'];

        return $base + [
            'status_next167' => $nextSourceAdmitted
                ? 'trigger-recursive-view-returning-next-source-admitted-after-current-drain-next167'
                : 'trigger-recursive-view-returning-current-source-drain-fenced-next167',
            'drain_cursor' => $drainCursor,
            'page_size' => $pageSize,
            'current_returning_pages' => $currentPages,
            'attempted_next_returning_pages' => $attemptedNextPages,
            'next_returning_pages' => $nextPages,
            'current_drain_complete' => $currentComplete,
            'next_source_visible_after_current_drain' => $nextSourceAdmitted && $currentComplete,
            'attempted_next_source_blocked_by_current_drain' => !$nextSourceAdmitted && $currentComplete,
            'visible_returning_pages' => $nextSourceAdmitted ? array_merge($currentPages, $nextPages) : $currentPages,
            'blocked_next_source_pages' => $nextSourceAdmitted ? [] : $attemptedNextPages,
            'source_signatures' => [
                'current' => self::sourceSignature($base['current_view']),
                'next' => self::sourceSignature($base['next_view']),
                'visible' => self::sourceSignature($base['visible_view']),
            ],
            'yield_boundary_next167' => $nextSourceAdmitted
                ? 'recursive-view-returning-next167-next-source-visible-after-current-pages-drained'
                : 'recursive-view-returning-next167-next-source-blocked-until-current-pages-drain',
            'dependencies_next167' => [
                'sqlite-trigger-recursive-view-returning-current-source-next167',
                'sqlite-returning-current-source-drain-before-next-source',
                'sqlite-view-trigger-recursive-returning-page-yield',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private static function pages(array $rows, int $pageSize, string $phase, string $cursor): array
    {
        $pages = [];
        foreach (array_chunk($rows, $pageSize) as $index => $chunk) {
            $last = $index === intdiv(max(count($rows) - 1, 0), $pageSize);
            $pages[] = [
                'cursor' => $cursor . ':' . $phase . ':' . $index,
                'phase' => $phase,
                'page' => $index,
                'count' => count($chunk),
                'drained' => true,
                'last' => $last,
                'sources' => array_values(array_unique(array_column($chunk, 'source'))),
                'trigger_sources' => array_values(array_unique(array_column($chunk, 'trigger_source'))),
                'names' => array_column(array_column($chunk, 'returning'), 'option_name'),
                'rows' => $chunk,
            ];
        }

        return $pages;
    }

    /**
     * @param list<array<string,mixed>> $pages
     */
    private static function pagesDrained(array $pages): bool
    {
        foreach ($pages as $page) {
            if (($page['drained'] ?? false) !== true) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string,mixed> $view
     */
    private static function sourceSignature(array $view): string
    {
        return implode('|', [
            (string) ($view['name'] ?? ''),
            (string) ($view['source'] ?? ''),
            (string) ($view['trigger'] ?? ''),
            (string) ($view['trigger_source'] ?? ''),
            implode(',', (array) ($view['columns'] ?? [])),
        ]);
    }

    private static function token(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@\\/-]+$/', $value) !== 1) {
            throw new InvalidArgumentException("SQLite recursive view RETURNING next167 {$label} is malformed");
        }

        return $value;
    }
}
