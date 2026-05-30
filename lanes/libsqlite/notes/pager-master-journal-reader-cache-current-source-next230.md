# Pager Master-Journal Reader Cache Current Source Next230

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next230`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext230Plan`. It layers on the accepted next226 page-1 change-counter/version-valid-for fence and adds the SQLite version-number header stamp at offset 96 as a reader-cache current-source admission key after master-journal recovery.

SQLite readers should not reuse recovered current-source cache entries when the cache entry or read ticket was produced under an older page-1 SQLite version-number stamp, even when recovered page bytes, master-journal membership, database token, header digest, page count, and change-counter/version-valid-for coherence already pass.

Application smoke: `application-pager-master-journal-reader-cache-current-source-next230.php` covers a copied `wp_options` recovery where the `active_plugins` page bytes match the recovered source but the read ticket predates the writer stamp. The next read misses cache and reopens against the current master-journal recovered source.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext230Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext230Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next230.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext230Test.php`
  - `1 test files, 64 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next230.php --self-test`

Expected dashboard delta: `phpPass` moves from `112201` to `112265` from 64 newly passing focused PASS lines. Mapped upstream coverage remains `631 / 1589`; this is focused pager reader-cache current-source behavior over the existing master-journal inventory rather than a new upstream row.

Non-overlap: avoids accepted next226 change-counter/version-valid-for pair coherence, next219 page-count invalidation, next217 database-header digest admission, next218 cleanup-token fencing, raw master-journal bytes, member token/header/order fences, rollback-journal apply, WAL, VFS writer, super-journal commit, and current-source next225/next226 pager reader-cache surfaces. The new behavior is specifically page-1 SQLite version-number stamp fencing before reader-cache reuse.

Dependency closure: no new support component is needed. The slice reuses existing bounded pager/master-journal reader-cache plans and adds a native PHP metadata fence only.

Next task: wire these current-source metadata fences into the native pager open/read path once the lane owns direct reader-cache entries rather than bounded plan arrays.
