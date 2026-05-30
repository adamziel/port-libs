# Pager WAL savepoint master journal current source next126

Status: focused PHP behavior growth for `pager-wal-savepoint-master-journal-current-source-next126`.

This slice adds `SQLitePagerWalSavepointMasterJournalCurrentSourceNextPlan`. It models the pager edge where a copied Application database has stale cached master-journal membership, current VFS master-journal bytes name the database rollback journal, and WAL savepoint replay must reject the cached source before trusting hot-journal recovery, WAL prefix truncation, and the next checkpoint image.

Application smoke: `application-pager-wal-savepoint-master-journal-current-source-next126.php` covers a copied `wp_options` plugin import where stale master-journal cache state is discarded and the current master source allows the retained WAL prefix to checkpoint clean `active_plugins` content while discarded plugin activation frames stay out of the current source.

Verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerWalSavepointMasterJournalCurrentSourceNext126Test.php
php -l lanes/libsqlite/src/SQLitePagerWalSavepointMasterJournalCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLitePagerWalSavepointMasterJournalCurrentSourceNext126Test.php
php -l lanes/libsqlite/examples/application-pager-wal-savepoint-master-journal-current-source-next126.php
php lanes/libsqlite/examples/application-pager-wal-savepoint-master-journal-current-source-next126.php --self-test
git diff --check -- lanes/libsqlite
```

Dashboard delta: `phpPass` should move by the focused PASS-line count from the new test. Mapped upstream coverage is unchanged because this adds focused pager/WAL behavior over already mapped journal/savepoint primitives rather than a fresh manifest-backed inventory unit.

Non-overlap: this avoids accepted pager statement-journal savepoint master current-source next123, pager master-journal cache recovery next122, WAL savepoint master-journal current-source next82, WAL checkpoint/restart/truncate reader/savepoint batches, WAL byte truncation, VFS savepoint rollback, rollback-journal apply/commit, super-journal commit, B-tree overflow/freelist/page-move/root-collapse clusters, JSON table source/cursor/constraint clusters, SELECT SQL text/group/order/subquery clusters, and Unicode GLOB behavior. The new surface is rejecting stale cached master-journal membership before WAL savepoint replay and checkpoint state are planned from the current VFS master-journal source.

Dependency closure: no new support component is needed. The slice reuses lane-local rollback-journal, WAL, savepoint, and master-journal current-source primitives.

Next task: continue with broader pager/VFS transaction application or another distinct WAL durability edge; avoid another master-journal wrapper unless it applies a different current-source transition.
