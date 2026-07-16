# PRAGMA Schema Current-Source Resolve Next11

This slice adds bounded native PHP behavior for schema PRAGMAs executed from an attached schema catalog:

- Unqualified `PRAGMA table_info`, `table_xinfo`, and `index_list` now resolve the current source table using SQLite-style `temp`, `main`, then attached database search order.
- Unqualified `PRAGMA index_info` and `index_xinfo` resolve the current source index across the same schema order.
- Explicit schema-qualified PRAGMAs remain pinned to the requested schema.
- Missing unqualified targets keep SQLite-style empty rowsets from the main catalog, while missing explicit schemas still raise.
- The Application smoke demonstrates copied `wp_options` temp-table shadowing plus attached `wp_sitemeta` index introspection without `ext/sqlite`.

Focused verification on this worktree:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaSchemaCurrentSourceResolveTest.php
Focused test run: 1 selected test files (root lock skipped)
40 PASS cases, 40 assertions, 0 failures
```

Dashboard delta:

- `phpPass`: +40, from 3796 to 3836.
- `benchmarkDenominator.mapped`: unchanged; this is focused PHP behavior coverage for an existing PRAGMA/schema surface, not a newly hydrated upstream Tcl inventory unit.

Non-overlap:

This avoids accepted standalone schema PRAGMA catalog rows, attach/temp schema catalog rows, PRAGMA integrity/quick_check, synchronous/journal/locking-mode state, JSON table source/cursor/constraint work, SELECT SQL text/subquery/GROUP/ORDER/LIMIT clusters, VFS writer/sync/lock/rollback clusters, WAL checkpoint/savepoint byte truncation, B-tree page move/root collapse/overflow release, and Unicode GLOB work.

Dependency closure:

No new support component is needed. The slice reuses lane-local schema record, attached-schema catalog, and schema PRAGMA catalog primitives.
