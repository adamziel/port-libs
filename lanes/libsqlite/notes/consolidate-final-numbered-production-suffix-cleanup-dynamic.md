# Pager Reader-Cache Numbered Suffix Cleanup

Consolidated the pager master-journal reader-cache `variantNext172()` through
`variantNext176()` production entry points into stable descriptive methods on
`SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan`:

- `variantAttachedDatabaseScope()`
- `variantFreshMasterMembershipDigest()`
- `variantRollbackJournalSourceRebase()`
- `variantRollbackJournalChecksumFence()`
- `variantMasterJournalSourceRolloverFence()`

The five direct pager tests and WordPress examples were renamed to match those
stable helper names and migrated to call the descriptive methods. Existing
result status keys, dependency strings, operation labels, and non-overlap text
remain unchanged so accepted evidence consumers keep the same observable
metadata.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l` for the five renamed pager tests and five renamed examples
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheAttachedDatabaseScopeTest.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheFreshMasterMembershipDigestTest.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheRollbackJournalSourceRebaseTest.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheRollbackJournalChecksumFenceTest.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheMasterJournalSourceRolloverFenceTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCache*Test.php`
- five renamed WordPress examples with `--self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this is a naming
consolidation over the existing pager reader-cache implementation.
