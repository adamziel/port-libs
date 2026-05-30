# Row-Value UPDATE/DELETE RETURNING Window Current Source Next242

- Added `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext242Plan`, extending the existing row-value UPDATE/DELETE RETURNING savepoint/window path with lag/lead receipts, ROWS frame receipts, GROUPS peer-frame receipts, and a current-source release seal after rollback/retry.
- Added focused coverage in `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext242Test.php`: `68` PASS assertions over row-value subquery predicates, retry UPDATE/DELETE selected rows, retry lag/lead windows, ROWS and GROUPS frame ids/sums, suppressed attempt rows, yield rows, source-generation seals, final current-source membership, custom savepoint behavior, dependency notes, non-overlap notes, and malformed inputs.
- Added `application-rowvalue-returning-window-current-source-next242.php` smoke for copied `wp_options` import batches that rollback a row-value UPDATE/DELETE RETURNING attempt and retry from the savepoint image while proving suppressed attempt rows stay out of the released current source.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext242Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 68 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next242.php
exit 0
```

Dependency closure: no new support component needed; this reuses the native PHP row-value UPDATE/DELETE RETURNING executor, savepoint current-source image, and existing statement-partition window rows.

Non-overlap: this slice avoids accepted next238 pair classification, next239 ntile/percent/cume partition windows, trigger RETURNING, WAL/VFS, JSON table, B-tree, PRAGMA, and encoding clusters.
