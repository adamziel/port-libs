# pager-master-journal-reader-cache-current-source-next202

## Behavior

Adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext202Plan`, a pager
reader-cache current-source fence layered above next196. After master-journal
recovery, a reader-cache page may only be reused when every attached
rollback-journal member has the current playback/body digest. This catches the
case where member paths, file tokens, and rollback-journal headers still match
but the journal body/page playback belongs to a different recovered source.

## Evidence

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext202Plan.php`
  - no syntax errors
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext202Test.php`
  - no syntax errors
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next202.php`
  - no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext202Test.php`
  - `1 test files, 61 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next202.php`
  - `application-pager-master-journal-reader-cache-current-source-next202 self-test passed`

Expected dashboard delta: `phpPass` moves from `97068` to `97129` from 61
new focused PASS lines. Mapped upstream coverage remains `619 / 1589`; this is
focused pager behavior over existing master-journal reader-cache inventory
rather than a new manifest-backed upstream row.

## Non-Overlap

This avoids accepted next196 attached member rollback-journal header digest
fencing, next192 member token fencing, next191 master-journal delete/directory
sync fencing, next186 recovered-page-set sequencing, accepted rollback-journal
apply/commit, super-journal commit, VFS writer/sync/lock clusters, WAL
checkpoint/savepoint/restart/truncate visibility, and unrelated B-tree, JSON,
SELECT, PRAGMA, trigger, planner, and encoding surfaces. The new behavior is
only the attached member rollback-journal playback/body digest fence before
reader-cache reuse after master-journal recovery.

## Dependency Closure

No new support component is needed. The slice reuses lane-local pager
master-journal reader-cache current-source primitives and adds bounded native
PHP playback-digest admission metadata.
