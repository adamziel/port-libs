# VACUUM INTO Auto-vacuum Page Edge Next14

This slice extends `SQLiteVacuumBackupSerializePlan::vacuumInto()` so a VACUUM
INTO destination can apply the same bounded page-size / auto-vacuum rewrite
metadata used by the VACUUM planner. The plan now reports destination
auto-vacuum mode, incremental flag, largest-root header field, pointer-map page
numbers, and pointer-map entry page numbers while preserving the existing
write/sync/sync-directory operation order for callers.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLiteVacuumBackupSerializePlan.php
php -l lanes/libsqlite/tests/SQLiteVacuumIntoAutoVacuumPageEdgeTest.php
php -l lanes/libsqlite/examples/application-vacuum-into-autovacuum-page-edge.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVacuumIntoAutoVacuumPageEdgeTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVacuumBackupSerializeCorpusTest.php lanes/libsqlite/tests/SQLiteVacuumPageSizeAutoVacuumCorpusTest.php lanes/libsqlite/tests/SQLiteVacuumIntoAutoVacuumPageEdgeTest.php
php lanes/libsqlite/examples/application-vacuum-into-autovacuum-page-edge.php
```

Results:

- New focused file: `1 test files, 53 assertions, 0 failures`.
- Related focused VACUUM suite: `3 test files, 215 assertions, 0 failures`.
- Application smoke reported incremental auto-vacuum with pointer-map pages
  `[2, 105]` and 103 pointer-map entry pages for the copied 512-byte database.

Dashboard delta:

- `phpPass` increases by exactly 53 new PASS lines.
- `benchmarkDenominator.mapped` is unchanged; no new upstream inventory unit
  was mapped.

Non-overlap:

This avoids accepted VACUUM page-size header-only coverage, VACUUM backup /
serialize byte-copy coverage, B-tree page relocation, overflow freelist release,
root collapse, VFS writer/sync/rollback/lock clusters, WAL byte truncation and
checkpoint transaction clusters, JSON table source/cursor/constraint work,
Unicode GLOB, grouped SELECT SQL text, SELECT subqueries, expression ORDER BY,
and range-cost planner slices.

Dependency closure:

No new support component is needed. The slice reuses native PHP
`SQLiteVacuumPageSizeAutoVacuumPlan`, `SQLiteDatabase`, and pointer-map stride
helpers; follow-up transaction application can feed the emitted write/sync
operations into the already accepted VFS writer paths.
