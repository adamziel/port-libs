# Pager savepoint master-journal reader current-source next146

Status: focused PHP behavior growth for `pager-savepoint-master-journal-reader-current-source-next146`.

This slice adds `SQLitePagerSavepointMasterJournalReaderCurrentSourceNextPlan`. It models the pager boundary where hot master-journal recovery establishes a recovered current source, `ROLLBACK TO` restores savepoint before-images on top of that source, and readers pinned to either the pre-recovery source or the pre-rollback source must reopen before the next read/write can proceed. Fresh readers admitted after reopen inherit the savepoint current-source token, and the next write journals the savepoint-restored before-image rather than stale or merely hot-recovered bytes.

Application smoke: `application-pager-savepoint-master-journal-reader-current-source-next146.php` covers copied `wp_options` plugin import recovery where a crashed reader predates master-journal recovery, a reopened reader sees the savepoint-restored plugin option page, and the next plugin option write captures the savepoint current-source before-image.

Focused verification:

- `php -l lanes/libsqlite/src/SQLitePagerSavepointMasterJournalReaderCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerSavepointMasterJournalReaderCurrentSourceNext146Test.php`
- `php -l lanes/libsqlite/examples/application-pager-savepoint-master-journal-reader-current-source-next146.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerSavepointMasterJournalReaderCurrentSourceNext146Test.php`
- `php lanes/libsqlite/examples/application-pager-savepoint-master-journal-reader-current-source-next146.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard delta: `phpPass` moves from `64226` to `64310` from 84 newly passing focused PASS lines. Mapped upstream coverage remains `606 / 1589`; this is focused pager behavior over already mapped master-journal/savepoint primitives rather than a fresh upstream denominator row.

Non-overlap: this avoids accepted pager master-journal hot cache next136, pager master-journal savepoint cache next138, pager cache-spill savepoint master next141, WAL checkpoint hot-journal truncate next138, rollback-journal commit/apply, super-journal commit, VFS writer/sync/lock/savepoint rollback clusters, WAL byte truncation, B-tree, JSON, SQL executor, and encoding clusters. The new surface is reader current-source admission after `ROLLBACK TO` when master-journal recovery changed the source before the next pager read/write.

Dependency closure: no new support component is needed. The slice reuses lane-local pager master-journal recovery, savepoint rollback, and current-source reader admission primitives.
