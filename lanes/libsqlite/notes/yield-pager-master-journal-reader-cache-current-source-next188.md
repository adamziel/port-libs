# Pager Master-Journal Reader Cache Current-Source Next188

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next188`.

Behavior: adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext188Plan`, which parses sector-padded master-journal bytes that contain NUL-separated member names, deduplicates and canonicalizes the member set, and uses that token as a reader-cache admission fence before the next source reads. Clean cache pages whose member token still matches are retained or refreshed from current source bytes; dirty pages, stale member-token/digest entries, stale source/epoch entries, and pinned stale images are forced to reopen.

Application smoke: `application-pager-master-journal-reader-cache-current-source-next188.php --self-test` models copied `wp_options` import recovery where the alloptions root can refresh across a NUL-padded master-journal read, while plugin settings and rewrite-rule cache entries are reopened because their member token or dirty state cannot cross the current-source boundary.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext188Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext188Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next188.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext188Test.php`
  - `1 test files, 77 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next188.php --self-test`
  - `application-pager-master-journal-reader-cache-current-source-next188 self-test passed`

Expected dashboard delta: `phpPass` moves from `89524` to `89601` from 77 newly passing focused assertions. Mapped upstream coverage remains `616 / 1589`; this is focused pager master-journal reader-cache behavior over existing pager inventory, not a newly mapped manifest unit.

Non-overlap: avoids next185 finite rollback-journal original-size truncation, next184 file generation/read tokens, next181 pending membership behavior, rollback-journal apply/commit/super-journal paths, WAL hot-journal/checkpoint/savepoint reader work, VFS writer/sync/lock clusters, and non-pager SQL/JSON/B-tree/encoding surfaces. This slice is specifically the NUL-separated, sector-padded master-journal member parser and reader-cache token fence.

Dependency closure: no new support component is needed. The slice reuses lane-local pager reader-cache current-source primitives and adds only bounded native PHP parsing/canonicalization for master-journal member bytes.
