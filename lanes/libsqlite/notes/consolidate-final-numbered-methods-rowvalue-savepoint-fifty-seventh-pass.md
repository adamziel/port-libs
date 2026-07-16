# Row-Value Savepoint Consolidation Fifty-Seventh Pass

Consolidated the row-value select-retry savepoint RELEASE surface away from
worker-numbered diagnostics.

- `executeSelectRetrySavepointRelease()` now returns unsuffixed status,
  dependency, receipt, boolean-flag, non-overlap, default-savepoint, and phase
  names.
- Renamed the direct focused test to
  `SQLiteRowValueSelectRetrySavepointReleaseTest.php`.
- Renamed the Application smoke to
  `application-rowvalue-select-retry-savepoint-release.php`.

Verification:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueSelectRetrySavepointReleaseTest.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-select-retry-savepoint-release.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueSelectRetrySavepointReleaseTest.php`
- `php lanes/libsqlite/examples/application-rowvalue-select-retry-savepoint-release.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component needed; this cleanup reuses the
existing native PHP row-value UPDATE/DELETE RETURNING executor and savepoint
current-source row images.

Non-overlap: consolidation-only row-value savepoint cleanup; no WAL/VFS, JSON,
planner, trigger, B-tree, rowvalue-window, or behavior-counter surface changed.
