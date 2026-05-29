# Pager master-journal reader-cache current-source next251

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next251`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan`. It layers a reader-snapshot fence on top of the accepted next247 pager reader-cache generation fence after master-journal current-source recovery. Cache pages are reusable only when their active reader snapshot token matches the recovered current source; stale snapshots reopen before the next read, while stale generation/provenance rows keep their inherited rejection reasons.

WordPress smoke: `wordpress-pager-master-journal-reader-cache-current-source-next251.php` models a copied WordPress database where schema cache pages survive master-journal recovery, but stale `wp_options` and `active_plugins` reader snapshots reopen before import continues.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext251Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next251.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext251Test.php`
  - Result: `1 test files, 62 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next251.php --self-test`
  - Result: `wordpress-pager-master-journal-reader-cache-current-source-next251 self-test passed`

Expected dashboard delta: `phpPass +62` from focused lane-local PASS lines (`128615 -> 128677`). Mapped upstream coverage remains `657 / 1589`; this is current-source PHP behavior over existing pager master-journal/reader-cache inventory rather than a fresh manifest-backed upstream row.

Non-overlap: avoids accepted next247 pager-generation, next246 version-vector, next243 provenance, next240 statement-root, schema/read-transaction token fences, rollback-journal apply/commit, WAL checkpoint/savepoint/restart/truncate visibility, VFS writer/sync/lock, B-tree, JSON, SQL executor, encoding, and suite evidence clusters. The narrower behavior is active reader-snapshot admission after pager-generation admission has already passed.

Dependency closure: no new support component is needed. The slice reuses lane-local pager master-journal recovery, reader-cache generation, current-source provenance, and read-ticket primitives.
