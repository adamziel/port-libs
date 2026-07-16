# Pager master-journal reader-cache current-source next257

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next257`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext257Plan`. It layers a recovered-page checksum receipt fence after accepted next254 master-journal recovery-receipt admission. Reader-cache pages that otherwise match the recovered current source are still reopened when their checksum receipt was computed against an older recovered page source, preventing stale cached `wp_options` pages from surviving an attached master-journal recovery boundary.

Application smoke: `application-pager-master-journal-reader-cache-current-source-next257.php` models copied `wp_options` import behavior where schema and `active_plugins` pages remain reusable with the current recovered-page checksum receipt, while a stale options-root receipt forces a reader reopen before plugin import continues.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext257Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext257Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next257.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext257Test.php`
  - `1 test files, 59 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next257.php --self-test`
  - `application-pager-master-journal-reader-cache-current-source-next257 self-test passed`
- `git diff --check -- lanes/libsqlite`

Expected dashboard delta: `phpPass` moves from `136435` to `136494` from 59 newly passing focused PASS lines. `benchmarkDenominator.mapped` remains `680 / 1589`; this is focused pager reader-cache current-source behavior over existing master-journal inventory rather than a fresh manifest row.

Non-overlap: avoids accepted next254 recovery-receipt tokens, next251 reader snapshots, next247 reader-cache generation, current-source provenance, master-journal byte/token/member fences, rollback-journal apply/commit, WAL checkpoint/savepoint, VFS writer/sync/lock, B-tree, JSON, SQL executor, and encoding behavior. The new behavior is specifically the recovered-page checksum receipt required before prepared reader-cache reuse after master-journal recovery.

Dependency closure: no new support component is needed. The slice reuses lane-local pager master-journal reader-cache and current-source token-fence primitives.

Next task: continue pager work only on a non-overlapping transaction application or durability edge; avoid another reader-cache token fence unless it applies a distinct SQLite current-source admission layer with focused evidence.
