# Row-value UPDATE/DELETE RETURNING window current-source next250

Status: focused PHP behavior growth for row-value UPDATE/DELETE RETURNING
window frames at the current/next source boundary.

This slice adds `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext250Plan`.
It builds on the accepted next247 transition peer groups but models the
distinct `GROUPS ... EXCLUDE TIES` rule: the current RETURNING row remains in
the frame while other rows from the same transition class are removed. The
plan records preserved current rows, removed peer tie rowids, frame receipts,
partition summaries, and a digest fence for retry-after-rollback visibility.

Application path:
`application-rowvalue-returning-window-current-source-next250.php` models a
copied `wp_options` migration where attempted row-value UPDATE/DELETE
RETURNING rows are rolled back, retried, and then audited with EXCLUDE TIES
window semantics before the next source is published.

Verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext250Test.php
# Focused test run: 1 selected test files (root lock skipped)
# 1 test files, 77 assertions, 0 failures

php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext250Plan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext250Test.php
php -l lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next250.php

php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next250.php --self-test
# application-rowvalue-returning-window-current-source-next250 self-test passed

git diff --check -- lanes/libsqlite
```

Expected dashboard movement: `phpPass +77`, from `127481` to `127558`.
Mapped upstream coverage remains `654 / 1589`; this is current-source PHP
behavior over already mapped row-value UPDATE/DELETE RETURNING and window
inventory rather than a fresh upstream manifest-backed row.

Non-overlap: avoids accepted next247 `EXCLUDE GROUP`, next244 lag/lead
transition chains, next243 tuple frames, next241 CURRENT ROW frames,
row-value savepoint retry variants, trigger RETURNING, WAL/VFS, JSON table,
B-tree, PRAGMA, planner, encoding, and suite-evidence clusters. The new
surface is specifically `EXCLUDE TIES` current-row preservation and peer-tie
removal over row-value UPDATE/DELETE RETURNING transition partitions.

Dependency closure: no new support component is needed. The slice reuses
native row-value UPDATE/DELETE RETURNING execution, savepoint rollback,
transition-chain partitioning, and bounded PHP window-frame accounting.

Next task: row-value/window work should move away from EXCLUDE frame variants
unless a fresh current-source blocker is named; prefer distinct SQL executor,
WAL/pager, JSON planner, B-tree, encoding, or suite-admission gaps.
