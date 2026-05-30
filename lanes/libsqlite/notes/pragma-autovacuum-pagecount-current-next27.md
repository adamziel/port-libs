# PRAGMA Auto Vacuum Page Count Current Next27

This slice adds bounded native PHP coverage for upstream-style
`PRAGMA auto_vacuum` current/pending behavior beside existing PRAGMA
`page_count` state.

Implemented behavior:

- Parses `PRAGMA auto_vacuum`, schema-qualified forms, `=` assignment,
  parenthesized assignment, trailing semicolons, numeric modes `0`/`1`/`2`,
  and keyword modes `NONE`, `OFF`, `FULL`, and `INCREMENTAL`.
- Reports SQLite row shape `auto_vacuum` and keeps `page_count` read-only.
- Keeps attached schema auto-vacuum state isolated from `main`.
- Treats temp-schema assignments as connection-local no-ops.
- Models current versus next behavior for non-empty databases: enabling
  auto-vacuum from `NONE` or disabling from a pointer-map mode reports a
  pending value and `requires_vacuum`, while `FULL`/`INCREMENTAL` switching is
  current.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaAutoVacuumPageCountCurrentNext27Test.php
```

Result: `1 test files, 51 assertions, 0 failures`.

Application smoke:

```sh
php lanes/libsqlite/examples/application-pragma-autovacuum-pagecount-current-next27.php
```

The smoke reports a copied `wp_options` database where enabling incremental
auto-vacuum remains pending until `VACUUM`, while current `page_count` remains
unchanged for import preflight.

Non-overlap: this does not repeat accepted auto-vacuum pointer-map page moves,
overflow freelist release, VFS rollback/savepoint/sync application, JSON table
source/cursor/constraint pushdown, Unicode GLOB, SELECT SQL text/grouping/
subquery/order-expression work, or prior PRAGMA runtime catalog metadata.

Dependency closure: no new support component is needed; this reuses the
lane-local bounded PRAGMA state helper.
