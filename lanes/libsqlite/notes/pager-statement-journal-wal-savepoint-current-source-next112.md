# Pager Statement Journal WAL Savepoint Current Source Next112

This slice adds `SQLitePagerStatementJournalWalSavepointCurrentSourceNextPlan` for the pager edge where a failed statement writes WAL frames inside an active savepoint, then rolls back through its statement journal before the retry statement appends new WAL frames.

The planner keeps the outer savepoint WAL frames retained, discards failed statement frames after the retained savepoint prefix, restores statement before-images into the retry current source, and verifies that retry frames restart after the retained savepoint frame rather than building on stale failed statement pages. The Application smoke models a copied `wp_options` plugin import retry.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerStatementJournalWalSavepointCurrentSourceNextPlan.php && php -l lanes/libsqlite/tests/SQLitePagerStatementJournalWalSavepointCurrentSourceNext112Test.php && php -l lanes/libsqlite/examples/application-pager-statement-journal-wal-savepoint-current-source-next112.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerStatementJournalWalSavepointCurrentSourceNext112Test.php`
  - `1 test files, 102 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-statement-journal-wal-savepoint-current-source-next112.php`
  - status `pager_statement_journal_wal_savepoint_current_source_next112`
  - WAL truncate frame `12`, discarded failed statement frames `[13, 14]`, retry frames `[13, 14, 15, 16]`

Focused PASS delta: `+102` PASS lines from `SQLitePagerStatementJournalWalSavepointCurrentSourceNext112Test.php`. `lane-status.json` `phpPass` moves from `43574` to `43676`. Mapped upstream coverage is unchanged because this is a focused pager/WAL behavior slice over existing mapped pager/savepoint inventory rather than a new manifest-backed upstream unit.

Non-overlap: avoids accepted batch106-108 pager hot-journal statement cache recovery, master-journal savepoint current-source handling, WAL checksum/salt recovery, WAL checkpoint/restart/truncate reader visibility, VFS savepoint rollback apply, WAL byte truncation, and rollback-journal commit/super-journal paths. This patch is specifically statement-journal rollback plus WAL retry frame current-source composition.

Dependency closure: no new support component is needed. The slice reuses lane-local pager/WAL byte-image modeling and does not require live SQLite, external VFS locks, or provider services. A future VFS application slice can consume this plan when wiring statement-journal/WAL retry state into durable file-handle writes.
