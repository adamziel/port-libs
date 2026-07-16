# Pager master-journal reader-cache current-source next315-318

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next315-318`.

This slice extends the accepted pager master-journal reader-cache token fence through database-header and shared-schema reader state after next310. It adds next315 application-id, next316 user-version, next317 data-version, and next318 schema-lock admission checks before a recovered reader-cache entry can serve the next read.

Application path: `application-pager-master-journal-reader-cache-current-source-next318.php` models copied `wp_options`/`wp_usermeta` recovery where stale reader tickets reopen after master-journal recovery when their header or schema-lock observations predate the current source.

Validation:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext318Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next318.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext318Test.php`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next318.php --self-test`
- `git diff --check`

Non-overlap: this avoids upstream-suite countability rows, next311-314 suite evidence, earlier pager reader-cache source/recovery/snapshot/generation/pragma tokens, WAL, VFS, B-tree, JSON, SQL executor, trigger, and encoding behavior. The new behavior is only the next315-318 reader-cache current-source admission for database-header and shared-schema observations.

Dependency closure: no new support component needed; this composes the existing consolidated pager master-journal reader-cache plan and token-fence helper.
