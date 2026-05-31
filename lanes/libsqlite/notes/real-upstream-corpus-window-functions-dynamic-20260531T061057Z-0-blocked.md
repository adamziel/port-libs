# real-upstream-corpus-window-functions-dynamic-20260531T061057Z-0 blocked

Status: blocked, no ready behavior patch emitted.

This worker was assigned a fresh real-upstream SQLite window-function dynamic
corpus slice. I inspected the hydrated upstream source truth in
`/home/claude/port-libs/.upstream-cache/libsqlite/test` and the current
accepted lane tests/notes in this worktree before editing.

Attempted upstream domain:

- `window1.test` through `window9.test`
- `windowA.test` through `windowE.test`
- `windowerr.test`
- `windowfault.test`
- `windowpushd.test`

Current accepted overlap found:

- `SQLiteRealUpstreamWindowARangeNullsDynamicTest.php`,
  `SQLiteRealUpstreamWindowAOrderedRangeDynamicTest.php`, and
  `SQLiteRealUpstreamWindowFractionalRangeDynamicTest.php` already cover the
  upstream `windowA.test` descending `RANGE` / `NULLS FIRST` / `NULLS LAST`
  matrix.
- `SQLiteRealUpstreamWindowErrDynamicTest.php` and
  `SQLiteRealUpstreamWindowErrDynamicCorpusTest.php` already cover the
  upstream `windowerr.test` negative frame-boundary, invalid offset, invalid
  function-argument, and parser-level rejection cases.
- Existing focused PHP corpus files cover `window1`, `window2`, `window3`,
  `window4`, `window5`, `window6`, `window7`, `window8`, `window9`,
  `windowA`, `windowB`, `windowC`, `windowD`, `windowE`, `windowerr`,
  `windowfault`, and `windowpushd`, including large dynamic batches for
  ranking/value functions, `ROWS`/`RANGE`/`GROUPS` frames, `EXCLUDE`, `FILTER`,
  custom aggregate/window behavior, JSON-object inverse windows, collation
  ranges, parser-level named windows, and pushdown behavior.

Blocker:

The remaining obvious upstream window-function surface in the current accepted
worktree is too saturated to honestly satisfy the active real-corpus hard
handoff floor. Adding another convenience-sized dynamic file would mostly
repeat accepted `SQLiteRealUpstreamWindow*` behavior and would not satisfy any
valid ready gate: it would not add at least 1000 distinct TestRunner PASS
cases, 5000 behavior assertions, a named behavior/tooling fix that unlocks the
next 2000 PASS cases, or real mapped denominator movement.

Next larger batch to try:

- Build a scenario-level coverage index for hydrated upstream `window*.test`
  files by extracting `do_execsql_test`, `do_catchsql_test`, `do_test`, and
  generated `foreach` scenario ids, then map those ids to existing
  `SQLiteRealUpstreamWindow*` test names and notes.
- Select only unmapped residual upstream scenarios and batch at least 1000
  distinct TestRunner cases from them. Good first candidates are residuals in
  `window7.test`, `window8.test`, `windowB.test`, `windowfault.test`, and
  `windowpushd.test`, because those have the largest upstream scenario volume.
- If the residual index cannot find a 1000-PASS non-overlapping window batch,
  future real-corpus throughput should pivot to the known-red domains named in
  `lanes/libsqlite/lane-status.json`: pager/WAL default-memory pressure,
  SELECT limit/compound-collation, JSON502 escaped-path behavior, PRAGMA
  expected-shape regressions, or expression IS/unary-plus semantics.

Verification:

- No production source, tests, examples, manifests, or status counters were
  changed.
- Dependency closure: no new support component is needed; the blocker is
  corpus saturation without a scenario-level residual coverage index.
