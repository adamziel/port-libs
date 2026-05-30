# Pager Master Journal Reader Cache Current Source Next153

This slice fixes the canonical pager master-journal reader-cache member parser used by the consolidated current-source family. Master-journal member lists now accept both newline-separated and NUL-terminated journal path records before validating reader-cache reuse after recovery.

Red-first evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheMasterRecoveryTest.php`
- Before the fix: `1 test files, 83 assertions, 2 failures`
- Failing assertions: NUL-terminated master members were treated as one member and mixed newline/NUL member lists were not deduplicated correctly.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheMasterRecoveryTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheMasterRecoveryTest.php` -> `1 test files, 84 assertions, 0 failures`
- `php tools/run-tests.php $(rg --files lanes/libsqlite/tests | rg 'SQLitePagerMasterJournalReaderCache.*Test\.php' | sort -V)` -> `149 test files, 9993 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this reuses the existing lane-local master-journal reader-cache model and broadens accepted member-list parsing.

Non-overlap: this does not change suite-evidence/window root-gate status accounting, dashboard/progress files, VFS/WAL writer paths, B-tree, SQL planner, JSON, encoding, PRAGMA, or numbered production class surfaces.
