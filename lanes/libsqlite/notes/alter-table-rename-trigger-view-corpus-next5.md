# ALTER TABLE rename trigger/view corpus next5

## Scope

Adds a bounded native PHP schema SQL rewrite helper for SQLite-style
`ALTER TABLE old RENAME TO new` behavior over sqlite_schema text. The focused
corpus covers table SQL, indexes, triggers, views, foreign-key references,
qualified names, quoted identifiers, and preservation of string literals and
comments.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAlterTableRenameTriggerViewCorpusTest.php`
  - `1 test files, 50 assertions, 0 failures`
  - 50 PASS lines
- `php lanes/libsqlite/examples/wordpress-alter-rename-trigger-view.php`
  - Emits copied `wp_options` view, trigger, and index SQL rewritten to
    `wp_options_imported` while preserving object names and string literals.

## Status Delta

- `lane-status.json` `phpPass`: `1684 -> 1734` from the verified focused
  PASS-line delta.
- `UPSTREAM_TEST_MANIFEST.json` mapped coverage: `452 -> 453` for one newly
  mapped focused schema DDL inventory row.

## Non-Overlap

This slice does not repeat accepted DML trigger conflict inheritance,
schema PRAGMA/DDL introspection, ALTER TABLE DROP COLUMN, JSON table work,
SELECT SQL text dispatch, VFS/WAL/B-tree storage clusters, or recent
high-yield batch4 corpus cases. It focuses only on ALTER TABLE RENAME schema
SQL rewriting for dependent trigger/view/index/table definitions.

## Dependency Closure

No new support component is needed. The implementation is native PHP token
rewriting over bounded sqlite_schema SQL text and reuses the existing focused
test harness.
