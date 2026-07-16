# Real upstream window corpus inventory unblock

Slice: `real-upstream-corpus-window-functions-dynamic-20260531T101122Z-0`

Base accepted HEAD: `db6e720333280b900b4f227c59e0153ddd55f2fc`

This handoff implements the lane-local Tcl-id inventory comparator requested by
the prior window-corpus blocked notes. It does not add a new manual window
behavior variant. Instead, it makes the remaining real upstream window/filter
surface machine-checkable before another high-yield batch is selected.

Source truth scanned:

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

Focused evidence:

- `SQLiteWindowCorpusInventory::inventory()` parses `do_execsql_test` and
  `do_catchsql_test` IDs from active Tcl lines in the hydrated upstream files.
- The focused test proves `2444` active upstream Tcl IDs across the window and
  filter corpus. This intentionally excludes the three commented-out `window6`
  Tcl commands that the prior raw grep note counted, so follow-up workers do
  not claim dead source rows.
- The current-base citation report scans `lanes/libsqlite/tests` and
  `lanes/libsqlite/notes`, finds `143` ownership files, and emits explicit
  uncovered real upstream IDs. On this base the exact/range citation report is
  `516` covered and `1928` uncovered. The uncovered number is intentionally an
  over-candidate list because older dynamic tests often cite broad source
  sections rather than every Tcl row.

Non-overlap:

This patch avoids repeating accepted window behavior batches for `window1`
through `windowE`, `windowerr`, `windowfault`, `windowpushd`, `filter1`, and
`filter2`. It also avoids JSON/WAL/VFS/B-tree/planner/PRAGMA/trigger surfaces,
metadata-only runner rows, and generated fake upstream script IDs.

Next gate:

Use the new inventory report to choose the next non-overlapping window/filter
behavior batch. The next implementation worker should inspect the emitted
uncovered IDs, remove broad-citation false positives, then port enough
distinct real upstream behavior to satisfy the real-corpus floor.

Dependency closure:

No new runtime support component is needed. The new helper is lane-local
inventory tooling over hydrated upstream Tcl sources and existing PHP/notes
ownership citations.
