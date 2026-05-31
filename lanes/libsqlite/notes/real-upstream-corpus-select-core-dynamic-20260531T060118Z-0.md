# real-upstream-corpus-select-core-dynamic-20260531T060118Z-0

Base accepted HEAD: `5a0bbcc53e4d53b976a73e07fed57fd92e934f80`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectF.test`
- Ported section: `selectF-2`
- Behavior: compound `UNION ALL` result rows must remain stable when ordered by
  result-column positions `ORDER BY 2, 1`, including SQL `NULL` sort-key rows.

## PHP Coverage

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusSelectCoreDynamicSelectFTest.php`.
- Adds one source-truth case, one canonical upstream-shaped behavior case,
  1,200 dynamic behavior cases, and one non-overlap/dependency case.
- Expected focused PASS-line movement: 1,203 TestRunner PASS cases.

## Non-Overlap

This slice owns the `selectF.test` OP_Copy/compound-order regression surface. It
does not repeat accepted selectC alias or derived distinct batches, select2
multi-table WHERE/CASE coverage, select5/select6 aggregate-derived batches,
single-table/JOIN/GROUP BY SELECT text dispatch, expression `ORDER BY`, JSON
table source/cursor/constraint work, WAL, B-tree, or VFS behavior.

## Dependency Closure

No new support component is needed. The slice reuses the existing
`SQLiteSelectSql` compound SELECT executor and lane-local row-array test harness.
