# real-upstream-corpus-select-core-dynamic-20260531T072823Z-0

Implemented a real upstream SELECT-core batch from hydrated SQLite
`/home/claude/port-libs/.upstream-cache/libsqlite/test/selectA.test`.

Owned upstream section:

- `selectA-2.31` through `selectA-2.36`
- `selectA-2.40`

Added `SQLiteRealUpstreamCorpusSelectCoreDynamicSelectAReversedUnionTest.php`
with canonical reversed-arm `UNION` merge-order assertions plus 1,000 dynamic
`LIMIT`/`OFFSET` windows over mixed NULL, numeric, text, blob, binary collation,
and nocase collation rows.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusSelectCoreDynamicSelectAReversedUnionTest.php`
- Result: `1 test files, 7051 assertions, 0 failures`
- PASS lines: 1009

Non-overlap:

- Avoids accepted union-all `selectA`, left-arm union `selectA`, `select9` set
  ops, `selectB` compound subquery, `selectD` parenthesized joins, `selectH`
  omit-unused, JSON table, WAL, B-tree, and VFS clusters.

Follow-up:

- `selectA-2.37` through `selectA-2.39` were attempted and expose a compound
  result-column binding gap for reversed-arm `ORDER BY c...`; they were left
  out of this passing throughput handoff for a focused behavior-fix slice.

Dependency closure:

- No new support component is needed; the batch uses existing
  `SQLiteSelectSql` and `SQLiteBlobValue` behavior.
