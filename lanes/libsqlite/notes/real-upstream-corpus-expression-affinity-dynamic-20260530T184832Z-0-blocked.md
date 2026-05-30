# real-upstream-corpus-expression-affinity-dynamic-20260530T184832Z-0 blocked

Slice: `real-upstream-corpus-expression-affinity-dynamic-20260530T184832Z-0`

Base accepted HEAD: `7e63d4798cb030955a466f3272d59cba9c03648e`

Status: blocked by overlap and the active real-upstream hard handoff floor.

Upstream source files inspected from the hydrated SQLite checkout:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity3.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/cast.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/numcast.test`

The current base already contains the expression/affinity real-corpus surface in
these focused PHP files:

- `SQLiteRealUpstreamCorpusExpressionAffinityDynamicTest.php`
- `SQLiteRealUpstreamCorpusExpressionAffinityDynamicFollowupTest.php`
- `SQLiteRealUpstreamCorpusExpressionAffinityDynamicCastTest.php`
- `SQLiteRealUpstreamExpressionAffinityDynamicTest.php`
- `SQLiteRealUpstreamExpressionAffinityDynamicMatrixTest.php`
- `SQLiteRealUpstreamExpressionAffinityDynamicLargeTest.php`
- `SQLiteRealUpstreamExpressionConcatBulkCorpusTest.php`
- `SQLiteRealUpstreamExprBulkDynamicTest.php`
- `SQLiteRealUpstreamTypes2AffinityDynamicBulkTest.php`

Those files total `2444` lines of existing focused test source and cover the
same upstream families this slice was assigned: `e_expr.test` precedence,
literal storage classes, unary/binary operators, `expr.test` operator cases,
`affinity2.test` comparison affinity, `types2.test` indexed affinity rowsets,
large-value expression affinity, concatenation, and cast/real-expression
follow-ups.

The current `lane-status.json` also records that a recent `real-cast` handoff
was rejected with `20` focused failures. Replaying a small subset of
`cast.test` or `numcast.test` here would either duplicate accepted expression
affinity coverage or re-enter that known red cast surface without meeting the
active gate.

No ready patch was emitted because the active `real-upstream-corpus-*` floor
requires at least one of:

- `1000` distinct focused TestRunner PASS cases;
- `5000` behavior assertions from real upstream SQLite cases;
- a named blocker fix that unlocks at least `2000` PASS cases or `10000`
  assertions;
- real mapped denominator movement with guarded runner evidence.

This micro-slice cannot honestly satisfy any of those gates with a bounded
manual port from the remaining expression/affinity files on this base. A small
green add-on would be a convenience-sized duplicate and should be rejected.

Next larger batch to try:

- Build or reuse a lane-local corpus adapter for `cast.test`, `numcast.test`,
  `types.test`, `types3.test`, and the uncovered non-duplicate portions of
  `affinity3.test`.
- Exclude the already accepted `e_expr.test`, `expr.test`, `affinity2.test`,
  `types2.test`, expression-large, matrix, concat, and real-cast rejected rows.
- First repair the rejected `real-cast` failures as a named behavior blocker,
  then admit a larger generated-but-source-traceable PHP corpus batch with at
  least `5000` assertions or `1000` PASS cases.

Dependency closure: no new external support component is needed. The missing
piece is a bounded lane-local upstream expression/affinity corpus extraction
and de-duplication helper, plus a repair for the rejected real-cast behavior
before `cast.test` and `numcast.test` can be counted safely.

Root harness: not run - isolated micro-slice.
