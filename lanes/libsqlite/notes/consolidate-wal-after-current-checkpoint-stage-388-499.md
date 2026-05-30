# WAL after-current checkpoint stage consolidation

Consolidated `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`
numbered production wrappers for after-current checkpoint stages 388-403 and
420-499 onto the stable `afterCurrentCheckpointStage()` helper. Direct WAL
tests and WordPress examples now call the canonical stage API while preserving
the existing status, dependency, operation, receipt, and reason strings.

Verification:

- `php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php`
- `php -l` for the six changed direct tests and six changed examples
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext388403Test.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext420435Test.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext436451Test.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext452467Test.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext468483Test.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext484499Test.php` -> `6 test files, 390 assertions, 0 failures`
- `php tools/run-tests.php $(find lanes/libsqlite/tests -maxdepth 1 -name 'SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext*Test.php' | sort)` -> `2 test files, 10205 assertions, 0 failures`
- Changed WordPress example files execute with `php ... >/dev/null`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component needed; this reuses existing
lane-local WAL checkpoint receipt and source-fencing helpers.
