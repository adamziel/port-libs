# real-upstream-corpus-select-core-dynamic-20260531T054325Z-0

Base accepted HEAD: `4492e9529d6540daf2941a27323f36260b8cf64c`.

Added focused real-upstream SELECT corpus coverage from
`/home/claude/port-libs/.upstream-cache/libsqlite/test/selectH.test`.

Owned upstream scenarios:

- `selectH-1.2`: `SELECT DISTINCT` from a `UNION ALL` subquery while unused
  subquery output columns remain unprojected by the outer query.
- `selectH-2.1`: `UNION ALL` subquery `ORDER BY` on an output column that is
  not projected by the outer query.
- `selectH-5.1` / `selectH-5.2`: `SELECT DISTINCT ... UNION ALL ...` with an
  empty or optional right arm under outer `count(1234)`.

Patch:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamSelectHOmitUnusedDynamicTest.php`.
- The new file cites the hydrated upstream source and adds 3,600 dynamic
  SELECT cases plus one source citation case.
- The dynamic cases vary wide source rows, duplicate `DISTINCT` values, inner
  compound sort keys, empty/non-empty right arms, and outer aggregate counts.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectHOmitUnusedDynamicTest.php`
- Result: `1 test files, 18007 assertions, 0 failures`
- PASS lines: `3601`

Non-overlap:

- This owns the `selectH.test` omit-unused compound subquery behavior cluster.
- It does not repeat accepted SELECT SQL text/JOIN/GROUP/subquery/ORDER/LIMIT
  clusters, `selectB` set ops, `selectC` alias behavior, `selectD`
  parenthesized joins, `select2` scalar WHERE batches, JSON table source/cursor
  work, VFS/WAL/B-tree clusters, or metadata-only runner rows.

Dependency closure:

- No new support component is needed. The slice reuses lane-local
  `SQLiteSelectSql` compound SELECT, DISTINCT, wildcard, subquery source,
  inner `ORDER BY`, outer aggregate, and row-array execution support.
