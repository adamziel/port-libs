# real-upstream-corpus-window-functions-dynamic-20260531T060610Z-0 Blocked

Slice: `real-upstream-corpus-window-functions-dynamic-20260531T060610Z-0`

Base accepted HEAD: `5a0bbcc53e4d53b976a73e07fed57fd92e934f80`

Status: blocked by non-overlap and throughput floor.

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

Overlap found:

- `window1.test` is already covered by dedicated files for basic aggregates,
  partitioned running aggregates, subquery and partition behavior, view/trigger
  behavior, regional sales frames, correlated filter subqueries, lead/outer
  ORDER BY/LIMIT, and chained window definitions.
- `window2.test` has generated ROWS/frame-boundary/exclude dynamic corpus
  coverage.
- `window3.test` and `window4.test` have ranking, distribution, navigation,
  value, frame, and aggregate dynamic coverage.
- `window5.test` custom window aggregate behavior is already present in
  `SQLiteRealUpstreamWindow5CustomAggregateDynamicTest.php`,
  `SQLiteRealUpstreamWindow5CustomDynamicCorpusTest.php`,
  `SQLiteRealUpstreamWindow5CustomFunctionDynamicTest.php`, and related
  dynamic corpus files.
- `window6.test`, `window7.test`, `window8.test`, `window9.test`, and
  `windowA.test` through `windowE.test` all have existing real-upstream dynamic
  batches for their value, range, groups, collation, NULL-placement, separator,
  truth, and large-frame behaviors.
- `windowerr.test`, `windowfault.test`, and `windowpushd.test` have existing
  focused dynamic or fault/pushdown batches.
- `filter1.test` and `filter2.test` are already covered by
  `SQLiteRealUpstreamFilterWindowDynamicTest.php`.

Why no ready patch:

The current hard handoff floor for `real-upstream-corpus-*` requires at least
1,000 distinct focused PASS cases, 5,000 behavior assertions, a blocker fix
that unlocks at least 2,000 PASS cases or 10,000 assertions, or real mapped
denominator movement. A small additional hand-port from the inspected window
files would either duplicate accepted coverage or add a convenience-sized
test below the floor. Adding generated variants around already-covered helper
behavior would not satisfy the real-upstream corpus rule.

Next larger batch to try:

Use the hydrated upstream runner or a parser-assisted inventory tool to compare
every `do_execsql_test` and `do_catchsql_test` id from the window-related Tcl
files against the existing `SQLiteRealUpstreamWindow*` and
`SQLiteRealUpstreamFilterWindow*` PHP tests. The next useful implementation
slice should own only the resulting missing Tcl ids and should batch enough
missing behavior to meet the current floor, or should fix the inventory tooling
so the next admitted batch can prove at least 2,000 new PASS cases or 10,000
assertions.

Dependency closure: no new runtime support component was added. The likely
next support component is a lane-local Tcl-id inventory comparison tool for
real upstream corpus admission; its activation gate should be a report of
uncovered real `window*.test`/`filter*.test` ids with exact PHP test ownership
and no generated fake script ids.
