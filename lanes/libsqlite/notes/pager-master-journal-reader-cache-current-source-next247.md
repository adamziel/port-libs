# Pager master-journal reader-cache current-source next247

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next247`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext247Plan`. It builds on the accepted next243 current-source provenance fence and adds a pager reader-cache generation fence: pages that pass master-journal membership, cleanup, read-transaction, schema-reparse, statement schema-root, and current-source provenance checks still reopen if their pager cache generation predates the recovered master-journal source. This prevents a reader from serving a cache page retained across a pager cache reset/reopen after recovery.

WordPress smoke: `wordpress-pager-master-journal-reader-cache-current-source-next247.php` covers copied `wp_options` import behavior where the schema page remains cached, the options root page reopens because it was from the previous pager generation, and `active_plugins` reopens because its current-source provenance is stale.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext247Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext247Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next247.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext247Test.php`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next247.php`
- `git diff --check -- lanes/libsqlite`

Focused result: `1 test files, 96 assertions, 0 failures`.

Expected dashboard delta: `phpPass` moves from `125265` to `125361` from 96 newly passing focused PASS lines. Mapped upstream coverage remains `650 / 1589`; this is focused pager/master-journal current-source behavior over existing pager inventory rather than a fresh upstream denominator row.

Non-overlap: avoids accepted next243 current-source provenance, next240 statement-root, next236 schema-reparse, next233 read-transaction, earlier master-journal byte/token/member fences, rollback-journal apply/commit/super-journal, WAL checkpoint/savepoint/restart/truncate visibility, VFS writer/sync/lock clusters, B-tree, JSON, SELECT, and encoding surfaces. The new behavior is specifically the pager reader-cache generation fence after current-source provenance has already passed.

Dependency closure: no new support component is needed. The slice reuses lane-local pager master-journal reader-cache, current-source provenance, statement schema-root, schema-reparse, and read-transaction primitives.

Next task: wire this generation token into broader pager/VFS transaction application only where a real recovered cache source transition exists; avoid another reader-cache wrapper unless it protects a distinct source transition.
