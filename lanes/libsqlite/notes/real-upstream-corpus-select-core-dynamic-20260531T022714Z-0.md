# real-upstream-corpus-select-core-dynamic-20260531T022714Z-0

Base accepted HEAD: `63d51c7a21361d431b9c650a3274805716613669`.

Added focused real-upstream SELECT core coverage from
`/home/claude/port-libs/.upstream-cache/libsqlite/test/select4.test`.

Owned upstream scenarios:

- `select4-14.1` and `select4-14.2`: table rows intersected with `VALUES`
  rows.
- `select4-14.3` and `select4-14.4`: `UNION` with inline `VALUES` rows and
  final `ORDER BY 1, 2, 3`.
- `select4-14.5` through `select4-14.8`: `EXCEPT` against one or more
  `VALUES` arms.
- `select4-14.16` and `select4-14.17`: `VALUES` as the left compound arm with
  `UNION ALL SELECT` and final `LIMIT`.

Focused PHP coverage:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamSelect4ValuesCompoundDynamicTest.php`.
- 1 source-citation case, 1 canonical upstream row-shape case, and 1,000
  dynamic TestRunner PASS cases.
- Each dynamic case varies the table row values and inline `VALUES` rows while
  checking flat result values, result counts, per-position values, and
  fingerprints across `INTERSECT`, `UNION`, `EXCEPT`, and `UNION ALL ... LIMIT`
  behavior.

Non-overlap:

- This owns the `select4.test` `select4-14` compound-with-VALUES cluster.
- It does not repeat accepted `select4` earlier compound coverage,
  `selectG` scalar/large VALUES coverage, `selectF` copy-register ordering,
  `selectH` omit-unused/empty-right compound coverage, `selectC` alias
  resolution, grouped SELECT text, expression `ORDER BY`, JSON table
  source/cursor/constraint work, WAL/VFS/B-tree surfaces, or metadata-only
  runner rows.
- Mapped denominator remains unchanged because `select4.test` is already
  represented in the hydrated upstream manifest/runner map.

Expected selected throughput movement:

- PASS lines: `+1002`.
- Focused assertions: `39039`.

Dependency closure: no new support component is needed; this reuses the
existing native `SQLiteSelectSql` compound SELECT and `VALUES` execution paths.
