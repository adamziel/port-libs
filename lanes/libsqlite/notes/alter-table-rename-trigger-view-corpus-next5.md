# ALTER TABLE rename trigger/view corpus next5

## Scope

Adds a bounded native PHP schema SQL rewrite helper for SQLite-style
`ALTER TABLE old RENAME TO new` behavior over sqlite_schema text. The focused
corpus covers table SQL, indexes, triggers, views, foreign-key references,
qualified names, quoted identifiers, and preservation of string literals and
comments.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAlterTableRenameTriggerViewCorpusTest.php`
  - Previous accepted rename-table corpus: `1 test files, 50 assertions, 0 failures`
  - Current rename-table plus rename-column corpus: `1 test files, 98 assertions, 0 failures`
  - +48 focused PASS lines from the rename-column index/trigger/view cases.
- `php lanes/libsqlite/examples/application-alter-rename-trigger-view.php`
  - Emits copied `wp_options` view, trigger, and index SQL rewritten to
    `wp_options_imported`, plus copied `option_name` dependent index/view/
    trigger SQL rewritten to `option_key`, while preserving object names,
    comments, and string literals.

## Status Delta

- `lane-status.json` `phpPass`: `3796 -> 3844` from the verified +48
  focused PASS-line delta in this isolated worktree.
- `UPSTREAM_TEST_MANIFEST.json` mapped coverage is unchanged; this slice adds
  focused PHP coverage for the existing ALTER TABLE rename schema-rewrite row
  instead of claiming a fresh upstream inventory unit.

## Non-Overlap

This slice does not repeat accepted DML trigger conflict inheritance,
schema PRAGMA/DDL introspection, ALTER TABLE DROP COLUMN, JSON table work,
SELECT SQL text dispatch, VFS/WAL/B-tree storage clusters, or recent
high-yield batch4 corpus cases. The next12 addition is narrower than the
accepted table-rename rewrite: it focuses on ALTER TABLE RENAME COLUMN
rewrites for dependent index/view/trigger/table definitions, including
`UPDATE OF`, `old.`/`new.`, partial index predicates, generated expressions,
and quoted identifier preservation.

## Dependency Closure

No new support component is needed. The implementation is native PHP token
rewriting over bounded sqlite_schema SQL text and reuses the existing focused
test harness.
