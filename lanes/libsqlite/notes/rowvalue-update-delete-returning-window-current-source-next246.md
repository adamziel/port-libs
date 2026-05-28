# Row-Value UPDATE/DELETE RETURNING Window Current Source Next246

- Added `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext246Plan`, extending the accepted next242 row-value UPDATE/DELETE RETURNING current-source window path with FILTER-style receipts over retry, suppressed attempt, and yielded pre-rollback RETURNING rows.
- The slice proves retry UPDATE rows are visible after release, retry DELETE rows are absent after release, suppressed-only attempt rows remain restored by rollback, and yielded rows are fenced separately from the released retry stream.
- Added focused coverage in `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext246Test.php`: `66` PASS assertions over row-value parser retention, direct retry UPDATE/DELETE row selection, retry/suppressed/yield partition keys, FILTER update/delete counts, filtered byte totals, receipt tokens, release audit ids, current-source membership, custom savepoint behavior, dependency/non-overlap notes, and malformed inputs.
- Added `wordpress-rowvalue-returning-filter-window-current-source-next246.php` smoke for copied `wp_options` import batches that compute filtered RETURNING window receipts across a rolled-back attempt and a released retry.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext246Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 66 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/wordpress-rowvalue-returning-filter-window-current-source-next246.php
exit 0
```

```text
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext246Plan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext246Plan.php

php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext246Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext246Test.php

php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-filter-window-current-source-next246.php
No syntax errors detected in lanes/libsqlite/examples/wordpress-rowvalue-returning-filter-window-current-source-next246.php
```

```text
git diff --check -- lanes/libsqlite
exit 0
```

Dependency closure: no new support component needed; this reuses native PHP row-value UPDATE/DELETE RETURNING execution, next242 released current-source windows, and bounded PHP window FILTER receipt calculation.

Non-overlap: avoids accepted next242 lag/lead and ROWS/GROUPS frames, next239 ntile/percent/cume windows, trigger RETURNING, WAL/VFS, JSON table, B-tree, PRAGMA, planner range-cost, and encoding clusters.
