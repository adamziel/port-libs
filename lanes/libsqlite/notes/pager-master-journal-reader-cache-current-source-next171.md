# Pager master-journal reader-cache current-source next171

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next171`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext171Plan`. It models the pager reader-cache boundary after master-journal recovery where the cache ticket must include the current master-journal digest, recovery sequence, and read-lock generation. Clean matching pages can be retained, stale clean pages can be refreshed from the current source, but dirty cache rows, stale recovery/read-lock tickets, stale master digests, source/generation mismatches, and pinned mismatched pages are forced to reopen before the next reader can serve data.

Application smoke: `application-pager-master-journal-reader-cache-current-source-next171.php` models a copied `wp_options` import where the schema page survives the recovery boundary, the options root page refreshes, and stale `active_plugins` / `rewrite_rules` readers opened before master-journal recovery are reopened against the current source.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext171Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext171Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next171.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext171Test.php`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next171.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard delta: `phpPass` moves from `77680` to `77786` from 106 newly passing focused PASS lines. Mapped upstream coverage remains `612 / 1589`; this is focused pager reader-cache current-source behavior over existing master-journal inventory rather than a fresh manifest row.

Non-overlap: avoids accepted pager master-journal reader-cache next162/next166/next167 generation, deleted-state, schema/page-count, and changed-page fences; master-journal hot-cache/cache-spill/savepoint slices; rollback-journal apply/commit/super-journal; WAL checkpoint/savepoint/restart/truncate visibility; VFS writer/sync/lock clusters; B-tree, JSON, SELECT, and encoding surfaces. The new behavior is specifically the recovery-sequence/read-lock-generation ticket that prevents pre-recovery shared readers from reusing cache rows after current master-journal recovery.

Dependency closure: no new support component is needed. The slice reuses lane-local pager master-journal membership and reader-cache current-source primitives.
