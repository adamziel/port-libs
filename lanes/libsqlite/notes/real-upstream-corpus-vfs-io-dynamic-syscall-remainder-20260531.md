## real-upstream-corpus-vfs-io-dynamic syscall remainder

Slice: `real-upstream-corpus-vfs-io-dynamic-20260531T041531Z-0`

Base accepted HEAD: `6e668fbae83ee0543bff0a4aa8940cbc4e4fb4ca`

Added focused PHP corpus coverage in
`SQLiteRealUpstreamCorpusVfsSyscallRemainderDynamicTest.php` for hydrated
SQLite upstream `/home/claude/port-libs/.upstream-cache/libsqlite/test/syscall.test`:

- `syscall.test` 1.1.1-1.3.2: xSetSystemCall reset/install behavior across the
  Unix syscall registry.
- `syscall.test` 2.1.1-2.1.2: xGetSystemCall existence behavior.
- `syscall.test` 3.1: xNextSystemCall list/cursor behavior over installed
  system calls.
- `syscall.test` 7.1-7.3: one-byte database files open as empty; two or more
  bytes without a SQLite header are treated as not-a-database.
- `syscall.test` 8.1 and 8.2.1-8.2.5: chunk-size file-control size hints round
  preallocation to the configured chunk.

Focused verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsSyscallRemainderDynamicTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsSyscallRemainderDynamicTest.php`
  - `1 test files, 14906 assertions, 0 failures`
  - `1302` generated upstream behavior PASS cases plus malformed-input and
    upstream-citation guards.

Expected movement:

- PASS-line growth: `+1302` focused PASS lines for the new file, including
  guard/citation cases.
- Behavior assertion growth: `+14906`.
- Mapped denominator remains `1589 / 1589`; this is focused PHP corpus growth,
  not new denominator mapping.

Non-overlap:

- Does not repeat accepted VFS lock-state, process-lock, locked-writer,
  sync-plan/apply, rollback-journal apply, WAL byte truncation, or mmap dynamic
  slices.
- Uses existing generic `SQLiteVfsIoDynamicPlan` behavior and adds no
  WordPress-specific API or scenario names.

Dependency closure:

- No new support component is needed. The slice reuses the existing bounded VFS
  I/O dynamic helpers and hydrated upstream `syscall.test` source truth.
