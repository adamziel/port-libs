# ALTER TABLE rename column current next15

## Scope

This slice tightens bounded SQLite-style `ALTER TABLE ... RENAME COLUMN`
schema rewrites for current trigger/view SQL text. It covers dependent view
SELECT bodies, trigger `UPDATE OF` lists, `old.` / `new.` pseudo-table
references, aliases, CTEs, nested SELECTs, compound SELECTs, window/filter
expressions, expression indexes, generated columns, checks, and foreign-key
references while preserving object names, string literals, comments, function
names, and explicit result aliases.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAlterTableRenameColumnCurrentNext15Test.php`
  - Red-first before the implementation fix: `1 test files, 64 assertions, 1 failures`
  - After fix: `1 test files, 64 assertions, 0 failures`
  - +64 focused PASS lines for the new lane-scoped current-source test file.
- `php lanes/libsqlite/examples/application-alter-rename-column-current-next15.php`
  - Emits copied `wp_options` view, trigger, and expression-index SQL for
    `option_name -> option_key`, preserving explicit `AS option_name` output
    aliases and string literals while rewriting source references.

## Status Delta

- `lane-status.json` `phpPass`: `4362 -> 4426` from the verified +64
  focused PASS-line delta in this isolated worktree.
- `UPSTREAM_TEST_MANIFEST.json` mapped coverage is unchanged; this is focused
  PHP coverage for the existing ALTER TABLE rename schema-rewrite inventory
  surface, not a newly mapped upstream unit.

## Non-Overlap

This does not repeat accepted table rename rewrites, the earlier broad
rename-column/index/trigger corpus, parser-level SELECT SQL text/JOIN/GROUP BY
execution, JSON table cursor/source/constraint work, VFS/WAL/B-tree accepted
clusters, Unicode GLOB, rollback-journal commit, or current B-tree overflow
freelist slices. The new behavior is the current trigger/view rename-column
rewrite edge where explicit output aliases matching the old column name must
remain aliases while current-source column references are rewritten.

## Dependency Closure

No new support component is needed. The slice reuses native PHP sqlite_schema
token rewriting and the existing focused PHP lane harness.
