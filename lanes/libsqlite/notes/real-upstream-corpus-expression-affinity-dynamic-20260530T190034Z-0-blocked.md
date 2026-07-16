# real-upstream-corpus-expression-affinity-dynamic-20260530T190034Z-0 blocked

Slice: `real-upstream-corpus-expression-affinity-dynamic-20260530T190034Z-0`

Base accepted HEAD: `28d061295d83cf4ef005caf2fa1b98587d6f90d3`

Status: blocked by current-base overlap plus the active hard handoff floor for
`real-upstream-corpus-*` slices.

Hydrated upstream SQLite files inspected:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity3.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/cast.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/types.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/types3.test`

Current-base overlap found:

- `SQLiteRealUpstreamCorpusExpressionAffinityDynamicTest.php`
- `SQLiteRealUpstreamCorpusExpressionAffinityDynamicFollowupTest.php`
- `SQLiteRealUpstreamCorpusExpressionAffinityDynamicCastTest.php`
- `SQLiteRealUpstreamExpressionAffinityDynamicCorpusTest.php`
- `SQLiteRealUpstreamExpressionAffinityDynamicTest.php`
- `SQLiteRealUpstreamExpressionAffinityDynamicMatrixTest.php`
- `SQLiteRealUpstreamExpressionAffinityDynamicLargeTest.php`
- `SQLiteRealUpstreamExpressionConcatBulkCorpusTest.php`
- `SQLiteRealUpstreamExprBulkDynamicTest.php`
- `SQLiteRealUpstreamEExprNullTypeBulkTest.php`
- `SQLiteRealUpstreamTypes2AffinityDynamicBulkTest.php`

`lane-status.json` also records accepted expression-affinity hex/text/types2,
matrix, large-value, and dynamic corpus behavior. The remaining manual
expression/affinity work in `cast.test`, `types.test`, `types3.test`, and the
non-duplicate parts of `affinity3.test` is real, but a bounded hand port from
those files would not satisfy any active ready gate:

- at least `1000` distinct focused TestRunner PASS cases;
- at least `5000` behavior assertions from real upstream cases;
- a named blocker fix that unlocks at least `2000` PASS cases or `10000`
  assertions in the next admitted batch;
- guarded mapped-denominator movement.

This session therefore does not emit a small passing patch. A small add-on
would overlap the already accepted expression-affinity corpus or re-enter the
known rejected real-cast surface without proving the required unlock volume.

Next larger batch to try:

- Build a lane-local expression/affinity corpus adapter that consumes real
  `do_execsql_test`, `do_catchsql_test`, and loop-generated rows from
  `cast.test`, `types.test`, `types3.test`, and the non-overlapping parts of
  `affinity3.test`.
- Reuse the existing expression-affinity focused test pattern, but de-duplicate
  against the already accepted `e_expr.test`, `expr.test`, `affinity2.test`,
  `types2.test`, matrix, large-value, concat, and null/type bulk files.
- Repair the known rejected real-cast behavior first, then admit a single
  source-traceable batch with at least `5000` assertions or `1000` PASS cases.

Dependency closure: no new external component is needed. The missing piece is
a lane-local corpus extraction/de-duplication helper plus the real-cast
behavior repair.

Root harness: not run - isolated micro-slice.
