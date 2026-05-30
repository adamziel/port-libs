# Pager hot-journal WAL savepoint current-source next124

Status: focused PHP behavior growth for the pager edge where hot rollback-journal recovery must establish the current database image and committed WAL prefix before an open WAL savepoint is rolled back.

This slice adds `SQLitePagerHotJournalWalSavepointCurrentSourceNextPlan`. It verifies the parsed rollback journal and WAL match the current source bytes, restores the hot-journal database image, trims WAL recovery to the committed prefix, then performs savepoint WAL byte truncation against that recovered prefix. The current reader keeps retained WAL frames and falls back to hot-recovered database pages for savepoint-discarded frames.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerHotJournalWalSavepointCurrentSourceNext124Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 75 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-pager-hot-journal-wal-savepoint-current-source-next124.php --self-test
application-pager-hot-journal-wal-savepoint-current-source-next124 self-test passed
```

Dashboard delta: `phpPass` increases by the verified 75 focused PASS lines, from `49426` to `49501`. Mapped upstream coverage remains conservative at `606 / 1589`; this is behavior-backed focused PHP coverage over the existing pager/WAL savepoint inventory rather than a new manifest-denominator row.

Non-overlap: avoids accepted pager master-journal cache recovery next122, WAL hot-journal checkpoint reader next122, WAL savepoint reader/checkpoint/restart slices, WAL byte truncation, WAL checkpoint transactions, rollback-journal commit/apply, VFS savepoint rollback, VFS writer/sync/lock clusters, B-tree freeblock/freelist/page-move/overflow clusters, JSON table source/cursor/constraint clusters, SELECT SQL group/order/subquery clusters, and Unicode GLOB work. The new surface is the no-checkpoint current-source handoff from hot journal recovery into WAL savepoint rollback.

Dependency closure: no new support component is needed. The patch reuses native PHP rollback-journal hot recovery, WAL committed-prefix recovery, and savepoint WAL byte-truncation primitives.

Next task: continue with broader pager/VFS transaction application or a distinct WAL durability edge; avoid another savepoint wrapper unless it applies a new source transition.
