# ATTACH/DETACH Transaction Current

This slice adds `SQLiteAttachDetachTransactionPlan`, a bounded native PHP
planner for SQLite ATTACH/DETACH transaction admission that is distinct from
the accepted ATTACH WAL/temp schema-cache reprepare work.

Behavior covered:

- Clean attached WAL database DETACH schedules a checkpoint-before-close,
  removes `-wal`/`-shm` sidecars, closes the btree, and renumbers the database
  array.
- Dirty pager pages, active statements, open savepoints, WAL reader snapshots,
  and reserved/exclusive locks block DETACH with database-is-locked admission.
- `main` and `temp` remain reserved schema names.
- Temporary or memory attached databases discard transient pager state before
  btree close.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachDetachTransactionCurrentTest.php`
  passed: `1 test files, 60 assertions, 0 failures`.
- `php lanes/libsqlite/examples/wordpress-attach-detach-transaction-current.php --self-test`
  passed.
- `php -l` passed for changed PHP files.
- `git diff --check -- lanes/libsqlite` passed.

Dashboard delta:

- `phpPass`: `+60` verified focused PASS lines.
- Mapped upstream denominator: unchanged; this is focused PHP behavior coverage,
  not a new upstream inventory unit.

Dependency closure:

- No new support component is required. The slice reuses lane-local bounded
  pager/WAL/VFS state models and adds no root dependency activation.

Non-overlap:

- Avoids accepted ATTACH WAL/temp schema-cache reprepare, WAL byte truncation,
  VFS writer/lock/sync, rollback commit/apply, JSON table source/cursor/
  constraint, B-tree page move/root collapse/overflow freelist, and SELECT SQL
  text/subquery/group/order clusters.
