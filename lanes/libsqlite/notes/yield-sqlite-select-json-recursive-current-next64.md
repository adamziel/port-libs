# select-json-recursive-current-next64

## Behavior

- Added `SQLiteSelectRecursiveJsonMaterialization::jsonCurrentNextWindow()` for bounded recursive JSON SELECT materializations.
- The helper partitions materialized JSON rows by caller-selected columns, orders each partition, and exposes previous/current/next row metadata with row numbers, partition sizes, first/last flags, and recursive iteration hints.
- The slice is intentionally narrower than accepted parser-level JSON table SELECT source/cursor work and accepted recursive JSON/window materialization batches: it covers current/previous/next window exposure over already materialized recursive JSON rows.

## Focused Evidence

Command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectJsonRecursiveCurrentNext64Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 108 assertions, 0 failures
```

PASS-line delta: `+78` lane-local focused tests, updating `phpPass` from `23341` to `23419` in this isolated worktree.

Application smoke:

```sh
php lanes/libsqlite/examples/application-select-json-recursive-current-next64.php
```

The smoke reports recursive `wp_options` route traversal rows and current/next JSON rule metadata without requiring `ext/sqlite`.

## Non-Overlap

Avoided accepted batch55 surfaces, queued batch56/batch57 surfaces, parser-level JSON table SELECT source/cursor work, JSON hidden/visible constraint pushdown, recursive JSON/window current-next50 through current-next55, and WAL/B-tree/VFS storage clusters. No new support dependency is needed; this reuses the existing native PHP JSON table, JSONB, recursive SELECT, and materialization helpers.
