# real-upstream-corpus-window-functions-dynamic-20260531T064309Z-0 Blocked

Slice: `real-upstream-corpus-window-functions-dynamic-20260531T064309Z-0`

Base accepted HEAD: `adb26e7f16ecd89937cf2d16ad3f15841131934b`

Status: blocked by current-base non-overlap and the real-upstream corpus
handoff floor.

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
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/filterfault.test`

Static inventory sampled on this base:

```text
window1.test 295
window2.test 65
window3.test 1222
window4.test 220
window5.test 6
window6.test 45
window7.test 10
window8.test 361
window9.test 40
windowA.test 18
windowB.test 58
windowC.test 3
windowD.test 13
windowE.test 15
windowerr.test 1
windowfault.test 7
windowpushd.test 17
filter1.test 35
filter2.test 15
filterfault.test 1
```

Total upstream window/filter `do_execsql_test` and `do_catchsql_test` rows
sampled: 2,447. Current local window/filter source citations appear in 244
lane test/note files.

Current-base overlap found:

- `window1.test` is already represented by accepted real-upstream dynamic
  files for basic frames, partitioned running aggregates, regional sales,
  chained windows, subquery partitions, lead/outer ORDER BY/LIMIT, mixed
  RANGE offsets, and late dynamic follow-ups.
- `window2.test`, `window3.test`, and `window4.test` already have dynamic
  frame-boundary, EXCLUDE, ranking, distribution, navigation, aggregate, and
  value-function coverage.
- `window5.test` and `window6.test` already have custom window aggregate,
  custom function, named-window, and `nth_value()` argument/default-frame
  coverage.
- `window7.test`, `window8.test`, `window9.test`, and `windowA.test` through
  `windowE.test` already have accepted dynamic batches for GROUPS/RANGE,
  collation, NULL placement, separators, truth handling, inverse JSON/object
  behavior, custom collation, and large numeric following-frame behavior.
- `windowerr.test`, `windowfault.test`, `windowpushd.test`, `filter1.test`,
  and `filter2.test` have focused dynamic, fault, filter, and pushdown
  coverage. `filterfault.test` has only one upstream fault-injection row and
  does not by itself meet the current floor.

Why no ready patch:

The active hard handoff floor for `real-upstream-corpus-*` requires at least
one of: 1,000 distinct focused TestRunner PASS cases, 5,000 behavior
assertions, a named blocker fix that unlocks at least 2,000 PASS cases or
10,000 assertions, or real mapped denominator movement. The current accepted
base already has broad real upstream window coverage, and another manual
window hand-port from the inspected files would either duplicate accepted
behavior or fall below the required gate. Generating additional variants
around existing helper behavior would not satisfy the real upstream corpus
rule.

Next larger batch to try:

Build a lane-local Tcl-id inventory comparator for this domain. It should parse
every real `do_execsql_test` and `do_catchsql_test` id from `window*.test`,
`filter1.test`, `filter2.test`, and `filterfault.test`, map each id to existing
`SQLiteRealUpstreamWindow*` / `SQLiteRealUpstreamFilterWindow*` PHP ownership,
and emit only uncovered real upstream ids. A follow-up implementation slice
should own that uncovered-id report and either batch enough distinct behavior
to satisfy the current floor or classify the window/filter domain as exhausted
with machine-checkable evidence.

Dependency closure: no new runtime support component is needed. The smallest
useful support component is the lane-local Tcl-id inventory comparator above;
its activation gate is an uncovered-id report with exact PHP test ownership
and no generated fake upstream script ids.
