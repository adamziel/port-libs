# real-upstream-corpus-select-core-dynamic-20260530T190127Z-0 blocked

Slice: `real-upstream-corpus-select-core-dynamic-20260530T190127Z-0`

Base accepted HEAD: `28d061295d83cf4ef005caf2fa1b98587d6f90d3`

## Upstream source truth inspected

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectA.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectB.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectC.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectD.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectE.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectF.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectG.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectH.test`

## Blocker

The remaining select-core dynamic surface in this worktree is not large enough
for a ready throughput handoff under the active hard floor without duplicating
accepted or just-ready coverage.

Direct scan results:

- Existing PHP corpus files already own `selectA.test` compound merge ordering
  and `selectB.test` derived compound flattening:
  `SQLiteRealUpstreamSelectACompoundOrderDynamicTest.php` and
  `SQLiteRealUpstreamSelectBDerivedCompoundDynamicTest.php`.
- Existing notes and tests already cover broad `select1.test` through
  `select9.test` behavior, including recent select4 compound, select8
  grouped LIMIT/OFFSET, select9 compound LIMIT/OFFSET, grouped SELECT text,
  expression ORDER BY, SELECT subqueries, JSON table SELECT sources, and
  derived compound flattening.
- The upstream runner-map note
  `bulk-upstream-runner-map-gap-closure-dynamic-20260530T182643Z-0.md`
  already records zero-error runner evidence for `selectA.test` through
  `selectH.test`, so this slice cannot honestly claim mapped denominator
  growth for those script ids.
- `selectC.test` through `selectH.test` contain small residual sections or
  surfaces that are already covered by accepted DISTINCT, compound SELECT,
  ORDER BY, view, trigger, schema-name resolution, generated-column, and
  sqlite_schema behavior. Porting a convenience subset would be below the
  required `real-upstream-corpus-*` gates of at least 1,000 distinct focused
  PASS cases, 5,000 behavior assertions, a named behavior blocker unlocking
  at least 2,000 PASS cases / 10,000 assertions, or guarded denominator
  movement.

## Rejected small patch shape

I did not add another small PHP test file for `selectC.test`/`selectE.test`
because the likely green subset would mostly reassert accepted DISTINCT,
compound ORDER BY collation, or view/compound behavior and would not satisfy
the active throughput floor. I also did not add metadata-only admission rows or
fabricated dynamic loops.

## Next larger batch to try

The next useful select-family batch should be assigned as a broader
`real-upstream-corpus-select-*` or `bulk-upstream-*` slice that owns one of:

- a non-overlapping upstream family outside `select1.test` through
  `selectH.test`, such as a coherent `where*` planner/executor corpus that can
  produce at least 1,000 distinct focused PASS cases or 5,000 assertions; or
- a real `SQLiteSelectSql`/runner blocker that makes a currently failing
  hydrated upstream script family admit at least 2,000 PASS cases or 10,000
  assertions in the next batch; or
- guarded upstream-runner mapped denominator growth for select-family scripts
  not already counted by the current runner-map evidence.

Dependency closure: no new support component was added. A future larger batch
should reuse the lane-local `SQLiteSelectSql`, `SQLiteSelectQuery`, compound
SELECT, collation, and runner evidence helpers, unless it identifies a concrete
parser/executor blocker with failing upstream evidence.
