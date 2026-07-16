# Real Upstream Corpus VFS I/O Dynamic

Slice: `real-upstream-corpus-vfs-io-dynamic-20260530T164345Z-0`

Accepted base: `77aaee93e1232164eda546b44d6f0e2ddd146261`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/io.test`
- Focused subtests/scenarios: `io-2.2`, `io-2.3`, `io-2.4.1-2.4.3`, `io-2.5.1-2.5.3`, `io-2.6.*`, `io-3.*`, and `io-4.*`.

Implemented behavior:

- Added `SQLiteVfsIoTransactionSequencePlan` to model dynamic VFS I/O traffic decisions for rollback-journal transactions without changing the existing VFS I/O dynamic corpus helpers.
- Ported atomic-write, sequential-device, and safe-append behavior from upstream `io.test` into focused PHP tests.
- The batch adds 20 focused TestRunner PASS cases with 149 assertions. It is below the normal 500-assertion real-corpus floor because this is a bounded VFS I/O behavior model rather than a metadata admission batch; no denominator rows are claimed.

Dependency closure:

- No new support component is needed. The implementation is a generic PHP VFS I/O traffic planner and reuses existing lane autoload/test infrastructure.
