# Pager Master-Journal Reader-Cache Current Source Next235

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next235`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext235Plan`. It extends the accepted next232 database path namespace fence without repeating it: after master-journal recovery, reader-cache rows and next-read tickets must also match the current SQLite database header change counter. This prevents a page image from the previous committed database generation from being reused after master-journal recovery when path/source/lease tickets otherwise look current.

Application smoke: `application-pager-master-journal-reader-cache-current-source-next235.php` models copied `wp_options` recovery where schema/options pages remain reusable when the recovered header change counter matches, while an image-identical `active_plugins` cache row from the previous counter reopens before plugin import continues.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext235Plan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext235Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext235Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext235Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next235.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next235.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext235Test.php`
  - `1 test files, 55 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next235.php`
  - `application-pager-master-journal-reader-cache-current-source-next235 self-test passed`
- `git diff --check -- lanes/libsqlite`
  - no output

Expected dashboard delta: `phpPass` moves from `116027` to `116082` from 55 newly passing focused PASS lines. Mapped upstream coverage remains `638 / 1589`; this is focused pager reader-cache current-source behavior over existing master-journal inventory rather than a fresh manifest-backed row.

Non-overlap: avoids accepted next232 database path namespace admission, next229 pager-cache-source admission, next224 reader-lease admission, next218 cleanup-token admission, database page-count fences, database header digest fences, raw master-journal bytes, member-token/header/order fences, VFS writer/sync/lock, rollback-journal apply/commit, WAL checkpoint/byte truncation, super-journal commit, B-tree, JSON, SELECT, PRAGMA, trigger, and encoding behavior. The new surface is only the database change-counter fence after database path/source tickets have already been observed as current.

Dependency closure: no new support component is needed. The behavior reuses existing lane-local pager master-journal reader-cache state, current-source tickets, cleanup-token evidence, reader-lease evidence, pager-cache-source evidence, database path namespace evidence, and SQLite header change-counter evidence.

Next task: wire this counter fence into the eventual native pager cache owner when broader pager transaction execution owns reader-cache generations directly.
