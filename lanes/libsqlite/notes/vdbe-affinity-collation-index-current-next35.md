# VDBE affinity/collation index current-next35

This slice adds current/next key-record inspection to `SQLiteVdbeIndexCursor`.
The cursor now exposes `currentRecord()`, `nextRecord()`, and
`compareCurrentToNext()` so VDBE-style index loops can compare adjacent index
keys with the same affinity, collation, descending-order, storage-class, and
rowid-tie behavior already used by the sorter comparator.

Focused movement:

- Added `SQLiteVdbeIndexAffinityCollationCurrentNext35Test.php` with 56 PASS
  lines covering NOCASE duplicate option-name keys, RTRIM/BINARY cache
  boundaries, numeric and BLOB affinity probes, rowid tie order, prefix seeks,
  EOF current/next records, invalid selected key columns, and bad affinity /
  collation guards.
- Added `application-vdbe-index-affinity-collation-current-next35.php` to smoke
  copied `wp_options` option-name index current/next records and boundary
  comparisons without requiring ext/sqlite.
- Updated `lane-status.json` `phpPass` from 12271 to 12327 by the verified
  +56 focused PASS-line delta.

Verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeIndexAffinityCollationCurrentNext35Test.php
# 1 test files, 56 assertions, 0 failures

php lanes/libsqlite/examples/application-vdbe-index-affinity-collation-current-next35.php
# prints autoload plugin rowids [10,20], cache current/next records, and network rowid 60
```

Non-overlap:

This does not repeat accepted SELECT SQL text/JOIN/GROUP/subquery/expression
ORDER BY, JSON table cursor/source/hidden/visible constraints, Unicode GLOB,
VFS writer/sync/lock/rollback, WAL checkpoint/savepoint byte truncation, B-tree
page move/root collapse/overflow freelist, aggregate ORDER BY, or prior VDBE
sorter/distinct cursor clusters. The new surface is adjacent current/next
inspection on the index cursor itself for VDBE-like index scans.

Dependency closure:

No new support component is needed. The implementation reuses the existing
native PHP `SQLiteVdbeSortCompare`, `SQLiteAffinityComparison`, and
`SQLiteBlobValue` primitives.
