# Pager cache-spill savepoint master current-source next141

Status: focused PHP behavior growth for `pager-cache-spill-savepoint-master-current-source-next141`.

This slice adds `SQLitePagerCacheSpillSavepointMasterCurrentSourceNextPlan`.
It models the pager boundary where cache-spill candidates are considered while
a savepoint is open after current master-journal recovery. Dirty cache pages are
eligible to spill only when their source id and epoch still match the recovered
current master-journal source; stale source pages, pinned pages, clean pages,
and old-epoch pages are rejected before the existing savepoint current-source
spill planner sees them.

WordPress smoke: `wordpress-pager-cache-spill-savepoint-master-current-source-next141.php`
covers a copied `wp_options` import where recovered `active_plugins` and plugin
setting pages may spill to WAL under the savepoint, while a stale transient
cache page from an older master-journal source is rejected.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerCacheSpillSavepointMasterCurrentSourceNext141Test.php`
  - `1 test files, 71 assertions, 0 failures`

Expected dashboard delta: `phpPass` moves from `60841` to `60912` from 71 newly
passing focused PASS lines. Mapped upstream coverage remains `606 / 1589`; this
is focused pager/master-journal/savepoint behavior over existing mapped pager
inventory rather than a fresh upstream denominator row.

Non-overlap: this avoids accepted cache-spill journal-mode next107,
master-journal cache-spill savepoint next114, stale master-journal cache
recovery next122, master savepoint cache next138, pager hot-cache next136, VFS
savepoint rollback/write/sync/lock clusters, rollback-journal commit/apply,
super-journal commit, WAL checkpoint/hot-journal/truncate clusters, B-tree,
JSON, SQL executor, and encoding surfaces. The new surface is specifically the
master-journal source id/epoch filter before a savepoint-protected cache spill.

Dependency closure: no new support component is needed. The slice composes
lane-local native PHP master-journal hot-cache rebasing, savepoint before-image
tracking, and cache-spill journal-mode planning.
