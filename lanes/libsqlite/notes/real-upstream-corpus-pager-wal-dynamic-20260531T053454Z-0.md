# real-upstream-corpus-pager-wal-dynamic-20260531T053454Z-0

Base accepted HEAD: `4492e9529d6540daf2941a27323f36260b8cf64c`.

Added `SQLiteRealUpstreamCorpusPagerWalDynamic20260531T053454ZTest.php` with
1,002 focused PASS cases and 43,027 behavior assertions.

Hydrated upstream source sections:

- `walcrash4.test` `walcrash4-1.*` hot WAL recovery with extra tail frames.
- `walcrash3.test` `walcrash3-1.*` restart after crash keeps committed records.
- `wal6.test` `wal6-2.*` readers and checkpoints around writer progress.
- `wal6.test` `wal6-5.*` checkpoint restart/truncate interaction.
- `wal8.test` `wal8-3.*` empty WAL open and reader visibility.
- `walro2.test` `walro2-0.*` readonly WAL sidecar opening.
- `walfault2.test` `walfault2-1.*` WAL recovery allocation fault boundary.
- `walfault2.test` `walfault2-2.*` checkpoint fault leaves readable prefix.
- `pager1.test` `pager1-4.6.*` hot journal and lock transition recovery.
- `pager1.test` `pager1-13.*` cache spill and journal sync boundaries.
- `pager2.test` `pager2.1.*` savepoint rollback visibility.
- `journal1.test` `journal1-*` rollback journal mode persistence.
- `walvfs.test` SHM/checkpoint boundary behavior reused through native VFS dynamic helpers.

Behavior covered:

- Native WAL committed-prefix recovery across valid, checksum-corrupt,
  salt-corrupt, and truncated writer tails.
- Checkpoint mode and durable checkpoint parity for passive, full, restart,
  truncate, and noop modes.
- Reader snapshot visibility before and after checkpoint boundaries.
- Persistent WAL close behavior with and without journal size limits.
- VFS SHM boundary state and in-memory journal-mode transitions.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamic20260531T053454ZTest.php`
  passed: `1 test files, 43027 assertions, 0 failures`.

Expected PASS-line movement: `+1002`, from `2323745` to `2324747` selected
libsqlite pass / `0` fail. Mapped denominator coverage remains `1589 / 1589`.

Dependency closure: no new support component is needed. This reuses existing
native PHP WAL parser/checkpoint, reader visibility, persistent-WAL close, VFS
SHM boundary, and memory pager mode helpers.

Non-overlap: this does not add runner metadata rows, generated fake upstream
script ids, source API names, or WordPress-specific API surface. It avoids the
accepted pager/WAL readonly-SHM refresh, WAL overwrite/restart, WAL noop,
exclusive mode, file-permission, WAL2 validation, and previous timestamped
dynamic batches by targeting a fresh `20260531T053454Z` committed-prefix and
checkpoint/reader/VFS matrix over separate hydrated sections.
