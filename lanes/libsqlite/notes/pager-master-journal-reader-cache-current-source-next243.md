# Pager master-journal reader-cache current-source next243

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next243`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan`. It models the pager reader-cache fence where pages that pass master-journal membership, cleanup, read-transaction, schema-reparse, and statement schema-root checks still must prove they belong to the current recovered source provenance before reuse. Stale cache rows or reader tickets reopen instead of serving a page from the pre-recovery source.

Application smoke: `application-pager-master-journal-reader-cache-current-source-next243.php` covers copied `wp_options` import behavior where schema/options pages remain cached after master-journal recovery, while a stale `active_plugins` read reopens before plugin import resumes.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext243Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next243.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext243Test.php`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next243.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard delta: `phpPass` moves from `122940` to `123011` from 71 newly passing focused PASS lines. Mapped upstream coverage remains `647 / 1589`; this is focused pager/master-journal current-source behavior over existing pager inventory rather than a fresh upstream denominator row.

Non-overlap: avoids accepted pager master-journal reader-cache next240 statement-root, next236 schema-reparse, next233 read-transaction, earlier master-journal bytes/token/member fences, rollback-journal apply/commit/super-journal, WAL checkpoint/savepoint/restart/truncate visibility, VFS writer/sync/lock clusters, B-tree, JSON, SELECT, and encoding surfaces. The new behavior is specifically the current-source provenance fence after all prior reader-cache tokens have passed.

Dependency closure: no new support component is needed. The slice reuses lane-local pager master-journal reader-cache, read-transaction, schema-reparse, and statement schema-root primitives.
