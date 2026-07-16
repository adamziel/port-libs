# real-upstream-corpus-select-core-dynamic-20260531T041706Z-0

Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectH.test`.

Ported section:

- `selectH-5.1`: `SELECT DISTINCT val1 FROM t1 UNION ALL SELECT val2 FROM t2`
- `selectH-5.2`: `SELECT count(1234) FROM (SELECT DISTINCT val1 FROM t1 UNION ALL SELECT val2 FROM t2)`

Lane movement:

- Added `SQLiteRealUpstreamSelectHDistinctUnionDerivedDynamicTest.php`.
- Focused PASS-line growth: `+1003`.
- Focused behavior assertions: `3507`.
- Mapped denominator movement: none; mapped inventory remains `1589 / 1589`.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectHDistinctUnionDerivedDynamicTest.php`
  - `1 test files, 3507 assertions, 0 failures`
  - `1003` focused PASS lines

Dependency closure:

- No new support component needed. The slice reuses native `SQLiteSelectSql` compound SELECT execution, derived table execution, DISTINCT row filtering, UNION ALL concatenation, and aggregate count handling.

Non-overlap:

- Avoids the parked SELECT regression handoffs by staying on upstream `selectH.test` section 5 only.
- Does not repeat accepted selectE/selectF compound collation/order coverage, select8 LIMIT/OFFSET coverage, select1 derived compound LIMIT/OFFSET coverage, grouped SELECT SQL text, expression ORDER BY, JSON table source/cursor behavior, WAL/VFS/B-tree clusters, or source-neutral cleanup surfaces.
