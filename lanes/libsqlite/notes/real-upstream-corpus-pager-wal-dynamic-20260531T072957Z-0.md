# real-upstream-corpus-pager-wal-dynamic-20260531T072957Z-0

Session: `port-dev-sqlite-yield-dyn-real-pager-20260531T072957Z`
Base accepted HEAD: `49647c646cee956ed1d4c9609a0c5aac0efc4e84`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal5.test`
- Added focused dynamic coverage for `wal5.test` checkpoint reader/busy sections:
  `wal5-2.4.1`, `2.4.3`, `2.4.5`, `2.4.6`, `2.4.7`, `2.4.9`,
  `2.4.10`, `2.4.11`, `2.4.13`, `2.4.14`, `5.15`, `5.17`,
  `5.18`, and `5.20`.

Behavior ported:

- Adds 1,000 distinct TestRunner cases that build real WAL byte streams and
  exercise `SQLiteWal::checkpointModeResult()` plus
  `SQLiteWal::durableCheckpointResult()`.
- Each row verifies checkpoint mode normalization, reader-end-frame blocking,
  checkpointed frame counts, reset/truncate decisions, durable WAL sidecar
  action, database byte size, and dependency tags.
- The intentionally excluded `wal5-2.4.2`, `2.4.4`, `2.4.8`,
  `2.4.12`, `5.6`, `5.8`, `5.9`, and `5.11` rows require multi-client
  busy-handler state transitions that are not directly represented by the
  existing `SQLiteWal` byte model; they remain follow-up candidates for a
  broader pager/client scheduler model.

Focused verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWal5CheckpointBusyDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWal5CheckpointBusyDynamicTest.php`
  - Result: `1 test files, 20008 assertions, 0 failures`
  - Focused PASS growth: `+1002` TestRunner cases from real upstream
    `wal5.test`.

Non-overlap:

- Avoids prior `walckptnoop`, `wal2` validation/fullfsync, `walhook`
  autocheckpoint, `walpersist`, readonly-SHM, checkpoint-blocking, accepted
  VFS writer/sync/lock, rollback-journal apply/commit, WAL byte truncation,
  and checkpoint transaction wrapper batches.
- Mapped denominator coverage remains complete at `1589 / 1589`; this is
  countable PHP PASS-line growth over already mapped real upstream WAL
  inventory.

Dependency closure:

- No new support component is needed for the admitted rows. The follow-up
  excluded rows would need a bounded multi-client busy-handler scheduler model
  before they can be represented without faking client state.
