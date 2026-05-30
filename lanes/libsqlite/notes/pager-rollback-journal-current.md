# Pager Rollback Journal Current

Implemented `SQLitePagerRollbackJournalCurrentPlan` to admit dirty pager pages
only when a synced rollback journal contains checksum-valid before-images that
match the current database page images.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerRollbackJournalCurrentTest.php`
- Result: `1 test files, 67 assertions, 0 failures`
- PASS-line delta: `+52`

Application smoke:

- `php lanes/libsqlite/examples/application-pager-rollback-journal-current.php --self-test`

Dependency closure: no new support component needed; this reuses lane-local
rollback journal parsing, checksum validation, and pager current-page image
fencing.

Non-overlap: this does not repeat rollback-journal commit apply, hot-journal
recovery/application, super-journal commit, WAL byte truncation, or numbered
`CurrentSourceNext` consolidation.
