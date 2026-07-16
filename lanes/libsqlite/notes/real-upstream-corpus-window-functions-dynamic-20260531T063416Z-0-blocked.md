# real-upstream-corpus-window-functions-dynamic-20260531T063416Z-0 Blocked

Slice: `real-upstream-corpus-window-functions-dynamic-20260531T063416Z-0`

Base accepted HEAD: `7685e747971ca86ceced872addf2e1032378bd34`

Status: blocked by non-overlap and the current real-upstream throughput floor.

Attempted upstream source window:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window5.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window6.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window7.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window8.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window9.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowA.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowB.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowC.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowD.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowerr.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowfault.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/filter1.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/filter2.test`

Current-base overlap found:

- The current accepted tree already has dedicated real-upstream dynamic PHP
  files for `window1.test` lead/sales/regional/chained/basic-frame behavior,
  `window2.test` ROWS/frame-boundary/exclude/range partitions, `window3.test`
  ranking/value/aggregate execution, and `window4.test` ntile/value/navigation
  behavior.
- `window5.test` custom aggregate and custom function behavior is already
  represented by the `SQLiteRealUpstreamWindow5*DynamicTest.php` family.
- `window6.test`, `window7.test`, `window8.test`, `window9.test`, and
  `windowA.test` through `windowE.test` have accepted dynamic batches for
  default frames, GROUPS/RANGE, collation, NULL placement, separators, truth
  handling, inverse JSON/object behavior, and large following-frame behavior.
- `windowerr.test`, `windowfault.test`, `windowpushd.test`, `filter1.test`,
  and `filter2.test` have focused dynamic or fault/filter/pushdown coverage.

Why no ready patch:

The active handoff floor for `real-upstream-corpus-*` requires at least one of:
1,000 distinct focused PASS cases, 5,000 behavior assertions, a blocker fix
that unlocks at least 2,000 PASS cases or 10,000 assertions, or real mapped
denominator movement. The current base already contains broad accepted window
coverage, and a small extra hand-port from the inspected files would either
duplicate accepted behavior or fall below the required gate. Generating more
variants around existing helper behavior would not satisfy the real upstream
corpus rule.

Next larger batch to try:

Add a lane-local Tcl-id inventory comparison tool for the real window corpus.
The tool should parse every `do_execsql_test` and `do_catchsql_test` id from
`window*.test`, `filter1.test`, and `filter2.test`, map each id to existing
`SQLiteRealUpstreamWindow*` / `SQLiteRealUpstreamFilterWindow*` ownership, and
emit only uncovered real upstream ids. The next implementation worker should
own that uncovered id list and batch enough distinct behavior to meet the
current floor, or classify the domain as exhausted with machine-checkable
evidence rather than another manual note.

Dependency closure: no new runtime support component is needed. The smallest
useful support component is the lane-local Tcl-id inventory comparator described
above; its activation gate is an uncovered-id report with exact PHP test
ownership and no generated fake upstream script ids.
