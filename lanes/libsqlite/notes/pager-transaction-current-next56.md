# Pager Transaction Current Next56

This slice adds `SQLitePagerTransactionStatePlan`, a bounded pager transaction
state model for current/next dirty-page, spilled-page, page-count,
change-counter, journal-action, and lock/cache cleanup behavior across commit,
rollback, and no-dirty close paths.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLitePagerTransactionStatePlan.php
php -l lanes/libsqlite/tests/SQLitePagerTransactionCurrentNext56Test.php
php -l lanes/libsqlite/examples/application-pager-transaction-current-next56.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerTransactionCurrentNext56Test.php
php lanes/libsqlite/examples/application-pager-transaction-current-next56.php --self-test
git diff --check -- lanes/libsqlite
```

Expected dashboard movement: `phpPass` +61, from 20008 to 20069, from the 61
independent PASS lines in `SQLitePagerTransactionCurrentNext56Test.php`.
Mapped upstream coverage is unchanged.

Non-overlap: this avoids accepted/queued WAL byte truncation, WAL checkpoint
transactions, rollback-journal commit/apply, VFS file writer/lock/sync apply,
savepoint page-image rollback, B-tree overflow/freelist/page-move work, JSON
table planner/source/cursor work, and SELECT SQL execution clusters. The new
surface is pager transaction current/next state after dirty-page cache events.

Dependency closure: no new support component is needed. The implementation is
lane-local native PHP and composes with existing pager/VFS transaction
primitives without requiring `ext/sqlite`.
