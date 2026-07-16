# Real Upstream Corpus VFS I/O Dynamic Slice

Session: `port-dev-sqlite-yield-dyn-real-vfs-20260531T000425Z`
Base accepted HEAD: `dd1b1090c602dc6e35c0593d57edce4faedf25d2`

Added focused PHP TestRunner coverage for real upstream SQLite VFS/device
I/O behavior, sourced from the hydrated upstream checkout:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/io.test`
  - `io-2.4` through `io-2.11`: atomic-write journal absence/creation,
    second-connection visibility, deferred journal commit boundaries,
    multi-file commit journal creation, rollback before journal creation,
    sector-size gating, specific `IOCAP_ATOMIC*` flags, and exclusive-locking
    journal-free behavior.
  - `io-3.*` and `io-4.*`: sequential-device sync elision and safe-append
    journal-header behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/crash3.test`
  - `crash3-1` through `crash3-3`: device-characteristic crash recovery where
    database content is either prior content or the completed transaction and
    integrity remains `ok`.

New focused test file:

- `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoAtomicDeviceDynamicTest.php`

Focused movement:

- Added `1,001` distinct TestRunner PASS cases.
- Focused command passed with `1 test files, 10751 assertions, 0 failures`.
- Expected selected `phpPass` movement: `1273283 -> 1274284`.
- Mapped denominator remains `1589 / 1589`; this is PASS-line growth, not new
  mapped inventory growth.

Non-overlap:

- Does not repeat the accepted VFS IOERR pointer-map batch, VFS lock-state,
  process locks, locked writer, rollback-journal apply, sync apply, WAL
  checkpoint transaction, WAL byte truncation, or JSON/SQL/B-tree clusters.
- This slice uses existing generic VFS traffic planning helpers and adds real
  upstream `io.test`/`crash3.test` behavioral coverage only.

Dependency closure:

- No new support component is needed. The slice reuses existing generic native
  PHP `SQLiteVfsIoTrafficPlan` behavior.
