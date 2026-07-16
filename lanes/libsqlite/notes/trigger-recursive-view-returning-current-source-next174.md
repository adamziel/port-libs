# Trigger Recursive View RETURNING Current Source Next174

Status: focused PHP behavior growth for
`trigger-recursive-view-returning-current-source-next174`.

This slice adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext174Plan`.
It composes the accepted next170 recursive `INSTEAD OF` view-trigger
`RETURNING` source-drain/reprepare barrier with a current-source duplicate-key
watermark. When staged next-source rows reuse keys already yielded by the
current recursive view cursor, they stay held behind the current-source
watermark even after a matching reprepare token admits non-conflicting staged
rows.

Application smoke:
`application-trigger-recursive-view-returning-current-source-next174.php` models a
copied `wp_options` import through a recursive view trigger where reparsed
next-source rows would yield duplicate `option_name` values. The current-source
`RETURNING` rows remain immutable while non-conflicting staged network rows can
be reported after the reprepare boundary.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext174Plan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext174Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext174Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext174Test.php`
- `php -l lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next174.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next174.php`
- Red-first focused run before expectation cleanup:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext174Test.php`
  - `1 test files, 54 assertions, 2 failures`
- Final focused run:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext174Test.php`
  - `1 test files, 55 assertions, 0 failures`
- Application smoke:
  - `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next174.php`
  - `application-trigger-recursive-view-returning-current-source-next174 self-test passed`

Expected dashboard movement: `+55` focused PHP PASS lines for the new
lane-scoped test file. No mapped upstream denominator change is claimed.

Dependency closure: no new support component is needed; the slice reuses native
recursive view `RETURNING`, current-source cursor, and reprepare barrier
primitives already present in the libsqlite lane.

Non-overlap: this avoids accepted next170 source-drain/reprepare behavior,
next168 view DELETE `RETURNING`, next149/next144 UPSERT view `RETURNING`,
next128 deferred recursive view behavior, savepoint rollback, deferred-FK,
schema reparse, and WAL/VFS current-source clusters. The new behavior is the
duplicate-key watermark for staged next-source rows after current recursive
view `RETURNING` rows have already been yielded.

Next task: wire this watermark into the broader parser/executor view-trigger
path once the native SQL DML executor owns recursive `INSTEAD OF` view trigger
statements directly.
