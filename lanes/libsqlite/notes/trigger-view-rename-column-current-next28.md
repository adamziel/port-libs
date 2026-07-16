# Trigger/view rename-column current next28

## Scope

This slice tightens bounded SQLite-style `ALTER TABLE ... RENAME COLUMN`
schema rewrites for dependent trigger and view SQL when identifiers matching
the old column name are actually result aliases, table aliases, derived-table
aliases, CTE names, or source-table names. Real `wp_options.option_name`,
bare `option_name`, and `old.` / `new.` trigger references are still rewritten
to `option_key`; aliases, CTE names, source names, comments, string literals,
and `raise()` messages are preserved.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerViewRenameColumnCurrentNext28Test.php`
  - Red-first before the implementation fix: `1 test files, 56 assertions, 10 failures`
  - After fix: `1 test files, 56 assertions, 0 failures`
  - +56 focused PASS lines for the new lane-scoped current-source test file.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAlterTableRenameColumnCurrentNext15Test.php lanes/libsqlite/tests/SQLiteAlterTableRenameTriggerViewCorpusTest.php`
  - Regression bundle: `2 test files, 162 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-trigger-view-rename-column-current-next28.php`
  - Emits copied `wp_options` view/trigger SQL for `option_name -> option_key`
    while preserving implicit output aliases, CTE names, table aliases, and
    `raise()` messages that still intentionally spell `option_name`.

## Status Delta

- `lane-status.json` `phpPass`: `10028 -> 10084` from the verified +56
  focused PASS-line delta in this isolated worktree.
- `UPSTREAM_TEST_MANIFEST.json` mapped coverage is unchanged; this is focused
  PHP coverage for an already mapped ALTER TABLE rename-column schema-rewrite
  surface, not a fresh upstream inventory claim.

## Non-Overlap

This does not repeat accepted table rename rewrites, the broad rename-column
current-next15 corpus, trigger/view name-resolution slices, JSON/WAL/B-tree/VFS
accepted clusters, SELECT SQL execution work, or recent batch23-25 surfaces.
The new behavior is the narrower trigger/view schema-text rewrite edge where
aliases or source names collide with the old column name.

## Dependency Closure

No new support component is needed. The slice reuses native PHP sqlite_schema
token rewriting and the existing focused PHP lane harness.
