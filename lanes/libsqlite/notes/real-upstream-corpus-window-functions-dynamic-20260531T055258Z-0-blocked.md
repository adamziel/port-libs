# real-upstream-corpus-window-functions-dynamic-20260531T055258Z-0 blocked

Status: blocked, no ready behavior patch emitted.

This slice was assigned to real upstream SQLite window-function dynamic corpus
work. I inspected the hydrated upstream source truth at
`/home/claude/port-libs/.upstream-cache/libsqlite/test` and the current
accepted lane tests under `lanes/libsqlite/tests`.

Attempted upstream domain:

- `window1.test` through `window9.test`
- `windowA.test` through `windowE.test`
- `windowerr.test`
- `windowfault.test`
- `windowpushd.test`

Current accepted overlap found:

- `SQLiteRealUpstreamWindow9AggregateSubqueryDynamicTest.php`
  already covers upstream `window9.test` sections `7.1-7.4`, `8.1.1-8.4`,
  and `9.1` with 1200 dynamic aggregate/order/subquery cases.
- `SQLiteRealUpstreamWindow9CollationFilterDynamicTest.php`
  already covers upstream `window9.test` sections `1.2-1.5` and `10.1-10.4`
  with 1000 dynamic filtered-frame cases.
- The accepted window corpus already contains focused files for
  `window1`, `window2`, `window3`, `window4`, `window5`, `window6`,
  `window7`, `window8`, `windowA`, `windowB`, `windowC`, `windowD`,
  `windowE`, `windowerr`, `windowfault`, and `windowpushd`, including
  parser-level `SQLiteSelectSql` JSON/window pushdown and large dynamic
  pushdown batches.

Blocker:

The remaining obvious window-function corpus surface in this worktree is too
dense with accepted coverage to honestly meet the active throughput handoff
floor by adding another focused PHP test file. A small additional hand-port
would overlap existing real upstream window files and would not satisfy any
valid ready gate: it would not add 1000 distinct focused PASS cases, 5000
behavior assertions, a named blocker unlock, or mapped denominator movement.

Next larger batch to try:

- Build or reuse a static coverage index that maps each upstream
  `window*.test` `do_execsql_test` / `do_catchsql_test` scenario to the current
  `SQLiteRealUpstreamWindow*` PHP test filenames, then select only the
  unmapped residual scenarios.
- If unmapped residuals are found, batch at least 1000 distinct TestRunner
  cases from those residuals, preferably across `window7`, `window8`,
  `windowB`, `windowfault`, and `windowpushd`, because those files have the
  largest upstream scenario volume.
- If no residuals of that size remain, pivot future real-corpus throughput to
  a less saturated domain such as pager/WAL default-memory pressure, SELECT
  known-red compound/limit cases, JSON502 escaped-path behavior, or PRAGMA
  expected-shape regressions named in `lane-status.json`.

Verification:

- No source or test files were changed.
- Dependency closure: no new support component was needed; this is a
  bounded corpus-overlap blocker note.
