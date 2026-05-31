# real-upstream-corpus-select-core-dynamic-20260531T020207Z-1

Base accepted HEAD: `e1f1e0a66bff0730bf5e4118bd715c8a11c33354`.

Added focused real-upstream SELECT corpus coverage from
`/home/claude/port-libs/.upstream-cache/libsqlite/test/selectH.test`.

Owned upstream scenarios:

- `selectH-4.1`: `SELECT 1 FROM (...)` over a compound subquery whose first arm
  is `SELECT DISTINCT name COLLATE rtrim FROM sqlite_schema`.
- `selectH-4.2`: direct `SELECT DISTINCT name COLLATE rtrim FROM sqlite_schema
  UNION ALL SELECT a FROM t1` result rows.
- `selectH-5.1`: `SELECT DISTINCT val1 FROM t1 UNION ALL SELECT val2 FROM t2`
  where the right arm is empty.

Implementation movement:

- `SQLiteSelectResult` now applies SELECT DISTINCT collation metadata when
  computing distinct row keys.
- `SQLiteSelectSql` now carries `COLLATE` result-column metadata into
  SELECT DISTINCT and compound SELECT set handling, including collated column
  names inside compound arms.

Focused PHP coverage:

- Added `SQLiteRealUpstreamSelectHSchemaDistinctUnionDynamicTest.php`.
- 1 citation case plus 3,000 dynamic real-upstream behavior cases.
- Focused result: `1 test files, 12005 assertions, 0 failures`.

Non-overlap:

- This owns the `selectH-4.1`, `selectH-4.2`, and `selectH-5.1`
  schema/distinct-union tail cluster.
- It does not repeat accepted `selectH-1.2`, `selectH-2.1`, `selectH-3.*`,
  `selectH-5.2`, `select1` through `selectG`, grouped SELECT text, expression
  ORDER BY, JSON table cursor/source/constraint work, WAL/VFS/B-tree surfaces,
  or metadata-only runner rows.

Dependency closure:

- No new support component is needed. The batch reuses the existing native
  `SQLiteSelectSql`, `SQLiteSelectQuery`, `SQLiteSelectResult`, and
  `SQLiteSelectCompound` execution path.
