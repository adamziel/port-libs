# Pager master-journal reader-cache current-source next311-314

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next311-314`.

This slice extends the accepted next307-310 reader-cache current-source chain with four pager header/config fences: application id, user version, schema format, and incremental auto-vacuum state. After master-journal recovery, a reader-cache row and next read may only reuse the current source when those tokens match the recovered database source.

WordPress smoke: `wordpress-pager-master-journal-reader-cache-current-source-next314.php` models copied `wp_options` recovery where a stale reader ticket is forced open again when the application-id, user-version, schema-format, or incremental-auto-vacuum state predates the recovered current source.

Validation:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext314Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next314.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext314Test.php`
  - 54 assertions, 0 failures
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next314.php --self-test`
  - `wordpress-pager-master-journal-reader-cache-current-source-next314 self-test passed`
- `git diff --check`

Non-overlap: this follows next307-310 and only adds header/config reader-cache token admission for application id, user version, schema format, and incremental auto-vacuum state. It does not repeat earlier cache source, journal membership, schema object, transaction, PRAGMA, lock, WAL, VFS, B-tree, JSON, or SQL executor behavior.

Next slice: continue pager master-journal reader-cache current-source fences with the next unreconciled pager header/cache admission tokens after next314.
