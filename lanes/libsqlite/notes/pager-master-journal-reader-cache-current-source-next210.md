# Pager Master Journal Reader Cache Current Source Next210

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next210`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext210Plan`. It layers a VFS master-journal read-source token above the accepted member-token, member-header, member-order, file-token, and raw-byte digest fences. A reader cache page can have matching recovered bytes and matching master-journal bytes, but still be rejected when its cache ticket was built from an older VFS read source for the master-journal sidecar.

Application smoke: `application-pager-master-journal-reader-cache-current-source-next210.php` models copied `wp_options` recovery where the schema page keeps the current read-source token, while the `wp_options` root page must reopen because it was cached from an older master-journal xRead source even though the raw bytes and file token match.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext210Plan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext210Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext210Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext210Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next210.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next210.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext210Test.php`
  - `1 test files, 65 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next210.php --self-test`
  - `application-pager-master-journal-reader-cache-current-source-next210 self-test passed`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Expected dashboard delta: `phpPass` moves from `102317` to `102382` from 65 newly passing focused PASS lines. Mapped upstream coverage remains `622 / 1589`; this is additional focused pager reader-cache current-source behavior over the existing master-journal inventory rather than a fresh manifest-backed upstream row.

Non-overlap: avoids accepted next209 raw master-journal byte digest fencing, next206 file-token fencing, next203 ordered-member fencing, next196 member-header fencing, next192 member-token fencing, next191 delete/directory-sync fencing, accepted rollback-journal apply/commit, super-journal commits, WAL checkpoint/savepoint/restart/truncate visibility, VFS writer/sync/lock clusters, and unrelated B-tree, JSON, SELECT, PRAGMA, trigger, planner, and encoding surfaces. The new behavior is only the VFS read-source token for the current master-journal sidecar before reader-cache reuse.

Dependency closure: no new support component is needed. The patch reuses lane-local pager master-journal reader-cache current-source primitives and adds bounded native PHP metadata for the master-journal VFS read-source token.

Next task: wire the read-source token into a broader native pager/VFS recovery executor once reader cache entries are owned by the file-handle layer directly.
