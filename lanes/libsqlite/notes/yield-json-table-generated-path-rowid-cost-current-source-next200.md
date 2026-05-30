# JSON Table Generated Path Rowid Cost Current Source Next200

## Behavior

- Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext200()`.
- Extends the accepted generated-path/rowid current-source pinning profile with a bounded xFilter argv tape for `json`, `root`, generated path, and rowid terms.
- Preserves current-source reuse only when the pinned source is still valid and the xFilter rowid set intersects the materialized rowids; otherwise it emits reprepare, reseek, or empty-rowid rejection diagnostics.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext200Test.php`
- Result: `1 test files, 47 assertions, 0 failures`
- PASS-line delta: `+47`
- Application smoke: `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next200.php --self-test`

## Non-overlap

Avoids accepted next194 source pinning, next191 xFilter recheck, next190 xColumn yield, JSON visible/hidden constraint pushdown, JSON table cursor/source wiring, and JSON host joins. This slice only records the generated-path/rowid xFilter argv handoff and reuse decision for a pinned current source.

## Dependency Closure

No new support component needed. The slice reuses the native JSON table planner, generated-path rowid cost profiles, current-source pinning, and row materialization already present under `lanes/libsqlite/src`.
