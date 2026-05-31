# real-upstream-corpus-vfs-io-dynamic-sizehint-chunks-20260531T044216Z

## Scope

- Micro-slice: `real-upstream-corpus-vfs-io-dynamic-20260531T044216Z-0`
- Accepted base: `ea98db4ecded4356aee592549997cc44a35fab5b`
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/syscall.test`
  - `syscall.test` section `8.2`: `file_control_chunksize_test db main 4096` plus size hints `1000`, `3000`, `4096`, and `4197`
  - `syscall.test` section `8.4`: `file_control_chunksize_test db main 16` plus size hints `5`, `13`, `45`, `48`, and `49`

## Behavior Ported

Added `SQLiteVfsIoDynamicPlan::sizeHintChunkGrowthProfile()` for VFS file-control size-hint growth semantics:

- size hints grow the file only when the hint exceeds the current file size;
- growth rounds up to the configured chunk-size boundary;
- no-growth hints preserve the current file size and do not force a shrink or realignment;
- invalid chunk sizes and negative byte counts are rejected.

The new focused test file expands those exact upstream chunk boundaries into a dynamic matrix over 16-byte and 4096-byte chunks plus adjacent chunk sizes, preserving the upstream `syscall-8` rounding contract without duplicating accepted VFS lock/write/sync clusters.

## Verification

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamVfsIoDynamicSizeHintChunksTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamVfsIoDynamicSizeHintChunksTest.php`
  - `1 test files, 8648 assertions, 0 failures`
  - `1299` focused PASS cases
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsIoDynamicUpstreamCorpusTest.php`
  - `1 test files, 588 assertions, 0 failures`

## Dashboard Movement

- Expected focused PASS-line movement: `+1299`
- `lane-status.json` updated from `2125874` to `2127173` `phpPass`.
- Mapped denominator coverage remains `1589 / 1589`; this is PASS-line growth only.

## Dependency Closure

No new support component is needed. This reuses the existing bounded VFS I/O dynamic plan surface and existing PHP arithmetic; no external SQLite runner, live service, or native extension dependency is introduced.

## Non-Overlap

This slice does not repeat accepted VFS file writer, locked writer, sync plan/apply, rollback journal apply/commit, process file locks, VFS lock state, WAL checkpoint transactions, WAL byte truncation, or temp-fault coverage. It owns only `syscall.test` chunk-size size-hint growth rounding.
