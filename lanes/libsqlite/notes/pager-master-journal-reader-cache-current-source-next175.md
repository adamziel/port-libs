# Pager master-journal reader-cache current-source next175

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next175`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext175Plan`. It extends the accepted reader-cache current-source fences without repeating next170 rollback-journal source-digest checks or next173 master-membership checks: after current master-journal membership is established, each rollback-journal page checksum is verified against the current journal nonce before the pager can retain, refresh, or read from reader-cache entries. Pages whose current rollback-journal image has a checksum mismatch are quarantined, their reader-cache entries are invalidated, next reads block on checksum recovery, and next writes are refused until a verified before-image exists.

Application smoke: `application-pager-master-journal-reader-cache-current-source-next175.php` covers copied `wp_options` recovery where `active_plugins` can be refreshed and rewritten from a checksum-verified rollback-journal page, while a corrupt `plugin settings` journal page is quarantined before the next read/write can treat it as current.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext175Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext175Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next175.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext175Test.php`
  - `1 test files, 90 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next175.php`
  - `application-pager-master-journal-reader-cache-current-source-next175 self-test passed`

Expected dashboard delta: `phpPass` moves from `81770` to `81860` from 90 newly passing focused assertions. Mapped upstream coverage remains `613 / 1589`; this is focused pager behavior over existing rollback-journal/master-journal inventory rather than a fresh manifest row.

Non-overlap: avoids accepted next170 rollback-journal source digest/page-set fences, next172 attached database reader-cache scoping, next173 master-membership digest/ticket fences, next166 generation/schema/page-count fences, hot-cache/cache-spill/savepoint pager slices, rollback-journal apply/commit/super-journal, WAL checkpoint/savepoint/restart/truncate visibility, VFS writer/sync/lock clusters, and B-tree/JSON/SELECT/encoding surfaces. The new behavior is specifically checksum admission for current rollback-journal pages before reader-cache reuse after master-journal recovery.

Dependency closure: no new support component is needed. The slice reuses lane-local rollback-journal checksum parsing and pager reader-cache current-source primitives.

Next task: wire checksum-quarantine decisions into the eventual native pager recovery/open path that owns reader-cache entries directly.
