# PRAGMA optimize analysis schema current-source next107

## Behavior

- Extends `SQLitePragmaOptimizePlan` with schema/stat current-source tokens for
  `PRAGMA optimize` worklists.
- Stable table snapshots still schedule `ANALYZE` for touched, missing-stat,
  forced, or row-count-drift cases.
- Stale schema cookies, stale `sqlite_stat1` cookies, or stale source ids skip
  analysis with `stale-current-source` even when the force-all mask is used.
- Schema-qualified `PRAGMA network.optimize` remains isolated from main-schema
  current-source state.

## Application Relevance

Copied Application SQLite databases can run optimize preflights for `wp_options`,
`wp_postmeta`, and network metadata tables without scheduling `ANALYZE` from an
obsolete schema/stat snapshot after plugin migrations or multisite attachment
changes.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaOptimizeAnalysisLimitCurrentNext36Test.php lanes/libsqlite/tests/SQLitePragmaOptimizeAnalysisSchemaCurrentSourceNext107Test.php`
  - `2 test files, 106 assertions, 0 failures`
  - New next107 focused file contributes `56` PASS lines.
- `php lanes/libsqlite/examples/application-pragma-optimize-current-source-next107.php`
  - Emits main/network optimize plans with stale current-source rows skipped.

## Non-Overlap

This avoids the accepted batch104/105 PRAGMA foreign-key/integrity pointer-map
checks and the older next36 `analysis_limit` scheduling surface. It narrows
only `PRAGMA optimize` current-source schema/stat reuse and does not touch
queued schema catalog/FK recursive behavior.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
PRAGMA optimize planner and adds bounded current-source metadata only.
