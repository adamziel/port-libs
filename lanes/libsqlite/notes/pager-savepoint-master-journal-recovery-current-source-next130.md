# Pager savepoint master-journal recovery current-source next130

Status: focused PHP behavior growth for `pager-savepoint-master-journal-recovery-current-source-next130`.

This slice adds `SQLitePagerSavepointMasterJournalRecoveryCurrentSourceNextPlan`. It models an already-open savepoint after a crash where pager recovery must discard stale cached master-journal membership, re-read the current master-journal member list, restore current pages for all current member databases, capture savepoint before-images from that recovered source, and make `ROLLBACK TO` feed the next retry write from the recovered current source rather than dirty savepoint pages.

WordPress smoke: `wordpress-pager-savepoint-master-journal-recovery-current-source-next130.php` covers a copied `wp_options` plugin import touching a main database and an attached stats/audit database. It proves a stale detached cached master-journal member is ignored, the current attached journal is recovered, failed savepoint writes roll back to recovered pages, and retry writes are based on that rollback source.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerSavepointMasterJournalRecoveryCurrentSourceNext130Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 93 assertions, 0 failures
```

```text
php -l lanes/libsqlite/src/SQLitePagerSavepointMasterJournalRecoveryCurrentSourceNextPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLitePagerSavepointMasterJournalRecoveryCurrentSourceNextPlan.php

php -l lanes/libsqlite/tests/SQLitePagerSavepointMasterJournalRecoveryCurrentSourceNext130Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLitePagerSavepointMasterJournalRecoveryCurrentSourceNext130Test.php

php -l lanes/libsqlite/examples/wordpress-pager-savepoint-master-journal-recovery-current-source-next130.php
No syntax errors detected in lanes/libsqlite/examples/wordpress-pager-savepoint-master-journal-recovery-current-source-next130.php
```

```text
php lanes/libsqlite/examples/wordpress-pager-savepoint-master-journal-recovery-current-source-next130.php --self-test
wordpress-pager-savepoint-master-journal-recovery-current-source-next130 self-test passed
```

Dashboard delta: `phpPass` moves from `54071` to `54164` from 93 newly passing focused PASS lines. Mapped upstream coverage remains `606 / 1589`; this is fresh focused PHP pager behavior over already mapped master-journal/savepoint primitives rather than a new manifest-backed upstream inventory unit.

Non-overlap: this avoids accepted pager master-journal savepoint current-source next108, master-journal cache recovery next122, statement-journal master current-source next123, pager savepoint hot-journal cache next128, WAL checkpoint/savepoint and byte-truncation clusters, VFS savepoint rollback/rollback-journal/super-journal/sync/lock/write clusters, B-tree overflow/freelist/page-move clusters, JSON table source/cursor/constraint clusters, SELECT SQL text/group/order/subquery clusters, and Unicode GLOB behavior. The new surface is `ROLLBACK TO` and retry capture after current master-journal recovery across multiple current member databases while rejecting stale cached master-journal membership.

Dependency closure: no new support component is needed. The slice reuses lane-local pager page-image modeling and bounded master-journal/savepoint current-source primitives.

Next task: continue with broader pager/VFS transaction application or a distinct WAL/pager durability edge; avoid another savepoint wrapper unless it applies a different pager state transition.
