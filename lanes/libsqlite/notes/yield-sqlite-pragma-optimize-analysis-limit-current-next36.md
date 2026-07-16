# PRAGMA optimize / analysis_limit current next36

## Delta

- Added `SQLitePragmaOptimizePlan` for bounded native PHP `PRAGMA analysis_limit`
  state and `PRAGMA optimize` scheduling.
- Covers query/assignment forms, schema qualification, decimal/hex optimize
  masks, missing or stale `sqlite_stat1` rows, touched-table masks, forced
  analysis, temporary analysis-limit application, and restoration of the prior
  limit.
- Added a Application smoke for copied `wp_options`, `wp_postmeta`, and `wp_posts`
  preflight planning without requiring `ext/sqlite`.

## Verification

Completed in lane worker before handoff:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaOptimizeAnalysisLimitCurrentNext36Test.php`: `1 test files, 50 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-pragma-optimize-analysis-limit-current-next36.php`: emitted main/aux Application optimize plans with restored analysis limits.
- `php -l lanes/libsqlite/src/SQLitePragmaOptimizePlan.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLitePragmaOptimizeAnalysisLimitCurrentNext36Test.php`: no syntax errors.
- `php -l lanes/libsqlite/examples/application-pragma-optimize-analysis-limit-current-next36.php`: no syntax errors.
- `git diff --check -- lanes/libsqlite`: clean.

## Non-overlap

This does not repeat accepted `ANALYZE sqlite_stat1` index ranking,
expression-index range-cost ranking, schema PRAGMA metadata, journal/sync/VFS
work, JSON table planner work, SELECT SQL execution, or B-tree/WAL clusters. It
adds the previously separate `PRAGMA optimize` current-source scheduling and
`analysis_limit` state behavior.

## Dependency closure

No new support component is needed. The slice reuses lane-local PHP arrays for
schema/table metadata and `sqlite_stat1` freshness; a future executor can feed
the resulting `ANALYZE "schema"."table"` operations into the existing stat
planner path.
