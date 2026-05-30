# Pager Reader Cache Stable Fence Consolidation

Consolidated a four-method pager master-journal reader-cache production band
into descriptive stable methods on
`SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan`:

- `stableMasterReadTokenFence()`
- `transactionSnapshotDigestFence()`
- `masterMemberTicketFence()`
- `memberJournalHeaderDigestFence()`

The direct tests and WordPress examples were renamed away from generated
`CurrentSourceNext193` through `CurrentSourceNext196` filenames and migrated to
the stable method names. Existing observable status, dependency, operation, and
non-overlap strings are preserved so downstream evidence keys do not silently
change during cleanup.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l` for the four renamed direct tests
- `php -l` for the four renamed WordPress examples
- `php tools/run-tests.php` for the four renamed direct tests
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCache*Test.php`
- `php` self-test for the four renamed WordPress examples
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this is a naming
consolidation over existing pager reader-cache behavior.
