# real-upstream-corpus-pager-wal-dynamic-20260601T071236Z-0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_wal.test`

Upstream sections:

- `e_wal.test` `4.2.3`: `PRAGMA journal_mode = delete` deliberately changes out of WAL mode.
- `e_wal.test` `4.2.4`: after leaving WAL mode, `test.db-wal` no longer exists.
- `e_wal.test` `4.3`: database header bytes 18 and 19 revert to `0101` so older SQLite versions can access the database file again.

Implementation:

- Added `SQLiteWalExclusiveModePlan::journalModeExitRows()` with 1000 deterministic dynamic rows for WAL exit header reversion, WAL sidecar deletion, checkpoint-before-unlink accounting, page-size variation, and legacy-reader accessibility after returning to rollback journal mode.
- Added `SQLiteRealUpstreamCorpusPagerWalExitHeaderReversionDynamic20260601Test.php` with 1000 focused dynamic PASS cases plus hydrated-source, non-overlap/dependency, and malformed-count guards.

Non-overlap:

- This targets the missing exit-WAL half of `e_wal.test` section 4, specifically `4.2.3`, `4.2.4`, and `4.3`.
- It avoids the existing `SQLiteWalExclusiveModePlan::access()` dynamic batch for `e_wal.test` old-VFS exclusive access, sticky exclusive locking, SHM creation, and WAL header entry through `4.2.1`.
- It also avoids accepted checkpoint, WAL byte truncation, VFS writer/sync/lock-state, rollback-journal apply/commit, walpersist, walrestart, walvfs, walprotocol, and application-WAL slices.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalExitHeaderReversionDynamic20260601Test.php`: `1 test files, 34013 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalExclusiveModeDynamicTest.php`: `1 test files, 15012 assertions, 0 failures`.
- Focused PASS-line delta: `+1003` (`1000` dynamic rows plus `3` source/non-overlap/malformed guards).
- Dependency closure: no new support component needed; this reuses generic WAL journal-mode state and database-header byte modeling against hydrated upstream `e_wal.test`.
