# Pager Savepoint Current/Next 65

This slice adds `SQLitePagerSavepointCurrentNextPlan`, a bounded pager
current/next planner for SAVEPOINT state transitions. It replays lane-local
pager events into the existing `SQLiteSavepointStack`, snapshots the current
transaction/savepoint stack, applies one SAVEPOINT, ROLLBACK TO, RELEASE,
COMMIT, or ROLLBACK action on a cloned stack, and reports the next stack,
dirty-page/WAL-frame ownership, pager journal action, cache action, lock state,
and ordered operations.

The surface is intentionally distinct from accepted WAL byte truncation,
savepoint page-image rollback, VFS savepoint rollback application, rollback
journal commit/apply, and batch56 dirty-page transaction current/next planning.
It covers the pager savepoint command state machine that decides whether
dirty pages are preserved, merged into a parent savepoint, restored from the
savepoint journal, committed by outer RELEASE, or cleared by outer rollback.

Verification:

```sh
php -l lanes/libsqlite/src/SQLitePagerSavepointCurrentNextPlan.php
php -l lanes/libsqlite/tests/SQLitePagerSavepointCurrentNext65Test.php
php -l lanes/libsqlite/examples/application-pager-savepoint-current-next65.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerSavepointCurrentNext65Test.php
php lanes/libsqlite/examples/application-pager-savepoint-current-next65.php
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed. The slice composes
existing lane-local `SQLiteSavepointStack` state with pager current/next
diagnostics and does not require external SQLite, VFS, WAL, or filesystem
support.
