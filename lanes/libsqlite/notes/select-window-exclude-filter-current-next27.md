# SELECT Window EXCLUDE/FILTER Current Next 27

2026-05-27 isolated slice `yield-sqlite-select-window-exclude-filter-current-next27`.

## Behavior

Extends parser-level `SQLiteSelectSql` window-frame handling beyond the already accepted `CURRENT ROW AND N FOLLOWING` form. The parser now admits bounded SQLite frames of the form:

- `ROWS BETWEEN N PRECEDING AND CURRENT ROW`
- `ROWS BETWEEN N PRECEDING AND N FOLLOWING`
- `RANGE BETWEEN N PRECEDING AND CURRENT ROW`
- `GROUPS BETWEEN N PRECEDING AND CURRENT ROW`
- `BETWEEN CURRENT ROW AND CURRENT ROW`

The existing native window executor then applies aggregate `FILTER` predicates after frame and `EXCLUDE` selection, covering `EXCLUDE CURRENT ROW`, `EXCLUDE GROUP`, and `EXCLUDE TIES` over copied `wp_options` rows.

## Verification

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectWindowExcludeFilterCurrentNext27Test.php
Focused test run: 1 selected test files (root lock skipped)
51 PASS lines
1 test files, 51 assertions, 0 failures
```

Additional verification for this handoff is recorded in the final worker response: PHP lint for changed PHP files, the Application smoke example, and `git diff --check -- lanes/libsqlite`.

## Status Delta

- `phpPass`: `9342 -> 9393` (`+51` verified PASS lines in the new focused file).
- `benchmarkDenominator.mapped`: unchanged; no fresh upstream inventory unit claimed.
- Root harness: not run; isolated micro-slice.

## Non-Overlap

Avoids accepted batch24 surfaces and the newer accepted clusters named in the prompt: JSON table cursor/source/constraint work, expression `ORDER BY`, SELECT subqueries, grouped SELECT text, VFS file writer/sync/lock/rollback-commit, WAL byte/checkpoint transaction paths, B-tree page moves/root collapse/overflow freelist release, Unicode GLOB, and rollback-journal application.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP SELECT parser/executor, window aggregate helper, predicate evaluator, and lane TestRunner; it does not require ext/sqlite, upstream shell-outs, or shared dependency activation.
