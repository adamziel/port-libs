## Pager Master-Journal Reader Cache Current Source Next255

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext255Plan`.
It layers a reader page-map digest fence after the accepted next251 reader
snapshot admission. A reader-cache page can be reused after master-journal
recovery only when its page-map digest was computed from the recovered current
source; stale page-map metadata reopens the reader even when provenance,
generation, and snapshot tokens still look current.

Application smoke: `application-pager-master-journal-reader-cache-current-source-next255.php`
models a copied `wp_options` database where a schema page remains reusable, a
stale options-root page-map digest reopens the options reader, and an
`active_plugins` reader still inherits the older snapshot fence.

Focused verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext255Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext255Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next255.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext255Test.php`
  - `1 test files, 62 assertions, 0 failures`

Status delta: `lane-status.json` `phpPass` moves from `133054` to `133116`
for the 62 focused PASS assertions. Mapped upstream coverage is unchanged.

Dependency closure: no new support component is needed; this reuses the
lane-local pager master-journal reader-cache family and existing current-source
snapshot/generation/provenance fences.

Non-overlap: this does not repeat next251 reader snapshot admission, next247
generation, next243 provenance, rollback-journal apply/commit, super-journal,
WAL checkpoint/savepoint, VFS writer/sync/lock, B-tree, JSON, SQL executor, or
encoding behavior. The new behavior is only page-map digest admission after a
reader snapshot has already passed.
