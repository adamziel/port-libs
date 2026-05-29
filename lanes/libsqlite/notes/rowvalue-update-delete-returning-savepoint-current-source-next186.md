# rowvalue-update-delete-returning-savepoint-current-source-next186

Status: focused PHP behavior growth for current-source row-value UPDATE/DELETE
RETURNING execution.

This slice makes empty row-value tuple lists explicit in the bounded DML
executor and adds savepoint coverage for SQLite's upstream behavior:
`(a,b) IN ()` is false, while `(a,b) NOT IN ()` is true even when the tuple
contains `NULL`. The new `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext186Plan`
records an outer current-source update, an attempted inner stream that is
rolled back, and retry UPDATE/DELETE RETURNING statements that reread the
inner savepoint image.

WordPress smoke:
`wordpress-rowvalue-empty-in-savepoint-current-source-next186.php` models a
copied `wp_options` cleanup/import retry where plugin-generated empty composite
key batches must not delete rows for `IN ()`, but `NOT IN ()` retry cleanup
still selects nullable option tuples after `ROLLBACK TO`.

Verification:

```bash
php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext186Plan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext186Test.php
php -l lanes/libsqlite/examples/wordpress-rowvalue-empty-in-savepoint-current-source-next186.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext186Test.php
php lanes/libsqlite/examples/wordpress-rowvalue-empty-in-savepoint-current-source-next186.php --self-test
git diff --check -- lanes/libsqlite
```

Dashboard delta: update `phpPass` by the focused PASS-line delta verified for
this new test file. Mapped upstream coverage is unchanged; this is focused PHP
executor behavior over already mapped row-value UPDATE/DELETE RETURNING and
savepoint primitives, not a newly hydrated upstream Tcl inventory unit.

Dependency closure: no new support component is needed. The slice reuses the
existing native PHP row-array UPDATE/DELETE RETURNING executor, savepoint
orchestration pattern, and WordPress smoke infrastructure.

Non-overlap: avoids accepted next183 nested delete savepoint handling,
next176 nullable row-value equality/inequality, next172/163 BETWEEN and
NOT BETWEEN savepoint streams, row-value `IS` / `IS NOT`, row-value assignment
parsing, OR ROLLBACK/FAIL/IGNORE/REPLACE conflict handling, DELETE row-value
`IN` non-empty handling, SELECT/JSON/WAL/VFS/B-tree/encoding/PRAGMA clusters,
and suite-runner evidence. The new surface is specifically empty row-value
`IN ()` / `NOT IN ()` semantics inside UPDATE/DELETE RETURNING savepoint
current-source retry execution.

Next task: continue with a non-overlapping SQL executor/planner gap or a
distinct row-value DML edge backed by focused passing tests.
