# real-upstream-corpus-window-functions-dynamic-20260530T234116Z-0

Base accepted HEAD: `1e28a5dbe5f8813a907a64ec2d403f8339418de7`

This slice adds a real upstream `windowpushd.test` expansion focused on dynamic
window-function view pushdown behavior. It uses the hydrated upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test`
- Scenarios: `2.0` through `2.1`, specifically view `v1` `max(c) OVER
  (PARTITION BY a)` and view `v3` `row_number() OVER (PARTITION BY b)` with
  pushed `IN`, `IS`, collation-like equality, less-than, range, and residual
  filters.

Added focused file:

- `lanes/libsqlite/tests/SQLiteRealUpstreamWindowPushdownExpandedDynamicTest.php`

The file contributes 1,001 focused TestRunner PASS cases: 500 native
partitioned `max()` window checks, 500 native partitioned `row_number()` checks,
and one upstream source-citation check. The actual side uses
`SQLiteWindowFunction`; expected values are computed by independent PHP oracles,
not by metadata rows or repeated self-comparisons.

Non-overlap:

- This extends the accepted `windowpushd.test` coverage in
  `SQLiteRealUpstreamWindowPushdownDynamicTest.php` with a broader filter/corpus
  matrix. It does not repeat accepted `window4`, `window8`, `window9`,
  `windowA`, `windowB`, `windowC`, `windowD`, `windowE`, `windowerr`,
  `windowfault`, JSON, WAL, B-tree, VFS, source-neutral, or suite-evidence
  surfaces.

Dependency closure:

- No new support component is needed. This reuses the native
  `SQLiteWindowFunction` implementation and lane-local PHP oracle code.
