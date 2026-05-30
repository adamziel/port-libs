# real-upstream-corpus-vfs-io-dynamic-20260530T193508Z-0 blocker

Base accepted HEAD: `28f29f1b7137ae1bf099a6bea9838aec79fed0b3`.

Attempted upstream section:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/io.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr3.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr4.test`
- Existing coupled corpus tests:
  - `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicTest.php`
  - `lanes/libsqlite/tests/SQLiteRealUpstreamVfsIoDynamicCorpusTest.php`

Focused evidence on this accepted base:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamVfsIoDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicTest.php
Focused test run: 2 selected test files (root lock skipped)
2 test files, 13968 assertions, 0 failures
```

Why this slice is blocked instead of ready:

- The current accepted tree already covers the high-yield dynamic VFS I/O corpus from real upstream `io.test`, including io-1 quick-balance write counts, io-2 atomic-write visibility and journal admission, io-3 sequential sync behavior, io-4 safe-append journal sizing, io-5 default page-size selection, and io-6 pager-cache retention after atomic commits.
- The same focused files already cover real upstream dynamic fault matrices from `ioerr.test`, `ioerr5.test`, `ioerr6.test`, and `pagerfault.test`, including pager error-state recovery, hot-journal residue, SHM full faults, and unknown-lock retry/reopen behavior.
- The unported material inspected here is not large enough to satisfy the current hard handoff floor without artificial expansion:
  - `ioerr3.test` has two soft-heap-limit I/O-error scenarios, `ioerr3-1` and `ioerr3-2`.
  - `ioerr4.test` has six setup checks plus one `do_ioerr_test ioerr4-2` incremental-vacuum shared-cache scenario.
- Turning those small remaining sections into hundreds or thousands of `TestRunner` PASS cases would be repetitive wrapper inflation around one or two static behavior records, which the current throughput rule explicitly rejects.

Next larger batch to try:

- Combine `ioerr3.test` and `ioerr4.test` only after adding a real native fault-injection runner that can enumerate actual `do_ioerr_test` failpoints from the Tcl harness and execute/cache distinct pager/VFS transitions in PHP.
- The useful unlock is a parser/runner tool that hydrates `do_ioerr_test` failpoint counts and maps each failpoint to a concrete port operation such as xRead, xWrite, xSync, xTruncate, xShmMap, xLock, or shared-cache incremental-vacuum pager state.
- Gate for a future ready handoff: at least 1,000 distinct focused PASS cases or 5,000 behavior assertions from real failpoint rows, with upstream scenario names and failpoint indexes derived from the hydrated Tcl runner rather than generated fake script ids.

Dependency closure:

- No new support component is needed for this blocker note.
- A future ready patch needs a bounded native PHP upstream failpoint enumerator for `do_ioerr_test` if this VFS I/O dynamic family is expected to move additional high-volume PASS lines honestly.
