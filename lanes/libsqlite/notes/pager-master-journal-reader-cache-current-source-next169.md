# Pager master-journal reader cache current-source next169

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next169`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext169Plan`. It models a pager barrier for attached-database master-journal recovery: reader-cache rows are reusable only after every current master-journal member has been recovered, is no longer hot, and its rollback journal has been deleted. The cache row must also carry the current source id, epoch, member journal, member generation, and page digest. Dirty, pinned mismatched, stale-generation, stale-epoch, stale-source, unknown-member, or incomplete-member rows reopen against current-source bytes before the next Application reader observes them.

Application smoke: `application-pager-master-journal-reader-cache-current-source-next169.php` covers a copied multisite options import where the schema page is retained, but `active_plugins` and site-meta cache rows are reopened because their member image/generation predates the attached master-journal recovery.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext169Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext169Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next169.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext169Test.php`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next169.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard delta: `phpPass` moves from `76154` to `76236` from 82 newly passing focused assertions in this isolated worktree. Mapped upstream coverage remains `611 / 1589`; this is focused pager/master-journal reader-cache current-source behavior over existing pager inventory rather than a new upstream denominator row.

Non-overlap: this avoids accepted next159 through next168 reader-cache surfaces for cached membership, next-reader tickets, current-to-next source digests, generation/schema/page-count fences, master deletion generation, and source digest/generation fences. The new behavior is specifically attached master-journal member recovery completeness and per-member generation fencing before reader-cache reuse.

Dependency closure: no new support component is needed. The slice reuses lane-local master-journal member parsing, pager reader-cache source tokens, and bounded Application page-image fixtures.

Next task: connect this attached-member reader-cache fence to broader pager/VFS transaction application if a later slice needs durable file-handle reads after attached recovery; avoid another standalone cache wrapper unless it applies new recovery state or write behavior.
