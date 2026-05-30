# WAL Restart Truncate Reader Current Source Next86

Status: focused PHP behavior growth for WAL restart/truncate reader current-source admission.

This slice adds `SQLiteWal::restartTruncateReaderCurrentSourceNext()`. It validates that raw WAL bytes still match the parsed current WAL header salt, checkpoint sequence, page size, and frame count before applying a drained-reader `RESTART` or `TRUNCATE` checkpoint boundary. Safe current-source bytes preserve current-reader page visibility, then expose the checkpointed database image plus restarted header or empty WAL to next readers. Stale salt, stale checkpoint sequence, shortened frame counts, stale parsed WAL objects, invalid modes, and invalid reader/page inputs are rejected before any reset/truncate decision is trusted.

Focused evidence:

```bash
php -l lanes/libsqlite/src/SQLiteWal.php
php -l lanes/libsqlite/tests/SQLiteWalRestartTruncateReaderCurrentSourceNext86Test.php
php -l lanes/libsqlite/examples/application-wal-restart-truncate-current-source-next86.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalRestartTruncateReaderCurrentSourceNext86Test.php
php lanes/libsqlite/examples/application-wal-restart-truncate-current-source-next86.php --self-test
git diff --check -- lanes/libsqlite
```

Focused result: `1 test files, 66 assertions, 0 failures`.

Dashboard delta: `phpPass` moves from `32160` to `32226` for the 66 verified PASS lines. Mapped upstream coverage remains unchanged because this extends the existing WAL restart/truncate reader inventory with a current-source admission guard rather than claiming a new upstream denominator row.

Application smoke: `application-wal-restart-truncate-current-source-next86.php --self-test` reports copied Application database readers where stale WAL bytes are rejected before reset/truncate decisions, a current reader can retain an older WAL snapshot, and next readers resolve checkpointed `wp_options` pages from a restarted or truncated WAL boundary without ext/sqlite.

Non-overlap: avoids accepted WAL reader-pin restart/truncate handoff, savepoint byte truncation, savepoint checkpoint current-source admission, checksum/salt recovery, WAL checkpoint transaction planning, VFS file-writer/sync/rollback/lock clusters, rollback-journal and super-journal application, B-tree freeblock/freelist/page-move clusters, JSON table source/cursor/constraint clusters, SQL SELECT text/subquery/GROUP/ORDER/LIMIT clusters, and encoding Unicode GLOB work. The new surface is current-source validation for non-savepoint drained-reader restart/truncate boundaries.

Dependency closure: no new support component is needed. The patch reuses lane-local WAL parsing, checksum validation, durable checkpoint result generation, and reader snapshot visibility primitives.

Next task: continue with broader WAL/pager/VFS transaction application or another distinct WAL recovery edge; avoid another reader wrapper unless it applies a new durability rule.
