## Pager Master Reader Cache Numbered Entry Cleanup

Consolidated five remaining numbered production entry helpers on
`SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` into stable
descriptive method names:

- `planCurrentMasterJournalSourceEpochFence()`
- `planMasterJournalMemberDigestRefreshFence()`
- `planReaderCacheMasterJournalRecoveryFence()`
- `planPinnedReaderCacheMasterJournalRevalidation()`
- `planMasterJournalDeleteDirectorySyncFence()`

The direct tests and examples now call the descriptive entries. Accepted
observable status strings, dependency labels, operation names, and proof keys
were preserved.

Verification:

- `php -l` for the changed production file, five changed direct test files, and
  five changed examples.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext162Test.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext163Test.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext164Test.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext165Test.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext191Test.php`
  - `5 test files, 425 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext*Test.php`
  - `120 test files, 8684 assertions, 0 failures`
- `php` self-test runs for the five changed pager-master Application examples.
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component needed; this is a production
symbol consolidation over existing pager master-journal reader-cache behavior.
