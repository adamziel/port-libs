# pager-master-journal-reader-cache-current-source-next209

## Behavior

Adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan`, a pager
reader-cache current-source fence layered above next206. After master-journal
recovery, cached reader pages may only be reused when the raw master-journal
bytes digest matches the current VFS read. This catches a same-member,
same-file-token case where the parsed member list and sidecar token still
match, but the current master-journal bytes were reread from a different
source representation before the next reader.

## Evidence

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
  - no syntax errors
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext209Test.php`
  - no syntax errors
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next209.php`
  - no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext209Test.php`
  - `1 test files, 54 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next209.php --self-test`
  - `application-pager-master-journal-reader-cache-current-source-next209 self-test passed`

Expected dashboard delta: `phpPass` moves from `100087` to `100141` from 54
new focused PASS/assertion lines. Mapped upstream coverage remains
`621 / 1589`; this is focused pager reader-cache behavior over existing
master-journal inventory rather than a new manifest-backed upstream row.

## Non-Overlap

This avoids accepted next206 master-journal file-token fencing, next203 member
order fencing, next196 member header fencing, next192 member token fencing,
next191 delete/directory-sync fencing, accepted master-journal hot-cache and
cache recovery work, rollback-journal apply/commit, super-journal commit, VFS
writer/sync/lock clusters, WAL checkpoint/savepoint/restart/truncate
visibility, and unrelated B-tree, JSON, SELECT, PRAGMA, trigger, planner, and
encoding surfaces. The new behavior is only the raw master-journal byte digest
admission key before reader-cache reuse after current-source recovery.

## Dependency Closure

No new support component is needed. The slice reuses lane-local pager
master-journal reader-cache current-source primitives and adds bounded native
PHP raw-byte-digest admission metadata.
