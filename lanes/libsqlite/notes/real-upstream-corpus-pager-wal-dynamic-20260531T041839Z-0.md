# real-upstream-corpus-pager-wal-dynamic-20260531T041839Z-0

Micro-slice: `real-upstream-corpus-pager-wal-dynamic-20260531T041839Z-0`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_wal.test`

Ported upstream sections:

- `e_wal.test` `1.1.1..1.3.3`: old VFS without shared-memory methods may create, read, and write WAL only when `locking_mode=EXCLUSIVE` is set before first access.
- `e_wal.test` `2.1.1..2.3.4`: exclusive WAL without a shared-memory wal-index remains sticky until the connection leaves WAL mode.
- `e_wal.test` `3.0..3.4.2`: normal WAL access on a shared-memory-capable VFS creates the SHM sidecar and permits locking-mode changes while only one connection owns the wal-index.
- `e_wal.test` `4.1.1..4.2.1`: entering WAL mode changes database header read/write format bytes from `1,1` to `2,2`.

Implementation:

- Added `SQLiteWalExclusiveModePlan`, a generic native PHP model for the `e_wal.test` VFS-version, shared-memory, exclusive-locking, SHM-sidecar, and WAL header-format boundaries.
- Added `SQLiteRealUpstreamPagerWalExclusiveModeDynamicTest.php` with 1,000 dynamic TestRunner cases plus hydrated-source, malformed-input, non-overlap, and dependency-closure guards.

Focused assertion/PASS movement:

- Focused TestRunner cases added: 1,003.
- Focused assertions verified locally: 15,012.
- Expected dashboard movement: `phpPass` +1,003 if the integrator accepts this focused file.
- Mapped denominator coverage remains `1589 / 1589`.

Non-overlap:

- This batch covers `e_wal.test` old-VFS exclusive WAL access, sticky exclusive locking, SHM creation, and header-format bytes.
- It avoids accepted checkpoint, WAL byte truncation, readonly-SHM, VFS writer/sync/lock-state, rollback-journal apply/commit, `walpersist`, `walrestart`, `walvfs`, `walprotocol`, and app-WAL slices.

Dependency closure:

- No new support component is needed.
- The slice reuses generic VFS shared-memory capability and WAL mode state modeling.
